<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;
use Support\Mailer;

/**
 * A forwarding file: the sea or air leg, the port, the customs, and the hand
 * over to the trucks.
 *
 * The cargo it moves is a shipment like any other, created with the job, so
 * the papers, the clearance file, the container and the trip all attach to
 * it as they always have. The job adds what happens before the truck: the
 * carrier, the vessel or flight, the bills of lading, the Kenyan references,
 * the insurance, and the milestones the customer is told about.
 */
final class FreightJobs
{
    public const MODES = ['sea' => 'Sea freight', 'air' => 'Air freight', 'road' => 'Road'];
    public const DIRECTIONS = ['import' => 'Import', 'export' => 'Export', 'transit' => 'Transit'];
    public const SCOPES = [
        'door_to_door' => 'Door to door', 'clearing_forwarding' => 'Clearing and forwarding', 'clearing_only' => 'Clearing only',
        'freight_only' => 'Freight only', 'transport_only' => 'Transport only',
    ];
    public const LOAD_TYPES = [
        'fcl' => 'FCL (full container)', 'lcl' => 'LCL (shared container)', 'loose' => 'Loose cargo', 'units' => 'Units (trucks, trailers, cars)',
        'out_of_gauge' => 'Out of gauge', 'project' => 'Project cargo',
    ];
    public const INCOTERMS = ['EXW' => 'EXW', 'FCA' => 'FCA', 'FAS' => 'FAS', 'FOB' => 'FOB', 'CFR' => 'CFR', 'CIF' => 'CIF', 'CPT' => 'CPT', 'CIP' => 'CIP', 'DAP' => 'DAP', 'DPU' => 'DPU', 'DDP' => 'DDP'];

