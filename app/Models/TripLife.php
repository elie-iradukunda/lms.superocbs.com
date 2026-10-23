<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * A trip from planning to handover (section 7).
 *
 *   planned        the OM created it; its F1 is in the chain
 *   ready          F1 paid and the driver confirmed the money arrived
 *   on_road        the driver confirmed departure
 *   at_turnaround  arrived where it loads and turns back (Mombasa): the
 *                  return clock starts and the fuel top-up (F2) is raised
 *   loading        at the loading point
 *   offloading     at the customer
 *   returning      the delivery note is done; empty, returning
 *   arrived        back at base, waiting for the handover
 *   closed         the OM confirmed the handover; km and fuel are checked
 *
 * Controls held here: no departure before the money is authorized, paid and
 * confirmed (1); documents in date for the whole trip (2); load within the
 * maximum (3); no truck or driver on two trips (4); road toll and mileage
 * once per trip (5, on the F1); fuel different from the standard needs a
 * reason (6).
 */
final class TripLife
{
    public const STATUS = [
        'planned' => 'Planned (money in approval)',
        'ready' => 'Ready to depart',
        'on_road' => 'On the road',
        'at_turnaround' => 'At the turnaround point',
        'loading' => 'Loading',
        'offloading' => 'Offloading',
        'returning' => 'Empty, returning',
        'arrived' => 'Back, waiting for handover',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled',
        // earlier records
        'requested' => 'Requested', 'approved' => 'Approved', 'in_transit' => 'In transit', 'delivered' => 'Delivered',
    ];

    public const ACTIVE = ['planned', 'ready', 'on_road', 'at_turnaround', 'loading', 'offloading', 'returning', 'arrived', 'requested', 'approved', 'in_transit'];

    /** What the truck's status is while the trip is in each stage. */
    private const VEHICLE_FOR = [
        'planned' => 'assigned', 'ready' => 'assigned', 'on_road' => 'on_road', 'at_turnaround' => 'on_road',
        'loading' => 'loading', 'offloading' => 'offloading', 'returning' => 'returning', 'arrived' => 'returning',
    ];

    /** The driver's and OM's buttons: action => [from statuses, to status]. */
    public const ACTIONS = [
        'depart' => [['ready'], 'on_road', 'Confirm departure'],
        'arrive_turnaround' => [['on_road'], 'at_turnaround', 'Arrived at the turnaround point'],
        'start_loading' => [['on_road', 'at_turnaround'], 'loading', 'Loading started'],
        'loaded' => [['loading'], 'on_road', 'Loading done'],
        'arrive_customer' => [['on_road', 'at_turnaround'], 'offloading', 'At the customer: offloading'],
        'arrive_base' => [['returning', 'on_road'], 'arrived', 'Back at base'],
    ];

    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function find(int $id): ?array
    {
        $statement = self::db()->prepare(
            'SELECT t.*, r.route_code, r.route_name, r.turnaround_point, r.max_return_days, r.expected_days, r.crosses_border,
                    v.plate_number, v.fleet, v.max_payload_t, v.cargo_tank_l, v.container_sizes, v.normal_l_per_100km, v.mileage AS vehicle_mileage,
                    tr.trailer_number, d.full_name AS driver_name, d.phone AS driver_phone, d.user_id AS driver_user_id,
                    c.customer_name
               FROM trips t
               LEFT JOIN routes r ON r.id = t.route_id
               LEFT JOIN vehicles v ON v.id = t.vehicle_id
               LEFT JOIN trailers tr ON tr.id = t.trailer_id
               LEFT JOIN drivers d ON d.id = t.driver_id
               LEFT JOIN customers c ON c.id = t.customer_id
              WHERE t.id = ? AND t.deleted_at IS NULL'
        );
        $statement->execute([$id]);
        $trip = $statement->fetch(PDO::FETCH_ASSOC);

        return $trip === false ? null : $trip;
    }

    public static function route(int $id): ?array
    {
        $statement = self::db()->prepare('SELECT * FROM routes WHERE id = ? AND deleted_at IS NULL');
        $statement->execute([$id]);
        $route = $statement->fetch(PDO::FETCH_ASSOC);

        return $route === false ? null : $route;
    }

    // ------------------------------------------------------------- planning

    /**
     * The route's standard amounts on a day for a fleet (the rate card).
     *
     * @return array{fuel: list<array>, road_toll: ?array, mileage: ?array, other: list<array>}
     */
    public static function standards(int $routeId, string $fleet, ?string $date = null): array
    {
        $date ??= date('Y-m-d');
        $statement = self::db()->prepare(
            "SELECT rr.*, s.supplier_name FROM route_rates rr LEFT JOIN suppliers s ON s.id = rr.supplier_id
              WHERE rr.route_id = ? AND rr.deleted_at IS NULL AND rr.is_active = 1
                AND rr.valid_from <= ? AND (rr.valid_to IS NULL OR rr.valid_to >= ?)
                AND rr.fleet IN ('all', ?)
              ORDER BY rr.rate_item, rr.fleet = 'all', rr.valid_from DESC, rr.id DESC"
        );
        $statement->execute([$routeId, $date, $date, $fleet === 'tanker' || $fleet === 'container' ? $fleet : 'all']);

        $standards = ['fuel' => [], 'road_toll' => null, 'mileage' => null, 'other' => []];
        $seenFuel = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $rate) {
            $item = (string) $rate['rate_item'];
            if ($item === 'fuel') {
                $point = strtolower((string) $rate['fuel_point']) . '|' . $rate['fuel_on'];
                if (!isset($seenFuel[$point])) {
                    $seenFuel[$point] = true;
                    $standards['fuel'][] = $rate;
                }
            } elseif ($item === 'other') {
                $standards['other'][] = $rate;
            } elseif ($standards[$item] === null) {
                $standards[$item] = $rate;
            }
        }

