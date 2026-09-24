-- Double-entry accounting: a chart of accounts, a journal, and the ledger the
-- accounting books are read from.
--
-- Balances are never stored. Every figure in the Trial Balance, Profit & Loss,
-- Balance Sheet and Cash Flow statement is derived from the journal lines, so
-- an edited or reversed entry corrects its own balance with no rebuild step.

CREATE TABLE IF NOT EXISTS gl_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_code VARCHAR(20) NOT NULL UNIQUE,
    account_name VARCHAR(150) NOT NULL,
    account_type ENUM('asset','liability','equity','income','cost_of_sales','expense') NOT NULL,
    normal_balance ENUM('debit','credit') NOT NULL,
    parent_id INT UNSIGNED NULL,
    is_header TINYINT(1) NOT NULL DEFAULT 0,
    is_contra TINYINT(1) NOT NULL DEFAULT 0,
    depth TINYINT UNSIGNED NOT NULL DEFAULT 0,
    report_section VARCHAR(60) NOT NULL DEFAULT 'Other',
    section_order SMALLINT UNSIGNED NOT NULL DEFAULT 999,
    cash_flow_class ENUM('operating','investing','financing','cash','none') NOT NULL DEFAULT 'none',
    is_bank TINYINT(1) NOT NULL DEFAULT 0,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES gl_accounts(id) ON DELETE SET NULL,
    INDEX idx_gl_accounts_type (account_type),
    INDEX idx_gl_accounts_section (section_order, account_code),
    INDEX idx_gl_accounts_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gl_journal_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_no VARCHAR(32) NOT NULL UNIQUE,
    entry_date DATE NOT NULL,
    memo VARCHAR(255) NOT NULL,
    reference VARCHAR(64) NULL,
    -- Where the entry came from. A source pair is unique, so posting the same
    -- invoice twice replaces its entry instead of duplicating it.
    source_type VARCHAR(40) NULL,
    source_id INT UNSIGNED NULL,
    source_code VARCHAR(64) NULL,
    status ENUM('draft','posted','reversed') NOT NULL DEFAULT 'posted',
    reversed_by INT UNSIGNED NULL,
    posted_by INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_gl_source (source_type, source_id),
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_gl_entries_date (entry_date),
    INDEX idx_gl_entries_status (status),
    INDEX idx_gl_entries_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gl_journal_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_id INT UNSIGNED NOT NULL,
    line_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    account_id INT UNSIGNED NOT NULL,
    description VARCHAR(255) NULL,
    debit DECIMAL(16,2) NOT NULL DEFAULT 0,
    credit DECIMAL(16,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (entry_id) REFERENCES gl_journal_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES gl_accounts(id),
    INDEX idx_gl_lines_account (account_id),
    INDEX idx_gl_lines_entry (entry_id, line_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Closed periods stop a posting being back-dated into a month the accountant
-- has already reported on.
CREATE TABLE IF NOT EXISTS gl_fiscal_periods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_label VARCHAR(40) NOT NULL UNIQUE,
    starts_on DATE NOT NULL,
    ends_on DATE NOT NULL,
    status ENUM('open','closed') NOT NULL DEFAULT 'open',
    closed_by INT UNSIGNED NULL,
    closed_at DATETIME NULL,
    FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_gl_periods_range (starts_on, ends_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- permissions
INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('accounts', 'Chart of accounts', 'Accounting', 230),
('journal', 'Journal entries', 'Accounting', 240),
('books', 'Accounting books', 'Accounting', 250)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.permission_key, 1, 1, 1, 1, 1
FROM roles r CROSS JOIN permissions p
WHERE r.role_key = 'super_admin' AND p.permission_key IN ('accounts', 'journal', 'books')
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, v.k, v.vw, v.cr, v.ed, v.dl, v.ap FROM roles r JOIN (
              SELECT 'accounts' k, 1 vw, 1 cr, 1 ed, 0 dl, 0 ap
    UNION ALL SELECT 'journal', 1, 1, 1, 1, 1
    UNION ALL SELECT 'books', 1, 0, 0, 0, 0
) v WHERE r.role_key = 'finance'
ON DUPLICATE KEY UPDATE can_view = VALUES(can_view), can_create = VALUES(can_create), can_edit = VALUES(can_edit), can_delete = VALUES(can_delete), can_approve = VALUES(can_approve);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, v.k, 1, 0, 0, 0, 0 FROM roles r JOIN (
              SELECT 'books' k
    UNION ALL SELECT 'accounts'
) v WHERE r.role_key = 'management'
ON DUPLICATE KEY UPDATE can_view = 1;

-- ------------------------------------------------------------------ settings
INSERT INTO company_settings (setting_key, setting_value, setting_label, setting_group, input_type) VALUES
('accounting_basis', 'Accrual Basis', 'Reporting basis', 'Accounting', 'text'),
('fiscal_year_start', '01-01', 'Financial year starts (MM-DD)', 'Accounting', 'text'),
('gl_auto_post', '1', 'Post operational documents to the ledger automatically', 'Accounting', 'number')
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label), setting_group = VALUES(setting_group), input_type = VALUES(input_type);
