-- ERI specification, sections 6 and 13: delivery notes, shortages, customer
-- invoicing and the drivers' payroll list.
--
--   F8 delivery note confirmed -> a shortage above the customer's tolerance
--   raises a deduction request (F9) -> Finance verifies, the MD authorizes ->
--   the deduction goes on the driver's next payroll list.
--
--   F13 customer invoice: Finance picks the customer and the period, the
--   delivered trips are priced from the customer's rate card (per trip, per
--   ton, per litre or per container), shortage deductions are shown, and once
--   the MD authorizes it posts and becomes the customer's invoice.
--
-- Safe to run more than once.

-- Customer rate card: per customer, route, cargo and currency.
ALTER TABLE rate_cards
    ADD COLUMN IF NOT EXISTS route_id INT UNSIGNED NULL AFTER customer_id,
    ADD COLUMN IF NOT EXISTS cargo_type VARCHAR(20) NULL COMMENT 'litres, tonnes, containers; NULL = any' AFTER route_id;
ALTER TABLE rate_cards MODIFY COLUMN rate_type ENUM('per_trip','per_kg','per_m3','per_package','per_day','per_ton','per_litre','per_container') NOT NULL DEFAULT 'per_trip';

-- Shortage tolerance and the value of the cargo, per customer and cargo.
CREATE TABLE IF NOT EXISTS shortage_rules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL COMMENT 'NULL = every customer',
    cargo_type VARCHAR(20) NOT NULL DEFAULT 'litres',
    tolerance_type ENUM('quantity','percent') NOT NULL DEFAULT 'percent',
    tolerance_value DECIMAL(12,3) NOT NULL DEFAULT 0,
    unit_value DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'the customer''s price of one unit of cargo',
    currency CHAR(3) NOT NULL DEFAULT 'RWF',
    charge ENUM('excess','full') NOT NULL DEFAULT 'excess' COMMENT 'charge only what is above the tolerance, or the whole shortage',
    valid_from DATE NOT NULL,
    valid_to DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_shortage_rules_customer (customer_id, cargo_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The invoice made from an authorized F13.
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS request_id INT UNSIGNED NULL COMMENT 'the F13 it was made from' AFTER journey_id;
ALTER TABLE invoice_lines
    ADD COLUMN IF NOT EXISTS trip_id INT UNSIGNED NULL AFTER invoice_id,
    ADD COLUMN IF NOT EXISTS request_line_id INT UNSIGNED NULL AFTER trip_id;

-- Deductions for the drivers' payroll list.
CREATE TABLE IF NOT EXISTS driver_deductions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    driver_id INT UNSIGNED NOT NULL,
    request_id INT UNSIGNED NULL COMMENT 'the F9',
    trip_id INT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    currency CHAR(3) NOT NULL,
    status ENUM('pending','deducted','cancelled') NOT NULL DEFAULT 'pending',
    payroll_run_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_driver_deductions_driver (driver_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_runs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    period CHAR(7) NOT NULL COMMENT 'YYYY-MM',
    status ENUM('draft','closed') NOT NULL DEFAULT 'draft',
    rows_json LONGTEXT NULL COMMENT 'the list as closed',
    closed_by INT UNSIGNED NULL,
    closed_at DATETIME NULL,
    entry_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payroll_runs (company_id, period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ledger mappings for invoice lines and payroll deductions.
INSERT INTO ledger_mappings (company_id, item_code, item_label, debit_account, credit_account, notes)
SELECT NULL, m.item_code, m.item_label, m.debit_account, m.credit_account, m.notes FROM (
    SELECT 'transport' AS item_code, 'Transport invoiced (F13)' AS item_label, '1100' AS debit_account, '4000' AS credit_account, 'Customer receivable and freight revenue.' AS notes
    UNION ALL SELECT 'deduction', 'Shortage deducted by the customer (F13)', '5900', '1100', 'The customer pays less; recovered from the driver through F9.'
    UNION ALL SELECT 'vat', 'VAT on a customer invoice (F13)', '1100', '2100', NULL
    UNION ALL SELECT 'payroll_deduction', 'Deduction taken on the payroll', '2200', '1160', 'Clears the driver receivable when the payroll is closed.'
) m
WHERE NOT EXISTS (SELECT 1 FROM ledger_mappings x WHERE x.item_code = m.item_code AND x.company_id IS NULL);

INSERT INTO company_settings (setting_key, setting_value, setting_label, setting_group, input_type) VALUES
('invoice_vat_percent', '0', 'VAT on customer invoices (%)', 'Invoicing', 'number'),
('invoice_due_days', '30', 'Invoice due after (days), when the customer has no terms', 'Invoicing', 'number')
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label), setting_group = VALUES(setting_group);

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('shortage_rules', 'Shortage tolerance and cargo value', 'Commercial', 141),
('billing', 'Customer invoicing (F13)', 'Commercial', 142),
('payroll', 'Drivers payroll list', 'Fleet', 78),
('portal', 'Customer and supplier portal', 'Portal', 300)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label);
