-- Revenue side (rate cards, invoices, payments), database-driven permissions,
-- company settings and login security.

CREATE TABLE IF NOT EXISTS rate_cards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rate_code VARCHAR(32) NOT NULL UNIQUE,
    customer_id INT UNSIGNED NULL,
    origin VARCHAR(150) NOT NULL,
    destination VARCHAR(150) NOT NULL,
    vehicle_type VARCHAR(80) NULL,
    rate_type ENUM('per_trip','per_kg','per_m3','per_package','per_day') NOT NULL DEFAULT 'per_trip',
    rate_amount DECIMAL(14,2) NOT NULL,
    minimum_charge DECIMAL(14,2) NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    status ENUM('active','expired','draft') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_rates_status (status),
    INDEX idx_rates_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(32) NOT NULL UNIQUE,
    customer_id INT UNSIGNED NOT NULL,
    trip_id INT UNSIGNED NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 18.00,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('draft','issued','partially_paid','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
    issued_by INT UNSIGNED NULL,
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_invoices_status (status),
    INDEX idx_invoices_issue (issue_date),
    INDEX idx_invoices_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    shipment_id INT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(14,2) AS (quantity * unit_price) STORED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE SET NULL,
    INDEX idx_invoice_lines_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_code VARCHAR(32) NOT NULL UNIQUE,
    invoice_id INT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    method ENUM('cash','bank_transfer','mobile_money','cheque','card') NOT NULL DEFAULT 'bank_transfer',
    reference VARCHAR(80) NULL,
    paid_at DATETIME NOT NULL,
    recorded_by INT UNSIGNED NULL,
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_payments_date (paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    permission_key VARCHAR(50) NOT NULL UNIQUE,
    permission_label VARCHAR(100) NOT NULL,
    permission_group VARCHAR(60) NOT NULL DEFAULT 'General',
    sort_order INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    permission_key VARCHAR(50) NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 1,
    can_create TINYINT(1) NOT NULL DEFAULT 0,
    can_edit TINYINT(1) NOT NULL DEFAULT 0,
    can_delete TINYINT(1) NOT NULL DEFAULT 0,
    can_approve TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_role_permission (role_id, permission_key),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(60) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NULL,
    setting_label VARCHAR(120) NOT NULL,
    setting_group VARCHAR(60) NOT NULL DEFAULT 'Company',
    input_type VARCHAR(20) NOT NULL DEFAULT 'text',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NULL,
    succeeded TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attempts_email (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_resets_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER prvg,
    ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL AFTER must_change_password,
    ADD COLUMN IF NOT EXISTS failed_login_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER password_changed_at,
    ADD COLUMN IF NOT EXISTS locked_until DATETIME NULL AFTER failed_login_count,
    ADD COLUMN IF NOT EXISTS job_title VARCHAR(100) NULL AFTER department,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER created_at;

CREATE INDEX IF NOT EXISTS idx_users_deleted ON users (deleted_at);

ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS entity_type VARCHAR(40) NULL AFTER link_route,
    ADD COLUMN IF NOT EXISTS entity_id VARCHAR(64) NULL AFTER entity_type,
    ADD COLUMN IF NOT EXISTS read_at DATETIME NULL AFTER is_read;

ALTER TABLE reports
    ADD COLUMN IF NOT EXISTS report_key VARCHAR(60) NULL AFTER id,
    ADD COLUMN IF NOT EXISTS description VARCHAR(255) NULL AFTER report_name,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER action_label;

CREATE UNIQUE INDEX IF NOT EXISTS uq_reports_key ON reports (report_key);

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('dashboard', 'Dashboard', 'Overview', 10),
('vehicles', 'Vehicles', 'Fleet', 20),
('drivers', 'Drivers', 'Fleet', 30),
('maintenance', 'Maintenance', 'Fleet', 40),
('vehicle_documents', 'Vehicle documents', 'Fleet', 50),
('requests', 'Transport requests', 'Transport', 60),
('trips', 'Trips', 'Transport', 70),
('shipments', 'Shipments', 'Transport', 80),
('deliveries', 'Deliveries', 'Transport', 90),
('customers', 'Customers', 'Commercial', 100),
('rates', 'Rate cards', 'Commercial', 110),
('invoices', 'Invoices', 'Commercial', 120),
('fuel', 'Fuel management', 'Finance', 130),
('expenses', 'Logistics expenses', 'Finance', 140),
('warehouse', 'Warehouse and inventory', 'Warehouse', 150),
('movements', 'Stock movements', 'Warehouse', 160),
('procurement', 'Procurement', 'Warehouse', 170),
('suppliers', 'Suppliers', 'Warehouse', 180),
('reports', 'Reports', 'Insights', 190),
('users', 'Users and permissions', 'Administration', 200),
('settings', 'Company settings', 'Administration', 210),
('audit', 'Audit trail', 'Administration', 220)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO company_settings (setting_key, setting_value, setting_label, setting_group, input_type) VALUES
('company_name', '', 'Company name', 'Company', 'text'),
('company_tin', '', 'TIN number', 'Company', 'text'),
('company_phone', '', 'Phone', 'Company', 'text'),
('company_email', '', 'Email', 'Company', 'text'),
('company_address', '', 'Address', 'Company', 'text'),
('currency_code', 'RWF', 'Currency code', 'Finance', 'text'),
('currency_symbol', 'RWF', 'Currency symbol', 'Finance', 'text'),
('tax_rate', '18', 'Default VAT rate (%)', 'Finance', 'number'),
('invoice_prefix', 'INV', 'Invoice number prefix', 'Finance', 'text'),
('payment_terms_days', '30', 'Default payment terms (days)', 'Finance', 'number'),
('licence_alert_days', '90', 'Driver licence alert window (days)', 'Operations', 'number'),
('document_alert_days', '30', 'Vehicle document alert window (days)', 'Operations', 'number'),
('service_alert_days', '14', 'Service due alert window (days)', 'Operations', 'number'),
('on_time_grace_minutes', '30', 'On-time delivery grace (minutes)', 'Operations', 'number'),
('max_login_attempts', '5', 'Failed logins before lockout', 'Security', 'number'),
('lockout_minutes', '15', 'Lockout duration (minutes)', 'Security', 'number')
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label), setting_group = VALUES(setting_group), input_type = VALUES(input_type);

UPDATE reports SET report_key = 'vehicle_utilization' WHERE report_name LIKE 'Vehicle utilization%' AND report_key IS NULL;
UPDATE reports SET report_key = 'fuel_consumption' WHERE report_name LIKE 'Fuel consumption%' AND report_key IS NULL;
UPDATE reports SET report_key = 'delivery_performance' WHERE report_name LIKE 'Delivery performance%' AND report_key IS NULL;
UPDATE reports SET report_key = 'maintenance_cost' WHERE report_name LIKE 'Maintenance cost%' AND report_key IS NULL;
UPDATE reports SET report_key = 'driver_performance' WHERE report_name LIKE 'Driver performance%' AND report_key IS NULL;
UPDATE reports SET report_key = 'inventory_movement' WHERE report_name LIKE 'Inventory movement%' AND report_key IS NULL;
UPDATE reports SET report_key = 'trip_profitability' WHERE report_name LIKE 'Trip profitability%' AND report_key IS NULL;
UPDATE reports SET report_key = 'expense_summary' WHERE report_name LIKE 'Expense summary%' AND report_key IS NULL;

UPDATE users SET password_changed_at = COALESCE(password_changed_at, created_at);
