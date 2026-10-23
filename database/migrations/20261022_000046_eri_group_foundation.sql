-- ERI specification, foundation (sections 3, 4 and 21).
--
-- One system holds every company of the group. Each company has its own base
-- currency, logo and stamp, and its own departments. Every operational record
-- carries the company it belongs to, so each company sees only its own trucks,
-- drivers, requests and books, while the head of the business sees them all.
--
-- Customer warehousing (storage lots, gate passes, customer cargo kept in a
-- warehouse) is not part of the specification and is removed. The parts store
-- keeps using the `warehouses` table, now called "Stores" on screen.
--
-- Safe to run more than once.

-- --------------------------------------------------------------- companies
CREATE TABLE IF NOT EXISTS companies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_code VARCHAR(20) NOT NULL,
    company_name VARCHAR(150) NOT NULL COMMENT 'as printed on every form header',
    short_name VARCHAR(60) NULL,
    country VARCHAR(60) NULL,
    city VARCHAR(80) NULL,
    address VARCHAR(255) NULL,
    phone VARCHAR(60) NULL,
    email VARCHAR(190) NULL,
    website VARCHAR(190) NULL,
    tin_number VARCHAR(40) NULL,
    base_currency CHAR(3) NOT NULL DEFAULT 'RWF' COMMENT 'the currency its books are kept in',
    logo_file VARCHAR(255) NULL,
    stamp_file VARCHAR(255) NULL COMMENT 'printed when the MD authorizes',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_company_code (company_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS departments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    department_name VARCHAR(100) NOT NULL,
    head_user_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(255) NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_department (company_id, department_name),
    CONSTRAINT fk_department_company FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------- users
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER role_id,
    ADD COLUMN IF NOT EXISTS department_id INT UNSIGNED NULL AFTER company_id,
    ADD COLUMN IF NOT EXISTS supplier_id INT UNSIGNED NULL COMMENT 'a supplier login sees only its own orders' AFTER department_id,
    ADD COLUMN IF NOT EXISTS customer_id INT UNSIGNED NULL COMMENT 'a customer login sees only its own cargo' AFTER supplier_id,
    ADD COLUMN IF NOT EXISTS whatsapp_number VARCHAR(40) NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS two_step_login TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'a code by email at every sign-in' AFTER must_change_password,
    ADD COLUMN IF NOT EXISTS last_seen_at DATETIME NULL AFTER last_login_at;

-- ------------------------------------------- the company each record belongs to
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_vehicles_company (company_id);
ALTER TABLE drivers ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_drivers_company (company_id);
ALTER TABLE trips ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_trips_company (company_id);
ALTER TABLE customers ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL COMMENT 'blank: a customer of the whole group' AFTER id, ADD INDEX IF NOT EXISTS idx_customers_company (company_id);
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL COMMENT 'blank: a supplier of the whole group' AFTER id, ADD INDEX IF NOT EXISTS idx_suppliers_company (company_id);
ALTER TABLE maintenance_orders ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_maintenance_company (company_id);
ALTER TABLE warehouses ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_warehouses_company (company_id);
ALTER TABLE inventory_items ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_items_company (company_id);
ALTER TABLE stock_movements ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_movements_company (company_id);
ALTER TABLE purchase_requests ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_purchases_company (company_id);
ALTER TABLE supplier_bills ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_bills_company (company_id);
ALTER TABLE supplier_payments ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_supplier_payments_company (company_id);
ALTER TABLE fuel_records ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_fuel_company (company_id);
ALTER TABLE deliveries ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_deliveries_company (company_id);
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_invoices_company (company_id);
ALTER TABLE payments ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_payments_company (company_id);
ALTER TABLE expenses ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_expenses_company (company_id);
ALTER TABLE rate_cards ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_rates_company (company_id);
ALTER TABLE support_requests ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_support_company (company_id);
ALTER TABLE vehicle_documents ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_vdocs_company (company_id);
ALTER TABLE driver_documents ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_ddocs_company (company_id);
ALTER TABLE shipments ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_shipments_company (company_id);
ALTER TABLE border_crossings ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_crossings_company (company_id);
ALTER TABLE containers ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_containers_company (company_id);
ALTER TABLE freight_jobs ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_freight_company (company_id);
ALTER TABLE cargo_claims ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_claims_company (company_id);
ALTER TABLE transport_requests ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_requests_company (company_id);
ALTER TABLE price_offers ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_offers_company (company_id);
ALTER TABLE journeys ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_journeys_company (company_id);
ALTER TABLE disbursements ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_disbursements_company (company_id);
ALTER TABLE gl_journal_entries ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_journal_company (company_id);
ALTER TABLE gl_budgets ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_budgets_company (company_id);
ALTER TABLE gl_cheque_books ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_chequebooks_company (company_id);
ALTER TABLE gl_cheques ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_cheques_company (company_id);
ALTER TABLE bank_statements ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_statements_company (company_id);
ALTER TABLE payment_methods ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL COMMENT 'blank: used by every company' AFTER id, ADD INDEX IF NOT EXISTS idx_methods_company (company_id);
ALTER TABLE approval_steps ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id, ADD INDEX IF NOT EXISTS idx_steps_company (company_id);
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id;
ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER id;

-- ---------------------------------------- customer warehousing is not kept
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS storage_releases;
DROP TABLE IF EXISTS storage_lots;
DROP TABLE IF EXISTS warehouse_cargo;
SET FOREIGN_KEY_CHECKS = 1;
DELETE FROM role_permissions WHERE permission_key IN ('storage_lots', 'storage_releases');
DELETE FROM permissions WHERE permission_key IN ('storage_lots', 'storage_releases');
UPDATE permissions SET permission_label = 'Stores', permission_group = 'Store' WHERE permission_key = 'warehouses';
UPDATE permissions SET permission_label = 'Items and stock', permission_group = 'Store' WHERE permission_key = 'warehouse';
UPDATE permissions SET permission_label = 'Stock movements', permission_group = 'Store' WHERE permission_key = 'movements';

-- -------------------------------------------------------------------- roles
-- The internal keys stay; the names are the specification's. New roles are
-- added for the people who were not in the system before.
INSERT IGNORE INTO roles (role_key, role_name) VALUES
('super_admin', 'Admin'),
('management', 'Managing Director'),
('logistics_manager', 'Operational Manager'),
('finance', 'Finance Manager'),
('cashier', 'Cashier'),
('warehouse_manager', 'Storekeeper'),
('workshop', 'Mechanic / Garage'),
('hr', 'HR'),
('driver', 'Driver'),
('sister_supervisor', 'Sister-company supervisor'),
('supplier', 'Supplier'),
('customer', 'Customer'),
('clearance_officer', 'Declaration Manager'),
('front_office', 'Front office'),
('customer_care', 'Customer care'),
('accounts_receivable', 'Accounts receivable'),
('accounts_payable', 'Accounts payable'),
('shipping_clerk', 'Shipping clerk'),
('declaration_assistant', 'Declaration assistant'),
('port_clerk', 'Port clerk');

UPDATE roles SET role_name = 'Admin' WHERE role_key = 'super_admin';
UPDATE roles SET role_name = 'Managing Director' WHERE role_key = 'management';
UPDATE roles SET role_name = 'Operational Manager' WHERE role_key = 'logistics_manager';
UPDATE roles SET role_name = 'Finance Manager' WHERE role_key = 'finance';
UPDATE roles SET role_name = 'Storekeeper' WHERE role_key = 'warehouse_manager';
UPDATE roles SET role_name = 'Mechanic / Garage' WHERE role_key = 'workshop';
UPDATE roles SET role_name = 'Declaration Manager' WHERE role_key = 'clearance_officer';

-- The fleet manager is the Operational Manager in the specification: one per
-- fleet. Anyone holding the old role moves across, and the role goes.
UPDATE users u
INNER JOIN roles old ON old.id = u.role_id AND old.role_key = 'fleet_manager'
INNER JOIN roles om ON om.role_key = 'logistics_manager'
SET u.role_id = om.id;
DELETE rp FROM role_permissions rp INNER JOIN roles r ON r.id = rp.role_id WHERE r.role_key = 'fleet_manager';
DELETE FROM roles WHERE role_key = 'fleet_manager';

-- --------------------------------------------------------- new permissions
INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('companies', 'Companies', 'Administration', 200),
('departments', 'Departments', 'Administration', 201),
('all_companies', 'See all companies', 'Administration', 202)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.permission_key, 1, 1, 1, 1, 1 FROM roles r
INNER JOIN (SELECT 'companies' AS permission_key UNION ALL SELECT 'departments' UNION ALL SELECT 'all_companies') p
WHERE r.role_key = 'super_admin'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'all_companies', 1, 0, 0, 0, 0 FROM roles r WHERE r.role_key = 'management'
ON DUPLICATE KEY UPDATE can_view = 1;

-- Everyone needs the dashboard and their approvals inbox.
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.permission_key, 1, 0, 0, 0, 0 FROM roles r
CROSS JOIN (SELECT 'dashboard' AS permission_key UNION ALL SELECT 'approvals') p
WHERE 1 = 1
ON DUPLICATE KEY UPDATE can_view = 1;

-- ------------------------------------------------------------------ settings
INSERT INTO company_settings (setting_key, setting_value, setting_label, setting_group, input_type) VALUES
('default_company_id', '1', 'Company a record belongs to when none is chosen', 'Company', 'number'),
('session_timeout_minutes', '60', 'Sign out after this many idle minutes', 'Security', 'number'),
('password_min_length', '10', 'Shortest password allowed', 'Security', 'number'),
('two_step_for_approvers', '1', 'Approvers sign in with a code sent by email (1 = yes)', 'Security', 'number')
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label), setting_group = VALUES(setting_group);
