<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Dashboard data: KPI cards, charts, attention items and recent trips, all read from the database.
 *
 * A chart is a plain array the browser renders (see public/assets/js/lms-charts.js):
 * id, title, subtitle, type (donut|column|bar|area|line|stacked|meter|notice), span (grid columns of 12),
 * format (int|rwf|litres|pct), categories, series [{name, data}], slots (categorical colour slot per
 * series - or per category for donuts - so colour follows the entity, never its rank).
 */
final class ChartData
{
    private const VEHICLE_STATUSES = ['available' => 'Available', 'on_trip' => 'On trip', 'maintenance' => 'Maintenance', 'inactive' => 'Inactive'];
    private const TRIP_STATUSES = ['requested' => 'Requested', 'approved' => 'Approved', 'loading' => 'Loading', 'in_transit' => 'In transit', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
    private const REQUEST_STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'assigned' => 'Assigned', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'];
    private const DELIVERY_STATUSES = ['loading' => 'Loading', 'in_transit' => 'In transit', 'delivered' => 'Delivered', 'failed' => 'Failed'];
    private const STOCK_STATUSES = ['in_stock' => 'In stock', 'reorder' => 'Reorder', 'out_of_stock' => 'Out of stock'];
    private const PURCHASE_STATUSES = ['draft' => 'Draft', 'quotation' => 'Quotation', 'approved' => 'Approved', 'received' => 'Received', 'rejected' => 'Rejected'];
    /** Same colour as the trip statuses of the same name (Loading, In transit, Delivered), so a status reads the same everywhere. */
    private const DELIVERY_SLOTS = [2, 3, 4, 5];
    private const EXPENSE_CATEGORIES = ['Fuel', 'Toll', 'Repair', 'Allowance', 'Parking', 'Insurance'];

    public static function forRole(string $role, ?int $userId): array
    {
        return match ($role) {
            'super_admin' => [self::fleetStatus(4), self::monthlyExpenses(8), self::tripsByStatus(6), self::auditActivity(6)],
            'logistics_manager' => [self::tripsWeekly(8, 'Trips per week', 'Trips departing each week, last 8 weeks'), self::deliveryCompletion(4), self::requestPipeline(6), self::deliveriesByStatus(6)],
            'fleet_manager' => [self::fleetStatus(4), self::fuelTrend(8), self::fuelByVehicle(6), self::maintenanceCost(6), self::licenceExpiry(12)],
            'warehouse_manager' => [self::stockLevels(8), self::stockStatus(4), self::stockValue(6), self::purchaseStatus(6)],
            'finance' => [self::expensesByCategory(4), self::expenseTrend(8), self::expenseStatusByMonth(6), self::fuelCostByVehicle(6), self::procurementBySupplier(12)],
            'management' => [self::fleetUtilization(4), self::tripsTrend(8), self::costTrend(6), self::costPerTrip(6)],
            'driver' => self::driverCharts($userId),
            default => [],
        };
    }

    /** @return list<array{label: string, value: string, trend: string, icon: string}> */
    public static function metrics(string $role, ?int $userId): array
    {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $vehicles = (int) self::scalar('SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL');
        $onTrip = (int) self::scalar("SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL AND status = 'on_trip'");
        $available = (int) self::scalar("SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL AND status = 'available'");
        $activeTrips = (int) self::scalar("SELECT COUNT(*) FROM trips WHERE deleted_at IS NULL AND status IN ('loading','in_transit')");
        $monthExpenses = (float) self::scalar("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE deleted_at IS NULL AND status <> 'rejected' AND expense_date >= ?", [$monthStart]);
        $pendingExpenses = self::row("SELECT COUNT(*) n, COALESCE(SUM(amount), 0) total FROM expenses WHERE deleted_at IS NULL AND status = 'pending'");
        $serviceDue = (int) self::scalar('SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL AND next_service_date IS NOT NULL AND next_service_date <= DATE_ADD(?, INTERVAL 14 DAY)', [$today]);
        $lowStock = (int) self::scalar('SELECT COUNT(*) FROM inventory_items WHERE deleted_at IS NULL AND quantity <= minimum_level');
        $delivered = (int) self::scalar("SELECT COUNT(*) FROM deliveries WHERE deleted_at IS NULL AND status = 'delivered'");
        $deliveries = (int) self::scalar('SELECT COUNT(*) FROM deliveries WHERE deleted_at IS NULL');
        $card = static fn (string $label, string|int $value, string $caption, string $icon): array => ['label' => $label, 'value' => (string) $value, 'trend' => $caption, 'icon' => $icon];

        return match ($role) {
            'super_admin' => [
                $card('Total vehicles', $vehicles, "{$available} available", 'truck'),
                $card('Active trips', $activeTrips, self::scalar("SELECT COUNT(*) FROM trips WHERE deleted_at IS NULL AND status IN ('requested','approved')") . ' planned', 'navigation'),
                $card('Pending requests', (int) self::scalar("SELECT COUNT(*) FROM transport_requests WHERE deleted_at IS NULL AND status = 'pending'"), self::scalar("SELECT COUNT(*) FROM transport_requests WHERE deleted_at IS NULL AND status = 'pending' AND priority IN ('high','urgent')") . ' high priority', 'clipboard'),
                $card('Monthly expenses', self::rwf($monthExpenses), "{$pendingExpenses['n']} pending approval", 'credit-card'),
            ],
            'logistics_manager' => [
                $card('Fleet vehicles', $vehicles, "{$onTrip} on trip", 'truck'),
                $card('Active trips', $activeTrips, self::scalar("SELECT COUNT(*) FROM trips WHERE deleted_at IS NULL AND status IN ('requested','approved')") . ' planned', 'navigation'),
                $card('Deliveries in progress', (int) self::scalar("SELECT COUNT(*) FROM deliveries WHERE deleted_at IS NULL AND status IN ('loading','in_transit')"), "{$delivered} delivered", 'package'),
                $card('Open maintenance', (int) self::scalar("SELECT COUNT(*) FROM maintenance_orders WHERE deleted_at IS NULL AND status IN ('open','scheduled','in_progress')"), self::scalar("SELECT COUNT(*) FROM maintenance_orders WHERE deleted_at IS NULL AND status IN ('open','scheduled','in_progress') AND priority = 'urgent'") . ' urgent', 'tool'),
            ],
            'fleet_manager' => [
                $card('Fleet vehicles', $vehicles, "{$onTrip} on trip", 'truck'),
                $card('Available vehicles', $available, self::scalar("SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL AND status = 'maintenance'") . ' in maintenance', 'check'),
                $card('Due for service', $serviceDue, self::scalar('SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL AND next_service_date < ?', [$today]) . ' overdue', 'tool'),
                $card('Fuel this month', self::number((float) self::scalar('SELECT COALESCE(SUM(litres), 0) FROM fuel_records WHERE deleted_at IS NULL AND purchased_at >= ?', [$monthStart])) . ' L', self::scalar('SELECT COUNT(*) FROM fuel_records WHERE deleted_at IS NULL AND purchased_at >= ?', [$monthStart]) . ' fill-ups', 'droplet'),
            ],
            'warehouse_manager' => [
                $card('Stock items', (int) self::scalar('SELECT COUNT(*) FROM inventory_items WHERE deleted_at IS NULL'), self::scalar("SELECT COUNT(*) FROM warehouses WHERE deleted_at IS NULL AND status = 'active'") . ' warehouses', 'package'),
                $card('Low stock alerts', $lowStock, self::scalar('SELECT COUNT(*) FROM inventory_items WHERE deleted_at IS NULL AND quantity = 0') . ' out of stock', 'alert-triangle'),
                $card('Open purchase requests', (int) self::scalar("SELECT COUNT(*) FROM purchase_requests WHERE deleted_at IS NULL AND status IN ('draft','quotation','approved')"), self::scalar("SELECT COUNT(*) FROM purchase_requests WHERE deleted_at IS NULL AND status = 'approved'") . ' approved', 'clipboard'),
                $card('Stock value', self::rwf((float) self::scalar('SELECT COALESCE(SUM(quantity * unit_cost), 0) FROM inventory_items WHERE deleted_at IS NULL')), 'across all warehouses', 'layers'),
            ],
            'finance' => [
                $card('Monthly expenses', self::rwf($monthExpenses), self::scalar("SELECT COUNT(*) FROM expenses WHERE deleted_at IS NULL AND status <> 'rejected' AND expense_date >= ?", [$monthStart]) . ' claims', 'credit-card'),
                $card('Pending approvals', (int) $pendingExpenses['n'], self::rwf((float) $pendingExpenses['total']), 'clock'),
                $card('Fuel spend', self::rwf((float) self::scalar('SELECT COALESCE(SUM(litres * unit_price), 0) FROM fuel_records WHERE deleted_at IS NULL AND purchased_at >= ?', [$monthStart])), 'this month', 'droplet'),
                $card('Cost per trip', self::costPerTripThisMonth($monthStart, $monthExpenses), 'this month', 'trending-down'),
            ],
            'management' => [
                $card('Fleet utilization', self::percent($onTrip, $vehicles), "{$onTrip} of {$vehicles} vehicles on trip", 'truck'),
                $card('Delivery completion', self::percent($delivered, $deliveries), "{$delivered} of {$deliveries} deliveries", 'check'),
                $card('Monthly logistics cost', self::rwf($monthExpenses), "{$pendingExpenses['n']} pending approval", 'credit-card'),
                $card('Open risks', $serviceDue + $lowStock + self::licencesExpiringCount(90), 'service, licences, stock', 'alert-triangle'),
            ],
            'driver' => self::driverMetrics($userId),
            default => [],
        };
    }

    /** @return list<array{tone: string, title: string, text: string}> */
    public static function attention(string $role, ?int $userId): array
    {
        $today = date('Y-m-d');
        $plural = static fn (int $n, string $one, string $many): string => $n . ' ' . ($n === 1 ? $one : $many);
        $items = [
            'service' => static function () use ($today, $plural): array {
                $n = (int) self::scalar('SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL AND next_service_date IS NOT NULL AND next_service_date <= DATE_ADD(?, INTERVAL 14 DAY)', [$today]);
                return ['Vehicles due for service', $n, $plural($n, 'vehicle is', 'vehicles are') . ' due or overdue for service within 14 days.'];
            },
            'licences' => static function () use ($plural): array {
                $n = self::licencesExpiringCount(90);
                return ['Licences expiring soon', $n, $plural($n, 'driver licence expires', 'driver licences expire') . ' within 90 days.'];
            },
            'stock' => static function () use ($plural): array {
                $n = (int) self::scalar('SELECT COUNT(*) FROM inventory_items WHERE deleted_at IS NULL AND quantity <= minimum_level');
                return ['Low stock items', $n, $plural($n, 'item is', 'items are') . ' at or below the minimum level.'];
            },
            'approvals' => static function (): array {
                $r = self::row("SELECT COUNT(*) n, COALESCE(SUM(amount), 0) total FROM expenses WHERE deleted_at IS NULL AND status = 'pending'");
                return ['Expenses awaiting approval', (int) $r['n'], $r['n'] . ' pending, worth ' . self::rwf((float) $r['total']) . '.'];
            },
            'purchases' => static function () use ($plural): array {
                $n = (int) self::scalar("SELECT COUNT(*) FROM purchase_requests WHERE deleted_at IS NULL AND status IN ('draft','quotation')");
                return ['Purchase requests in progress', $n, $plural($n, 'request is', 'requests are') . ' still in draft or quotation.'];
            },
            'work_orders' => static function () use ($plural): array {
                $n = (int) self::scalar("SELECT COUNT(*) FROM maintenance_orders WHERE deleted_at IS NULL AND status IN ('open','scheduled','in_progress')");
                return ['Open work orders', $n, $plural($n, 'work order is', 'work orders are') . ' open or in progress.'];
            },
        ];

        if ($role === 'driver') {
            return self::driverAttention($userId);
        }

        $keys = match ($role) {
            'fleet_manager' => ['service', 'licences', 'work_orders'],
            'warehouse_manager' => ['stock', 'purchases', 'work_orders'],
            'finance' => ['approvals', 'purchases', 'stock'],
            default => ['service', 'licences', 'approvals', 'stock'],
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
                WHERE t.deleted_at IS NULL';
        $params = [];
        if ($role === 'driver') {
            $sql .= ' AND d.user_id = ?';
            $params[] = $userId ?? 0;
        }
        $sql .= ' ORDER BY t.departure_at DESC, t.id DESC LIMIT ' . (int) $limit;

        return array_map(static fn (array $r): array => [
            'reference' => $r['reference_code'],
            'route' => $r['pickup_location'] . ' - ' . $r['destination'],
            'vehicle' => $r['vehicle'],
            'driver' => $r['driver'],
            'status' => self::label($r['status']),
        ], self::rows($sql, $params));
    }

    // ---- charts ---------------------------------------------------------------------------------

    private static function fleetStatus(int $span): array
    {
        return self::donut('fleet-status', 'Fleet status', 'Vehicles by current status', $span, self::VEHICLE_STATUSES, self::countBy('vehicles', 'status'), 'vehicles');
    }

    private static function tripsByStatus(int $span): array
    {
        return self::bars('trips-status', 'Trips by status', 'All trips on record', $span, 'bar', self::TRIP_STATUSES, self::countBy('trips', 'status'), 'Trips');
    }

    private static function monthlyExpenses(int $span): array
    {
        $months = self::months();
        $data = self::monthly("SELECT DATE_FORMAT(expense_date, '%Y-%m') m, SUM(amount) v FROM expenses WHERE deleted_at IS NULL AND status <> 'rejected' GROUP BY m", $months);

        return self::timeChart('monthly-expenses', 'Monthly expenses', 'Approved and pending claims, last 6 months', $span, 'area', 'rwf', $months, [['Expenses', $data]]);
    }

    private static function auditActivity(int $span): array
    {
        $rows = self::rows("SELECT entity_type, COUNT(*) n FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND entity_type <> 'database' GROUP BY entity_type ORDER BY n DESC, entity_type LIMIT 6");
        $chart = self::baseChart('audit-activity', 'System activity', 'Recorded actions by module, last 30 days', $span, 'bar', 'int');
        $chart['categories'] = array_map(static fn (array $r): string => ucfirst($r['entity_type']), $rows);
        $chart['series'] = [['name' => 'Actions', 'data' => array_map(static fn (array $r): int => (int) $r['n'], $rows)]];
        $chart['slots'] = [0];

        return $chart;
    }

    private static function tripsWeekly(int $span, string $title, string $subtitle, ?int $driverId = null): array
    {
        $weeks = self::weeks();
        $where = "status <> 'cancelled'" . ($driverId !== null ? ' AND driver_id = ' . (int) $driverId : '');
        $data = self::keyed(self::rows("SELECT DATE_FORMAT(DATE_SUB(DATE(departure_at), INTERVAL WEEKDAY(departure_at) DAY), '%Y-%m-%d') k, COUNT(*) v FROM trips WHERE deleted_at IS NULL AND departure_at IS NOT NULL AND {$where} GROUP BY k"), array_keys($weeks));
        $chart = self::timeChart($driverId !== null ? 'my-trips-weekly' : 'trips-weekly', $title, $subtitle, $span, 'column', 'int', $weeks, [['Trips', $data]]);

        return $chart;
    }

    private static function deliveryCompletion(int $span): array
    {
        $total = (int) self::scalar('SELECT COUNT(*) FROM deliveries WHERE deleted_at IS NULL');
        $done = (int) self::scalar("SELECT COUNT(*) FROM deliveries WHERE deleted_at IS NULL AND status = 'delivered'");
        $failed = (int) self::scalar("SELECT COUNT(*) FROM deliveries WHERE deleted_at IS NULL AND status = 'failed'");

        return self::meter('delivery-completion', 'Delivery completion', 'Deliveries completed successfully', $span, $total === 0 ? 0.0 : $done / $total * 100, 100, 'pct', 'accent', "{$done} of {$total} deliveries delivered" . ($failed > 0 ? ", {$failed} failed" : ''));
    }

    private static function requestPipeline(int $span): array
    {
        return self::bars('request-pipeline', 'Transport request pipeline', 'Requests by status', $span, 'bar', self::REQUEST_STATUSES, self::countBy('transport_requests', 'status'), 'Requests');
    }

    private static function deliveriesByStatus(int $span, ?int $driverId = null): array
    {
        $counts = $driverId === null
            ? self::countBy('deliveries', 'status')
            : self::keyed2(self::rows('SELECT d.status k, COUNT(*) v FROM deliveries d INNER JOIN trips t ON t.id = d.trip_id WHERE d.deleted_at IS NULL AND t.deleted_at IS NULL AND t.driver_id = ? GROUP BY d.status', [$driverId]));

        return self::donut($driverId === null ? 'deliveries-status' : 'my-deliveries', $driverId === null ? 'Deliveries by status' : 'My deliveries', 'Proof-of-delivery workflow', $span, self::DELIVERY_STATUSES, $counts, 'deliveries', self::DELIVERY_SLOTS);
    }

    private static function fuelTrend(int $span): array
    {
        $months = self::months();
        $data = self::monthly("SELECT DATE_FORMAT(purchased_at, '%Y-%m') m, SUM(litres) v FROM fuel_records WHERE deleted_at IS NULL GROUP BY m", $months);

        return self::timeChart('fuel-trend', 'Fuel purchased', 'Litres per month, last 6 months', $span, 'area', 'litres', $months, [['Litres', $data]]);
    }

    private static function fuelByVehicle(int $span): array
    {
        $rows = self::rows('SELECT v.plate_number, SUM(f.litres) n FROM fuel_records f INNER JOIN vehicles v ON v.id = f.vehicle_id WHERE f.deleted_at IS NULL AND v.deleted_at IS NULL AND f.purchased_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY v.id, v.plate_number ORDER BY n DESC LIMIT 8');

        return self::rankedBars('fuel-by-vehicle', 'Fuel by vehicle', 'Litres purchased, last 90 days (top 8)', $span, 'litres', $rows, 'plate_number', 'Litres');
    }

    private static function maintenanceCost(int $span): array
    {
        $months = self::months();
        $data = self::monthly("SELECT DATE_FORMAT(due_date, '%Y-%m') m, SUM(estimated_cost) v FROM maintenance_orders WHERE deleted_at IS NULL AND status <> 'cancelled' AND due_date IS NOT NULL GROUP BY m", $months);

        return self::timeChart('maintenance-cost', 'Maintenance cost', 'Work order cost by due month, last 6 months', $span, 'column', 'rwf', $months, [['Cost', $data]]);
    }

    private static function licenceExpiry(int $span): array
    {
        $today = date('Y-m-d');
        $row = self::row(
            'SELECT
                SUM(license_expiry < ?) expired,
                SUM(license_expiry >= ? AND license_expiry <= DATE_ADD(?, INTERVAL 30 DAY)) d30,
                SUM(license_expiry > DATE_ADD(?, INTERVAL 30 DAY) AND license_expiry <= DATE_ADD(?, INTERVAL 90 DAY)) d90,
                SUM(license_expiry > DATE_ADD(?, INTERVAL 90 DAY)) later
             FROM drivers WHERE deleted_at IS NULL AND license_expiry IS NOT NULL',
            array_fill(0, 6, $today)
        );
        $chart = self::baseChart('licence-expiry', 'Driver licence expiry', 'Drivers by time left on their licence', $span, 'bar', 'int');
        $chart['categories'] = ['Expired', 'Within 30 days', '31 to 90 days', 'Over 90 days'];
        $chart['series'] = [['name' => 'Drivers', 'data' => [(int) $row['expired'], (int) $row['d30'], (int) $row['d90'], (int) $row['later']]]];
        $chart['slots'] = [0];

        return $chart;
    }

    private static function stockLevels(int $span): array
    {
        $rows = self::rows('SELECT item_name, quantity, minimum_level FROM inventory_items WHERE deleted_at IS NULL ORDER BY quantity / GREATEST(minimum_level, 1) ASC, item_name LIMIT 8');
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

    private static function stockValue(int $span): array
    {
        $rows = self::rows('SELECT w.warehouse_name, SUM(i.quantity * i.unit_cost) n FROM inventory_items i INNER JOIN warehouses w ON w.id = i.warehouse_id WHERE i.deleted_at IS NULL GROUP BY w.id, w.warehouse_name ORDER BY n DESC');

        return self::rankedBars('stock-value', 'Stock value by warehouse', 'Quantity on hand times unit cost', $span, 'rwf', $rows, 'warehouse_name', 'Stock value');
    }

    private static function purchaseStatus(int $span): array
    {
        return self::bars('purchase-status', 'Purchase requests', 'Requests by status', $span, 'column', self::PURCHASE_STATUSES, self::countBy('purchase_requests', 'status'), 'Requests');
    }

    private static function expensesByCategory(int $span): array
    {
        $totals = self::keyed2(self::rows("SELECT category k, SUM(amount) v FROM expenses WHERE deleted_at IS NULL AND status <> 'rejected' GROUP BY category"));
        $chart = self::baseChart('expenses-category', 'Expenses by category', 'Approved and pending claims', $span, 'donut', 'rwf');
        $chart['categories'] = self::EXPENSE_CATEGORIES;
        $chart['series'] = [['name' => 'Expenses', 'data' => array_map(static fn (string $c): float => (float) ($totals[$c] ?? 0), self::EXPENSE_CATEGORIES)]];
        $chart['slots'] = range(0, count(self::EXPENSE_CATEGORIES) - 1);

        return $chart;
    }

    private static function expenseTrend(int $span): array
    {
        $months = self::months();
        $data = self::monthly("SELECT DATE_FORMAT(expense_date, '%Y-%m') m, SUM(amount) v FROM expenses WHERE deleted_at IS NULL AND status <> 'rejected' GROUP BY m", $months);

        return self::timeChart('expense-trend', 'Monthly spend', 'Approved and pending claims, last 6 months', $span, 'area', 'rwf', $months, [['Expenses', $data]]);
    }

    private static function expenseStatusByMonth(int $span): array
    {
        $months = self::months();
        $series = [];
        foreach (['approved' => 'Approved', 'pending' => 'Pending', 'rejected' => 'Rejected'] as $status => $label) {
            $series[] = [$label, self::monthly("SELECT DATE_FORMAT(expense_date, '%Y-%m') m, SUM(amount) v FROM expenses WHERE deleted_at IS NULL AND status = '{$status}' GROUP BY m", $months)];
        }

        return self::timeChart('expense-status', 'Expense approvals', 'Claim value by approval status, last 6 months', $span, 'stacked', 'rwf', $months, $series);
    }

    private static function fuelCostByVehicle(int $span): array
    {
        $rows = self::rows('SELECT v.plate_number, SUM(f.litres * f.unit_price) n FROM fuel_records f INNER JOIN vehicles v ON v.id = f.vehicle_id WHERE f.deleted_at IS NULL AND v.deleted_at IS NULL AND f.purchased_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY v.id, v.plate_number ORDER BY n DESC LIMIT 8');

        return self::rankedBars('fuel-cost-vehicle', 'Fuel cost by vehicle', 'Litres times unit price, last 90 days (top 8)', $span, 'rwf', $rows, 'plate_number', 'Fuel cost');
    }

    private static function procurementBySupplier(int $span): array
    {
        $rows = self::rows("SELECT s.supplier_name, SUM(p.amount) n FROM purchase_requests p INNER JOIN suppliers s ON s.id = p.supplier_id WHERE p.deleted_at IS NULL AND p.status <> 'rejected' GROUP BY s.id, s.supplier_name ORDER BY n DESC");

        return self::rankedBars('procurement-supplier', 'Procurement by supplier', 'Purchase request value, excluding rejected', $span, 'rwf', $rows, 'supplier_name', 'Spend');
    }

    private static function fleetUtilization(int $span): array
    {
        $total = (int) self::scalar('SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL');
        $onTrip = (int) self::scalar("SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL AND status = 'on_trip'");

        return self::meter('fleet-utilization', 'Fleet utilization', 'Share of vehicles currently on a trip', $span, $total === 0 ? 0.0 : $onTrip / $total * 100, 100, 'pct', 'accent', "{$onTrip} of {$total} vehicles on trip");
    }

    private static function tripsTrend(int $span): array
    {
        $months = self::months();
        $data = self::monthly("SELECT DATE_FORMAT(departure_at, '%Y-%m') m, COUNT(*) v FROM trips WHERE deleted_at IS NULL AND status <> 'cancelled' AND departure_at IS NOT NULL GROUP BY m", $months);

        return self::timeChart('trips-trend', 'Trips per month', 'Completed and active trips, last 6 months', $span, 'line', 'int', $months, [['Trips', $data]]);
    }

    private static function costTrend(int $span): array
    {
        $months = self::months();
        $data = self::monthly("SELECT DATE_FORMAT(expense_date, '%Y-%m') m, SUM(amount) v FROM expenses WHERE deleted_at IS NULL AND status <> 'rejected' GROUP BY m", $months);

        return self::timeChart('cost-trend', 'Logistics cost', 'Approved and pending claims per month', $span, 'area', 'rwf', $months, [['Cost', $data]]);
    }

    private static function costPerTrip(int $span): array
    {
        $months = self::months();
        $cost = self::monthly("SELECT DATE_FORMAT(expense_date, '%Y-%m') m, SUM(amount) v FROM expenses WHERE deleted_at IS NULL AND status <> 'rejected' GROUP BY m", $months);
        $trips = self::monthly("SELECT DATE_FORMAT(departure_at, '%Y-%m') m, COUNT(*) v FROM trips WHERE deleted_at IS NULL AND status <> 'cancelled' AND departure_at IS NOT NULL GROUP BY m", $months);
        $perTrip = [];
        foreach (array_keys($cost) as $i) {
            $perTrip[] = $trips[$i] > 0 ? round($cost[$i] / $trips[$i]) : null;
        }

        return self::timeChart('cost-per-trip', 'Cost per trip', 'Monthly claims divided by trips', $span, 'line', 'rwf', $months, [['Cost per trip', $perTrip]]);
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
            self::donut('my-trips-status', 'My trips', 'Trips assigned to you by status', 4, self::TRIP_STATUSES, $counts, 'trips'),
            self::tripsWeekly(8, 'My trips per week', 'Your trips departing each week, last 8 weeks', $driverId),
            self::deliveriesByStatus(6, $driverId),
            self::licenceValidity(6, $driver['license_expiry']),
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

    private static function driverMetrics(?int $userId): array
    {
        $driver = self::row('SELECT id, license_expiry FROM drivers WHERE deleted_at IS NULL AND user_id = ?', [$userId ?? 0]);
        $driverId = (int) ($driver['id'] ?? 0);
        $card = static fn (string $label, string|int $value, string $caption, string $icon): array => ['label' => $label, 'value' => (string) $value, 'trend' => $caption, 'icon' => $icon];
        $days = isset($driver['license_expiry']) ? (int) floor((strtotime($driver['license_expiry']) - strtotime(date('Y-m-d'))) / 86400) : null;

        return [
            $card('Assigned trips', (int) self::scalar("SELECT COUNT(*) FROM trips WHERE deleted_at IS NULL AND driver_id = ? AND status IN ('requested','approved','loading','in_transit')", [$driverId]), self::scalar('SELECT COUNT(*) FROM trips WHERE deleted_at IS NULL AND driver_id = ? AND DATE(departure_at) = CURDATE()', [$driverId]) . ' today', 'navigation'),
            $card('Deliveries pending', (int) self::scalar("SELECT COUNT(*) FROM deliveries d INNER JOIN trips t ON t.id = d.trip_id WHERE d.deleted_at IS NULL AND t.driver_id = ? AND d.status IN ('loading','in_transit')", [$driverId]), self::scalar("SELECT COUNT(*) FROM deliveries d INNER JOIN trips t ON t.id = d.trip_id WHERE d.deleted_at IS NULL AND t.driver_id = ? AND d.status = 'in_transit'", [$driverId]) . ' in transit', 'package'),
            $card('Completed trips', (int) self::scalar("SELECT COUNT(*) FROM trips WHERE deleted_at IS NULL AND driver_id = ? AND status = 'delivered'", [$driverId]), self::scalar("SELECT COUNT(*) FROM trips WHERE deleted_at IS NULL AND driver_id = ? AND status = 'delivered' AND departure_at >= ?", [$driverId, date('Y-m-01')]) . ' this month', 'check'),
            $card('Licence valid for', $days === null ? 'n/a' : ($days < 0 ? 'Expired' : $days . ' days'), $driver['license_expiry'] ?? 'No expiry date recorded', 'shield'),
        ];
    }

    /** @return list<array{tone: string, title: string, text: string}> */
    private static function driverAttention(?int $userId): array
    {
        $driver = self::row('SELECT id, license_expiry FROM drivers WHERE deleted_at IS NULL AND user_id = ?', [$userId ?? 0]);
        if ($driver === null) {
            return [['tone' => 'warning', 'title' => 'No driver profile', 'text' => 'Ask an administrator to link your login to a driver profile.']];
        }

        $active = (int) self::scalar("SELECT COUNT(*) FROM trips WHERE deleted_at IS NULL AND driver_id = ? AND status IN ('loading','in_transit')", [$driver['id']]);
        $proof = (int) self::scalar("SELECT COUNT(*) FROM deliveries d INNER JOIN trips t ON t.id = d.trip_id WHERE d.deleted_at IS NULL AND t.driver_id = ? AND d.status = 'delivered' AND d.proof_file IS NULL", [$driver['id']]);
        $days = $driver['license_expiry'] !== null ? (int) floor((strtotime($driver['license_expiry']) - strtotime(date('Y-m-d'))) / 86400) : null;

        return [
            ['tone' => $active > 0 ? 'primary' : 'success', 'title' => 'Trips in progress', 'text' => $active > 0 ? "You have {$active} trip(s) loading or on the road." : 'No trips in progress right now.'],
            ['tone' => $proof > 0 ? 'warning' : 'success', 'title' => 'Proof of delivery', 'text' => $proof > 0 ? "{$proof} delivered trip(s) still need proof uploaded." : 'All your deliveries have proof attached.'],
            ['tone' => $days !== null && $days <= 90 ? 'warning' : 'success', 'title' => 'Driving licence', 'text' => $days === null ? 'No expiry date recorded.' : ($days < 0 ? 'Your licence has expired.' : "Your licence expires in {$days} days.")],
        ];
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
        $chart['series'] = [['name' => ucfirst($unit), 'data' => array_map(static fn (string $key): int => (int) ($counts[$key] ?? 0), array_keys($statuses))]];
        $chart['slots'] = $slots ?? range(0, count($statuses) - 1);

        return $chart;
    }

    private static function bars(string $id, string $title, string $subtitle, int $span, string $type, array $statuses, array $counts, string $seriesName): array
    {
        $chart = self::baseChart($id, $title, $subtitle, $span, $type, 'int');
        $chart['categories'] = array_values($statuses);
        $chart['series'] = [['name' => $seriesName, 'data' => array_map(static fn (string $key): int => (int) ($counts[$key] ?? 0), array_keys($statuses))]];
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

    /** Counts rows per enum value for a whitelisted table/column pair. @return array<string, int> */
    private static function countBy(string $table, string $column): array
    {
        $allowed = ['vehicles' => 'status', 'trips' => 'status', 'transport_requests' => 'status', 'deliveries' => 'status', 'inventory_items' => 'status', 'purchase_requests' => 'status'];
        if (($allowed[$table] ?? null) !== $column) {
            throw new \InvalidArgumentException('Unsupported count target.');
        }

        return self::keyed2(self::rows("SELECT {$column} k, COUNT(*) v FROM {$table} GROUP BY {$column}"));
    }

    private static function licencesExpiringCount(int $days): int
    {
        return (int) self::scalar('SELECT COUNT(*) FROM drivers WHERE deleted_at IS NULL AND license_expiry IS NOT NULL AND license_expiry <= DATE_ADD(?, INTERVAL ? DAY)', [date('Y-m-d'), $days]);
    }

    private static function costPerTripThisMonth(string $monthStart, float $monthExpenses): string
    {
        $trips = (int) self::scalar("SELECT COUNT(*) FROM trips WHERE deleted_at IS NULL AND status <> 'cancelled' AND departure_at >= ?", [$monthStart]);

        return $trips === 0 ? 'n/a' : self::rwf($monthExpenses / $trips);
    }

    private static function percent(int $part, int $whole): string
    {
        return $whole === 0 ? '0%' : round($part / $whole * 100) . '%';
    }

    private static function rwf(float $amount): string
    {
        return 'RWF ' . match (true) {
            $amount >= 1_000_000 => rtrim(rtrim(number_format($amount / 1_000_000, 1), '0'), '.') . 'M',
            $amount >= 1_000 => rtrim(rtrim(number_format($amount / 1_000, 1), '0'), '.') . 'K',
            default => number_format($amount),
        };
    }

    private static function number(float $value): string
    {
        return number_format($value);
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
