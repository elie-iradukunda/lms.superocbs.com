<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * What the company owes and what it has paid on somebody else's behalf.
 *
 *   Supplier bill approved   Dr each line's account (+ VAT reclaimable)   Cr Accounts payable
 *   Supplier paid            Dr Accounts payable                          Cr Bank, cash or wallet
 *   Disbursement paid        Dr Disbursements recoverable (1150)          Cr Bank, cash or wallet
 *   Disbursement invoiced    (on the invoice) Dr Receivable               Cr 1150, no VAT
 *   Disbursement written off Dr Other expenses                            Cr 1150
 *
 * A bill linked to a goods receipt, a finished work order or a border crossing
 * that the ledger already carries as a payable is not posted a second time:
 * the bill is then the paper that proves the amount, and paying it clears the
 * payable that already exists.
 */
final class Payables
{
    private const PAYABLE = '2000';
    private const VAT = '2100';
    private const OTHER_EXPENSE = '6900';
    public const RECOVERABLE = '1150';

    public const BILL_STATUS = ['draft' => 'Draft', 'approved' => 'Approved', 'partially_paid' => 'Partially paid', 'paid' => 'Paid', 'cancelled' => 'Cancelled'];
    public const DISBURSEMENT_STATUS = ['pending' => 'Not yet invoiced', 'invoiced' => 'On an invoice', 'recovered' => 'Recovered', 'written_off' => 'Written off'];

    private static function db(): PDO
    {
        return Database::connection();
    }

    // ---------------------------------------------------------------- workflow

    public static function guard(string $module, string $action, array $record): ?string
    {
        if ($module === 'supplier_bills' && $action === 'approve') {
            self::recalculateBill((int) $record['id']);
            $total = (float) self::scalar('SELECT total_amount FROM supplier_bills WHERE id = ?', [(int) $record['id']]);
            if ($total <= 0) {
                return 'Add at least one line to the bill before approving it.';
            }
        }

        if ($module === 'supplier_bills' && $action === 'reject' && (float) $record['amount_paid'] > 0) {
            return 'Part of this bill has already been paid. Delete those supplier payments first.';
        }

        return null;
    }

    public static function applied(string $module, string $action, array $record, string $reason, ?int $actor): string
    {
        $id = (int) $record['id'];

        if ($module === 'supplier_bills' && $action === 'approve') {
            self::db()->prepare('UPDATE supplier_bills SET approved_by = ?, approved_at = NOW() WHERE id = ?')->execute([$actor, $id]);
            self::recalculateBill($id);
            self::postBill($id);

            return sprintf('Bill %s is approved and now counts as money owed to the supplier.', $record['bill_no']);
        }

        if ($module === 'supplier_bills' && $action === 'reject') {
            Posting::unpost('supplier_bill', $id);

            return sprintf('Bill %s was cancelled.', $record['bill_no']);
        }

        if ($module === 'disbursements' && $action === 'write_off') {
            self::postWriteOff($id);

            return sprintf('Disbursement %s was written off as a cost.', $record['disbursement_code']);
        }

        return 'Done.';
    }

    // ------------------------------------------------------------- arithmetic

    /** Totals from the lines, paid from the payments, and the status that follows. */
    public static function recalculateBill(int $billId): void
    {
        $bill = self::row('SELECT tax_rate, status FROM supplier_bills WHERE id = ?', [$billId]);
        if ($bill === null) {
            return;
        }

        $subtotal = round((float) self::scalar('SELECT COALESCE(SUM(line_total), 0) FROM supplier_bill_lines WHERE bill_id = ?', [$billId]), 2);
        $tax = round($subtotal * ((float) $bill['tax_rate'] / 100), 2);
        $total = round($subtotal + $tax, 2);
        $paid = round((float) self::scalar('SELECT COALESCE(SUM(amount), 0) FROM supplier_payments WHERE bill_id = ? AND deleted_at IS NULL', [$billId]), 2);

        $status = (string) $bill['status'];
        if (!in_array($status, ['draft', 'cancelled'], true)) {
            $status = match (true) {
                $paid >= $total && $total > 0 => 'paid',
                $paid > 0 => 'partially_paid',
                default => 'approved',
            };
        }

        self::db()->prepare('UPDATE supplier_bills SET subtotal = ?, tax_amount = ?, total_amount = ?, amount_paid = ?, status = ? WHERE id = ?')
            ->execute([$subtotal, $tax, $total, $paid, $status, $billId]);
    }

