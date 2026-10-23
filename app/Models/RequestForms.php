<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * The request forms F1-F15 and their approval chain (sections 8 and 9).
 *
 *   Prepared   the OM (or a driver, a supervisor) fills the form and sends it.
 *              It is locked, numbered, and their name and signature go on it.
 *   Verified   the Finance Manager checks it line by line; the books post.
 *   Authorized the MD reviews it; the company stamp goes on it. Final.
 *   Paid       the Cashier pays it in MTN or cash and records the batch.
 *
 * Rejected at verification, or sent back at authorization, it returns to the
 * preparer with the reason; sent back after verification, its entries are
 * reversed. Every version and every reason is kept. Nobody verifies or
 * authorizes their own request, and a signature is placed only by its owner
 * while signed in.
 */
final class RequestForms
{
    public const CHAINS = [
        'full' => 'Prepared → Verified (Finance) → Authorized (MD) → Paid (Cashier)',
        'finance_md' => 'Prepared → Authorized (MD)',
        'om_confirm' => 'Sent → Confirmed by the OM',
        'store' => 'Sent → Issued by the storekeeper',
        'supplier' => 'Sent → Accepted by the supplier',
        'none' => 'No approval',
    ];

    public const STEP_LABELS = [
        'verify' => 'Verify (Finance Manager)',
        'authorize' => 'Authorize and stamp (MD)',
        'pay' => 'Pay (Cashier)',
        'confirm' => 'Confirm (Operational Manager)',
    ];

    public const STATUS = [
        'draft' => 'Draft',
        'sent' => 'Waiting for verification',
        'verified' => 'Waiting for authorization',
        'authorized' => 'Authorized',
        'partly_paid' => 'Partly paid',
        'paid' => 'Paid',
        'returned' => 'Returned to preparer',
        'cancelled' => 'Cancelled',
        'closed' => 'Closed',
    ];

    /** The kinds of line a form carries; each maps to its accounts. */
    public const ITEMS = [
        'fuel' => 'Fuel', 'road_toll' => 'Road toll', 'mileage' => 'Mileage', 'part' => 'Spare part', 'tyre' => 'Tyre',
        'crane' => 'Crane lift', 'labour' => 'Labour', 'service' => 'Service / repair', 'support' => 'Driver support',
        'shortage' => 'Shortage', 'duty' => 'Duty / tax', 'shipping' => 'Shipping charge', 'transport' => 'Transport',
        'deduction' => 'Shortage deducted by the customer', 'vat' => 'VAT', 'other' => 'Other',
    ];

    private static function db(): PDO
    {
        return Database::connection();
    }

    // ----------------------------------------------------------------- read

