-- ERI specification, section 12: maintenance, garage and stores.
--
-- One flow replaces the garage WhatsApp group, the main-office stock system and
-- the printed delivery note for parts:
--
--   F3 service request -> (chain) -> job card + F4 stores issue note
--   -> storekeeper issues (stock goes down, value charged to the truck)
--   -> mechanic records work, parts and odometer, and closes the job card
--
-- Also: goods received against a purchase order (F5), stock counts with the
-- differences explained and approved, a tyre log (F6 is built from it) and a
-- crane log (F7 is built from it).
--
-- Safe to run more than once.

-- Job cards ------------------------------------------------------------------

ALTER TABLE maintenance_orders
    ADD COLUMN IF NOT EXISTS request_id INT UNSIGNED NULL COMMENT 'the F3 (or F11) it came from' AFTER vehicle_id,
    ADD COLUMN IF NOT EXISTS trailer_id INT UNSIGNED NULL AFTER request_id,
    ADD COLUMN IF NOT EXISTS owner_company_id INT UNSIGNED NULL COMMENT 'the company that owns the truck, when it is a sister company' AFTER trailer_id,
    ADD COLUMN IF NOT EXISTS flow ENUM('legacy','eri') NOT NULL DEFAULT 'eri' COMMENT 'eri: parts post when issued, outside work through F12' AFTER owner_company_id,
    ADD COLUMN IF NOT EXISTS closed_by INT UNSIGNED NULL AFTER completed_at;

-- Rows that existed before this flow keep the old posting.
UPDATE maintenance_orders SET flow = 'legacy' WHERE request_id IS NULL AND created_at < '2026-10-23';

ALTER TABLE maintenance_parts
    ADD COLUMN IF NOT EXISTS request_line_id INT UNSIGNED NULL COMMENT 'the F4 line it was issued against' AFTER item_id,
    ADD COLUMN IF NOT EXISTS movement_code VARCHAR(40) NULL AFTER request_line_id,
    ADD COLUMN IF NOT EXISTS issued_at DATETIME NULL AFTER movement_code;

-- What has been issued or received against each request line.
ALTER TABLE form_request_lines
    ADD COLUMN IF NOT EXISTS done_quantity DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'F4 issued / F5 received' AFTER paid_amount;

-- Goods received against a purchase order (F5) -------------------------------

