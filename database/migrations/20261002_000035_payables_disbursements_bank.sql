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