    /**
     * Keeps disbursements in step with the invoice lines that carry them: a line
     * removed hands the disbursement back to "not yet invoiced", a paid invoice
     * marks it recovered.
     */
    public static function syncInvoiceDisbursements(int $invoiceId): void
    {
        try {
            $db = self::db();
            $invoice = self::row('SELECT status, deleted_at FROM invoices WHERE id = ?', [$invoiceId]);
            $live = $invoice !== null && $invoice['deleted_at'] === null && $invoice['status'] !== 'cancelled';

            // Released: no longer on a line of this invoice, or the invoice is gone.
            $release = $live
                ? "UPDATE disbursements SET status = 'pending', invoice_id = NULL
                    WHERE invoice_id = ? AND status IN ('invoiced','recovered')
                      AND id NOT IN (SELECT disbursement_id FROM invoice_lines WHERE invoice_id = ? AND disbursement_id IS NOT NULL)"
                : "UPDATE disbursements SET status = 'pending', invoice_id = NULL WHERE invoice_id = ? AND status IN ('invoiced','recovered') AND ? > 0";
            $db->prepare($release)->execute([$invoiceId, $invoiceId]);

            if (!$live) {
                return;
            }

            $state = $invoice['status'] === 'paid' ? 'recovered' : 'invoiced';
            $db->prepare(
                "UPDATE disbursements d
                    INNER JOIN invoice_lines l ON l.disbursement_id = d.id AND l.invoice_id = ?
                    SET d.invoice_id = ?, d.status = ?
                  WHERE d.status IN ('pending','invoiced','recovered')"
            )->execute([$invoiceId, $invoiceId, $state]);
        } catch (\Throwable) {
            // Before the disbursement tables exist there is nothing to keep in step.
        }
    }

    /**
     * Adds every disbursement still waiting for this customer (on this trip, when
     * the invoice names one) to a draft invoice, at cost and without VAT.
     *
     * @return int how many were added
     */
    public static function addToInvoice(int $invoiceId): int
    {
        $invoice = self::row('SELECT * FROM invoices WHERE id = ? AND deleted_at IS NULL', [$invoiceId]);
        if ($invoice === null) {
            throw new \RuntimeException('That invoice no longer exists.');
        }
        if ($invoice['status'] !== 'draft') {
            throw new \RuntimeException('Disbursements can only be added to a draft invoice.');
        }

        $sql = "SELECT id, disbursement_code, category, description, amount FROM disbursements
                 WHERE deleted_at IS NULL AND status = 'pending' AND customer_id = ? AND currency = ?";
        $parameters = [(int) $invoice['customer_id'], (string) $invoice['currency']];
        if (!empty($invoice['journey_id'])) {
            // Duty paid on any leg of the journey belongs on its invoice.
            $sql .= ' AND (trip_id IN (SELECT id FROM trips WHERE journey_id = ?) OR trip_id IS NULL)';
            $parameters[] = (int) $invoice['journey_id'];
        } elseif (!empty($invoice['trip_id'])) {
            $sql .= ' AND (trip_id = ? OR trip_id IS NULL)';
            $parameters[] = (int) $invoice['trip_id'];
        }

        $waiting = self::rows($sql . ' ORDER BY paid_at, id', $parameters);
        if ($waiting === []) {
            return 0;
        }

        $insert = self::db()->prepare(
            "INSERT INTO invoice_lines (invoice_id, description, quantity, unit_price, line_kind, disbursement_id) VALUES (?, ?, 1, ?, 'disbursement', ?)"
        );
        foreach ($waiting as $row) {
            $insert->execute([
                $invoiceId,
                sprintf('%s: %s (%s, at cost)', $row['category'], $row['description'], $row['disbursement_code']),
                (float) $row['amount'],
                (int) $row['id'],
            ]);
        }

        LogisticsData::recalculateInvoice($invoiceId);
        self::syncInvoiceDisbursements($invoiceId);

        return count($waiting);
    }

    // ---------------------------------------------------------------- posting

