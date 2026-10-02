-- LMS update of 2 October 2026, for phpMyAdmin (SQL tab, or Import).
-- Run it once on the live database BEFORE extracting the zip of files. Safe to run again.
-- Contents: driver papers (034), supplier bills / disbursements / bank reconciliation (035),
-- customer storage and containers (036), workshop job cards and multi-leg journeys (037).
-- Do NOT run scripts/migrate.php on the live server.

-- ======================================================== 20261002_000034_driver_papers.sql
-- Driver papers (passport, licence, national ID, permits) become a page of
-- their own under Fleet management, so they can be added from the screen.
-- The fleet manager keeps them; a driver sees only his own.
-- Safe to run more than once.

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
    ('driver_documents', 'Driver papers', 'Fleet', 35)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'driver_documents', 1, 1, 1, 1, 1 FROM roles r WHERE r.role_key IN ('super_admin', 'fleet_manager')
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'driver_documents', 1, 1, 1, 0, 0 FROM roles r WHERE r.role_key = 'logistics_manager'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'driver_documents', 1, 0, 0, 0, 0 FROM roles r WHERE r.role_key = 'driver'
ON DUPLICATE KEY UPDATE can_view = 1;

-- ======================================================== 20261002_000035_payables_disbursements_bank.sql
-- Batch A: the money side a large haulier needs.
--   * Supplier bills and the payments that settle them (accounts payable),
--     with an aging report.
--   * Disbursements: duty, port charges and agent fees paid on a customer's
--     behalf, recharged on the invoice at cost and without VAT.
--   * Bank reconciliation: a bank statement, its lines, and which ledger lines
--     they match.
-- Safe to run more than once.

INSERT IGNORE INTO gl_accounts (account_code, account_name, account_type, normal_balance, parent_id, is_header, is_contra, depth, report_section, section_order, cash_flow_class, is_bank, description, is_active)
VALUES ('1150', 'Disbursements recoverable', 'asset', 'debit', NULL, 0, 0, 0, 'Other current assets', 30, 'operating', 0,
        'Duty, port charges and fees paid on behalf of customers, waiting to be recharged.', 1);

-- Payment methods are set up by the company (a second bank account, a USD
-- account, a wallet), but these two columns still only accepted the five
-- original names: anything else was saved blank and posted to the wrong bank.
ALTER TABLE payments MODIFY COLUMN method VARCHAR(40) NOT NULL DEFAULT 'bank_transfer';
ALTER TABLE expenses MODIFY COLUMN payment_method VARCHAR(40) NOT NULL DEFAULT 'cash';

