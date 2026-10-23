<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * What the request chain writes in the books (section 14).
 *
 *   Verified   Dr the expense of each line (road toll, mileage, fuel, tyres...)
 *              Cr the payable (drivers payable, supplier payable)
 *   Sent back  the verification entry is reversed, never deleted
 *   Paid       Dr the payable  Cr the MoMo account, cash box or bank
 *
 * Which accounts a line uses comes from the ledger mapping Finance keeps, then
 * the form's default accounts. Every entry carries the company, the request
 * and, line by line, the truck and the trip.
 */
final class RequestPosting
{
    private static function db(): PDO
    {
        return Database::connection();
    }

    /** [debit, credit] account codes for one kind of line in one company. */
    public static function accountsFor(int $companyId, string $itemCode, ?array $formType = null, ?string $lineDebit = null): array
    {
        $statement = self::db()->prepare(
            'SELECT debit_account, credit_account FROM ledger_mappings WHERE item_code = ? AND (company_id = ? OR company_id IS NULL)
              ORDER BY company_id IS NULL LIMIT 1'
        );
        $statement->execute([$itemCode, $companyId]);
        $map = $statement->fetch(PDO::FETCH_ASSOC) ?: null;

        $debit = $lineDebit ?: ($map['debit_account'] ?? null) ?: ($formType['debit_account'] ?? null) ?: '6900';
        $credit = ($map['credit_account'] ?? null) ?: ($formType['credit_account'] ?? null) ?: '2000';

        return [(string) $debit, (string) $credit];
    }

    /** The entry made when Finance verifies. Returns its id, or null when there is nothing to post. */
    public static function verified(int $requestId): ?int
    {
        $request = RequestForms::find($requestId, false);
        if ($request === null) {
            return null;
        }
        $type = RequestForms::formType((string) $request['form_code']);
        $companyId = (int) $request['company_id'];
        // What the store received against the order: a supplier invoice (F12)
        // clears that from "goods received not invoiced" instead of booking
        // the cost a second time. It is one pool, drained across the invoice's
        // stock lines; only the excess (a higher price, or a line not
        // received) is expensed.
        $received = $request['form_code'] === 'F12' ? Store::receivedValues($request['parent_request_id'] ? (int) $request['parent_request_id'] : null) : ['by_line' => [], 'by_item' => []];
        $receivedPool = array_sum($received['by_item']);
        $clearing = $receivedPool > 0 ? self::accountsFor($companyId, 'goods_received', null)[1] : null;

        $lines = [];
        foreach (RequestForms::lines($requestId) as $line) {
            $amount = round((float) $line['amount'], 2);
            // A deduction is the one line that carries a negative amount (the
            // customer paid less); its mapping already says which way it posts.
            if ($amount < 0 && (string) $line['item_code'] === 'deduction') {
                $amount = abs($amount);
            }
            if ($amount <= 0) {
                continue;
            }
            [$debit, $credit] = self::accountsFor($companyId, (string) $line['item_code'], $type, $line['debit_account'] ?: null);
            $who = trim((string) ($line['plate_number'] ?? '') . ' ' . (string) ($line['payee_name'] ?? ''));
            $dimensions = ['vehicle_id' => $line['vehicle_id'], 'trip_id' => $line['trip_id'] ?: $request['trip_id'], 'request_id' => $requestId];
            if ($request['form_code'] === 'F12') {
                // A supplier invoice is always owed to the supplier.
                $credit = (string) ($type['credit_account'] ?? '2000') ?: '2000';
                // A stock line (part or tyre) clears from the received pool; a
                // labour or service line on the invoice is an ordinary cost.
                $isStock = in_array((string) $line['item_code'], ['part', 'tyre'], true);
                $clear = $clearing !== null && !$line['debit_account'] && $isStock ? round(min($amount, $receivedPool), 2) : 0.0;
                if ($clear > 0) {
                    $receivedPool -= $clear;
                    $lines[] = ['account' => $clearing, 'debit' => $clear, 'description' => 'Received into the store — ' . $line['description']] + $dimensions;
                    if ($amount - $clear > 0.004) {
                        // Invoiced above the order price: the difference is a cost.
                        $lines[] = ['account' => $debit, 'debit' => round($amount - $clear, 2), 'description' => 'Price above the order — ' . $line['description']] + $dimensions;
                    }
                    $lines[] = ['account' => $credit, 'credit' => $amount, 'description' => trim(($line['payee_name'] ?: $request['supplier_name']) . ' — ' . $line['description'])] + $dimensions;
                    continue;
                }
            }
            $lines[] = ['account' => $debit, 'debit' => $amount, 'description' => trim($line['description'] . ' ' . $who)] + $dimensions;
            $lines[] = ['account' => $credit, 'credit' => $amount, 'description' => trim(($line['payee_name'] ?: $request['supplier_name'] ?: $request['form_name']) . ' — ' . $line['description'])] + $dimensions;
        }
        if ($lines === []) {
            return null;
        }

        return Ledger::post(
            date('Y-m-d'),
            sprintf('%s %s', $request['form_code'], $request['title']),
            $lines,
            self::source($request),
            $requestId,
            (string) $request['request_no'],
            $request['trip_code'] ?? null,
            (string) $request['currency'],
            $companyId
        );
    }

