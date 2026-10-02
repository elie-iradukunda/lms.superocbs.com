<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * The garage side of a work order.
 *
 * A job card says what the driver reported, what the mechanic found and what
 * was done. Parts on it can come off our own shelves: those are named by stock
 * item, priced at what the stock cost, and taken out of the store when the job
 * is approved — so the shelf, the job's cost and the books all say the same.
 */
final class Workshop
{
    private static function db(): PDO
    {
        return Database::connection();
    }

    /** Part lines that come from our stock and have not been issued yet. */
    public static function stockLines(int $orderId): array
    {
        $statement = self::db()->prepare(
            'SELECT p.id, p.item_id, p.quantity, p.unit_cost, p.part_name, i.item_name, i.quantity AS on_hand, i.unit_cost AS item_cost, w.warehouse_name
               FROM maintenance_parts p
               INNER JOIN inventory_items i ON i.id = p.item_id
               LEFT JOIN warehouses w ON w.id = i.warehouse_id
               INNER JOIN maintenance_orders m ON m.id = p.maintenance_id
              WHERE p.maintenance_id = ? AND m.stock_issued_at IS NULL
              ORDER BY p.id'
        );
        try {
            $statement->execute([$orderId]);
        } catch (\Throwable) {
            // Before the job-card columns exist no line comes from stock.
            return [];
        }

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Why the job cannot be closed yet, or null. Read before anything is written. */
    public static function guardComplete(int $orderId): ?string
    {
        $wanted = [];
        foreach (self::stockLines($orderId) as $line) {
            $id = (int) $line['item_id'];
            $wanted[$id] = ($wanted[$id] ?? 0) + (float) $line['quantity'];
            if ($wanted[$id] > (float) $line['on_hand'] + 0.004) {
                return sprintf('The job needs %s of %s but %s holds only %s.',
                    self::number($wanted[$id]), $line['item_name'], $line['warehouse_name'] ?: 'the store', self::number((float) $line['on_hand']));
            }
        }

        return null;
    }

    /**
     * Takes the parts off the shelf. Stamped on the order so approving twice,
     * or reopening and approving again, never issues the same parts twice.
     */
    public static function issueStock(int $orderId, string $code): int
    {
        $lines = self::stockLines($orderId);
        foreach ($lines as $line) {
            StockLedger::record(
                (int) $line['item_id'],
                'stock_out',
                (float) $line['quantity'],
                (float) $line['unit_cost'] > 0 ? (float) $line['unit_cost'] : ($line['item_cost'] !== null ? (float) $line['item_cost'] : null),
                'maintenance',
                $code,
                null,
                'Used on work order ' . $code
            );
        }

        if ($lines !== []) {
            self::db()->prepare('UPDATE maintenance_orders SET stock_issued_at = NOW() WHERE id = ? AND stock_issued_at IS NULL')->execute([$orderId]);
        }

        return count($lines);
    }

    /**
     * After the lines are saved: a stock part with no price takes the stock
     * card's cost and name, so nobody looks either up.
     */
    public static function afterLines(int $orderId): void
    {
        try {
            self::db()->prepare(
                "UPDATE maintenance_parts p
                   INNER JOIN inventory_items i ON i.id = p.item_id
                    SET p.unit_cost = COALESCE(i.unit_cost, 0),
                        p.line_type = 'part'
                  WHERE p.maintenance_id = ? AND p.unit_cost = 0"
            )->execute([$orderId]);
        } catch (\Throwable) {
            // Before parts could name a stock item there is nothing to price.
            return;
        }

        $total = (float) self::db()->query('SELECT COALESCE(SUM(line_total), 0) FROM maintenance_parts WHERE maintenance_id = ' . $orderId)->fetchColumn();
        self::db()->prepare('UPDATE maintenance_orders SET actual_cost = ? WHERE id = ?')->execute([$total > 0 ? $total : null, $orderId]);
    }

    /** What the parts from our own stock cost, for the ledger. */
    public static function stockCost(int $orderId): float
    {
        try {
            return round((float) self::db()->query(
                'SELECT COALESCE(SUM(line_total), 0) FROM maintenance_parts WHERE item_id IS NOT NULL AND maintenance_id = ' . $orderId
            )->fetchColumn(), 2);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /** The odometer and the next service the mechanic wrote on the card go onto the truck. */
    public static function updateVehicle(array $order): void
    {
        if (empty($order['vehicle_id'])) {
            return;
        }

        $db = self::db();
        if (!empty($order['odometer_reading'])) {
            $db->prepare('UPDATE vehicles SET mileage = GREATEST(COALESCE(mileage, 0), ?) WHERE id = ?')->execute([(int) $order['odometer_reading'], (int) $order['vehicle_id']]);
        }
        if (!empty($order['next_service_date'])) {
            $db->prepare('UPDATE vehicles SET next_service_date = ? WHERE id = ?')->execute([(string) $order['next_service_date'], (int) $order['vehicle_id']]);
        }
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ','), '0'), '.');
    }
}
