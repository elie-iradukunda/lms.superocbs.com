-- ERI specification, sections 7 to 10: routes and rate cards, the request
-- forms F1-F14, the approval chain with signatures and stamp, and payments.
--
-- Every request follows one chain: Prepared by the OM, Verified by the Finance
-- Manager (the books post), Authorized by the MD with the company stamp, Paid
-- by the Cashier. Who signs each step, an optional backup and how long before
-- a reminder or the backup are settings, not code.
--
-- Safe to run more than once.

-- ------------------------------------------------------------------- routes
CREATE TABLE IF NOT EXISTS routes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL COMMENT 'blank: used by the whole group',
    route_code VARCHAR(30) NOT NULL COMMENT 'KGL-MBA-KGL',
    route_name VARCHAR(150) NOT NULL,
    origin VARCHAR(120) NOT NULL,
    destination VARCHAR(120) NOT NULL,
    turnaround_point VARCHAR(120) NULL COMMENT 'where the truck loads and turns back (Mombasa)',
    round_trip TINYINT(1) NOT NULL DEFAULT 1,
    crosses_border TINYINT(1) NOT NULL DEFAULT 1,
    total_km INT UNSIGNED NULL,
    expected_days DECIMAL(5,1) NULL COMMENT 'the whole trip',
    max_return_days DECIMAL(5,1) NULL COMMENT 'from the turnaround point back (3 days from Mombasa)',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_route_code (route_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS route_legs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    route_id INT UNSIGNED NOT NULL,
    leg_no TINYINT UNSIGNED NOT NULL,
    from_place VARCHAR(120) NOT NULL,
    to_place VARCHAR(120) NOT NULL,
    distance_km INT UNSIGNED NULL,
    expected_hours DECIMAL(6,1) NULL,
    border_post_id INT UNSIGNED NULL COMMENT 'the border crossed at the end of this leg',
    is_turnaround TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'the leg arrives where the truck turns back',
    notes VARCHAR(255) NULL,
    KEY idx_leg_route (route_id, leg_no),
    CONSTRAINT fk_leg_route FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The standard amounts of a route: fuel at each filling point, road toll and
-- mileage. Each has a currency and dates, so a new rate never rewrites the
-- requests already made at the old one.
CREATE TABLE IF NOT EXISTS route_rates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    route_id INT UNSIGNED NOT NULL,
    rate_item ENUM('fuel','road_toll','mileage','other') NOT NULL,
    item_label VARCHAR(120) NULL COMMENT 'for "other"',
    fleet ENUM('all','tanker','container') NOT NULL DEFAULT 'all',
    fuel_point VARCHAR(120) NULL COMMENT 'where the fuel is drawn: Kigali, Mombasa',
    fuel_on ENUM('departure','turnaround') NULL COMMENT 'ordered with F1, or raised as a top-up F2 at the turnaround',
    supplier_id INT UNSIGNED NULL COMMENT 'the station or sister company that gives the fuel',
    quantity DECIMAL(12,2) NULL COMMENT 'litres, for fuel',
    amount DECIMAL(14,2) NULL COMMENT 'money paid to the driver (toll, mileage)',
    currency CHAR(3) NOT NULL DEFAULT 'RWF',
    valid_from DATE NOT NULL,
    valid_to DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(255) NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_route_rates (route_id, rate_item, valid_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Other rate cards: crane lifts, tyre fitting, labour.
CREATE TABLE IF NOT EXISTS service_rates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    rate_type ENUM('crane','tyre','labour','other') NOT NULL,
    description VARCHAR(150) NOT NULL COMMENT 'Crane lift, 20 ft, loading',
    variant VARCHAR(60) NULL COMMENT '20ft, 40ft, loading, offloading',
    unit VARCHAR(30) NOT NULL DEFAULT 'each',
    amount DECIMAL(14,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'RWF',
    supplier_id INT UNSIGNED NULL,
    valid_from DATE NOT NULL,
    valid_to DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_service_rates (rate_type, valid_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------- form types
-- F1-F14 of section 8. The chain, numbering and the accounts each line posts
-- to are kept here so Finance and Admin can change them.
CREATE TABLE IF NOT EXISTS form_types (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    form_code VARCHAR(10) NOT NULL,
    form_name VARCHAR(120) NOT NULL,
    title_on_pdf VARCHAR(120) NOT NULL COMMENT 'FUEL ORDER, TRIP MONEY REQUEST',
    chain ENUM('full','finance_md','om_confirm','store','supplier','none') NOT NULL DEFAULT 'full',
    is_money TINYINT(1) NOT NULL DEFAULT 1,
    goes_to_payment TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'appears on the Cashier''s To pay list once authorized',
    posts_on_verify TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'the books post when Finance verifies (a PO only commits)',
    number_format VARCHAR(80) NOT NULL DEFAULT '{SEQ}/{MONTH}/{YEAR}',
    number_reset ENUM('month','year','never') NOT NULL DEFAULT 'month',
    debit_account VARCHAR(20) NULL COMMENT 'default expense or asset account',
    credit_account VARCHAR(20) NULL COMMENT 'default payable',
    raised_by_roles VARCHAR(255) NULL COMMENT 'comma-separated role keys',
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_form_code (form_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO form_types (form_code, form_name, title_on_pdf, chain, is_money, goes_to_payment, posts_on_verify, number_format, debit_account, credit_account, raised_by_roles, sort_order, description) VALUES
('F1', 'Trip money: fuel, road toll, mileage', 'ROAD TOLL, FUEL AND MILEAGE REQUEST', 'full', 1, 1, 1, '{SEQ}/{MONTHNAME}/{YEAR}', '5200', '2300', 'logistics_manager', 10, 'Created with the trip; paid to the drivers before departure.'),
('F2', 'Fuel order', 'FUEL ORDER', 'full', 1, 0, 0, 'FUEL ORDER {SEQ}/{MONTHNAME}/{YEAR}', '5000', '2000', 'logistics_manager', 20, 'Sent to the station or ERI Kenya; the fuel expense posts when their invoice is verified.'),
('F3', 'Service / repair request', 'SERVICE / REPAIR REQUEST', 'full', 1, 0, 0, 'SRV {SEQ}/{MONTHNAME}/{YEAR}', '6000', '2000', 'logistics_manager,workshop,driver', 30, 'Opens a job card and a stores issue note.'),
('F4', 'Stores issue note', 'STORES ISSUE NOTE', 'store', 0, 0, 0, 'SIN {SEQ}/{YEAR}', '6000', '1200', 'logistics_manager,workshop,warehouse_manager', 40, 'The storekeeper issues parts against it; stock goes down.'),
('F5', 'Purchase order', 'PURCHASE ORDER', 'full', 1, 0, 0, 'PO {SEQ}/{YEAR}', '1200', '2000', 'logistics_manager,warehouse_manager', 50, 'A commitment: nothing posts until the goods are received.'),
('F6', 'Tyre change payment', 'CHANGING TYRES PAYMENT REQUEST', 'full', 1, 1, 1, 'TYR {SEQ}/{MONTHNAME}/{YEAR}', '6050', '2000', 'logistics_manager', 60, 'From the tyre log.'),
('F7', 'Crane payment', 'CRANE PAYMENT REQUEST', 'full', 1, 1, 1, 'CRN {SEQ}/{MONTHNAME}/{YEAR}', '5400', '2000', 'logistics_manager', 70, 'From the crane log.'),
('F8', 'Delivery note', 'DELIVERY NOTE', 'om_confirm', 0, 0, 0, 'DN {SEQ}/{YEAR}', NULL, NULL, 'driver,logistics_manager', 80, 'Filled by the driver at the customer.'),
('F9', 'Shortage deduction', 'SHORTAGE DEDUCTION', 'full', 1, 0, 1, 'SHD {SEQ}/{YEAR}', '1160', '5900', 'logistics_manager,finance', 90, 'Goes on the driver''s next payroll list.'),
('F10', 'Driver support', 'DRIVER SUPPORT REQUEST', 'full', 1, 1, 1, 'SUP {SEQ}/{MONTHNAME}/{YEAR}', '5150', '2300', 'driver,logistics_manager', 100, 'Money follows the full chain and lands on the trip.'),
('F11', 'Sister-company request', 'SISTER-COMPANY REQUEST', 'om_confirm', 0, 0, 0, 'ICR {SEQ}/{YEAR}', NULL, NULL, 'sister_supervisor', 110, 'Creates the job card and issue note in the receiving company.'),
('F12', 'Supplier invoice', 'SUPPLIER INVOICE', 'full', 1, 1, 1, 'SINV {SEQ}/{YEAR}', '6900', '2000', 'supplier,finance,warehouse_manager', 120, 'Matched to the PO and what was received.'),
('F13', 'Customer invoice', 'INVOICE', 'finance_md', 1, 0, 1, 'INV {SEQ}/{YEAR}', '1100', '4000', 'finance', 130, 'Sent to the customer once authorized.'),
('F14', 'Vehicle handover', 'VEHICLE HANDOVER', 'om_confirm', 0, 0, 0, 'HO {SEQ}/{YEAR}', NULL, NULL, 'driver', 140, 'Ends the trip; the OM confirms.'),
('F15', 'Payment request (other)', 'PAYMENT REQUEST', 'full', 1, 1, 1, 'PR {SEQ}/{MONTHNAME}/{YEAR}', '6900', '2000', 'logistics_manager,finance,clearance_officer,shipping_clerk', 150, 'Any other payment, including clearance charges and duties.')
ON DUPLICATE KEY UPDATE form_name = form_name;

CREATE TABLE IF NOT EXISTS document_sequences (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    sequence_key VARCHAR(40) NOT NULL,
    period_key VARCHAR(10) NOT NULL,
    last_no INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_sequence (company_id, sequence_key, period_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- the requests
CREATE TABLE IF NOT EXISTS form_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    form_code VARCHAR(10) NOT NULL,
    request_no VARCHAR(60) NULL COMMENT 'given when it is sent',
    title VARCHAR(190) NOT NULL,
    status ENUM('draft','sent','verified','authorized','partly_paid','paid','returned','cancelled','closed') NOT NULL DEFAULT 'draft',
    version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    currency CHAR(3) NOT NULL DEFAULT 'RWF',
    total_amount DECIMAL(16,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(16,2) NOT NULL DEFAULT 0,
    total_quantity DECIMAL(14,2) NULL COMMENT 'litres on a fuel order',
    base_currency CHAR(3) NULL,
    exchange_rate DECIMAL(18,8) NULL,
    base_total DECIMAL(16,2) NULL,
    route_id INT UNSIGNED NULL,
    trip_id INT UNSIGNED NULL,
    vehicle_id INT UNSIGNED NULL,
    driver_id INT UNSIGNED NULL,
    supplier_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    job_card_id INT UNSIGNED NULL,
    parent_request_id INT UNSIGNED NULL COMMENT 'the PO behind an invoice, the F3 behind an F4',
    to_company_id INT UNSIGNED NULL COMMENT 'a sister-company request goes to this company',
    period_from DATE NULL,
    period_to DATE NULL,
    needed_on DATE NULL,
    non_standard_reason VARCHAR(255) NULL COMMENT 'why an amount differs from the rate card',
    payload TEXT NULL COMMENT 'JSON: the fields particular to one form',
    notes TEXT NULL,
    prepared_by INT UNSIGNED NULL,
    prepared_at DATETIME NULL,
    verified_by INT UNSIGNED NULL,
    verified_at DATETIME NULL,
    authorized_by INT UNSIGNED NULL,
    authorized_at DATETIME NULL,
    paid_by INT UNSIGNED NULL,
    paid_at DATETIME NULL,
    returned_reason VARCHAR(255) NULL,
    waiting_since DATETIME NULL COMMENT 'when the current step started, for reminders',
    reminded_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_requests_company (company_id, status),
    KEY idx_requests_form (form_code, status),
    KEY idx_requests_trip (trip_id),
    UNIQUE KEY uq_request_no (company_id, request_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS form_request_lines (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    line_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    group_no SMALLINT UNSIGNED NULL COMMENT 'lines of one truck on a multi-truck F1',
    trip_id INT UNSIGNED NULL,
    vehicle_id INT UNSIGNED NULL,
    trailer_id INT UNSIGNED NULL,
    driver_id INT UNSIGNED NULL,
    item_code VARCHAR(40) NOT NULL DEFAULT 'other' COMMENT 'fuel, road_toll, mileage, part, tyre, crane, labour, support...',
    item_id INT UNSIGNED NULL COMMENT 'a stock item',
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(14,2) NOT NULL DEFAULT 1,
    unit VARCHAR(20) NULL,
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    amount DECIMAL(16,2) NOT NULL DEFAULT 0,
    standard_quantity DECIMAL(14,2) NULL COMMENT 'what the rate card says, to spot a difference',
    standard_amount DECIMAL(16,2) NULL,
    payee_type ENUM('driver','supplier','employee','company','other','none') NOT NULL DEFAULT 'none',
    payee_id INT UNSIGNED NULL,
    payee_name VARCHAR(150) NULL,
    payment_method ENUM('momo','cash','bank','none') NOT NULL DEFAULT 'none',
    momo_number VARCHAR(40) NULL,
    momo_name VARCHAR(150) NULL,
    debit_account VARCHAR(20) NULL,
    extra TEXT NULL COMMENT 'JSON: container no., size, tyre position, date...',
    paid_amount DECIMAL(16,2) NOT NULL DEFAULT 0,
    pay_status ENUM('not_payable','unpaid','in_batch','paid','failed') NOT NULL DEFAULT 'not_payable',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_lines_request (request_id),
    KEY idx_lines_trip (trip_id),
    CONSTRAINT fk_lines_request FOREIGN KEY (request_id) REFERENCES form_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Who did what to a request, with their signature as it was at that moment:
-- "sent by, received by, approved by" on every document.
CREATE TABLE IF NOT EXISTS form_request_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    step ENUM('created','prepared','verified','authorized','paid','returned','resent','cancelled','received','forwarded','confirmed','note') NOT NULL,
    user_id INT UNSIGNED NULL,
    on_behalf_of INT UNSIGNED NULL COMMENT 'when a delegate signs',
    role_key VARCHAR(50) NULL,
    reason TEXT NULL,
    signature_file VARCHAR(255) NULL,
    stamp_file VARCHAR(255) NULL,
    snapshot TEXT NULL COMMENT 'JSON copy of the request and lines at this step',
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_events_request (request_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------- who signs each step
CREATE TABLE IF NOT EXISTS approval_step_settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL COMMENT 'blank: every company',
    step ENUM('verify','authorize','pay','confirm') NOT NULL,
    role_key VARCHAR(50) NOT NULL,
    main_user_id INT UNSIGNED NULL COMMENT 'blank: anyone holding the role',
    backup_user_id INT UNSIGNED NULL,
    backup_after_hours SMALLINT UNSIGNED NULL COMMENT 'blank: no backup, the approver is reminded until they act',
    reminder_hours SMALLINT UNSIGNED NOT NULL DEFAULT 4,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_step_setting (company_id, step)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO approval_step_settings (company_id, step, role_key, reminder_hours) VALUES
(NULL, 'verify', 'finance', 4),
(NULL, 'authorize', 'management', 4),
(NULL, 'pay', 'cashier', 4),
(NULL, 'confirm', 'logistics_manager', 4)
ON DUPLICATE KEY UPDATE role_key = role_key;

-- An approver going on leave hands their step to someone for set dates. The
-- document always shows who actually signed, and for whom.
CREATE TABLE IF NOT EXISTS approval_delegations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    from_user_id INT UNSIGNED NOT NULL,
    to_user_id INT UNSIGNED NOT NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NOT NULL,
    reason VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_delegation (to_user_id, starts_on, ends_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------- payments
CREATE TABLE IF NOT EXISTS payment_batches (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    batch_no VARCHAR(40) NOT NULL COMMENT 'BATCH-2026-10-031',
    method ENUM('momo','cash','bank') NOT NULL DEFAULT 'momo',
    payment_method_id INT UNSIGNED NULL COMMENT 'the MoMo account, cash box or bank account it leaves',
    currency CHAR(3) NOT NULL,
    total_amount DECIMAL(16,2) NOT NULL DEFAULT 0,
    line_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('built','recorded','partly_failed','cancelled') NOT NULL DEFAULT 'built',
    mtn_batch_no VARCHAR(80) NULL,
    proof_file VARCHAR(255) NULL,
    built_by INT UNSIGNED NULL,
    built_at DATETIME NULL,
    recorded_by INT UNSIGNED NULL,
    recorded_at DATETIME NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_batch_no (company_id, batch_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_batch_lines (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL,
    request_id INT UNSIGNED NOT NULL,
    payee_type VARCHAR(20) NOT NULL,
    payee_id INT UNSIGNED NULL,
    payee_name VARCHAR(150) NOT NULL,
    momo_number VARCHAR(40) NULL,
    momo_name VARCHAR(150) NULL,
    amount DECIMAL(16,2) NOT NULL,
    currency CHAR(3) NOT NULL,
    status ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
    failure_reason VARCHAR(255) NULL,
    paid_at DATETIME NULL,
    receipt_no VARCHAR(40) NULL,
    confirmed_at DATETIME NULL COMMENT 'the payee said the money arrived',
    confirmed_via ENUM('app','pin','signature') NULL,
    confirmation_signature VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_batch_lines (batch_id),
    KEY idx_batch_request (request_id),
    CONSTRAINT fk_batch_lines FOREIGN KEY (batch_id) REFERENCES payment_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A driver's cash PIN, for confirming cash taken at the cash office.
ALTER TABLE drivers ADD COLUMN IF NOT EXISTS cash_pin_hash VARCHAR(255) NULL AFTER momo_name;

-- ---------------------------------------------------------------- accounts
INSERT INTO gl_accounts (account_code, account_name, account_type, normal_balance, report_section, section_order, cash_flow_class)
SELECT * FROM (
    SELECT '1160' AS c, 'Driver receivables (deductions)' AS n, 'asset' AS t, 'debit' AS b, 'Current assets' AS s, 20 AS o, 'operating' AS f UNION ALL
    SELECT '1170', 'Receivable from sister companies', 'asset', 'debit', 'Current assets', 21, 'operating' UNION ALL
    SELECT '2300', 'Drivers payable', 'liability', 'credit', 'Current liabilities', 10, 'operating' UNION ALL
    SELECT '2310', 'Payable to sister companies', 'liability', 'credit', 'Current liabilities', 11, 'operating' UNION ALL
    SELECT '4200', 'Clearing and forwarding income', 'income', 'credit', 'Revenue', 20, 'operating' UNION ALL
    SELECT '4300', 'Service income (sister companies)', 'income', 'credit', 'Revenue', 30, 'operating' UNION ALL
    SELECT '5150', 'Driver support', 'cost_of_sales', 'debit', 'Cost of sales', 25, 'operating' UNION ALL
    SELECT '5210', 'Road toll', 'cost_of_sales', 'debit', 'Cost of sales', 30, 'operating' UNION ALL
    SELECT '5220', 'Mileage allowance', 'cost_of_sales', 'debit', 'Cost of sales', 31, 'operating' UNION ALL
    SELECT '5900', 'Shortage loss', 'cost_of_sales', 'debit', 'Cost of sales', 60, 'operating' UNION ALL
    SELECT '6050', 'Tyres', 'expense', 'debit', 'Operating expenses', 15, 'operating' UNION ALL
    SELECT '6950', 'Exchange gains and losses', 'expense', 'debit', 'Operating expenses', 90, 'operating'
) a
WHERE NOT EXISTS (SELECT 1 FROM gl_accounts g WHERE g.account_code = a.c);

-- Road toll and mileage post to their own accounts on F1.
UPDATE form_types SET debit_account = NULL WHERE form_code = 'F1';

-- ---------------------------------------------------------------- permissions
INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('routes', 'Routes', 'Transport', 60),
('route_rates', 'Route rate card', 'Transport', 61),
('service_rates', 'Crane, tyre and labour rates', 'Transport', 62),
('form_requests', 'Requests (F1-F15)', 'Requests', 100),
('form_types', 'Request forms', 'Administration', 217),
('approval_step_settings', 'Approvers per step', 'Administration', 218),
('approval_delegations', 'Delegations', 'Administration', 219),
('payment_batches', 'Payment batches', 'Payments', 110)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.k, 1, 1, 1, 1, 1 FROM roles r
INNER JOIN (SELECT 'routes' AS k UNION ALL SELECT 'route_rates' UNION ALL SELECT 'service_rates' UNION ALL SELECT 'form_requests'
            UNION ALL SELECT 'form_types' UNION ALL SELECT 'approval_step_settings' UNION ALL SELECT 'approval_delegations' UNION ALL SELECT 'payment_batches') p
WHERE r.role_key = 'super_admin'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

-- ------------------------------------------------------------------ settings
INSERT INTO company_settings (setting_key, setting_value, setting_label, setting_group, input_type) VALUES
('batch_number_format', 'BATCH-{YEAR}-{MONTH}-{SEQ}', 'Payment batch number', 'Payments', 'text'),
('mtn_file_columns', 'msisdn,amount,name,reference', 'MTN bulk file columns, in order', 'Payments', 'text'),
('mtn_number_format', '250XXXXXXXXX', 'How MoMo numbers are written in the MTN file', 'Payments', 'text'),
('approval_reminder_until_handled', '1', 'Remind approvers until they act (1 = yes)', 'Approvals', 'number')
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label), setting_group = VALUES(setting_group);

-- The Finance Manager maps each kind of form line to its accounts once
-- (section 14). A company may override the group's mapping.
CREATE TABLE IF NOT EXISTS ledger_mappings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL COMMENT 'blank: the whole group',
    item_code VARCHAR(40) NOT NULL,
    item_label VARCHAR(120) NOT NULL,
    debit_account VARCHAR(20) NOT NULL,
    credit_account VARCHAR(20) NOT NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mapping (company_id, item_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ledger_mappings (company_id, item_code, item_label, debit_account, credit_account) VALUES
(NULL, 'road_toll', 'Road toll (F1)', '5210', '2300'),
(NULL, 'mileage', 'Mileage (F1)', '5220', '2300'),
(NULL, 'fuel', 'Fuel', '5000', '2000'),
(NULL, 'part', 'Spare parts issued', '6000', '1200'),
(NULL, 'tyre', 'Tyre change', '6050', '2000'),
(NULL, 'crane', 'Crane lift', '5400', '2000'),
(NULL, 'labour', 'Garage labour', '6000', '2000'),
(NULL, 'service', 'Service / repair', '6000', '2000'),
(NULL, 'support', 'Driver support', '5150', '2300'),
(NULL, 'shortage', 'Cargo shortage (driver)', '1160', '5900'),
(NULL, 'duty', 'Duties and taxes for a client', '1150', '2000'),
(NULL, 'shipping', 'Shipping line charges', '1150', '2000'),
(NULL, 'other', 'Anything else', '6900', '2000')
ON DUPLICATE KEY UPDATE item_label = item_label;

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('ledger_mappings', 'Ledger mapping', 'Accounting', 160)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label);
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'ledger_mappings', 1, 1, 1, 1, 0 FROM roles r WHERE r.role_key IN ('super_admin', 'finance')
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1;

-- Section 14: each company keeps its books in its own base currency. Every
-- entry keeps the original amount and currency, the rate used and the
-- base-currency amount; every line carries the truck, the trip and the request.
ALTER TABLE gl_journal_entries
    ADD COLUMN IF NOT EXISTS base_currency CHAR(3) NULL AFTER currency,
    ADD COLUMN IF NOT EXISTS exchange_rate DECIMAL(18,8) NULL COMMENT 'one unit of the entry currency in the base currency; blank: no rate on file' AFTER base_currency;
ALTER TABLE gl_journal_lines
    ADD COLUMN IF NOT EXISTS base_debit DECIMAL(16,2) NULL AFTER credit,
    ADD COLUMN IF NOT EXISTS base_credit DECIMAL(16,2) NULL AFTER base_debit,
    ADD COLUMN IF NOT EXISTS vehicle_id INT UNSIGNED NULL AFTER base_credit,
    ADD COLUMN IF NOT EXISTS trip_id INT UNSIGNED NULL AFTER vehicle_id,
    ADD COLUMN IF NOT EXISTS request_id INT UNSIGNED NULL AFTER trip_id,
    ADD INDEX IF NOT EXISTS idx_lines_vehicle (vehicle_id),
    ADD INDEX IF NOT EXISTS idx_lines_trip (trip_id);

INSERT INTO company_settings (setting_key, setting_value, setting_label, setting_group, input_type) VALUES
('signature_required_to_approve', '1', 'Approvers must have a signature on file before signing (1 = yes)', 'Approvals', 'number')
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label);

UPDATE form_types SET chain = 'full', goes_to_payment = 0 WHERE form_code = 'F9';
