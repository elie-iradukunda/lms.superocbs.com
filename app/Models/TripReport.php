<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;
use Support\Mailer;

/**
 * The trip report: everything about one trip, written out in full.
 *
 * Made when the truck is dispatched and kept up to date after: the trip and
 * who planned and dispatched it, the truck and the driver, the route with its
 * stops and borders, every load with its container, seal and receiver, every
 * paper the shipment, truck and driver carry, the final check before leaving,
 * the deliveries, the border clearances, and, for the office only, the money.
 *
 * One set of facts, three ways out: a printable page, a PDF and an Excel
 * workbook. The driver's copy never shows prices; the office copy does.
 */
final class TripReport
{
    private static function db(): PDO
    {
        return Database::connection();
    }

    /** @return array<string, mixed>|null every section of the report, or null when the trip does not exist */
    public static function data(int $tripId, bool $commercial): ?array
    {
        $db = self::db();
        $statement = $db->prepare(
            'SELECT t.*, c.customer_name, c.contact_name AS customer_contact, c.phone AS customer_phone,
                    r.reference_code AS request_code, r.lpo_number AS request_lpo, j.journey_code,
                    v.plate_number, v.vehicle_type, v.make, v.model, v.capacity_kg, v.capacity_m3, v.has_cooling_unit, v.mileage, v.ownership,
                    d.full_name AS driver_name, d.user_id AS driver_user_id, d.phone AS driver_phone, du.email AS driver_email, d.license_number, d.license_class, d.license_expiry, d.emergency_contact, d.emergency_phone,
                    u.full_name AS planned_by
               FROM trips t
               LEFT JOIN customers c ON c.id = t.customer_id
               LEFT JOIN transport_requests r ON r.id = t.request_id
               LEFT JOIN journeys j ON j.id = t.journey_id
               LEFT JOIN vehicles v ON v.id = t.vehicle_id
               LEFT JOIN drivers d ON d.id = t.driver_id
               LEFT JOIN users du ON du.id = d.user_id
               LEFT JOIN users u ON u.id = t.created_by
              WHERE t.id = ? AND t.deleted_at IS NULL'
        );
        $statement->execute([$tripId]);
        $trip = $statement->fetch(PDO::FETCH_ASSOC);
        if ($trip === false) {
            return null;
        }

        $dispatcher = $db->prepare(
            "SELECT a.user_id, u.full_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
              WHERE a.entity_type = 'trips' AND a.entity_id IN (?, ?) AND a.action_name LIKE '%dispatch%'
              ORDER BY a.id DESC LIMIT 1"
        );
        $dispatcher->execute([(string) $trip['reference_code'], (string) $trip['id']]);
        $dispatch = $dispatcher->fetch(PDO::FETCH_ASSOC) ?: [];
        // Recorded on the trip at the moment of dispatch; the audit trail is the fallback for older trips.
        if (!empty($trip['dispatched_by'])) {
            $who = $db->prepare('SELECT id AS user_id, full_name FROM users WHERE id = ?');
            $who->execute([(int) $trip['dispatched_by']]);
            $dispatch = $who->fetch(PDO::FETCH_ASSOC) ?: $dispatch;
        }
        $trip['dispatched_by'] = $dispatch['full_name'] ?? null;
        $trip['dispatched_by_id'] = isset($dispatch['user_id']) ? (int) $dispatch['user_id'] : null;

        $loads = $db->prepare(
            "SELECT s.*, c.customer_name, wo.warehouse_name AS from_depot, wd.warehouse_name AS to_depot, r.reference_code AS request_code
               FROM shipments s
               LEFT JOIN customers c ON c.id = s.customer_id
               LEFT JOIN warehouses wo ON wo.id = s.origin_warehouse_id
               LEFT JOIN warehouses wd ON wd.id = s.destination_warehouse_id
               LEFT JOIN transport_requests r ON r.id = s.request_id
              WHERE s.trip_id = ? AND s.deleted_at IS NULL AND s.status <> 'cancelled'
              ORDER BY s.id"
        );
        $loads->execute([$tripId]);
        $shipments = $loads->fetchAll(PDO::FETCH_ASSOC);

        $stops = $db->prepare('SELECT * FROM trip_stops WHERE trip_id = ? ORDER BY stop_sequence, id');
        $stops->execute([$tripId]);

        $papers = [];
        foreach ($shipments as $shipment) {
            $docs = $db->prepare('SELECT document_type, document_number, issue_date, expiry_date, status, document_file FROM shipment_documents WHERE shipment_id = ? ORDER BY id');
            $docs->execute([(int) $shipment['id']]);
            foreach ($docs->fetchAll(PDO::FETCH_ASSOC) as $doc) {
                $papers[] = ['for' => $shipment['shipment_code'], 'kind' => 'Shipment', 'type' => $doc['document_type'], 'number' => $doc['document_number'],
                             'issued' => $doc['issue_date'], 'expires' => $doc['expiry_date'], 'status' => $doc['status'], 'scan' => trim((string) $doc['document_file']) !== ''];
            }
        }
        if (!empty($trip['vehicle_id'])) {
            $docs = $db->prepare('SELECT document_type, document_number, provider_name, issued_on, expires_on, status, document_file FROM vehicle_documents WHERE vehicle_id = ? AND deleted_at IS NULL AND status <> "cancelled" ORDER BY document_type');
            $docs->execute([(int) $trip['vehicle_id']]);
            foreach ($docs->fetchAll(PDO::FETCH_ASSOC) as $doc) {
                $papers[] = ['for' => $trip['plate_number'], 'kind' => 'Truck', 'type' => Schema::label((string) $doc['document_type']), 'number' => $doc['document_number'],
                             'issued' => $doc['issued_on'], 'expires' => $doc['expires_on'], 'status' => $doc['status'], 'scan' => trim((string) $doc['document_file']) !== ''];
            }
        }
        if (!empty($trip['driver_id'])) {
            $docs = $db->prepare('SELECT document_type, document_number, issue_date, expiry_date, status, document_file FROM driver_documents WHERE driver_id = ? AND status <> "rejected" ORDER BY document_type');
            $docs->execute([(int) $trip['driver_id']]);
            foreach ($docs->fetchAll(PDO::FETCH_ASSOC) as $doc) {
                $papers[] = ['for' => $trip['driver_name'], 'kind' => 'Driver', 'type' => Schema::label((string) $doc['document_type']), 'number' => $doc['document_number'],
                             'issued' => $doc['issue_date'], 'expires' => $doc['expiry_date'], 'status' => $doc['status'], 'scan' => trim((string) $doc['document_file']) !== ''];
            }
        }

        $checks = [];
        foreach ($shipments as $shipment) {
            $check = $db->prepare('SELECT pc.*, u.full_name AS checked_by_name FROM shipment_pre_dispatch_checks pc LEFT JOIN users u ON u.id = pc.checked_by WHERE pc.shipment_id = ?');
            $check->execute([(int) $shipment['id']]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if ($row !== false) {
                $checks[] = ['shipment' => $shipment['shipment_code']] + $row;
            }
        }

        $deliveries = $db->prepare('SELECT dl.*, s.shipment_code FROM deliveries dl LEFT JOIN shipments s ON s.id = dl.shipment_id WHERE dl.trip_id = ? AND dl.deleted_at IS NULL ORDER BY dl.id');
        $deliveries->execute([$tripId]);

        $crossings = $db->prepare(
            'SELECT bc.reference, bc.direction, bc.customs_country, bc.declaration_no, bc.status, bc.arrived_at, bc.cleared_at, bc.released_at, bc.charges_total, bc.currency,
                    bp.post_name, s.shipment_code
               FROM border_crossings bc
               LEFT JOIN border_posts bp ON bp.id = bc.border_post_id
               LEFT JOIN shipments s ON s.id = bc.shipment_id
              WHERE bc.trip_id = ? AND bc.deleted_at IS NULL ORDER BY bc.id'
        );
        $crossings->execute([$tripId]);

        $totals = [
            'loads' => count($shipments),
            'weight' => array_sum(array_map(static fn (array $s): float => (float) $s['weight_kg'], $shipments)),
            'volume' => array_sum(array_map(static fn (array $s): float => (float) $s['volume_m3'], $shipments)),
            'packages' => array_sum(array_map(static fn (array $s): float => (float) $s['packages_count'], $shipments)),
        ];

        $money = null;
        if ($commercial) {
            $money = self::money($tripId, $shipments);
        }

        // What happens on the road and after: fuel and expenses, the container,
        // goods put into storage, and (office copy) the invoice and its payments.
        $fuel = $db->prepare('SELECT f.*, d.full_name AS driver_name FROM fuel_records f LEFT JOIN drivers d ON d.id = f.driver_id WHERE f.trip_id = ? AND f.deleted_at IS NULL ORDER BY f.purchased_at, f.id');
        $fuel->execute([$tripId]);
        $expenses = $db->prepare('SELECT e.*, u.full_name AS submitted_by_name FROM expenses e LEFT JOIN users u ON u.id = e.submitted_by WHERE e.trip_id = ? AND e.deleted_at IS NULL ORDER BY e.expense_date, e.id');
        $expenses->execute([$tripId]);

        $shipmentIds = array_map(static fn (array $s): int => (int) $s['id'], $shipments) ?: [0];
        $in = implode(',', $shipmentIds);
        $containers = $db->prepare("SELECT * FROM containers WHERE deleted_at IS NULL AND (trip_id = ? OR shipment_id IN ($in)) ORDER BY id");
        $containers->execute([$tripId]);
        $storage = $db->query("SELECT sl.*, w.warehouse_name FROM storage_lots sl LEFT JOIN warehouses w ON w.id = sl.warehouse_id WHERE sl.deleted_at IS NULL AND sl.shipment_id IN ($in) ORDER BY sl.id");

        $invoices = [];
        if ($commercial) {
            $statement = $db->prepare("SELECT * FROM invoices WHERE trip_id = ? AND deleted_at IS NULL ORDER BY id");
            $statement->execute([$tripId]);
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $invoice) {
                $payments = $db->prepare('SELECT payment_code, amount, currency, method, reference, paid_at FROM payments WHERE invoice_id = ? AND deleted_at IS NULL ORDER BY paid_at');
                $payments->execute([(int) $invoice['id']]);
                $invoice['payments'] = $payments->fetchAll(PDO::FETCH_ASSOC);
                $invoices[] = $invoice;
            }
        }

        $report = [
            'trip' => $trip,
            'shipments' => $shipments,
            'stops' => $stops->fetchAll(PDO::FETCH_ASSOC),
            'papers' => $papers,
            'checks' => $checks,
            'deliveries' => $deliveries->fetchAll(PDO::FETCH_ASSOC),
            'crossings' => $crossings->fetchAll(PDO::FETCH_ASSOC),
            'fuel' => $fuel->fetchAll(PDO::FETCH_ASSOC),
            'expenses' => $expenses->fetchAll(PDO::FETCH_ASSOC),
            'containers' => $containers->fetchAll(PDO::FETCH_ASSOC),
            'storage' => $storage->fetchAll(PDO::FETCH_ASSOC),
            'invoices' => $invoices,
            'support' => SupportRequest::forTrip($tripId),
            'totals' => $totals,
            'readiness' => DispatchReadiness::forTrip($tripId),
            'money' => $money,
            'commercial' => $commercial,
            'company' => [
                'name' => \company_name(),
                'details' => \Support\Report::companyDetails(),
                'phone' => Settings::get('company_phone', ''),
                'email' => Settings::get('company_email', ''),
            ],
            'generated_at' => date('Y-m-d H:i'),
        ];
        $report['distance'] = TripProgress::distance($tripId);
        $report['signatures'] = self::signatures($report);
        $report['progress'] = self::progress($report);
        $report['timeline'] = self::timeline($report);

        return $report;
    }