    public static function formType(string $code): ?array
    {
        $statement = self::db()->prepare('SELECT * FROM form_types WHERE form_code = ?');
        $statement->execute([$code]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return array<string, string> code => "F1 Trip money" */
    public static function formOptions(bool $onlyMine = false): array
    {
        $options = [];
        foreach (self::db()->query('SELECT form_code, form_name, raised_by_roles FROM form_types WHERE is_active = 1 ORDER BY sort_order')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($onlyMine && !self::mayRaise($row)) {
                continue;
            }
            $options[(string) $row['form_code']] = $row['form_code'] . ' — ' . $row['form_name'];
        }

        return $options;
    }

    public static function mayRaise(array $formType): bool
    {
        if (\current_role() === 'super_admin') {
            return true;
        }
        $roles = array_filter(array_map('trim', explode(',', (string) ($formType['raised_by_roles'] ?? ''))));

        return $roles === [] || in_array(\current_role(), $roles, true);
    }

    public static function find(int $id, bool $scoped = true): ?array
    {
        $statement = self::db()->prepare(
            'SELECT r.*, ft.form_name, ft.title_on_pdf, ft.chain, ft.is_money, ft.goes_to_payment, ft.posts_on_verify,
                    co.company_name, co.short_name AS company_short, co.base_currency AS company_currency,
                    pu.full_name AS prepared_name, vu.full_name AS verified_name, au.full_name AS authorized_name, cu.full_name AS paid_name,
                    t.reference_code AS trip_code, v.plate_number, d.full_name AS driver_name, s.supplier_name, c.customer_name,
                    ro.route_code, ro.route_name, tc.short_name AS to_company
               FROM form_requests r
               INNER JOIN form_types ft ON ft.form_code = r.form_code
               LEFT JOIN companies co ON co.id = r.company_id
               LEFT JOIN companies tc ON tc.id = r.to_company_id
               LEFT JOIN users pu ON pu.id = r.prepared_by
               LEFT JOIN users vu ON vu.id = r.verified_by
               LEFT JOIN users au ON au.id = r.authorized_by
               LEFT JOIN users cu ON cu.id = r.paid_by
               LEFT JOIN trips t ON t.id = r.trip_id
               LEFT JOIN vehicles v ON v.id = r.vehicle_id
               LEFT JOIN drivers d ON d.id = r.driver_id
               LEFT JOIN suppliers s ON s.id = r.supplier_id
               LEFT JOIN customers c ON c.id = r.customer_id
               LEFT JOIN routes ro ON ro.id = r.route_id
              WHERE r.id = ? AND r.deleted_at IS NULL'
        );
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        // A sister company sees the requests sent to it as well as its own.
        if ($scoped && \is_logged_in() && \company_scope() !== null) {
            $mine = \company_scope();
            if (!in_array((int) $row['company_id'], $mine, true) && !in_array((int) ($row['to_company_id'] ?? 0), $mine, true)) {
                return null;
            }
        }
        $row['payload'] = json_decode((string) ($row['payload'] ?? ''), true) ?: [];

        return $row;
    }

    /** @return list<array<string, mixed>> */
    public static function lines(int $requestId): array
    {
        $statement = self::db()->prepare(
            'SELECT l.*, v.plate_number, tr.trailer_number, d.full_name AS driver_name, d.license_number, d.passport_number, d.national_id,
                    t.reference_code AS trip_code
               FROM form_request_lines l
               LEFT JOIN vehicles v ON v.id = l.vehicle_id
               LEFT JOIN trailers tr ON tr.id = l.trailer_id
               LEFT JOIN drivers d ON d.id = l.driver_id
               LEFT JOIN trips t ON t.id = l.trip_id
              WHERE l.request_id = ?
              ORDER BY COALESCE(l.group_no, l.line_no), l.line_no'
        );
        $statement->execute([$requestId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['extra'] = json_decode((string) ($row['extra'] ?? ''), true) ?: [];
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> who did what, oldest first */
    public static function events(int $requestId): array
    {
        $statement = self::db()->prepare(
            'SELECT e.*, u.full_name, ob.full_name AS on_behalf_name
               FROM form_request_events e
               LEFT JOIN users u ON u.id = e.user_id
               LEFT JOIN users ob ON ob.id = e.on_behalf_of
              WHERE e.request_id = ?
              ORDER BY e.id'
        );
        $statement->execute([$requestId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    // --------------------------------------------------------------- write

    /**
     * A new request, in draft unless $send says otherwise.
     *
     * @param array<string, mixed> $header
     * @param list<array<string, mixed>> $lines
     */
    public static function create(array $header, array $lines, bool $send = false): int
    {
        $code = (string) ($header['form_code'] ?? '');
        $type = self::formType($code);
        if ($type === null) {
            throw new \RuntimeException('Unknown form ' . $code . '.');
        }

        $companyId = (int) ($header['company_id'] ?? 0) ?: \current_company_id();
        $currency = strtoupper((string) ($header['currency'] ?? '')) ?: Company::baseCurrency($companyId);

        $db = self::db();
        $owning = !$db->inTransaction();
        if ($owning) {
            $db->beginTransaction();
        }
        try {
            $db->prepare(
                'INSERT INTO form_requests
                    (company_id, form_code, title, status, currency, route_id, trip_id, vehicle_id, driver_id, supplier_id, customer_id,
                     job_card_id, parent_request_id, to_company_id, period_from, period_to, needed_on, non_standard_reason, payload, notes, created_by)
                 VALUES (?, ?, ?, \'draft\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $companyId, $code, mb_substr((string) ($header['title'] ?? $type['form_name']), 0, 190), $currency,
                self::idOrNull($header['route_id'] ?? null), self::idOrNull($header['trip_id'] ?? null), self::idOrNull($header['vehicle_id'] ?? null),
                self::idOrNull($header['driver_id'] ?? null), self::idOrNull($header['supplier_id'] ?? null), self::idOrNull($header['customer_id'] ?? null),
                self::idOrNull($header['job_card_id'] ?? null), self::idOrNull($header['parent_request_id'] ?? null), self::idOrNull($header['to_company_id'] ?? null),
                ($header['period_from'] ?? '') ?: null, ($header['period_to'] ?? '') ?: null, ($header['needed_on'] ?? '') ?: null,
                trim((string) ($header['non_standard_reason'] ?? '')) ?: null,
                isset($header['payload']) ? json_encode($header['payload']) : null,
                trim((string) ($header['notes'] ?? '')) ?: null,
                \current_user_id(),
            ]);
            $id = (int) $db->lastInsertId();
            self::writeLines($id, $lines, $currency, $type);
            self::event($id, 1, 'created');
            if ($owning) {
                $db->commit();
            }
        } catch (\Throwable $exception) {
            if ($owning && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        AuditLog::record('request.created', 'form_requests', $code . ' #' . $id);

        if ($send) {
            self::send($id);
        }

        return $id;
    }

    /** Changes a draft, or a request that came back, before it is sent (again). */
    public static function update(int $id, array $header, array $lines): void
    {
        $request = self::find($id) ?? throw new \RuntimeException('That request does not exist.');
        if (!in_array($request['status'], ['draft', 'returned'], true)) {
            throw new \RuntimeException('Only a draft or a returned request can be changed: once sent it is locked.');
        }
        $type = self::formType((string) $request['form_code']);
        $currency = strtoupper((string) ($header['currency'] ?? $request['currency']));

        self::db()->prepare(
            'UPDATE form_requests SET title = ?, currency = ?, route_id = ?, supplier_id = ?, customer_id = ?, period_from = ?, period_to = ?,
                    needed_on = ?, non_standard_reason = ?, notes = ?, payload = COALESCE(?, payload) WHERE id = ?'
        )->execute([
            mb_substr((string) ($header['title'] ?? $request['title']), 0, 190), $currency,
            self::idOrNull($header['route_id'] ?? $request['route_id']), self::idOrNull($header['supplier_id'] ?? $request['supplier_id']),
            self::idOrNull($header['customer_id'] ?? $request['customer_id']),
            ($header['period_from'] ?? $request['period_from']) ?: null, ($header['period_to'] ?? $request['period_to']) ?: null,
            ($header['needed_on'] ?? $request['needed_on']) ?: null,
            trim((string) ($header['non_standard_reason'] ?? $request['non_standard_reason'] ?? '')) ?: null,
            trim((string) ($header['notes'] ?? $request['notes'] ?? '')) ?: null,
            isset($header['payload']) ? json_encode($header['payload']) : null,
            $id,
        ]);
        self::writeLines($id, $lines, $currency, $type);
        AuditLog::record('request.edited', 'form_requests', self::label($request));
    }

    private static function writeLines(int $id, array $lines, string $currency, ?array $type): void
    {
        $db = self::db();
        $db->prepare('DELETE FROM form_request_lines WHERE request_id = ?')->execute([$id]);
        $insert = $db->prepare(
            'INSERT INTO form_request_lines
                (request_id, line_no, group_no, trip_id, vehicle_id, trailer_id, driver_id, item_code, item_id, description, quantity, unit, unit_price, amount,
                 standard_quantity, standard_amount, payee_type, payee_id, payee_name, payment_method, momo_number, momo_name, debit_account, extra, pay_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $total = 0.0;
        $quantity = 0.0;
        $lineNo = 0;
        foreach ($lines as $line) {
            $description = trim((string) ($line['description'] ?? ''));
            $item = (string) ($line['item_code'] ?? 'other');
            if ($description === '' && $item !== '' && isset(self::ITEMS[$item])) {
                $description = self::ITEMS[$item];
            }
            if ($description === '' && (float) ($line['amount'] ?? 0) == 0.0 && (float) ($line['quantity'] ?? 0) == 0.0) {
                continue;
            }
            $qty = self::num($line['quantity'] ?? 1);
            $price = self::num($line['unit_price'] ?? 0);
            $amount = array_key_exists('amount', $line) && $line['amount'] !== '' && $line['amount'] !== null
                ? round(self::num($line['amount']), 2)
                : round($qty * $price, 2);
            $payeeType = (string) ($line['payee_type'] ?? 'none');
            $method = (string) ($line['payment_method'] ?? 'none');
            $payable = (int) ($type['is_money'] ?? 0) === 1 && (int) ($type['goes_to_payment'] ?? 0) === 1 && $payeeType !== 'none' && $amount > 0;

            $insert->execute([
                $id, ++$lineNo, isset($line['group_no']) ? (int) $line['group_no'] : null,
                self::idOrNull($line['trip_id'] ?? null), self::idOrNull($line['vehicle_id'] ?? null), self::idOrNull($line['trailer_id'] ?? null),
                self::idOrNull($line['driver_id'] ?? null), $item ?: 'other', self::idOrNull($line['item_id'] ?? null),
                mb_substr($description, 0, 255), $qty, ($line['unit'] ?? '') ?: null, $price, $amount,
                isset($line['standard_quantity']) && $line['standard_quantity'] !== '' ? self::num($line['standard_quantity']) : null,
                isset($line['standard_amount']) && $line['standard_amount'] !== '' ? self::num($line['standard_amount']) : null,
                in_array($payeeType, ['driver', 'supplier', 'employee', 'company', 'other', 'none'], true) ? $payeeType : 'none',
                self::idOrNull($line['payee_id'] ?? null), trim((string) ($line['payee_name'] ?? '')) ?: null,
                in_array($method, ['momo', 'cash', 'bank', 'none'], true) ? $method : 'none',
                trim((string) ($line['momo_number'] ?? '')) ?: null, trim((string) ($line['momo_name'] ?? '')) ?: null,
                trim((string) ($line['debit_account'] ?? '')) ?: null,
                !empty($line['extra']) ? json_encode($line['extra']) : null,
                $payable ? 'unpaid' : 'not_payable',
            ]);
            $total += $amount;
            if (in_array($item, ['fuel'], true)) {
                $quantity += $qty;
            }
        }

        $db->prepare('UPDATE form_requests SET total_amount = ?, total_quantity = ? WHERE id = ?')
            ->execute([round($total, 2), $quantity > 0 ? $quantity : null, $id]);
    }

    // --------------------------------------------------------------- chain

    /** The step a request is waiting at, or null when nobody needs to act. */
    public static function pendingStep(array $request): ?string
    {
        $chain = (string) ($request['chain'] ?? 'full');
        $status = (string) $request['status'];

        return match (true) {
            $status === 'sent' && $chain === 'full' => 'verify',
            $status === 'sent' && $chain === 'finance_md' => 'authorize',
            $status === 'verified' => 'authorize',
            $status === 'sent' && $chain === 'om_confirm' => 'confirm',
            in_array($status, ['authorized', 'partly_paid'], true) && (int) $request['goes_to_payment'] === 1 => 'pay',
            default => null,
        };
    }

    /** What must be right before a request may leave the preparer. @return list<string> */
    public static function problems(array $request, array $lines): array
    {
        $problems = [];
        $type = self::formType((string) $request['form_code']) ?? [];
        if ($lines === []) {
            $problems[] = 'The form has no lines.';
        }

        $moneyLines = array_filter($lines, static fn (array $l): bool => (float) $l['amount'] > 0);
        if ((int) ($type['is_money'] ?? 0) === 1 && $moneyLines === [] && (float) ($request['total_quantity'] ?? 0) <= 0) {
            $problems[] = 'The form carries no amount.';
        }

        foreach ($lines as $line) {
            $label = trim(($line['plate_number'] ?? '') . ' ' . $line['description']);
            if (((float) $line['amount'] < 0 && $line['item_code'] !== 'deduction') || (float) $line['quantity'] < 0) {
                $problems[] = $label . ': amounts and quantities cannot be negative.';
            }
            if ($line['pay_status'] !== 'not_payable') {
                if (trim((string) $line['payee_name']) === '') {
                    $problems[] = $label . ': who is paid is missing.';
                }
                if ($line['payment_method'] === 'momo' && trim((string) $line['momo_number']) === '') {
                    $problems[] = $label . ': the MoMo number is missing.';
                }
                if ($line['payment_method'] === 'none') {
                    $problems[] = $label . ': say whether it is paid by MoMo, cash or bank.';
                }
            }
            $standardQty = $line['standard_quantity'];
            $standardAmount = $line['standard_amount'];
            $differs = ($standardQty !== null && abs((float) $standardQty - (float) $line['quantity']) > 0.004)
                || ($standardAmount !== null && abs((float) $standardAmount - (float) $line['amount']) > 0.004);
            if ($differs && trim((string) ($request['non_standard_reason'] ?? '')) === '') {
                $problems[] = $label . ' differs from the rate card: give the reason (detour, waiting, load) for Finance.';
            }
        }

        // Road toll and mileage are paid once per trip (control 5).
        if ($request['form_code'] === 'F1') {
            foreach ($lines as $line) {
                if (!in_array($line['item_code'], ['road_toll', 'mileage'], true) || empty($line['trip_id'])) {
                    continue;
                }
                $statement = self::db()->prepare(
                    "SELECT r.request_no FROM form_request_lines l INNER JOIN form_requests r ON r.id = l.request_id
                      WHERE l.trip_id = ? AND l.item_code = ? AND r.id <> ? AND r.deleted_at IS NULL
                        AND r.status NOT IN ('draft', 'returned', 'cancelled') LIMIT 1"
                );
                $statement->execute([(int) $line['trip_id'], $line['item_code'], (int) $request['id']]);
                $other = $statement->fetchColumn();
                if ($other !== false) {
                    $problems[] = sprintf('%s of trip %s is already on %s: it is paid once per trip.', self::ITEMS[$line['item_code']], $line['trip_code'], $other);
                }
            }
        }

        return array_values(array_unique($problems));
    }

    /** Prepared: locked, numbered, signed and sent to the first approver. */
    public static function send(int $id): array
    {
        $request = self::find($id) ?? throw new \RuntimeException('That request does not exist.');
        if (!in_array($request['status'], ['draft', 'returned'], true)) {
            throw new \RuntimeException('This request has already been sent.');
        }
        $problems = self::problems($request, self::lines($id));
        if ($problems !== []) {
            throw new \RuntimeException(implode(' ', $problems));
        }

        $db = self::db();
        $version = $request['status'] === 'returned' ? (int) $request['version'] + 1 : (int) $request['version'];
        $number = $request['request_no'];
        if ($number === null || $number === '') {
            $type = self::formType((string) $request['form_code']);
            $number = Numbering::next((int) $request['company_id'], 'form:' . $request['form_code'], (string) $type['number_format'], (string) $type['number_reset']);
        }

        $chain = (string) $request['chain'];
        $status = $chain === 'none' ? 'closed' : 'sent';
        $db->prepare(
            "UPDATE form_requests SET status = ?, request_no = ?, version = ?, prepared_by = ?, prepared_at = NOW(),
                    returned_reason = NULL, waiting_since = NOW(), reminded_at = NULL WHERE id = ?"
        )->execute([$status, $number, $version, \current_user_id(), $id]);
        self::event($id, $version, $request['status'] === 'returned' ? 'resent' : 'prepared', null, true);

        $request = self::find($id, false);
        AuditLog::record('request.sent', 'form_requests', self::label($request));
        self::tellNext($request);

        return $request;
    }

    /** May the signed-in person sign this step of this request? Returns the reason when not. */
    public static function refusal(array $request, string $step): ?string
    {
        $expected = self::pendingStep($request);
        if ($expected !== $step) {
            return 'This request is not waiting for that step.';
        }
        $userId = \current_user_id();
        if ($userId === null) {
            return 'Sign in first.';
        }
        // Control 10: nobody verifies or authorizes their own request.
        if (in_array($step, ['verify', 'authorize'], true) && (int) $request['prepared_by'] === $userId) {
            return 'You prepared this request, so you cannot ' . ($step === 'verify' ? 'verify' : 'authorize') . ' it.';
        }
        if ($step === 'authorize' && (int) ($request['verified_by'] ?? 0) === $userId) {
            return 'You verified this request; another person must authorize it.';
        }
        if (self::actingFor($request, $step) === false) {
            return 'This step is signed by ' . (Approver::roleLabel((int) $request['company_id'], $step)) . '.';
        }
        if (in_array($step, ['verify', 'authorize'], true) && Settings::int('signature_required_to_approve', 1) === 1 && Signature::path($userId) === null) {
            return 'Upload your signature on My profile first: it is printed on the request when you sign.';
        }

        return null;
    }

    /**
     * Whether the signed-in person may sign the step: false when not, null
     * when in their own right, or the id of the approver they stand in for.
     */
    public static function actingFor(array $request, string $step): int|false|null
    {
        return Approver::actingFor(self::approvingCompany($request, $step), $step, (int) \current_user_id(), \current_role(), (string) ($request['waiting_since'] ?? ''));
    }

    /**
     * The company whose approver signs a step. A sister-company request (F11)
     * is confirmed by the company it was sent to, not the one that raised it;
     * every other step is signed in the request's own company.
     */
    public static function approvingCompany(array $request, string $step): int
    {
        if ($step === 'confirm' && (string) $request['form_code'] === 'F11' && !empty($request['to_company_id'])) {
            return (int) $request['to_company_id'];
        }

        return (int) $request['company_id'];
    }

    public static function verify(int $id, string $comment = ''): array
    {
        $request = self::find($id) ?? throw new \RuntimeException('That request does not exist.');
        if (($refusal = self::refusal($request, 'verify')) !== null) {
            throw new \RuntimeException($refusal);
        }

        $db = self::db();
        $owning = !$db->inTransaction();
        if ($owning) {
            $db->beginTransaction();
        }
        try {
            $db->prepare("UPDATE form_requests SET status = 'verified', verified_by = ?, verified_at = NOW(), waiting_since = NOW(), reminded_at = NULL WHERE id = ?")
                ->execute([\current_user_id(), $id]);
            // Verification posts the books (section 14).
            if ((int) $request['posts_on_verify'] === 1 && (int) $request['is_money'] === 1) {
                RequestPosting::verified($id);
            }
            self::event($id, (int) $request['version'], 'verified', $comment, true, self::actingFor($request, 'verify') ?: null);
            if ($owning) {
                $db->commit();
            }
        } catch (\Throwable $exception) {
            if ($owning && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        $request = self::find($id, false);
        AuditLog::record('request.verified', 'form_requests', self::label($request), $comment ?: null);
        self::tellNext($request);
        self::tellPreparer($request, 'Request verified', sprintf('%s was verified by %s and is with the MD.', self::label($request), \current_user_name()), 'info');

        return $request;
    }

    public static function authorize(int $id, string $comment = ''): array
    {
        $request = self::find($id) ?? throw new \RuntimeException('That request does not exist.');
        if (($refusal = self::refusal($request, 'authorize')) !== null) {
            throw new \RuntimeException($refusal);
        }

        $db = self::db();
        $owning = !$db->inTransaction();
        if ($owning) {
            $db->beginTransaction();
        }
        try {
            $final = (int) $request['goes_to_payment'] === 1 ? 'authorized' : ((int) $request['is_money'] === 1 && $request['chain'] === 'full' ? 'authorized' : 'closed');
            $db->prepare('UPDATE form_requests SET status = ?, authorized_by = ?, authorized_at = NOW(), waiting_since = NOW(), reminded_at = NULL WHERE id = ?')
                ->execute([$final, \current_user_id(), $id]);
            // A form that posts at authorization (an invoice prepared by Finance).
            if ($request['chain'] === 'finance_md' && (int) $request['posts_on_verify'] === 1 && (int) $request['is_money'] === 1) {
                RequestPosting::verified($id);
            }
            self::event($id, (int) $request['version'], 'authorized', $comment, true, self::actingFor($request, 'authorize') ?: null, true);
            if ($owning) {
                $db->commit();
            }
        } catch (\Throwable $exception) {
            if ($owning && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        $request = self::find($id, false);
        AuditLog::record('request.authorized', 'form_requests', self::label($request), $comment ?: null);
        RequestFlow::authorized($request);
        self::tellNext($request);
        self::tellPreparer($request, 'Request authorized', sprintf('%s was authorized and stamped by %s.', self::label($request), \current_user_name()), 'success');

        return $request;
    }

    /** The OM confirms a delivery note, a handover or a sister-company request. */
    public static function confirm(int $id, string $comment = ''): array
    {
        $request = self::find($id) ?? throw new \RuntimeException('That request does not exist.');
        if (($refusal = self::refusal($request, 'confirm')) !== null) {
            throw new \RuntimeException($refusal);
        }
        self::db()->prepare("UPDATE form_requests SET status = 'closed', authorized_by = ?, authorized_at = NOW(), waiting_since = NULL WHERE id = ?")
            ->execute([\current_user_id(), $id]);
        self::event($id, (int) $request['version'], 'confirmed', $comment, true);
        $request = self::find($id, false);
        AuditLog::record('request.confirmed', 'form_requests', self::label($request), $comment ?: null);
        RequestFlow::confirmed($request);
        self::tellPreparer($request, 'Confirmed', sprintf('%s was confirmed by %s.', self::label($request), \current_user_name()), 'success');

        return $request;
    }

    /**
     * Rejected by Finance, or sent back by the MD: it goes back to the
     * preparer with the reason. Entries already posted are reversed.
     */
    public static function sendBack(int $id, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \RuntimeException('Give the reason: it goes back to the person who prepared the request.');
        }
        $request = self::find($id) ?? throw new \RuntimeException('That request does not exist.');
        $step = self::pendingStep($request);
        if (!in_array($step, ['verify', 'authorize', 'confirm'], true)) {
            throw new \RuntimeException('Only a request waiting for a signature can be sent back.');
        }
        if (self::actingFor($request, $step) === false) {
            throw new \RuntimeException('This step is signed by ' . Approver::roleLabel((int) $request['company_id'], $step) . '.');
        }

        $db = self::db();
        $owning = !$db->inTransaction();
        if ($owning) {
            $db->beginTransaction();
        }
        try {
            if ($request['status'] === 'verified') {
                RequestPosting::reverse($id, 'Sent back by ' . \current_user_name() . ': ' . $reason);
            }
            $db->prepare("UPDATE form_requests SET status = 'returned', returned_reason = ?, verified_by = NULL, verified_at = NULL, waiting_since = NOW() WHERE id = ?")
                ->execute([$reason, $id]);
            self::event($id, (int) $request['version'], 'returned', $reason, true);
            if ($owning) {
                $db->commit();
            }
        } catch (\Throwable $exception) {
            if ($owning && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        $request = self::find($id, false);
        AuditLog::record('request.returned', 'form_requests', self::label($request), $reason);
        RequestFlow::returned($request);
        self::tellPreparer($request, $step === 'verify' ? 'Request rejected' : 'Request sent back', sprintf('%s came back from %s: %s', self::label($request), \current_user_name(), $reason), 'warning');

        return $request;
    }

    public static function cancel(int $id, string $reason): void
    {
        $request = self::find($id) ?? throw new \RuntimeException('That request does not exist.');
        if (!in_array($request['status'], ['draft', 'returned'], true)) {
            throw new \RuntimeException('Only a draft or a returned request can be cancelled.');
        }
        if (trim($reason) === '') {
            throw new \RuntimeException('Give the reason for cancelling.');
        }
        self::db()->prepare("UPDATE form_requests SET status = 'cancelled', returned_reason = ? WHERE id = ?")->execute([trim($reason), $id]);
        self::event($id, (int) $request['version'], 'cancelled', $reason, false);
        AuditLog::record('request.cancelled', 'form_requests', self::label($request), $reason);
        RequestFlow::cancelled($request);
    }

    // ------------------------------------------------------------ payments

    /** A payment recorded against lines: the request moves to partly paid or paid. */
    public static function refreshPaid(int $id): void
    {
        $db = self::db();
        $statement = $db->prepare(
            "SELECT COALESCE(SUM(paid_amount), 0) AS paid,
                    SUM(pay_status IN ('unpaid', 'in_batch', 'failed')) AS open_lines,
                    SUM(pay_status = 'paid') AS paid_lines
               FROM form_request_lines WHERE request_id = ?"
        );
        $statement->execute([$id]);
        $state = $statement->fetch(PDO::FETCH_ASSOC);
        $status = (int) $state['open_lines'] === 0 ? 'paid' : ((int) $state['paid_lines'] > 0 ? 'partly_paid' : 'authorized');
        $db->prepare(
            "UPDATE form_requests SET paid_amount = ?, status = IF(status IN ('authorized', 'partly_paid', 'paid'), ?, status),
                    paid_by = IF(? = 'paid', ?, paid_by), paid_at = IF(? = 'paid', NOW(), paid_at) WHERE id = ?"
        )->execute([(float) $state['paid'], $status, $status, \current_user_id(), $status, $id]);

        if ($status === 'paid') {
            $request = self::find($id, false);
            if ($request !== null && !self::hasEvent($id, 'paid')) {
                self::event($id, (int) $request['version'], 'paid', null, true);
                RequestFlow::paid($request);
            }
        }
    }

    private static function hasEvent(int $id, string $step): bool
    {
        $statement = self::db()->prepare('SELECT COUNT(*) FROM form_request_events WHERE request_id = ? AND step = ?');
        $statement->execute([$id, $step]);

        return (int) $statement->fetchColumn() > 0;
    }

    // ------------------------------------------------------------- inboxes

    /**
     * What waits for the signed-in person: the requests at a step they sign.
     *
     * @return list<array<string, mixed>>
     */
    public static function inbox(int $limit = 100): array
    {
        $rows = self::db()->query(
            "SELECT r.id FROM form_requests r INNER JOIN form_types ft ON ft.form_code = r.form_code
              WHERE r.deleted_at IS NULL AND r.status IN ('sent', 'verified', 'authorized', 'partly_paid')
              ORDER BY r.waiting_since, r.id LIMIT 500"
        )->fetchAll(PDO::FETCH_COLUMN);

        $mine = [];
        foreach ($rows as $id) {
            $request = self::find((int) $id);
            if ($request === null) {
                continue;
            }
            $step = self::pendingStep($request);
            if ($step === null || $step === 'pay' && !\can_view('payment_batches')) {
                continue;
            }
            if (self::actingFor($request, $step) === false) {
                continue;
            }
            if (in_array($step, ['verify', 'authorize'], true) && (int) $request['prepared_by'] === (int) \current_user_id()) {
                continue;
            }
            $request['step'] = $step;
            $mine[] = $request;
            if (count($mine) >= $limit) {
                break;
            }
        }

        return $mine;
    }

    public static function inboxCount(): int
    {
        try {
            return count(self::inbox(200));
        } catch (\Throwable) {
            return 0;
        }
    }

    // ------------------------------------------------------------- helpers

    public static function label(?array $request): string
    {
        if ($request === null) {
            return '';
        }

        return trim($request['form_code'] . ' ' . ($request['request_no'] ?: '#' . $request['id']));
    }

    /**
     * Writes one step of the history, with the signer's signature as it is
     * now (and the company stamp at authorization), so a later change of
     * signature never changes what an old document shows.
     */
    public static function event(int $id, int $version, string $step, ?string $reason = null, bool $sign = false, ?int $onBehalfOf = null, bool $stamp = false): void
    {
        $userId = \current_user_id();
        $signature = null;
        if ($sign && $userId !== null) {
            $statement = self::db()->prepare('SELECT signature_file FROM users WHERE id = ?');
            $statement->execute([$userId]);
            $signature = $statement->fetchColumn() ?: null;
        }
        $stampFile = null;
        if ($stamp) {
            $statement = self::db()->prepare('SELECT co.stamp_file FROM form_requests r INNER JOIN companies co ON co.id = r.company_id WHERE r.id = ?');
            $statement->execute([$id]);
            $stampFile = $statement->fetchColumn() ?: null;
        }
        $snapshot = null;
        if (in_array($step, ['prepared', 'resent', 'verified', 'authorized'], true)) {
            $statement = self::db()->prepare('SELECT * FROM form_requests WHERE id = ?');
            $statement->execute([$id]);
            $snapshot = json_encode(['request' => $statement->fetch(PDO::FETCH_ASSOC), 'lines' => self::lines($id)]);
        }

        self::db()->prepare(
            'INSERT INTO form_request_events (request_id, version, step, user_id, on_behalf_of, role_key, reason, signature_file, stamp_file, snapshot, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $id, $version, $step, $userId, $onBehalfOf, \is_logged_in() ? \current_role() : null,
            $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            $signature, $stampFile, $snapshot,
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? (PHP_SAPI === 'cli' ? 'console' : '')), 0, 255) ?: null,
        ]);
    }

    /** Tells whoever signs the next step that it is their turn. */
    private static function tellNext(array $request): void
    {
        $step = self::pendingStep($request);
        if ($step === null) {
            return;
        }
        $titles = [
            'verify' => 'Request to verify',
            'authorize' => 'Request to authorize',
            'pay' => 'Request to pay',
            'confirm' => 'To confirm',
        ];
        $message = sprintf(
            '%s — %s%s, prepared by %s.',
            self::label($request),
            $request['title'],
            (int) $request['is_money'] === 1 ? ': ' . Currency::format((float) $request['total_amount'], (string) $request['currency']) : '',
            $request['prepared_name'] ?? 'someone'
        );
        Approver::notify(self::approvingCompany($request, $step), $step, $titles[$step], $message, (int) $request['id']);
    }

    private static function tellPreparer(array $request, string $title, string $message, string $severity): void
    {
        $preparer = (int) ($request['prepared_by'] ?? 0) ?: (int) ($request['created_by'] ?? 0);
        if ($preparer > 0 && $preparer !== (int) \current_user_id()) {
            Notifier::forCompany((int) $request['company_id'], static fn () => Notifier::toUser($preparer, $title, $message, 'form_requests', $severity, 'form_requests', (string) $request['id']));
        }
    }

    private static function idOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' || (int) $value <= 0 ? null : (int) $value;
    }

    private static function num(mixed $value): float
    {
        return (float) str_replace([',', ' '], '', (string) $value);
    }
}
