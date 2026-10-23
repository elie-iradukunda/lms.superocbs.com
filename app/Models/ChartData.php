<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Dashboard charts and attention items (ERI specification, section 20), all
 * read from the database for the company in view — or the whole group for
 * someone who sees every company and chose "All companies".
 *
 * The to-do counts of each role are the home cards (Models\RoleHome); this
 * class draws the trends behind them.
 *
 * A chart is a plain array the browser renders (see public/assets/js/lms-charts.js):
 * id, title, subtitle, type (donut|column|bar|area|line|stacked|meter|notice), span (grid columns of 12),
 * format (int|rwf|litres|pct), categories, series [{name, data}], slots (categorical colour slot per
 * series - or per category for donuts - so colour follows the entity, never its rank).
 * Format "rwf" means money: it is drawn in the currency chosen above the charts.
 */
final class ChartData
{
    private const STOCK_STATUSES = ['in_stock' => 'In stock', 'reorder' => 'Reorder', 'out_of_stock' => 'Out of stock'];
    private const REQUEST_STEPS = ['draft' => 'Draft', 'sent' => 'To verify', 'verified' => 'To authorize', 'authorized' => 'To pay', 'partly_paid' => 'Partly paid', 'returned' => 'Returned'];
    private const JOB_STATUSES = ['open' => 'Open', 'scheduled' => 'Scheduled', 'in_progress' => 'In progress', 'completed' => 'Completed'];

    /** The currency the money charts are drawn in. Money is never added across currencies. */
    private static string $currency = '';

    public static function forRole(string $role, ?int $userId, ?string $currency = null): array
    {
        self::$currency = self::currencyCode($currency);
        $charts = match ($role) {
            'super_admin' => [self::fleetStatus(4), self::tripsByStatus(8), self::requestPipeline(6), self::auditActivity(6)],
            // The MD's dashboard is lean (section 20): utilization, trips, money.
            'management' => [self::fleetUtilization(4), self::tripsTrend(8), self::incomeAndCost(8), self::requestPipeline(4)],
            'logistics_manager' => [self::fleetStatus(4), self::tripsWeekly(8, 'Trips per week', 'Trips departing each week, last 8 weeks'), self::tripsByStatus(6), self::driverStatus(3), self::licenceExpiry(3), self::fuelTrend(6)],
            'finance' => [self::spendByForm(4), self::spendTrend(8), self::incomeAndCost(6), self::paidTrend(6), self::billedAndCollected(12)],
            'cashier' => [self::paidTrend(8), self::paymentMethods(4)],
            'warehouse_manager' => [self::stockLevels(8), self::stockStatus(4), self::jobCards(6), self::stockValue(6), self::maintenanceCost(12)],
            'clearance_officer' => [self::clearanceStages(6), self::borderDwell(6)],
            'driver' => self::driverCharts($userId),
            default => [],
        };

        $charts = array_values(array_filter($charts, static fn (?array $chart): bool => $chart !== null));
        foreach ($charts as &$chart) {
            if (($chart['format'] ?? '') === 'rwf') {
                $chart['currency'] = self::$currency;
            }
        }
        unset($chart);

        return $charts;
    }

    /** The currency asked for, when it is a real one; the company's base currency otherwise. */
    public static function currencyCode(?string $code): string
    {
        $code = strtoupper(trim((string) $code));
        if (preg_match('/^[A-Z]{3}$/', $code) === 1) {
            return $code;
        }
        $company = \is_logged_in() ? \current_company_id() : 0;

        return $company > 0 ? Company::baseCurrency($company) : Currency::base();
    }

    /** " AND currency = 'USD'": the code is checked to be three letters before it is written in. */
    private static function cur(string $column = 'currency'): string
    {
        return sprintf(" AND %s = '%s'", $column, self::currencyCode(self::$currency !== '' ? self::$currency : null));
    }

    /** " AND t.company_id = 2" for the company in view; nothing when looking at the whole group. */
    private static function co(string $table, string $column = 'company_id'): string
    {
        $condition = Company::condition($table, $column);

        return $condition !== null ? ' AND ' . $condition : '';
    }

    /**
     * "RWF 1.2M · USD 3.4K": a total per currency, base currency first, never added together.
     *
     * @param string $sql returns rows of (currency, total)
     */
    public static function moneyByCurrency(string $sql, array $params = []): string
    {
        $base = Currency::base();
        $totals = [];
        foreach (self::rows($sql, $params) as $row) {
            $code = strtoupper((string) ($row['currency'] ?? '')) ?: $base;
            $totals[$code] = ($totals[$code] ?? 0) + (float) ($row['total'] ?? 0);
        }
        $totals = array_filter($totals, static fn (float $v): bool => abs($v) >= 0.005);
        if ($totals === []) {
            return $base . ' 0';
        }
        uksort($totals, static fn (string $a, string $b): int => [$a !== $base, $a] <=> [$b !== $base, $b]);

        return implode(' · ', array_map(static fn (string $code, float $amount): string => self::compact($amount, $code), array_keys($totals), $totals));
    }

