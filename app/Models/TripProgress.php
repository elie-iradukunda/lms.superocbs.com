<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * A truck on the road, as its driver reports it: each stop reached and left,
 * with the time it happened, the kilometres on the clock, how things are and
 * a note. That is what moves the trip report forward and tells the company
 * where the truck is, without a phone call.
 */
final class TripProgress
{
    public const CONDITIONS = ['fine' => 'All fine', 'problem' => 'Small problem', 'help' => 'Need help'];

    /** Who hears that a truck reached a stop: the people who run the trucks. */
    private const OFFICE = ['super_admin', 'logistics_manager', 'fleet_manager'];

    /**
     * How far the truck has driven on this trip, from the kilometres the
     * driver gave when he started and at each stop since.
     *
     * @return array{start_km: ?int, last_km: ?int, last_at: ?string, last_place: ?string, driven: ?int, end_km: ?int}
     */
    public static function distance(int $tripId): array
    {
        $db = Database::connection();
        $trip = $db->prepare('SELECT start_mileage, end_mileage, departure_at, dispatched_at, pickup_location FROM trips WHERE id = ?');
        $trip->execute([$tripId]);
        $t = $trip->fetch(PDO::FETCH_ASSOC) ?: [];
        $start = isset($t['start_mileage']) && $t['start_mileage'] !== null ? (int) $t['start_mileage'] : null;

        $reading = $db->prepare('SELECT location_name, arrival_mileage, actual_arrival_at FROM trip_stops WHERE trip_id = ? AND arrival_mileage IS NOT NULL ORDER BY arrival_mileage DESC, actual_arrival_at DESC LIMIT 1');
        $reading->execute([$tripId]);
        $r = $reading->fetch(PDO::FETCH_ASSOC) ?: null;

        $lastKm = $r !== null ? (int) $r['arrival_mileage'] : $start;
        $end = isset($t['end_mileage']) && $t['end_mileage'] !== null ? (int) $t['end_mileage'] : null;

        return [
            'start_km' => $start,
            'last_km' => $lastKm,
            'last_at' => $r !== null ? (string) $r['actual_arrival_at'] : (($t['departure_at'] ?? null) ?: ($t['dispatched_at'] ?? null)),
            'last_place' => $r !== null ? (string) $r['location_name'] : ($start !== null ? 'the start' : null),
            'driven' => $start !== null && $lastKm !== null ? max(0, ($end ?? $lastKm) - $start) : null,
            'end_km' => $end,
        ];
    }