    /**
     * The signatures for the three boxes at the foot of the report, each only
     * once that person has done their part: whoever pressed Dispatch, the
     * driver once the truck has left, and the receiver's own signature taken
     * at delivery.
     *
     * @return array{dispatched: ?string, driver: ?string, received: ?string, received_name: string} file paths
     */
    private static function signatures(array $r): array
    {
        $t = $r['trip'];
        $left = !empty($t['dispatched_at']) || in_array($t['status'], ['in_transit', 'delivered'], true);
        $received = null;
        $receivedName = '';
        foreach ($r['deliveries'] as $delivery) {
            $path = Signature::uploadedImage($delivery['recipient_signature'] ?? null);
            if ($path !== null) {
                $received = $path;
                $receivedName = (string) ($delivery['collected_by'] ?: $delivery['recipient_name']);
                break;
            }
            if ($delivery['status'] === 'delivered' && $receivedName === '') {
                $receivedName = (string) ($delivery['collected_by'] ?: $delivery['recipient_name']);
            }
        }

        return [
            'dispatched' => $left ? Signature::path($t['dispatched_by_id'] ?? null) : null,
            'driver' => $left ? Signature::path(isset($t['driver_user_id']) ? (int) $t['driver_user_id'] : null) : null,
            'received' => $received,
            'received_name' => $receivedName,
        ];
    }