    private static function compact(float $amount, string $code): string
    {
        return $code . ' ' . match (true) {
            abs($amount) >= 1_000_000 => rtrim(rtrim(number_format($amount / 1_000_000, 1), '0'), '.') . 'M',
            abs($amount) >= 1_000 => rtrim(rtrim(number_format($amount / 1_000, 1), '0'), '.') . 'K',
            default => number_format($amount),
        };
    }

    /**
     * The KPI row. Every ERI role has its home cards instead; only the
     * clearance desk keeps its four figures here.
     *
     * @return list<array{label: string, value: string, trend: string, icon: string}>
     */
    public static function metrics(string $role, ?int $userId): array
    {
        if ($role !== 'clearance_officer') {
            return [];
        }
        $card = static fn (string $label, string|int $value, string $caption, string $icon): array => ['label' => $label, 'value' => (string) $value, 'trend' => $caption, 'icon' => $icon];
        $co = self::co('border_crossings');

        return [
            $card('Open clearance files', (int) self::scalar("SELECT COUNT(*) FROM border_crossings WHERE deleted_at IS NULL AND status NOT IN ('released','rejected')" . $co), self::scalar("SELECT COUNT(*) FROM border_crossings WHERE deleted_at IS NULL AND status IN ('draft','documents_pending')" . $co) . ' still collecting papers', 'flag'),
            $card('Papers to check', (int) self::scalar("SELECT COUNT(*) FROM border_documents d INNER JOIN border_crossings c ON c.id = d.crossing_id WHERE d.status = 'pending' AND d.document_file IS NOT NULL AND c.deleted_at IS NULL" . self::co('border_crossings', 'c.company_id')), 'uploaded, waiting for Verify', 'file-text'),
            $card('At a border now', count(BorderCrossing::atTheBorder()), 'trucks waiting at a post', 'clock'),
            $card('Released this week', (int) self::scalar('SELECT COUNT(*) FROM border_crossings WHERE deleted_at IS NULL AND released_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)' . $co), 'files closed', 'check-circle'),
        ];
    }

    /** @return list<array{tone: string, title: string, text: string}> */
    public static function attention(string $role, ?int $userId): array
    {
        if ($role === 'driver') {
            return self::driverAttention($userId);
        }

        $plural = static fn (int $n, string $one, string $many): string => $n . ' ' . ($n === 1 ? $one : $many);
        $items = [
            'service' => static function () use ($plural): array {
                $n = self::serviceDueCount();
                return ['Service due', $n, $plural($n, 'truck is', 'trucks are') . ' due for service or within the warning distance.'];
            },
            'documents' => static function () use ($plural): array {
                $n = (int) self::scalar("SELECT COUNT(*) FROM vehicle_documents WHERE deleted_at IS NULL AND status <> 'cancelled' AND expires_on <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)" . self::co('vehicle_documents'))
                    + (int) self::scalar("SELECT COUNT(*) FROM driver_documents WHERE deleted_at IS NULL AND status <> 'rejected' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)" . self::co('driver_documents'));
                return ['Documents expiring', $n, $plural($n, 'truck or driver document expires', 'truck and driver documents expire') . ' within 30 days, or already have.'];
            },
            'late' => static function () use ($plural): array {
                $n = (int) self::scalar("SELECT COUNT(*) FROM trips WHERE deleted_at IS NULL AND return_due_at < NOW() AND status IN ('at_turnaround','loading','on_road','offloading','returning')" . self::co('trips'));
                return ['Trips past their time', $n, $plural($n, 'trip is', 'trips are') . ' past the expected return.'];
            },
            'signatures' => static function () use ($plural): array {
                $n = RequestForms::inboxCount() + ManualJournals::inboxCount();
                return ['Waiting for your signature', $n, $plural($n, 'request or journal waits', 'requests and journals wait') . ' for you on Approvals.'];
            },
            'fuel' => static function () use ($plural): array {
                $n = (int) self::scalar('SELECT COUNT(*) FROM trips WHERE deleted_at IS NULL AND fuel_flag IS NOT NULL AND closed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)' . self::co('trips'));
                return ['Fuel checks flagged', $n, $plural($n, 'closed trip used', 'closed trips used') . ' more or less fuel than normal in the last 30 days.'];
            },
            'stock' => static function () use ($plural): array {
                $n = (int) self::scalar('SELECT COUNT(*) FROM inventory_items WHERE deleted_at IS NULL AND quantity <= minimum_level' . self::co('inventory_items'));
                return ['Stock below minimum', $n, $plural($n, 'item is', 'items are') . ' at or below the minimum level.'];
            },
            'job_cards' => static function () use ($plural): array {
                $n = (int) self::scalar("SELECT COUNT(*) FROM maintenance_orders WHERE deleted_at IS NULL AND status IN ('open','scheduled','in_progress')" . self::co('maintenance_orders'));
                return ['Open job cards', $n, $plural($n, 'job card is', 'job cards are') . ' open or in progress.'];
            },
            'licences' => static function () use ($plural): array {
                $n = (int) self::scalar("SELECT COUNT(*) FROM drivers WHERE deleted_at IS NULL AND status <> 'left' AND license_expiry IS NOT NULL AND license_expiry <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)" . self::co('drivers'));
                return ['Driving licences expiring', $n, $plural($n, 'driving licence expires', 'driving licences expire') . ' within 90 days.'];
            },
        ];

        $keys = match ($role) {
            'management' => ['signatures', 'late', 'fuel'],
            'logistics_manager' => ['late', 'documents', 'licences', 'service', 'fuel'],
            'finance', 'cashier' => ['signatures', 'fuel'],
            'warehouse_manager' => ['stock', 'job_cards', 'service'],
            'super_admin' => ['documents', 'late'],
            default => [],
        };

        return array_map(static function (string $key) use ($items): array {
            [$title, $count, $text] = $items[$key]();
            return ['tone' => $count > 0 ? 'warning' : 'success', 'title' => $title, 'text' => $count > 0 ? $text : 'Nothing needs attention right now.'];
        }, $keys);
    }