CREATE TABLE IF NOT EXISTS goods_receipts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    receipt_no VARCHAR(40) NOT NULL,
    request_id INT UNSIGNED NOT NULL COMMENT 'the F5',
    supplier_id INT UNSIGNED NULL,
    warehouse_id INT UNSIGNED NULL,
    received_on DATE NOT NULL,
    delivery_ref VARCHAR(80) NULL COMMENT 'the supplier delivery note number',
    currency CHAR(3) NOT NULL,
    total_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    entry_id INT UNSIGNED NULL,
    received_by INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_goods_receipts_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goods_receipt_lines (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    receipt_id INT UNSIGNED NOT NULL,
    request_line_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(14,2) NOT NULL,
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    movement_code VARCHAR(40) NULL,
    KEY idx_goods_receipt_lines_receipt (receipt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock counts -----------------------------------------------------------------

CREATE TABLE IF NOT EXISTS stock_counts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    count_no VARCHAR(40) NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    counted_on DATE NOT NULL,
    status ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
    counted_by INT UNSIGNED NULL,
    submitted_at DATETIME NULL,
    decided_by INT UNSIGNED NULL,
    decided_at DATETIME NULL,
    decision_note VARCHAR(255) NULL,
    entry_id INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_stock_counts_company (company_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_count_lines (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    count_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    system_quantity DECIMAL(14,2) NOT NULL,
    counted_quantity DECIMAL(14,2) NULL,
    unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
    reason VARCHAR(255) NULL COMMENT 'why the count differs',
    KEY idx_stock_count_lines_count (count_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tyre log (F6 is built from it) -----------------------------------------------

CREATE TABLE IF NOT EXISTS tyre_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    changed_on DATE NOT NULL,
    vehicle_id INT UNSIGNED NULL,
    trailer_id INT UNSIGNED NULL,
    driver_id INT UNSIGNED NULL,
    position VARCHAR(30) NOT NULL,
    odometer_km INT UNSIGNED NULL,
    tyre_serial VARCHAR(60) NULL,
    brand VARCHAR(60) NULL,
    reason VARCHAR(30) NOT NULL DEFAULT 'worn',
    quantity DECIMAL(8,2) NOT NULL DEFAULT 1,
    item_id INT UNSIGNED NULL COMMENT 'the tyre from the store, if any',
    supplier_id INT UNSIGNED NULL COMMENT 'the tyre fitter',
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'fitting fee per tyre',
    currency CHAR(3) NOT NULL DEFAULT 'RWF',
    maintenance_id INT UNSIGNED NULL,
    request_id INT UNSIGNED NULL COMMENT 'the F6 that paid the fitter',
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_tyre_logs_vehicle (vehicle_id, changed_on),
    KEY idx_tyre_logs_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crane log (F7 is built from it) ----------------------------------------------

CREATE TABLE IF NOT EXISTS crane_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    lift_date DATE NOT NULL,
    vehicle_id INT UNSIGNED NULL,
    trip_id INT UNSIGNED NULL,
    container_no VARCHAR(20) NOT NULL,
    operation ENUM('loading','offloading') NOT NULL DEFAULT 'loading',
    container_size VARCHAR(10) NOT NULL DEFAULT '20',
    supplier_id INT UNSIGNED NULL COMMENT 'the crane operator',
    fee DECIMAL(14,2) NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'RWF',
    request_id INT UNSIGNED NULL COMMENT 'the F7 that paid the operator',
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_crane_logs_date (lift_date),
    KEY idx_crane_logs_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Accounts and mappings --------------------------------------------------------

INSERT INTO gl_accounts (account_code, account_name, account_type, normal_balance, is_header, is_contra, depth, report_section, section_order, cash_flow_class, is_bank, description)
VALUES
('2050', 'Goods received not invoiced', 'liability', 'credit', 0, 0, 0, 'Accounts payable', 50, 'operating', 0, 'Stock received against a purchase order before the supplier invoice is verified.'),
('6960', 'Stock count differences', 'expense', 'debit', 0, 0, 0, 'Operating expenses', 120, 'operating', 0, 'Stock found short (or over) at a count, once the count is approved.')
ON DUPLICATE KEY UPDATE account_name = VALUES(account_name);

INSERT INTO ledger_mappings (company_id, item_code, item_label, debit_account, credit_account, notes)
SELECT NULL, m.item_code, m.item_label, m.debit_account, m.credit_account, m.notes FROM (
    SELECT 'goods_received' AS item_code, 'Goods received into store (against F5)' AS item_label, '1200' AS debit_account, '2050' AS credit_account, 'Cleared by the supplier invoice (F12).' AS notes
    UNION ALL SELECT 'part_issued', 'Parts issued to a truck (F4)', '6000', '1200', 'Charged to the truck on the job card.'
    UNION ALL SELECT 'tyre_issued', 'Tyres issued to a truck (F4)', '6050', '1200', 'Charged to the truck on the job card.'
    UNION ALL SELECT 'stock_count', 'Stock count difference', '6960', '1200', 'A loss debits the first account; a gain reverses it.'
) m
WHERE NOT EXISTS (SELECT 1 FROM ledger_mappings x WHERE x.item_code = m.item_code AND x.company_id IS NULL);

-- Settings, permissions --------------------------------------------------------

INSERT INTO company_settings (setting_key, setting_value, setting_label, setting_group, input_type) VALUES
('job_card_number_format', 'JC-{YEAR}-{SEQ4}', 'Job card number', 'Garage and store', 'text'),
('goods_receipt_number_format', 'GRN-{YEAR}-{SEQ4}', 'Goods received note number', 'Garage and store', 'text'),
('stock_count_number_format', 'SC-{YEAR}-{SEQ4}', 'Stock count number', 'Garage and store', 'text'),
('part_repeat_days', '30', 'Warn when the same part is fitted to the same truck again within (days)', 'Garage and store', 'number'),
('service_request_auto', '1', 'Raise a draft service request (F3) when a truck reaches its service (1 = yes, 0 = no)', 'Garage and store', 'number')
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label), setting_group = VALUES(setting_group);

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('garage', 'Garage: job cards', 'Fleet', 75),
('store_issues', 'Store: issue notes', 'Warehouse', 120),
('goods_receipts', 'Store: goods received', 'Warehouse', 121),
('stock_counts', 'Store: stock counts', 'Warehouse', 122),
('tyre_logs', 'Tyre log', 'Fleet', 76),
('crane_logs', 'Crane log', 'Trips', 77)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label);

-- The history of a request also records the store and supplier steps.
ALTER TABLE form_request_events MODIFY COLUMN step ENUM('created','prepared','verified','authorized','paid','returned','resent','cancelled','received','forwarded','confirmed','note',
    'issued','partly_issued','withdrawn','accepted','declined','goods_received') NOT NULL;

-- Suppliers are paid by MoMo or bank (section 18, supplier record).
ALTER TABLE suppliers
    ADD COLUMN IF NOT EXISTS payment_method ENUM('momo','bank','cash') NOT NULL DEFAULT 'bank' AFTER payment_terms_days,
    ADD COLUMN IF NOT EXISTS momo_number VARCHAR(20) NULL AFTER payment_method,
    ADD COLUMN IF NOT EXISTS momo_name VARCHAR(120) NULL AFTER momo_number,
    ADD COLUMN IF NOT EXISTS bank_name VARCHAR(120) NULL AFTER momo_name,
    ADD COLUMN IF NOT EXISTS bank_account VARCHAR(60) NULL AFTER bank_name;