        return $standards;
    }

    /** When the truck is expected back, for checking documents over the whole trip. */
    public static function expectedBack(array $route, string $departure): string
    {
        $days = (float) ($route['expected_days'] ?? 0) ?: 1.0;

        return date('Y-m-d H:i:s', (int) strtotime($departure) + (int) round($days * 86400));
    }

    /**
     * Trucks, trailers and drivers for the planner, each with what (if
     * anything) stops it from going: the lists show only what is free and valid,
     * and say why the others are not.
     *
     * @return array{vehicles: list<array>, trailers: list<array>, drivers: list<array>}
     */
    public static function candidates(?int $routeId, ?string $departure = null): array
    {
        $route = $routeId ? self::route($routeId) : null;
        $departure ??= date('Y-m-d H:i:s');
        $until = $route ? self::expectedBack($route, $departure) : $departure;
        $crossBorder = $route === null || (int) $route['crosses_border'] === 1;
        $company = Company::condition('vehicles', 'company_id');

        $vehicles = [];
        foreach (self::db()->query("SELECT * FROM vehicles WHERE deleted_at IS NULL AND status <> 'inactive'" . ($company ? " AND {$company}" : '') . ' ORDER BY plate_number')->fetchAll(PDO::FETCH_ASSOC) as $vehicle) {
            $problems = Fleet::documentProblems('vehicle', (int) $vehicle['id'], (string) $vehicle['plate_number'], (string) $vehicle['fleet'], substr($until, 0, 10), $crossBorder);
            if ($vehicle['status'] !== 'available') {
                $problems[] = sprintf('%s is %s.', $vehicle['plate_number'], strtolower(Fleet::VEHICLE_STATUS[$vehicle['status']] ?? $vehicle['status']));
            }
            $service = Fleet::serviceState($vehicle);
            $vehicles[] = $vehicle + ['problems' => $problems, 'service' => $service];
        }

        $trailers = [];
        $company = Company::condition('trailers', 'company_id');
        foreach (self::db()->query("SELECT * FROM trailers WHERE deleted_at IS NULL AND status <> 'inactive'" . ($company ? " AND {$company}" : '') . ' ORDER BY trailer_number')->fetchAll(PDO::FETCH_ASSOC) as $trailer) {
            $problems = Fleet::documentProblems('trailer', (int) $trailer['id'], (string) $trailer['trailer_number'], (string) ($trailer['trailer_type'] === 'tanker' ? 'tanker' : 'container'), substr($until, 0, 10), $crossBorder);
            if (FleetForms::activeTrip('trailer_id', (int) $trailer['id']) !== null) {
                $problems[] = $trailer['trailer_number'] . ' is on another trip.';
            } elseif (!in_array($trailer['status'], ['available', 'in_use'], true)) {
                $problems[] = sprintf('%s is %s.', $trailer['trailer_number'], strtolower(Fleet::TRAILER_STATUS[$trailer['status']] ?? $trailer['status']));
            }
            $trailers[] = $trailer + ['problems' => $problems];
        }

        $drivers = [];
        $company = Company::condition('drivers', 'company_id');
        foreach (self::db()->query("SELECT * FROM drivers WHERE deleted_at IS NULL AND status IN ('active', 'on_trip')" . ($company ? " AND {$company}" : '') . ' ORDER BY full_name')->fetchAll(PDO::FETCH_ASSOC) as $driver) {
            $problems = Fleet::documentProblems('driver', (int) $driver['id'], (string) $driver['full_name'], (string) $driver['fleet'], substr($until, 0, 10), $crossBorder);
            if (($trip = FleetForms::activeTrip('driver_id', (int) $driver['id'])) !== null) {
                $problems[] = sprintf('%s is on trip %s.', $driver['full_name'], $trip);
            }
            if ($driver['payment_method'] === 'momo' && trim((string) $driver['momo_number']) === '') {
                $problems[] = $driver['full_name'] . ' has no MoMo number.';
            }
            $drivers[] = $driver + ['problems' => $problems];
        }

        return ['vehicles' => $vehicles, 'trailers' => $trailers, 'drivers' => $drivers];
    }

    /**
     * Everything that stops a plan, in red on the planner (section 7, step 3).
     *
     * @param array<string, mixed> $plan
     * @return list<string>
     */
    public static function problems(array $plan): array
    {
        $problems = [];
        $route = !empty($plan['route_id']) ? self::route((int) $plan['route_id']) : null;
        if ($route === null) {
            return ['Choose the route.'];
        }
        $rows = array_values(array_filter((array) ($plan['rows'] ?? []), static fn (array $row): bool => !empty($row['vehicle_id']) || !empty($row['driver_id'])));
        if ($rows === []) {
            return ['Add at least one truck with its driver.'];
        }
        if (empty($plan['cargo_type'])) {
            $problems[] = 'Say what the trucks carry: litres, tonnes or containers.';
        }
        $departure = (string) ($plan['departure_at'] ?? '') ?: date('Y-m-d H:i:s');
        $until = substr(self::expectedBack($route, $departure), 0, 10);
        $crossBorder = (int) $route['crosses_border'] === 1;

        $seen = ['vehicle' => [], 'driver' => [], 'trailer' => []];
        foreach ($rows as $index => $row) {
            $n = $index + 1;
            $vehicle = !empty($row['vehicle_id']) ? Fleet::vehicle((int) $row['vehicle_id']) : null;
            $driver = !empty($row['driver_id']) ? Fleet::driver((int) $row['driver_id']) : null;
            $trailer = !empty($row['trailer_id']) ? Fleet::trailer((int) $row['trailer_id']) : null;
            if ($vehicle === null) {
                $problems[] = "Truck {$n}: choose the truck.";
                continue;
            }
            $plate = (string) $vehicle['plate_number'];
            if ($driver === null) {
                $problems[] = "{$plate}: choose the driver.";
            }

            foreach (['vehicle' => $vehicle, 'driver' => $driver, 'trailer' => $trailer] as $kind => $record) {
                if ($record === null) {
                    continue;
                }
                if (isset($seen[$kind][(int) $record['id']])) {
                    $problems[] = sprintf('%s appears twice on this plan.', $kind === 'driver' ? $record['full_name'] : ($record['plate_number'] ?? $record['trailer_number']));
                }
                $seen[$kind][(int) $record['id']] = true;
                // Control 4: not on another trip.
                $column = $kind . '_id';
                if (($trip = FleetForms::activeTrip($column, (int) $record['id'])) !== null) {
                    $problems[] = sprintf('%s is on trip %s.', $kind === 'driver' ? $record['full_name'] : ($record['plate_number'] ?? $record['trailer_number']), $trip);
                }
            }

            // Control 2: documents valid for the whole expected trip.
            $problems = array_merge($problems, Fleet::documentProblems('vehicle', (int) $vehicle['id'], $plate, (string) $vehicle['fleet'], $until, $crossBorder));
            if ($trailer !== null) {
                $problems = array_merge($problems, Fleet::documentProblems('trailer', (int) $trailer['id'], (string) $trailer['trailer_number'], $trailer['trailer_type'] === 'tanker' ? 'tanker' : 'container', $until, $crossBorder));
            }
            if ($driver !== null) {
                if ($driver['status'] !== 'active') {
                    $problems[] = sprintf('%s is %s, not active.', $driver['full_name'], strtolower(Fleet::DRIVER_STATUS[$driver['status']] ?? $driver['status']));
                }
                $problems = array_merge($problems, Fleet::documentProblems('driver', (int) $driver['id'], (string) $driver['full_name'], (string) $driver['fleet'], $until, $crossBorder));
                if ($driver['payment_method'] === 'momo' && trim((string) $driver['momo_number']) === '') {
                    $problems[] = $driver['full_name'] . ' is paid by MoMo but has no MoMo number.';
                }
            }

            if (!in_array($vehicle['status'], ['available'], true)) {
                $problems[] = sprintf('%s is %s.', $plate, strtolower(Fleet::VEHICLE_STATUS[$vehicle['status']] ?? $vehicle['status']));
            }

            // Not due for service, or the reason it will be serviced on return.
            if (Fleet::serviceState($vehicle)['state'] === 'due' && trim((string) ($row['service_due_reason'] ?? '')) === '') {
                $problems[] = sprintf('%s is due for service: record why it will be serviced on return.', $plate);
            }

            // Control 3: the load within the maximum.
            $quantity = (float) str_replace(',', '', (string) ($row['cargo_quantity'] ?? $plan['cargo_quantity'] ?? 0));
            $type = (string) ($plan['cargo_type'] ?? '');
            if ($type === 'tonnes' && $quantity > 0) {
                $maximum = (float) ($vehicle['max_payload_t'] ?? 0);
                if ($trailer !== null && (float) ($trailer['max_load_t'] ?? 0) > 0) {
                    $maximum = (float) $trailer['max_load_t'];
                }
                if ($maximum > 0 && $quantity > $maximum + 0.0001) {
                    $problems[] = sprintf('%s: %s t is above its maximum of %s t.', $plate, self::trim($quantity), self::trim($maximum));
                }
            }
            if ($type === 'litres' && $quantity > 0) {
                $tank = (float) ($trailer['tank_capacity_l'] ?? 0) ?: (float) ($vehicle['cargo_tank_l'] ?? 0);
                if ($tank > 0 && $quantity > $tank) {
                    $problems[] = sprintf('%s: %s L is more than the tank holds (%s L).', $plate, number_format($quantity), number_format($tank));
                }
                if ($vehicle['fleet'] !== 'tanker') {
                    $problems[] = $plate . ' is not a tanker.';
                }
            }
            if ($type === 'containers') {
                $size = (string) ($row['container_size'] ?? '');
                if ($size === '') {
                    $problems[] = $plate . ': give the container size.';
                } else {
                    $allowed = array_filter(explode(',', (string) (($trailer['container_sizes'] ?? '') ?: $vehicle['container_sizes'])));
                    if ($allowed !== [] && !in_array($size, $allowed, true)) {
                        $problems[] = sprintf('%s may not carry %s.', $plate, Fleet::CONTAINER_SIZES[$size] ?? $size);
                    }
                }
            }

            // Control 6: fuel different from the standard needs a reason.
            $standardFuel = self::departureFuel(self::standards((int) $route['id'], (string) $vehicle['fleet'], substr($departure, 0, 10)));
            if (isset($row['fuel_litres']) && $row['fuel_litres'] !== '' && $standardFuel !== null
                && abs((float) $row['fuel_litres'] - (float) $standardFuel['quantity']) > 0.004 && trim((string) ($plan['non_standard_reason'] ?? '')) === '') {
                $problems[] = sprintf('%s: %s L of fuel instead of the standard %s L needs a reason (detour, waiting, load).', $plate, self::trim((float) $row['fuel_litres']), self::trim((float) $standardFuel['quantity']));
            }
        }

        $standards = self::standards((int) $route['id'], 'all', substr($departure, 0, 10));
        if ($standards['road_toll'] === null && $standards['mileage'] === null && $standards['fuel'] === []) {
            $problems[] = sprintf('Route %s has no standard amounts: add fuel, road toll and mileage on the route rate card.', $route['route_code']);
        }

        return array_values(array_unique($problems));
    }

    /** The fuel drawn at departure (the Kigali fill), from the rate card. */
    public static function departureFuel(array $standards): ?array
    {
        foreach ($standards['fuel'] as $fuel) {
            if (($fuel['fuel_on'] ?? 'departure') !== 'turnaround') {
                return $fuel;
            }
        }

        return null;
    }

    /**
     * Creates the trips and their F1 in one step (section 7, step 4), and
     * sends the F1 into the chain prepared by the OM.
     *
     * @param array<string, mixed> $plan
     * @return array{trip_ids: list<int>, request_ids: list<int>}
     */
    public static function plan(array $plan): array
    {
        $problems = self::problems($plan);
        if ($problems !== []) {
            throw new \RuntimeException(implode(' ', $problems));
        }

        $route = self::route((int) $plan['route_id']);
        $departure = date('Y-m-d H:i:s', (int) strtotime((string) (($plan['departure_at'] ?? '') ?: 'now')));
        $expectedBack = self::expectedBack($route, $departure);
        $companyId = \current_company_id();
        $rows = array_values(array_filter((array) $plan['rows'], static fn (array $row): bool => !empty($row['vehicle_id'])));

        $db = self::db();
        $owning = !$db->inTransaction();
        if ($owning) {
            $db->beginTransaction();
        }

        try {
            $group = count($rows) > 1 ? 'GRP-' . date('ymd-His') : null;
            $tripIds = [];
            $moneyLines = [];   // currency => lines
            $fuelLines = [];
            foreach ($rows as $index => $row) {
                $vehicle = Fleet::vehicle((int) $row['vehicle_id']);
                $driver = Fleet::driver((int) $row['driver_id']);
                $trailerId = !empty($row['trailer_id']) ? (int) $row['trailer_id'] : (!empty($vehicle['trailer_id']) ? (int) $vehicle['trailer_id'] : null);
                $code = Numbering::next($companyId, 'trip', Settings::get('trip_number_format', 'TRP-{YEAR}-{SEQ4}'), 'year');
                $quantity = (string) ($row['cargo_quantity'] ?? '') !== '' ? (float) $row['cargo_quantity'] : ((string) ($plan['cargo_quantity'] ?? '') !== '' ? (float) $plan['cargo_quantity'] : null);

                $db->prepare(
                    "INSERT INTO trips (company_id, reference_code, trip_group, customer_id, route_id, trip_type, vehicle_id, trailer_id, driver_id,
                                        pickup_location, destination, planned_departure_at, planned_arrival_at, expected_back_at, status,
                                        cargo_summary, cargo_type, cargo_quantity, container_numbers, container_size, service_due_reason, created_by, notes)
                     VALUES (?, ?, ?, ?, ?, 'delivery', ?, ?, ?, ?, ?, ?, ?, ?, 'planned', ?, ?, ?, ?, ?, ?, ?, ?)"
                )->execute([
                    $companyId, $code, $group, !empty($plan['customer_id']) ? (int) $plan['customer_id'] : null, (int) $route['id'],
                    (int) $vehicle['id'], $trailerId, (int) $driver['id'],
                    (string) $route['origin'], (string) ($route['turnaround_point'] ?: $route['destination']),
                    $departure, $expectedBack, $expectedBack, mb_substr((string) ($plan['cargo_summary'] ?? ''), 0, 255) ?: null,
                    $plan['cargo_type'] ?: null, $quantity, trim((string) ($row['container_numbers'] ?? '')) ?: null,
                    ($row['container_size'] ?? '') ?: null, trim((string) ($row['service_due_reason'] ?? '')) ?: null,
                    \current_user_id(), trim((string) ($plan['notes'] ?? '')) ?: null,
                ]);
                $tripId = (int) $db->lastInsertId();
                $tripIds[] = $tripId;

                $db->prepare("UPDATE vehicles SET status = 'assigned', service_first_on_return = IF(? IS NULL, service_first_on_return, 1) WHERE id = ?")
                    ->execute([trim((string) ($row['service_due_reason'] ?? '')) ?: null, (int) $vehicle['id']]);
                if ($trailerId !== null) {
                    $db->prepare("UPDATE trailers SET status = 'in_use' WHERE id = ?")->execute([$trailerId]);
                }
                self::event($tripId, 'planned', 'om', null, sprintf('Planned on %s by %s', $route['route_code'], \current_user_name()));

                // The F1 lines of this truck, from the rate card (or the OM's figure, with the standard kept beside it).
                $standards = self::standards((int) $route['id'], (string) $vehicle['fleet'], substr($departure, 0, 10));
                $payee = [
                    'payee_type' => 'driver', 'payee_id' => (int) $driver['id'], 'payee_name' => (string) $driver['full_name'],
                    'payment_method' => $driver['payment_method'] === 'cash' ? 'cash' : 'momo',
                    'momo_number' => $driver['momo_number'], 'momo_name' => $driver['momo_name'],
                ];
                $base = ['trip_id' => $tripId, 'vehicle_id' => (int) $vehicle['id'], 'trailer_id' => $trailerId, 'driver_id' => (int) $driver['id'], 'group_no' => $index + 1];

                $fuel = self::departureFuel($standards);
                if ($fuel !== null || (isset($row['fuel_litres']) && $row['fuel_litres'] !== '')) {
                    $litres = isset($row['fuel_litres']) && $row['fuel_litres'] !== '' ? (float) $row['fuel_litres'] : (float) $fuel['quantity'];
                    $fuelLines[] = $base + [
                        'item_code' => 'fuel', 'description' => 'Fuel at ' . ($fuel['fuel_point'] ?? $route['origin']), 'quantity' => $litres, 'unit' => 'L', 'unit_price' => 0, 'amount' => 0,
                        'standard_quantity' => $fuel['quantity'] ?? null,
                        'extra' => ['supplier_id' => $fuel['supplier_id'] ?? null, 'fuel_point' => $fuel['fuel_point'] ?? $route['origin']],
                    ];
                }
                foreach (['road_toll' => 'Road toll', 'mileage' => 'Mileage'] as $item => $label) {
                    $rate = $standards[$item];
                    $typed = $row[$item] ?? '';
                    if ($rate === null && ($typed === '' || $typed === null)) {
                        continue;
                    }
                    $amount = $typed !== '' && $typed !== null ? (float) str_replace(',', '', (string) $typed) : (float) $rate['amount'];
                    $currency = strtoupper((string) ($rate['currency'] ?? Company::baseCurrency($companyId)));
                    $moneyLines[$currency][] = $base + $payee + [
                        'item_code' => $item, 'description' => $label . ' ' . $route['route_code'], 'quantity' => 1, 'unit_price' => $amount, 'amount' => $amount,
                        'standard_amount' => $rate['amount'] ?? null,
                    ];
                }
                foreach ($standards['other'] as $other) {
                    $currency = strtoupper((string) $other['currency']);
                    $moneyLines[$currency][] = $base + $payee + [
                        'item_code' => 'other', 'description' => (string) ($other['item_label'] ?: 'Other'), 'quantity' => 1,
                        'unit_price' => (float) $other['amount'], 'amount' => (float) $other['amount'], 'standard_amount' => $other['amount'],
                    ];
                }
            }

            // One F1 per currency: road toll paid in USD stays in USD. The
            // fuel litres go on the first one.
            if ($moneyLines === []) {
                $moneyLines[Company::baseCurrency($companyId)] = [];
            }
            $requestIds = [];
            $first = true;
            $plates = implode(', ', array_map(static fn (int $id): string => (string) (Fleet::vehicle(self::find($id)['vehicle_id'] ?? 0)['plate_number'] ?? ''), $tripIds));
            foreach ($moneyLines as $currency => $lines) {
                $lines = $first ? array_merge($fuelLines, $lines) : $lines;
                $requestIds[] = RequestForms::create([
                    'form_code' => 'F1',
                    'company_id' => $companyId,
                    'title' => sprintf('Fuel, road toll and mileage — %s — %d truck%s (%s)', $route['route_code'], count($tripIds), count($tripIds) === 1 ? '' : 's', $plates),
                    'currency' => $currency,
                    'route_id' => (int) $route['id'],
                    'trip_id' => count($tripIds) === 1 ? $tripIds[0] : null,
                    'customer_id' => !empty($plan['customer_id']) ? (int) $plan['customer_id'] : null,
                    'needed_on' => substr($departure, 0, 10),
                    'non_standard_reason' => $plan['non_standard_reason'] ?? null,
                ], $lines);
                $first = false;
            }
            foreach ($tripIds as $tripId) {
                $db->prepare('UPDATE trips SET money_request_id = ? WHERE id = ?')->execute([$requestIds[0], $tripId]);
            }

            if ($owning) {
                $db->commit();
            }
        } catch (\Throwable $exception) {
            if ($owning && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        // Sent into the chain once everything is saved: prepared by the OM.
        foreach ($requestIds as $requestId) {
            RequestForms::send($requestId);
        }
        foreach ($tripIds as $tripId) {
            $trip = self::find($tripId);
            AuditLog::record('trip.planned', 'trips', (string) $trip['reference_code']);
            Notifier::toUser($trip['driver_user_id'] !== null ? (int) $trip['driver_user_id'] : null, 'New trip', sprintf('%s on %s with %s. The trip money is being approved; you will be told when it is sent.', $trip['reference_code'], $trip['route_code'], $trip['plate_number']), 'my-trips', 'info');
        }

        return ['trip_ids' => $tripIds, 'request_ids' => $requestIds];
    }

    // ---------------------------------------------------------------- money

    /**
     * Where the trip money stands (control 1): every F1 line for this trip
     * authorized, paid, and confirmed by the driver.
     *
     * @return array{requests: list<array>, authorized: bool, paid: bool, confirmed: bool, ready: bool}
     */
    public static function moneyState(int $tripId): array
    {
        $statement = self::db()->prepare(
            "SELECT DISTINCT r.id, r.request_no, r.status, r.currency, r.total_amount
               FROM form_requests r INNER JOIN form_request_lines l ON l.request_id = r.id
              WHERE r.form_code = 'F1' AND r.deleted_at IS NULL AND r.status NOT IN ('cancelled', 'draft') AND l.trip_id = ?"
        );
        $statement->execute([$tripId]);
        $requests = $statement->fetchAll(PDO::FETCH_ASSOC);

        $authorized = $requests !== [] && array_reduce($requests, static fn (bool $carry, array $r): bool => $carry && in_array($r['status'], ['authorized', 'partly_paid', 'paid'], true), true);

        $lines = self::db()->prepare(
            "SELECT l.pay_status, l.request_id, l.payee_id FROM form_request_lines l
               INNER JOIN form_requests r ON r.id = l.request_id
              WHERE l.trip_id = ? AND r.form_code = 'F1' AND r.status NOT IN ('cancelled', 'draft') AND l.pay_status <> 'not_payable'"
        );
        $lines->execute([$tripId]);
        $payable = $lines->fetchAll(PDO::FETCH_ASSOC);
        $paid = $authorized && $payable !== [] && array_reduce($payable, static fn (bool $carry, array $l): bool => $carry && $l['pay_status'] === 'paid', true);
        if ($authorized && $payable === []) {
            $paid = true; // nothing to pay the driver
        }

        $confirmed = false;
        if ($paid) {
            $trip = self::db()->prepare('SELECT driver_id, money_confirmed_at FROM trips WHERE id = ?');
            $trip->execute([$tripId]);
            $row = $trip->fetch(PDO::FETCH_ASSOC);
            if ($payable === [] || $row['money_confirmed_at'] !== null) {
                $confirmed = true;
            } else {
                $ids = implode(',', array_map('intval', array_unique(array_column($payable, 'request_id'))));
                $open = self::db()->prepare(
                    "SELECT COUNT(*) FROM payment_batch_lines WHERE request_id IN ({$ids}) AND payee_type = 'driver' AND payee_id = ? AND status = 'paid' AND confirmed_at IS NULL"
                );
                $open->execute([(int) $row['driver_id']]);
                $confirmed = (int) $open->fetchColumn() === 0;
            }
        }

        return ['requests' => $requests, 'authorized' => $authorized, 'paid' => $paid, 'confirmed' => $confirmed, 'ready' => $authorized && $paid && $confirmed];
    }

    /** F1 authorized: the fuel orders go to the stations; the Cashier pays the drivers. */
    public static function moneyAuthorized(array $request): void
    {
        foreach (RequestForms::lines((int) $request['id']) as $line) {
            if ($line['item_code'] === 'fuel' && (float) $line['quantity'] > 0 && !empty($line['trip_id'])) {
                FuelOrders::fromRequestLine($request, $line);
            }
        }
        foreach (self::tripsOf((int) $request['id']) as $tripId) {
            self::event($tripId, 'money_authorized', 'system', null, RequestForms::label($request) . ' authorized');
        }
    }

    /** F1 paid: each driver is asked to confirm; a trip whose driver confirmed is ready. */
    public static function moneyPaid(array $request): void
    {
        foreach (self::tripsOf((int) $request['id']) as $tripId) {
            self::event($tripId, 'money_paid', 'system', null, RequestForms::label($request) . ' paid');
            self::refreshReady($tripId);
        }
    }

    public static function moneyCancelled(array $request): void
    {
        foreach (self::tripsOf((int) $request['id']) as $tripId) {
            self::event($tripId, 'money_cancelled', 'system', null, RequestForms::label($request) . ' cancelled');
        }
    }

    /** A payee confirmed (in the app, by PIN or signature): trips of that request may become ready. */
    public static function receiptConfirmed(int $requestId): void
    {
        foreach (self::tripsOf($requestId) as $tripId) {
            $trip = self::find($tripId);
            if ($trip === null) {
                continue;
            }
            $open = self::db()->prepare(
                "SELECT COUNT(*) FROM payment_batch_lines bl
                   INNER JOIN form_requests r ON r.id = bl.request_id
                   INNER JOIN form_request_lines l ON l.request_id = r.id AND l.trip_id = ?
                  WHERE r.form_code = 'F1' AND bl.payee_type = 'driver' AND bl.payee_id = ? AND bl.status = 'paid' AND bl.confirmed_at IS NULL"
            );
            $open->execute([$tripId, (int) $trip['driver_id']]);
            if ((int) $open->fetchColumn() === 0 && $trip['money_confirmed_at'] === null) {
                self::db()->prepare('UPDATE trips SET money_confirmed_at = NOW() WHERE id = ?')->execute([$tripId]);
                self::event($tripId, 'money_received', 'driver', null, 'The driver confirmed the money arrived');
            }
            self::refreshReady($tripId);
        }
    }

    public static function supportAuthorized(array $request): void
    {
        if (!empty($request['trip_id'])) {
            self::event((int) $request['trip_id'], 'support_authorized', 'system', null, RequestForms::label($request) . ' authorized: ' . $request['title']);
        }
    }

    /** @return list<int> the trips a request pays for */
    public static function tripsOf(int $requestId): array
    {
        $statement = self::db()->prepare(
            'SELECT DISTINCT trip_id FROM form_request_lines WHERE request_id = ? AND trip_id IS NOT NULL
             UNION SELECT trip_id FROM form_requests WHERE id = ? AND trip_id IS NOT NULL'
        );
        $statement->execute([$requestId, $requestId]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function refreshReady(int $tripId): void
    {
        $trip = self::find($tripId);
        if ($trip === null || $trip['status'] !== 'planned') {
            return;
        }
        if (self::moneyState($tripId)['ready']) {
            self::db()->prepare("UPDATE trips SET status = 'ready' WHERE id = ?")->execute([$tripId]);
            self::event($tripId, 'ready', 'system', null, 'Money paid and confirmed: ready to depart');
            Notifier::forCompany((int) $trip['company_id'], static fn () => Notifier::bellToRole('logistics_manager', 'Ready to depart', sprintf('%s (%s) has its money and can leave.', $trip['reference_code'], $trip['plate_number']), 'trips', 'success', 'trips', (string) $tripId));
            Notifier::toUser($trip['driver_user_id'] !== null ? (int) $trip['driver_user_id'] : null, 'Ready to depart', sprintf('%s: tap Confirm departure when you leave.', $trip['reference_code']), 'my-trips', 'success');
        }
    }

    // ------------------------------------------------------------ on the road

    /**
     * One of the driver's (or the OM's) buttons.
     *
     * @param array{note?: string, odometer?: int|string, quantity?: float|string, place?: string, lat?: float|string, lng?: float|string, photo?: ?string, source?: string} $details
     */
    public static function act(int $tripId, string $action, array $details = []): string
    {
        $trip = self::find($tripId) ?? throw new \RuntimeException('That trip does not exist.');
        $rule = self::ACTIONS[$action] ?? throw new \RuntimeException('Unknown trip action.');
        [$from, $to, $label] = $rule;
        if (!in_array($trip['status'], $from, true)) {
            throw new \RuntimeException(sprintf('The trip is %s; "%s" is not possible now.', strtolower(self::STATUS[$trip['status']] ?? $trip['status']), $label));
        }
        $source = (string) ($details['source'] ?? (\current_role() === 'driver' ? 'driver' : 'om'));

        if ($action === 'depart') {
            // Control 1: no truck leaves before its driver has the money.
            $money = self::moneyState($tripId);
            if (!$money['ready']) {
                throw new \RuntimeException(!$money['authorized'] ? 'The trip money is not authorized yet.' : (!$money['paid'] ? 'The trip money has not been paid yet.' : 'The driver has not confirmed the money arrived.'));
            }
        }
        if ($action === 'loaded' && ($details['quantity'] ?? '') === '') {
            throw new \RuntimeException('Record the quantity loaded.');
        }

        $db = self::db();
        $updates = ['status = ?'];
        $values = [$to];
        $odometer = isset($details['odometer']) && $details['odometer'] !== '' ? (int) $details['odometer'] : null;
        switch ($action) {
            case 'depart':
                $updates[] = 'departure_at = NOW(), dispatched_at = NOW(), dispatched_by = ?';
                $values[] = \current_user_id();
                $updates[] = 'start_mileage = ?';
                $values[] = $odometer ?? (int) $trip['vehicle_mileage'];
                break;
            case 'arrive_turnaround':
                $days = (float) ($trip['max_return_days'] ?? 0);
                $updates[] = 'turnaround_at = NOW()';
                if ($days > 0) {
                    $updates[] = 'return_due_at = DATE_ADD(NOW(), INTERVAL ? MINUTE)';
                    $values[] = (int) round($days * 1440);
                }
                break;
            case 'loaded':
                $updates[] = 'loaded_quantity = ?';
                $values[] = (float) str_replace(',', '', (string) $details['quantity']);
                break;
            case 'arrive_base':
                $updates[] = 'arrival_at = NOW()';
                break;
        }
        $values[] = $tripId;
        $db->prepare('UPDATE trips SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($values);

        if (isset(self::VEHICLE_FOR[$to]) && !empty($trip['vehicle_id'])) {
            Fleet::setVehicleStatus((int) $trip['vehicle_id'], self::VEHICLE_FOR[$to]);
        }
        if ($action === 'depart') {
            $db->prepare("UPDATE drivers SET status = 'on_trip' WHERE id = ?")->execute([(int) $trip['driver_id']]);
        }
        if ($odometer !== null && $odometer > (int) $trip['vehicle_mileage']) {
            $db->prepare('UPDATE vehicles SET mileage = ? WHERE id = ?')->execute([$odometer, (int) $trip['vehicle_id']]);
        }

        self::event($tripId, $action, $source, $details, $details['note'] ?? $label);
        AuditLog::record('trip.' . $action, 'trips', (string) $trip['reference_code'], $details['note'] ?? null);

        $trip = self::find($tripId);
        $message = sprintf('%s (%s, %s): %s.', $trip['reference_code'], $trip['plate_number'], $trip['driver_name'], strtolower($label));
        Notifier::forCompany((int) $trip['company_id'], static fn () => Notifier::bellToRole('logistics_manager', $label, $message, 'trips', 'info', 'trips', (string) $tripId));

        if ($action === 'depart') {
            TripSheet::send($tripId);
        }
        if ($action === 'arrive_turnaround') {
            FuelOrders::raiseTopUp($trip);
        }

        return $label . ' recorded.';
    }

    /** The driver reports a delay or a problem: it reaches the OM at once. */
    public static function report(int $tripId, string $kind, string $note, array $details = []): void
    {
        $trip = self::find($tripId) ?? throw new \RuntimeException('That trip does not exist.');
        if (trim($note) === '') {
            throw new \RuntimeException('Say what happened.');
        }
        self::event($tripId, $kind, (string) ($details['source'] ?? 'driver'), $details, $note);
        Notifier::forCompany((int) $trip['company_id'], static fn () => Notifier::toRole('logistics_manager', $kind === 'delay' ? 'Delay reported' : 'Problem on the road', sprintf('%s (%s): %s', $trip['reference_code'], $trip['plate_number'], $note), 'trips', 'warning', 'trips', (string) $tripId));
    }

    /** The delivery note is done: empty, returning (section 5 status list). */
    public static function deliveryDone(int $tripId, float $delivered): void
    {
        $trip = self::find($tripId);
        if ($trip === null || !in_array($trip['status'], ['offloading', 'on_road', 'at_turnaround'], true)) {
            return;
        }
        self::db()->prepare("UPDATE trips SET status = 'returning', delivered_quantity = ?, completed_at = NOW() WHERE id = ?")->execute([$delivered, $tripId]);
        Fleet::setVehicleStatus((int) $trip['vehicle_id'], 'returning');
        self::event($tripId, 'delivered', 'driver', ['quantity' => $delivered], 'Delivery note done');
    }

    public static function deliveryConfirmed(array $request): void
    {
        if (!empty($request['trip_id'])) {
            self::event((int) $request['trip_id'], 'delivery_note_checked', 'om', null, RequestForms::label($request) . ' checked by the OM');
        }
        // A shortage above the customer's tolerance becomes a deduction (F9).
        Shortages::fromDeliveryNote($request);
    }

    // ------------------------------------------------------------- changes

    /** The OM replaces the driver or the truck mid-trip; the trip keeps one record. */
    public static function replace(int $tripId, string $what, int $newId, string $reason, ?int $odometer = null): void
    {
        $trip = self::find($tripId) ?? throw new \RuntimeException('That trip does not exist.');
        if (!in_array($trip['status'], self::ACTIVE, true)) {
            throw new \RuntimeException('Only a trip that is still running can change driver or truck.');
        }
        if (trim($reason) === '') {
            throw new \RuntimeException('Give the reason for the change (breakdown, accident, sickness...).');
        }
        $column = match ($what) {
            'driver' => 'driver_id',
            'vehicle' => 'vehicle_id',
            'trailer' => 'trailer_id',
            default => throw new \RuntimeException('Only the driver, the truck or the trailer can be replaced.'),
        };
        if (($other = FleetForms::activeTrip($column, $newId, $tripId)) !== null) {
            throw new \RuntimeException('That one is on trip ' . $other . '.');
        }
        if ($what === 'driver') {
            $driver = Fleet::driver($newId);
            if ($driver === null || $driver['status'] !== 'active') {
                throw new \RuntimeException('Choose an active driver.');
            }
        }

        $db = self::db();
        $old = $trip[$column] !== null ? (int) $trip[$column] : null;
        $db->prepare("UPDATE trips SET {$column} = ? WHERE id = ?")->execute([$newId, $tripId]);
        $db->prepare('INSERT INTO trip_changes (trip_id, change_type, old_id, new_id, reason, odometer, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$tripId, $what, $old, $newId, trim($reason), $odometer, \current_user_id()]);

        if ($what === 'vehicle') {
            if ($old !== null) {
                $db->prepare("UPDATE vehicles SET status = 'off_road', off_road_reason = ? WHERE id = ?")->execute(['Replaced on ' . $trip['reference_code'] . ': ' . trim($reason), $old]);
            }
            Fleet::setVehicleStatus($newId, self::VEHICLE_FOR[$trip['status']] ?? 'assigned');
        }
        if ($what === 'driver') {
            if ($old !== null) {
                $db->prepare("UPDATE drivers SET status = 'active' WHERE id = ? AND status = 'on_trip'")->execute([$old]);
            }
            if (!in_array($trip['status'], ['planned', 'ready'], true)) {
                $db->prepare("UPDATE drivers SET status = 'on_trip' WHERE id = ?")->execute([$newId]);
            }
        }
        if ($what === 'trailer') {
            if ($old !== null) {
                $db->prepare("UPDATE trailers SET status = 'available' WHERE id = ?")->execute([$old]);
            }
            $db->prepare("UPDATE trailers SET status = 'in_use' WHERE id = ?")->execute([$newId]);
        }
        self::event($tripId, 'replaced_' . $what, 'om', ['odometer' => $odometer], trim($reason));
        AuditLog::record('trip.replaced_' . $what, 'trips', (string) $trip['reference_code'], trim($reason));
    }

    public static function cancel(int $tripId, string $reason): void
    {
        $trip = self::find($tripId) ?? throw new \RuntimeException('That trip does not exist.');
        if (!in_array($trip['status'], ['planned', 'ready'], true)) {
            throw new \RuntimeException('Only a trip that has not left can be cancelled. Replace the truck or driver instead.');
        }
        if (trim($reason) === '') {
            throw new \RuntimeException('Give the reason for cancelling.');
        }
        $db = self::db();
        $db->prepare("UPDATE trips SET status = 'cancelled', notes = CONCAT(COALESCE(notes, ''), ?) WHERE id = ?")->execute(["\nCancelled: " . trim($reason), $tripId]);
        $db->prepare("UPDATE vehicles SET status = 'available' WHERE id = ? AND status = 'assigned'")->execute([(int) $trip['vehicle_id']]);
        if (!empty($trip['trailer_id'])) {
            $db->prepare("UPDATE trailers SET status = 'available' WHERE id = ? AND status = 'in_use'")->execute([(int) $trip['trailer_id']]);
        }
        self::event($tripId, 'cancelled', 'om', null, trim($reason));
        AuditLog::record('trip.cancelled', 'trips', (string) $trip['reference_code'], trim($reason));
        foreach (self::moneyState($tripId)['requests'] as $request) {
            if (in_array($request['status'], ['draft', 'returned'], true)) {
                RequestForms::cancel((int) $request['id'], 'Trip ' . $trip['reference_code'] . ' cancelled: ' . trim($reason));
            } elseif (in_array($request['status'], ['sent', 'verified', 'authorized'], true)) {
                Notifier::forCompany((int) $trip['company_id'], static fn () => Notifier::toRole('finance', 'Trip cancelled with money in the chain', sprintf('%s was cancelled (%s). Its F1 %s is %s: send it back or recover the money.', $trip['reference_code'], trim($reason), $request['request_no'], RequestForms::STATUS[$request['status']]), 'form_requests', 'warning', 'form_requests', (string) $request['id']));
            }
        }
    }

    // ------------------------------------------------------------- handover

    /** F14 confirmed by the OM: the trip closes and the truck is free (section 6). */
    public static function handoverConfirmed(array $request): void
    {
        $handover = $request['payload']['handover_id'] ?? null;
        if ($handover !== null) {
            Handover::confirm((int) $handover);
        }
    }

    /**
     * Closes the trip from the confirmed handover: kilometres, fuel check,
     * the truck back to Available (or first in line for its service).
     */
    public static function close(int $tripId, int $odometer): void
    {
        $trip = self::find($tripId);
        if ($trip === null || in_array($trip['status'], ['closed', 'cancelled'], true)) {
            return;
        }
        $db = self::db();
        $start = (int) ($trip['start_mileage'] ?? 0);
        $km = $start > 0 && $odometer >= $start ? $odometer - $start : null;

        // Section 11 step 4: litres given against kilometres and normal consumption.
        $given = (float) self::db()->query('SELECT COALESCE(SUM(COALESCE(litres_delivered, litres_ordered)), 0) FROM fuel_orders WHERE trip_id = ' . $tripId . " AND status <> 'cancelled'")->fetchColumn();
        $normal = (float) ($trip['normal_l_per_100km'] ?? 0);
        $expected = $km !== null && $normal > 0 ? round($km * $normal / 100, 2) : null;
        $flag = null;
        if ($expected !== null && $given > 0) {
            $limit = max(0.0, Settings::float('fuel_variance_percent', 3));
            $difference = $given - $expected;
            if (abs($difference) > $expected * $limit / 100) {
                $flag = $difference > 0
                    ? sprintf('Used more than normal: %s L given for %s km, %s L expected (%s L over).', self::trim($given), number_format($km), self::trim($expected), self::trim($difference))
                    : sprintf('Got less than expected: %s L given where %s L was expected for %s km.', self::trim($given), self::trim($expected), number_format($km));
            }
        }

        $db->prepare("UPDATE trips SET status = 'closed', closed_at = NOW(), end_mileage = ?, km_driven = ?, fuel_given_l = ?, fuel_expected_l = ?, fuel_flag = ? WHERE id = ?")
            ->execute([$odometer, $km, $given > 0 ? $given : null, $expected, $flag, $tripId]);
        $db->prepare('UPDATE vehicles SET mileage = GREATEST(mileage, ?) WHERE id = ?')->execute([$odometer, (int) $trip['vehicle_id']]);
        $vehicle = Fleet::vehicle((int) $trip['vehicle_id']);
        $nextStatus = (int) ($vehicle['service_first_on_return'] ?? 0) === 1 || Fleet::serviceState($vehicle)['state'] === 'due' ? 'available' : 'available';
        Fleet::setVehicleStatus((int) $trip['vehicle_id'], $nextStatus);
        $db->prepare("UPDATE drivers SET status = 'active' WHERE id = ? AND status = 'on_trip'")->execute([(int) $trip['driver_id']]);
        if (!empty($trip['trailer_id'])) {
            $db->prepare("UPDATE trailers SET status = 'available' WHERE id = ? AND status = 'in_use'")->execute([(int) $trip['trailer_id']]);
        }
        self::event($tripId, 'closed', 'om', ['odometer' => $odometer], $km !== null ? sprintf('Closed: %s km', number_format($km)) : 'Closed');
        AuditLog::record('trip.closed', 'trips', (string) $trip['reference_code']);

        Notifier::forCompany((int) $trip['company_id'], static function () use ($trip, $flag, $vehicle): void {
            if ($flag !== null) {
                Notifier::toRoles(['finance', 'logistics_manager'], 'Fuel check: ' . $trip['plate_number'], $trip['reference_code'] . ': ' . $flag, 'trips', 'warning', 'trips', (string) $trip['id']);
            }
            if ((int) ($vehicle['service_first_on_return'] ?? 0) === 1 || Fleet::serviceState($vehicle)['state'] === 'due') {
                Notifier::toRoles(['logistics_manager', 'warehouse_manager'], 'Service first', sprintf('%s is back from %s and due for service: put it first in line.', $vehicle['plate_number'], $trip['reference_code']), 'vehicles', 'warning', 'vehicles', (string) $vehicle['id']);
            }
        });
    }

    // --------------------------------------------------------------- alerts

    /**
     * Past the maximum days back from the turnaround point: the OM, the driver
     * and the MD are told once (section 7, step 6).
     */
    public static function lateAlerts(): int
    {
        $count = 0;
        $rows = self::db()->query(
            "SELECT id FROM trips WHERE deleted_at IS NULL AND return_due_at IS NOT NULL AND return_due_at < NOW() AND late_alerted_at IS NULL
                AND status IN ('at_turnaround', 'loading', 'on_road', 'offloading', 'returning')"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $id) {
            $trip = self::find((int) $id);
            if ($trip === null) {
                continue;
            }
            $message = sprintf('%s (%s, %s) left %s on %s and should have been back by %s.', $trip['reference_code'], $trip['plate_number'], $trip['driver_name'], $trip['turnaround_point'] ?: 'the turnaround point', human_date((string) $trip['turnaround_at'], true), human_date((string) $trip['return_due_at'], true));
            Notifier::forCompany((int) $trip['company_id'], static function () use ($trip, $message): void {
                Notifier::toRoles(['logistics_manager', 'management'], 'Trip past its expected time', $message, 'trips', 'danger', 'trips', (string) $trip['id']);
                Notifier::toUser($trip['driver_user_id'] !== null ? (int) $trip['driver_user_id'] : null, 'You are past the return time', $message . ' Report the reason in the app.', 'my-trips', 'danger');
            });
            self::db()->prepare('UPDATE trips SET late_alerted_at = NOW() WHERE id = ?')->execute([(int) $id]);
            $count++;
        }

        return $count;
    }

    // -------------------------------------------------------------- history

    /** @return list<array<string, mixed>> */
    public static function events(int $tripId): array
    {
        $statement = self::db()->prepare('SELECT e.*, u.full_name FROM trip_events e LEFT JOIN users u ON u.id = e.user_id WHERE e.trip_id = ? ORDER BY e.happened_at, e.id');
        $statement->execute([$tripId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function event(int $tripId, string $type, string $source, ?array $details, ?string $note): void
    {
        self::db()->prepare(
            'INSERT INTO trip_events (trip_id, event_type, happened_at, source, place, latitude, longitude, odometer, quantity, note, photo_file, user_id)
             VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $tripId, $type, in_array($source, ['driver', 'om', 'gps', 'system'], true) ? $source : 'system',
            trim((string) ($details['place'] ?? '')) ?: null,
            isset($details['lat']) && $details['lat'] !== '' ? (float) $details['lat'] : null,
            isset($details['lng']) && $details['lng'] !== '' ? (float) $details['lng'] : null,
            isset($details['odometer']) && $details['odometer'] !== '' && $details['odometer'] !== null ? (int) $details['odometer'] : null,
            isset($details['quantity']) && $details['quantity'] !== '' && $details['quantity'] !== null ? (float) str_replace(',', '', (string) $details['quantity']) : null,
            $note !== null ? mb_substr($note, 0, 255) : null,
            $details['photo'] ?? null,
            \current_user_id(),
        ]);
    }

    private static function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.');
    }
}