    /** @return list<array{reference: string, route: string, vehicle: string, driver: string, status: string}> */
    public static function recentTrips(?int $userId, string $role, int $limit = 6): array
    {
        $sql = 'SELECT t.reference_code, t.pickup_location, t.destination, COALESCE(v.plate_number, "") vehicle, COALESCE(d.full_name, "") driver, t.status
                FROM trips t
                LEFT JOIN vehicles v ON v.id = t.vehicle_id
                LEFT JOIN drivers d ON d.id = t.driver_id
                WHERE t.deleted_at IS NULL' . self::co('trips', 't.company_id');
        $params = [];
        if ($role === 'driver') {
            $sql .= ' AND d.user_id = ?';
            $params[] = $userId ?? 0;
        }
        $sql .= ' ORDER BY COALESCE(t.departure_at, t.planned_departure_at, t.created_at) DESC, t.id DESC LIMIT ' . (int) $limit;

        return array_map(static fn (array $r): array => [
            'reference' => (string) $r['reference_code'],
            'route' => trim($r['pickup_location'] . ' - ' . $r['destination'], ' -'),
            'vehicle' => $r['vehicle'],
            'driver' => $r['driver'],
            'status' => TripLife::STATUS[$r['status']] ?? self::label((string) $r['status']),
        ], self::rows($sql, $params));
    }

    // ---- charts ---------------------------------------------------------------------------------

    private static function fleetStatus(int $span): array
    {
        return self::donut('fleet-status', 'Fleet status', 'Trucks by current status', $span, Fleet::VEHICLE_STATUS, self::countBy('vehicles', 'status'), 'trucks');
    }

    private static function tripsByStatus(int $span): array
    {
        $statuses = array_slice(TripLife::STATUS, 0, 10, true);

        return self::bars('trips-status', 'Trips by status', 'Every trip on record', $span, 'bar', $statuses, self::countBy('trips', 'status'), 'Trips');
    }

    private static function driverStatus(int $span): array
    {
        return self::donut('driver-status', 'Drivers', 'Drivers by status', $span, Fleet::DRIVER_STATUS, self::countBy('drivers', 'status'), 'drivers');
    }

    private static function requestPipeline(int $span): array
    {
        $counts = self::keyed2(self::rows('SELECT status k, COUNT(*) v FROM form_requests WHERE deleted_at IS NULL' . self::co('form_requests') . ' GROUP BY status'));

        return self::bars('request-pipeline', 'Requests in the chain', 'Prepared → verified → authorized → paid', $span, 'bar', self::REQUEST_STEPS, $counts, 'Requests');
    }

    private static function auditActivity(int $span): array
    {
        $rows = self::rows("SELECT entity_type, COUNT(*) n FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND entity_type <> 'database'" . self::co('audit_logs') . ' GROUP BY entity_type ORDER BY n DESC, entity_type LIMIT 6');
        $chart = self::baseChart('audit-activity', 'System activity', 'Recorded actions by area, last 30 days', $span, 'bar', 'int');
        $chart['categories'] = array_map(static fn (array $r): string => self::label((string) $r['entity_type']), $rows);
        $chart['series'] = [['name' => 'Actions', 'data' => array_map(static fn (array $r): int => (int) $r['n'], $rows)]];
        $chart['slots'] = [0];

        return $chart;
    }

