<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Models\LogisticsData;
use Models\Permission;
use Models\Schema;
use Models\Workflow;

/**
 * A small JSON API, aimed at a driver using a phone: see my trips, see my
 * deliveries, mark one delivered or failed. Everything is scoped to the caller
 * exactly as the web screens are.
 */
final class ApiController
{
    public function health(): void
    {
        $this->json(['status' => 'ok', 'app' => \config('app.name'), 'time' => date('c')]);
    }

    public function me(): void
    {
        $this->json([
            'id' => \current_user_id(),
            'name' => \current_user_name(),
            'email' => \current_user_email(),
            'role' => \current_role(),
            'role_label' => \role_label(),
            'driver_id' => \current_driver_id(),
            'can_switch_role' => \can_switch_role(),
            'permissions' => Permission::forRole(\current_role()),
        ]);
    }

    /** GET /api/modules/{module} — one page of any module the caller may view. */
    public function listing(array $params): void
    {
        $module = (string) ($params['module'] ?? '');
        if (!Schema::has($module)) {
            $this->json(['error' => 'Unknown module.'], 404);
        }

        if (!\role_can($module, 'view')) {
            $this->json(['error' => 'This module is not available for your role.'], 403);
        }

        $listing = LogisticsData::listing($module, $_GET, \current_context());

        $this->json([
            'module' => $module,
            'columns' => Schema::get($module)['list'],
            'page' => $listing['page'],
            'pages' => $listing['pages'],
            'total' => $listing['total'],
            'rows' => $listing['rows'],
        ]);
    }

    /** GET /api/my/trips — the signed-in driver's own work, newest first. */
    public function myTrips(): void
    {
        $driverId = \current_driver_id();
        if ($driverId === null) {
            $this->json(['trips' => [], 'message' => 'This login is not linked to a driver profile.']);
        }

        $statement = Database::connection()->prepare(
            "SELECT t.id, t.reference_code, t.pickup_location, t.destination, t.status,
                    t.planned_departure_at, t.planned_arrival_at, t.departure_at, t.arrival_at,
                    t.cargo_summary, v.plate_number AS vehicle,
                    (SELECT COUNT(*) FROM deliveries d WHERE d.trip_id = t.id AND d.deleted_at IS NULL) AS deliveries
               FROM trips t
               LEFT JOIN vehicles v ON v.id = t.vehicle_id
              WHERE t.driver_id = ? AND t.deleted_at IS NULL
              ORDER BY FIELD(t.status, 'in_transit','loading','approved','requested','delivered','cancelled'), t.id DESC
              LIMIT 50"
        );
        $statement->execute([$driverId]);

        $this->json(['driver_id' => $driverId, 'trips' => $statement->fetchAll()]);
    }

    /** GET /api/my/deliveries — deliveries on the caller's own trips. */
    public function myDeliveries(): void
    {
        $driverId = \current_driver_id();
        if ($driverId === null) {
            $this->json(['deliveries' => [], 'message' => 'This login is not linked to a driver profile.']);
        }

        $statement = Database::connection()->prepare(
            'SELECT d.id, d.delivery_code, d.recipient_name, d.recipient_phone, d.destination,
                    d.status, d.attempt_number, d.planned_at, d.delivered_at,
                    d.proof_file IS NOT NULL AS has_proof,
                    t.reference_code AS trip
               FROM deliveries d
               INNER JOIN trips t ON t.id = d.trip_id
              WHERE t.driver_id = ? AND d.deleted_at IS NULL
              ORDER BY d.id DESC
              LIMIT 50'
        );
        $statement->execute([$driverId]);

        $this->json(['driver_id' => $driverId, 'deliveries' => $statement->fetchAll()]);
    }

    /** GET /api/my/shipments/{id}/document-pack - documents a driver may show at a border. */
    public function documentPack(array $params): void
    {
        $driverId = \current_driver_id();
        if ($driverId === null) {
            $this->json(['error' => 'This login is not linked to a driver profile.'], 403);
        }

        $pack = \Models\DocumentPack::forDriver((int) ($params['id'] ?? 0), $driverId);
        if ($pack === null) {
            $this->json(['error' => 'That shipment is not assigned to you.'], 404);
        }

        $this->json($pack);
    }

    /** POST /api/deliveries/{id}/{action} — complete or fail a delivery from the road. */
    public function deliveryAction(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $action = (string) ($params['action'] ?? '');

        if (!in_array($action, ['complete', 'fail'], true)) {
            $this->json(['error' => 'Only complete and fail are supported here.'], 400);
        }

        if (!\role_can('deliveries', 'edit')) {
            $this->json(['error' => 'Your role may not update deliveries.'], 403);
        }

        if (LogisticsData::find('deliveries', $id, \current_context()) === null) {
            $this->json(['error' => 'That delivery is not yours or does not exist.'], 404);
        }

        $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
        $reason = trim((string) ($payload['reason'] ?? $_POST['reason'] ?? ''));

        $result = Workflow::apply('deliveries', $id, $action, $reason);

        $this->json($result, $result['ok'] ? 200 : 422);
    }

