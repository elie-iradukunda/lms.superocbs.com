<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Goods of ours that leave on a truck.
 *
 * A customer who asks us to carry their own cement is one thing; a customer who
 * orders eight tyres we hold in Kigali is another. The second is stock leaving
 * the company, and if the shed does not lose them the balance is a lie — the
 * next person to order against that number is ordering something that is not
 * there any more.
 *
 * So a consignment may name an item and a quantity, and dispatching the truck
 * issues them through the ordinary stock ledger: the same movements, the same
 * running balance, the same audit trail as any other issue, with the trip as
 * the document behind it.
 *
 * Two things are refused rather than discovered later:
 *
 *   dispatching more than the shed holds    checked before the truck leaves,
 *                                           where it can still be fixed
 *   issuing the same goods twice            a second dispatch finds the work
 *                                           already stamped and does nothing
 */
final class StockIssue
{
    /**
     * Why this trip must not leave yet, or null when it may.
     *
     * Read before anything is written, so a refusal changes nothing.
     */
    public static function guardTrip(int $tripId): ?string
    {
        foreach (self::pending($tripId) as $load) {
            $wanted = (float) $load['stock_quantity'];
            $held = (float) $load['quantity'];

            if ($wanted <= 0) {
                return sprintf('%s names %s but no quantity. Set how much is being carried.', $load['shipment_code'], $load['item_name']);
            }

            if ($wanted > $held) {
                return sprintf(
                    '%s carries %s of %s, but %s holds only %s.',
                    $load['shipment_code'],
                    self::number($wanted),
                    $load['item_name'],
                    $load['warehouse_name'] ?: 'the depot',
                    self::number($held)
                );
            }

            // Carrying stock out of a depot that does not hold it is not a
            // shortage, it is the wrong depot, and the message has to say so.
            if ((int) $load['origin_warehouse_id'] > 0 && (int) $load['item_warehouse_id'] !== (int) $load['origin_warehouse_id']) {
                return sprintf(
                    '%s is held at %s, but %s is loading at %s.',
                    $load['item_name'],
                    $load['warehouse_name'] ?: 'another depot',
                    $load['shipment_code'],
                    $load['origin_name'] ?: 'a different depot'
                );
            }
        }

        return null;
    }

    /**
     * Takes the goods out of the shed, one movement per consignment.
     *
     * @return int how many consignments were issued
     */
    public static function issueTrip(int $tripId, string $tripCode): int
    {
        $db = Database::connection();
        $issued = 0;

        foreach (self::pending($tripId) as $load) {
            StockLedger::record(
                (int) $load['stock_item_id'],
                'stock_out',
                (float) $load['stock_quantity'],
                $load['unit_cost'] !== null ? (float) $load['unit_cost'] : null,
                'trip',
                $tripCode,
                null,
                sprintf('Carried to %s on %s', $load['destination'], $load['shipment_code'])
            );

            // Stamped so a second dispatch of the same trip finds nothing left
            // to do, whatever order the buttons are pressed in.
            $db->prepare('UPDATE shipments SET stock_issued_at = NOW() WHERE id = ?')->execute([(int) $load['id']]);
            $issued++;
        }

        return $issued;
    }

    /**
     * Consignments on this trip that name our stock and have not been issued.
     *
     * @return list<array<string, mixed>>
     */
    private static function pending(int $tripId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT s.id, s.shipment_code, s.destination, s.origin_warehouse_id,
                    s.stock_item_id, s.stock_quantity,
                    i.item_name, i.quantity, i.unit_cost, i.warehouse_id AS item_warehouse_id,
                    w.warehouse_name, o.warehouse_name AS origin_name
               FROM shipments s
               INNER JOIN inventory_items i ON i.id = s.stock_item_id
               LEFT JOIN warehouses w ON w.id = i.warehouse_id
               LEFT JOIN warehouses o ON o.id = s.origin_warehouse_id
              WHERE s.trip_id = ?
                AND s.deleted_at IS NULL
                AND s.status <> 'cancelled'
                AND s.stock_issued_at IS NULL
              ORDER BY s.id"
        );
        $statement->execute([$tripId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Whole numbers stay whole: 8 tyres, not 8.00 tyres. */
    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ','), '0'), '.');
    }
}