    private static function tripsWeekly(int $span, string $title, string $subtitle, ?int $driverId = null): array
    {
        $weeks = self::weeks();
        $where = "status <> 'cancelled'" . ($driverId !== null ? ' AND driver_id = ' . (int) $driverId : self::co('trips'));
        $data = self::keyed(self::rows("SELECT DATE_FORMAT(DATE_SUB(DATE(departure_at), INTERVAL WEEKDAY(departure_at) DAY), '%Y-%m-%d') k, COUNT(*) v FROM trips WHERE deleted_at IS NULL AND departure_at IS NOT NULL AND {$where} GROUP BY k"), array_keys($weeks));

        return self::timeChart($driverId !== null ? 'my-trips-weekly' : 'trips-weekly', $title, $subtitle, $span, 'column', 'int', $weeks, [['Trips', $data]]);
    }

    private static function tripsTrend(int $span): array
    {
        $months = self::months();
        $departed = self::monthly("SELECT DATE_FORMAT(departure_at, '%Y-%m') m, COUNT(*) v FROM trips WHERE deleted_at IS NULL AND status <> 'cancelled' AND departure_at IS NOT NULL" . self::co('trips') . ' GROUP BY m', $months);
        $closed = self::monthly("SELECT DATE_FORMAT(closed_at, '%Y-%m') m, COUNT(*) v FROM trips WHERE deleted_at IS NULL AND closed_at IS NOT NULL" . self::co('trips') . ' GROUP BY m', $months);

        return self::timeChart('trips-trend', 'Trips per month', 'Departed and closed, last 6 months', $span, 'line', 'int', $months, [['Departed', $departed], ['Closed', $closed]]);
    }

    private static function fleetUtilization(int $span): array
    {
        $co = self::co('vehicles');
        $total = (int) self::scalar("SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL AND status <> 'inactive'" . $co);
        $onTrip = (int) self::scalar("SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL AND status IN ('" . implode("','", Fleet::ON_TRIP) . "')" . $co);

        return self::meter('fleet-utilization', 'Fleet utilization', 'Share of trucks on a trip now', $span, $total === 0 ? 0.0 : $onTrip / $total * 100, 100, 'pct', 'accent', "{$onTrip} of {$total} trucks on a trip");
    }

    /** Money verified into the books, by form (F1 trip money, F2 fuel, F3 service...). */
    private static function spendByForm(int $span): array
    {
        $rows = self::rows(
            "SELECT r.form_code, t.form_name, SUM(r.total_amount) n FROM form_requests r INNER JOIN form_types t ON t.form_code = r.form_code
              WHERE r.deleted_at IS NULL AND t.is_money = 1 AND r.form_code NOT IN ('F13') AND r.status IN ('verified','authorized','partly_paid','paid','closed')
                AND r.verified_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')" . self::cur('r.currency') . self::co('form_requests', 'r.company_id') . '
              GROUP BY r.form_code, t.form_name ORDER BY n DESC'
        );
        $chart = self::baseChart('spend-by-form', 'Spent this month', 'Verified requests by kind', $span, 'donut', 'rwf');
        $chart['categories'] = array_map(static fn (array $r): string => $r['form_code'] . ' ' . $r['form_name'], $rows);
        $chart['series'] = [['name' => 'Spent', 'data' => array_map(static fn (array $r): float => round((float) $r['n'], 2), $rows)]];
        $chart['slots'] = $rows === [] ? [] : range(0, count($rows) - 1);
        if ($rows === []) {
            return ['id' => 'spend-by-form', 'type' => 'notice', 'span' => $span, 'title' => 'Spent this month', 'subtitle' => '', 'message' => 'Nothing verified this month in ' . self::$currency . '.'];
        }

        return $chart;
    }

    private static function spendTrend(int $span): array
    {
        $months = self::months();
        $base = "FROM form_requests r INNER JOIN form_types t ON t.form_code = r.form_code WHERE r.deleted_at IS NULL AND t.is_money = 1 AND r.form_code <> 'F13' AND r.verified_at IS NOT NULL AND r.status <> 'cancelled'" . self::cur('r.currency') . self::co('form_requests', 'r.company_id');
        $trip = self::monthly("SELECT DATE_FORMAT(r.verified_at, '%Y-%m') m, SUM(r.total_amount) v {$base} AND r.form_code IN ('F1','F2','F10') GROUP BY m", $months);
        $fleet = self::monthly("SELECT DATE_FORMAT(r.verified_at, '%Y-%m') m, SUM(r.total_amount) v {$base} AND r.form_code IN ('F3','F4','F5','F6','F7') GROUP BY m", $months);
        $other = self::monthly("SELECT DATE_FORMAT(r.verified_at, '%Y-%m') m, SUM(r.total_amount) v {$base} AND r.form_code NOT IN ('F1','F2','F10','F3','F4','F5','F6','F7') GROUP BY m", $months);

        return self::timeChart('spend-trend', 'Monthly spend', 'Verified requests: trips, trucks and other, last 6 months', $span, 'stacked', 'rwf', $months, [['Trips (F1, F2, F10)', $trip], ['Trucks (F3–F7)', $fleet], ['Other', $other]]);
    }

