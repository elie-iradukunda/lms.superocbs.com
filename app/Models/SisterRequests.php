<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Sister-company requests (ERI specification, section 17, F11).
 *
 * A sister company raises F11 for its own truck and sends it to another
 * company in the group. The receiving company confirms it, which opens a job
 * card and a stores issue note there (section 12). When the job closes, an
 * inter-company invoice is made: a receivable (1170) and income (4300) in the
 * company that did the work, and an expense on the truck with a payable (2310)
 * in the company that asked. Each company sees only its own books.
 */
final class SisterRequests
{
    private const RECEIVABLE = '1170';
    private const INCOME = '4300';
    private const PAYABLE = '2310';
    private const REPAIRS = '6000';

    private static function db(): PDO
    {
        return Database::connection();
    }

    /**
     * The receiving OM confirmed F11: a job card and issue note open in the
     * receiving company for the sister's truck, and the forwarded role is told.
     */
    public static function confirmed(array $request): void
    {
        $receiving = (int) ($request['to_company_id'] ?? 0);
        if ($receiving <= 0) {
            return;
        }
        // Already opened (confirm is idempotent).
        $has = self::db()->prepare('SELECT COUNT(*) FROM maintenance_orders WHERE request_id = ? AND deleted_at IS NULL');
        $has->execute([(int) $request['id']]);
        if ((int) $has->fetchColumn() > 0) {
            return;
        }
        $lines = RequestForms::lines((int) $request['id']);
        $jobId = Garage::openJob($receiving, [
            'request_id' => (int) $request['id'],
            'vehicle_id' => $request['vehicle_id'] ? (int) $request['vehicle_id'] : null,
            'trailer_id' => $request['trailer_id'] ?? null,
            'owner_company_id' => (int) $request['company_id'],
            'title' => sprintf('%s (for %s)', $request['title'], $request['company_short'] ?? 'sister company'),
            'complaint' => (string) ($request['notes'] ?? ''),
            'currency' => Company::baseCurrency($receiving),
            'lines' => $lines,
        ]);

        $payload = is_array($request['payload'] ?? null) ? $request['payload'] : (json_decode((string) ($request['payload'] ?? ''), true) ?: []);
        $forward = (string) ($payload['forward_role'] ?? 'warehouse_manager');
        Notifier::forCompany($receiving, static fn () => Notifier::toRole(
            in_array($forward, ['warehouse_manager', 'finance', 'logistics_manager', 'management'], true) ? $forward : 'warehouse_manager',
            'Sister-company job',
            sprintf('%s from %s: a job card is open. Issue the parts and do the work; the cost is invoiced back.', RequestForms::label($request), $request['company_name'] ?? ''),
            'garage',
            'info',
            'maintenance',
            (string) $jobId
        ));
    }