    /**
     * POST /api/gps/positions — positions from GPS trackers.
     *
     * Takes what the common servers send: Traccar's JSON forwarding
     * ({"device": {"uniqueId"}, "position": {"latitude", "longitude", "speed" in knots, "course", "fixTime"}}),
     * the OsmAnd protocol most tracker apps speak (id, lat, lon, timestamp, speed in knots as query or form fields),
     * or a plain list of {"device_id", "lat", "lng", "speed_kmh", "heading", "time"}.
     * The tracker key from Company settings comes in the X-Tracker-Key header or a "key" field.
     */
    public function gpsPositions(): void
    {
        $expected = trim(\Models\Settings::get('gps_api_token', ''));
        $given = trim((string) ($_SERVER['HTTP_X_TRACKER_KEY'] ?? $_GET['key'] ?? $_POST['key'] ?? ''));
        if ($expected === '' || strlen($expected) < 16) {
            $this->json(['error' => 'Tracking is not switched on: set a tracker key of at least 16 characters under Company settings.'], 503);
        }
        if ($given === '' || !hash_equals($expected, $given)) {
            $this->json(['error' => 'Wrong tracker key.'], 401);
        }

        $body = json_decode(file_get_contents('php://input') ?: '', true);
        $points = [];
        if (is_array($body) && isset($body['position']) && is_array($body['position'])) {
            $points[] = [
                'device' => (string) ($body['device']['uniqueId'] ?? $body['device']['id'] ?? ''),
                'lat' => $body['position']['latitude'] ?? null,
                'lng' => $body['position']['longitude'] ?? null,
                'speed' => isset($body['position']['speed']) ? (float) $body['position']['speed'] * 1.852 : null,
                'heading' => isset($body['position']['course']) ? (int) $body['position']['course'] : null,
                'time' => (string) ($body['position']['fixTime'] ?? $body['position']['deviceTime'] ?? ''),
                'accuracy' => isset($body['position']['accuracy']) && (float) $body['position']['accuracy'] > 0 ? (float) $body['position']['accuracy'] : null,
            ];
        } elseif (is_array($body)) {
            foreach (array_is_list($body) ? $body : [$body] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $points[] = [
                    'device' => (string) ($row['device_id'] ?? $row['id'] ?? ''),
                    'lat' => $row['lat'] ?? $row['latitude'] ?? null,
                    'lng' => $row['lng'] ?? $row['lon'] ?? $row['longitude'] ?? null,
                    'speed' => isset($row['speed_kmh']) ? (float) $row['speed_kmh'] : null,
                    'heading' => isset($row['heading']) ? (int) $row['heading'] : null,
                    'time' => (string) ($row['time'] ?? $row['recorded_at'] ?? ''),
                    'accuracy' => isset($row['accuracy']) ? (float) $row['accuracy'] : null,
                ];
            }
        } else {
            $in = $_POST + $_GET;
            $stamp = (string) ($in['timestamp'] ?? '');
            $points[] = [
                'device' => (string) ($in['id'] ?? $in['deviceid'] ?? ''),
                'lat' => $in['lat'] ?? null,
                'lng' => $in['lon'] ?? $in['lng'] ?? null,
                'speed' => isset($in['speed']) ? (float) $in['speed'] * 1.852 : null,
                'heading' => isset($in['bearing']) ? (int) $in['bearing'] : (isset($in['heading']) ? (int) $in['heading'] : null),
                'time' => ctype_digit($stamp) ? date('Y-m-d H:i:s', (int) (strlen($stamp) > 10 ? intdiv((int) $stamp, 1000) : (int) $stamp)) : $stamp,
                'accuracy' => isset($in['accuracy']) ? (float) $in['accuracy'] : null,
            ];
        }

        $db = Database::connection();
        $find = $db->prepare('SELECT id FROM vehicles WHERE gps_device_id = ? AND deleted_at IS NULL LIMIT 1');
        $stored = 0;
        $unknown = [];
        foreach (array_slice($points, 0, 500) as $point) {
            if ($point['device'] === '' || !is_numeric($point['lat']) || !is_numeric($point['lng'])) {
                continue;
            }
            $find->execute([$point['device']]);
            $vehicleId = (int) $find->fetchColumn();
            if ($vehicleId <= 0) {
                $unknown[$point['device']] = true;
                continue;
            }
            $result = \Models\Tracking::record($vehicleId, (float) $point['lat'], (float) $point['lng'], $point['time'] !== '' ? $point['time'] : null, 'device', $point['speed'], $point['heading'], $point['accuracy']);
            $stored += $result['ok'] ? 1 : 0;
        }

        $this->json(['stored' => $stored, 'unknown_devices' => array_keys($unknown)], $stored > 0 || $unknown === [] ? 200 : 404);
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