    /** The milestones, in order, as the customer reads them. */
    public const MILESTONES = [
        'booked' => ['Booked', null],
        'departed' => ['Left the port of loading', 'departed_at'],
        'arrived' => ['Arrived at the port of discharge', 'arrived_at'],
        'customs' => ['In customs', 'customs_at'],
        'cleared' => ['Cleared by customs', 'cleared_at'],
        'inland' => ['On the truck to its destination', 'inland_at'],
        'delivered' => ['Delivered', 'delivered_at'],
    ];

    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function load(int $id): ?array
    {
        $statement = self::db()->prepare('SELECT * FROM freight_jobs WHERE id = ? AND deleted_at IS NULL');
        $statement->execute([$id]);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ------------------------------------------------------- the shipment

    /**
     * After a job is saved: it has its shipment, and the shipment carries the
     * facts it shares with the job, while the shipment has not left yet.
     */
    public static function syncShipment(int $jobId): void
    {
        $job = self::load($jobId);
        if ($job === null) {
            return;
        }
        $db = self::db();
        $customer = null;
        if (!empty($job['customer_id'])) {
            $statement = $db->prepare('SELECT customer_name, contact_name, phone FROM customers WHERE id = ?');
            $statement->execute([(int) $job['customer_id']]);
            $customer = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        // Inland, an import starts at the port; an export ends there.
        $from = $job['direction'] === 'export' ? ($job['origin'] ?: 'Origin') : ($job['port_of_discharge'] ?: ($job['origin'] ?: 'Port'));
        $to = $job['direction'] === 'export' ? ($job['port_of_loading'] ?: ($job['final_destination'] ?: 'Port')) : ($job['final_destination'] ?: ($job['port_of_discharge'] ?: 'Destination'));
        $container = trim(explode(',', (string) $job['container_numbers'])[0] ?? '');
        $cargoType = match ($job['load_type']) {
            'out_of_gauge', 'project' => 'bulk',
            default => 'general',
        };
        $values = [
            'customer_id' => $job['customer_id'] ?: null,
            'origin' => mb_substr((string) $from, 0, 150),
            'destination' => mb_substr((string) $to, 0, 150),
            'cargo_description' => mb_substr((string) $job['commodity'], 0, 255),
            'packages_count' => max(1, (int) ($job['packages'] ?? 1)),
            'weight_kg' => $job['weight_kg'],
            'volume_m3' => $job['volume_m3'],
            'container_no' => $container !== '' ? mb_substr($container, 0, 40) : null,
            'container_size' => in_array($job['load_type'], ['fcl', 'lcl'], true) ? null : 'loose',
            'bill_of_lading' => $job['house_bl'] ?: $job['master_bl'],
            'currency' => $job['currency'] ?: Currency::base(),
        ];

        if (empty($job['shipment_id'])) {
            $code = Reference::next('SHP', 'shipments', 'shipment_code');
            $db->prepare(
                'INSERT INTO shipments (shipment_code, freight_job_id, request_id, customer_id, consignee_name, consignee_phone, origin, destination, cargo_type, cargo_description,
                                        packages_count, weight_kg, volume_m3, container_no, container_size, bill_of_lading, currency, customer_reference, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'draft\')'
            )->execute([
                $code, $jobId, $job['request_id'] ?: null, $values['customer_id'], mb_substr((string) ($customer['contact_name'] ?? $customer['customer_name'] ?? 'Consignee'), 0, 150),
                $customer['phone'] ?? null, $values['origin'], $values['destination'], $cargoType, $values['cargo_description'], $values['packages_count'],
                $values['weight_kg'], $values['volume_m3'], $values['container_no'], $values['container_size'] ?? '20ft', $values['bill_of_lading'], $values['currency'], $job['job_code'],
            ]);
            $shipmentId = (int) $db->lastInsertId();
            $db->prepare('UPDATE freight_jobs SET shipment_id = ? WHERE id = ?')->execute([$shipmentId, $jobId]);
            if ($job['quoted_amount'] !== null && (float) $job['quoted_amount'] > 0) {
                $db->prepare("UPDATE shipments SET quoted_amount = ?, quote_basis = 'Agreed on freight job' WHERE id = ?")->execute([(float) $job['quoted_amount'], $shipmentId]);
            }
            AuditLog::record('freight_job.shipment_opened', 'freight_jobs', (string) $job['job_code'], null, ['shipment' => $code]);

            return;
        }

        // The price agreed on the job is what the shipment is invoiced at.
        if ($job['quoted_amount'] !== null && (float) $job['quoted_amount'] > 0) {
            $db->prepare("UPDATE shipments SET quoted_amount = ?, currency = ?, quote_basis = 'Agreed on freight job' WHERE id = ? AND status NOT IN ('delivered','cancelled','returned')")
               ->execute([(float) $job['quoted_amount'], $job['currency'] ?: Currency::base(), (int) $job['shipment_id']]);
        }

        // Once the shipment is on the road, its own record is the truth.
        $db->prepare(
            "UPDATE shipments SET freight_job_id = ?, customer_id = ?, origin = ?, destination = ?, cargo_description = ?, packages_count = ?, weight_kg = ?, volume_m3 = ?,
                    container_no = COALESCE(?, container_no), bill_of_lading = COALESCE(?, bill_of_lading)
              WHERE id = ? AND status IN ('draft','booked')"
        )->execute([
            $jobId, $values['customer_id'], $values['origin'], $values['destination'], $values['cargo_description'], $values['packages_count'],
            $values['weight_kg'], $values['volume_m3'], $values['container_no'], $values['bill_of_lading'], (int) $job['shipment_id'],
        ]);
    }

    // ------------------------------------------------------------ the rules

    public static function guard(string $action, array $job): ?string
    {
        if ($action === 'clear' && trim((string) $job['entry_no']) === '') {
            return 'Record the customs entry number before marking the cargo cleared.';
        }
        // Released from the port: every clearance file on the cargo is lodged
        // (a border further on is cleared when the truck gets there), and none
        // is held.
        if ($action === 'clear' && $job['direction'] !== 'export' && !empty($job['shipment_id'])) {
            $open = self::db()->prepare("SELECT reference, status FROM border_crossings WHERE shipment_id = ? AND deleted_at IS NULL AND status IN ('draft','documents_pending','rejected') ORDER BY id LIMIT 1");
            $open->execute([(int) $job['shipment_id']]);
            $file = $open->fetch(PDO::FETCH_ASSOC);
            if ($file !== false) {
                return $file['status'] === 'rejected'
                    ? sprintf('Clearance file %s is held by customs: sort it out first.', $file['reference'])
                    : sprintf('Clearance file %s is still open and not lodged: lodge its declaration first.', $file['reference']);
            }
        }
        if ($action === 'go_inland') {
            $trip = self::db()->prepare('SELECT t.reference_code FROM shipments s INNER JOIN trips t ON t.id = s.trip_id WHERE s.id = ?');
            $trip->execute([(int) ($job['shipment_id'] ?? 0)]);
            if ($trip->fetchColumn() === false) {
                return 'Load the shipment on a trip first: the truck and driver are what take it inland.';
            }
        }
        if ($action === 'depart' && trim((string) ($job['master_bl'] ?? '')) === '' && $job['mode'] !== 'road') {
            return $job['mode'] === 'air' ? 'Record the air waybill number before the cargo leaves.' : 'Record the bill of lading number before the vessel sails.';
        }

        return null;
    }

    /** A milestone reached: stamped, the shipment kept in step, the customer told. */
    public static function applied(string $action, array $job, string $reason): string
    {
        $db = self::db();
        $id = (int) $job['id'];
        $column = match ($action) {
            'depart' => 'departed_at', 'arrive' => 'arrived_at', 'start_customs' => 'customs_at', 'clear' => 'cleared_at',
            'go_inland' => 'inland_at', 'deliver' => 'delivered_at', 'close' => 'closed_at', default => null,
        };
        if ($column !== null) {
            $db->prepare("UPDATE freight_jobs SET {$column} = COALESCE({$column}, NOW()) WHERE id = ?")->execute([$id]);
        }

        $shipmentId = (int) ($job['shipment_id'] ?? 0);
        if ($shipmentId > 0) {
            match ($action) {
                'arrive' => $db->prepare("UPDATE shipments SET status = 'booked', booked_at = COALESCE(booked_at, NOW()) WHERE id = ? AND status = 'draft'")->execute([$shipmentId]),
                'clear' => $db->prepare("UPDATE shipments SET status = 'cleared' WHERE id = ? AND status IN ('draft','booked','loaded')")->execute([$shipmentId]),
                'deliver' => $db->prepare("UPDATE shipments SET status = 'delivered' WHERE id = ? AND status NOT IN ('cancelled','returned')")->execute([$shipmentId]),
                'cancel' => $db->prepare("UPDATE shipments SET status = 'cancelled' WHERE id = ? AND status IN ('draft','booked')")->execute([$shipmentId]),
                default => null,
            };
        }

        $fresh = self::load($id) ?? $job;
        $milestone = match ($action) {
            'depart' => 'departed', 'arrive' => 'arrived', 'start_customs' => 'customs', 'clear' => 'cleared', 'go_inland' => 'inland', 'deliver' => 'delivered', default => null,
        };
        $told = $milestone !== null ? self::tellCustomer($fresh, $milestone) : false;

        if (in_array($action, ['arrive', 'clear'], true)) {
            Notifier::bellToRole('logistics_manager', sprintf('%s: %s', $job['job_code'], self::MILESTONES[$milestone][0]), sprintf('%s · %s', $job['commodity'], $job['container_numbers'] ?: ($job['master_bl'] ?: '')), 'freight_jobs', 'info', 'freight_jobs', (string) $id);
        }
        if ($action === 'arrive') {
            Notifier::toRole('clearance_officer', 'Cargo arrived: ' . $job['job_code'], sprintf('%s arrived at %s. Open the clearance file and lodge the entry.', $job['commodity'], $job['port_of_discharge'] ?: 'the port'), 'freight_jobs', 'warning', 'freight_jobs', (string) $id);
        }

        return match ($action) {
            'cancel' => sprintf('%s cancelled: %s', $job['job_code'], $reason),
            'close' => sprintf('%s closed.', $job['job_code']),
            default => sprintf('%s: %s.%s', $job['job_code'], self::MILESTONES[$milestone][0] ?? 'updated', $told ? ' The customer has been emailed.' : ''),
        };
    }

    /** The customer hears each milestone, with the job's references. */
    public static function tellCustomer(array $job, string $milestone): bool
    {
        if ((int) ($job['notify_customer'] ?? 1) !== 1 || empty($job['customer_id'])) {
            return false;
        }
        $statement = self::db()->prepare('SELECT customer_name, contact_name, email FROM customers WHERE id = ?');
        $statement->execute([(int) $job['customer_id']]);
        $customer = $statement->fetch(PDO::FETCH_ASSOC);
        if ($customer === false || trim((string) $customer['email']) === '') {
            return false;
        }
        $what = self::MILESTONES[$milestone][0] ?? $milestone;
        $shipmentCode = !empty($job['shipment_id']) ? (string) self::scalar('SELECT shipment_code FROM shipments WHERE id = ?', [(int) $job['shipment_id']]) : '';
        $track = Mailer::link('track?code=' . rawurlencode($shipmentCode !== '' ? $shipmentCode : (string) $job['job_code']));

        try {
            $result = Mailer::send([
                'key' => 'freight-' . $job['id'] . '-' . $milestone,
                'category' => 'notification',
                'to' => (string) $customer['email'],
                'to_name' => (string) ($customer['contact_name'] ?: $customer['customer_name']),
                'subject' => sprintf('%s: %s', $job['job_code'], $what),
                'heading' => $what,
                'preheader' => sprintf('Your %s is now: %s.', mb_strtolower((string) $job['commodity']), mb_strtolower($what)),
                'lines' => [
                    sprintf('Dear %s,', $customer['contact_name'] ?: $customer['customer_name']),
                    sprintf('Your cargo (%s) has reached a new stage: %s.', $job['commodity'], mb_strtolower($what)),
                    $track !== '' ? sprintf('You can follow it at any time at %s.', $track) : 'We will write again at the next stage.',
                ],
                'facts' => array_filter([
                    'Our reference' => (string) $job['job_code'],
                    'Bill of lading / AWB' => (string) ($job['house_bl'] ?: $job['master_bl'] ?: ''),
                    'Container(s)' => (string) ($job['container_numbers'] ?? ''),
                    'Vessel / flight' => trim((string) $job['vessel_flight'] . ' ' . (string) $job['voyage_no']),
                    'Expected' => $milestone === 'departed' && $job['eta'] ? \human_date((string) $job['eta']) : '',
                ]),
                'entity_type' => 'freight_jobs',
                'entity_id' => (string) $job['job_code'],
            ]);
        } catch (\Throwable) {
            return false;
        }

        return (bool) ($result['sent'] ?? false) || (bool) ($result['queued'] ?? false);
    }

    /**
     * The truck carrying a cleared job has left: the job is on the truck, and
     * the customer hears it, without anybody pressing the job's own button.
     */
    public static function followTrip(int $tripId): void
    {
        $jobs = self::db()->prepare("SELECT fj.id FROM freight_jobs fj INNER JOIN shipments s ON s.id = fj.shipment_id WHERE s.trip_id = ? AND fj.deleted_at IS NULL AND fj.status = 'cleared'");
        $jobs->execute([$tripId]);
        foreach ($jobs->fetchAll(PDO::FETCH_COLUMN) as $jobId) {
            Workflow::apply('freight_jobs', (int) $jobId, 'go_inland');
        }
    }

    /** The cargo was handed over: its job is delivered too. */
    public static function followDelivery(int $shipmentId): void
    {
        $jobs = self::db()->prepare("SELECT id FROM freight_jobs WHERE shipment_id = ? AND deleted_at IS NULL AND status IN ('arrived','cleared','inland')");
        $jobs->execute([$shipmentId]);
        foreach ($jobs->fetchAll(PDO::FETCH_COLUMN) as $jobId) {
            Workflow::apply('freight_jobs', (int) $jobId, 'deliver');
        }
    }

    /** @return list<array<string, mixed>> jobs arriving soon or waiting at the port */
    public static function arriving(int $days = 7, int $limit = 8): array
    {
        try {
            $statement = self::db()->prepare(
                "SELECT fj.id, fj.job_code, fj.mode, fj.commodity, fj.container_numbers, fj.port_of_discharge, fj.eta, fj.status, c.customer_name
                   FROM freight_jobs fj LEFT JOIN customers c ON c.id = fj.customer_id
                  WHERE fj.deleted_at IS NULL
                    AND (fj.status IN ('arrived','customs') OR (fj.status IN ('booked','departed') AND fj.eta IS NOT NULL AND fj.eta <= DATE_ADD(NOW(), INTERVAL ? DAY)))
                  ORDER BY FIELD(fj.status, 'customs', 'arrived', 'departed', 'booked'), fj.eta
                  LIMIT " . max(1, $limit)
            );
            $statement->execute([$days]);

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * The sea or air leg of a shipment, for the customer's tracking page.
     *
     * @return list<array{what: string, when: ?string, done: bool}>
     */
    public static function timeline(array $job): array
    {
        $steps = [];
        foreach (self::MILESTONES as $key => [$label, $column]) {
            if (in_array($key, ['inland', 'delivered'], true)) {
                continue;
            }
            $when = $column === null ? (string) $job['created_at'] : ($job[$column] ?? null);
            $place = match ($key) {
                'departed' => $job['port_of_loading'] ? ' (' . $job['port_of_loading'] . ')' : '',
                'arrived' => $job['port_of_discharge'] ? ' (' . $job['port_of_discharge'] . ')' : '',
                default => '',
            };
            $steps[] = ['what' => $label . $place, 'when' => $when ?: null, 'done' => $when !== null && $when !== ''];
        }

        return $steps;
    }

    private static function scalar(string $sql, array $params = []): mixed
    {
        $statement = self::db()->prepare($sql);
        $statement->execute($params);
        $value = $statement->fetchColumn();

        return $value === false ? null : $value;
    }
}
