<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * What is in the shed, and where yesterday's load went.
 *
 * A depot manager is asked two questions and until now could answer neither.
 * The stock ledger next door counts the company's own things — tyres, oil,
 * spare parts — which are bought, valued and consumed. A customer's 200 bundles
 * are none of those: we never own them, they are never worth anything in our
 * books, and they leave exactly as they arrived. Adding them to the same balance
 * would make the spare-parts valuation nonsense.
 *
 * So cargo has its own register, and it is a log rather than a balance. Each
 * arrival and each departure is a row carrying the document behind it, which is
 * what lets the answer be "it left on TRP-2026-0003 at 06:14" instead of a
 * number that went down. What is on hand is worked out from the rows.
 *
 * The four moments, in the order a load meets them:
 *
 *   received   the customer brings it to the depot          in
 *   loaded     it goes onto a truck                         out
 *   arrived    it comes off the truck at its destination    in
 *   collected  the customer takes it away                   out
 */
final class CargoCustody
{
    /** Cargo arriving at the depot it will be collected from. */
    public static function received(int $shipmentId, ?string $at = null): bool
    {
        $shipment = self::shipment($shipmentId);
        if ($shipment === null || $shipment['origin_warehouse_id'] === null) {
            return false;
        }

        return self::log($shipment, (int) $shipment['origin_warehouse_id'], 'in', 'received', $at, [
            'trip_id' => $shipment['trip_id'] !== null ? (int) $shipment['trip_id'] : null,
        ]);
    }

    /** Onto the truck. Everything on this trip leaves the depot it was held at. */
    public static function loaded(int $tripId, ?string $at = null): int
    {
        $moved = 0;

        foreach (self::shipmentsOn($tripId) as $shipment) {
            if ($shipment['origin_warehouse_id'] === null) {
                continue;
            }

            // A load can only leave a depot it was recorded as reaching. Booking
            // it straight onto a truck is normal — the customer drove it to the
            // yard that morning — so the arrival is written first rather than
            // refused.
            self::log($shipment, (int) $shipment['origin_warehouse_id'], 'in', 'received', $at, [
                'trip_id' => $tripId,
                'notes' => 'Recorded on dispatch; no separate delivery to the depot was logged.',
            ]);

            $moved += self::log($shipment, (int) $shipment['origin_warehouse_id'], 'out', 'loaded', $at, [
                'trip_id' => $tripId,
            ]) ? 1 : 0;
        }

        return $moved;
    }

    /** Off the truck at the depot the customer collects from. */
    public static function arrived(int $shipmentId, ?int $warehouseId = null, ?int $deliveryId = null, ?string $at = null): bool
    {
        $shipment = self::shipment($shipmentId);
        if ($shipment === null) {
            return false;
        }

        $warehouseId ??= $shipment['destination_warehouse_id'] !== null ? (int) $shipment['destination_warehouse_id'] : null;
        if ($warehouseId === null) {
            return false;
        }

        return self::log($shipment, $warehouseId, 'in', 'arrived', $at, [
            'trip_id' => $shipment['trip_id'] !== null ? (int) $shipment['trip_id'] : null,
            'delivery_id' => $deliveryId,
        ]);
    }

    /** The customer takes it away, and the depot is clear of it. */
    public static function collected(int $shipmentId, ?int $warehouseId = null, ?int $deliveryId = null, ?string $at = null): bool
    {
        $shipment = self::shipment($shipmentId);
        if ($shipment === null) {
            return false;
        }

        $warehouseId ??= $shipment['destination_warehouse_id'] !== null ? (int) $shipment['destination_warehouse_id'] : null;
        if ($warehouseId === null) {
            return false;
        }

        // Handed over straight off the tailboard, with no depot stop recorded:
        // write the arrival so the departure has something to answer to.
        self::log($shipment, $warehouseId, 'in', 'arrived', $at, [
            'trip_id' => $shipment['trip_id'] !== null ? (int) $shipment['trip_id'] : null,
            'delivery_id' => $deliveryId,
            'notes' => 'Recorded on handover; the load was not logged into the depot separately.',
        ]);

        return self::log($shipment, $warehouseId, 'out', 'collected', $at, [
            'trip_id' => $shipment['trip_id'] !== null ? (int) $shipment['trip_id'] : null,
            'delivery_id' => $deliveryId,
        ]);
    }

