<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\Flash;
use Models\Fleet;
use Models\RequestFormConfig;
use Models\TripLife;
use Models\TripSheet;

/**
 * New trip (section 7) and the trip page: the planner checks every truck,
 * trailer and driver before anything is saved, and creates the trips and
 * their F1 in one step; the trip page shows where the money and the truck
 * stand and gives the OM the trip's buttons.
 */
final class TripPlannerController
{
    public function planner(): void
    {
        $routeId = (int) ($_GET['route_id'] ?? $_POST['route_id'] ?? 0);
        $departure = (string) ($_GET['departure_at'] ?? date('Y-m-d\TH:i'));
        $this->renderPlanner($routeId, $departure, ['rows' => [[]]], []);
    }

    /** JSON for the planner: the problems with the plan as it stands. */
    public function check(): void
    {
        header('Content-Type: application/json');
        echo json_encode(['problems' => TripLife::problems($this->plan())]);
        exit;
    }

    public function store(): void
    {
        $plan = $this->plan();
        try {
            $result = TripLife::plan($plan);
            Flash::success(sprintf('%d trip%s created, and F1 sent to Finance for verification.', count($result['trip_ids']), count($result['trip_ids']) === 1 ? '' : 's'));
            $this->redirect(\url(['form_requests', $result['request_ids'][0]]));
        } catch (\Throwable $exception) {
            $this->renderPlanner((int) ($plan['route_id'] ?? 0), (string) ($plan['departure_at'] ?? ''), $plan, [$exception->getMessage()]);
        }
    }

    public function show(array $params): void
    {
        $trip = $this->load($params);
        $id = (int) $trip['id'];
        $db = Database::connection();
        $changes = $db->prepare(
            'SELECT c.*, u.full_name AS by_name FROM trip_changes c LEFT JOIN users u ON u.id = c.user_id WHERE c.trip_id = ? ORDER BY c.id'
        );
        $changes->execute([$id]);
        $fuel = $db->prepare('SELECT f.*, s.supplier_name FROM fuel_orders f LEFT JOIN suppliers s ON s.id = f.supplier_id WHERE f.trip_id = ? ORDER BY f.id');
        $fuel->execute([$id]);
        $handovers = $db->prepare('SELECT * FROM vehicle_handovers WHERE trip_id = ? AND deleted_at IS NULL ORDER BY id DESC');
        $handovers->execute([$id]);
        $requests = $db->prepare(
            'SELECT DISTINCT r.id, r.form_code, r.request_no, r.title, r.status, r.currency, r.total_amount
               FROM form_requests r LEFT JOIN form_request_lines l ON l.request_id = r.id
              WHERE (l.trip_id = ? OR r.trip_id = ?) AND r.deleted_at IS NULL ORDER BY r.id'
        );
        $requests->execute([$id, $id]);

        \view('trips/show', [
            'title' => $trip['reference_code'],
            'trip' => $trip,
            'money' => TripLife::moneyState($id),
            'events' => TripLife::events($id),
            'changes' => $changes->fetchAll(\PDO::FETCH_ASSOC),
            'fuel' => $fuel->fetchAll(\PDO::FETCH_ASSOC),
            'handovers' => $handovers->fetchAll(\PDO::FETCH_ASSOC),
            'requests' => $requests->fetchAll(\PDO::FETCH_ASSOC),
            'lists' => RequestFormConfig::lists(),
        ]);
    }

    /** One of the trip's buttons, pressed by the OM on the driver's behalf. */
    public function act(array $params): void
    {
        $trip = $this->load($params);
        try {
            $message = TripLife::act((int) $trip['id'], (string) ($params['action'] ?? ''), [
                'odometer' => $_POST['odometer'] ?? '',
                'quantity' => $_POST['quantity'] ?? '',
                'note' => trim((string) ($_POST['note'] ?? '')) ?: null,
                'source' => 'om',
            ]);
            Flash::success($message);
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }
        $this->redirect(\url(['trips', $trip['id']]));
    }

    public function report(array $params): void
    {
        $trip = $this->load($params);
        try {
            TripLife::report((int) $trip['id'], (string) ($_POST['kind'] ?? 'problem') === 'delay' ? 'delay' : 'problem', (string) ($_POST['note'] ?? ''), ['source' => 'om']);
            Flash::success('Recorded on the trip.');
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }
        $this->redirect(\url(['trips', $trip['id']]));
    }

