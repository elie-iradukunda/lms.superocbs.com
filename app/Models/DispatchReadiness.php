<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Is this truck ready to start its trip? Everything the system can know,
 * worked out rather than ticked by hand.
 *
 * For one shipment, or a whole trip, it looks at the load against the truck
 * (weight, volume, cooling, stock), every paper the shipment, the truck and
 * the driver must carry (present, valid, and still valid on the day the truck
 * arrives, not just today), the truck and driver being free and fit, and the
 * route being planned. Each point comes back as passed, a warning, or failed,
 * with the figures, so the person checking sees why; and with what to show
 * when it is opened (the paper's details, its scan, the record, or where to
 * add what is missing).
 *
 * What only a person standing by the truck can see (the cargo is loaded, the
 * count matches, the packaging is sound) is left to them.
 */
final class DispatchReadiness
{
    public const GROUPS = [
        'load' => 'Load and truck capacity',
        'papers' => 'Shipment papers',
        'truck' => 'Truck',
        'driver' => 'Driver',
        'route' => 'Route and plan',
    ];

    /** The truck papers every trip needs, and the ones a border crossing adds. */
    private const TRUCK_PAPERS = ['insurance' => 'Insurance', 'inspection' => 'Technical inspection'];
    private const TRUCK_PAPERS_CROSS_BORDER = ['permit' => 'Cross-border permit'];
    private const TRUCK_PAPER_NAMES = ['insurance' => 'Insurance', 'inspection' => 'Technical inspection', 'registration' => 'Registration', 'road_license' => 'Road licence', 'permit' => 'Cross-border permit', 'other' => 'Other'];

    private const DRIVER_PAPERS = ['driving_licence' => 'Driving licence'];
    private const DRIVER_PAPERS_CROSS_BORDER = ['passport' => 'Passport'];
    private const DRIVER_PAPER_NAMES = ['driver_id' => 'National ID', 'driving_licence' => 'Driving licence', 'passport' => 'Passport', 'permit' => 'Work / transit permit', 'other' => 'Other'];

    private static function db(): PDO
    {
        return Database::connection();
    }

    /** The analysis for the trip a shipment is on, with that shipment's papers. */
    public static function forShipment(int $shipmentId): ?array
    {
        $statement = self::db()->prepare('SELECT * FROM shipments WHERE id = ? AND deleted_at IS NULL');
        $statement->execute([$shipmentId]);
        $shipment = $statement->fetch(PDO::FETCH_ASSOC);
        if ($shipment === false) {
            return null;
        }

        return self::analyse($shipment['trip_id'] !== null ? (int) $shipment['trip_id'] : null, [$shipment]);
    }

