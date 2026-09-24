<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Turns the operational documents into double-entry postings.
 *
 * Nobody in operations should have to think about debits. An invoice is issued,
 * a driver buys fuel, a workshop finishes a job - and the ledger follows on its
 * own. Each posting carries the document it came from, so re-posting corrects
 * the entry instead of duplicating it, and cancelling a document takes its entry
 * back out.
 *
 * The rules, in the order money actually moves:
 *
 *   Invoice issued      Dr Accounts receivable   Cr Freight revenue, Cr VAT payable
 *   Payment received    Dr Bank or cash          Cr Accounts receivable
 *   Fuel bought         Dr Fuel                  Cr Bank, cash or payables
 *   Expense approved    Dr the category account  Cr Bank, cash or payables
 *   Work order done     Dr Vehicle maintenance   Cr Accounts payable
 *   Goods received      Dr Inventory             Cr Accounts payable
 */
final class Posting
{
    /** Expense category on the operational side, account code on the ledger side. */
    private const EXPENSE_ACCOUNTS = [
        'Fuel' => '5000',
        'Allowance' => '5100',
        'Toll' => '5200',
        'Parking' => '5200',
        'Permit' => '5200',
        'Loading' => '5400',
        'Repair' => '6000',
        'Insurance' => '6100',
    ];

    /**
     * How a payment was made decides which account it left or entered, and the
     * company decides that on the Payment methods page rather than here.
     */
    private static function paymentAccount(string $method, string $fallback = '1020'): string
    {
        return PaymentMethod::accountCode($method, $fallback);
    }

    private const RECEIVABLE = '1100';
    private const PAYABLE = '2000';
    private const VAT_PAYABLE = '2100';
    private const INVENTORY = '1200';
    private const REVENUE = '4000';
    private const MAINTENANCE = '6000';
    private const FUEL = '5000';
    private const OTHER_EXPENSE = '6900';

    /**
     * What a document is worth, in the money it was written in.
     *
     * A fuel receipt from Namanga reads KES 12,500 and is posted as KES 12,500.
     * It is never restated into francs: each currency keeps its own books, so
     * the shilling page adds shillings and the page balances on its own. A rate
     * that moves next month cannot change what this receipt says.
     */
    private static function inBooks(array $document, string $column, ?float $fallback = null): float
    {
        return round($fallback ?? (float) ($document[$column] ?? 0), 2);
    }

    /**
     * The currency an entry belongs in: the one on the document.
     *
     * Each currency is its own set of books, so a shilling receipt is posted in
     * shillings and read on the shilling page. Rows written before currencies
     * existed carry none, and those were all in the base currency.
     */
    private static function moneyOf(array $document): string
    {
        $code = strtoupper(trim((string) ($document['currency'] ?? '')));

        return $code !== '' ? $code : Currency::base();
    }

    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function enabled(): bool
    {
        return Settings::int('gl_auto_post', 1) === 1;
    }

    // ------------------------------------------------------------ documents

    /** Dr Accounts receivable, Cr revenue and VAT. Draft and cancelled invoices are not posted. */
    public static function invoice(int $invoiceId): ?int
    {
        $invoice = self::row(
            'SELECT i.*, c.customer_name, t.reference_code AS trip_code
               FROM invoices i
               LEFT JOIN customers c ON c.id = i.customer_id
               LEFT JOIN trips t ON t.id = i.trip_id
              WHERE i.id = ?',
            [$invoiceId]
        );

        if ($invoice === null) {
            return null;
        }

        if ($invoice['deleted_at'] !== null || in_array($invoice['status'], ['draft', 'cancelled'], true)) {
            self::unpost('invoice', $invoiceId);
            return null;
        }

        $subtotal = round((float) $invoice['subtotal'], 2);
        $tax = round((float) $invoice['tax_amount'], 2);
        $total = round((float) $invoice['total_amount'], 2);
        if ($total <= 0) {
            return null;
        }

        $who = $invoice['customer_name'] ?? 'customer';
        $lines = [
            ['account' => self::RECEIVABLE, 'debit' => $total, 'description' => $who],
            ['account' => self::REVENUE, 'credit' => $subtotal, 'description' => 'Freight invoiced to ' . $who],
        ];
        if ($tax > 0) {
            $lines[] = ['account' => self::VAT_PAYABLE, 'credit' => $tax, 'description' => 'VAT on ' . $invoice['invoice_number']];
        }

        return Ledger::post(
            (string) $invoice['issue_date'],
            sprintf('Invoice %s to %s', $invoice['invoice_number'], $who),
            $lines,
            'invoice',
            $invoiceId,
            (string) $invoice['invoice_number'],
            $invoice['trip_code'] ?? null,
            self::moneyOf($invoice)
        );
    }