    public function replace(array $params): void
    {
        $trip = $this->load($params);
        try {
            TripLife::replace((int) $trip['id'], (string) ($_POST['what'] ?? ''), (int) ($_POST['new_id'] ?? 0), (string) ($_POST['reason'] ?? ''), ($_POST['odometer'] ?? '') !== '' ? (int) $_POST['odometer'] : null);
            Flash::success('Replaced. The trip keeps one record, with the change and the reason.');
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }
        $this->redirect(\url(['trips', $trip['id']]));
    }

    public function cancel(array $params): void
    {
        $trip = $this->load($params);
        try {
            TripLife::cancel((int) $trip['id'], (string) ($_POST['reason'] ?? ''));
            Flash::success('Trip cancelled; the truck is free again.');
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }
        $this->redirect(\url(['trips', $trip['id']]));
    }

    public function sheet(array $params): void
    {
        $trip = $this->load($params);
        $bytes = TripSheet::render((int) $trip['id']);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $trip['reference_code'] . '.pdf"');
        echo $bytes;
        exit;
    }

    // --------------------------------------------------------------- helpers

    private function renderPlanner(int $routeId, string $departure, array $plan, array $errors): void
    {
        $departureSql = $departure !== '' ? date('Y-m-d H:i:s', (int) strtotime($departure)) : date('Y-m-d H:i:s');
        $db = Database::connection();
        $company = \Models\Company::condition('routes', 'company_id');
        $routes = $db->query("SELECT * FROM routes WHERE deleted_at IS NULL AND is_active = 1" . ($company ? " AND {$company}" : '') . ' ORDER BY route_code')->fetchAll(\PDO::FETCH_ASSOC);
        $route = $routeId > 0 ? TripLife::route($routeId) : null;
        $standards = [];
        if ($route !== null) {
            foreach (['all', 'tanker', 'container'] as $fleet) {
                $found = TripLife::standards($routeId, $fleet, substr($departureSql, 0, 10));
                $fuel = TripLife::departureFuel($found);
                $standards[$fleet] = [
                    'fuel' => $fuel['quantity'] ?? null,
                    'fuel_point' => $fuel['fuel_point'] ?? null,
                    'road_toll' => $found['road_toll']['amount'] ?? null,
                    'road_toll_currency' => $found['road_toll']['currency'] ?? null,
                    'mileage' => $found['mileage']['amount'] ?? null,
                    'mileage_currency' => $found['mileage']['currency'] ?? null,
                    'top_up' => array_values(array_map(static fn (array $f): string => rtrim(rtrim((string) $f['quantity'], '0'), '.') . ' L at ' . $f['fuel_point'], array_filter($found['fuel'], static fn (array $f): bool => ($f['fuel_on'] ?? '') === 'turnaround'))),
                ];
            }
        }

        \view('trips/planner', [
            'title' => 'New trip',
            'routes' => $routes,
            'selectedRoute' => $route,
            'departure' => date('Y-m-d\TH:i', (int) strtotime($departureSql)),
            'candidates' => TripLife::candidates($routeId > 0 ? $routeId : null, $departureSql),
            'standards' => $standards,
            'customers' => RequestFormConfig::lists()['customers'],
            'plan' => $plan,
            'errors' => $errors,
        ]);
    }

    /** The posted plan. @return array<string, mixed> */
    private function plan(): array
    {
        $rows = [];
        foreach ((array) ($_POST['rows'] ?? []) as $row) {
            if (!is_array($row) || (empty($row['vehicle_id']) && empty($row['driver_id']))) {
                continue;
            }
            $rows[] = array_map(static fn ($value) => is_string($value) ? trim($value) : $value, $row);
        }

        return [
            'route_id' => (int) ($_POST['route_id'] ?? 0),
            'departure_at' => (string) ($_POST['departure_at'] ?? ''),
            'customer_id' => ($_POST['customer_id'] ?? '') !== '' ? (int) $_POST['customer_id'] : null,
            'cargo_type' => (string) ($_POST['cargo_type'] ?? ''),
            'cargo_quantity' => trim((string) ($_POST['cargo_quantity'] ?? '')),
            'cargo_summary' => trim((string) ($_POST['cargo_summary'] ?? '')),
            'non_standard_reason' => trim((string) ($_POST['non_standard_reason'] ?? '')),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
            'rows' => $rows,
        ];
    }

    private function load(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $visible = \Models\LogisticsData::find('trips', $id, \current_context());
        $trip = $visible !== null ? TripLife::find($id) : null;
        if ($trip === null) {
            Flash::error('That trip does not exist, or belongs to another company.');
            $this->redirect(\url('trips'));
        }

        return $trip;
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