    /** Income against cost in the books, per month, in the currency chosen. */
    private static function incomeAndCost(int $span): array
    {
        $months = self::months();
        $base = "FROM gl_journal_lines l INNER JOIN gl_journal_entries e ON e.id = l.entry_id INNER JOIN gl_accounts a ON a.id = l.account_id
                 WHERE e.deleted_at IS NULL AND e.status = 'posted'" . self::cur('e.currency') . self::co('gl_journal_entries', 'e.company_id');
        $income = self::monthly("SELECT DATE_FORMAT(e.entry_date, '%Y-%m') m, SUM(l.credit - l.debit) v {$base} AND a.account_type = 'income' GROUP BY m", $months);
        $cost = self::monthly("SELECT DATE_FORMAT(e.entry_date, '%Y-%m') m, SUM(l.debit - l.credit) v {$base} AND a.account_type IN ('expense','cost_of_sales') GROUP BY m", $months);

        return self::timeChart('income-cost', 'Income and cost', 'From the books, last 6 months', $span, 'column', 'rwf', $months, [['Income', $income], ['Cost', $cost]]);
    }

    private static function paidTrend(int $span): array
    {
        $months = self::months();
        $base = "FROM payment_batch_lines l INNER JOIN payment_batches b ON b.id = l.batch_id WHERE l.status = 'paid' AND l.paid_at IS NOT NULL" . self::cur('l.currency') . self::co('payment_batches', 'b.company_id');
        $momo = self::monthly("SELECT DATE_FORMAT(l.paid_at, '%Y-%m') m, SUM(l.amount) v {$base} AND b.method = 'momo' GROUP BY m", $months);
        $cash = self::monthly("SELECT DATE_FORMAT(l.paid_at, '%Y-%m') m, SUM(l.amount) v {$base} AND b.method = 'cash' GROUP BY m", $months);
        $bank = self::monthly("SELECT DATE_FORMAT(l.paid_at, '%Y-%m') m, SUM(l.amount) v {$base} AND b.method NOT IN ('momo','cash') GROUP BY m", $months);

        return self::timeChart('paid-trend', 'Paid out', 'By the Cashier, per month and method', $span, 'stacked', 'rwf', $months, [['Mobile money', $momo], ['Cash', $cash], ['Bank', $bank]]);
    }

    private static function paymentMethods(int $span): array
    {
        $rows = self::keyed2(self::rows("SELECT b.method k, COUNT(*) v FROM payment_batch_lines l INNER JOIN payment_batches b ON b.id = l.batch_id WHERE l.status = 'paid' AND l.paid_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)" . self::co('payment_batches', 'b.company_id') . ' GROUP BY b.method'));
        $methods = ['momo' => 'Mobile money', 'cash' => 'Cash', 'bank' => 'Bank'];
        foreach (array_keys($rows) as $method) {
            $methods[$method] ??= self::label((string) $method);
        }

        return self::donut('payment-methods', 'Payments by method', 'Lines paid, last 30 days', $span, $methods, $rows, 'payments');
    }

    private static function billedAndCollected(int $span): array
    {
        $months = self::months();
        $billed = self::monthly("SELECT DATE_FORMAT(issue_date, '%Y-%m') m, SUM(total_amount) v FROM invoices WHERE deleted_at IS NULL AND is_opening = 0 AND status NOT IN ('draft','cancelled')" . self::cur() . self::co('invoices') . ' GROUP BY m', $months);
        $collected = self::monthly("SELECT DATE_FORMAT(p.paid_at, '%Y-%m') m, SUM(p.amount) v FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE p.deleted_at IS NULL" . self::cur('i.currency') . self::co('invoices', 'i.company_id') . ' GROUP BY m', $months);

        return self::timeChart('billed-collected', 'Billed and collected', 'Customer invoices and the money received, last 6 months', $span, 'column', 'rwf', $months, [['Billed', $billed], ['Collected', $collected]]);
    }

    private static function fuelTrend(int $span): array
    {
        $months = self::months();
        $data = self::monthly("SELECT DATE_FORMAT(COALESCE(delivered_at, created_at), '%Y-%m') m, SUM(COALESCE(litres_delivered, litres_ordered)) v FROM fuel_orders WHERE status <> 'cancelled'" . self::co('fuel_orders') . ' GROUP BY m', $months);

        return self::timeChart('fuel-trend', 'Fuel', 'Litres given to trucks per month, last 6 months', $span, 'area', 'litres', $months, [['Litres', $data]]);
    }

