<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;
use Support\Mailer;

/**
 * The trip sheet a driver leaves with.
 *
 * A driver used to be told a trip existed and nothing else. Everything he needs
 * on the road — which loads are behind him, who receives each one, which phone
 * to ring at the depot, where the truck is expected and when — lived on screens
 * he cannot open from the cab.
 *
 * So dispatching now sends him the sheet: the trip, the loads, the deliveries
 * and the stops, in the order he will meet them. It is the one message he can
 * work from without the office.
 */
final class DriverMail
{
    /**
     * The trip is ready to leave: every load has passed its final check and
     * nothing the system checks is wrong. The driver gets the full report (his
     * copy, no prices) by email and on the bell, and is told to press Start
     * trip on his phone when he drives out. Sent once per trip.
     */
    public static function tripReady(int $tripId): bool
    {
        $trip = self::trip($tripId);
        if ($trip === null || !in_array((string) $trip['status'], ['requested', 'approved', 'loading'], true) || (int) $trip['load_count'] === 0) {
            return false;
        }
        $pending = Database::connection()->prepare(
            "SELECT COUNT(*) FROM shipments s
              LEFT JOIN shipment_pre_dispatch_checks pc ON pc.shipment_id = s.id
              WHERE s.trip_id = ? AND s.deleted_at IS NULL AND s.status <> 'cancelled' AND (pc.status IS NULL OR pc.status <> 'ready')"
        );
        $pending->execute([$tripId]);
        $analysis = DispatchReadiness::forTrip($tripId);
        if ((int) $pending->fetchColumn() > 0 || $analysis === null || !$analysis['ready']) {
            return false;
        }

        $driverUser = Database::connection()->prepare('SELECT user_id FROM drivers WHERE id = ?');
        $driverUser->execute([(int) $trip['driver_id']]);
        $userId = $driverUser->fetchColumn();
        Notifier::toUser($userId !== false && $userId !== null ? (int) $userId : null, 'Your trip is ready',
            sprintf('%s, %s to %s, is ready. Read the trip report, then press Start trip on My trips when you drive out.', $trip['reference_code'], $trip['pickup_location'], $trip['destination']),
            'my-trips', 'success', 'trips', (string) $trip['reference_code']);

        if (trim((string) $trip['email']) === '') {
            return false;
        }
        $report = TripReport::store($tripId, false);
        $result = Mailer::send([
            'key' => 'trip-ready-' . $tripId,
            'category' => 'notification',
            'to' => trim((string) $trip['email']),
            'to_name' => (string) $trip['driver_name'],
            'subject' => sprintf('Your trip %s is ready — %s to %s', $trip['reference_code'], $trip['pickup_location'], $trip['destination']),
            'heading' => 'Your trip is ready: ' . $trip['reference_code'],
            'lines' => [
                sprintf('Dear %s,', $trip['driver_name'] ?: 'Driver'),
                sprintf('%s from %s to %s has passed its final check. The full trip report is attached: the loads, the route and every stop, the papers you carry and who to call.', $trip['reference_code'], $trip['pickup_location'], $trip['destination']),
                'When you drive out, open My trips on your phone and press Start trip. The time is recorded and the office and the customer are told you are on your way.',
                'At each stop on the way, press I have arrived when you get there and Leaving when you go on. If you need anything (money, help with the truck, a problem at the border), press Ask for support.',
            ],
            'facts' => array_filter([
                'Truck' => (string) ($trip['plate_number'] ?? ''),
                'Weight on board' => self::kg((float) $trip['total_weight']),
                'Planned departure' => self::when((string) $trip['planned_departure_at']),
                'Expected at destination' => self::when((string) $trip['planned_arrival_at']),
            ], static fn (string $value): bool => $value !== ''),
            'action' => ['label' => 'Open My trips', 'path' => 'my-trips'],
            'attachments' => $report !== null ? array_slice($report, 0, 1) : [],
            'signature' => sprintf('Sent by %s. You may reply to this message and it will reach the office.', \company_name()),
            'entity_type' => 'trips',
            'entity_id' => (string) $trip['reference_code'],
        ]);

        return (bool) $result['queued'];
    }

