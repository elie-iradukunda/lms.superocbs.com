<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * The store (ERI specification, section 12): goods received against a
 * purchase order, and stock counts with the differences explained and
 * approved. Issues are on Models\Garage (only against an issue note).
 *
 * Stock is valued at weighted average cost in the company's base currency.
 */
final class Store
{
    public const COUNT_STATUS = [
        'draft' => ['Counting', 'warning'],
        'submitted' => ['To approve', 'info'],
        'approved' => ['Approved', 'success'],
        'rejected' => ['Sent back', 'danger'],
    ];

    private static function db(): PDO
    {
        return Database::connection();
    }

    // -------------------------------------------------------- goods received

    /** @return list<array<string, mixed>> purchase orders with something still to receive */
    public static function toReceive(): array
    {
        $condition = Company::condition('form_requests', 'r.company_id');

        return self::db()->query(
            "SELECT r.*, s.supplier_name, v.plate_number,
                    (SELECT COALESCE(SUM(l.quantity - l.done_quantity), 0) FROM form_request_lines l WHERE l.request_id = r.id) AS outstanding
               FROM form_requests r LEFT JOIN suppliers s ON s.id = r.supplier_id LEFT JOIN vehicles v ON v.id = r.vehicle_id
              WHERE r.form_code = 'F5' AND r.status = 'authorized' AND r.deleted_at IS NULL" . ($condition !== null ? ' AND ' . $condition : '') . '
              HAVING outstanding > 0 ORDER BY r.id'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public static function receipts(int $orderId): array
    {
        $statement = self::db()->prepare('SELECT g.*, u.full_name AS received_name FROM goods_receipts g LEFT JOIN users u ON u.id = g.received_by WHERE g.request_id = ? ORDER BY g.id');
        $statement->execute([$orderId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * The storekeeper records what arrived (or the OM confirms work done).
     * Stock lines go into the store at the order price converted to the base
     * currency (Dr stock, Cr goods received not invoiced); the supplier's
     * invoice (F12) later clears the second account.
     *
     * @param array<int, float|string> $quantities order line id => quantity received now
     */
    public static function receive(int $orderId, array $quantities, string $deliveryRef = '', string $notes = ''): int
    {
        $order = RequestForms::find($orderId) ?? throw new \RuntimeException('That purchase order does not exist.');
        if ($order['form_code'] !== 'F5' || $order['status'] !== 'authorized') {
            throw new \RuntimeException('Goods are received only against an authorized purchase order.');
        }
        $lines = [];
        foreach (RequestForms::lines($orderId) as $line) {
            $lines[(int) $line['id']] = $line;
        }

        $companyId = (int) $order['company_id'];
        $base = Company::baseCurrency($companyId);
        $rate = Currency::crossRate((string) $order['currency'], $base, date('Y-m-d'));
        if ($rate === null) {
            throw new \RuntimeException(sprintf('There is no exchange rate from %s to %s. Finance enters it under Exchange rates first.', $order['currency'], $base));
        }

        $db = self::db();
        $db->beginTransaction();
        try {
            $number = Numbering::next($companyId, 'goods_receipt', Settings::get('goods_receipt_number_format', 'GRN-{YEAR}-{SEQ4}'), 'year');
            $db->prepare('INSERT INTO goods_receipts (company_id, receipt_no, request_id, supplier_id, received_on, delivery_ref, currency, received_by, notes) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?)')
                ->execute([$companyId, $number, $orderId, $order['supplier_id'], trim($deliveryRef) ?: null, $order['currency'], \current_user_id(), trim($notes) ?: null]);
            $receiptId = (int) $db->lastInsertId();

            $count = 0;
            $total = 0.0;
            $stockValue = 0.0;
            foreach ($quantities as $lineId => $quantity) {
                $quantity = round((float) str_replace(',', '', (string) $quantity), 2);
                $line = $lines[(int) $lineId] ?? null;
                if ($line === null || $quantity <= 0) {
                    continue;
                }
                $left = round((float) $line['quantity'] - (float) $line['done_quantity'], 2);
                if ($quantity > $left + 0.004) {
                    throw new \RuntimeException(sprintf('Only %s of "%s" is still to receive on this order.', rtrim(rtrim(number_format($left, 2), '0'), '.'), $line['description']));
                }
                $price = (float) $line['unit_price'] > 0 ? (float) $line['unit_price'] : ((float) $line['quantity'] > 0 ? (float) $line['amount'] / (float) $line['quantity'] : 0.0);
                $amount = round($quantity * $price, 2);
                $movement = null;
                if (!empty($line['item_id'])) {
                    $baseCost = round($price * $rate, 2);
                    self::averageCost((int) $line['item_id'], $quantity, $baseCost);
                    $movement = StockLedger::record((int) $line['item_id'], 'stock_in', $quantity, $baseCost, 'goods_receipt', $number, null, 'Received against ' . RequestForms::label($order));
                    $stockValue += round($quantity * $baseCost, 2);
                }
                $db->prepare('INSERT INTO goods_receipt_lines (receipt_id, request_line_id, item_id, description, quantity, unit_price, amount, movement_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$receiptId, (int) $lineId, $line['item_id'] ?: null, $line['description'], $quantity, $price, $amount, $movement]);
                $db->prepare('UPDATE form_request_lines SET done_quantity = done_quantity + ? WHERE id = ?')->execute([$quantity, (int) $lineId]);
                $total += $amount;
                $count++;
            }
            if ($count === 0) {
                throw new \RuntimeException('Enter the quantity received on at least one line.');
            }

            $entryId = null;
            if ($stockValue > 0) {
                [$debit, $credit] = RequestPosting::accountsFor($companyId, 'goods_received', null);
                $dims = ['vehicle_id' => $order['vehicle_id'], 'request_id' => $orderId];
                $entryId = Ledger::post(date('Y-m-d'), sprintf('Goods received %s — %s', $number, $order['supplier_name'] ?? ''), [
                    ['account' => $debit, 'debit' => round($stockValue, 2), 'description' => 'Into the store'] + $dims,
                    ['account' => $credit, 'credit' => round($stockValue, 2), 'description' => (string) ($order['supplier_name'] ?? 'Supplier') . ' — not yet invoiced'] + $dims,
                ], 'goods_receipt', $receiptId, $number, null, $base, $companyId);
            }
            $db->prepare('UPDATE goods_receipts SET total_amount = ?, entry_id = ? WHERE id = ?')->execute([round($total, 2), $entryId, $receiptId]);

            $left = (float) $db->query("SELECT COALESCE(SUM(quantity - done_quantity), 0) FROM form_request_lines WHERE request_id = {$orderId}")->fetchColumn();
            if ($left <= 0.004) {
                $db->prepare("UPDATE form_requests SET status = 'closed' WHERE id = ?")->execute([$orderId]);
            }
            RequestForms::event($orderId, (int) $order['version'], 'goods_received', sprintf('%s: %d line(s)%s', $number, $count, $deliveryRef !== '' ? ', delivery note ' . $deliveryRef : ''), true);
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        AuditLog::record('store.received', 'goods_receipts', $number, RequestForms::label($order), ['lines' => $count, 'total' => $total]);
        Notifier::forCompany($companyId, static fn () => Notifier::toRoles(
            ['logistics_manager', 'finance'],
            'Goods received',
            sprintf('%s received against %s from %s. The supplier invoice can now be matched.', $number, RequestForms::label($order), $order['supplier_name'] ?? 'the supplier'),
            'form_requests',
            'info',
            'form_requests',
            (string) $orderId
        ));

        return $receiptId;
    }

    /** New stock changes the item's cost to the weighted average of old and new. */
    private static function averageCost(int $itemId, float $quantity, float $unitCost): void
    {
        $statement = self::db()->prepare('SELECT quantity, unit_cost FROM inventory_items WHERE id = ?');
        $statement->execute([$itemId]);
        $item = $statement->fetch(PDO::FETCH_ASSOC);
        if ($item === false) {
            return;
        }
        $onHand = max(0.0, (float) $item['quantity']);
        $cost = $onHand + $quantity > 0
            ? round(($onHand * (float) $item['unit_cost'] + $quantity * $unitCost) / ($onHand + $quantity), 2)
            : $unitCost;
        self::db()->prepare('UPDATE inventory_items SET unit_cost = ? WHERE id = ?')->execute([$cost, $itemId]);
    }

    /**
     * What the store received into stock against an order: what a supplier
     * invoice (F12) clears from "goods received not invoiced". Keyed by the
     * order line, and also pooled by stock item so an invoice line that does
     * not name its order line (entered by hand, not through the portal) still
     * clears.
     *
     * @return array{by_line: array<int, float>, by_item: array<int, float>}
     */
    public static function receivedValues(?int $orderId): array
    {
        if (!$orderId) {
            return ['by_line' => [], 'by_item' => []];
        }
        $statement = self::db()->prepare(
            'SELECT l.request_line_id, l.item_id, SUM(l.amount) AS amount FROM goods_receipts g INNER JOIN goods_receipt_lines l ON l.receipt_id = g.id
              WHERE g.request_id = ? AND l.item_id IS NOT NULL GROUP BY l.request_line_id, l.item_id'
        );
        $statement->execute([$orderId]);
        $byLine = [];
        $byItem = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byLine[(int) $row['request_line_id']] = ($byLine[(int) $row['request_line_id']] ?? 0) + (float) $row['amount'];
            $byItem[(int) $row['item_id']] = ($byItem[(int) $row['item_id']] ?? 0) + (float) $row['amount'];
        }

        return ['by_line' => $byLine, 'by_item' => $byItem];
    }

    // ----------------------------------------------------------- stock counts

    /** @return list<array<string, mixed>> */
    public static function counts(): array
    {
        $condition = Company::condition('stock_counts', 'c.company_id');

        return self::db()->query(
            'SELECT c.*, w.warehouse_name, u.full_name AS counted_name, d.full_name AS decided_name,
                    (SELECT COUNT(*) FROM stock_count_lines l WHERE l.count_id = c.id AND l.counted_quantity IS NOT NULL AND l.counted_quantity <> l.system_quantity) AS differences
               FROM stock_counts c LEFT JOIN warehouses w ON w.id = c.warehouse_id LEFT JOIN users u ON u.id = c.counted_by LEFT JOIN users d ON d.id = c.decided_by
              WHERE 1 = 1' . ($condition !== null ? ' AND ' . $condition : '') . ' ORDER BY c.id DESC LIMIT 100'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public static function count(int $id): ?array
    {
        $condition = Company::condition('stock_counts', 'c.company_id');
        $statement = self::db()->prepare(
            'SELECT c.*, w.warehouse_name, u.full_name AS counted_name, d.full_name AS decided_name
               FROM stock_counts c LEFT JOIN warehouses w ON w.id = c.warehouse_id LEFT JOIN users u ON u.id = c.counted_by LEFT JOIN users d ON d.id = c.decided_by
              WHERE c.id = ?' . ($condition !== null ? ' AND ' . $condition : '')
        );
        $statement->execute([$id]);
        $count = $statement->fetch(PDO::FETCH_ASSOC);
        if ($count === false) {
            return null;
        }
        $lines = self::db()->prepare('SELECT l.*, i.item_name, i.sku, i.unit_of_measure FROM stock_count_lines l INNER JOIN inventory_items i ON i.id = l.item_id WHERE l.count_id = ? ORDER BY i.item_name');
        $lines->execute([$id]);
        $count['lines'] = $lines->fetchAll(PDO::FETCH_ASSOC);

        return $count;
    }

    /** Starts a count of one store: every item, with what the system holds now. */
    public static function startCount(int $warehouseId, string $notes = ''): int
    {
        $statement = self::db()->prepare('SELECT * FROM warehouses WHERE id = ? AND deleted_at IS NULL');
        $statement->execute([$warehouseId]);
        $warehouse = $statement->fetch(PDO::FETCH_ASSOC);
        if ($warehouse === false) {
            throw new \RuntimeException('Choose the store to count.');
        }
        $companyId = (int) ($warehouse['company_id'] ?: \current_company_id());
        $open = self::db()->prepare("SELECT COUNT(*) FROM stock_counts WHERE warehouse_id = ? AND status IN ('draft','submitted')");
        $open->execute([$warehouseId]);
        if ((int) $open->fetchColumn() > 0) {
            throw new \RuntimeException('A count of this store is already open. Finish it first.');
        }

        $db = self::db();
        $db->beginTransaction();
        try {
            $number = Numbering::next($companyId, 'stock_count', Settings::get('stock_count_number_format', 'SC-{YEAR}-{SEQ4}'), 'year');
            $db->prepare('INSERT INTO stock_counts (company_id, count_no, warehouse_id, counted_on, counted_by, notes) VALUES (?, ?, ?, CURDATE(), ?, ?)')
                ->execute([$companyId, $number, $warehouseId, \current_user_id(), trim($notes) ?: null]);
            $id = (int) $db->lastInsertId();
            $db->prepare('INSERT INTO stock_count_lines (count_id, item_id, system_quantity, unit_cost) SELECT ?, id, quantity, unit_cost FROM inventory_items WHERE warehouse_id = ? AND deleted_at IS NULL')
                ->execute([$id, $warehouseId]);
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
        AuditLog::record('store.count_started', 'stock_counts', $number, (string) $warehouse['warehouse_name']);

        return $id;
    }

    /**
     * Saves what was counted, and the reasons. With $submit, every line must
     * be counted and every difference explained; it then goes for approval.
     *
     * @param array<int, string> $counted line id => quantity counted
     * @param array<int, string> $reasons line id => why it differs
     */
    public static function saveCount(int $id, array $counted, array $reasons, bool $submit = false): void
    {
        $count = self::count($id) ?? throw new \RuntimeException('That stock count does not exist.');
        if (!in_array($count['status'], ['draft', 'rejected'], true)) {
            throw new \RuntimeException('This count has been submitted and can no longer be changed.');
        }
        $update = self::db()->prepare('UPDATE stock_count_lines SET counted_quantity = ?, reason = ? WHERE id = ? AND count_id = ?');
        $problems = [];
        foreach ($count['lines'] as $line) {
            $lineId = (int) $line['id'];
            $raw = trim((string) ($counted[$lineId] ?? ''));
            $quantity = $raw === '' ? null : round((float) str_replace(',', '', $raw), 2);
            $reason = trim((string) ($reasons[$lineId] ?? '')) ?: null;
            $update->execute([$quantity, $reason !== null ? mb_substr($reason, 0, 255) : null, $lineId, $id]);
            if ($submit) {
                if ($quantity === null) {
                    $problems[] = sprintf('%s is not counted.', $line['item_name']);
                } elseif ($quantity < 0) {
                    $problems[] = sprintf('%s cannot be negative.', $line['item_name']);
                } elseif (abs($quantity - (float) $line['system_quantity']) > 0.004 && $reason === null) {
                    $problems[] = sprintf('%s differs from the system: say why.', $line['item_name']);
                }
            }
        }
        if ($problems !== []) {
            throw new \RuntimeException(implode(' ', array_slice($problems, 0, 6)) . (count($problems) > 6 ? sprintf(' …and %d more.', count($problems) - 6) : ''));
        }
        if ($submit) {
            self::db()->prepare("UPDATE stock_counts SET status = 'submitted', submitted_at = NOW() WHERE id = ?")->execute([$id]);
            AuditLog::record('store.count_submitted', 'stock_counts', (string) $count['count_no']);
            Notifier::forCompany((int) $count['company_id'], static fn () => Notifier::toRoles(['finance', 'logistics_manager'], 'Stock count to approve', sprintf('%s of %s was submitted by %s.', $count['count_no'], $count['warehouse_name'], \current_user_name()), 'stock_counts', 'warning', 'stock_counts', (string) $id));
        }
    }

    /**
     * Approved, the differences move the stock and post: a loss debits stock
     * count differences, a gain credits it. The counter cannot approve.
     */
    public static function approveCount(int $id): void
    {
        $count = self::count($id) ?? throw new \RuntimeException('That stock count does not exist.');
        if ($count['status'] !== 'submitted') {
            throw new \RuntimeException('Only a submitted count can be approved.');
        }
        if ((int) $count['counted_by'] === (int) \current_user_id()) {
            throw new \RuntimeException('The person who counted cannot approve the count.');
        }

        $companyId = (int) $count['company_id'];
        [$lossAccount, $stockAccount] = RequestPosting::accountsFor($companyId, 'stock_count', null);
        $db = self::db();
        $db->beginTransaction();
        try {
            $ledger = [];
            foreach ($count['lines'] as $line) {
                $difference = round((float) $line['counted_quantity'] - (float) $line['system_quantity'], 2);
                if (abs($difference) < 0.005) {
                    continue;
                }
                $note = sprintf('Stock count %s: %s', $count['count_no'], $line['reason']);
                StockLedger::record((int) $line['item_id'], $difference > 0 ? 'adjustment' : 'stock_out', abs($difference), (float) $line['unit_cost'], 'stock_count', (string) $count['count_no'], null, $note);
                $value = round(abs($difference) * (float) $line['unit_cost'], 2);
                if ($value <= 0) {
                    continue;
                }
                $what = sprintf('%s %s %s (%s)', $line['item_name'], $difference > 0 ? 'over by' : 'short by', rtrim(rtrim(number_format(abs($difference), 2), '0'), '.'), $line['reason']);
                if ($difference < 0) {
                    $ledger[] = ['account' => $lossAccount, 'debit' => $value, 'description' => $what];
                    $ledger[] = ['account' => $stockAccount, 'credit' => $value, 'description' => $what];
                } else {
                    $ledger[] = ['account' => $stockAccount, 'debit' => $value, 'description' => $what];
                    $ledger[] = ['account' => $lossAccount, 'credit' => $value, 'description' => $what];
                }
            }
            $entryId = $ledger === [] ? null : Ledger::post(date('Y-m-d'), 'Stock count ' . $count['count_no'] . ' — ' . $count['warehouse_name'], $ledger, 'stock_count', $id, (string) $count['count_no'], null, Company::baseCurrency($companyId), $companyId);
            $db->prepare("UPDATE stock_counts SET status = 'approved', decided_by = ?, decided_at = NOW(), entry_id = ? WHERE id = ?")->execute([\current_user_id(), $entryId, $id]);
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
        AuditLog::record('store.count_approved', 'stock_counts', (string) $count['count_no']);
        Notifier::forCompany($companyId, static fn () => Notifier::toUser((int) $count['counted_by'], 'Stock count approved', sprintf('%s was approved and the stock adjusted.', $count['count_no']), 'stock_counts', 'success', 'stock_counts', (string) $id));
    }

    public static function rejectCount(int $id, string $reason): void
    {
        $count = self::count($id) ?? throw new \RuntimeException('That stock count does not exist.');
        if ($count['status'] !== 'submitted') {
            throw new \RuntimeException('Only a submitted count can be sent back.');
        }
        if (trim($reason) === '') {
            throw new \RuntimeException('Say what to recount or explain.');
        }
        self::db()->prepare("UPDATE stock_counts SET status = 'rejected', decided_by = ?, decided_at = NOW(), decision_note = ? WHERE id = ?")->execute([\current_user_id(), mb_substr(trim($reason), 0, 255), $id]);
        AuditLog::record('store.count_rejected', 'stock_counts', (string) $count['count_no'], trim($reason));
        Notifier::forCompany((int) $count['company_id'], static fn () => Notifier::toUser((int) $count['counted_by'], 'Stock count sent back', sprintf('%s: %s', $count['count_no'], trim($reason)), 'stock_counts', 'danger', 'stock_counts', (string) $id));
    }

    // ----------------------------------------------------------------- alerts

    /** Items at or below their minimum, told to the storekeeper once per level. Run by the clock. */
    public static function lowStockAlerts(): int
    {
        $raised = 0;
        foreach (self::db()->query("SELECT i.*, w.warehouse_name FROM inventory_items i LEFT JOIN warehouses w ON w.id = i.warehouse_id WHERE i.deleted_at IS NULL AND i.minimum_level > 0 AND i.quantity <= i.minimum_level")->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $key = sprintf('low-stock:%d:%s', (int) $item['id'], rtrim(rtrim(number_format((float) $item['quantity'], 2, '.', ''), '0'), '.'));
            Notifier::forCompany($item['company_id'] !== null ? (int) $item['company_id'] : null, static function () use ($key, $item, &$raised): void {
                Notifier::raiseOnce(
                    $key,
                    ['warehouse_manager', 'logistics_manager'],
                    'Stock below minimum',
                    sprintf('%s in %s: %s left, minimum %s.', $item['item_name'], $item['warehouse_name'] ?? 'the store', rtrim(rtrim(number_format((float) $item['quantity'], 2), '0'), '.'), rtrim(rtrim(number_format((float) $item['minimum_level'], 2), '0'), '.')),
                    'warehouse',
                    (float) $item['quantity'] <= 0 ? 'danger' : 'warning',
                    'warehouse',
                    (string) $item['id']
                );
                $raised++;
            });
        }

        return $raised;
    }
}