    private static function fuelByVehicle(int $span): array
    {
        $rows = self::rows("SELECT v.plate_number, SUM(COALESCE(f.litres_delivered, f.litres_ordered)) n FROM fuel_orders f INNER JOIN vehicles v ON v.id = f.vehicle_id WHERE f.status <> 'cancelled' AND COALESCE(f.delivered_at, f.created_at) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)" . self::co('fuel_orders', 'f.company_id') . ' GROUP BY v.id, v.plate_number ORDER BY n DESC LIMIT 8');

        return self::rankedBars('fuel-by-vehicle', 'Fuel by truck', 'Litres, last 90 days (top 8)', $span, 'litres', $rows, 'plate_number', 'Litres');
    }

    private static function jobCards(int $span): array
    {
        return self::bars('job-cards', 'Job cards', 'Garage work by status', $span, 'column', self::JOB_STATUSES, self::countBy('maintenance_orders', 'status'), 'Job cards');
    }

    private static function maintenanceCost(int $span): ?array
    {
        if (self::$currency !== self::currencyCode(null)) {
            return null;   // job cards are costed in the company's own currency
        }
        $months = self::months();
        $data = self::monthly("SELECT DATE_FORMAT(COALESCE(completed_at, due_date, created_at), '%Y-%m') m, SUM(COALESCE(actual_cost, estimated_cost)) v FROM maintenance_orders WHERE deleted_at IS NULL AND status <> 'cancelled'" . self::co('maintenance_orders') . ' GROUP BY m', $months);

        return self::timeChart('maintenance-cost', 'Garage cost', 'Job card cost per month, last 6 months', $span, 'column', 'rwf', $months, [['Cost', $data]]);
    }

    private static function licenceExpiry(int $span): array
    {
        $today = date('Y-m-d');
        $row = self::row(
            "SELECT
                SUM(license_expiry < ?) expired,
                SUM(license_expiry >= ? AND license_expiry <= DATE_ADD(?, INTERVAL 30 DAY)) d30,
                SUM(license_expiry > DATE_ADD(?, INTERVAL 30 DAY) AND license_expiry <= DATE_ADD(?, INTERVAL 90 DAY)) d90,
                SUM(license_expiry > DATE_ADD(?, INTERVAL 90 DAY)) later
             FROM drivers WHERE deleted_at IS NULL AND status <> 'left' AND license_expiry IS NOT NULL" . self::co('drivers'),
            array_fill(0, 6, $today)
        );
        $chart = self::baseChart('licence-expiry', 'Driving licence expiry', 'Drivers by time left on their licence', $span, 'bar', 'int');
        $chart['categories'] = ['Expired', 'Within 30 days', '31 to 90 days', 'Over 90 days'];
        $chart['series'] = [['name' => 'Drivers', 'data' => [(int) ($row['expired'] ?? 0), (int) ($row['d30'] ?? 0), (int) ($row['d90'] ?? 0), (int) ($row['later'] ?? 0)]]];
        $chart['slots'] = [0];

        return $chart;
    }

    private static function stockLevels(int $span): array
    {
        $rows = self::rows('SELECT item_name, quantity, minimum_level FROM inventory_items WHERE deleted_at IS NULL' . self::co('inventory_items') . ' ORDER BY quantity / GREATEST(minimum_level, 1) ASC, item_name LIMIT 8');
        $chart = self::baseChart('stock-levels', 'Stock against minimum level', 'Items closest to running out (lowest 8)', $span, 'bar', 'int');
        $chart['categories'] = array_column($rows, 'item_name');
        $chart['series'] = [
            ['name' => 'On hand', 'data' => array_map(static fn (array $r): float => (float) $r['quantity'], $rows)],
            ['name' => 'Minimum level', 'data' => array_map(static fn (array $r): float => (float) $r['minimum_level'], $rows)],
        ];
        $chart['slots'] = [0, 1];

        return $chart;
    }

    private static function stockStatus(int $span): array
    {
        return self::donut('stock-status', 'Stock status', 'Items by stock status', $span, self::STOCK_STATUSES, self::countBy('inventory_items', 'status'), 'items');
    }

    private static function stockValue(int $span): ?array
    {
        if (self::$currency !== self::currencyCode(null)) {
            return null;   // stock is valued in the company's own currency
        }
        $rows = self::rows('SELECT w.warehouse_name, SUM(i.quantity * i.unit_cost) n FROM inventory_items i INNER JOIN warehouses w ON w.id = i.warehouse_id WHERE i.deleted_at IS NULL' . self::co('inventory_items', 'i.company_id') . ' GROUP BY w.id, w.warehouse_name ORDER BY n DESC');

        return self::rankedBars('stock-value', 'Stock value by store', 'Quantity on hand times unit cost', $span, 'rwf', $rows, 'warehouse_name', 'Stock value');
    }

