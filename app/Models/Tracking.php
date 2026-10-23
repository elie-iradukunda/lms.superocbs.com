<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Where the trucks are.
 *
 * A position comes from one of two places: a GPS tracker in the truck (sent
 * straight here, or through a Traccar or Wialon server that forwards it), or
 * the driver's own phone while My trips is open on the road. Either way it is
 * one row in `vehicle_positions`, tied to the trip the truck is on.
 *
 * A position inside the circle of a border post, or of a stop given its
 * coordinates, records the arrival there without anybody pressing a button,
 * and leaving the circle of a border records the departure. A truck on the
 * road that stops reporting raises an alert.
 */
final class Tracking
{
    /** A stop with coordinates but no circle of its own. */
    private const STOP_RADIUS_M = 1500;

    private static function db(): PDO
    {
        return Database::connection();
    }

    /**
     * Stores one position and works out what it means for the trip.
     *
     * @return array{ok: bool, message: string, trip_id?: ?int}
     */
    public static function record(int $vehicleId, float $lat, float $lng, ?string $recordedAt = null, string $source = 'phone', ?float $speedKmh = null, ?int $heading = null, ?float $accuracy = null, ?int $driverId = null): array
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || (abs($lat) < 0.0001 && abs($lng) < 0.0001)) {
            return ['ok' => false, 'message' => 'That position is not a real place.'];
        }
        // A phone that cannot see the sky reports a guess kilometres wide: not worth keeping.
        if ($accuracy !== null && $accuracy > 2000) {
            return ['ok' => false, 'message' => 'The position is too rough to use.'];
        }
        $when = $recordedAt !== null && strtotime($recordedAt) !== false ? date('Y-m-d H:i:s', (int) strtotime($recordedAt)) : date('Y-m-d H:i:s');
        if (strtotime($when) > time() + 300) {
            $when = date('Y-m-d H:i:s');
        }

        $trip = self::db()->prepare("SELECT id, driver_id, status FROM trips WHERE vehicle_id = ? AND deleted_at IS NULL AND status IN ('in_transit','loading','approved') ORDER BY FIELD(status, 'in_transit', 'loading', 'approved'), id DESC LIMIT 1");
        $trip->execute([$vehicleId]);
        $onTrip = $trip->fetch(PDO::FETCH_ASSOC) ?: null;
        $tripId = $onTrip !== null ? (int) $onTrip['id'] : null;
        $driverId ??= $onTrip !== null && $onTrip['driver_id'] !== null ? (int) $onTrip['driver_id'] : null;

        self::db()->prepare(
            'INSERT INTO vehicle_positions (vehicle_id, trip_id, driver_id, latitude, longitude, speed_kmh, heading, accuracy_m, source, recorded_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$vehicleId, $tripId, $driverId, round($lat, 7), round($lng, 7), $speedKmh !== null ? round($speedKmh, 1) : null, $heading, $accuracy !== null ? round($accuracy, 1) : null, $source === 'device' ? 'device' : 'phone', $when]);

        $events = [];
        if ($onTrip !== null && $onTrip['status'] === 'in_transit') {
            $events = self::geofence((int) $onTrip['id'], $lat, $lng);
        }

        return ['ok' => true, 'message' => $events === [] ? 'Position recorded.' : implode(' ', $events), 'trip_id' => $tripId];
    }

    /**
     * Arrivals and departures the position gives away.
     *
     * @return list<string>
     */
    public static function geofence(int $tripId, float $lat, float $lng): array
    {
        $events = [];
        $stops = self::db()->prepare('SELECT * FROM trip_stops WHERE trip_id = ? ORDER BY stop_sequence, id');
        $stops->execute([$tripId]);
        $posts = self::db()->query('SELECT post_name, latitude, longitude, geofence_m FROM border_posts WHERE deleted_at IS NULL AND latitude IS NOT NULL AND longitude IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC);

        foreach ($stops->fetchAll(PDO::FETCH_ASSOC) as $stop) {
            [$pointLat, $pointLng, $radius] = self::pointFor($stop, $posts);
            if ($pointLat === null) {
                continue;
            }
            $distance = self::metres($lat, $lng, $pointLat, $pointLng);

            if (empty($stop['actual_arrival_at']) && $distance <= $radius) {
                $result = TripProgress::stop($tripId, (int) $stop['id'], 'arrive', ['note' => 'Recorded by GPS', 'condition' => 'fine']);
                if ($result['ok']) {
                    self::db()->prepare("UPDATE trip_stops SET arrival_source = 'gps' WHERE id = ?")->execute([(int) $stop['id']]);
                    $events[] = sprintf('Arrived at %s (GPS).', $stop['location_name']);
                }
                continue;
            }

            // Out of the circle of a border or a waypoint it reached: it has left.
            // A drop-off is left by the driver, once the goods are off.
            if (!empty($stop['actual_arrival_at']) && empty($stop['departed_at'])
                && in_array($stop['stop_type'], ['checkpoint', 'waypoint'], true) && $distance > $radius * 1.5) {
                $result = TripProgress::stop($tripId, (int) $stop['id'], 'leave', ['note' => 'Recorded by GPS']);
                if ($result['ok']) {
                    $events[] = sprintf('Left %s (GPS).', $stop['location_name']);
                }
            }
        }

        return $events;
    }

    /**
     * The point a stop is recognised at: its own coordinates, or for a
     * border stop the coordinates of the post it names.
     *
     * @param list<array<string, mixed>> $posts
     * @return array{0: ?float, 1: ?float, 2: float}
     */
    private static function pointFor(array $stop, array $posts): array
    {
        if ($stop['latitude'] !== null && $stop['longitude'] !== null) {
            return [(float) $stop['latitude'], (float) $stop['longitude'], (float) self::STOP_RADIUS_M];
        }
        if ($stop['stop_type'] === 'checkpoint') {
            foreach ($posts as $post) {
                if (BorderCrossing::sameBorder((string) $post['post_name'], (string) $stop['location_name'])) {
                    return [(float) $post['latitude'], (float) $post['longitude'], (float) max(200, (int) $post['geofence_m'])];
                }
            }
        }

        return [null, null, 0.0];
    }

    /** Great-circle distance in metres. */
    public static function metres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    // ------------------------------------------------------------- reading

    /**
     * The last known position of every truck that reported in the last week,
     * with what it is doing.
     *
     * @return list<array<string, mixed>>
     */
    public static function fleet(int $days = 7): array
    {
        try {
            $statement = self::db()->prepare(
                "SELECT p.vehicle_id, p.latitude, p.longitude, p.speed_kmh, p.heading, p.source, p.recorded_at,
                        v.plate_number, v.status AS vehicle_status, v.make, v.model,
                        t.id AS trip_id, t.reference_code AS trip, t.pickup_location, t.destination, t.status AS trip_status, t.planned_arrival_at,
                        d.full_name AS driver, d.phone AS driver_phone,
                        TIMESTAMPDIFF(MINUTE, p.recorded_at, NOW()) AS minutes_ago
                   FROM vehicle_positions p
                   INNER JOIN (SELECT vehicle_id, MAX(id) AS id FROM vehicle_positions WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY vehicle_id) last ON last.id = p.id
                   INNER JOIN vehicles v ON v.id = p.vehicle_id
                   LEFT JOIN trips t ON t.id = p.trip_id
                   LEFT JOIN drivers d ON d.id = COALESCE(t.driver_id, p.driver_id)
                  WHERE v.deleted_at IS NULL
                  ORDER BY v.plate_number"
            );
            $statement->execute([$days]);

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array{lat: float, lng: float, at: string, speed: ?float}> the road a trip has taken */
    public static function trail(int $tripId, int $limit = 2000): array
    {
        try {
            $statement = self::db()->prepare('SELECT latitude, longitude, recorded_at, speed_kmh FROM vehicle_positions WHERE trip_id = ? ORDER BY recorded_at, id LIMIT ' . max(1, $limit));
            $statement->execute([$tripId]);

            return array_map(static fn (array $r): array => ['lat' => (float) $r['latitude'], 'lng' => (float) $r['longitude'], 'at' => (string) $r['recorded_at'], 'speed' => $r['speed_kmh'] !== null ? (float) $r['speed_kmh'] : null], $statement->fetchAll(PDO::FETCH_ASSOC));
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> the posts and stops a map draws as places */
    public static function places(?int $tripId = null): array
    {
        $places = [];
        try {
            foreach (self::db()->query('SELECT post_name, latitude, longitude, geofence_m FROM border_posts WHERE deleted_at IS NULL AND is_active = 1 AND latitude IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC) as $post) {
                $places[] = ['name' => (string) $post['post_name'], 'lat' => (float) $post['latitude'], 'lng' => (float) $post['longitude'], 'radius' => (int) $post['geofence_m'], 'kind' => 'border'];
            }
            if ($tripId !== null) {
                $stops = self::db()->prepare('SELECT location_name, latitude, longitude, stop_type, actual_arrival_at FROM trip_stops WHERE trip_id = ? AND latitude IS NOT NULL ORDER BY stop_sequence');
                $stops->execute([$tripId]);
                foreach ($stops->fetchAll(PDO::FETCH_ASSOC) as $stop) {
                    $places[] = ['name' => (string) $stop['location_name'], 'lat' => (float) $stop['latitude'], 'lng' => (float) $stop['longitude'], 'radius' => self::STOP_RADIUS_M, 'kind' => (string) $stop['stop_type']];
                }
            }
        } catch (\Throwable) {
        }

        return $places;
    }

    /** The last position of a trip's truck, for the customer page and the trip page. */
    public static function lastForTrip(int $tripId): ?array
    {
        try {
            $statement = self::db()->prepare('SELECT latitude, longitude, recorded_at, speed_kmh FROM vehicle_positions WHERE trip_id = ? ORDER BY recorded_at DESC, id DESC LIMIT 1');
            $statement->execute([$tripId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            return $row === false ? null : $row;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * A truck on the road that has reported before and has gone quiet: the
     * office is told, once a day per trip.
     */
    public static function silenceAlerts(callable $raise): void
    {
        $hours = max(1, Settings::int('gps_silence_hours', 3));
        try {
            $rows = self::db()->query(
                "SELECT t.id, t.reference_code, v.plate_number, d.full_name AS driver, MAX(p.recorded_at) AS last_at
                   FROM trips t
                   INNER JOIN vehicle_positions p ON p.trip_id = t.id
                   LEFT JOIN vehicles v ON v.id = t.vehicle_id
                   LEFT JOIN drivers d ON d.id = t.driver_id
                  WHERE t.deleted_at IS NULL AND t.status = 'in_transit'
                  GROUP BY t.id, t.reference_code, v.plate_number, d.full_name"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return;
        }
        foreach ($rows as $row) {
            $quiet = (time() - (int) strtotime((string) $row['last_at'])) / 3600;
            if ($quiet < $hours) {
                continue;
            }
            $raise(
                'gps-quiet-' . $row['id'] . '-' . date('Ymd'),
                ['logistics_manager', 'fleet_manager'],
                'No position from ' . ($row['plate_number'] ?: $row['reference_code']),
                sprintf('%s on %s has not reported its position for %d hours (last at %s). Call %s.', $row['plate_number'] ?: 'The truck', $row['reference_code'], (int) $quiet, \human_date((string) $row['last_at'], true), $row['driver'] ?: 'the driver'),
                'tracking',
                'warning'
            );
        }
    }
}