    /**
     * Where the trip has got to: each step from planning to payment, done or
     * not, with when; the first one not done is where it is now. Every stop on
     * the route is a step, so a border reached shows as soon as it is recorded.
     *
     * @return array{stages: list<array{label: string, state: string, when: ?string, detail: string}>, current: string, done: int, total: int, percent: int}
     */
    private static function progress(array $r): array
    {
        $t = $r['trip'];
        $stages = [];
        $add = static function (string $label, bool $done, ?string $when = null, string $detail = '') use (&$stages): void {
            $stages[] = ['label' => $label, 'state' => $done ? 'done' : 'todo', 'when' => $when ?: null, 'detail' => $detail];
        };

        $add('Trip planned', true, $t['created_at'], trim(($t['planned_by'] ?? '') !== '' ? 'by ' . $t['planned_by'] : ''));
        $add('Loads booked on the truck', $r['shipments'] !== [], null, $r['shipments'] !== [] ? sprintf('%d load%s, %s kg', count($r['shipments']), count($r['shipments']) === 1 ? '' : 's', number_format((float) $r['totals']['weight'])) : '');
        $ready = $r['shipments'] !== [] && count($r['checks']) >= count($r['shipments']) && array_filter($r['checks'], static fn (array $c): bool => $c['status'] !== 'ready') === [];
        $checkedAt = $r['checks'] !== [] ? max(array_map(static fn (array $c): string => (string) $c['checked_at'], $r['checks'])) : null;
        $add('Final check passed', $ready, $ready ? $checkedAt : null);
        $add('Trip started', !empty($t['dispatched_at']), $t['dispatched_at'] ?: null, ($t['dispatched_by'] ?? '') !== '' ? 'released by ' . $t['dispatched_by'] : '');

        $finished = in_array($t['status'], ['delivered'], true) || !empty($t['completed_at']);
        foreach ($r['stops'] as $stop) {
            $reached = in_array($stop['status'], ['arrived', 'completed', 'skipped'], true) || !empty($stop['actual_arrival_at']) || $finished;
            $what = match ($stop['stop_type']) {
                'pickup' => 'Loaded at',
                'dropoff' => 'Arrived at',
                'checkpoint' => 'Crossed',
                default => 'Passed',
            };
            $detail = $stop['status'] === 'skipped' ? 'skipped' : (!empty($stop['departed_at']) ? 'left ' . date('j M H:i', (int) strtotime((string) $stop['departed_at'])) : (!empty($stop['actual_arrival_at']) ? 'there now' : ''));
            foreach ($r['crossings'] as $crossing) {
                if ($stop['stop_type'] === 'checkpoint' && $crossing['post_name'] !== null && stripos((string) $stop['location_name'], strtok((string) $crossing['post_name'], ' ')) !== false) {
                    $detail = trim($detail . ' ' . $crossing['reference'] . ' ' . Schema::label((string) $crossing['status']));
                }
            }
            $add($what . ' ' . $stop['location_name'], $reached, $stop['actual_arrival_at'] ?: null, $detail);
        }
        if ($r['stops'] === []) {
            $add('Arrived at ' . $t['destination'], !empty($t['arrival_at']) || $finished, $t['arrival_at'] ?: null);
        }

        $deliveredAt = array_filter(array_column($r['deliveries'], 'delivered_at'));
        $allDelivered = $finished || ($r['deliveries'] !== [] && array_filter($r['deliveries'], static fn (array $d): bool => $d['status'] !== 'delivered') === []);
        $add('Goods delivered', $allDelivered, $deliveredAt !== [] ? max($deliveredAt) : ($t['completed_at'] ?: null),
            $r['deliveries'] !== [] ? implode(', ', array_map(static fn (array $d): string => $d['delivery_code'] . ' ' . Schema::label((string) $d['status']), $r['deliveries'])) : '');

        foreach ($r['containers'] as $container) {
            $add('Container ' . $container['container_no'] . ' returned empty', $container['status'] === 'returned' || !empty($container['returned_at']), $container['returned_at'] ?: null,
                $container['returned_at'] ? (string) ($container['return_depot'] ?? '') : ($container['unloaded_at'] ? 'unloaded ' . date('j M', (int) strtotime((string) $container['unloaded_at'])) : ''));
        }

        if ($r['commercial']) {
            $issued = array_filter($r['invoices'], static fn (array $i): bool => !in_array($i['status'], ['draft', 'cancelled'], true));
            $add('Invoiced', $issued !== [], $issued !== [] ? (string) min(array_column($issued, 'issue_date')) : null,
                implode(', ', array_map(static fn (array $i): string => $i['invoice_number'] . ' ' . Schema::label((string) $i['status']), $r['invoices'])));
            $paid = $issued !== [] && array_filter($issued, static fn (array $i): bool => $i['status'] !== 'paid') === [];
            $lastPayment = null;
            foreach ($issued as $invoice) {
                foreach ($invoice['payments'] as $payment) {
                    $lastPayment = max((string) $lastPayment, (string) $payment['paid_at']);
                }
            }
            $add('Paid by the customer', $paid, $paid ? $lastPayment : null);
        }

        if ($t['status'] === 'cancelled') {
            $stages[] = ['label' => 'Trip cancelled', 'state' => 'done', 'when' => null, 'detail' => ''];
        }

        $done = count(array_filter($stages, static fn (array $s): bool => $s['state'] === 'done'));
        $current = 'All done';
        $reached = '';
        foreach ($stages as $i => $stage) {
            if ($stage['state'] !== 'done') {
                $stages[$i]['state'] = 'now';
                $current = $stage['label'];
                break;
            }
            $reached = $stage['label'];
        }

        return ['stages' => $stages, 'current' => $current, 'reached' => $reached, 'done' => $done, 'total' => count($stages), 'percent' => count($stages) > 0 ? (int) round($done / count($stages) * 100) : 0];
    }