    /**
     * The job for a sister truck closed: the parts issued (plus any markup)
     * become the inter-company invoice, posted on both companies' books.
     */
    public static function jobClosed(int $jobId): void
    {
        $job = Garage::job($jobId);
        if ($job === null || empty($job['owner_company_id']) || (int) $job['owner_company_id'] === (int) $job['company_id']) {
            return;
        }
        $exists = self::db()->prepare('SELECT COUNT(*) FROM intercompany_invoices WHERE job_card_id = ?');
        $exists->execute([$jobId]);
        if ((int) $exists->fetchColumn() > 0) {
            return;
        }

        $doing = (int) $job['company_id'];
        $asking = (int) $job['owner_company_id'];
        $parts = round((float) self::db()->query("SELECT COALESCE(SUM(line_total), 0) FROM maintenance_parts WHERE maintenance_id = {$jobId}")->fetchColumn(), 2);
        if ($parts <= 0) {
            return;
        }
        $markup = max(0.0, Settings::float('intercompany_markup_percent', 0));
        $charged = round($parts * (1 + $markup / 100), 2);
        // Both companies keep their books in their own base currency; the
        // invoice is raised in the doing company's currency.
        $currency = Company::baseCurrency($doing);
        $plate = (string) ($job['plate_number'] ?? 'the truck');

        $db = self::db();
        $owning = !$db->inTransaction();
        if ($owning) {
            $db->beginTransaction();
        }
        try {
            $number = Numbering::next($doing, 'intercompany', Settings::get('intercompany_invoice_format', 'ICI-{YEAR}-{SEQ4}'), 'year');

            // The company that did the work: owed by the sister, income earned.
            $fromEntry = Ledger::post(date('Y-m-d'), sprintf('Inter-company %s — work on %s', $number, $plate), [
                ['account' => self::RECEIVABLE, 'debit' => $charged, 'description' => ($job['owner_company'] ?? 'Sister company') . ' — ' . $job['work_order_code'], 'vehicle_id' => $job['vehicle_id']],
                ['account' => self::INCOME, 'credit' => $charged, 'description' => 'Work on ' . $plate, 'vehicle_id' => $job['vehicle_id']],
            ], 'intercompany_out', $jobId, $number, $plate, $currency, $doing);

            // The company that asked: the repair sits on its truck, owed to the sister.
            $toRate = Currency::crossRate($currency, Company::baseCurrency($asking), date('Y-m-d')) ?? 1.0;
            $toAmount = round($charged * $toRate, 2);
            $toEntry = Ledger::post(date('Y-m-d'), sprintf('Inter-company %s — %s did work on %s', $number, $job['owner_company'] ?? '', $plate), [
                ['account' => self::REPAIRS, 'debit' => $toAmount, 'description' => 'Work by a sister company on ' . $plate, 'vehicle_id' => $job['vehicle_id']],
                ['account' => self::PAYABLE, 'credit' => $toAmount, 'description' => 'Owed to a sister company — ' . $number, 'vehicle_id' => $job['vehicle_id']],
            ], 'intercompany_in', $jobId, $number, $plate, Company::baseCurrency($asking), $asking);

            $db->prepare(
                'INSERT INTO intercompany_invoices (invoice_no, from_company_id, to_company_id, request_id, job_card_id, vehicle_id, currency, parts_amount, labour_amount, total_amount, from_entry_id, to_entry_id, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)'
            )->execute([$number, $doing, $asking, $job['request_id'], $jobId, $job['vehicle_id'], $currency, $parts, $charged, $fromEntry, $toEntry, \current_user_id()]);

            if ($owning) {
                $db->commit();
            }
        } catch (\Throwable $exception) {
            if ($owning && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        AuditLog::record('intercompany.invoiced', 'intercompany_invoices', $number, $plate, ['from' => $doing, 'to' => $asking, 'total' => $charged]);
        Notifier::forCompany($asking, static fn () => Notifier::toRoles(['finance', 'management'], 'Inter-company invoice', sprintf('%s from %s for work on %s: %s to settle.', $number, $job['short_name'] ?? 'a sister company', $plate, Currency::format(round($charged * (Currency::crossRate($currency, Company::baseCurrency($asking), date('Y-m-d')) ?? 1.0), 2), Company::baseCurrency($asking))), 'intercompany', 'warning'));
    }

    /** @return list<array<string, mixed>> inter-company invoices touching the company in view */
    public static function invoices(): array
    {
        $company = \current_company_id();
        $where = $company > 0 ? "WHERE (i.from_company_id = {$company} OR i.to_company_id = {$company})" : '';

        return self::db()->query(
            "SELECT i.*, fc.short_name AS from_name, tc.short_name AS to_name, v.plate_number
               FROM intercompany_invoices i
               LEFT JOIN companies fc ON fc.id = i.from_company_id
               LEFT JOIN companies tc ON tc.id = i.to_company_id
               LEFT JOIN vehicles v ON v.id = i.vehicle_id
               {$where} ORDER BY i.id DESC LIMIT 200"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
