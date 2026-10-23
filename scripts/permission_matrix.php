<?php

declare(strict_types=1);

/**
 * Writes database/migrations/99991231_000000_eri_permission_matrix_<hash>.sql: the
 * default permissions of the ERI specification (section 21) for every role.
 * Admin adjusts them afterwards on Administration > Role permissions; this is
 * only where a fresh installation starts.
 *
 * Abilities: V view, C create, E edit, D delete, A approve.
 *
 *     php scripts/permission_matrix.php
 */

$view = 'V';
$work = 'VCE';
$full = 'VCED';
$sign = 'VCEA';

$everything = [
    'dashboard', 'approvals', 'form_requests', 'payment_batches', 'reports', 'tracking',
    'vehicles', 'trailers', 'vehicle_documents', 'drivers', 'driver_documents', 'driver_momo_changes', 'maintenance',
    'routes', 'route_rates', 'service_rates', 'trip_planner', 'trips', 'fuel_orders', 'vehicle_handovers', 'support_requests',
    'deliveries', 'customers', 'rates', 'invoices', 'payments', 'suppliers', 'supplier_bills', 'supplier_payments',
    'warehouses', 'warehouse', 'movements', 'procurement', 'books', 'journal', 'accounts', 'ledger_mappings', 'budgets',
    'bank_statements', 'currencies', 'payment_methods', 'cheques', 'cheque_books', 'expenses', 'fuel', 'disbursements',
    'cargo_claims', 'crossings', 'clearance_documents', 'clearance_inspections', 'clearance_payments', 'clearance_releases',
    'containers', 'border_posts', 'shipments', 'shipment_documents', 'freight_jobs', 'journeys', 'requests', 'price_offers',
    'audit', 'garage', 'store_issues', 'goods_receipts', 'stock_counts', 'tyre_logs', 'crane_logs', 'billing', 'shortage_rules', 'payroll', 'intercompany',
];

// Each company has its own full set of staff (OM, Finance, MD, Cashier, Store
// & garage, Driver, Clearing agent). Only Admin sees across companies; every
// other role is scoped to the company its user belongs to, so ERI Uganda's
// people never see ERI Rwanda's data. Admin assigns or removes any of these on
// Administration > Role permissions.
$roles = [
    // The Managing Director gives the final authorization and reads every
    // report for their company; no all_companies, so each company's MD sees
    // only their own. The dashboard shows them approvals, money and reports.
    'management' => array_fill_keys($everything, $view) + [
        'form_requests' => 'VA', 'invoices' => 'VA', 'journal' => 'VA', 'supplier_bills' => 'VA',
        'trips' => 'VA', 'support_requests' => 'VA', 'opening_balances' => 'VA', 'currency_rates' => $view, 'intercompany' => $view,
    ],

    // The Operational Manager runs every operation: trucks, trailers, drivers
    // (hiring and ending them too), routes, trips, requests, deliveries, fuel,
    // driver support, and raising sister-company requests.
    'logistics_manager' => [
        'dashboard' => $view, 'approvals' => $view, 'reports' => $view, 'tracking' => $view,
        'vehicles' => $work, 'trailers' => $work, 'vehicle_documents' => $full, 'drivers' => $work, 'driver_documents' => $full, 'document_types' => $work,
        'driver_momo_changes' => $sign, 'maintenance' => $view, 'garage' => $view, 'tyre_logs' => $view, 'crane_logs' => $work, 'store_issues' => $view, 'goods_receipts' => $view, 'stock_counts' => $view, 'warehouse' => $view,
        'routes' => $view, 'route_rates' => $view, 'service_rates' => $view, 'payroll' => $view,
        'trip_planner' => $sign, 'trips' => $sign, 'fuel_orders' => $sign, 'vehicle_handovers' => $sign, 'support_requests' => $sign,
        'deliveries' => $sign, 'form_requests' => $work, 'customers' => $work, 'suppliers' => 'VC',
        'procurement' => $work, 'containers' => $work, 'crossings' => $view, 'border_posts' => $view, 'fuel' => $view,
        'shipments' => $work, 'requests' => $sign, 'journeys' => $view, 'freight_jobs' => $view,
        'price_offers' => $work, 'rates' => $view, 'payment_batches' => $view, 'shortage_rules' => $view,
    ],

    // Finance verifies money requests (the books post), keeps the accounting,
    // raises customer invoices, runs the payroll and closes the month.
    'finance' => [
        'dashboard' => $view, 'approvals' => $view, 'reports' => $view, 'form_requests' => $work, 'payment_batches' => $view,
        'books' => $view, 'journal' => $sign, 'accounts' => $work, 'ledger_mappings' => $full, 'budgets' => $full,
        'bank_statements' => $full, 'currencies' => $work, 'currency_rates' => $full, 'opening_balances' => $work, 'payment_methods' => $work, 'cheques' => $sign, 'cheque_books' => $work,
        'invoices' => 'VCEDA', 'payments' => $work, 'customers' => $work, 'rates' => $work, 'suppliers' => $work,
        'supplier_bills' => $sign, 'supplier_payments' => $work, 'expenses' => 'VA', 'disbursements' => $work, 'cargo_claims' => $view,
        'trips' => $view, 'vehicles' => $view, 'drivers' => $view, 'driver_documents' => $view, 'fuel' => $view, 'fuel_orders' => $view, 'route_rates' => $view,
        'service_rates' => $view, 'routes' => $view, 'maintenance' => $view, 'deliveries' => $view, 'procurement' => $view,
        'crossings' => $view, 'clearance_payments' => $view, 'support_requests' => $view,
        'stock_counts' => 'VA', 'goods_receipts' => $view, 'tyre_logs' => $view, 'crane_logs' => $view, 'garage' => $view, 'warehouse' => $view,
        'billing' => $work, 'shortage_rules' => $full, 'payroll' => 'VCEA', 'intercompany' => $view,
    ],

    // The Cashier pays authorized requests and records the batch and proof,
    // and reads the accounting and finance.
    'cashier' => [
        'dashboard' => $view, 'approvals' => $view, 'form_requests' => $view, 'payment_batches' => 'VCEA', 'books' => $view,
        'journal' => $view, 'accounts' => $view, 'reports' => $view, 'invoices' => $view, 'supplier_bills' => $view, 'supplier_payments' => $view,
        'payments' => $view, 'payment_methods' => $view, 'bank_statements' => $view, 'currency_rates' => $view, 'intercompany' => $view,
    ],

    // The Store & garage keeper issues parts, receives deliveries, counts
    // stock, and keeps the job cards (the old mechanic role is part of this).
    'warehouse_manager' => [
        'dashboard' => $view, 'approvals' => $view, 'form_requests' => 'VC', 'warehouses' => $view, 'warehouse' => $work,
        'movements' => $work, 'procurement' => $work, 'suppliers' => $view, 'reports' => $view,
        'store_issues' => $work, 'goods_receipts' => 'VC', 'stock_counts' => $work,
        'garage' => $work, 'maintenance' => 'VE', 'tyre_logs' => $work, 'vehicles' => $view, 'trailers' => $view, 'support_requests' => $view,
    ],

    // The driver works from the app; lists are limited to their own work, and
    // they raise support requests to the OM.
    'driver' => [
        'dashboard' => $view, 'trips' => $view, 'deliveries' => 'VE', 'support_requests' => 'VC', 'driver_documents' => $view,
        'vehicle_handovers' => 'VC', 'form_requests' => 'VC', 'vehicles' => $view,
    ],

    // The clearing agent (Declaration Manager) does the whole clearance file
    // before a truck leaves for another country.
    'clearance_officer' => [
        'dashboard' => $view, 'approvals' => $view, 'reports' => $view, 'crossings' => $sign, 'border_posts' => $view,
        'clearance_documents' => $work, 'clearance_inspections' => $work, 'clearance_payments' => $work, 'clearance_releases' => $work,
        'containers' => $work, 'shipments' => $work, 'shipment_documents' => $work, 'customers' => 'VC', 'trips' => $view,
        'form_requests' => 'VC', 'documents' => $view,
    ],

    // Supplier and customer logins work only in their own portal.
    'supplier' => ['dashboard' => $view, 'portal' => $view],
    'customer' => ['dashboard' => $view, 'portal' => $view],
];

