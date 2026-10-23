-- The default permissions of the ERI specification (section 21), written by
-- scripts/permission_matrix.php. Admin adjusts them under Administration >
-- Role permissions. Admin itself keeps every page but signs nothing: the
-- approval chain is held by the Finance Manager, the MD and the Cashier.

-- Admin: every page.
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.permission_key, 1, 1, 1, 1, 1 FROM roles r CROSS JOIN permissions p WHERE r.role_key = 'super_admin'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

-- management
DELETE rp FROM role_permissions rp INNER JOIN roles r ON r.id = rp.role_id WHERE r.role_key = 'management';
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, g.k, g.v, g.c, g.e, g.d, g.a FROM roles r
INNER JOIN (
    SELECT 'dashboard' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'approvals' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'reports' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'tracking' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'form_requests' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'journal' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'invoices' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'supplier_bills' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'trips' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'support_requests' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'requests' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'opening_balances' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'payment_batches' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'payments' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'customers' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'price_offers' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'rates' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'vehicles' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'trailers' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'drivers' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'deliveries' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'fuel' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'shipments' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'crossings' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'freight_jobs' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'journeys' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'intercompany' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'currency_rates' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a
) g
WHERE r.role_key = 'management';

-- logistics_manager
DELETE rp FROM role_permissions rp INNER JOIN roles r ON r.id = rp.role_id WHERE r.role_key = 'logistics_manager';
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, g.k, g.v, g.c, g.e, g.d, g.a FROM roles r
INNER JOIN (
    SELECT 'dashboard' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'approvals' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'reports' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'tracking' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'vehicles' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'trailers' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'vehicle_documents' AS k, 1 AS v, 1 AS c, 1 AS e, 1 AS d, 0 AS a UNION ALL
    SELECT 'drivers' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'driver_documents' AS k, 1 AS v, 1 AS c, 1 AS e, 1 AS d, 0 AS a UNION ALL
    SELECT 'document_types' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'driver_momo_changes' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'maintenance' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'garage' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'tyre_logs' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'crane_logs' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'store_issues' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'goods_receipts' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'stock_counts' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'warehouse' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'routes' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'route_rates' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'service_rates' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'payroll' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'trip_planner' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'trips' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'fuel_orders' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'vehicle_handovers' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'support_requests' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'deliveries' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'form_requests' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'customers' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'suppliers' AS k, 1 AS v, 1 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'procurement' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'containers' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'crossings' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'border_posts' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'fuel' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'shipments' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'requests' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'journeys' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'freight_jobs' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'price_offers' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'rates' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'payment_batches' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'shortage_rules' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a
) g
WHERE r.role_key = 'logistics_manager';

-- finance
DELETE rp FROM role_permissions rp INNER JOIN roles r ON r.id = rp.role_id WHERE r.role_key = 'finance';
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, g.k, g.v, g.c, g.e, g.d, g.a FROM roles r
INNER JOIN (
    SELECT 'dashboard' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'approvals' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'reports' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'form_requests' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'payment_batches' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'books' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'journal' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'accounts' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'ledger_mappings' AS k, 1 AS v, 1 AS c, 1 AS e, 1 AS d, 0 AS a UNION ALL
    SELECT 'budgets' AS k, 1 AS v, 1 AS c, 1 AS e, 1 AS d, 0 AS a UNION ALL
    SELECT 'bank_statements' AS k, 1 AS v, 1 AS c, 1 AS e, 1 AS d, 0 AS a UNION ALL
    SELECT 'currencies' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'currency_rates' AS k, 1 AS v, 1 AS c, 1 AS e, 1 AS d, 0 AS a UNION ALL
    SELECT 'opening_balances' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'payment_methods' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'cheques' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'cheque_books' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'invoices' AS k, 1 AS v, 1 AS c, 1 AS e, 1 AS d, 1 AS a UNION ALL
    SELECT 'payments' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'customers' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'rates' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'suppliers' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'supplier_bills' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'supplier_payments' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'expenses' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'disbursements' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'cargo_claims' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'trips' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'vehicles' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'drivers' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'driver_documents' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'fuel' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'fuel_orders' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'route_rates' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'service_rates' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'routes' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'maintenance' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'deliveries' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'procurement' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'crossings' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'clearance_payments' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'support_requests' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'stock_counts' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'goods_receipts' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'tyre_logs' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'crane_logs' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'garage' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'warehouse' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'billing' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'shortage_rules' AS k, 1 AS v, 1 AS c, 1 AS e, 1 AS d, 0 AS a UNION ALL
    SELECT 'payroll' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'intercompany' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a
) g
WHERE r.role_key = 'finance';