    /** Dr the account the money arrived in, Cr Accounts receivable. */
    public static function payment(int $paymentId): ?int
    {
        $payment = self::row(
            'SELECT p.*, i.invoice_number, c.customer_name
               FROM payments p
               INNER JOIN invoices i ON i.id = p.invoice_id
               LEFT JOIN customers c ON c.id = i.customer_id
              WHERE p.id = ?',
            [$paymentId]
        );

        if ($payment === null || $payment['deleted_at'] !== null) {
            self::unpost('payment', $paymentId);
            return null;
        }

        $amount = self::inBooks($payment, 'amount');
        if ($amount <= 0) {
            return null;
        }

        $bank = self::paymentAccount((string) $payment['method']);
        $who = $payment['customer_name'] ?? 'customer';

        return Ledger::post(
            date('Y-m-d', (int) strtotime((string) $payment['paid_at'])),
            sprintf('Payment %s from %s', $payment['payment_code'], $who),
            [
                ['account' => $bank, 'debit' => $amount, 'description' => 'Received against ' . $payment['invoice_number']],
                ['account' => self::RECEIVABLE, 'credit' => $amount, 'description' => $who],
            ],
            'payment',
            $paymentId,
            (string) $payment['payment_code'],
            (string) $payment['invoice_number'],
            self::moneyOf($payment)
        );
    }

    /** Dr the category's account, Cr wherever it was paid from. Only approved claims post. */
    public static function expense(int $expenseId): ?int
    {
        $expense = self::row(
            'SELECT e.*, v.plate_number, t.reference_code AS trip_code
               FROM expenses e
               LEFT JOIN vehicles v ON v.id = e.vehicle_id
               LEFT JOIN trips t ON t.id = e.trip_id
              WHERE e.id = ?',
            [$expenseId]
        );

        if ($expense === null || $expense['deleted_at'] !== null || $expense['status'] !== 'approved') {
            self::unpost('expense', $expenseId);
            return null;
        }

        $amount = self::inBooks($expense, 'amount');
        if ($amount <= 0) {
            return null;
        }

        $account = self::EXPENSE_ACCOUNTS[(string) $expense['category']] ?? self::OTHER_EXPENSE;
        $credit = self::paymentAccount((string) $expense['payment_method'], self::PAYABLE);
        $what = trim((string) $expense['category'] . ' ' . (string) ($expense['plate_number'] ?? ''));

        return Ledger::post(
            (string) $expense['expense_date'],
            sprintf('Expense %s - %s', $expense['reference_code'], $what),
            [
                ['account' => $account, 'debit' => $amount, 'description' => $what],
                ['account' => $credit, 'credit' => $amount, 'description' => 'Settled by ' . str_replace('_', ' ', (string) $expense['payment_method'])],
            ],
            'expense',
            $expenseId,
            (string) $expense['reference_code'],
            $expense['trip_code'] ?? null,
            self::moneyOf($expense)
        );
    }

    /** Dr Fuel, Cr the fuel card account or the bank. */
    public static function fuel(int $fuelId): ?int
    {
        $fuel = self::row(
            'SELECT f.*, v.plate_number, t.reference_code AS trip_code
               FROM fuel_records f
               LEFT JOIN vehicles v ON v.id = f.vehicle_id
               LEFT JOIN trips t ON t.id = f.trip_id
              WHERE f.id = ?',
            [$fuelId]
        );

        if ($fuel === null || $fuel['deleted_at'] !== null) {
            self::unpost('fuel', $fuelId);
            return null;
        }

        $amount = self::inBooks($fuel, '', round((float) $fuel['litres'] * (float) $fuel['unit_price'], 2));
        if ($amount <= 0) {
            return null;
        }

        $description = sprintf(
            '%s litres for %s at %s',
            rtrim(rtrim(number_format((float) $fuel['litres'], 2, '.', ''), '0'), '.'),
            (string) ($fuel['plate_number'] ?? 'the fleet'),
            (string) $fuel['station_name']
        );

        return Ledger::post(
            date('Y-m-d', (int) strtotime((string) $fuel['purchased_at'])),
            sprintf('Fuel %s - %s', $fuel['reference_code'], (string) ($fuel['plate_number'] ?? '')),
            [
                ['account' => self::FUEL, 'debit' => $amount, 'description' => $description],
                ['account' => self::PAYABLE, 'credit' => $amount, 'description' => (string) $fuel['station_name']],
            ],
            'fuel',
            $fuelId,
            (string) $fuel['reference_code'],
            $fuel['trip_code'] ?? null,
            self::moneyOf($fuel)
        );
    }

    /** Dr Vehicle maintenance, Cr Accounts payable. Only a completed job has a real cost. */
    public static function maintenance(int $orderId): ?int
    {
        $order = self::row(
            'SELECT m.*, v.plate_number
               FROM maintenance_orders m
               LEFT JOIN vehicles v ON v.id = m.vehicle_id
              WHERE m.id = ?',
            [$orderId]
        );

        if ($order === null || $order['deleted_at'] !== null || $order['status'] !== 'completed') {
            self::unpost('maintenance', $orderId);
            return null;
        }

        $amount = round((float) ($order['actual_cost'] ?? $order['estimated_cost']), 2);
        if ($amount <= 0) {
            return null;
        }

        $provider = (string) ($order['provider_name'] ?? 'workshop');

        return Ledger::post(
            date('Y-m-d', (int) strtotime((string) ($order['completed_at'] ?? $order['due_date']))),
            sprintf('Work order %s - %s', $order['work_order_code'], (string) $order['service_name']),
            [
                ['account' => self::MAINTENANCE, 'debit' => $amount, 'description' => (string) $order['service_name'] . ' on ' . (string) ($order['plate_number'] ?? '')],
                ['account' => self::PAYABLE, 'credit' => $amount, 'description' => $provider],
            ],
            'maintenance',
            $orderId,
            (string) $order['work_order_code'],
            $order['plate_number'] ?? null,
            self::moneyOf($order)
        );
    }