    /**
     * What is standing in a depot now: the consignments whose arrivals have not
     * been answered by a departure.
     *
     * @return list<array<string, mixed>>
     */
    public static function onHand(int $warehouseId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT s.id, s.shipment_code, s.cargo_description, s.status,
                    c.customer_name,
                    SUM(CASE WHEN wc.direction = 'in' THEN wc.packages ELSE -wc.packages END) AS packages,
                    SUM(CASE WHEN wc.direction = 'in' THEN wc.weight_kg ELSE -wc.weight_kg END) AS weight_kg,
                    MAX(wc.moved_at) AS since
               FROM warehouse_cargo wc
               INNER JOIN shipments s ON s.id = wc.shipment_id
               LEFT JOIN customers c ON c.id = s.customer_id
              WHERE wc.warehouse_id = ? AND s.deleted_at IS NULL
              GROUP BY s.id, s.shipment_code, s.cargo_description, s.status, c.customer_name
             HAVING SUM(CASE WHEN wc.direction = 'in' THEN wc.weight_kg ELSE -wc.weight_kg END) > 0
              ORDER BY since DESC"
        );
        $statement->execute([$warehouseId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * The totals behind the depot page: how many consignments, how heavy, how
     * many packages.
     *
     * @return array{loads: int, packages: int, weight: float}
     */
    public static function heldAt(int $warehouseId): array
    {
        $loads = self::onHand($warehouseId);

        return [
            'loads' => count($loads),
            'packages' => (int) array_sum(array_column($loads, 'packages')),
            'weight' => (float) array_sum(array_column($loads, 'weight_kg')),
        ];
    }

    /**
     * Everywhere one consignment has been, in order.
     *
     * @return list<array<string, mixed>>
     */
    public static function trail(int $shipmentId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT wc.*, w.warehouse_name, t.reference_code AS trip_code, u.full_name AS performed_by_name
               FROM warehouse_cargo wc
               INNER JOIN warehouses w ON w.id = wc.warehouse_id
               LEFT JOIN trips t ON t.id = wc.trip_id
               LEFT JOIN users u ON u.id = wc.performed_by
              WHERE wc.shipment_id = ?
              ORDER BY wc.moved_at, wc.id'
        );
        $statement->execute([$shipmentId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** In the words a depot manager would use. */
    public static function reasonLabel(string $reason, string $direction): string
    {
        return match ($reason) {
            'received' => 'Brought in by the customer',
            'loaded' => 'Loaded onto a truck',
            'arrived' => 'Arrived off a truck',
            'collected' => 'Collected by the customer',
            'returned' => 'Returned to the depot',
            default => ($direction === 'in' ? 'In' : 'Out'),
        };
    }

    /**
     * Writes one movement, unless that exact step is already recorded.
     *
     * Pressing dispatch twice, or re-running a repair, must not take the same
     * load out of the same depot twice. The unique key on the table is what
     * enforces that; this reads the refusal as "already done" rather than as an
     * error, because that is what it means.
     *
     * @param array<string, mixed> $shipment
     * @param array{trip_id?: ?int, delivery_id?: ?int, notes?: string} $extra
     */
    private static function log(array $shipment, int $warehouseId, string $direction, string $reason, ?string $at, array $extra = []): bool
    {
        $db = Database::connection();

        $statement = $db->prepare(
            'INSERT IGNORE INTO warehouse_cargo
                (movement_code, warehouse_id, shipment_id, direction, reason, packages, weight_kg, trip_id, delivery_id, moved_at, performed_by, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $statement->execute([
            Reference::next('CGO', 'warehouse_cargo', 'movement_code'),
            $warehouseId,
            (int) $shipment['id'],
            $direction,
            $reason,
            $shipment['packages_count'] !== null ? (int) $shipment['packages_count'] : null,
            (float) ($shipment['weight_kg'] ?? 0),
            $extra['trip_id'] ?? null,
            $extra['delivery_id'] ?? null,
            $at ?? date('Y-m-d H:i:s'),
            \current_user_id(),
            $extra['notes'] ?? null,
        ]);

        return $statement->rowCount() > 0;
    }

    private static function shipment(int $shipmentId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, shipment_code, trip_id, packages_count, weight_kg, origin_warehouse_id, destination_warehouse_id
               FROM shipments WHERE id = ? AND deleted_at IS NULL'
        );
        $statement->execute([$shipmentId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    private static function shipmentsOn(int $tripId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT id, shipment_code, trip_id, packages_count, weight_kg, origin_warehouse_id, destination_warehouse_id
               FROM shipments
              WHERE trip_id = ? AND deleted_at IS NULL AND status <> 'cancelled'
              ORDER BY id"
        );
        $statement->execute([$tripId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
