-- ERI specification, sections 14 and 21.
--
-- Audit log: every action with who, role, company, record, values before and
-- after, time and device. Nobody can change or delete it: the database itself
-- refuses an UPDATE or DELETE on the table.
--
-- Opening balances: the trial balance and the open customer and supplier
-- balances exported from Tally at the go-live date, loaded per company,
-- checked by Finance and signed off by the MD before they post.
--
-- Safe to run more than once.

ALTER TABLE audit_logs
    ADD COLUMN IF NOT EXISTS role_key VARCHAR(50) NULL AFTER user_id,
    ADD COLUMN IF NOT EXISTS before_values LONGTEXT NULL AFTER metadata,
    ADD COLUMN IF NOT EXISTS after_values LONGTEXT NULL AFTER before_values,
    ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL AFTER after_values,
    ADD COLUMN IF NOT EXISTS user_agent VARCHAR(255) NULL AFTER ip_address;

DROP TRIGGER IF EXISTS audit_logs_no_update;
CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The audit log cannot be changed.';
DROP TRIGGER IF EXISTS audit_logs_no_delete;
CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The audit log cannot be deleted.';

CREATE TABLE IF NOT EXISTS opening_balances (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    as_of DATE NOT NULL COMMENT 'the go-live date; balances are as at the day before',
    kind ENUM('ledger','customers','suppliers') NOT NULL DEFAULT 'ledger',
    rows_json LONGTEXT NOT NULL COMMENT 'JSON rows as loaded',
    status ENUM('draft','checked','signed') NOT NULL DEFAULT 'draft',
    prepared_by INT UNSIGNED NULL,
    checked_by INT UNSIGNED NULL,
    checked_at DATETIME NULL,
    signed_by INT UNSIGNED NULL,
    signed_at DATETIME NULL,
    entry_ids VARCHAR(255) NULL COMMENT 'the journal entries posted at sign-off',
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_opening_company (company_id, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO gl_accounts (account_code, account_name, account_type, normal_balance, report_section, section_order, cash_flow_class)
SELECT '3900', 'Opening balance equity', 'equity', 'credit', 'Equity', 90, 'financing' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM gl_accounts WHERE account_code = '3900');

ALTER TABLE invoices ADD COLUMN IF NOT EXISTS is_opening TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'an open balance brought over from Tally; already in the opening ledger';
ALTER TABLE supplier_bills ADD COLUMN IF NOT EXISTS is_opening TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'an open balance brought over from Tally; already in the opening ledger';

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('opening_balances', 'Opening balances (from Tally)', 'Accounting', 165)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label);