    /** The full sheet for a trip that has just been dispatched. */
    public static function tripSheet(int $tripId): bool
    {
        $trip = self::trip($tripId);
        if ($trip === null || trim((string) $trip['email']) === '') {
            return false;
        }

        $from = trim((string) $trip['pickup_location']) ?: 'the depot';
        $to = trim((string) $trip['destination']) ?: 'the destination';
        $blocks = [self::tripBlock($trip)];

        $loads = self::loads($tripId);
        if ($loads !== []) {
            $blocks[] = self::loadsBlock($loads);
        }

        $drops = self::drops($tripId);
        if ($drops !== []) {
            $blocks[] = self::dropsBlock($drops);
        }

        $stops = self::stops($tripId);
        if ($stops !== []) {
            $blocks[] = self::stopsBlock($stops);
        }

        // The full trip report goes with it, as a PDF he can open in the cab,
        // without prices: the driver's copy.
        $report = TripReport::store($tripId, false);
        $attachments = $report !== null ? array_slice($report, 0, 1) : [];

        $result = Mailer::send([
            'attachments' => $attachments,
            'key' => 'tripsheet-' . $tripId,
            'category' => 'notification',
            'to' => trim((string) $trip['email']),
            'to_name' => (string) $trip['driver_name'],
            'subject' => sprintf('Trip sheet %s — %s to %s', $trip['reference_code'], $from, $to),
            'heading' => sprintf('Trip sheet — %s', $trip['reference_code']),
            'lines' => [
                sprintf('Dear %s,', $trip['driver_name'] ?: 'Driver'),
                sprintf(
                    'You are dispatched on %s from %s to %s. Everything you are carrying is set out below. Please read it before you leave the yard.',
                    $trip['reference_code'],
                    $from,
                    $to
                ),
            ],
            'items' => $blocks,
            'facts' => array_filter([
                'Total weight on board' => self::kg((float) $trip['total_weight']),
                'Vehicle capacity' => $trip['capacity_kg'] !== null ? self::kg((float) $trip['capacity_kg']) : '',
                'Expected at destination' => self::when((string) $trip['planned_arrival_at']),
            ], static fn (string $value): bool => $value !== ''),
            'closing' => array_values(array_filter([
                'Check each load against this sheet before you leave, and again before you hand anything over.',
                'Collect a signature and keep the proof for every drop. A load without a signature is not delivered.',
                trim((string) $trip['notes']) !== '' ? 'Note from the office: ' . trim((string) $trip['notes']) : '',
                'Ring the office if anything on this sheet does not match what is on the vehicle.',
            ])),
            'signature' => sprintf('Sent by %s. You may reply to this message and it will reach the office.', \company_name()),
            'entity_type' => 'trips',
            'entity_id' => (string) $trip['reference_code'],
        ]);

        return (bool) $result['queued'];
    }

    /** @param array<string, mixed> $trip */
    private static function tripBlock(array $trip): array
    {
        return [
            'title' => 'This trip',
            'columns' => ['detail' => 'Detail', 'value' => 'Value'],
            'numeric' => [],
            'rows' => [
                ['detail' => 'Trip reference', 'value' => (string) $trip['reference_code']],
                ['detail' => 'Vehicle', 'value' => (string) ($trip['plate_number'] ?: 'To be confirmed')],
                ['detail' => 'Route', 'value' => sprintf('%s to %s', $trip['pickup_location'], $trip['destination'])],
                ['detail' => 'Leaving', 'value' => self::when((string) ($trip['departure_at'] ?: $trip['planned_departure_at']))],
                ['detail' => 'Expected to arrive', 'value' => self::when((string) $trip['planned_arrival_at'])],
                ['detail' => 'Loads on board', 'value' => (string) (int) $trip['load_count']],
            ],
        ];
    }

    /** @param list<array<string, mixed>> $loads */
    private static function loadsBlock(array $loads): array
    {
        $rows = [];
        foreach ($loads as $load) {
            $rows[] = [
                'ref' => (string) $load['shipment_code'],
                'customer' => (string) ($load['customer_name'] ?: 'Internal'),
                'goods' => (string) $load['cargo_description'],
                'weight' => self::kg((float) $load['weight_kg']),
                'drop' => self::place($load, 'to_depot', 'destination'),
            ];
        }

        return [
            'title' => 'What you are carrying',
            'columns' => ['ref' => 'Consignment', 'customer' => 'Customer', 'goods' => 'Goods', 'weight' => 'Weight', 'drop' => 'Drops at'],
            'numeric' => ['weight'],
            'rows' => $rows,
        ];
    }

