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

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