    private static function driverCharts(?int $userId): array
    {
        $driver = self::row('SELECT id, license_expiry FROM drivers WHERE deleted_at IS NULL AND user_id = ?', [$userId ?? 0]);
        if ($driver === null) {
            return [['id' => 'no-driver', 'type' => 'notice', 'span' => 12, 'title' => 'Personal charts unavailable', 'subtitle' => '', 'message' => 'This account is not linked to a driver profile yet, so there are no trips to chart.']];
        }

        $driverId = (int) $driver['id'];
        $counts = self::keyed2(self::rows('SELECT status k, COUNT(*) v FROM trips WHERE deleted_at IS NULL AND driver_id = ? GROUP BY status', [$driverId]));

        return [
            self::donut('my-trips-status', 'My trips', 'Your trips by status', 4, array_slice(TripLife::STATUS, 0, 10, true), $counts, 'trips'),
            self::tripsWeekly(8, 'My trips per week', 'Your trips departing each week, last 8 weeks', $driverId),
            self::licenceValidity(12, $driver['license_expiry']),
        ];
    }

    private static function licenceValidity(int $span, ?string $expiry): array
    {
        if ($expiry === null) {
            return ['id' => 'licence-validity', 'type' => 'notice', 'span' => $span, 'title' => 'Licence validity', 'subtitle' => '', 'message' => 'No licence expiry date is recorded for your profile.'];
        }

        $days = (int) floor((strtotime($expiry) - strtotime(date('Y-m-d'))) / 86400);
        $tone = $days <= 30 ? 'critical' : ($days <= 90 ? 'warning' : 'accent');
        $caption = $days < 0 ? "Expired on {$expiry}" : "Expires on {$expiry}, in {$days} days";

        return self::meter('licence-validity', 'Licence validity', 'Days left on your driving licence (scale: one year)', $span, (float) max(0, min($days, 365)), 365, 'days', $tone, $caption);
    }

    /** @return list<array{tone: string, title: string, text: string}> */
    private static function driverAttention(?int $userId): array
    {
        $driver = self::row('SELECT id, license_expiry FROM drivers WHERE deleted_at IS NULL AND user_id = ?', [$userId ?? 0]);
        if ($driver === null) {
            return [['tone' => 'warning', 'title' => 'No driver profile', 'text' => 'Ask the Operational Manager to link your login to a driver profile.']];
        }

        $active = (int) self::scalar("SELECT COUNT(*) FROM trips WHERE deleted_at IS NULL AND driver_id = ? AND status IN ('" . implode("','", array_diff(TripLife::ACTIVE, ['planned'])) . "')", [$driver['id']]);
        $days = $driver['license_expiry'] !== null ? (int) floor((strtotime($driver['license_expiry']) - strtotime(date('Y-m-d'))) / 86400) : null;

        return [
            ['tone' => $active > 0 ? 'primary' : 'success', 'title' => 'Trip in progress', 'text' => $active > 0 ? 'You have a trip in progress. Use the app buttons as you go.' : 'No trip in progress right now.'],
            ['tone' => $days !== null && $days <= 90 ? 'warning' : 'success', 'title' => 'Driving licence', 'text' => $days === null ? 'No expiry date recorded.' : ($days < 0 ? 'Your licence has expired.' : "Your licence expires in {$days} days.")],
        ];
    }

    private static function clearanceStages(int $span): array
    {
        $chart = self::baseChart('clearance-stages', 'Clearance files by stage', 'Open files, from papers to release', $span, 'bar', 'int');
        $counts = [];
        foreach (Clearance::board() as $stage) {
            $counts[$stage['label']] = count($stage['files']);
        }
        $chart['categories'] = array_keys($counts);
        $chart['series'] = [['name' => 'Files', 'data' => array_values($counts)]];
        $chart['slots'] = [0];

        return $chart;
    }

    private static function borderDwell(int $span): array
    {
        $rows = array_map(static fn (array $r): array => ['post_name' => $r['post_name'], 'n' => (float) $r['avg_hours']], BorderCrossing::dwellByPost());

        return self::rankedBars('border-dwell', 'Hours at each border', 'Average from arrival to departure, last 90 days', $span, 'int', $rows, 'post_name', 'Hours');
    }

    // ---- chart builders -------------------------------------------------------------------------

    private static function baseChart(string $id, string $title, string $subtitle, int $span, string $type, string $format): array
    {
        return ['id' => $id, 'title' => $title, 'subtitle' => $subtitle, 'span' => $span, 'type' => $type, 'format' => $format, 'categories' => [], 'series' => [], 'slots' => []];
    }

    /** @param list<int>|null $slots colour slots per status; defaults to 0..n-1 in legend order */
    private static function donut(string $id, string $title, string $subtitle, int $span, array $statuses, array $counts, string $unit, ?array $slots = null): array
    {
        $chart = self::baseChart($id, $title, $subtitle, $span, 'donut', 'int');
        $chart['unit'] = $unit;
        $chart['categories'] = array_values($statuses);
        $chart['series'] = [['name' => ucfirst($unit), 'data' => array_map(static fn (string|int $key): int => (int) ($counts[$key] ?? 0), array_keys($statuses))]];
        $chart['slots'] = $slots ?? range(0, count($statuses) - 1);

        return $chart;
    }