-- cashier
DELETE rp FROM role_permissions rp INNER JOIN roles r ON r.id = rp.role_id WHERE r.role_key = 'cashier';
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, g.k, g.v, g.c, g.e, g.d, g.a FROM roles r
INNER JOIN (
    SELECT 'dashboard' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'approvals' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'form_requests' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'payment_batches' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'payments' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'payment_methods' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'supplier_payments' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'cheques' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'invoices' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'supplier_bills' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'reports' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a
) g
WHERE r.role_key = 'cashier';

-- warehouse_manager
DELETE rp FROM role_permissions rp INNER JOIN roles r ON r.id = rp.role_id WHERE r.role_key = 'warehouse_manager';
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, g.k, g.v, g.c, g.e, g.d, g.a FROM roles r
INNER JOIN (
    SELECT 'dashboard' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'approvals' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'form_requests' AS k, 1 AS v, 1 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'warehouses' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'warehouse' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'movements' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'procurement' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'suppliers' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'reports' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'store_issues' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'goods_receipts' AS k, 1 AS v, 1 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'stock_counts' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'garage' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'maintenance' AS k, 1 AS v, 0 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'tyre_logs' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'vehicles' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'trailers' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'support_requests' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a
) g
WHERE r.role_key = 'warehouse_manager';

-- driver
DELETE rp FROM role_permissions rp INNER JOIN roles r ON r.id = rp.role_id WHERE r.role_key = 'driver';
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, g.k, g.v, g.c, g.e, g.d, g.a FROM roles r
INNER JOIN (
    SELECT 'dashboard' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'trips' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'deliveries' AS k, 1 AS v, 0 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'support_requests' AS k, 1 AS v, 1 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'driver_documents' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'vehicle_handovers' AS k, 1 AS v, 1 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'form_requests' AS k, 1 AS v, 1 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'vehicles' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a
) g
WHERE r.role_key = 'driver';

-- clearance_officer
DELETE rp FROM role_permissions rp INNER JOIN roles r ON r.id = rp.role_id WHERE r.role_key = 'clearance_officer';
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, g.k, g.v, g.c, g.e, g.d, g.a FROM roles r
INNER JOIN (
    SELECT 'dashboard' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'approvals' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'reports' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'crossings' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 1 AS a UNION ALL
    SELECT 'border_posts' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'clearance_documents' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'clearance_inspections' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'clearance_payments' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'clearance_releases' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'containers' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'shipments' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'shipment_documents' AS k, 1 AS v, 1 AS c, 1 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'customers' AS k, 1 AS v, 1 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'trips' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'form_requests' AS k, 1 AS v, 1 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'documents' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a
) g
WHERE r.role_key = 'clearance_officer';

-- supplier
DELETE rp FROM role_permissions rp INNER JOIN roles r ON r.id = rp.role_id WHERE r.role_key = 'supplier';
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, g.k, g.v, g.c, g.e, g.d, g.a FROM roles r
INNER JOIN (
    SELECT 'dashboard' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'portal' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a
) g
WHERE r.role_key = 'supplier';

-- customer
DELETE rp FROM role_permissions rp INNER JOIN roles r ON r.id = rp.role_id WHERE r.role_key = 'customer';
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, g.k, g.v, g.c, g.e, g.d, g.a FROM roles r
INNER JOIN (
    SELECT 'dashboard' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a UNION ALL
    SELECT 'portal' AS k, 1 AS v, 0 AS c, 0 AS e, 0 AS d, 0 AS a
) g
WHERE r.role_key = 'customer';