$sql = "-- The default permissions of the ERI specification (section 21), written by\n"
    . "-- scripts/permission_matrix.php. Admin adjusts them under Administration >\n"
    . "-- Role permissions. Admin itself keeps every page but signs nothing: the\n"
    . "-- approval chain is held by the Finance Manager, the MD and the Cashier.\n\n";

$sql .= "-- Admin: every page.\n"
    . "INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)\n"
    . "SELECT r.id, p.permission_key, 1, 1, 1, 1, 1 FROM roles r CROSS JOIN permissions p WHERE r.role_key = 'super_admin'\n"
    . "ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;\n\n";

foreach ($roles as $role => $grants) {
    $sql .= "-- {$role}\n";
    $sql .= "DELETE rp FROM role_permissions rp INNER JOIN roles r ON r.id = rp.role_id WHERE r.role_key = '{$role}';\n";
    $rows = [];
    foreach ($grants as $permission => $abilities) {
        $rows[] = sprintf(
            "SELECT '%s' AS k, %d AS v, %d AS c, %d AS e, %d AS d, %d AS a",
            $permission,
            str_contains($abilities, 'V') ? 1 : 0,
            str_contains($abilities, 'C') ? 1 : 0,
            str_contains($abilities, 'E') ? 1 : 0,
            str_contains($abilities, 'D') ? 1 : 0,
            str_contains($abilities, 'A') ? 1 : 0
        );
    }
    $sql .= "INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)\n"
        . "SELECT r.id, g.k, g.v, g.c, g.e, g.d, g.a FROM roles r\nINNER JOIN (\n    "
        . implode(" UNION ALL\n    ", $rows)
        . "\n) g\nWHERE r.role_key = '{$role}';\n\n";
}

// Always the last migration, and a new file name whenever the matrix changes,
// so a database that ran an older matrix gets the new one on its next migrate.
foreach (glob(__DIR__ . '/../database/migrations/*_eri_permission_matrix*.sql') ?: [] as $old) {
    unlink($old);
}
$path = __DIR__ . '/../database/migrations/99991231_000000_eri_permission_matrix_' . substr(md5($sql), 0, 8) . '.sql';
file_put_contents($path, $sql);
echo "Wrote " . basename($path) . " (" . count($roles) . " roles).\n";