    private static function bars(string $id, string $title, string $subtitle, int $span, string $type, array $statuses, array $counts, string $seriesName): array
    {
        $chart = self::baseChart($id, $title, $subtitle, $span, $type, 'int');
        $chart['categories'] = array_values($statuses);
        $chart['series'] = [['name' => $seriesName, 'data' => array_map(static fn (string|int $key): int => (int) ($counts[$key] ?? 0), array_keys($statuses))]];
        $chart['slots'] = [0];

        return $chart;
    }

    private static function rankedBars(string $id, string $title, string $subtitle, int $span, string $format, array $rows, string $labelColumn, string $seriesName): array
    {
        $chart = self::baseChart($id, $title, $subtitle, $span, 'bar', $format);
        $chart['categories'] = array_column($rows, $labelColumn);
        $chart['series'] = [['name' => $seriesName, 'data' => array_map(static fn (array $r): float => round((float) $r['n'], 2), $rows)]];
        $chart['slots'] = [0];

        return $chart;
    }

    /** @param array<string, string> $periods key => label; @param list<array{0: string, 1: list<float|int|null>}> $series */
    private static function timeChart(string $id, string $title, string $subtitle, int $span, string $type, string $format, array $periods, array $series): array
    {
        $chart = self::baseChart($id, $title, $subtitle, $span, $type, $format);
        $chart['categories'] = array_values($periods);
        foreach ($series as $index => [$name, $data]) {
            $chart['series'][] = ['name' => $name, 'data' => $data];
            $chart['slots'][] = $index;
        }

        return $chart;
    }

    private static function meter(string $id, string $title, string $subtitle, int $span, float $value, float $max, string $format, string $tone, string $caption): array
    {
        return ['id' => $id, 'title' => $title, 'subtitle' => $subtitle, 'span' => $span, 'type' => 'meter', 'format' => $format, 'meter' => ['value' => round($value, 1), 'max' => $max, 'tone' => $tone, 'caption' => $caption]];
    }

    // ---- data helpers ---------------------------------------------------------------------------

    /** @return array<string, string> month key (Y-m) => short label, oldest first */
    private static function months(int $count = 6): array
    {
        $months = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            $time = strtotime("first day of -{$i} month");
            $months[date('Y-m', $time)] = date('M', $time);
        }

        return $months;
    }

    /** @return array<string, string> Monday date (Y-m-d) => label, oldest first */
    private static function weeks(int $count = 8): array
    {
        $weeks = [];
        $monday = strtotime('monday this week');
        for ($i = $count - 1; $i >= 0; $i--) {
            $time = strtotime("-{$i} week", $monday);
            $weeks[date('Y-m-d', $time)] = date('j M', $time);
        }

        return $weeks;
    }

    /** @return list<float> values aligned to $periods, 0 where a period has no rows */
    private static function monthly(string $sql, array $periods): array
    {
        return self::keyed(self::rows($sql), array_keys($periods), 'm');
    }

    /** @return list<float> */
    private static function keyed(array $rows, array $keys, string $keyColumn = 'k'): array
    {
        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row[$keyColumn]] = (float) $row['v'];
        }

        return array_map(static fn (string $key): float => $byKey[$key] ?? 0.0, $keys);
    }

    /** @return array<string, int|float> */
    private static function keyed2(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[$row['k']] = $row['v'] + 0;
        }

        return $out;
    }

    /** Counts rows per status for a whitelisted table, in the company in view. @return array<string, int> */
    private static function countBy(string $table, string $column): array
    {
        $allowed = ['vehicles' => 'status', 'trips' => 'status', 'drivers' => 'status', 'inventory_items' => 'status', 'maintenance_orders' => 'status'];
        if (($allowed[$table] ?? null) !== $column) {
            throw new \InvalidArgumentException('Unsupported count target.');
        }

        return self::keyed2(self::rows("SELECT {$column} k, COUNT(*) v FROM {$table} WHERE deleted_at IS NULL" . self::co($table) . " GROUP BY {$column}"));
    }

    private static function serviceDueCount(): int
    {
        $count = 0;
        foreach (self::rows("SELECT * FROM vehicles WHERE deleted_at IS NULL AND status <> 'inactive'" . self::co('vehicles')) as $vehicle) {
            if (Fleet::serviceState($vehicle)['state'] !== 'ok') {
                $count++;
            }
        }

        return $count;
    }

    private static function label(string $value): string
    {
        return ucfirst(str_replace('_', ' ', $value));
    }

    private static function rows(string $sql, array $params = []): array
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function row(string $sql, array $params = []): ?array
    {
        $rows = self::rows($sql, $params);

        return $rows[0] ?? null;
    }

    private static function scalar(string $sql, array $params = []): mixed
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        $value = $statement->fetchColumn();

        return $value === false ? 0 : $value;
    }
}
