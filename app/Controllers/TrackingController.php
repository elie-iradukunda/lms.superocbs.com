<?php

declare(strict_types=1);

namespace Controllers;

use Models\LogisticsData;
use Models\Settings;
use Models\Tracking;

/** The live map of the fleet, and the road one trip has taken. */
final class TrackingController
{
    public function index(): void
    {
        \view('tracking/index', [
            'title' => 'Live tracking',
            'fleet' => Tracking::fleet(),
            'silence' => max(1, Settings::int('gps_silence_hours', 3)),
            'pageScripts' => ['/assets/vendor/leaflet/leaflet.js', '/assets/js/lms-map.js'],
        ]);
    }

    public function positions(): void
    {
        $this->json([
            'vehicles' => array_map([self::class, 'vehicle'], Tracking::fleet()),
            'places' => Tracking::places(),
            'at' => date('c'),
        ]);
    }

    public function trail(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if (LogisticsData::find('trips', $id, \current_context()) === null) {
            $this->json(['error' => 'That trip is not visible to you.'], 404);
        }
        $last = Tracking::lastForTrip($id);

        $this->json([
            'trail' => Tracking::trail($id),
            'last' => $last === null ? null : ['lat' => (float) $last['latitude'], 'lng' => (float) $last['longitude'], 'at' => (string) $last['recorded_at']],
            'places' => Tracking::places($id),
        ]);
    }

    /** One truck as the map shows it. */
    private static function vehicle(array $row): array
    {
        $minutes = (int) $row['minutes_ago'];

        return [
            'plate' => (string) $row['plate_number'],
            'lat' => (float) $row['latitude'],
            'lng' => (float) $row['longitude'],
            'speed' => $row['speed_kmh'] !== null ? (float) $row['speed_kmh'] : null,
            'source' => (string) $row['source'],
            'at' => \human_date((string) $row['recorded_at'], true),
            'ago' => \time_ago((string) $row['recorded_at']),
            'stale' => $minutes > max(1, Settings::int('gps_silence_hours', 3)) * 60,
            'trip' => $row['trip'] ? (string) $row['trip'] : null,
            'trip_url' => $row['trip_id'] ? \url(['trips', (int) $row['trip_id']]) : null,
            'route' => $row['trip'] ? $row['pickup_location'] . ' → ' . $row['destination'] : null,
            'status' => $row['trip_status'] ? \Models\Schema::label((string) $row['trip_status']) : \Models\Schema::label((string) $row['vehicle_status']),
            'driver' => (string) ($row['driver'] ?? ''),
            'phone' => (string) ($row['driver_phone'] ?? ''),
        ];
    }

    private function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data);
        exit;
    }
}