CREATE TABLE IF NOT EXISTS supplier_bills (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bill_no VARCHAR(32) NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    supplier_invoice_no VARCHAR(60) NULL,
    bill_date DATE NOT NULL,
    due_date DATE NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'RWF',
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','approved','partially_paid','paid','cancelled') NOT NULL DEFAULT 'draft',
    trip_id INT UNSIGNED NULL,
    purchase_request_id INT UNSIGNED NULL,
    maintenance_id INT UNSIGNED NULL,
    crossing_id INT UNSIGNED NULL,
    bill_file VARCHAR(255) NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bill_no (bill_no),
    KEY idx_bill_supplier (supplier_id, status),
    KEY idx_bill_due (due_date),
    CONSTRAINT fk_bill_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_bill_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_bill_lines (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bill_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(14,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_bill_line (bill_id),
    CONSTRAINT fk_bill_line_bill FOREIGN KEY (bill_id) REFERENCES supplier_bills(id) ON DELETE CASCADE,
    CONSTRAINT fk_bill_line_account FOREIGN KEY (account_id) REFERENCES gl_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    payment_code VARCHAR(32) NOT NULL,
    bill_id INT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'RWF',
    method VARCHAR(40) NOT NULL DEFAULT 'bank_transfer',
    reference VARCHAR(80) NULL,
    paid_at DATETIME NOT NULL,
    recorded_by INT UNSIGNED NULL,
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_supplier_payment (payment_code),
    KEY idx_supplier_payment_bill (bill_id),
    CONSTRAINT fk_supplier_payment_bill FOREIGN KEY (bill_id) REFERENCES supplier_bills(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS disbursements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    disbursement_code VARCHAR(32) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    trip_id INT UNSIGNED NULL,
    shipment_id INT UNSIGNED NULL,
    supplier_id INT UNSIGNED NULL,
    category VARCHAR(60) NOT NULL DEFAULT 'Customs duty',
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'RWF',
    method VARCHAR(40) NOT NULL DEFAULT 'bank_transfer',
    reference VARCHAR(80) NULL,
    paid_at DATE NOT NULL,
    receipt_file VARCHAR(255) NULL,
    status ENUM('pending','invoiced','recovered','written_off') NOT NULL DEFAULT 'pending',
    invoice_id INT UNSIGNED NULL,
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_disbursement (disbursement_code),
    KEY idx_disbursement_trip (trip_id, status),
    KEY idx_disbursement_customer (customer_id, status),
    CONSTRAINT fk_disbursement_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_disbursement_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL,
    CONSTRAINT fk_disbursement_shipment FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE SET NULL,
    CONSTRAINT fk_disbursement_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    CONSTRAINT fk_disbursement_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- An invoice line is either our own service (VAT applies) or a disbursement
-- recharged at cost (no VAT, and it clears 1150 rather than earning revenue).
ALTER TABLE invoice_lines
    ADD COLUMN IF NOT EXISTS line_kind ENUM('service','disbursement') NOT NULL DEFAULT 'service' AFTER unit_price,
    ADD COLUMN IF NOT EXISTS disbursement_id INT UNSIGNED NULL AFTER line_kind;

CREATE TABLE IF NOT EXISTS bank_statements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    statement_code VARCHAR(32) NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'RWF',
    period_from DATE NOT NULL,
    period_to DATE NOT NULL,
    opening_balance DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    closing_balance DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    status ENUM('open','reconciled') NOT NULL DEFAULT 'open',
    statement_file VARCHAR(255) NULL,
    reconciled_by INT UNSIGNED NULL,
    reconciled_at DATETIME NULL,
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bank_statement (statement_code),
    KEY idx_bank_statement_account (account_id, period_to),
    CONSTRAINT fk_bank_statement_account FOREIGN KEY (account_id) REFERENCES gl_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_statement_lines (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    statement_id INT UNSIGNED NOT NULL,
    line_date DATE NOT NULL,
    description VARCHAR(255) NULL,
    reference VARCHAR(80) NULL,
    amount DECIMAL(16,2) NOT NULL COMMENT 'money in is positive, money out negative',
    -- The journal entry this bank line is. An entry, not a line: reposting a
    -- document rewrites its lines but keeps its entry.
    matched_entry_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_statement_line (statement_id),
    KEY idx_statement_line_entry (matched_entry_id),
    CONSTRAINT fk_statement_line_statement FOREIGN KEY (statement_id) REFERENCES bank_statements(id) ON DELETE CASCADE,
    CONSTRAINT fk_statement_line_entry FOREIGN KEY (matched_entry_id) REFERENCES gl_journal_entries(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
    ('supplier_bills', 'Supplier bills', 'Accounting', 322),
    ('supplier_payments', 'Supplier payments', 'Accounting', 324),
    ('disbursements', 'Disbursements', 'Commercial', 126),
    ('bank_statements', 'Bank reconciliation', 'Accounting', 326)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.k, 1, 1, 1, 1, 1 FROM roles r
CROSS JOIN (SELECT 'supplier_bills' AS k UNION ALL SELECT 'supplier_payments' UNION ALL SELECT 'disbursements' UNION ALL SELECT 'bank_statements') p
WHERE r.role_key IN ('super_admin', 'finance')
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.k, 1, 0, 0, 0, 0 FROM roles r
CROSS JOIN (SELECT 'supplier_bills' AS k UNION ALL SELECT 'supplier_payments' UNION ALL SELECT 'disbursements' UNION ALL SELECT 'bank_statements') p
WHERE r.role_key = 'management'
ON DUPLICATE KEY UPDATE can_view = 1;

-- Logistics pays duty and port charges at the border on the customer's behalf.
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'disbursements', 1, 1, 1, 0, 0 FROM roles r WHERE r.role_key = 'logistics_manager'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1;

-- ======================================================== 20261002_000036_storage_and_containers.sql
-- Batch B: what a customer keeps in our warehouse, and the boxes we must give back.
--   * Storage lots: goods a customer leaves with us (a goods received note),
--     released a part at a time against a gate pass, billed per day after the
--     free days.
--   * Containers: the shipping line's box, from leaving the port full to coming
--     back empty, with the free days and the detention that runs after them.
-- Safe to run more than once.

CREATE TABLE IF NOT EXISTS storage_lots (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    lot_code VARCHAR(32) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    shipment_id INT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'Bags',
    quantity_received DECIMAL(12,2) NOT NULL,
    quantity_released DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    weight_kg DECIMAL(12,2) NULL,
    volume_m3 DECIMAL(12,3) NULL,
    bay_location VARCHAR(60) NULL,
    received_at DATETIME NOT NULL,
    received_from VARCHAR(150) NULL,
    vehicle_plate VARCHAR(30) NULL,
    condition_note VARCHAR(255) NULL,
    free_days INT UNSIGNED NOT NULL DEFAULT 7,
    charge_basis ENUM('per_unit_day','per_lot_day','per_m3_day','per_tonne_day') NOT NULL DEFAULT 'per_unit_day',
    daily_rate DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    currency CHAR(3) NOT NULL DEFAULT 'RWF',
    billed_until DATE NULL,
    status ENUM('in_store','partly_released','released') NOT NULL DEFAULT 'in_store',
    grn_file VARCHAR(255) NULL,
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_storage_lot (lot_code),
    KEY idx_storage_customer (customer_id, status),
    KEY idx_storage_warehouse (warehouse_id, status),
    CONSTRAINT fk_storage_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_storage_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
    CONSTRAINT fk_storage_shipment FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS storage_releases (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    gate_pass_no VARCHAR(32) NOT NULL,
    lot_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    released_at DATETIME NOT NULL,
    collected_by VARCHAR(150) NOT NULL,
    collected_id_no VARCHAR(60) NULL,
    vehicle_plate VARCHAR(30) NULL,
    trip_id INT UNSIGNED NULL,
    authorised_by INT UNSIGNED NULL,
    customer_order_ref VARCHAR(80) NULL,
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_gate_pass (gate_pass_no),
    KEY idx_release_lot (lot_id),
    CONSTRAINT fk_release_lot FOREIGN KEY (lot_id) REFERENCES storage_lots(id),
    CONSTRAINT fk_release_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS containers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tracking_code VARCHAR(32) NOT NULL,
    container_no VARCHAR(40) NOT NULL,
    container_size VARCHAR(20) NULL,
    shipping_line_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    shipment_id INT UNSIGNED NULL,
    trip_id INT UNSIGNED NULL,
    bill_of_lading VARCHAR(60) NULL,
    picked_up_at DATETIME NOT NULL COMMENT 'gate-out full from the port or depot; the free days run from here',
    picked_up_from VARCHAR(120) NULL,
    free_days INT UNSIGNED NOT NULL DEFAULT 14,
    detention_rate DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'per day after the free days',
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    unloaded_at DATETIME NULL,
    returned_at DATETIME NULL COMMENT 'gate-in empty at the return depot',
    return_depot VARCHAR(120) NULL,
    return_interchange_no VARCHAR(60) NULL,
    deposit_amount DECIMAL(14,2) NULL,
    deposit_refunded_at DATE NULL,
    status ENUM('out_full','unloaded','returned') NOT NULL DEFAULT 'out_full',
    disbursement_id INT UNSIGNED NULL,
    interchange_file VARCHAR(255) NULL,
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_container_tracking (tracking_code),
    KEY idx_container_no (container_no),
    KEY idx_container_status (status, picked_up_at),
    CONSTRAINT fk_container_line FOREIGN KEY (shipping_line_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    CONSTRAINT fk_container_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_container_shipment FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE SET NULL,
    CONSTRAINT fk_container_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
    ('storage_lots', 'Customer storage', 'Warehouse', 205),
    ('storage_releases', 'Gate passes', 'Warehouse', 206),
    ('containers', 'Containers', 'Transport', 95)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.k, 1, 1, 1, 1, 1 FROM roles r
CROSS JOIN (SELECT 'storage_lots' AS k UNION ALL SELECT 'storage_releases' UNION ALL SELECT 'containers') p
WHERE r.role_key = 'super_admin'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.k, 1, 1, 1, 0, 1 FROM roles r
CROSS JOIN (SELECT 'storage_lots' AS k UNION ALL SELECT 'storage_releases') p
WHERE r.role_key = 'warehouse_manager'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'containers', 1, 1, 1, 0, 1 FROM roles r WHERE r.role_key = 'logistics_manager'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.k, 1, 0, 0, 0, 0 FROM roles r
CROSS JOIN (SELECT 'storage_lots' AS k UNION ALL SELECT 'storage_releases' UNION ALL SELECT 'containers') p
WHERE r.role_key IN ('finance', 'management', 'logistics_manager')
ON DUPLICATE KEY UPDATE can_view = 1;

-- ======================================================== 20261002_000037_workshop_and_journeys.sql
-- Batch C: the garage, and journeys longer than one truck.
--   * Work orders become job cards: in our own workshop or outside, the
--     mechanic, what the driver reported, what was found, what was done, the
--     hours; part lines can come from our stock and are issued on completion.
--   * A journey is one customer movement made of several legs (trips): Dar es
--     Salaam to Rusumo on one truck, Rusumo to Kigali on another. The cargo is
--     handed from leg to leg and its path is kept.
-- Safe to run more than once.

ALTER TABLE maintenance_orders
    ADD COLUMN IF NOT EXISTS workshop ENUM('in_house','external') NOT NULL DEFAULT 'external' AFTER maintenance_type,
    ADD COLUMN IF NOT EXISTS supplier_id INT UNSIGNED NULL AFTER provider_name,
    ADD COLUMN IF NOT EXISTS mechanic_id INT UNSIGNED NULL AFTER supplier_id,
    ADD COLUMN IF NOT EXISTS reported_by_driver_id INT UNSIGNED NULL AFTER mechanic_id,
    ADD COLUMN IF NOT EXISTS complaint TEXT NULL AFTER reported_by_driver_id,
    ADD COLUMN IF NOT EXISTS diagnosis TEXT NULL AFTER complaint,
    ADD COLUMN IF NOT EXISTS work_done TEXT NULL AFTER diagnosis,
    ADD COLUMN IF NOT EXISTS labour_hours DECIMAL(8,2) NULL AFTER work_done,
    ADD COLUMN IF NOT EXISTS next_service_km INT UNSIGNED NULL AFTER odometer_reading,
    ADD COLUMN IF NOT EXISTS next_service_date DATE NULL AFTER next_service_km,
    ADD COLUMN IF NOT EXISTS stock_issued_at DATETIME NULL AFTER completed_at;

ALTER TABLE maintenance_parts
    ADD COLUMN IF NOT EXISTS item_id INT UNSIGNED NULL AFTER line_type;

CREATE TABLE IF NOT EXISTS journeys (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    journey_code VARCHAR(32) NOT NULL,
    customer_id INT UNSIGNED NULL,
    request_id INT UNSIGNED NULL,
    origin VARCHAR(150) NOT NULL,
    final_destination VARCHAR(150) NOT NULL,
    route_via VARCHAR(255) NULL,
    planned_start DATE NULL,
    planned_end DATE NULL,
    status ENUM('planned','in_progress','completed','cancelled') NOT NULL DEFAULT 'planned',
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_journey (journey_code),
    KEY idx_journey_status (status),
    CONSTRAINT fk_journey_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_journey_request FOREIGN KEY (request_id) REFERENCES transport_requests(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS journey_id INT UNSIGNED NULL AFTER request_id,
    ADD COLUMN IF NOT EXISTS leg_no TINYINT UNSIGNED NULL AFTER journey_id,
    ADD INDEX IF NOT EXISTS idx_trip_journey (journey_id, leg_no);

ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS journey_id INT UNSIGNED NULL AFTER trip_id;

-- Every truck a consignment has been on, in order, so a load that changed
-- trucks twice still answers "where has it been".
CREATE TABLE IF NOT EXISTS shipment_legs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT UNSIGNED NOT NULL,
    trip_id INT UNSIGNED NOT NULL,
    leg_no TINYINT UNSIGNED NULL,
    handed_over_at DATETIME NULL COMMENT 'when it left this truck for the next one',
    handover_point VARCHAR(150) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_shipment_leg (shipment_id, trip_id),
    CONSTRAINT fk_shipment_leg_shipment FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    CONSTRAINT fk_shipment_leg_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
    ('journeys', 'Journeys (multi-leg)', 'Transport', 52)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'journeys', 1, 1, 1, 1, 1 FROM roles r WHERE r.role_key IN ('super_admin', 'logistics_manager')
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'journeys', 1, 0, 0, 0, 0 FROM roles r WHERE r.role_key IN ('finance', 'management', 'fleet_manager')
ON DUPLICATE KEY UPDATE can_view = 1;

-- The people in the garage: they open and fill job cards; the fleet manager
-- approves the cost.
INSERT IGNORE INTO roles (role_key, role_name) VALUES ('workshop', 'Workshop');

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.k, 1, p.c, p.c, 0, 0 FROM roles r
CROSS JOIN (SELECT 'dashboard' AS k, 0 AS c UNION ALL SELECT 'maintenance', 1 UNION ALL SELECT 'vehicles', 0
            UNION ALL SELECT 'warehouse', 0 UNION ALL SELECT 'movements', 0 UNION ALL SELECT 'vehicle_documents', 0) p
WHERE r.role_key = 'workshop'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = VALUES(can_create), can_edit = VALUES(can_edit);

-- ======================================================== record the four updates as applied
INSERT IGNORE INTO migrations (migration) VALUES
    ('20261002_000034_driver_papers.sql'),
    ('20261002_000035_payables_disbursements_bank.sql'),
    ('20261002_000036_storage_and_containers.sql'),
    ('20261002_000037_workshop_and_journeys.sql');

-- Check: should list all four.
SELECT migration, applied_at FROM migrations WHERE migration LIKE '20261002_%' ORDER BY migration;