    /** Sent back after verification: the entry is reversed automatically. */
    public static function reverse(int $requestId, string $reason): void
    {
        $request = RequestForms::find($requestId, false);
        if ($request === null) {
            return;
        }
        $statement = self::db()->prepare("SELECT id FROM gl_journal_entries WHERE source_type = ? AND source_id = ? AND status = 'posted' AND deleted_at IS NULL");
        $statement->execute([self::source($request), $requestId]);
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $entryId) {
            Ledger::reverse((int) $entryId, $reason);
        }
    }

    /**
     * The payment entry for one payee line of a batch: the payable is cleared
     * against the account the money left from.
     */
    public static function paid(int $batchLineId): ?int
    {
        $statement = self::db()->prepare(
            'SELECT bl.*, b.batch_no, b.mtn_batch_no, b.method, b.payment_method_id, b.company_id, r.form_code, r.request_no, r.title
               FROM payment_batch_lines bl
               INNER JOIN payment_batches b ON b.id = bl.batch_id
               INNER JOIN form_requests r ON r.id = bl.request_id
              WHERE bl.id = ?'
        );
        $statement->execute([$batchLineId]);
        $line = $statement->fetch(PDO::FETCH_ASSOC);
        if ($line === false || $line['status'] !== 'paid') {
            return null;
        }

        $type = RequestForms::formType((string) $line['form_code']);
        // The payable the verification credited, line by line, for this payee.
        $lines = [];
        $items = self::db()->prepare(
            'SELECT item_code, amount, debit_account, vehicle_id, trip_id FROM form_request_lines
              WHERE request_id = ? AND COALESCE(payee_id, 0) = ? AND payee_name = ? AND pay_status = \'paid\''
        );
        $items->execute([(int) $line['request_id'], (int) $line['payee_id'], (string) $line['payee_name']]);
        $owed = [];
        foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
            [, $credit] = self::accountsFor((int) $line['company_id'], (string) $item['item_code'], $type, $item['debit_account'] ?: null);
            $owed[$credit] = ($owed[$credit] ?? 0) + (float) $item['amount'];
        }
        if ($owed === []) {
            $owed[(string) ($type['credit_account'] ?? '2000')] = (float) $line['amount'];
        }
        $total = round(array_sum($owed), 2);
        $scale = $total > 0 ? (float) $line['amount'] / $total : 1.0;
        foreach ($owed as $account => $amount) {
            $lines[] = ['account' => (string) $account, 'debit' => round($amount * $scale, 2), 'description' => $line['payee_name'], 'request_id' => (int) $line['request_id']];
        }
        $lines[] = ['account' => self::moneyAccount($line), 'credit' => round((float) $line['amount'], 2), 'description' => sprintf('%s %s', strtoupper((string) $line['method']), $line['mtn_batch_no'] ?: $line['batch_no']), 'request_id' => (int) $line['request_id']];

        // Rounding of a scaled split must not unbalance the entry.
        $debits = array_sum(array_column($lines, 'debit'));
        $difference = round((float) $line['amount'] - $debits, 2);
        if (abs($difference) > 0) {
            $lines[0]['debit'] = round($lines[0]['debit'] + $difference, 2);
        }

        $entryId = Ledger::post(
            (string) ($line['paid_at'] ?: date('Y-m-d')),
            sprintf('Paid %s — %s %s', $line['payee_name'], $line['form_code'], $line['request_no']),
            $lines,
            'request_payment',
            $batchLineId,
            (string) $line['batch_no'],
            (string) ($line['mtn_batch_no'] ?: $line['request_no']),
            (string) $line['currency'],
            (int) $line['company_id']
        );

        self::exchangeDifference($batchLineId, (int) $line['request_id'], $owed === [] ? null : (string) array_key_first($owed), (float) $line['amount'], $entryId);

        return $entryId;
    }

    /**
     * A foreign-currency request paid at a different rate than it was verified
     * at: the difference in the company's base currency is an exchange gain or
     * loss, posted on its own (section 14).
     */
    private static function exchangeDifference(int $batchLineId, int $requestId, ?string $payable, float $amount, int $paymentEntryId): void
    {
        $request = RequestForms::find($requestId, false);
        if ($request === null || $payable === null) {
            return;
        }
        $statement = self::db()->prepare(
            "SELECT exchange_rate, base_currency, currency FROM gl_journal_entries
              WHERE source_type = ? AND source_id = ? AND status = 'posted' AND deleted_at IS NULL ORDER BY id DESC LIMIT 1"
        );
        $statement->execute([self::source($request), $requestId]);
        $verified = $statement->fetch(PDO::FETCH_ASSOC);
        $statement = self::db()->prepare('SELECT exchange_rate, base_currency, currency FROM gl_journal_entries WHERE id = ?');
        $statement->execute([$paymentEntryId]);
        $paid = $statement->fetch(PDO::FETCH_ASSOC);
        if ($verified === false || $paid === false || $paid['currency'] === $paid['base_currency']
            || $verified['exchange_rate'] === null || $paid['exchange_rate'] === null) {
            return;
        }

        $difference = round($amount * ((float) $paid['exchange_rate'] - (float) $verified['exchange_rate']), 2);
        if (abs($difference) < 0.01) {
            return;
        }
        $fx = '6950';
        $lines = $difference > 0
            // Paid more in base currency than was booked: a loss.
            ? [['account' => $fx, 'debit' => $difference, 'description' => 'Exchange loss', 'request_id' => $requestId],
               ['account' => $payable, 'credit' => $difference, 'description' => 'Exchange difference on payment', 'request_id' => $requestId]]
            : [['account' => $payable, 'debit' => -$difference, 'description' => 'Exchange difference on payment', 'request_id' => $requestId],
               ['account' => $fx, 'credit' => -$difference, 'description' => 'Exchange gain', 'request_id' => $requestId]];

        Ledger::post(
            date('Y-m-d'),
            sprintf('Exchange %s on %s %s (%s at %s, booked at %s)', $difference > 0 ? 'loss' : 'gain', $request['form_code'], $request['request_no'], $paid['currency'], $paid['exchange_rate'], $verified['exchange_rate']),
            $lines,
            'fx_difference',
            $batchLineId,
            (string) $request['request_no'],
            null,
            (string) $paid['base_currency'],
            (int) $request['company_id']
        );
    }

    /** The account a batch's money left from: its payment method, else MoMo / cash / bank. */
    private static function moneyAccount(array $line): string
    {
        if (!empty($line['payment_method_id'])) {
            $statement = self::db()->prepare('SELECT g.account_code FROM payment_methods p INNER JOIN gl_accounts g ON g.id = p.gl_account_id WHERE p.id = ?');
            $statement->execute([(int) $line['payment_method_id']]);
            $code = $statement->fetchColumn();
            if ($code !== false && $code !== null) {
                return (string) $code;
            }
        }

        return match ((string) $line['method']) {
            'cash' => PaymentMethod::accountCode('cash', '1010'),
            'bank' => PaymentMethod::accountCode('bank_transfer', '1020'),
            default => PaymentMethod::accountCode('mobile_money', '1030'),
        };
    }

    /** Each version of a request has its own entry, so a resent request never rewrites a reversed one. */
    private static function source(array $request): string
    {
        return 'request_v' . (int) $request['version'];
    }
}