    /** Dr Inventory, Cr Accounts payable, once the goods are actually received. */
    public static function purchase(int $requestId): ?int
    {
        $request = self::row(
            'SELECT p.*, s.supplier_name
               FROM purchase_requests p
               LEFT JOIN suppliers s ON s.id = p.supplier_id
              WHERE p.id = ?',
            [$requestId]
        );

        if ($request === null || $request['deleted_at'] !== null || $request['status'] !== 'received') {
            self::unpost('purchase', $requestId);
            return null;
        }

        $amount = self::inBooks($request, 'amount');
        if ($amount <= 0) {
            return null;
        }

        $supplier = (string) ($request['supplier_name'] ?? 'supplier');

        return Ledger::post(
            date('Y-m-d', (int) strtotime((string) ($request['received_at'] ?? $request['created_at']))),
            sprintf('Goods received on %s', $request['request_code']),
            [
                ['account' => self::INVENTORY, 'debit' => $amount, 'description' => mb_substr((string) $request['description'], 0, 200)],
                ['account' => self::PAYABLE, 'credit' => $amount, 'description' => $supplier],
            ],
            'purchase',
            $requestId,
            (string) $request['request_code'],
            $supplier,
            self::moneyOf($request)
        );
    }

    // ------------------------------------------------------------ plumbing

    /**
     * Posts one document without ever letting an accounting problem break the
     * operational action that triggered it. A failure is recorded in the audit
     * trail for the accountant to look at, and the trip still dispatches.
     */
    public static function tryPost(string $kind, int $id): void
    {
        if (!self::enabled()) {
            return;
        }

        try {
            match ($kind) {
                'invoice' => self::invoice($id),
                'payment' => self::payment($id),
                'expense' => self::expense($id),
                'fuel' => self::fuel($id),
                'maintenance' => self::maintenance($id),
                'purchase' => self::purchase($id),
                default => null,
            };
        } catch (\Throwable $exception) {
            AuditLog::record('gl.post_failed', 'journal', $kind . '#' . $id, $exception->getMessage());
        }
    }

    /** Takes an entry back out when its document is cancelled or deleted. */
    public static function unpost(string $sourceType, int $sourceId): void
    {
        try {
            $statement = self::db()->prepare(
                'UPDATE gl_journal_entries SET deleted_at = NOW() WHERE source_type = ? AND source_id = ? AND deleted_at IS NULL'
            );
            $statement->execute([$sourceType, $sourceId]);
        } catch (\Throwable) {
            // Never let this break the caller.
        }
    }

    /**
     * Posts everything that should be in the ledger and is not, and removes
     * entries whose documents no longer qualify.
     *
     * Run after a seed, after an import, or whenever the accountant wants to be
     * sure the books and the operations agree.
     *
     * @return array<string, int>
     */
    public static function syncAll(): array
    {
        $counts = [];

        $sources = [
            'invoice' => "SELECT id FROM invoices WHERE deleted_at IS NULL AND status NOT IN ('draft','cancelled')",
            'payment' => 'SELECT id FROM payments WHERE deleted_at IS NULL',
            'expense' => "SELECT id FROM expenses WHERE deleted_at IS NULL AND status = 'approved'",
            'fuel' => 'SELECT id FROM fuel_records WHERE deleted_at IS NULL',
            'maintenance' => "SELECT id FROM maintenance_orders WHERE deleted_at IS NULL AND status = 'completed'",
            'purchase' => "SELECT id FROM purchase_requests WHERE deleted_at IS NULL AND status = 'received'",
        ];

        foreach ($sources as $kind => $sql) {
            $posted = 0;
            foreach (self::db()->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $id) {
                try {
                    if (match ($kind) {
                        'invoice' => self::invoice((int) $id),
                        'payment' => self::payment((int) $id),
                        'expense' => self::expense((int) $id),
                        'fuel' => self::fuel((int) $id),
                        'maintenance' => self::maintenance((int) $id),
                        'purchase' => self::purchase((int) $id),
                    } !== null) {
                        $posted++;
                    }
                } catch (\Throwable $exception) {
                    AuditLog::record('gl.post_failed', 'journal', $kind . '#' . $id, $exception->getMessage());
                }
            }
            $counts[$kind] = $posted;
        }

        AuditLog::record('gl.synced', 'journal', null, null, $counts);

        return $counts;
    }

    private static function row(string $sql, array $parameters): ?array
    {
        $statement = self::db()->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }
}