    /**
     * Records "arrived" or "leaving" at one stop of a trip.
     *
     * @param array{note?: string, mileage?: string, condition?: string} $input what the driver filled in
     * @return array{ok: bool, message: string}
     */
    public static function stop(int $tripId, int $stopId, string $what, array $input = []): array
    {
        $db = Database::connection();
        $statement = $db->prepare('SELECT s.*, t.reference_code, t.destination, t.vehicle_id FROM trip_stops s INNER JOIN trips t ON t.id = s.trip_id WHERE s.id = ? AND s.trip_id = ?');
        $statement->execute([$stopId, $tripId]);
        $stop = $statement->fetch(PDO::FETCH_ASSOC);
        if ($stop === false) {
            return ['ok' => false, 'message' => 'That stop is not on this trip.'];
        }
        $code = (string) $stop['reference_code'];
        $place = (string) $stop['location_name'];
        $driver = \current_user_name() ?: 'The driver';
        $note = mb_substr(trim((string) ($input['note'] ?? '')), 0, 250);

        if ($what === 'arrive') {
            if (!empty($stop['actual_arrival_at'])) {
                return ['ok' => false, 'message' => sprintf('Your arrival at %s was already recorded at %s.', $place, date('H:i', (int) strtotime((string) $stop['actual_arrival_at'])))];
            }

            $condition = array_key_exists((string) ($input['condition'] ?? ''), self::CONDITIONS) ? (string) $input['condition'] : 'fine';
            $mileage = trim((string) ($input['mileage'] ?? ''));
            if ($mileage !== '' && !ctype_digit(str_replace([',', ' '], '', $mileage))) {
                return ['ok' => false, 'message' => 'Write the kilometres as a number, like 186900.'];
            }
            $km = $mileage === '' ? null : (int) str_replace([',', ' '], '', $mileage);
            $distance = self::distance($tripId);
            $flag = '';
            if ($km !== null && !empty($stop['vehicle_id'])) {
                $last = (int) $db->query('SELECT mileage FROM vehicles WHERE id = ' . (int) $stop['vehicle_id'])->fetchColumn();
                if ($last > 0 && $km < $last) {
                    return ['ok' => false, 'message' => sprintf('%s km is less than the truck\'s last reading of %s km. Check the clock and try again.', number_format($km), number_format($last))];
                }
                // No leg in the region is more than 2,000 km: a jump like that is a typing slip.
                $previous = $distance['last_km'] ?? ($last > 0 ? $last : null);
                if ($previous !== null && $km - $previous > 2000) {
                    return ['ok' => false, 'message' => sprintf('%s km is %s km more than the last reading (%s km). Check the clock and type it again.', number_format($km), number_format($km - $previous), number_format($previous))];
                }
                // Faster than a truck can go since the last reading: still recorded, flagged for the office.
                if ($previous !== null && $distance['last_at'] !== null) {
                    $hours = max(0.05, (time() - (int) strtotime((string) $distance['last_at'])) / 3600);
                    $speed = ($km - $previous) / $hours;
                    if ($speed > 110 && $km - $previous > 20) {
                        $flag = sprintf(' (check the reading: %s km in %s)', number_format($km - $previous), $hours < 1 ? round($hours * 60) . ' min' : round($hours, 1) . ' h');
                    }
                }
                $db->prepare('UPDATE vehicles SET mileage = GREATEST(COALESCE(mileage, 0), ?) WHERE id = ?')->execute([$km, (int) $stop['vehicle_id']]);
            }

            $db->prepare("UPDATE trip_stops SET actual_arrival_at = NOW(), status = 'arrived', arrival_mileage = ?, arrival_condition = ?,
                                 notes = TRIM(CONCAT(COALESCE(notes, ''), IF(? = '', '', CONCAT(IF(COALESCE(notes, '') = '', '', '\n'), 'Arrived: ', ?)))) WHERE id = ?")
               ->execute([$km, $condition, $note, $note, $stopId]);
            AuditLog::record('trip.stop_arrived', 'trips', $code, null, ['stop' => $place, 'mileage' => $km, 'condition' => $condition]);
            BorderCrossing::stopReported($tripId, $place);

            $last = $db->prepare('SELECT id FROM trip_stops WHERE trip_id = ? ORDER BY stop_sequence DESC, id DESC LIMIT 1');
            $last->execute([$tripId]);
            $atDestination = (int) $last->fetchColumn() === $stopId || $stop['stop_type'] === 'dropoff';

            // How far the truck has come: since the start, and since the last place it reported.
            $driven = '';
            if ($km !== null && $distance['start_km'] !== null) {
                $driven = sprintf(' · %s km since the start', number_format($km - $distance['start_km']));
                if ($distance['last_place'] !== null && $distance['last_km'] !== null && $distance['last_km'] !== $distance['start_km']) {
                    $driven .= sprintf(' (+%s km since %s)', number_format($km - $distance['last_km']), $distance['last_place']);
                }
            }
            $message = sprintf('%s: %s arrived at %s at %s%s%s%s · %s%s', $code, $driver, $place, date('H:i'),
                $km !== null ? ' · ' . number_format($km) . ' km' : '', $driven, $flag, self::CONDITIONS[$condition], $note !== '' ? ' · Note: ' . $note : '');
            $severity = $condition === 'help' ? 'danger' : ($condition === 'problem' ? 'warning' : ($atDestination ? 'success' : 'info'));

            if ($atDestination) {
                // At the destination: this is the arrival on-time delivery is
                // measured by, and the people there need to unload.
                $db->prepare('UPDATE trips SET arrival_at = COALESCE(arrival_at, NOW()), end_mileage = COALESCE(?, end_mileage) WHERE id = ?')->execute([$km, $tripId]);
                Notifier::toRoles(self::OFFICE, 'Truck arrived at its destination', $message . '. Ready to unload.', 'trips', $severity, 'trips', $code);
                if (self::warehouseOf($stop) !== null) {
                    Notifier::toRole('warehouse_manager', 'Truck arrived at your warehouse', $message . '. Ready to unload.', 'trips', $severity, 'trips', $code);
                }

                return ['ok' => true, 'message' => sprintf('Arrival at %s recorded at %s. The company has been told. Hand over the goods and get the delivery note signed.', $place, date('H:i'))];
            }

            $title = $stop['stop_type'] === 'checkpoint' ? 'Truck at the border' : 'Truck reached a stop';
            Notifier::toRoles(self::OFFICE, $condition === 'help' ? 'Driver needs help: ' . $place : $title, $message, 'trips', $severity, 'trips', $code);

            return ['ok' => true, 'message' => sprintf('Arrival at %s recorded at %s. The company has been told.%s', $place, date('H:i'),
                $condition === 'help' ? ' If you need money or other help, press Ask for support.' : '')];
        }

        if ($what === 'leave') {
            if (empty($stop['actual_arrival_at'])) {
                return ['ok' => false, 'message' => sprintf('Press "I have arrived" at %s first.', $place)];
            }
            if (!empty($stop['departed_at'])) {
                return ['ok' => false, 'message' => sprintf('You already left %s at %s.', $place, date('H:i', (int) strtotime((string) $stop['departed_at'])))];
            }
            if (!self::handedOver($tripId, $stop)) {
                return ['ok' => false, 'message' => sprintf('Unload first: you can leave %s once the goods are off the truck and recorded (dropped at the warehouse or handed over).', $place)];
            }
            $db->prepare("UPDATE trip_stops SET departed_at = NOW(), status = 'completed',
                                 notes = TRIM(CONCAT(COALESCE(notes, ''), IF(? = '', '', CONCAT(IF(COALESCE(notes, '') = '', '', '\n'), 'Left: ', ?)))) WHERE id = ?")
               ->execute([$note, $note, $stopId]);
            AuditLog::record('trip.stop_left', 'trips', $code, null, ['stop' => $place]);
            BorderCrossing::stopReported($tripId, $place);
            $waited = (int) round((time() - (int) strtotime((string) $stop['actual_arrival_at'])) / 60);
            $spent = $waited >= 60 ? sprintf('%dh %02dmin', intdiv($waited, 60), $waited % 60) : $waited . ' min';
            // Leaving a stop is on the bell; arriving is what the company is emailed about.
            foreach (self::OFFICE as $role) {
                Notifier::bellToRole($role, 'Truck on the way', sprintf('%s: %s left %s at %s after %s there%s', $code, $driver, $place, date('H:i'), $spent, $note !== '' ? '. Note: ' . $note : '.'), 'trips', 'info', 'trips', $code);
            }

            return ['ok' => true, 'message' => sprintf('Left %s at %s (%s there). Drive safely.', $place, date('H:i'), $spent)];
        }

        return ['ok' => false, 'message' => 'Unknown step.'];
    }

    /**
     * At the last drop-off the truck leaves empty: only once every delivery on
     * the trip is off the truck: dropped at the warehouse, handed over, or
     * failed and recorded. Any other stop
     * can be left at any time.
     *
     * @param array<string, mixed> $stop the trip_stops row
     */
    public static function handedOver(int $tripId, array $stop): bool
    {
        $db = Database::connection();
        $last = $db->prepare('SELECT id FROM trip_stops WHERE trip_id = ? ORDER BY stop_sequence DESC, id DESC LIMIT 1');
        $last->execute([$tripId]);
        if ((int) $last->fetchColumn() !== (int) $stop['id'] && ($stop['stop_type'] ?? '') !== 'dropoff') {
            return true;
        }
        $open = $db->prepare("SELECT COUNT(*) AS total, SUM(status IN ('at_destination', 'delivered', 'failed')) AS settled FROM deliveries WHERE trip_id = ? AND deleted_at IS NULL");
        $open->execute([$tripId]);
        $count = $open->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'settled' => 0];

        return (int) $count['total'] > 0 && (int) $count['total'] === (int) $count['settled'];
    }

    /**
     * The warehouse a stop is at: the one it names, or the one whose name the
     * stop's place carries ("ITEC Kigali Main Warehouse, Kigali").
     *
     * @param array<string, mixed> $stop the trip_stops row
     */
    private static function warehouseOf(array $stop): ?int
    {
        if (!empty($stop['warehouse_id'])) {
            return (int) $stop['warehouse_id'];
        }
        $place = strtolower((string) ($stop['location_name'] ?? ''));
        foreach (Database::connection()->query('SELECT id, warehouse_name FROM warehouses WHERE deleted_at IS NULL')->fetchAll(PDO::FETCH_ASSOC) as $warehouse) {
            $name = strtolower(trim((string) $warehouse['warehouse_name']));
            if ($name !== '' && str_contains($place, $name)) {
                return (int) $warehouse['id'];
            }
        }

        return null;
    }
}
