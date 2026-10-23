<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * The drivers' payroll list (section 6): every active driver with their
 * salary and the deductions authorized since the last list (shortages, F9).
 * HR prints or exports it for the payroll; closing the month marks the
 * deductions taken and clears the driver receivable (Dr payroll liabilities,
 * Cr driver receivables).
 */
final class Payroll
{
    private static function db(): PDO
    {
        return Database::connection();
    }

    /** F9 authorized: the amount goes on the driver's next payroll list. */
    public static function deductionAuthorized(array $request): void
    {
        if (empty($request['driver_id'])) {
            return;
        }
        $exists = self::db()->prepare('SELECT COUNT(*) FROM driver_deductions WHERE request_id = ?');
        $exists->execute([(int) $request['id']]);
        if ((int) $exists->fetchColumn() > 0) {
            return;
        }
        self::db()->prepare('INSERT INTO driver_deductions (company_id, driver_id, request_id, trip_id, description, amount, currency) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([(int) $request['company_id'], (int) $request['driver_id'], (int) $request['id'], $request['trip_id'] ?: null, mb_substr(RequestForms::label($request) . ' — ' . $request['title'], 0, 255), (float) $request['total_amount'], (string) $request['currency']]);
        $driver = Fleet::driver((int) $request['driver_id']);
        Notifier::forCompany((int) $request['company_id'], static function () use ($request, $driver): void {
            Notifier::toRole('finance', 'Deduction for the payroll', sprintf('%s: %s from %s on the next payroll.', RequestForms::label($request), Currency::format((float) $request['total_amount'], (string) $request['currency']), $driver['full_name'] ?? 'the driver'), 'payroll', 'info', 'form_requests', (string) $request['id']);
            if (!empty($driver['user_id'])) {
                Notifier::toUser((int) $driver['user_id'], 'Shortage deduction approved', sprintf('%s will be deducted on your next salary (%s).', Currency::format((float) $request['total_amount'], (string) $request['currency']), RequestForms::label($request)), 'my-trips', 'warning', 'form_requests', (string) $request['id']);
            }
        });
    }

    /**
     * The list for a month: active drivers (and those who left during it),
     * salary, deductions waiting, and net.
     *
     * @return list<array<string, mixed>>
     */
    public static function sheet(int $companyId, string $period): array
    {
        $start = $period . '-01';
        $end = date('Y-m-t', (int) strtotime($start));
        $run = self::run($companyId, $period);
        if ($run !== null && $run['status'] === 'closed') {
            return json_decode((string) $run['rows_json'], true) ?: [];
        }

        $drivers = self::db()->prepare(
            "SELECT d.id, d.full_name, d.national_id, d.passport_number, d.phone, d.payment_method, d.momo_number, d.momo_name, d.salary, d.salary_currency, d.status, d.hired_on, d.left_on
               FROM drivers d
              WHERE d.deleted_at IS NULL AND d.company_id = ? AND (d.hired_on IS NULL OR d.hired_on <= ?)
                AND (d.status <> 'left' OR d.left_on >= ?)
              ORDER BY d.full_name"
        );
        $drivers->execute([$companyId, $end, $start]);
        $deductions = self::db()->prepare("SELECT * FROM driver_deductions WHERE driver_id = ? AND company_id = ? AND status = 'pending' AND created_at <= ? ORDER BY id");

        $rows = [];
        foreach ($drivers->fetchAll(PDO::FETCH_ASSOC) as $driver) {
            $deductions->execute([(int) $driver['id'], $companyId, $end . ' 23:59:59']);
            $items = $deductions->fetchAll(PDO::FETCH_ASSOC);
            $currency = (string) ($driver['salary_currency'] ?: Company::baseCurrency($companyId));
            $byCurrency = [];
            foreach ($items as $item) {
                $byCurrency[$item['currency']] = ($byCurrency[$item['currency']] ?? 0) + (float) $item['amount'];
            }
            $salary = (float) ($driver['salary'] ?? 0);
            $rows[] = $driver + [
                'currency' => $currency,
                'deductions' => $items,
                'deducted' => $byCurrency,
                'net' => round($salary - ($byCurrency[$currency] ?? 0), 2),
                'other_currency' => array_diff_key($byCurrency, [$currency => true]) !== [],
            ];
        }

        return $rows;
    }

    public static function run(int $companyId, string $period): ?array
    {
        $statement = self::db()->prepare('SELECT * FROM payroll_runs WHERE company_id = ? AND period = ?');
        $statement->execute([$companyId, $period]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * HR closes the month: the deductions on the list are marked taken, and
     * the receivable they created is cleared against payroll liabilities.
     */
    public static function close(int $companyId, string $period): void
    {
        if (preg_match('/^\d{4}-\d{2}$/', $period) !== 1) {
            throw new \RuntimeException('Choose the month.');
        }
        if ($period > date('Y-m')) {
            throw new \RuntimeException('A month in the future cannot be closed.');
        }
        $run = self::run($companyId, $period);
        if ($run !== null && $run['status'] === 'closed') {
            throw new \RuntimeException('This month is already closed.');
        }
        $rows = self::sheet($companyId, $period);
        $db = self::db();
        $db->beginTransaction();
        try {
            if ($run === null) {
                $db->prepare('INSERT INTO payroll_runs (company_id, period) VALUES (?, ?)')->execute([$companyId, $period]);
                $runId = (int) $db->lastInsertId();
            } else {
                $runId = (int) $run['id'];
            }
            $totals = [];
            $mark = $db->prepare("UPDATE driver_deductions SET status = 'deducted', payroll_run_id = ? WHERE id = ? AND status = 'pending'");
            foreach ($rows as $row) {
                foreach ($row['deductions'] as $item) {
                    $mark->execute([$runId, (int) $item['id']]);
                    $totals[$item['currency']][] = ['account_driver' => $row['full_name'], 'amount' => (float) $item['amount'], 'trip_id' => $item['trip_id'], 'request_id' => $item['request_id']];
                }
            }
            $entryId = null;
            [$debit, $credit] = RequestPosting::accountsFor($companyId, 'payroll_deduction', null);
            foreach ($totals as $currency => $items) {
                $lines = [];
                foreach ($items as $item) {
                    $dims = ['trip_id' => $item['trip_id'], 'request_id' => $item['request_id']];
                    $lines[] = ['account' => $debit, 'debit' => $item['amount'], 'description' => 'Deducted on the ' . $period . ' payroll — ' . $item['account_driver']] + $dims;
                    $lines[] = ['account' => $credit, 'credit' => $item['amount'], 'description' => $item['account_driver']] + $dims;
                }
                $entryId = Ledger::post(date('Y-m-t', (int) strtotime($period . '-01')) > date('Y-m-d') ? date('Y-m-d') : date('Y-m-t', (int) strtotime($period . '-01')), 'Payroll deductions ' . $period, $lines, 'payroll', $runId, 'PAY-' . $period, null, (string) $currency, $companyId);
            }
            $db->prepare("UPDATE payroll_runs SET status = 'closed', rows_json = ?, closed_by = ?, closed_at = NOW(), entry_id = ? WHERE id = ?")
                ->execute([json_encode($rows), \current_user_id(), $entryId, $runId]);
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
        AuditLog::record('payroll.closed', 'payroll', $companyId . ':' . $period, sprintf('%d driver(s)', count($rows)));
    }
}
