<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * The top of each department's dashboard: the work waiting for it, as short
 * lists with a link on every line, rather than more numbers.
 *
 * Each panel is ['key', 'title', 'icon', 'count', 'items' => [['title', 'sub', 'url', 'badge', 'tone']], 'empty', 'more'].
 * Money in a panel is always shown per currency.
 */
final class Workboard
{
    /** @return list<array<string, mixed>> */
    public static function forRole(string $role, ?int $userId): array
    {
        $panels = match ($role) {
            'super_admin' => ['on_road', 'papers_expiring'],
            'management' => ['month', 'approvals', 'on_road', 'help', 'receivables'],
            'logistics_manager' => ['approvals', 'not_ready', 'on_road', 'help', 'quiet', 'papers_expiring', 'arrivals'],
            'clearance_officer' => ['approvals', 'arrivals', 'papers', 'clearance_blocked', 'at_border'],
            'finance' => ['approvals', 'receivables', 'overdue', 'unbilled', 'bills_due', 'duties'],
            default => [],
        };

        $out = [];
        foreach ($panels as $panel) {
            try {
                $built = self::{$panel}($role);
                if ($built !== null) {
                    $out[] = $built + ['key' => $panel, 'more' => null, 'empty' => 'Nothing here right now.'];
                }
            } catch (\Throwable $exception) {
                error_log(sprintf('Workboard %s: %s', $panel, $exception->getMessage()));
            }
        }

        return $out;
    }

    // --------------------------------------------------------------- panels

    /** Requests, journals and older approvals waiting for this person's signature. */
    private static function approvals(string $role): ?array
    {
        $items = [];
        foreach (RequestForms::inbox(50) as $r) {
            $items[] = [
                'title' => RequestForms::label($r) . ' · ' . $r['title'],
                'sub' => sprintf('%s%s · waiting %s', RequestForms::STEP_LABELS[$r['step']] ?? $r['step'], (int) $r['is_money'] === 1 ? ' · ' . Currency::format((float) $r['total_amount'], (string) $r['currency']) : '', \time_ago((string) ($r['waiting_since'] ?? ''))),
                'url' => $r['step'] === 'pay' ? \url('payment_batches') : \url(['form_requests', (int) $r['id']]),
                'badge' => null,
                'tone' => 'warning',
            ];
        }
        foreach (ManualJournals::waiting() as $journal) {
            if (ManualJournals::stepFor($journal) === null) {
                continue;
            }
            $items[] = [
                'title' => $journal['draft_no'] . ' · ' . $journal['memo'],
                'sub' => sprintf('Journal to %s · %s', $journal['status'] === 'sent' ? 'verify' : 'authorize', Currency::format((float) $journal['total'], (string) $journal['currency'])),
                'url' => \url(['journal', 'drafts', (int) $journal['id']]),
                'badge' => null,
                'tone' => 'warning',
            ];
        }
        foreach (Approvals::inbox($role, 50) as $s) {
            $items[] = [
                'title' => $s['module_title'] . ' ' . $s['record_code'],
                'sub' => sprintf('Step %d of %d: %s%s · waiting %s', $s['position'], $s['total'], $s['step_label'], $s['money'] !== '' ? ' · ' . $s['money'] : '', \time_ago((string) $s['activated_at'])),
                'url' => $s['url'],
                'badge' => !empty($s['overdue']) ? 'Late' : null,
                'tone' => !empty($s['overdue']) ? 'danger' : 'warning',
            ];
        }

        return [
            'title' => 'Waiting for your signature', 'icon' => 'check-square', 'count' => count($items),
            'items' => array_slice($items, 0, 6),
            'empty' => 'Nothing waiting for you.',
            'more' => ['label' => 'Open approvals', 'url' => \url('approvals')],
        ];
    }