    /** Dr the bill's accounts, Cr Accounts payable. Drafts and cancelled bills are not posted. */
    public static function postBill(int $billId): ?int
    {
        $bill = self::row(
            'SELECT b.*, s.supplier_name, t.reference_code AS trip_code
               FROM supplier_bills b
               LEFT JOIN suppliers s ON s.id = b.supplier_id
               LEFT JOIN trips t ON t.id = b.trip_id
              WHERE b.id = ?',
            [$billId]
        );

        if ($bill === null || $bill['deleted_at'] !== null || in_array($bill['status'], ['draft', 'cancelled'], true) || self::alreadyBooked($bill)) {
            Posting::unpost('supplier_bill', $billId);
            return null;
        }

        $total = round((float) $bill['total_amount'], 2);
        if ($total <= 0) {
            return null;
        }

        $who = (string) ($bill['supplier_name'] ?? 'supplier');
        $lines = [];
        foreach (self::rows(
            'SELECT l.account_id, l.description, l.line_total FROM supplier_bill_lines l WHERE l.bill_id = ? ORDER BY l.id',
            [$billId]
        ) as $line) {
            $amount = round((float) $line['line_total'], 2);
            if ($amount <= 0) {
                continue;
            }
            $lines[] = [
                'account' => $line['account_id'] !== null ? (int) $line['account_id'] : self::OTHER_EXPENSE,
                'debit' => $amount,
                'description' => mb_substr((string) $line['description'], 0, 200),
            ];
        }

        $tax = round((float) $bill['tax_amount'], 2);
        if ($tax > 0) {
            // VAT a supplier charged us comes off what we owe the tax office.
            $lines[] = ['account' => self::VAT, 'debit' => $tax, 'description' => 'VAT on ' . $bill['bill_no']];
        }
        $lines[] = ['account' => self::PAYABLE, 'credit' => $total, 'description' => $who];

        return Ledger::post(
            (string) $bill['bill_date'],
            sprintf('Bill %s from %s', $bill['bill_no'], $who),
            $lines,
            'supplier_bill',
            $billId,
            (string) $bill['bill_no'],
            $bill['supplier_invoice_no'] ?: ($bill['trip_code'] ?? null),
            (string) ($bill['currency'] ?: Currency::base())
        );
    }

    /** Dr Accounts payable, Cr whatever the money left from. */
    public static function postSupplierPayment(int $paymentId): ?int
    {
        $payment = self::row(
            'SELECT p.*, b.bill_no, s.supplier_name
               FROM supplier_payments p
               INNER JOIN supplier_bills b ON b.id = p.bill_id
               LEFT JOIN suppliers s ON s.id = b.supplier_id
              WHERE p.id = ?',
            [$paymentId]
        );

        if ($payment === null || $payment['deleted_at'] !== null) {
            Posting::unpost('supplier_payment', $paymentId);
            return null;
        }

        $amount = round((float) $payment['amount'], 2);
        if ($amount <= 0) {
            return null;
        }

        $who = (string) ($payment['supplier_name'] ?? 'supplier');

        return Ledger::post(
            date('Y-m-d', (int) strtotime((string) $payment['paid_at'])),
            sprintf('Payment %s to %s', $payment['payment_code'], $who),
            [
                ['account' => self::PAYABLE, 'debit' => $amount, 'description' => $who . ', bill ' . $payment['bill_no']],
                ['account' => PaymentMethod::accountCode((string) $payment['method']), 'credit' => $amount, 'description' => 'Paid by ' . PaymentMethod::name((string) $payment['method'])],
            ],
            'supplier_payment',
            $paymentId,
            (string) $payment['payment_code'],
            (string) ($payment['reference'] ?: $payment['bill_no']),
            (string) ($payment['currency'] ?: Currency::base())
        );
    }

    /** Dr Disbursements recoverable, Cr the account the money was paid from. */
    public static function postDisbursement(int $id): ?int
    {
        $row = self::row(
            'SELECT d.*, c.customer_name, t.reference_code AS trip_code
               FROM disbursements d
               LEFT JOIN customers c ON c.id = d.customer_id
               LEFT JOIN trips t ON t.id = d.trip_id
              WHERE d.id = ?',
            [$id]
        );

        if ($row === null || $row['deleted_at'] !== null) {
            Posting::unpost('disbursement', $id);
            Posting::unpost('disbursement_wo', $id);
            return null;
        }

        $amount = round((float) $row['amount'], 2);
        if ($amount <= 0) {
            return null;
        }

        $who = (string) ($row['customer_name'] ?? 'customer');

        return Ledger::post(
            (string) $row['paid_at'],
            sprintf('Disbursement %s for %s', $row['disbursement_code'], $who),
            [
                ['account' => self::RECOVERABLE, 'debit' => $amount, 'description' => $row['category'] . ' paid for ' . $who],
                ['account' => PaymentMethod::accountCode((string) $row['method'], self::PAYABLE), 'credit' => $amount, 'description' => mb_substr((string) $row['description'], 0, 200)],
            ],
            'disbursement',
            $id,
            (string) $row['disbursement_code'],
            $row['trip_code'] ?? $row['reference'],
            (string) ($row['currency'] ?: Currency::base())
        );
    }

