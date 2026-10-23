<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Payments (section 10). Money still leaves through MTN MoMo bulk payment or
 * the cash office; everything before and after happens here.
 *
 *   To pay       authorized request lines not yet paid, one row per payee
 *   Build batch  the Cashier ticks rows of one currency and method; the
 *                system numbers the batch and lays out the file for MTN
 *   Record       the MTN batch number and proof; failed lines stay open
 *   Cash         paid at the cash office; the driver confirms with a PIN or
 *                signs on the Cashier's screen; a receipt is printed
 *   Confirm      each payee taps "Money received"; a trip cannot depart
 *                until its driver has
 */
final class Payments
{
    private static function db(): PDO
    {
        return Database::connection();
    }

    /**
     * Authorized, unpaid lines, one row per request and payee.
     *
     * @return list<array<string, mixed>>
     */
    public static function toPay(?string $currency = null, ?string $method = null): array
    {
        $company = Company::condition('form_requests', 'r.company_id');
        $sql = "SELECT r.id AS request_id, r.request_no, r.form_code, r.title, r.currency, r.company_id, r.authorized_at,
                       l.payee_type, l.payee_id, l.payee_name, l.payment_method, l.momo_number, l.momo_name,
                       SUM(l.amount - l.paid_amount) AS amount, COUNT(*) AS line_count,
                       GROUP_CONCAT(DISTINCT v.plate_number SEPARATOR ', ') AS trucks,
                       MAX(l.pay_status = 'failed') AS has_failed,
                       GROUP_CONCAT(l.id) AS line_ids
                  FROM form_request_lines l
                  INNER JOIN form_requests r ON r.id = l.request_id
                  LEFT JOIN vehicles v ON v.id = l.vehicle_id
                 WHERE r.deleted_at IS NULL AND r.status IN ('authorized', 'partly_paid')
                   AND l.pay_status IN ('unpaid', 'failed')"
            . ($company !== null ? " AND {$company}" : '')
            . ($currency !== null && $currency !== '' ? ' AND r.currency = ' . self::db()->quote(strtoupper($currency)) : '')
            . ($method !== null && $method !== '' ? ' AND l.payment_method = ' . self::db()->quote($method) : '')
            . ' GROUP BY r.id, l.payee_type, l.payee_id, l.payee_name, l.payment_method, l.momo_number, l.momo_name
                ORDER BY r.authorized_at, r.id, l.payee_name';

        $rows = self::db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['key'] = self::payeeKey($row);
        }

