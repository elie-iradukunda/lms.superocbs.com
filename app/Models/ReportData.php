<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * The eight reports the sidebar promises, each backed by a real query over the
 * operational tables instead of a row in the `reports` catalogue.
 *
 * Every report returns the same shape so one view and one CSV exporter serve
 * them all: columns (key => label + type) and rows (list of associative arrays).
 *
 * Distance-based measures (kilometres travelled, litres per 100 km, cost per km)
 * are deliberately left out until route distance and GPS are added.
 */
final class ReportData
{
    public const CATALOGUE_LABELS = [
        'vehicle_utilization' => 'Vehicle utilization',
        'fuel_consumption' => 'Fuel consumption',
        'delivery_performance' => 'Delivery performance',
        'maintenance_cost' => 'Maintenance cost',
        'driver_performance' => 'Driver performance',
        'inventory_movement' => 'Inventory movement',
        'trip_profitability' => 'Trip profitability',
        'expense_summary' => 'Expense summary',
        'ar_aging' => 'Customer balances (AR aging)',
        'ap_aging' => 'What we owe (AP aging)',
    ];

    private const PERMISSIONS = [
        'vehicle_utilization' => 'vehicles',
        'fuel_consumption' => 'fuel',
        'delivery_performance' => 'deliveries',
        'maintenance_cost' => 'maintenance',
        'driver_performance' => 'drivers',
        'inventory_movement' => 'warehouse',
        'trip_profitability' => 'trips',
        'expense_summary' => 'expenses',
        'ar_aging' => 'invoices',
        'ap_aging' => 'supplier_bills',
    ];

    private const DESCRIPTIONS = [
        'vehicle_utilization' => 'How many trips each vehicle ran, how many finished, and how much of the fleet time it used.',
        'fuel_consumption' => 'Litres and money spent per vehicle, with the number of fill-ups and the average price paid.',
        'delivery_performance' => 'Deliveries made, failed and re-attempted per month, with the on-time rate against the planned arrival.',
        'maintenance_cost' => 'Estimated against actual maintenance cost per vehicle, and how far the estimate was out.',
        'driver_performance' => 'Trips completed, deliveries made, failures and on-time rate per driver.',
        'inventory_movement' => 'Stock in, stock out and closing balance per item over the period.',
        'trip_profitability' => 'Invoiced revenue, net of VAT, against trip cost (expenses plus fuel) for each trip, and the resulting margin.',
        'expense_summary' => 'Expense totals per category with approved, pending and rejected value.',
        'ar_aging' => 'What each customer still owes on issued invoices, by how late it is: not yet due, 1-30, 31-60, 61-90 and over 90 days past the due date, as at the end date. Each currency on its own row.',
        'ap_aging' => 'What we still owe each supplier on approved bills, by how late it is, as at the end date. Pay the oldest first. Each currency on its own row.',
    ];

    public static function keys(): array
    {
        return array_keys(self::CATALOGUE_LABELS);
    }

    public static function exists(string $key): bool
    {
        return isset(self::CATALOGUE_LABELS[$key]);
    }

    public static function label(string $key): string
    {
        return self::CATALOGUE_LABELS[$key] ?? $key;
    }

    public static function description(string $key): string
    {
        return self::DESCRIPTIONS[$key] ?? '';
    }

    public static function permission(string $key): string
    {
        return self::PERMISSIONS[$key] ?? 'reports';
    }

    /** Reports the signed-in role is allowed to open. */
    public static function availableFor(string $roleKey): array
    {
        $available = [];
        foreach (self::CATALOGUE_LABELS as $key => $label) {
            if (Permission::allows($roleKey, self::permission($key), 'view') || Permission::allows($roleKey, 'reports', 'view')) {
                $available[$key] = $label;
            }
        }

        return $available;
    }