    /** Money paid for a customer that will never come back becomes a cost. */
    private static function postWriteOff(int $id): void
    {
        $row = self::row('SELECT * FROM disbursements WHERE id = ?', [$id]);
        if ($row === null || (float) $row['amount'] <= 0) {
            return;
        }

        try {
            Ledger::post(
                date('Y-m-d'),
                sprintf('Disbursement %s written off', $row['disbursement_code']),
                [
                    ['account' => self::OTHER_EXPENSE, 'debit' => (float) $row['amount'], 'description' => 'Not recoverable: ' . mb_substr((string) $row['description'], 0, 150)],
                    ['account' => self::RECOVERABLE, 'credit' => (float) $row['amount'], 'description' => (string) $row['disbursement_code']],
                ],
                'disbursement_wo',
                $id,
                (string) $row['disbursement_code'],
                null,
                (string) ($row['currency'] ?: Currency::base())
            );
        } catch (\Throwable $exception) {
            AuditLog::record('gl.post_failed', 'journal', 'disbursement_wo#' . $id, $exception->getMessage());
        }
    }

    /** Posts without ever letting an accounting problem stop the save. */
    public static function tryPost(string $kind, int $id): void
    {
        if (!Posting::enabled()) {
            return;
        }

        try {
            match ($kind) {
                'supplier_bill' => self::postBill($id),
                'supplier_payment' => self::postSupplierPayment($id),
                'disbursement' => self::postDisbursement($id),
                default => null,
            };
        } catch (\Throwable $exception) {
            AuditLog::record('gl.post_failed', 'journal', $kind . '#' . $id, $exception->getMessage());
        }
    }

    /** @return array<string, string> source type => SQL listing the ids that should be posted */
    public static function postingSources(): array
    {
        return [
            'supplier_bill' => "SELECT id FROM supplier_bills WHERE deleted_at IS NULL AND status NOT IN ('draft','cancelled')",
            'supplier_payment' => 'SELECT id FROM supplier_payments WHERE deleted_at IS NULL',
            'disbursement' => 'SELECT id FROM disbursements WHERE deleted_at IS NULL',
        ];
    }

    /**
     * True when the liability behind this bill is already in the ledger through
     * the document it is linked to.
     */
    private static function alreadyBooked(array $bill): bool
    {
        foreach (['purchase_request_id' => 'purchase', 'maintenance_id' => 'maintenance', 'crossing_id' => 'crossing'] as $column => $source) {
            if (empty($bill[$column])) {
                continue;
            }
            $posted = (int) self::scalar(
                'SELECT COUNT(*) FROM gl_journal_entries WHERE source_type = ? AND source_id = ? AND deleted_at IS NULL',
                [$source, (int) $bill[$column]]
            );
            if ($posted > 0) {
                return true;
            }
        }

        return false;
    }

    // ---------------------------------------------------------------- aging

    /**
     * Open balances by how late they are, one row per customer or supplier.
     *
     * @param 'receivable'|'payable' $side
     * @return list<array<string, mixed>>
     */
    public static function aging(string $side, ?string $asOf = null): array
    {
        $asOf ??= date('Y-m-d');

        $sql = $side === 'payable'
            ? "SELECT s.supplier_name AS party, b.currency, b.due_date, (b.total_amount - b.amount_paid) AS balance
                 FROM supplier_bills b INNER JOIN suppliers s ON s.id = b.supplier_id
                WHERE b.deleted_at IS NULL AND b.status IN ('approved','partially_paid') AND b.bill_date <= :as_of"
            : "SELECT c.customer_name AS party, i.currency, i.due_date, (i.total_amount - i.amount_paid) AS balance
                 FROM invoices i INNER JOIN customers c ON c.id = i.customer_id
                WHERE i.deleted_at IS NULL AND i.status IN ('issued','partially_paid','overdue') AND i.issue_date <= :as_of";

        $statement = self::db()->prepare($sql);
        $statement->execute(['as_of' => $asOf]);

        $buckets = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $balance = round((float) $row['balance'], 2);
            if ($balance <= 0) {
                continue;
            }
            $late = (int) floor((strtotime($asOf) - strtotime((string) $row['due_date'])) / 86400);
            $bucket = match (true) {
                $late <= 0 => 'current',
                $late <= 30 => 'd30',
                $late <= 60 => 'd60',
                $late <= 90 => 'd90',
                default => 'd90plus',
            };

            $key = $row['party'] . '|' . $row['currency'];
            $buckets[$key] ??= ['party' => $row['party'], 'currency' => $row['currency'], 'current' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd90' => 0.0, 'd90plus' => 0.0, 'total' => 0.0];
            $buckets[$key][$bucket] += $balance;
            $buckets[$key]['total'] += $balance;
        }

        $rows = array_values($buckets);
        usort($rows, static fn (array $a, array $b): int => [$a['currency'], -$a['total']] <=> [$b['currency'], -$b['total']]);

        return $rows;
    }

    // ---------------------------------------------------------------- helpers

    private static function row(string $sql, array $parameters): ?array
    {
        $statement = self::db()->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private static function rows(string $sql, array $parameters): array
    {
        $statement = self::db()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function scalar(string $sql, array $parameters): mixed
    {
        $statement = self::db()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn();
    }
}