    /**
     * Everything that has happened on the trip, in the order it happened.
     *
     * @return list<array{when: string, what: string, detail: string}>
     */
    private static function timeline(array $r): array
    {
        $t = $r['trip'];
        $events = [];
        $add = static function (?string $when, string $what, string $detail = '') use (&$events): void {
            if ($when !== null && $when !== '') {
                $events[] = ['when' => $when, 'what' => $what, 'detail' => trim($detail)];
            }
        };
        $money = static fn (float $amount, ?string $currency): string => Currency::format($amount, (string) ($currency ?: Currency::base()));

        $add($t['created_at'], 'Trip planned', ($t['planned_by'] ?? '') !== '' ? 'by ' . $t['planned_by'] : '');
        foreach ($r['checks'] as $c) {
            $add($c['checked_at'], 'Final check: ' . Schema::label((string) $c['status']), $c['shipment'] . (($c['checked_by_name'] ?? '') !== '' ? ', by ' . $c['checked_by_name'] : ''));
        }
        $add($t['dispatched_at'], 'Trip started', ($t['start_mileage'] !== null ? number_format((int) $t['start_mileage']) . ' km on the clock, ' : '') . trim(($t['plate_number'] ?? '') . ', ' . ($t['driver_name'] ?? '') . (($t['dispatched_by'] ?? '') !== '' ? ', released by ' . $t['dispatched_by'] : ''), ', '));
        foreach ($r['stops'] as $stop) {
            $add($stop['actual_arrival_at'], 'Reached ' . $stop['location_name'], implode(' · ', array_filter([
                Schema::label((string) $stop['stop_type']),
                !empty($stop['arrival_mileage']) ? number_format((int) $stop['arrival_mileage']) . ' km' . ($t['start_mileage'] !== null ? ' (+' . number_format((int) $stop['arrival_mileage'] - (int) $t['start_mileage']) . ' km from the start)' : '') : '',
                TripProgress::CONDITIONS[$stop['arrival_condition'] ?? ''] ?? '',
                trim(str_replace("
", ' · ', (string) $stop['notes'])),
            ])));
            if (!empty($stop['departed_at']) && !empty($stop['actual_arrival_at'])) {
                $minutes = (int) round((strtotime((string) $stop['departed_at']) - strtotime((string) $stop['actual_arrival_at'])) / 60);
                $add($stop['departed_at'], 'Left ' . $stop['location_name'], 'after ' . ($minutes >= 60 ? intdiv($minutes, 60) . 'h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) . 'min' : $minutes . ' min') . ' there');
            }
        }
        foreach ($r['crossings'] as $x) {
            $where = (string) ($x['post_name'] ?: 'the border');
            $add($x['arrived_at'], 'At ' . $where, $x['reference']);
            $add($x['cleared_at'], 'Customs cleared at ' . $where, trim($x['reference'] . ' ' . ($x['declaration_no'] ? 'declaration ' . $x['declaration_no'] : '')));
            $add($x['released_at'], 'Released at ' . $where, $x['reference'] . ($r['commercial'] && (float) $x['charges_total'] > 0 ? ', charges ' . $money((float) $x['charges_total'], $x['currency']) : ''));
        }
        foreach ($r['fuel'] as $f) {
            $add($f['purchased_at'], 'Fuel', sprintf('%s L at %s%s', rtrim(rtrim(number_format((float) $f['litres'], 2), '0'), '.'), $f['station_name'] ?: 'a station', $f['mileage'] ? ', ' . number_format((int) $f['mileage']) . ' km' : ''));
        }
        foreach ($r['expenses'] as $e) {
            $add($e['expense_date'] . ' 00:00:00', 'Expense: ' . $e['category'], $money((float) $e['amount'], $e['currency']) . ', ' . Schema::label((string) $e['status']));
        }
        foreach ($r['containers'] as $c) {
            $add($c['picked_up_at'], 'Container ' . $c['container_no'] . ' picked up', (string) ($c['picked_up_from'] ?? ''));
            $add($c['unloaded_at'], 'Container ' . $c['container_no'] . ' unloaded');
            $add($c['returned_at'], 'Container ' . $c['container_no'] . ' returned empty', (string) ($c['return_depot'] ?? ''));
        }
        $add($t['arrival_at'], 'Arrived at ' . $t['destination']);
        foreach ($r['deliveries'] as $d) {
            $add($d['dropped_at'], 'Goods dropped', $d['delivery_code'] . ' at ' . $d['destination']);
            $add($d['delivered_at'], 'Delivered', $d['delivery_code'] . ' to ' . ($d['collected_by'] ?: $d['recipient_name']));
        }
        foreach ($r['storage'] as $lot) {
            $add($lot['received_at'], 'Received into storage', $lot['lot_code'] . ' at ' . ($lot['warehouse_name'] ?? '') . ', ' . rtrim(rtrim(number_format((float) $lot['quantity_received'], 2), '0'), '.') . ' ' . $lot['unit']);
        }
        foreach ($r['support'] as $help) {
            $amount = (float) $help['amount_requested'] > 0 ? ', ' . $money((float) $help['amount_requested'], $help['currency']) : '';
            $add($help['created_at'], 'Driver asked for support', $help['request_code'] . ': ' . $help['title'] . $amount . ($help['location'] ? ' at ' . $help['location'] : ''));
            if ($help['status'] === 'resolved') {
                $given = (float) $help['amount_given'] > 0 ? ', ' . $money((float) $help['amount_given'], $help['currency']) . ($help['given_method'] ? ' by ' . $help['given_method'] : '') : '';
                $add($help['resolved_at'] ?: $help['responded_at'], 'Support given', $help['request_code'] . ' by ' . ($help['responded_name'] ?? 'the office') . $given);
            } elseif ($help['status'] === 'rejected') {
                $add($help['responded_at'], 'Support refused', $help['request_code'] . ': ' . ($help['rejection_reason'] ?? ''));
            }
        }
        $add($t['completed_at'], 'Trip completed');
        foreach ($r['invoices'] as $invoice) {
            if ($invoice['status'] !== 'draft') {
                $add($invoice['issue_date'] . ' 00:00:00', 'Invoice ' . $invoice['invoice_number'] . ' issued', $money((float) $invoice['total_amount'], $invoice['currency']));
            }
            foreach ($invoice['payments'] as $payment) {
                $add($payment['paid_at'], 'Payment received', $payment['payment_code'] . ', ' . $money((float) $payment['amount'], $payment['currency']) . ' by ' . $payment['method']);
            }
        }

        usort($events, static fn (array $a, array $b): int => strcmp($a['when'], $b['when']));

        return $events;
    }

    /**
     * What the trip is worth and what it has cost so far, each in the currency
     * it was charged or paid in: the books are kept per currency, so nothing
     * here is converted.
     */
    private static function money(int $tripId, array $shipments): array
    {
        $db = self::db();
        $revenue = [];
        $lines = [];
        foreach ($shipments as $shipment) {
            $currency = (string) ($shipment['currency'] ?: Currency::base());
            $amount = (float) ($shipment['quoted_amount'] ?? 0);
            $revenue[$currency] = ($revenue[$currency] ?? 0) + $amount;
            $lines[] = ['what' => 'Freight ' . $shipment['shipment_code'] . ' (' . ($shipment['customer_name'] ?? '') . ')', 'basis' => (string) ($shipment['quote_basis'] ?? ''), 'currency' => $currency, 'amount' => $amount];
        }

        $costs = [];
        $costLines = [];
        $expenses = $db->prepare("SELECT category, amount, currency, status FROM expenses WHERE trip_id = ? AND deleted_at IS NULL AND status <> 'rejected'");
        $expenses->execute([$tripId]);
        foreach ($expenses->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $currency = (string) ($row['currency'] ?: Currency::base());
            $costs[$currency] = ($costs[$currency] ?? 0) + (float) $row['amount'];
            $costLines[] = ['what' => 'Expense: ' . $row['category'] . ' (' . Schema::label((string) $row['status']) . ')', 'currency' => $currency, 'amount' => (float) $row['amount']];
        }
        $fuel = $db->prepare('SELECT station_name, litres, unit_price, currency FROM fuel_records WHERE trip_id = ? AND deleted_at IS NULL');
        $fuel->execute([$tripId]);
        foreach ($fuel->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $currency = (string) ($row['currency'] ?: Currency::base());
            $amount = round((float) $row['litres'] * (float) $row['unit_price'], 2);
            $costs[$currency] = ($costs[$currency] ?? 0) + $amount;
            $costLines[] = ['what' => sprintf('Fuel: %s L at %s', rtrim(rtrim(number_format((float) $row['litres'], 2), '0'), '.'), $row['station_name'] ?: 'station'), 'currency' => $currency, 'amount' => $amount];
        }
        try {
            $bills = $db->prepare("SELECT bill_no, total_amount, currency FROM supplier_bills WHERE trip_id = ? AND deleted_at IS NULL AND status <> 'cancelled'");
            $bills->execute([$tripId]);
            foreach ($bills->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $currency = (string) ($row['currency'] ?: Currency::base());
                $costs[$currency] = ($costs[$currency] ?? 0) + (float) $row['total_amount'];
                $costLines[] = ['what' => 'Supplier bill ' . $row['bill_no'], 'currency' => $currency, 'amount' => (float) $row['total_amount']];
            }
        } catch (\Throwable) {
            // Older installs without supplier bills.
        }

        $recharge = [];
        $disbursements = $db->prepare("SELECT category, description, amount, currency, status FROM disbursements WHERE trip_id = ? AND deleted_at IS NULL AND status <> 'written_off'");
        $disbursements->execute([$tripId]);
        foreach ($disbursements->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $recharge[] = ['what' => trim($row['category'] . ': ' . $row['description'], ': '), 'currency' => (string) ($row['currency'] ?: Currency::base()), 'amount' => (float) $row['amount'], 'status' => Schema::label((string) $row['status'])];
        }

        $currencies = array_unique(array_merge(array_keys($revenue), array_keys($costs)));
        $margin = [];
        foreach ($currencies as $currency) {
            $margin[$currency] = ($revenue[$currency] ?? 0) - ($costs[$currency] ?? 0);
        }

        return ['lines' => $lines, 'revenue' => $revenue, 'cost_lines' => $costLines, 'costs' => $costs, 'margin' => $margin, 'recharge' => $recharge];
    }

    // -------------------------------------------------------------- outputs

    public static function filename(array $data, string $extension): string
    {
        return 'Trip-report-' . preg_replace('/[^A-Za-z0-9-]+/', '-', (string) $data['trip']['reference_code']) . '.' . $extension;
    }

    /** Writes the PDF and the workbook under the uploads folder, ready to attach. */
    public static function store(int $tripId, bool $commercial): ?array
    {
        $data = self::data($tripId, $commercial);
        if ($data === null) {
            return null;
        }
        $directory = Mailer::uploadsRoot() . '/trips/reports';
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return null;
        }
        $suffix = $commercial ? '' : '-driver';
        $pdf = $directory . '/' . str_replace('.pdf', $suffix . '.pdf', self::filename($data, 'pdf'));
        $xlsx = $directory . '/' . str_replace('.xlsx', $suffix . '.xlsx', self::filename($data, 'xlsx'));
        if (@file_put_contents($pdf, TripReportPdf::build($data)) === false) {
            return null;
        }
        $files = [['filename' => self::filename($data, 'pdf'), 'path' => $pdf]];
        $sheet = TripReportSheet::build($data);
        if ($sheet !== null && @file_put_contents($xlsx, $sheet) !== false) {
            $files[] = ['filename' => self::filename($data, 'xlsx'), 'path' => $xlsx];
        }

        return $files;
    }

    /**
     * At dispatch: the full report to the people who run the trip, the PDF and
     * the workbook attached. The driver's copy goes with his trip sheet.
     *
     * @return int how many people it was sent to
     */
    /** "Yes" or "2 h 15 min late", against the planned arrival and the grace the company allows. */
    private static function punctuality(array $trip): string
    {
        if (empty($trip['arrival_at']) || empty($trip['planned_arrival_at'])) {
            return '';
        }
        $late = (int) round((strtotime((string) $trip['arrival_at']) - strtotime((string) $trip['planned_arrival_at'])) / 60);
        if ($late <= Settings::int('on_time_grace_minutes', 30)) {
            return $late < 0 ? sprintf('Yes, %s early', self::span(-$late)) : 'Yes';
        }

        return 'No, ' . self::span($late) . ' late';
    }

    private static function span(int $minutes): string
    {
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;

        return trim(($days > 0 ? $days . ' d ' : '') . ($hours > 0 ? $hours . ' h ' : '') . ($days === 0 && $mins > 0 ? $mins . ' min' : ''));
    }

    public static function sendToOffice(int $tripId, string $moment = 'dispatched'): int
    {
        // Prices go to those who deal with them; the fleet manager gets the
        // trip without them, like the driver.
        $copies = ['office' => self::store($tripId, true), 'plain' => self::store($tripId, false)];
        $data = self::data($tripId, true);
        if ($copies['office'] === null || $data === null) {
            return 0;
        }
        $trip = $data['trip'];

        $people = self::db()->query(
            "SELECT u.id, u.full_name, u.email, r.role_key FROM users u INNER JOIN roles r ON r.id = u.role_id
              WHERE u.deleted_at IS NULL AND u.status = 'active' AND u.notify_by_email = 1 AND u.email <> ''
                AND r.role_key IN ('super_admin', 'logistics_manager', 'management', 'finance', 'fleet_manager')"
        )->fetchAll(PDO::FETCH_ASSOC);

        $sent = 0;
        foreach ($people as $person) {
            $files = $person['role_key'] === 'fleet_manager' && $copies['plain'] !== null ? $copies['plain'] : $copies['office'];
            $result = Mailer::send([
                'key' => 'trip-report-' . $moment . '-' . $tripId . '-' . $person['id'] . '-' . date('YmdHi'),
                'category' => 'notification',
                'to' => (string) $person['email'],
                'to_name' => (string) $person['full_name'],
                'user_id' => (int) $person['id'],
                'subject' => sprintf('Trip %s %s — %s to %s', $trip['reference_code'], $moment, $trip['pickup_location'], $trip['destination']),
                'heading' => ($moment === 'completed' ? 'Trip completed: ' : 'Trip dispatched: ') . $trip['reference_code'],
                'lines' => [
                    sprintf('Dear %s,', $person['full_name']),
                    $moment === 'completed'
                        ? sprintf('%s has reached %s and is complete. This is the final report: everything that happened on the trip, from dispatch to delivery.', $trip['reference_code'], $trip['destination'])
                        : sprintf('%s has left %s for %s with %s, driven by %s.', $trip['reference_code'], $trip['pickup_location'], $trip['destination'], $trip['plate_number'] ?: 'the truck', $trip['driver_name'] ?: 'the driver'),
                    'The full trip report is attached as a PDF and an Excel workbook: the loads, the route and stops, every paper and the final check'
                        . ($files === $copies['office'] ? ', and the money.' : '.'),
                ],
                'facts' => array_filter([
                    'Loads' => (string) $data['totals']['loads'],
                    'Weight on board' => number_format((float) $data['totals']['weight']) . ' kg' . ((float) $trip['capacity_kg'] > 0 ? ' of ' . number_format((float) $trip['capacity_kg']) . ' kg' : ''),
                    // Once it is over, what happened, not what was planned.
                    'Expected at destination' => $moment !== 'completed' && $trip['planned_arrival_at'] ? \human_date((string) $trip['planned_arrival_at'], true) : '',
                    'Arrived' => $moment === 'completed' && !empty($trip['arrival_at']) ? \human_date((string) $trip['arrival_at'], true) : '',
                    'Planned arrival' => $moment === 'completed' && $trip['planned_arrival_at'] ? \human_date((string) $trip['planned_arrival_at'], true) : '',
                    'On time' => $moment === 'completed' ? self::punctuality($trip) : '',
                    'Distance' => $moment === 'completed' && !empty($trip['end_mileage']) && $trip['start_mileage'] !== null
                        ? number_format(max(0, (int) $trip['end_mileage'] - (int) $trip['start_mileage'])) . ' km' : '',
                ]),
                'action' => ['label' => 'Open the trip', 'path' => 'trips/' . $tripId],
                'attachments' => $files,
                'entity_type' => 'trips',
                'entity_id' => (string) $trip['reference_code'],
            ]);
            $sent += $result['queued'] ? 1 : 0;
        }

        return $sent;
    }
}