    /**
     * Runs one report.
     *
     * @return array{key: string, label: string, description: string, columns: array<string, array{label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>, from: string, to: string}
     */
    public static function run(string $key, ?string $from = null, ?string $to = null, ?string $currency = null): array
    {
        if (!self::exists($key)) {
            throw new \InvalidArgumentException("Unknown report: {$key}");
        }

        $from = self::date($from, date('Y-m-01', strtotime('-5 months')));
        $to = self::date($to, date('Y-m-d'));
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $report = match ($key) {
            'vehicle_utilization' => self::vehicleUtilization($from, $to),
            'fuel_consumption' => self::fuelConsumption($from, $to),
            'delivery_performance' => self::deliveryPerformance($from, $to),
            'maintenance_cost' => self::maintenanceCost($from, $to),
            'driver_performance' => self::driverPerformance($from, $to),
            'inventory_movement' => self::inventoryMovement($from, $to),
            'trip_profitability' => self::tripProfitability($from, $to),
            'expense_summary' => self::expenseSummary($from, $to),
            'ar_aging' => self::aging('receivable', $to),
            'ap_aging' => self::aging('payable', $to),
        };

        self::touchCatalogue($key);

        // Money is never added across currencies: a report with money carries a
        // currency on every row, can be narrowed to one currency, and is totalled
        // per currency.
        $report['currencies'] = [];
        $report['currency'] = '';
        $report['money_totals'] = [];
        if (isset($report['columns']['currency'])) {
            $base = Currency::base();
            $present = array_values(array_unique(array_filter(array_map(static fn (array $row): string => (string) ($row['currency'] ?? ''), $report['rows']))));
            usort($present, static fn (string $a, string $b): int => [$a !== $base, $a] <=> [$b !== $base, $b]);
            $report['currencies'] = $present;

            $wanted = strtoupper(trim((string) $currency));
            if ($wanted !== '' && in_array($wanted, $present, true)) {
                $report['currency'] = $wanted;
                $report['rows'] = array_values(array_filter($report['rows'], static fn (array $row): bool => (string) ($row['currency'] ?? '') === $wanted));
            }

            foreach ($report['rows'] as $row) {
                $code = (string) ($row['currency'] ?? $base);
                foreach ($report['money_sum'] ?? [] as $column) {
                    $report['money_totals'][$code][$column] = ($report['money_totals'][$code][$column] ?? 0.0) + (float) ($row[$column] ?? 0);
                }
            }
            uksort($report['money_totals'], static fn (string $a, string $b): int => [$a !== $base, $a] <=> [$b !== $base, $b]);

            $report['totals'] = [];
            foreach ($report['count_sum'] ?? [] as $column) {
                $report['totals'][$column] = array_sum(array_map('floatval', array_column($report['rows'], $column)));
            }
        }

        return $report + [
            'key' => $key,
            'label' => self::label($key),
            'description' => self::description($key),
            'from' => $from,
            'to' => $to,
            'totals' => $report['totals'] ?? [],
        ];
    }

    // -------------------------------------------------------------- reports