    /** Trips planned and not yet gone: their money in approval, or ready to depart. */
    private static function not_ready(string $role): array
    {
        $rows = self::rows("SELECT t.id, t.reference_code, t.pickup_location, t.destination, t.planned_departure_at, t.status, v.plate_number, d.full_name AS driver
                              FROM trips t LEFT JOIN vehicles v ON v.id = t.vehicle_id LEFT JOIN drivers d ON d.id = t.driver_id
                             WHERE t.deleted_at IS NULL AND t.status IN ('planned','ready')" . self::co('trips', 't.company_id') . '
                             ORDER BY t.status = \'ready\', t.planned_departure_at IS NULL, t.planned_departure_at, t.id LIMIT 8');

        return ['title' => 'Trips about to leave', 'icon' => 'clipboard', 'count' => count(array_filter($rows, static fn (array $r): bool => $r['status'] === 'planned')),
            'items' => array_map(static fn (array $r): array => [
                'title' => $r['reference_code'] . ' · ' . ($r['plate_number'] ?? '') . ' · ' . ($r['driver'] ?? ''),
                'sub' => $r['pickup_location'] . ' → ' . $r['destination'] . ($r['planned_departure_at'] ? ' · planned ' . \human_date((string) $r['planned_departure_at'], true) : ''),
                'url' => \url(['trips', (int) $r['id']]),
                'badge' => $r['status'] === 'ready' ? 'Ready' : 'Money in approval',
                'tone' => $r['status'] === 'ready' ? 'success' : 'warning',
            ], $rows),
            'empty' => 'No trip is planned.', 'more' => \can_view('trip_planner') ? ['label' => 'Trip planner', 'url' => \url('trip_planner')] : null];
    }

