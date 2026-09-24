-- Paying by cheque, and a budget to measure the year against.
--
-- The books could already say what had been spent. They could not say what was
-- promised but not yet taken from the bank — a cheque written on Friday and
-- presented the following week — and they had nothing to compare the year with.
-- Both are ordinary requirements of a business that banks like one.
--
-- A cheque is not just a payment record. It is a numbered leaf out of a book,
-- drawn on one account, that the bank may take days to clear, and that can be
-- cancelled without ever being cashed. Each of those is a state, and the number
-- must never be reused, which is why void keeps its row instead of deleting it.

-- --------------------------------------------------------------- cheque books
-- A bank issues a book of numbered leaves. Recording the range lets the system
-- offer the next number rather than asking somebody to remember it, and makes a
-- missing leaf visible.
CREATE TABLE IF NOT EXISTS gl_cheque_books (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bank_account_id INT UNSIGNED NOT NULL COMMENT 'gl_accounts.id, an account marked is_bank',
    prefix VARCHAR(20) NULL COMMENT 'when the leaves carry letters as well as digits',
    first_no BIGINT UNSIGNED NOT NULL,
    last_no BIGINT UNSIGNED NOT NULL,
    -- Only ever moves forward: a spoiled leaf is spent, not returned.
    next_no BIGINT UNSIGNED NOT NULL,
    status ENUM('active', 'finished') NOT NULL DEFAULT 'active',
    notes VARCHAR(255) NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cheque_book_account (bank_account_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------- cheques
-- draft      being prepared; nothing posted, no money moved
-- issued     posted to the ledger, the money is off the bank account
-- presented  the bank has taken it, seen on a statement
-- void       cancelled; the row and the number stay, the amount reads zero
CREATE TABLE IF NOT EXISTS gl_cheques (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(40) NOT NULL COMMENT 'CHQ-2026-0001, issued by the system',
    cheque_no VARCHAR(40) NULL COMMENT 'the number printed on the paper leaf',
    bank_account_id INT UNSIGNED NOT NULL,
    cheque_book_id INT UNSIGNED NULL,

    payee_name VARCHAR(200) NOT NULL COMMENT 'exactly as written on the leaf',
    supplier_id INT UNSIGNED NULL COMMENT 'when the payee is a supplier on file',
    payee_address VARCHAR(255) NULL,

    cheque_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    memo VARCHAR(255) NULL,

    status ENUM('draft', 'issued', 'presented', 'void') NOT NULL DEFAULT 'draft',
    entry_id INT UNSIGNED NULL COMMENT 'gl_journal_entries, once posted',

    prepared_by INT UNSIGNED NULL,
    issued_by INT UNSIGNED NULL,
    issued_at DATETIME NULL,
    printed_at DATETIME NULL,
    presented_on DATE NULL,
    void_by INT UNSIGNED NULL,
    void_at DATETIME NULL,
    void_reason VARCHAR(255) NULL,

    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_cheque_reference (reference),
    -- The rule that matters: one number per bank account, once, for ever.
    UNIQUE KEY uq_cheque_no_account (bank_account_id, cheque_no),
    INDEX idx_cheque_date (cheque_date),
    INDEX idx_cheque_status (status),
    INDEX idx_cheque_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One cheque can settle several things, each belonging to its own account.
-- These are the debit side; the credit is the bank, one line for the whole
-- cheque, because one line is what the bank statement shows.
CREATE TABLE IF NOT EXISTS gl_cheque_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cheque_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL COMMENT 'gl_accounts.id, what the money was for',
    description VARCHAR(255) NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    INDEX idx_cheque_line (cheque_id),
    INDEX idx_cheque_line_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE gl_cheques DROP FOREIGN KEY IF EXISTS fk_cheque_bank;
ALTER TABLE gl_cheques ADD CONSTRAINT fk_cheque_bank FOREIGN KEY (bank_account_id) REFERENCES gl_accounts(id);
ALTER TABLE gl_cheque_lines DROP FOREIGN KEY IF EXISTS fk_cheque_line_cheque;
ALTER TABLE gl_cheque_lines ADD CONSTRAINT fk_cheque_line_cheque FOREIGN KEY (cheque_id) REFERENCES gl_cheques(id) ON DELETE CASCADE;
ALTER TABLE gl_cheque_lines DROP FOREIGN KEY IF EXISTS fk_cheque_line_account;
ALTER TABLE gl_cheque_lines ADD CONSTRAINT fk_cheque_line_account FOREIGN KEY (account_id) REFERENCES gl_accounts(id);
ALTER TABLE gl_cheque_books DROP FOREIGN KEY IF EXISTS fk_cheque_book_bank;
ALTER TABLE gl_cheque_books ADD CONSTRAINT fk_cheque_book_bank FOREIGN KEY (bank_account_id) REFERENCES gl_accounts(id);

-- --------------------------------------------------------------------- budget
-- What the year is expected to cost and earn, per account. Month 0 is the
-- annual figure; 1 to 12 are the monthly ones, for a business that budgets that
-- way. Actuals are never stored here — they are read from the ledger, so the
-- comparison cannot drift.
CREATE TABLE IF NOT EXISTS gl_budgets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fiscal_year SMALLINT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = the whole year, 1-12 = one month',
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    notes VARCHAR(255) NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_budget_line (fiscal_year, account_id, month),
    INDEX idx_budget_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE gl_budgets DROP FOREIGN KEY IF EXISTS fk_budget_account;
ALTER TABLE gl_budgets ADD CONSTRAINT fk_budget_account FOREIGN KEY (account_id) REFERENCES gl_accounts(id);

-- ---------------------------------------------------------------- permissions
INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('cheques', 'Cheques', 'Accounting', 320),
('cheque_books', 'Cheque books', 'Accounting', 330),
('budgets', 'Budget', 'Accounting', 350)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.k, 1, 1, 1, 1, 1
  FROM roles r
  JOIN (SELECT 'cheques' k UNION ALL SELECT 'cheque_books' UNION ALL SELECT 'budgets') p
 WHERE r.role_key IN ('super_admin', 'finance')
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

-- Management reads the money without touching it.
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.k, 1, 0, 0, 0, 0
  FROM roles r
  JOIN (SELECT 'cheques' k UNION ALL SELECT 'cheque_books' UNION ALL SELECT 'budgets') p
 WHERE r.role_key = 'management'
ON DUPLICATE KEY UPDATE can_view = 1;

-- A cheque is drawn on a bank account, so the chart needs to say which accounts
-- are banks. The seeded cash and bank accounts already are; this is for anyone
-- whose chart predates the flag.
UPDATE gl_accounts SET is_bank = 1
 WHERE deleted_at IS NULL AND is_header = 0 AND is_bank = 0
   AND report_section = 'Cash and bank';
