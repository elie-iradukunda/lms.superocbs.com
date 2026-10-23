<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Fuel (section 11). A Kigali-Mombasa-Kigali trip gets 1,350 L: 1,200 L in
 * Kigali with F1 and 150 L in Mombasa as a top-up order (F2) to ERI Kenya.
 * The system plans both, orders both, and the station confirms the litres it
 * actually gave; the trip's consumption is checked against the kilometres when
 * it closes. The litres and the stations are on the route rate card.
 */
final class FuelOrders
{
    private static function db(): PDO
    {
        return Database::connection();
    }

    /** An authorized F1 or F2 fuel line becomes an order at the station. */
    public static function fromRequestLine(array $request, array $line): ?int
    {
        $exists = self::db()->prepare('SELECT id FROM fuel_orders WHERE request_id = ? AND trip_id <=> ? AND vehicle_id = ? AND status <> \'cancelled\'');
        $exists->execute([(int) $request['id'], $line['trip_id'] ?: null, (int) $line['vehicle_id']]);
        if (($id = $exists->fetchColumn()) !== false) {
            return (int) $id;
        }

        $extra = is_array($line['extra']) ? $line['extra'] : [];
        $supplierId = !empty($request['supplier_id']) ? (int) $request['supplier_id'] : (!empty($extra['supplier_id']) ? (int) $extra['supplier_id'] : null);
        self::db()->prepare(
            "INSERT INTO fuel_orders (company_id, order_code, request_id, trip_id, vehicle_id, driver_id, supplier_id, fuel_point, litres_ordered, standard_litres, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ordered')"
        )->execute([
            (int) $request['company_id'], trim($request['request_no'] . ' ' . ($line['plate_number'] ?? '')), (int) $request['id'],
            $line['trip_id'] ?: null, (int) $line['vehicle_id'], $line['driver_id'] ?: null, $supplierId,
            $extra['fuel_point'] ?? null, (float) $line['quantity'], $line['standard_quantity'],
        ]);
        $id = (int) self::db()->lastInsertId();

        if ($supplierId !== null) {
            $users = self::db()->prepare("SELECT id FROM users WHERE supplier_id = ? AND status = 'active' AND deleted_at IS NULL");
            $users->execute([$supplierId]);
            foreach ($users->fetchAll(PDO::FETCH_COLUMN) as $userId) {
                Notifier::toUser((int) $userId, 'Fuel order', sprintf('%s L for %s (%s). Confirm the litres given.', rtrim(rtrim(number_format((float) $line['quantity'], 2), '0'), '.'), $line['plate_number'] ?? '', $request['request_no']), 'portal', 'info', 'fuel_orders', (string) $id);
            }
        }

        return $id;
    }

    /** F2 authorized: its lines are ordered and the order goes to the station or sister company. */
    public static function orderAuthorized(array $request): void
    {
        foreach (RequestForms::lines((int) $request['id']) as $line) {
            if ($line['item_code'] === 'fuel' && (float) $line['quantity'] > 0 && !empty($line['vehicle_id'])) {
                self::fromRequestLine($request, $line);
            }
        }
        RequestFlow::sendToSupplier($request);
    }