    private static function on_road(string $role): array
    {
        $rows = self::rows("SELECT t.id, t.reference_code, t.pickup_location, t.destination, t.status, t.return_due_at, v.plate_number, d.full_name AS driver,
                                   (SELECT MAX(p.recorded_at) FROM vehicle_positions p WHERE p.trip_id = t.id) AS last_seen
                              FROM trips t LEFT JOIN vehicles v ON v.id = t.vehicle_id LEFT JOIN drivers d ON d.id = t.driver_id
                             WHERE t.deleted_at IS NULL AND t.status IN ('on_road','at_turnaround','loading','offloading','returning')" . self::co('trips', 't.company_id') . '
                             ORDER BY t.return_due_at IS NULL, t.return_due_at LIMIT 8');

        return ['title' => 'On the road', 'icon' => 'navigation', 'count' => count($rows), 'items' => array_map(static function (array $r): array {
            $late = $r['return_due_at'] !== null && strtotime((string) $r['return_due_at']) < time();

            return [
                'title' => $r['reference_code'] . ' · ' . ($r['plate_number'] ?? '') . ' · ' . ($r['driver'] ?? ''),
                'sub' => (TripLife::STATUS[$r['status']] ?? $r['status']) . ' · ' . $r['pickup_location'] . ' → ' . $r['destination'] . ($r['last_seen'] ? ' · GPS ' . \time_ago((string) $r['last_seen']) : '') . ($r['return_due_at'] ? ' · back by ' . \human_date((string) $r['return_due_at'], true) : ''),
                'url' => \url(['trips', (int) $r['id']]),
                'badge' => $late ? 'Late' : null,
                'tone' => $late ? 'danger' : 'primary',
            ];
        }, $rows), 'empty' => 'No truck is on the road.', 'more' => \can_view('tracking') ? ['label' => 'Live map', 'url' => \url('tracking')] : null];
    }

    private static function at_border(string $role): array
    {
        $rows = BorderCrossing::atTheBorder();

        return ['title' => 'At a border now', 'icon' => 'flag', 'count' => count($rows), 'items' => array_map(static fn (array $r): array => [
            'title' => $r['post_name'] . ' · ' . ($r['plate_number'] ?? '') . ' · ' . $r['reference'],
            'sub' => sprintf('%s · waiting %s h%s', Schema::label((string) $r['status']), number_format((float) $r['hours_waiting'], 1), $r['hold_reason'] ? ' · held: ' . $r['hold_reason'] : ''),
            'url' => \url('crossings', ['q' => $r['reference']]),
            'badge' => (float) $r['hours_waiting'] > 24 ? 'Over a day' : null,
            'tone' => $r['hold_reason'] ? 'danger' : 'warning',
        ], array_slice($rows, 0, 6)), 'empty' => 'No truck is waiting at a border.', 'more' => ['label' => 'Clearances', 'url' => \url('crossings')]];
    }

    private static function help(string $role): array
    {
        $rows = self::rows("SELECT sr.id, sr.request_code, sr.title, sr.urgency, sr.created_at, d.full_name AS driver FROM support_requests sr LEFT JOIN drivers d ON d.id = sr.driver_id
                             WHERE sr.status IN ('open','in_progress') AND sr.deleted_at IS NULL" . self::co('support_requests', 'sr.company_id') . "
                             ORDER BY FIELD(sr.urgency, 'emergency', 'urgent', 'normal'), sr.id DESC LIMIT 6");

        return ['title' => 'Drivers asking for help', 'icon' => 'life-buoy', 'count' => count($rows), 'items' => array_map(static fn (array $r): array => [
            'title' => $r['request_code'] . ' · ' . $r['title'],
            'sub' => ($r['driver'] ?? 'Driver') . ' · ' . \time_ago((string) $r['created_at']),
            'url' => \url(['support_requests', (int) $r['id'], 'decide']),
            'badge' => ucfirst((string) $r['urgency']),
            'tone' => in_array($r['urgency'], ['emergency', 'urgent'], true) ? 'danger' : 'warning',
        ], $rows), 'empty' => 'No driver is waiting for help.', 'more' => ['label' => 'Driver support', 'url' => \url('support_requests')]];
    }

    /** Vessels and flights due this week, and cargo waiting at the port. */
    private static function arrivals(string $role): array
    {
        $rows = FreightJobs::arriving();

        return ['title' => 'Arriving and at the port', 'icon' => 'anchor', 'count' => count($rows), 'items' => array_map(static fn (array $r): array => [
            'title' => $r['job_code'] . ' · ' . ($r['customer_name'] ?? '') . ' · ' . $r['commodity'],
            'sub' => trim(($r['container_numbers'] ?: '') . ' · ' . ($r['port_of_discharge'] ?: '') . ($r['eta'] ? ' · ETA ' . \human_date((string) $r['eta']) : ''), ' ·'),
            'url' => \url(['freight_jobs', (int) $r['id']]),
            'badge' => Schema::label((string) $r['status']),
            'tone' => in_array($r['status'], ['arrived', 'customs'], true) ? 'warning' : 'primary',
        ], $rows), 'empty' => 'No vessel or flight due this week.', 'more' => \can_view('freight_jobs') ? ['label' => 'Freight jobs', 'url' => \url('freight_jobs')] : null];
    }

    /** Papers uploaded and waiting for the clearance department to check. */
    private static function papers(string $role): array
    {
        $rows = self::rows("SELECT 'clearance_documents' AS module, d.id, d.document_type, bc.reference AS owner, d.document_file, d.created_at
                              FROM border_documents d INNER JOIN border_crossings bc ON bc.id = d.crossing_id
                             WHERE d.status = 'pending' AND bc.deleted_at IS NULL AND bc.status NOT IN ('released','rejected')
                             UNION ALL
                            SELECT 'shipment_documents', sd.id, sd.document_type, s.shipment_code, sd.document_file, sd.created_at
                              FROM shipment_documents sd INNER JOIN shipments s ON s.id = sd.shipment_id
                             WHERE sd.status = 'pending' AND s.deleted_at IS NULL AND s.status NOT IN ('delivered','cancelled','returned')
                             ORDER BY (document_file IS NULL), created_at LIMIT 10");

        return ['title' => 'Papers to collect or check', 'icon' => 'file-text', 'count' => count($rows), 'items' => array_map(static fn (array $r): array => [
            'title' => $r['document_type'] . ' · ' . $r['owner'],
            'sub' => $r['document_file'] ? 'Scan uploaded: check it and press Verify' : 'Not uploaded yet: get the paper and attach the scan',
            'url' => \url([(string) $r['module'], (int) $r['id']]),
            'badge' => $r['document_file'] ? 'To verify' : 'Missing',
            'tone' => $r['document_file'] ? 'warning' : 'danger',
        ], $rows), 'empty' => 'Every paper is checked.', 'more' => ['label' => 'Clearance documents', 'url' => \url('clearance_documents', ['status' => 'pending'])]];
    }

    private static function clearance_blocked(string $role): array
    {
        $rows = Clearance::blockedTrips(8);

        return ['title' => 'Trucks held in the yard by clearance', 'icon' => 'alert-octagon', 'count' => count($rows), 'items' => array_map(static fn (array $r): array => [
            'title' => $r['reference_code'] . ' · ' . $r['pickup_location'] . ' → ' . $r['destination'],
            'sub' => (string) $r['problem'],
            'url' => \url(['trips', (int) $r['id']]),
            'badge' => $r['planned_departure_at'] ? 'Leaves ' . \human_date((string) $r['planned_departure_at']) : null,
            'tone' => 'danger',
        ], $rows), 'empty' => 'No truck is waiting on its clearance.'];
    }

    /** Owed to us, per currency, never added together. */
    private static function receivables(string $role): array
    {
        $rows = self::rows("SELECT currency, COUNT(*) n, SUM(total_amount - amount_paid) owed,
                                   SUM(CASE WHEN status = 'overdue' THEN total_amount - amount_paid ELSE 0 END) late
                              FROM invoices WHERE deleted_at IS NULL AND status IN ('issued','partially_paid','overdue')" . self::co('invoices') . '
                             GROUP BY currency');
        $base = Currency::base();
        usort($rows, static fn (array $a, array $b): int => [$a['currency'] !== $base, $a['currency']] <=> [$b['currency'] !== $base, $b['currency']]);

        return ['title' => 'Owed to us', 'icon' => 'dollar-sign', 'count' => array_sum(array_map(static fn (array $r): int => (int) $r['n'], $rows)), 'items' => array_map(static fn (array $r): array => [
            'title' => Currency::format((float) $r['owed'], (string) $r['currency']),
            'sub' => sprintf('%d open invoice%s%s', $r['n'], (int) $r['n'] === 1 ? '' : 's', (float) $r['late'] > 0 ? ' · ' . Currency::format((float) $r['late'], (string) $r['currency']) . ' overdue' : ''),
            'url' => \url('invoices', ['currency' => $r['currency']]),
            'badge' => (string) $r['currency'],
            'tone' => (float) $r['late'] > 0 ? 'danger' : 'success',
        ], $rows), 'empty' => 'Nothing is owed.', 'more' => \can_view('books') ? ['label' => 'Accounting books', 'url' => \url('books')] : null];
    }

    private static function overdue(string $role): array
    {
        $rows = self::rows("SELECT i.id, i.invoice_number, i.due_date, i.currency, (i.total_amount - i.amount_paid) AS balance, c.customer_name
                              FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id
                             WHERE i.deleted_at IS NULL AND i.status = 'overdue'" . self::co('invoices', 'i.company_id') . ' ORDER BY i.due_date LIMIT 6');

        return ['title' => 'Overdue invoices', 'icon' => 'alert-triangle', 'count' => count($rows), 'items' => array_map(static fn (array $r): array => [
            'title' => $r['invoice_number'] . ' · ' . ($r['customer_name'] ?? ''),
            'sub' => Currency::format((float) $r['balance'], (string) $r['currency']) . ' · due ' . \human_date((string) $r['due_date']),
            'url' => \url(['invoices', (int) $r['id']]),
            'badge' => abs((int) \days_until((string) $r['due_date'])) . ' days late',
            'tone' => 'danger',
        ], $rows), 'empty' => 'No invoice is overdue.'];
    }

    /** Customs duties the clearance department is waiting on finance to pay. */
    private static function duties(string $role): array
    {
        $rows = self::rows("SELECT bc.id, bc.reference, bc.currency, bp.post_name,
                                   (SELECT COALESCE(SUM(amount), 0) FROM border_charges c WHERE c.crossing_id = bc.id AND c.status = 'pending') AS owed
                              FROM border_crossings bc INNER JOIN border_posts bp ON bp.id = bc.border_post_id
                             WHERE bc.deleted_at IS NULL AND bc.status IN ('duties_pending','payment_pending')" . self::co('border_crossings', 'bc.company_id') . ' ORDER BY bc.id LIMIT 8');

        return ['title' => 'Duties to pay', 'icon' => 'credit-card', 'count' => count($rows), 'items' => array_map(static fn (array $r): array => [
            'title' => $r['reference'] . ' · ' . $r['post_name'],
            'sub' => (float) $r['owed'] > 0 ? Currency::format((float) $r['owed'], (string) ($r['currency'] ?: Currency::base())) . ' to pay' : 'Charges not entered yet',
            'url' => \url(['crossings', (int) $r['id']]),
            'badge' => 'Customs',
            'tone' => 'warning',
        ], $rows), 'empty' => 'No duty is waiting to be paid.'];
    }

    /** Closed trips with a customer and no invoice yet. */
    private static function unbilled(string $role): array
    {
        $rows = self::rows("SELECT t.id, t.reference_code, t.destination, t.closed_at, c.customer_name FROM trips t INNER JOIN customers c ON c.id = t.customer_id
                             WHERE t.deleted_at IS NULL AND t.status IN ('closed','arrived','delivered')" . self::co('trips', 't.company_id') . "
                               AND NOT EXISTS (SELECT 1 FROM invoices i WHERE i.trip_id = t.id AND i.deleted_at IS NULL AND i.status <> 'cancelled')
                               AND NOT EXISTS (SELECT 1 FROM form_requests r WHERE r.trip_id = t.id AND r.form_code = 'F13' AND r.deleted_at IS NULL AND r.status <> 'cancelled')
                             ORDER BY t.closed_at DESC LIMIT 6");

        return ['title' => 'Delivered, not invoiced', 'icon' => 'file-plus', 'count' => count($rows), 'items' => array_map(static fn (array $r): array => [
            'title' => $r['reference_code'] . ' · ' . ($r['customer_name'] ?? ''),
            'sub' => 'Delivered to ' . $r['destination'] . ($r['closed_at'] ? ' · closed ' . \time_ago((string) $r['closed_at']) : ''),
            'url' => \url(['trips', (int) $r['id']]),
            'badge' => 'Invoice it',
            'tone' => 'warning',
        ], $rows), 'empty' => 'Every delivered trip is invoiced.'];
    }

    private static function bills_due(string $role): array
    {
        $rows = self::rows("SELECT b.id, b.bill_no, b.due_date, b.currency, (b.total_amount - b.amount_paid) AS balance, s.supplier_name
                              FROM supplier_bills b LEFT JOIN suppliers s ON s.id = b.supplier_id
                             WHERE b.deleted_at IS NULL AND b.status IN ('approved','partially_paid') AND b.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)" . self::co('supplier_bills', 'b.company_id') . '
                             ORDER BY b.due_date LIMIT 6');

        return ['title' => 'Supplier bills due this week', 'icon' => 'calendar', 'count' => count($rows), 'items' => array_map(static fn (array $r): array => [
            'title' => $r['bill_no'] . ' · ' . ($r['supplier_name'] ?? ''),
            'sub' => Currency::format((float) $r['balance'], (string) $r['currency']) . ' · due ' . \human_date((string) $r['due_date']),
            'url' => \url(['supplier_bills', (int) $r['id']]),
            'badge' => (string) $r['due_date'] < date('Y-m-d') ? 'Late' : null,
            'tone' => (string) $r['due_date'] < date('Y-m-d') ? 'danger' : 'warning',
        ], $rows), 'empty' => 'No supplier bill is due this week.'];
    }

    /** This month in money, per currency, and trips back on time. */
    private static function month(string $role): array
    {
        $start = date('Y-m-01');
        $billed = ChartData::moneyByCurrency("SELECT currency, SUM(total_amount) total FROM invoices WHERE deleted_at IS NULL AND is_opening = 0 AND status NOT IN ('draft','cancelled') AND issue_date >= ?" . self::co('invoices') . ' GROUP BY currency', [$start]);
        $collected = ChartData::moneyByCurrency('SELECT i.currency, SUM(p.amount) total FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE p.deleted_at IS NULL AND p.paid_at >= ?' . self::co('invoices', 'i.company_id') . ' GROUP BY i.currency', [$start]);
        $spent = ChartData::moneyByCurrency("SELECT r.currency, SUM(r.total_amount) total FROM form_requests r INNER JOIN form_types t ON t.form_code = r.form_code WHERE r.deleted_at IS NULL AND t.is_money = 1 AND r.form_code <> 'F13' AND r.status IN ('verified','authorized','partly_paid','paid','closed') AND r.verified_at >= ?" . self::co('form_requests', 'r.company_id') . ' GROUP BY r.currency', [$start]);
        $done = self::rows('SELECT COUNT(*) n, SUM(return_due_at IS NULL OR arrival_at IS NULL OR arrival_at <= return_due_at) on_time FROM trips WHERE deleted_at IS NULL AND closed_at >= ?' . self::co('trips'), [$start])[0] ?? ['n' => 0, 'on_time' => 0];
        $onTime = (int) $done['n'] > 0 ? (int) round((int) $done['on_time'] / (int) $done['n'] * 100) . '%' : 'n/a';

        return ['title' => 'This month', 'icon' => 'bar-chart-2', 'count' => null, 'items' => [
            ['title' => $billed, 'sub' => 'Invoiced', 'url' => \can_view('invoices') ? \url('invoices') : null, 'badge' => null, 'tone' => 'primary'],
            ['title' => $collected, 'sub' => 'Collected', 'url' => \can_view('payments') ? \url('payments') : null, 'badge' => null, 'tone' => 'success'],
            ['title' => $spent, 'sub' => 'Spent (verified requests)', 'url' => \can_view('form_requests') ? \url('form_requests') : null, 'badge' => null, 'tone' => 'warning'],
            ['title' => $onTime, 'sub' => sprintf('Back on time (%d trips closed)', (int) $done['n']), 'url' => null, 'badge' => null, 'tone' => 'primary'],
        ]];
    }

    /** Trucks on a trip whose GPS has gone silent. */
    private static function quiet(string $role): array
    {
        $hours = max(1, Settings::int('gps_silence_hours', 3));
        $moving = ['on_road', 'at_turnaround', 'loading', 'offloading', 'returning'];
        $rows = array_values(array_filter(Tracking::fleet(), static fn (array $r): bool => in_array($r['trip_status'], $moving, true) && (int) $r['minutes_ago'] > $hours * 60));

        return ['title' => 'Trucks gone quiet', 'icon' => 'wifi-off', 'count' => count($rows), 'items' => array_map(static fn (array $r): array => [
            'title' => $r['plate_number'] . ' · ' . ($r['trip'] ?? ''),
            'sub' => 'Last position ' . \time_ago((string) $r['recorded_at']) . ($r['driver'] ? ' · ' . $r['driver'] : ''),
            'url' => \url('tracking'),
            'badge' => null,
            'tone' => 'danger',
        ], $rows), 'empty' => 'Every truck on the road is reporting.'];
    }

    private static function papers_expiring(string $role): array
    {
        $days = max(Fleet::alertDays() ?: [30]);
        $rows = self::rows("SELECT vd.id, vd.document_type, vd.expires_on, COALESCE(v.plate_number, tr.trailer_number) AS plate_number
                              FROM vehicle_documents vd LEFT JOIN vehicles v ON v.id = vd.vehicle_id LEFT JOIN trailers tr ON tr.id = vd.trailer_id
                             WHERE vd.deleted_at IS NULL AND vd.status <> 'cancelled' AND vd.expires_on <= DATE_ADD(CURDATE(), INTERVAL ? DAY)" . self::co('vehicle_documents', 'vd.company_id') . '
                             ORDER BY vd.expires_on LIMIT 6', [$days]);

        return ['title' => 'Truck papers running out', 'icon' => 'shield', 'count' => count($rows), 'items' => array_map(static fn (array $r): array => [
            'title' => ($r['plate_number'] ?? '') . ' · ' . Schema::label((string) $r['document_type']),
            'sub' => 'Expires ' . \human_date((string) $r['expires_on']),
            'url' => \url(['vehicle_documents', (int) $r['id']]),
            'badge' => (string) $r['expires_on'] < date('Y-m-d') ? 'Expired' : null,
            'tone' => (string) $r['expires_on'] < date('Y-m-d') ? 'danger' : 'warning',
        ], $rows), 'empty' => 'Every truck paper is in date.'];
    }

    // -------------------------------------------------------------- helpers

    /** " AND t.company_id = 2" for the company in view; nothing for the whole group. */
    private static function co(string $table, string $column = 'company_id'): string
    {
        $condition = Company::condition($table, $column);

        return $condition !== null ? ' AND ' . $condition : '';
    }

    /** @return list<array<string, mixed>> */
    private static function rows(string $sql, array $params = []): array
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