    /** The analysis for a whole trip and every shipment on it. */
    public static function forTrip(int $tripId): ?array
    {
        $statement = self::db()->prepare("SELECT * FROM shipments WHERE trip_id = ? AND deleted_at IS NULL AND status <> 'cancelled' ORDER BY id");
        $statement->execute([$tripId]);

        return self::analyse($tripId, $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param list<array> $shipments
     * @return array{trip: ?array, items: list<array{group: string, label: string, state: string, detail: string, more: array}>, groups: array<string, string>, ready: bool, failures: list<string>}
     */
    private static function analyse(?int $tripId, array $shipments): array
    {
        $items = [];
        $add = static function (string $group, string $label, string $state, string $detail = '', array $more = []) use (&$items): void {
            $items[] = ['group' => $group, 'label' => $label, 'state' => $state, 'detail' => $detail, 'more' => $more];
        };

        $trip = null;
        if ($tripId !== null) {
            $statement = self::db()->prepare('SELECT * FROM trips WHERE id = ? AND deleted_at IS NULL');
            $statement->execute([$tripId]);
            $trip = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if ($trip === null) {
            $first = $shipments[0] ?? null;
            $add('load', 'On a trip', 'fail', 'The shipment is not loaded on a trip yet: choose the trip on the shipment.',
                $first !== null ? ['open' => \url(['shipments', $first['id'], 'edit']), 'open_label' => 'Choose the trip'] : []);

            return self::result(null, $items);
        }

        $vehicle = $trip['vehicle_id'] !== null ? self::row('vehicles', (int) $trip['vehicle_id']) : null;
        $driver = $trip['driver_id'] !== null ? self::row('drivers', (int) $trip['driver_id']) : null;
        $arrival = (string) ($trip['planned_arrival_at'] ?: $trip['planned_departure_at'] ?: date('Y-m-d'));
        $arrivalDay = substr($arrival, 0, 10);
        $crossBorder = self::crossesBorder((int) $trip['id']);
        $tripLink = ['open' => \url(['trips', $trip['id']]), 'open_label' => 'Open trip ' . $trip['reference_code']];

        // ------------------------------------------------------------- load
        $add('load', 'On a trip', 'ok', sprintf('%s, %s to %s', $trip['reference_code'], $trip['pickup_location'], $trip['destination']), $tripLink + ['facts' => [
            'Trip' => $trip['reference_code'], 'From' => $trip['pickup_location'], 'To' => $trip['destination'],
            'Status' => Schema::label((string) $trip['status']),
        ]]);

        $loads = self::db()->prepare("SELECT s.id, s.shipment_code, s.weight_kg, s.volume_m3, s.cargo_type, s.packages_count, c.customer_name
                                        FROM shipments s LEFT JOIN customers c ON c.id = s.customer_id
                                       WHERE s.trip_id = ? AND s.deleted_at IS NULL AND s.status <> 'cancelled' ORDER BY s.id");
        $loads->execute([(int) $trip['id']]);
        $onBoard = $loads->fetchAll(PDO::FETCH_ASSOC);
        $kg = array_sum(array_map(static fn (array $s): float => (float) $s['weight_kg'], $onBoard));
        $volume = array_sum(array_map(static fn (array $s): float => (float) $s['volume_m3'], $onBoard));
        $cold = count(array_filter($onBoard, static fn (array $s): bool => in_array($s['cargo_type'], ['cold_chain', 'perishable'], true)));
        $loadFacts = [];
        foreach ($onBoard as $s) {
            $loadFacts[$s['shipment_code']] = trim(number_format((float) $s['weight_kg']) . ' kg · ' . number_format((float) $s['packages_count']) . ' packages · ' . ($s['customer_name'] ?? ''), ' ·');
        }

        $capacity = $vehicle !== null ? (float) $vehicle['capacity_kg'] : 0.0;
        if ($vehicle === null) {
            $add('load', 'Weight', 'fail', sprintf('%s kg on board, but no truck is assigned to carry it.', number_format($kg)), $tripLink + ['facts' => $loadFacts]);
        } elseif ($capacity <= 0) {
            $add('load', 'Weight', 'warn', sprintf('%s kg on board; the capacity of %s is not recorded.', number_format($kg), $vehicle['plate_number']),
                ['facts' => $loadFacts, 'open' => \url(['vehicles', $vehicle['id'], 'edit']), 'open_label' => 'Record the capacity of ' . $vehicle['plate_number']]);
        } else {
            $share = $kg / $capacity * 100;
            $add('load', 'Weight', $kg > $capacity ? 'fail' : 'ok',
                sprintf('%s kg of %s kg (%d%%) on %s, %d load%s', number_format($kg), number_format($capacity), (int) round($share), $vehicle['plate_number'], count($onBoard), count($onBoard) === 1 ? '' : 's')
                . ($kg > $capacity ? '. Overloaded: take a load off or use a bigger truck.' : ''),
                $tripLink + ['facts' => $loadFacts + ['On board' => number_format($kg) . ' kg', 'Truck carries' => number_format($capacity) . ' kg', 'Space left' => number_format(max(0, $capacity - $kg)) . ' kg']]);
        }

        $capacityM3 = $vehicle !== null ? (float) ($vehicle['capacity_m3'] ?? 0) : 0.0;
        if ($volume > 0 && $capacityM3 > 0) {
            $add('load', 'Volume', $volume > $capacityM3 ? 'fail' : 'ok', sprintf('%s m³ of %s m³', self::trim($volume), self::trim($capacityM3)),
                ['facts' => ['On board' => self::trim($volume) . ' m³', 'Truck holds' => self::trim($capacityM3) . ' m³', 'Space left' => self::trim(max(0, $capacityM3 - $volume)) . ' m³']]);
        }

        if ($cold > 0) {
            $cooled = $vehicle !== null && (int) $vehicle['has_cooling_unit'] === 1;
            $add('load', 'Cooling', $cooled ? 'ok' : 'fail', $cooled ? 'Cold-chain cargo on a truck with a cooling unit.' : 'Cold-chain cargo, but the truck has no cooling unit.');
        }

        $stock = StockIssue::guardTrip((int) $trip['id']);
        if ($stock !== null) {
            $add('load', 'Goods from our stock', 'fail', $stock);
        }

        foreach ($shipments as $shipment) {
            $code = (string) $shipment['shipment_code'];
            $shipmentLink = ['open' => \url(['shipments', $shipment['id']]), 'open_label' => 'Open shipment ' . $code];
            if ($shipment['status'] === 'draft') {
                $add('load', $code . ' booked', 'warn', 'Still Draft: set it to Booked once the load is confirmed.',
                    ['open' => \url(['shipments', $shipment['id'], 'edit']), 'open_label' => 'Edit ' . $code]);
            }
            $loose = in_array((string) ($shipment['container_size'] ?? ''), ['', 'loose'], true);
            if (!$loose) {
                $container = trim((string) ($shipment['container_no'] ?? ''));
                $seal = trim((string) ($shipment['seal_no'] ?? ''));
                $complete = $container !== '' && $seal !== '';
                $add('load', $code . ' container and seal', $complete ? 'ok' : 'fail',
                    $complete ? sprintf('Container %s, seal %s', $container, $seal) : 'The container number and the seal number are needed at every border.',
                    $shipmentLink + ['facts' => [
                        'Container' => $container ?: '—', 'Size' => Schema::label((string) $shipment['container_size']), 'Seal' => $seal ?: '—',
                        'Trailer plate' => $shipment['trailer_plate'] ?: '—', 'Bill of lading' => $shipment['bill_of_lading'] ?: '—',
                        'Customer LPO' => $shipment['customer_reference'] ?: '—',
                    ]]);
            }
            if (trim((string) ($shipment['consignee_phone'] ?? '')) === '') {
                $add('load', $code . ' receiver phone', 'warn', 'No phone for the receiver: the driver cannot call ahead.',
                    ['open' => \url(['shipments', $shipment['id'], 'edit']), 'open_label' => 'Add the phone']);
            }
        }

        // ----------------------------------------------------- shipment papers
        foreach ($shipments as $shipment) {
            $code = (string) $shipment['shipment_code'];
            $docs = self::db()->prepare('SELECT * FROM shipment_documents WHERE shipment_id = ? ORDER BY id');
            $docs->execute([(int) $shipment['id']]);
            $held = [];
            foreach ($docs->fetchAll(PDO::FETCH_ASSOC) as $doc) {
                $held[$doc['document_type']] = $doc;
            }
            foreach (DocumentPack::requiredFor($shipment) as $type) {
                $label = count($shipments) > 1 ? $code . ': ' . $type : $type;
                $doc = $held[$type] ?? null;
                if ($doc === null) {
                    $add('papers', $label, 'fail', 'Missing: add it under Shipment papers.', [
                        'facts' => ['Shipment' => $code, 'Paper' => $type, 'Status' => 'Not on file'],
                        'add' => \url(['shipment_documents', 'create'], ['shipment_id' => $shipment['id'], 'document_type' => $type]), 'add_label' => 'Add ' . $type,
                    ]);
                    continue;
                }
                [$state, $detail] = self::paperState((string) $doc['status'], $doc['expiry_date'], $arrivalDay, (string) ($doc['document_number'] ?? ''));
                $add('papers', $label, $state, $detail, self::paperMore('shipment_documents', $doc, [
                    'Shipment' => $code, 'Paper' => $type, 'Number' => $doc['document_number'] ?: '—',
                    'Issued' => $doc['issue_date'] ?: '—', 'Expires' => $doc['expiry_date'] ?: 'No expiry',
                    'Status' => Schema::label((string) $doc['status']), 'Notes' => $doc['notes'] ?? '',
                ]));
            }
        }

        // ------------------------------------------------------------- truck
        if ($vehicle === null) {
            $add('truck', 'Truck assigned', 'fail', 'Choose the truck on the trip.', ['open' => \url(['trips', $trip['id'], 'edit']), 'open_label' => 'Choose the truck']);
        } else {
            $plate = (string) $vehicle['plate_number'];
            $vehicleLink = ['open' => \url(['vehicles', $vehicle['id']]), 'open_label' => 'Open ' . $plate];
            $fit = !in_array($vehicle['status'], ['maintenance', 'inactive'], true);
            $add('truck', 'Truck assigned', $fit ? 'ok' : 'fail', $plate . ' · ' . trim($vehicle['vehicle_type'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model']) . ($fit ? '' : ' · ' . Schema::label((string) $vehicle['status'])),
                $vehicleLink + ['facts' => [
                    'Plate' => $plate, 'Type' => $vehicle['vehicle_type'], 'Make and model' => trim($vehicle['make'] . ' ' . $vehicle['model']),
                    'Carries' => number_format((float) $vehicle['capacity_kg']) . ' kg' . ((float) ($vehicle['capacity_m3'] ?? 0) > 0 ? ' · ' . self::trim((float) $vehicle['capacity_m3']) . ' m³' : ''),
                    'Cooling unit' => (int) $vehicle['has_cooling_unit'] === 1 ? 'Yes' : 'No', 'Mileage' => number_format((int) $vehicle['mileage']) . ' km',
                    'Next service' => $vehicle['next_service_date'] ?: '—', 'Status' => Schema::label((string) $vehicle['status']),
                ]]);

            $clash = self::clash((int) $trip['id'], 'vehicle_id', (int) $vehicle['id']);
            $add('truck', 'Free for this trip', $clash === null ? 'ok' : 'fail', $clash === null ? 'Not out on another trip.' : 'Already out on trip ' . $clash['reference_code'] . '.',
                $clash === null ? [] : ['open' => \url(['trips', $clash['id']]), 'open_label' => 'Open trip ' . $clash['reference_code']]);

            $repairs = self::db()->prepare("SELECT id, work_order_code, service_name, status, due_date FROM maintenance_orders WHERE vehicle_id = ? AND deleted_at IS NULL AND status IN ('open','scheduled','in_progress') ORDER BY id");
            $repairs->execute([(int) $vehicle['id']]);
            $open = $repairs->fetchAll(PDO::FETCH_ASSOC);
            if ($open === []) {
                $add('truck', 'Workshop', 'ok', 'No repair open.');
            } else {
                $inWorkshop = in_array('in_progress', array_column($open, 'status'), true);
                $facts = [];
                foreach ($open as $order) {
                    $facts[$order['work_order_code']] = $order['service_name'] . ' · ' . Schema::label((string) $order['status']) . ($order['due_date'] ? ' · due ' . $order['due_date'] : '');
                }
                $add('truck', 'Workshop', $inWorkshop ? 'fail' : 'warn', implode('; ', array_map(static fn (array $o): string => $o['work_order_code'] . ' ' . $o['service_name'] . ' (' . Schema::label((string) $o['status']) . ')', $open)),
                    ['facts' => $facts, 'open' => \url(['maintenance', $open[0]['id']]), 'open_label' => 'Open ' . $open[0]['work_order_code']]);
            }
            if (!empty($vehicle['next_service_date']) && $vehicle['next_service_date'] < $arrivalDay) {
                $add('truck', 'Service', 'warn', sprintf('Service due on %s, before the truck is back.', $vehicle['next_service_date']), $vehicleLink);
            }

            $papers = self::db()->prepare('SELECT * FROM vehicle_documents WHERE vehicle_id = ? AND deleted_at IS NULL AND status <> "cancelled" ORDER BY expires_on DESC');
            $papers->execute([(int) $vehicle['id']]);
            $held = [];
            foreach ($papers->fetchAll(PDO::FETCH_ASSOC) as $paper) {
                $held[$paper['document_type']] ??= $paper;
            }
            $needed = self::TRUCK_PAPERS + ($crossBorder ? self::TRUCK_PAPERS_CROSS_BORDER : []);
            $truckPaper = static function (string $type, array $paper) use ($add, $plate, $arrivalDay): void {
                [$state, $detail] = self::paperState((string) $paper['status'], $paper['expires_on'], $arrivalDay, (string) ($paper['document_number'] ?? ''), ['expiring']);
                $add('truck', self::TRUCK_PAPER_NAMES[$type] ?? ucfirst($type), $state, $detail, self::paperMore('vehicle_documents', $paper, [
                    'Truck' => $plate, 'Paper' => self::TRUCK_PAPER_NAMES[$type] ?? ucfirst($type), 'Number' => $paper['document_number'] ?: '—',
                    'Issued by' => $paper['provider_name'] ?: '—', 'Issued' => $paper['issued_on'] ?: '—', 'Expires' => $paper['expires_on'] ?: 'No expiry',
                    'Status' => Schema::label((string) $paper['status']), 'Notes' => $paper['notes'] ?? '',
                ]));
            };
            foreach ($needed as $type => $label) {
                if (!isset($held[$type])) {
                    $add('truck', $label, 'fail', sprintf('Not on file for %s: add it under Fleet › Vehicle documents.', $plate), [
                        'facts' => ['Truck' => $plate, 'Paper' => $label, 'Status' => 'Not on file'],
                        'add' => \url(['vehicle_documents', 'create'], ['vehicle_id' => $vehicle['id'], 'document_type' => $type]), 'add_label' => 'Add ' . $label,
                    ]);
                    continue;
                }
                $truckPaper($type, $held[$type]);
            }
            foreach (array_diff_key($held, $needed) as $type => $paper) {
                $truckPaper((string) $type, $paper);
            }
        }

        // ------------------------------------------------------------ driver
        if ($driver === null) {
            $add('driver', 'Driver assigned', 'fail', 'Choose the driver on the trip.', ['open' => \url(['trips', $trip['id'], 'edit']), 'open_label' => 'Choose the driver']);
        } else {
            $name = (string) $driver['full_name'];
            $add('driver', 'Driver assigned', $driver['status'] === 'inactive' ? 'fail' : 'ok', $name . ($driver['status'] === 'inactive' ? ' is inactive.' : ''), [
                'open' => \url(['drivers', $driver['id']]), 'open_label' => 'Open ' . $name,
                'facts' => ['Name' => $name, 'Phone' => $driver['phone'] ?? '—', 'Licence' => trim(($driver['license_number'] ?? '') . ' ' . ($driver['license_class'] ? '(class ' . $driver['license_class'] . ')' : '')),
                            'Licence expires' => $driver['license_expiry'] ?: '—', 'Status' => Schema::label((string) $driver['status'])],
            ]);

            $clash = self::clash((int) $trip['id'], 'driver_id', (int) $driver['id']);
            $add('driver', 'Free for this trip', $clash === null ? 'ok' : 'fail', $clash === null ? 'Not out on another trip.' : 'Already out on trip ' . $clash['reference_code'] . '.',
                $clash === null ? [] : ['open' => \url(['trips', $clash['id']]), 'open_label' => 'Open trip ' . $clash['reference_code']]);

            $expiry = (string) ($driver['license_expiry'] ?? '');
            $driverEdit = ['open' => \url(['drivers', $driver['id'], 'edit']), 'open_label' => 'Edit ' . $name];
            if ($expiry === '') {
                $add('driver', 'Licence on the driver record', 'warn', 'No licence expiry date recorded for ' . $name . '.', $driverEdit);
            } else {
                [$state, $detail] = self::paperState('valid', $expiry, $arrivalDay, (string) ($driver['license_number'] ?? ''));
                $add('driver', 'Licence on the driver record', $state, $detail, $driverEdit + ['facts' => ['Licence' => $driver['license_number'] ?? '—', 'Expires' => $expiry]]);
            }

            $papers = self::db()->prepare('SELECT * FROM driver_documents WHERE driver_id = ? AND status <> "rejected" ORDER BY expiry_date DESC');
            $papers->execute([(int) $driver['id']]);
            $held = [];
            foreach ($papers->fetchAll(PDO::FETCH_ASSOC) as $paper) {
                $held[$paper['document_type']] ??= $paper;
            }
            $needed = self::DRIVER_PAPERS + ($crossBorder ? self::DRIVER_PAPERS_CROSS_BORDER : []);
            $driverPaper = static function (string $type, array $paper) use ($add, $name, $arrivalDay): void {
                [$state, $detail] = self::paperState((string) $paper['status'], $paper['expiry_date'], $arrivalDay, (string) ($paper['document_number'] ?? ''));
                $add('driver', self::DRIVER_PAPER_NAMES[$type] ?? ucfirst($type), $state, $detail, self::paperMore('driver_documents', $paper, [
                    'Driver' => $name, 'Paper' => self::DRIVER_PAPER_NAMES[$type] ?? ucfirst($type), 'Number' => $paper['document_number'] ?: '—',
                    'Issued' => $paper['issue_date'] ?: '—', 'Expires' => $paper['expiry_date'] ?: 'No expiry', 'Status' => Schema::label((string) $paper['status']),
                ]));
            };
            foreach ($needed as $type => $label) {
                if (!isset($held[$type])) {
                    $add('driver', $label, 'fail', sprintf('Not on file for %s: add it under Fleet › Driver papers.', $name), [
                        'facts' => ['Driver' => $name, 'Paper' => $label, 'Status' => 'Not on file'],
                        'add' => \url(['driver_documents', 'create'], ['driver_id' => $driver['id'], 'document_type' => $type]), 'add_label' => 'Add ' . $label,
                    ]);
                    continue;
                }
                $driverPaper($type, $held[$type]);
            }
            foreach (array_diff_key($held, $needed) as $type => $paper) {
                $driverPaper((string) $type, $paper);
            }
        }

        // ------------------------------------------------------------- route
        $tripEdit = ['open' => \url(['trips', $trip['id'], 'edit']), 'open_label' => 'Edit the trip'];
        $add('route', 'From and to', trim((string) $trip['pickup_location']) !== '' && trim((string) $trip['destination']) !== '' ? 'ok' : 'fail',
            $trip['pickup_location'] . ' to ' . $trip['destination'], $tripEdit + ['facts' => ['From' => $trip['pickup_location'], 'To' => $trip['destination'], 'Cargo' => $trip['cargo_summary'] ?? '']]);

        $departure = (string) ($trip['planned_departure_at'] ?? '');
        $plannedArrival = (string) ($trip['planned_arrival_at'] ?? '');
        $times = ['facts' => ['Planned departure' => $departure ?: '—', 'Planned arrival' => $plannedArrival ?: '—']] + $tripEdit;
        if ($departure === '') {
            $add('route', 'Planned departure', 'warn', 'No planned departure time.', $times);
        }
        if ($plannedArrival === '') {
            $add('route', 'Planned arrival', 'warn', 'No planned arrival: on-time delivery cannot be measured.', $times);
        } elseif ($departure !== '' && strtotime($plannedArrival) <= strtotime($departure)) {
            $add('route', 'Planned times', 'fail', sprintf('Arrival %s is not after departure %s.', $plannedArrival, $departure), $times);
        } else {
            $add('route', 'Planned times', 'ok', ($departure !== '' ? 'Leaves ' . date('d M Y H:i', (int) strtotime($departure)) . ', ' : '') . 'arrives ' . date('d M Y H:i', (int) strtotime($plannedArrival)), $times);
        }

        $stops = self::db()->prepare('SELECT stop_type, location_name, planned_arrival_at, contact_name, contact_phone FROM trip_stops WHERE trip_id = ? ORDER BY stop_sequence, id');
        $stops->execute([(int) $trip['id']]);
        $list = $stops->fetchAll(PDO::FETCH_ASSOC);
        $stopFacts = [];
        foreach ($list as $n => $stop) {
            $stopFacts[($n + 1) . '. ' . Schema::label((string) $stop['stop_type'])] = trim($stop['location_name']
                . ($stop['planned_arrival_at'] ? ' · ' . date('d M H:i', (int) strtotime((string) $stop['planned_arrival_at'])) : '')
                . ($stop['contact_name'] ? ' · ' . $stop['contact_name'] : '') . ($stop['contact_phone'] ? ' ' . $stop['contact_phone'] : ''));
        }
        if ($list === []) {
            $add('route', 'Stops', 'warn', 'No stops recorded. A straight run needs none; a border crossing should list the border.', $tripLink);
        } else {
            $borders = array_column(array_filter($list, static fn (array $s): bool => $s['stop_type'] === 'checkpoint'), 'location_name');
            $add('route', 'Stops', 'ok', sprintf('%d stop%s%s', count($list), count($list) === 1 ? '' : 's', $borders !== [] ? ', borders: ' . implode(', ', $borders) : ''), $tripLink + ['facts' => $stopFacts]);
        }

        return self::result($trip, $items);
    }

    /** Which of the boxes on the dispatch check the system can tick, given the analysis. */
    public static function ticks(array $analysis): array
    {
        $passed = static function (string $group) use ($analysis): bool {
            foreach ($analysis['items'] as $item) {
                if ($item['group'] === $group && $item['state'] === 'fail') {
                    return false;
                }
            }

            return true;
        };

        return [
            'documents_verified' => $passed('papers') ? 1 : 0,
            'vehicle_verified' => $passed('truck') && $passed('load') ? 1 : 0,
            'driver_verified' => $passed('driver') ? 1 : 0,
            'route_verified' => $passed('route') ? 1 : 0,
        ];
    }

    // ------------------------------------------------------------ internals

    private static function result(?array $trip, array $items): array
    {
        $failures = array_values(array_map(
            static fn (array $item): string => $item['label'] . ($item['detail'] !== '' ? ': ' . $item['detail'] : ''),
            array_filter($items, static fn (array $item): bool => $item['state'] === 'fail')
        ));

        return ['trip' => $trip, 'items' => $items, 'groups' => self::GROUPS, 'ready' => $failures === [], 'failures' => $failures];
    }

    /** A paper's details for the window: its facts, its scan when one was attached, its record. */
    private static function paperMore(string $module, array $paper, array $facts): array
    {
        $file = trim((string) ($paper['document_file'] ?? ''));
        $more = [
            'facts' => array_filter($facts, static fn ($v): bool => (string) $v !== ''),
            'open' => \url([$module, $paper['id']]), 'open_label' => 'Open the record',
            'edit' => \url([$module, $paper['id'], 'edit']),
        ];
        if ($file !== '') {
            $more['file'] = \url(array_merge(['files'], explode('/', $file)));
        }

        return $more;
    }

    /**
     * A paper is good when it is marked valid and is still valid the day the
     * truck arrives: an insurance that runs out at the Gatuna border is no use.
     *
     * @return array{0: string, 1: string} state and detail
     */
    private static function paperState(string $status, ?string $expiry, string $arrivalDay, string $number, array $alsoGood = []): array
    {
        $prefix = $number !== '' ? 'No. ' . $number . ' · ' : '';
        if ($status !== 'valid' && !in_array($status, $alsoGood, true)) {
            return ['fail', $prefix . 'marked ' . Schema::label($status) . '.'];
        }
        if ($expiry === null || $expiry === '') {
            return ['ok', rtrim($prefix . 'no expiry', ' ·')];
        }
        $today = date('Y-m-d');
        if ($expiry < $today) {
            return ['fail', $prefix . 'expired on ' . $expiry . '.'];
        }
        if ($expiry < $arrivalDay) {
            return ['fail', $prefix . 'expires on ' . $expiry . ', before the truck arrives on ' . $arrivalDay . '.'];
        }
        $days = (int) floor((strtotime($expiry) - strtotime($arrivalDay)) / 86400);
        if ($days <= Settings::int('document_alert_days', 30)) {
            return ['warn', $prefix . 'valid to ' . $expiry . ', only ' . $days . ' day' . ($days === 1 ? '' : 's') . ' after arrival: renew soon.'];
        }

        return ['ok', $prefix . 'valid to ' . $expiry];
    }

    /** Does the route cross a border? Then passports and permits are needed too. */
    private static function crossesBorder(int $tripId): bool
    {
        $statement = self::db()->prepare("SELECT COUNT(*) FROM trip_stops WHERE trip_id = ? AND stop_type = 'checkpoint'");
        $statement->execute([$tripId]);
        if ((int) $statement->fetchColumn() > 0) {
            return true;
        }
        $crossings = self::db()->prepare('SELECT COUNT(*) FROM border_crossings WHERE trip_id = ?');
        try {
            $crossings->execute([$tripId]);

            return (int) $crossings->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{id: int, reference_code: string}|null the other trip already out with this truck or driver */
    private static function clash(int $tripId, string $column, int $id): ?array
    {
        $statement = self::db()->prepare(
            "SELECT id, reference_code FROM trips WHERE id <> ? AND deleted_at IS NULL AND status IN ('loading','in_transit') AND {$column} = ? LIMIT 1"
        );
        $statement->execute([$tripId, $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private static function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.');
    }

    private static function row(string $table, int $id): ?array
    {
        $statement = self::db()->prepare("SELECT * FROM {$table} WHERE id = ? AND deleted_at IS NULL");
        $statement->execute([$id]);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