    /** @param list<array<string, mixed>> $drops */
    private static function dropsBlock(array $drops): array
    {
        $rows = [];
        foreach ($drops as $drop) {
            $rows[] = [
                'ref' => (string) $drop['delivery_code'],
                'recipient' => (string) $drop['recipient_name'],
                'phone' => (string) ($drop['recipient_phone'] ?: 'Not given'),
                'where' => self::place($drop, 'to_depot', 'destination'),
                'when' => self::when((string) $drop['planned_at']),
            ];
        }

        return [
            'title' => 'Who signs for it',
            'columns' => ['ref' => 'Delivery', 'recipient' => 'Recipient', 'phone' => 'Phone', 'where' => 'Address', 'when' => 'Planned'],
            'numeric' => [],
            'rows' => $rows,
        ];
    }

    /** @param list<array<string, mixed>> $stops */
    private static function stopsBlock(array $stops): array
    {
        $rows = [];
        foreach ($stops as $stop) {
            $contact = trim(((string) $stop['contact_name']) . ' ' . ((string) $stop['contact_phone']));
            $rows[] = [
                'type' => Schema::label((string) $stop['stop_type']),
                'where' => (string) $stop['location_name'],
                'contact' => $contact !== '' ? $contact : 'None',
                'when' => self::when((string) $stop['planned_arrival_at']),
            ];
        }

        return [
            'title' => 'Your route',
            'columns' => ['type' => 'Stop', 'where' => 'Location', 'contact' => 'Contact', 'when' => 'Planned'],
            'numeric' => [],
            'rows' => $rows,
        ];
    }

    private static function trip(int $tripId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT t.*, v.plate_number, v.capacity_kg,
                    d.full_name AS driver_name, u.email,
                    (SELECT COUNT(*) FROM shipments s WHERE s.trip_id = t.id AND s.deleted_at IS NULL) AS load_count,
                    (SELECT COALESCE(SUM(s.weight_kg), 0) FROM shipments s WHERE s.trip_id = t.id AND s.deleted_at IS NULL) AS total_weight
               FROM trips t
               LEFT JOIN vehicles v ON v.id = t.vehicle_id
               LEFT JOIN drivers d ON d.id = t.driver_id
               LEFT JOIN users u ON u.id = d.user_id
              WHERE t.id = ? AND t.deleted_at IS NULL'
        );
        $statement->execute([$tripId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    private static function loads(int $tripId): array
    {
        return self::rows(
            "SELECT s.shipment_code, s.cargo_description, s.weight_kg, s.destination,
                    c.customer_name, w.warehouse_name AS to_depot
               FROM shipments s
               LEFT JOIN customers c ON c.id = s.customer_id
               LEFT JOIN warehouses w ON w.id = s.destination_warehouse_id
              WHERE s.trip_id = ? AND s.deleted_at IS NULL AND s.status <> 'cancelled'
              ORDER BY s.id",
            $tripId
        );
    }

    /** @return list<array<string, mixed>> */
    private static function drops(int $tripId): array
    {
        return self::rows(
            "SELECT d.delivery_code, d.recipient_name, d.recipient_phone, d.destination, d.planned_at,
                    w.warehouse_name AS to_depot
               FROM deliveries d
               LEFT JOIN warehouses w ON w.id = d.destination_warehouse_id
              WHERE d.trip_id = ? AND d.deleted_at IS NULL AND d.status <> 'failed'
              ORDER BY d.planned_at IS NULL, d.planned_at, d.id",
            $tripId
        );
    }

    /** @return list<array<string, mixed>> */
    private static function stops(int $tripId): array
    {
        return self::rows(
            'SELECT stop_type, location_name, contact_name, contact_phone, planned_arrival_at
               FROM trip_stops WHERE trip_id = ? ORDER BY stop_sequence, id',
            $tripId
        );
    }

    /** @return list<array<string, mixed>> */
    private static function rows(string $sql, int $id): array
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute([$id]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** The depot's name when there is one, otherwise whatever was typed. */
    private static function place(array $row, string $depotColumn, string $textColumn): string
    {
        $depot = trim((string) ($row[$depotColumn] ?? ''));

        return $depot !== '' ? $depot : (string) ($row[$textColumn] ?? '');
    }

    private static function when(string $stamp): string
    {
        $stamp = trim($stamp);

        return $stamp === '' ? 'To be confirmed' : date('j F Y, H:i', (int) strtotime($stamp));
    }

    private static function kg(float $weight): string
    {
        return rtrim(rtrim(number_format($weight, 2, '.', ','), '0'), '.') . ' kg';
    }
}