    /**
     * At the turnaround point the top-up order (F2) is raised for the OM to
     * send with one tap: filled from the rate card, prepared but not sent.
     */
    public static function raiseTopUp(array $trip): ?int
    {
        if (empty($trip['route_id'])) {
            return null;
        }
        $already = self::db()->prepare(
            "SELECT r.id FROM form_requests r INNER JOIN form_request_lines l ON l.request_id = r.id
              WHERE r.form_code = 'F2' AND l.trip_id = ? AND r.status <> 'cancelled' LIMIT 1"
        );
        $already->execute([(int) $trip['id']]);
        if (($existing = $already->fetchColumn()) !== false) {
            return (int) $existing;
        }

        $standards = TripLife::standards((int) $trip['route_id'], (string) ($trip['fleet'] ?? 'all'));
        $topUps = array_filter($standards['fuel'], static fn (array $fuel): bool => ($fuel['fuel_on'] ?? '') === 'turnaround');
        if ($topUps === []) {
            return null;
        }
        $lines = [];
        $supplierId = null;
        foreach ($topUps as $fuel) {
            $supplierId ??= $fuel['supplier_id'] ? (int) $fuel['supplier_id'] : null;
            $lines[] = [
                'item_code' => 'fuel', 'description' => 'Fuel top-up at ' . ($fuel['fuel_point'] ?: $trip['turnaround_point']),
                'trip_id' => (int) $trip['id'], 'vehicle_id' => (int) $trip['vehicle_id'], 'trailer_id' => $trip['trailer_id'], 'driver_id' => (int) $trip['driver_id'],
                'quantity' => (float) $fuel['quantity'], 'unit' => 'L', 'standard_quantity' => (float) $fuel['quantity'],
                'extra' => ['fuel_point' => $fuel['fuel_point'], 'supplier_id' => $fuel['supplier_id']],
            ];
        }

        $id = Notifier::forCompany((int) $trip['company_id'], static function () use ($trip, $lines, $supplierId): int {
            $id = RequestForms::create([
                'form_code' => 'F2',
                'company_id' => (int) $trip['company_id'],
                'title' => sprintf('Fuel top-up at %s — %s', $trip['turnaround_point'] ?: 'the turnaround point', $trip['plate_number']),
                'route_id' => (int) $trip['route_id'],
                'trip_id' => (int) $trip['id'],
                'vehicle_id' => (int) $trip['vehicle_id'],
                'driver_id' => (int) $trip['driver_id'],
                'supplier_id' => $supplierId,
                'needed_on' => date('Y-m-d'),
            ], $lines);
            Notifier::toRole('logistics_manager', 'Fuel top-up to send', sprintf('%s reached %s. The top-up order is ready: open it and send it with one tap.', $trip['plate_number'], $trip['turnaround_point'] ?: 'the turnaround point'), 'form_requests', 'warning', 'form_requests', (string) $id);

            return $id;
        });

        TripLife::event((int) $trip['id'], 'top_up_raised', 'system', null, 'Fuel top-up order prepared for the OM');

        return $id;
    }

    /** The station (or whoever was there) confirms the litres actually given. */
    public static function confirmDelivered(int $orderId, float $litres, ?float $unitPrice = null, string $note = ''): void
    {
        $statement = self::db()->prepare('SELECT * FROM fuel_orders WHERE id = ?');
        $statement->execute([$orderId]);
        $order = $statement->fetch(PDO::FETCH_ASSOC);
        if ($order === false) {
            throw new \RuntimeException('That fuel order does not exist.');
        }
        if (!in_array($order['status'], ['ordered', 'delivered'], true)) {
            throw new \RuntimeException('This fuel order is ' . $order['status'] . '.');
        }
        if ($litres <= 0) {
            throw new \RuntimeException('Enter the litres given.');
        }
        self::db()->prepare("UPDATE fuel_orders SET litres_delivered = ?, unit_price = COALESCE(?, unit_price), delivered_at = NOW(), confirmed_by = ?, status = 'delivered', notes = COALESCE(NULLIF(?, ''), notes) WHERE id = ?")
            ->execute([$litres, $unitPrice, \current_user_id(), trim($note), $orderId]);
        if (!empty($order['trip_id'])) {
            TripLife::event((int) $order['trip_id'], 'fuel_given', 'system', ['quantity' => $litres], sprintf('%s L given at %s', rtrim(rtrim(number_format($litres, 2), '0'), '.'), $order['fuel_point'] ?: 'the station'));
        }
        if (abs($litres - (float) $order['litres_ordered']) > 0.004) {
            Notifier::forCompany((int) $order['company_id'], static fn () => Notifier::toRoles(['logistics_manager', 'finance'], 'Fuel given differs from the order', sprintf('%s: %s L ordered, %s L given.', $order['order_code'], rtrim(rtrim(number_format((float) $order['litres_ordered'], 2), '0'), '.'), rtrim(rtrim(number_format($litres, 2), '0'), '.')), 'fuel_orders', 'warning', 'fuel_orders', (string) $orderId));
        }
        AuditLog::record('fuel.delivered', 'fuel_orders', (string) $order['order_code'], $note ?: null, ['litres' => $litres]);
    }
}