    private static function vehicleUtilization(string $from, string $to): array
    {
        $rows = self::rows(
            "SELECT v.plate_number,
                    v.vehicle_type,
                    COALESCE(v.make, '') AS make,
                    v.status,
                    COUNT(t.id) AS trips,
                    SUM(CASE WHEN t.status = 'delivered' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN t.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                    COALESCE(SUM(TIMESTAMPDIFF(HOUR, t.departure_at, COALESCE(t.arrival_at, t.departure_at))), 0) AS hours_on_road
             FROM vehicles v
             LEFT JOIN trips t
                    ON t.vehicle_id = v.id
                   AND t.deleted_at IS NULL
                   AND DATE(COALESCE(t.departure_at, t.created_at)) BETWEEN ? AND ?
             WHERE v.deleted_at IS NULL
             GROUP BY v.id
             ORDER BY trips DESC, v.plate_number",
            [$from, $to]
        );

        $totalTrips = array_sum(array_column($rows, 'trips'));
        foreach ($rows as &$row) {
            $row['share'] = $totalTrips > 0 ? round(((int) $row['trips'] / $totalTrips) * 100, 1) : 0.0;
            $row['completion'] = (int) $row['trips'] > 0 ? round(((int) $row['completed'] / (int) $row['trips']) * 100, 1) : 0.0;
        }
        unset($row);

        return [
            'columns' => [
                'plate_number' => ['label' => 'Vehicle', 'type' => 'code'],
                'vehicle_type' => ['label' => 'Type', 'type' => 'text'],
                'status' => ['label' => 'Current status', 'type' => 'badge'],
                'trips' => ['label' => 'Trips', 'type' => 'number'],
                'completed' => ['label' => 'Completed', 'type' => 'number'],
                'cancelled' => ['label' => 'Cancelled', 'type' => 'number'],
                'hours_on_road' => ['label' => 'Hours on road', 'type' => 'number'],
                'completion' => ['label' => 'Completion', 'type' => 'percent'],
                'share' => ['label' => 'Share of trips', 'type' => 'percent'],
            ],
            'rows' => $rows,
            'totals' => ['trips' => $totalTrips, 'completed' => array_sum(array_column($rows, 'completed'))],
        ];
    }

    private static function fuelConsumption(string $from, string $to): array
    {
        $rows = self::rows(
            "SELECT v.plate_number,
                    v.vehicle_type,
                    v.fuel_type,
                    f.currency,
                    COUNT(f.id) AS fill_ups,
                    COALESCE(SUM(f.litres), 0) AS litres,
                    COALESCE(SUM(f.litres * f.unit_price), 0) AS total_cost,
                    COALESCE(AVG(f.unit_price), 0) AS average_price,
                    MAX(f.purchased_at) AS last_fill
             FROM vehicles v
             INNER JOIN fuel_records f
                    ON f.vehicle_id = v.id
                   AND f.deleted_at IS NULL
                   AND DATE(f.purchased_at) BETWEEN ? AND ?
             WHERE v.deleted_at IS NULL
             GROUP BY v.id, f.currency
             ORDER BY v.plate_number, f.currency",
            [$from, $to]
        );

        return [
            'columns' => [
                'plate_number' => ['label' => 'Vehicle', 'type' => 'code'],
                'vehicle_type' => ['label' => 'Type', 'type' => 'text'],
                'fuel_type' => ['label' => 'Fuel', 'type' => 'label'],
                'currency' => ['label' => 'Cur.', 'type' => 'text'],
                'fill_ups' => ['label' => 'Fill-ups', 'type' => 'number'],
                'litres' => ['label' => 'Litres', 'type' => 'decimal'],
                'average_price' => ['label' => 'Average price / L', 'type' => 'money'],
                'total_cost' => ['label' => 'Total cost', 'type' => 'money'],
                'last_fill' => ['label' => 'Last fill-up', 'type' => 'datetime'],
            ],
            'rows' => $rows,
            'money_sum' => ['total_cost'],
            'count_sum' => ['fill_ups', 'litres'],
        ];
    }

    private static function deliveryPerformance(string $from, string $to): array
    {
        $grace = Settings::int('on_time_grace_minutes', 30);

        $rows = self::rows(
            "SELECT DATE_FORMAT(COALESCE(d.delivered_at, d.planned_at, d.created_at), '%Y-%m') AS period,
                    COUNT(*) AS total,
                    SUM(CASE WHEN d.status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
                    SUM(CASE WHEN d.status = 'failed' THEN 1 ELSE 0 END) AS failed,
                    SUM(CASE WHEN d.attempt_number > 1 THEN 1 ELSE 0 END) AS re_attempts,
                    SUM(CASE
                            WHEN d.status = 'delivered'
                             AND t.planned_arrival_at IS NOT NULL
                             AND d.delivered_at IS NOT NULL
                             AND d.delivered_at <= DATE_ADD(t.planned_arrival_at, INTERVAL ? MINUTE)
                            THEN 1 ELSE 0 END) AS on_time,
                    SUM(CASE
                            WHEN d.status = 'delivered'
                             AND t.planned_arrival_at IS NOT NULL
                             AND d.delivered_at IS NOT NULL
                            THEN 1 ELSE 0 END) AS measurable
             FROM deliveries d
             LEFT JOIN trips t ON t.id = d.trip_id
             WHERE d.deleted_at IS NULL
               AND DATE(COALESCE(d.delivered_at, d.planned_at, d.created_at)) BETWEEN ? AND ?
             GROUP BY period
             ORDER BY period",
            [$grace, $from, $to]
        );

        foreach ($rows as &$row) {
            $row['success_rate'] = (int) $row['total'] > 0 ? round(((int) $row['delivered'] / (int) $row['total']) * 100, 1) : 0.0;
            $row['on_time_rate'] = (int) $row['measurable'] > 0 ? round(((int) $row['on_time'] / (int) $row['measurable']) * 100, 1) : null;
        }
        unset($row);

        return [
            'columns' => [
                'period' => ['label' => 'Month', 'type' => 'text'],
                'total' => ['label' => 'Deliveries', 'type' => 'number'],
                'delivered' => ['label' => 'Delivered', 'type' => 'number'],
                'failed' => ['label' => 'Failed', 'type' => 'number'],
                're_attempts' => ['label' => 'Re-attempts', 'type' => 'number'],
                'success_rate' => ['label' => 'Success rate', 'type' => 'percent'],
                'measurable' => ['label' => 'With planned ETA', 'type' => 'number'],
                'on_time_rate' => ['label' => 'On-time rate', 'type' => 'percent'],
            ],
            'rows' => $rows,
            'totals' => ['total' => array_sum(array_column($rows, 'total')), 'delivered' => array_sum(array_column($rows, 'delivered'))],
        ];
    }

    private static function maintenanceCost(string $from, string $to): array
    {
        $rows = self::rows(
            "SELECT v.plate_number,
                    v.vehicle_type,
                    COUNT(m.id) AS work_orders,
                    SUM(CASE WHEN m.status = 'completed' THEN 1 ELSE 0 END) AS completed,
                    COALESCE(SUM(m.estimated_cost), 0) AS estimated,
                    COALESCE(SUM(COALESCE(m.actual_cost, 0)), 0) AS actual
             FROM vehicles v
             INNER JOIN maintenance_orders m
                     ON m.vehicle_id = v.id
                    AND m.deleted_at IS NULL
                    AND COALESCE(DATE(m.completed_at), m.due_date) BETWEEN ? AND ?
             WHERE v.deleted_at IS NULL
             GROUP BY v.id
             ORDER BY actual DESC, estimated DESC",
            [$from, $to]
        );

        foreach ($rows as &$row) {
            $estimated = (float) $row['estimated'];
            $row['variance'] = (float) $row['actual'] - $estimated;
            $row['variance_pct'] = $estimated > 0 ? round(($row['variance'] / $estimated) * 100, 1) : null;
            // Work orders are costed in the books' currency.
            $row['currency'] = Currency::base();
        }
        unset($row);

        return [
            'money_sum' => ['estimated', 'actual', 'variance'],
            'count_sum' => ['work_orders', 'completed'],
            'columns' => [
                'plate_number' => ['label' => 'Vehicle', 'type' => 'code'],
                'vehicle_type' => ['label' => 'Type', 'type' => 'text'],
                'currency' => ['label' => 'Cur.', 'type' => 'text'],
                'work_orders' => ['label' => 'Work orders', 'type' => 'number'],
                'completed' => ['label' => 'Completed', 'type' => 'number'],
                'estimated' => ['label' => 'Estimated', 'type' => 'money'],
                'actual' => ['label' => 'Actual', 'type' => 'money'],
                'variance' => ['label' => 'Variance', 'type' => 'money'],
                'variance_pct' => ['label' => 'Variance', 'type' => 'percent'],
            ],
            'rows' => $rows,
            'totals' => [
                'estimated' => array_sum(array_map('floatval', array_column($rows, 'estimated'))),
                'actual' => array_sum(array_map('floatval', array_column($rows, 'actual'))),
            ],
        ];
    }

    private static function driverPerformance(string $from, string $to): array
    {
        $grace = Settings::int('on_time_grace_minutes', 30);

        $rows = self::rows(
            "SELECT dr.full_name,
                    dr.license_number,
                    dr.status,
                    COUNT(DISTINCT t.id) AS trips,
                    SUM(CASE WHEN t.status = 'delivered' THEN 1 ELSE 0 END) AS completed,
                    COUNT(DISTINCT CASE WHEN d.status = 'delivered' THEN d.id END) AS deliveries,
                    COUNT(DISTINCT CASE WHEN d.status = 'failed' THEN d.id END) AS failures,
                    COUNT(DISTINCT CASE
                            WHEN d.status = 'delivered'
                             AND t.planned_arrival_at IS NOT NULL
                             AND d.delivered_at IS NOT NULL
                             AND d.delivered_at <= DATE_ADD(t.planned_arrival_at, INTERVAL ? MINUTE)
                            THEN d.id END) AS on_time,
                    COUNT(DISTINCT CASE
                            WHEN d.status = 'delivered'
                             AND t.planned_arrival_at IS NOT NULL
                             AND d.delivered_at IS NOT NULL
                            THEN d.id END) AS measurable
             FROM drivers dr
             INNER JOIN trips t
                     ON t.driver_id = dr.id
                    AND t.deleted_at IS NULL
                    AND DATE(COALESCE(t.departure_at, t.created_at)) BETWEEN ? AND ?
             LEFT JOIN deliveries d ON d.trip_id = t.id AND d.deleted_at IS NULL
             WHERE dr.deleted_at IS NULL
             GROUP BY dr.id
             ORDER BY completed DESC, trips DESC",
            [$grace, $from, $to]
        );

        foreach ($rows as &$row) {
            $row['completion'] = (int) $row['trips'] > 0 ? round(((int) $row['completed'] / (int) $row['trips']) * 100, 1) : 0.0;
            $row['on_time_rate'] = (int) $row['measurable'] > 0 ? round(((int) $row['on_time'] / (int) $row['measurable']) * 100, 1) : null;
        }
        unset($row);

        return [
            'columns' => [
                'full_name' => ['label' => 'Driver', 'type' => 'code'],
                'license_number' => ['label' => 'Licence', 'type' => 'text'],
                'status' => ['label' => 'Status', 'type' => 'badge'],
                'trips' => ['label' => 'Trips', 'type' => 'number'],
                'completed' => ['label' => 'Completed', 'type' => 'number'],
                'deliveries' => ['label' => 'Deliveries', 'type' => 'number'],
                'failures' => ['label' => 'Failed', 'type' => 'number'],
                'completion' => ['label' => 'Completion', 'type' => 'percent'],
                'on_time_rate' => ['label' => 'On-time rate', 'type' => 'percent'],
            ],
            'rows' => $rows,
            'totals' => ['trips' => array_sum(array_column($rows, 'trips')), 'deliveries' => array_sum(array_column($rows, 'deliveries'))],
        ];
    }

    private static function inventoryMovement(string $from, string $to): array
    {
        $rows = self::rows(
            "SELECT it.sku,
                    it.item_name,
                    w.warehouse_name,
                    it.unit_of_measure,
                    COALESCE(SUM(CASE WHEN sm.movement_type IN ('stock_in','transfer_in','return') THEN sm.quantity ELSE 0 END), 0) AS stock_in,
                    COALESCE(SUM(CASE WHEN sm.movement_type IN ('stock_out','transfer_out','damage') THEN sm.quantity ELSE 0 END), 0) AS stock_out,
                    COALESCE(SUM(CASE WHEN sm.movement_type = 'adjustment' THEN sm.quantity ELSE 0 END), 0) AS adjustments,
                    COUNT(sm.id) AS movements,
                    it.quantity AS closing_balance,
                    it.minimum_level,
                    it.status
             FROM inventory_items it
             INNER JOIN warehouses w ON w.id = it.warehouse_id
             LEFT JOIN stock_movements sm
                    ON sm.item_id = it.id
                   AND DATE(sm.moved_at) BETWEEN ? AND ?
             WHERE it.deleted_at IS NULL
             GROUP BY it.id
             ORDER BY movements DESC, it.item_name",
            [$from, $to]
        );

        foreach ($rows as &$row) {
            $row['net_change'] = (float) $row['stock_in'] - (float) $row['stock_out'] + (float) $row['adjustments'];
        }
        unset($row);

        return [
            'columns' => [
                'sku' => ['label' => 'SKU', 'type' => 'code'],
                'item_name' => ['label' => 'Item', 'type' => 'text'],
                'warehouse_name' => ['label' => 'Warehouse', 'type' => 'text'],
                'stock_in' => ['label' => 'In', 'type' => 'decimal'],
                'stock_out' => ['label' => 'Out', 'type' => 'decimal'],
                'adjustments' => ['label' => 'Adjustments', 'type' => 'decimal'],
                'net_change' => ['label' => 'Net change', 'type' => 'decimal'],
                'closing_balance' => ['label' => 'Closing balance', 'type' => 'decimal'],
                'minimum_level' => ['label' => 'Minimum', 'type' => 'decimal'],
                'status' => ['label' => 'Status', 'type' => 'badge'],
            ],
            'rows' => $rows,
            'totals' => ['movements' => array_sum(array_column($rows, 'movements'))],
        ];
    }

    private static function tripProfitability(string $from, string $to): array
    {
        $trips = self::rows(
            "SELECT t.id,
                    t.reference_code,
                    COALESCE(c.customer_name, 'Internal') AS customer,
                    t.destination,
                    t.status
             FROM trips t
             LEFT JOIN customers c ON c.id = t.customer_id
             WHERE t.deleted_at IS NULL
               AND DATE(COALESCE(t.departure_at, t.created_at)) BETWEEN ? AND ?
             ORDER BY t.id DESC",
            [$from, $to]
        );

        // Each source is summed per trip and per currency. Shillings, francs and
        // dollars stay apart, so a trip that earned USD and spent UGX shows both.
        $payables = self::tableExists('supplier_bills');
        $sources = [
            // Net of VAT and of costs recharged at cost: neither is the carrier's revenue.
            'revenue' => $payables
                ? "SELECT i.trip_id, i.currency, SUM(i.subtotal - COALESCE((SELECT SUM(l.line_total) FROM invoice_lines l WHERE l.invoice_id = i.id AND l.line_kind = 'disbursement'), 0)) AS amount
                     FROM invoices i
                    WHERE i.deleted_at IS NULL AND i.status <> 'cancelled' AND i.trip_id IS NOT NULL
                    GROUP BY i.trip_id, i.currency"
                : "SELECT trip_id, currency, SUM(subtotal) AS amount FROM invoices
                    WHERE deleted_at IS NULL AND status <> 'cancelled' AND trip_id IS NOT NULL
                    GROUP BY trip_id, currency",
            'fuel_cost' => "SELECT trip_id, currency, SUM(litres * unit_price) AS amount FROM fuel_records
                    WHERE deleted_at IS NULL AND trip_id IS NOT NULL
                    GROUP BY trip_id, currency",
            'expense_cost' => "SELECT trip_id, currency, SUM(amount) AS amount FROM expenses
                    WHERE deleted_at IS NULL AND status <> 'rejected' AND trip_id IS NOT NULL
                    GROUP BY trip_id, currency",
        ];
        if ($payables) {
            $sources['bill_cost'] = "SELECT trip_id, currency, SUM(subtotal) AS amount FROM supplier_bills
                    WHERE deleted_at IS NULL AND status <> 'cancelled' AND trip_id IS NOT NULL
                    GROUP BY trip_id, currency";
        }

        $money = [];
        foreach ($sources as $field => $sql) {
            foreach (self::rows($sql, []) as $line) {
                $code = (string) ($line['currency'] ?: Currency::base());
                $money[(int) $line['trip_id']][$code][$field] = (float) $line['amount'];
            }
        }

        $base = Currency::base();
        $rows = [];
        foreach ($trips as $trip) {
            $byCurrency = $money[(int) $trip['id']] ?? [$base => []];
            uksort($byCurrency, static fn (string $a, string $b): int => [$a !== $base, $a] <=> [$b !== $base, $b]);
            foreach ($byCurrency as $code => $amounts) {
                $revenue = $amounts['revenue'] ?? 0.0;
                $fuel = $amounts['fuel_cost'] ?? 0.0;
                $claims = ($amounts['expense_cost'] ?? 0.0) + ($amounts['bill_cost'] ?? 0.0);
                $cost = $fuel + $claims;
                $rows[] = [
                    'reference_code' => $trip['reference_code'],
                    'customer' => $trip['customer'],
                    'destination' => $trip['destination'],
                    'status' => $trip['status'],
                    'currency' => $code,
                    'revenue' => $revenue,
                    'fuel_cost' => $fuel,
                    'expense_cost' => $claims,
                    'total_cost' => $cost,
                    'margin' => $revenue - $cost,
                    'margin_pct' => $revenue > 0 ? round((($revenue - $cost) / $revenue) * 100, 1) : null,
                ];
            }
        }

        return [
            'columns' => [
                'reference_code' => ['label' => 'Trip', 'type' => 'code'],
                'customer' => ['label' => 'Customer', 'type' => 'text'],
                'destination' => ['label' => 'To', 'type' => 'text'],
                'status' => ['label' => 'Status', 'type' => 'badge'],
                'currency' => ['label' => 'Cur.', 'type' => 'text'],
                'revenue' => ['label' => 'Revenue', 'type' => 'money'],
                'fuel_cost' => ['label' => 'Fuel', 'type' => 'money'],
                'expense_cost' => ['label' => 'Claims and bills', 'type' => 'money'],
                'total_cost' => ['label' => 'Total cost', 'type' => 'money'],
                'margin' => ['label' => 'Margin', 'type' => 'money'],
                'margin_pct' => ['label' => 'Margin %', 'type' => 'percent'],
            ],
            'rows' => $rows,
            'money_sum' => ['revenue', 'fuel_cost', 'expense_cost', 'total_cost', 'margin'],
        ];
    }

    private static function expenseSummary(string $from, string $to): array
    {
        $rows = self::rows(
            "SELECT e.category,
                    e.currency,
                    COUNT(*) AS claims,
                    COALESCE(SUM(e.amount), 0) AS total,
                    COALESCE(SUM(CASE WHEN e.status = 'approved' THEN e.amount ELSE 0 END), 0) AS approved,
                    COALESCE(SUM(CASE WHEN e.status = 'pending' THEN e.amount ELSE 0 END), 0) AS pending,
                    COALESCE(SUM(CASE WHEN e.status = 'rejected' THEN e.amount ELSE 0 END), 0) AS rejected,
                    COALESCE(AVG(e.amount), 0) AS average
             FROM expenses e
             WHERE e.deleted_at IS NULL
               AND e.expense_date BETWEEN ? AND ?
             GROUP BY e.category, e.currency
             ORDER BY e.category, e.currency",
            [$from, $to]
        );

        return [
            'money_sum' => ['total', 'approved', 'pending', 'rejected'],
            'count_sum' => ['claims'],
            'columns' => [
                'category' => ['label' => 'Category', 'type' => 'code'],
                'currency' => ['label' => 'Cur.', 'type' => 'text'],
                'claims' => ['label' => 'Claims', 'type' => 'number'],
                'total' => ['label' => 'Total', 'type' => 'money'],
                'approved' => ['label' => 'Approved', 'type' => 'money'],
                'pending' => ['label' => 'Pending', 'type' => 'money'],
                'rejected' => ['label' => 'Rejected', 'type' => 'money'],
                'average' => ['label' => 'Average claim', 'type' => 'money'],
            ],
            'rows' => $rows,
            'totals' => [
                'total' => array_sum(array_map('floatval', array_column($rows, 'total'))),
                'approved' => array_sum(array_map('floatval', array_column($rows, 'approved'))),
            ],
        ];
    }

    // -------------------------------------------------------------- helpers

    /** Open balances as at the end of the period, in late-ness buckets. */
    private static function aging(string $side, string $asOf): array
    {
        $rows = self::tableExists($side === 'payable' ? 'supplier_bills' : 'invoices') ? Payables::aging($side, $asOf) : [];

        return [
            // Totalled per currency in run(), never across them.
            'money_sum' => ['current', 'd30', 'd60', 'd90', 'd90plus', 'total'],
            'columns' => [
                'party' => ['label' => $side === 'payable' ? 'Supplier' : 'Customer', 'type' => 'text'],
                'currency' => ['label' => 'Cur.', 'type' => 'text'],
                'current' => ['label' => 'Not yet due', 'type' => 'money'],
                'd30' => ['label' => '1-30 days', 'type' => 'money'],
                'd60' => ['label' => '31-60 days', 'type' => 'money'],
                'd90' => ['label' => '61-90 days', 'type' => 'money'],
                'd90plus' => ['label' => 'Over 90 days', 'type' => 'money'],
                'total' => ['label' => 'Total owed', 'type' => 'money'],
            ],
            'rows' => $rows,
            'totals' => [],
        ];
    }

    private static function tableExists(string $table): bool
    {
        static $known = [];
        if (!isset($known[$table])) {
            try {
                Database::connection()->query("SELECT 1 FROM {$table} LIMIT 0");
                $known[$table] = true;
            } catch (\Throwable) {
                $known[$table] = false;
            }
        }

        return $known[$table];
    }

    private static function rows(string $sql, array $parameters = []): array
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function date(?string $value, string $fallback): string
    {
        $value = trim((string) $value);
        $time = $value === '' ? false : strtotime($value);

        return $time === false ? $fallback : date('Y-m-d', $time);
    }

    /** Keeps the catalogue row's "last run" column honest. */
    private static function touchCatalogue(string $key): void
    {
        try {
            $statement = Database::connection()->prepare('UPDATE reports SET last_generated_at = NOW() WHERE report_key = ?');
            $statement->execute([$key]);
        } catch (\Throwable) {
            // A missing catalogue row must never stop the report from rendering.
        }
    }
}