        return $rows;
    }

    /** One payee of one request, as a key the To pay list can tick. */
    public static function payeeKey(array $row): string
    {
        return $row['request_id'] . ':' . substr(md5(implode('|', [$row['payee_type'], (int) $row['payee_id'], $row['payee_name'], $row['payment_method'], $row['momo_number']])), 0, 12);
    }

    /**
     * The Cashier ticks the rows to pay together and builds the batch.
     *
     * @param list<string> $keys from toPay()
     */
    public static function build(array $keys, ?int $paymentMethodId = null, string $notes = ''): int
    {
        $rows = array_values(array_filter(self::toPay(), static fn (array $row): bool => in_array($row['key'], $keys, true)));
        if ($rows === []) {
            throw new \RuntimeException('Tick at least one line on the To pay list.');
        }
        $currencies = array_unique(array_column($rows, 'currency'));
        $methods = array_unique(array_column($rows, 'payment_method'));
        $companies = array_unique(array_column($rows, 'company_id'));
        if (count($currencies) > 1) {
            throw new \RuntimeException('A batch is paid in one currency. Build one batch per currency.');
        }
        if (count($methods) > 1) {
            throw new \RuntimeException('MoMo, cash and bank lines go in separate batches.');
        }
        if (count($companies) > 1) {
            throw new \RuntimeException('Each company pays from its own account: build one batch per company.');
        }
        foreach ($rows as $row) {
            if ($row['payment_method'] === 'momo' && trim((string) $row['momo_number']) === '') {
                throw new \RuntimeException(sprintf('%s has no MoMo number.', $row['payee_name']));
            }
        }

        $companyId = (int) $companies[0];
        $method = (string) $methods[0];
        $db = self::db();
        $owning = !$db->inTransaction();
        if ($owning) {
            $db->beginTransaction();
        }
        try {
            $batchNo = Numbering::next($companyId, 'payment_batch', Settings::get('batch_number_format', 'BATCH-{YEAR}-{MONTH}-{SEQ}'), 'month');
            $total = round(array_sum(array_map(static fn (array $r): float => (float) $r['amount'], $rows)), 2);
            $db->prepare(
                "INSERT INTO payment_batches (company_id, batch_no, method, payment_method_id, currency, total_amount, line_count, status, built_by, built_at, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'built', ?, NOW(), ?)"
            )->execute([$companyId, $batchNo, in_array($method, ['momo', 'cash', 'bank'], true) ? $method : 'momo', $paymentMethodId, $currencies[0], $total, count($rows), \current_user_id(), trim($notes) ?: null]);
            $batchId = (int) $db->lastInsertId();

            $insert = $db->prepare(
                'INSERT INTO payment_batch_lines (batch_id, request_id, payee_type, payee_id, payee_name, momo_number, momo_name, amount, currency)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($rows as $row) {
                $insert->execute([$batchId, (int) $row['request_id'], $row['payee_type'], $row['payee_id'], $row['payee_name'], $row['momo_number'], $row['momo_name'], round((float) $row['amount'], 2), $row['currency']]);
                $ids = implode(',', array_map('intval', explode(',', (string) $row['line_ids'])));
                $db->exec("UPDATE form_request_lines SET pay_status = 'in_batch' WHERE id IN ({$ids})");
            }
            if ($owning) {
                $db->commit();
            }
        } catch (\Throwable $exception) {
            if ($owning && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        AuditLog::record('payment.batch_built', 'payment_batches', $batchNo, null, ['total' => $total, 'currency' => $currencies[0], 'lines' => count($rows)]);

        return $batchId;
    }

    public static function batch(int $id): ?array
    {
        $statement = self::db()->prepare(
            'SELECT b.*, bu.full_name AS built_name, ru.full_name AS recorded_name, co.company_name, pm.method_name
               FROM payment_batches b
               LEFT JOIN users bu ON bu.id = b.built_by
               LEFT JOIN users ru ON ru.id = b.recorded_by
               LEFT JOIN companies co ON co.id = b.company_id
               LEFT JOIN payment_methods pm ON pm.id = b.payment_method_id
              WHERE b.id = ?'
        );
        $statement->execute([$id]);
        $batch = $statement->fetch(PDO::FETCH_ASSOC);
        if ($batch === false || (\company_scope() !== null && !in_array((int) $batch['company_id'], \company_scope(), true))) {
            return null;
        }

        return $batch;
    }

    /** @return list<array<string, mixed>> */
    public static function batchLines(int $batchId): array
    {
        $statement = self::db()->prepare(
            'SELECT bl.*, r.request_no, r.form_code, r.title
               FROM payment_batch_lines bl INNER JOIN form_requests r ON r.id = bl.request_id
              WHERE bl.batch_id = ? ORDER BY bl.id'
        );
        $statement->execute([$batchId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * The payment list laid out for MTN's bulk upload, in the columns set
     * under Settings > Payments, with the registered MoMo name for checking.
     */
    public static function mtnFile(int $batchId): string
    {
        $batch = self::batch($batchId) ?? throw new \RuntimeException('That batch does not exist.');
        $columns = array_filter(array_map('trim', explode(',', Settings::get('mtn_file_columns', 'msisdn,amount,name,reference'))));
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $columns);
        foreach (self::batchLines($batchId) as $line) {
            if ($line['status'] === 'paid') {
                continue;
            }
            $row = [];
            foreach ($columns as $column) {
                $row[] = match ($column) {
                    'msisdn', 'number', 'phone' => self::msisdn((string) $line['momo_number']),
                    'amount' => number_format((float) $line['amount'], (float) $line['amount'] == floor((float) $line['amount']) ? 0 : 2, '.', ''),
                    'name', 'registered_name' => (string) ($line['momo_name'] ?: $line['payee_name']),
                    'payee' => (string) $line['payee_name'],
                    'reference', 'ref' => $batch['batch_no'] . ' ' . $line['request_no'],
                    'currency' => (string) $line['currency'],
                    default => '',
                };
            }
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** A MoMo number as MTN wants it, from the format in settings (250XXXXXXXXX). */
    public static function msisdn(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number) ?? '';
        $format = Settings::get('mtn_number_format', '250XXXXXXXXX');
        $prefix = preg_replace('/X+$/', '', $format) ?? '';
        $length = max(1, strlen($format) - strlen($prefix));
        // The subscriber part is the last digits, whatever prefix was typed (0788…, +250788…).
        $local = substr($digits, -min($length, strlen($digits)));

        return $prefix . str_pad($local, $length, '0', STR_PAD_LEFT);
    }

    /**
     * Back in the LMS after MTN: the batch number, the proof, and the lines
     * that failed. The rest are paid, posted, and their payees told.
     *
     * @param array<int, string> $failed batch line id => reason
     */
    public static function record(int $batchId, string $mtnBatchNo, ?string $proofFile, array $failed = []): void
    {
        $batch = self::batch($batchId) ?? throw new \RuntimeException('That batch does not exist.');
        if ($batch['status'] !== 'built') {
            throw new \RuntimeException('This batch was already recorded.');
        }
        if ($batch['method'] !== 'cash' && trim($mtnBatchNo) === '') {
            throw new \RuntimeException('Enter the MTN batch number.');
        }
        if ($batch['method'] !== 'cash' && ($proofFile === null || $proofFile === '')) {
            throw new \RuntimeException('Upload the MTN proof or statement.');
        }
        foreach ($failed as $lineId => $reason) {
            if (trim((string) $reason) === '') {
                throw new \RuntimeException('Give the reason for each failed line, so it is not paid twice or forgotten.');
            }
        }

        $db = self::db();
        $owning = !$db->inTransaction();
        if ($owning) {
            $db->beginTransaction();
        }
        $paidLines = [];
        try {
            $db->prepare("UPDATE payment_batches SET mtn_batch_no = ?, proof_file = COALESCE(?, proof_file), status = ?, recorded_by = ?, recorded_at = NOW() WHERE id = ?")
                ->execute([trim($mtnBatchNo) ?: null, $proofFile, $failed === [] ? 'recorded' : 'partly_failed', \current_user_id(), $batchId]);

            foreach (self::batchLines($batchId) as $line) {
                if (isset($failed[(int) $line['id']])) {
                    $db->prepare("UPDATE payment_batch_lines SET status = 'failed', failure_reason = ? WHERE id = ?")->execute([trim((string) $failed[(int) $line['id']]), (int) $line['id']]);
                    self::requestLines($line, 'failed');
                    continue;
                }
                $db->prepare("UPDATE payment_batch_lines SET status = 'paid', paid_at = NOW() WHERE id = ?")->execute([(int) $line['id']]);
                self::requestLines($line, 'paid');
                $paidLines[] = (int) $line['id'];
            }
            foreach ($paidLines as $lineId) {
                RequestPosting::paid($lineId);
            }
            foreach (array_unique(array_column(self::batchLines($batchId), 'request_id')) as $requestId) {
                RequestForms::refreshPaid((int) $requestId);
            }
            if ($owning) {
                $db->commit();
            }
        } catch (\Throwable $exception) {
            if ($owning && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        AuditLog::record('payment.batch_recorded', 'payment_batches', (string) $batch['batch_no'], null, ['mtn' => $mtnBatchNo, 'failed' => count($failed)]);
        foreach ($paidLines as $lineId) {
            self::tellPayee($lineId);
        }
    }

    /**
     * Cash at the cash office: the Cashier pays one line and the driver
     * confirms there and then, with their PIN or a signature on the screen.
     */
    public static function payCash(int $batchLineId, string $pin = '', string $signatureData = ''): string
    {
        $line = self::line($batchLineId) ?? throw new \RuntimeException('That payment line does not exist.');
        if ($line['method'] !== 'cash') {
            throw new \RuntimeException('Only a cash batch is paid at the cash office.');
        }
        if ($line['status'] === 'paid') {
            throw new \RuntimeException('This line is already paid.');
        }

        $via = null;
        $signatureFile = null;
        if ($pin !== '') {
            if ($line['payee_type'] !== 'driver' || empty($line['payee_id'])) {
                throw new \RuntimeException('Only a driver confirms with a PIN.');
            }
            $statement = self::db()->prepare('SELECT cash_pin_hash FROM drivers WHERE id = ?');
            $statement->execute([(int) $line['payee_id']]);
            $hash = (string) ($statement->fetchColumn() ?: '');
            if ($hash === '' || !password_verify($pin, $hash)) {
                throw new \RuntimeException('That PIN is not right.');
            }
            $via = 'pin';
        } elseif ($signatureData !== '') {
            $signatureFile = self::storeSignature($signatureData, 'cash-' . $batchLineId);
            $via = 'signature';
        } else {
            throw new \RuntimeException('The person paid confirms with their PIN or signs on the screen.');
        }

        $db = self::db();
        $owning = !$db->inTransaction();
        if ($owning) {
            $db->beginTransaction();
        }
        try {
            $receipt = Numbering::next((int) $line['company_id'], 'cash_receipt', 'CR-{YEAR}-{SEQ4}', 'year');
            $db->prepare("UPDATE payment_batch_lines SET status = 'paid', paid_at = NOW(), receipt_no = ?, confirmed_at = NOW(), confirmed_via = ?, confirmation_signature = ? WHERE id = ?")
                ->execute([$receipt, $via, $signatureFile, $batchLineId]);
            self::requestLines($line, 'paid');
            RequestPosting::paid($batchLineId);
            RequestForms::refreshPaid((int) $line['request_id']);
            $open = (int) self::db()->query("SELECT COUNT(*) FROM payment_batch_lines WHERE batch_id = " . (int) $line['batch_id'] . " AND status <> 'paid'")->fetchColumn();
            if ($open === 0) {
                $db->prepare("UPDATE payment_batches SET status = 'recorded', recorded_by = ?, recorded_at = NOW() WHERE id = ?")->execute([\current_user_id(), (int) $line['batch_id']]);
            }
            if ($owning) {
                $db->commit();
            }
        } catch (\Throwable $exception) {
            if ($owning && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
        AuditLog::record('payment.cash_paid', 'payment_batches', (string) $line['batch_no'], null, ['receipt' => $receipt, 'payee' => $line['payee_name'], 'via' => $via]);
        TripLife::receiptConfirmed((int) $line['request_id']);

        return $receipt;
    }

    /** "Money received", tapped by the payee in the app. */
    public static function confirmReceipt(int $batchLineId, ?int $driverId): void
    {
        $line = self::line($batchLineId) ?? throw new \RuntimeException('That payment does not exist.');
        if ($driverId === null || $line['payee_type'] !== 'driver' || (int) $line['payee_id'] !== $driverId) {
            throw new \RuntimeException('Only the person paid can confirm it.');
        }
        if ($line['status'] !== 'paid') {
            throw new \RuntimeException('This payment has not been sent yet.');
        }
        if ($line['confirmed_at'] !== null) {
            return;
        }
        self::db()->prepare("UPDATE payment_batch_lines SET confirmed_at = NOW(), confirmed_via = 'app' WHERE id = ?")->execute([$batchLineId]);
        AuditLog::record('payment.confirmed', 'payment_batches', (string) $line['batch_no'], null, ['payee' => $line['payee_name']]);
        TripLife::receiptConfirmed((int) $line['request_id']);
    }

    /** Payments sent to a driver, newest first, with whether they confirmed. @return list<array<string, mixed>> */
    public static function forDriver(int $driverId, int $limit = 30): array
    {
        $statement = self::db()->prepare(
            "SELECT bl.*, b.batch_no, b.mtn_batch_no, b.method, r.request_no, r.form_code, r.title, r.trip_id
               FROM payment_batch_lines bl
               INNER JOIN payment_batches b ON b.id = bl.batch_id
               INNER JOIN form_requests r ON r.id = bl.request_id
              WHERE bl.payee_type = 'driver' AND bl.payee_id = ?
              ORDER BY bl.id DESC LIMIT " . max(1, $limit)
        );
        $statement->execute([$driverId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** The cash box of a company in a currency: what came in less what went out, from the books. */
    public static function cashBox(int $companyId, string $currency): float
    {
        $account = Ledger::idOf(PaymentMethod::accountCode('cash', '1010'));
        if ($account === null) {
            return 0.0;
        }
        $statement = self::db()->prepare(
            "SELECT COALESCE(SUM(l.debit - l.credit), 0) FROM gl_journal_lines l
               INNER JOIN gl_journal_entries e ON e.id = l.entry_id
              WHERE l.account_id = ? AND e.company_id = ? AND e.currency = ? AND e.deleted_at IS NULL"
        );
        $statement->execute([$account, $companyId, strtoupper($currency)]);

        return (float) $statement->fetchColumn();
    }

    // ------------------------------------------------------------- helpers

    private static function line(int $batchLineId): ?array
    {
        $statement = self::db()->prepare(
            'SELECT bl.*, b.batch_no, b.method, b.company_id FROM payment_batch_lines bl INNER JOIN payment_batches b ON b.id = bl.batch_id WHERE bl.id = ?'
        );
        $statement->execute([$batchLineId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** The request lines behind one payee line follow it: paid, or back on the To pay list. */
    private static function requestLines(array $batchLine, string $outcome): void
    {
        $statement = self::db()->prepare(
            "UPDATE form_request_lines SET pay_status = ?, paid_amount = IF(? = 'paid', amount, paid_amount)
              WHERE request_id = ? AND COALESCE(payee_id, 0) = ? AND payee_name = ? AND pay_status = 'in_batch'"
        );
        $statement->execute([$outcome, $outcome, (int) $batchLine['request_id'], (int) $batchLine['payee_id'], (string) $batchLine['payee_name']]);
    }

    private static function tellPayee(int $batchLineId): void
    {
        $line = self::line($batchLineId);
        if ($line === null) {
            return;
        }
        $message = sprintf('%s has been sent to you (%s). Tap "Money received" once it has arrived.', Currency::format((float) $line['amount'], (string) $line['currency']), $line['batch_no']);
        if ($line['payee_type'] === 'driver' && !empty($line['payee_id'])) {
            $user = self::db()->prepare('SELECT user_id FROM drivers WHERE id = ?');
            $user->execute([(int) $line['payee_id']]);
            $userId = $user->fetchColumn();
            Notifier::forCompany((int) $line['company_id'], static fn () => Notifier::toUser($userId ? (int) $userId : null, 'Money sent', $message, 'my-trips', 'success'));
        }
        if ($line['payee_type'] === 'supplier' && !empty($line['payee_id'])) {
            $users = self::db()->prepare("SELECT id FROM users WHERE supplier_id = ? AND status = 'active'");
            $users->execute([(int) $line['payee_id']]);
            foreach ($users->fetchAll(PDO::FETCH_COLUMN) as $userId) {
                Notifier::toUser((int) $userId, 'Payment sent', $message, 'portal', 'success');
            }
        }
        Notifier::forCompany((int) $line['company_id'], static fn () => Notifier::bellToRole('logistics_manager', 'Payment recorded', sprintf('%s paid %s (%s).', $line['payee_name'], Currency::format((float) $line['amount'], (string) $line['currency']), $line['batch_no']), 'payment_batches', 'info'));
    }

    private static function storeSignature(string $dataUrl, string $name): string
    {
        if (preg_match('#^data:image/png;base64,(.+)$#', $dataUrl, $match) !== 1) {
            throw new \RuntimeException('The signature could not be read.');
        }
        $bytes = base64_decode($match[1], true);
        if ($bytes === false || strlen($bytes) > 1024 * 1024 || @getimagesizefromstring($bytes) === false) {
            throw new \RuntimeException('The signature could not be read.');
        }
        $directory = \Support\Mailer::uploadsRoot() . '/cash_receipts';
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
        $file = $name . '-' . bin2hex(random_bytes(4)) . '.png';
        file_put_contents($directory . '/' . $file, $bytes);

        return 'cash_receipts/' . $file;
    }
}
