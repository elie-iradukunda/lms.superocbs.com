-- ERI specification, section 14: a manual journal is verified and authorized
-- like any request before it reaches the books.
--
-- Prepared (an accountant) -> Verified (Finance Manager) -> Authorized (MD)
-- -> posted. When the Finance Manager prepares it, preparing counts as the
-- verification and it goes straight to the MD.
--
-- Safe to run more than once.

CREATE TABLE IF NOT EXISTS journal_drafts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    draft_no VARCHAR(40) NULL,
    entry_date DATE NOT NULL,
    memo VARCHAR(255) NOT NULL,
    reference VARCHAR(60) NULL,
    currency CHAR(3) NOT NULL,
    lines_json LONGTEXT NOT NULL COMMENT 'JSON lines: account, description, debit, credit',
    total DECIMAL(18,2) NOT NULL DEFAULT 0,
    status ENUM('sent','verified','posted','refused','cancelled') NOT NULL DEFAULT 'sent',
    prepared_by INT UNSIGNED NULL,
    prepared_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verified_by INT UNSIGNED NULL,
    verified_at DATETIME NULL,
    authorized_by INT UNSIGNED NULL,
    authorized_at DATETIME NULL,
    refused_by INT UNSIGNED NULL,
    refused_at DATETIME NULL,
    refused_reason VARCHAR(255) NULL,
    entry_id INT UNSIGNED NULL COMMENT 'the journal entry once posted',
    KEY idx_journal_drafts_company (company_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO company_settings (setting_key, setting_value, setting_label, setting_group, input_type) VALUES
('journal_number_format', 'JV-{YEAR}-{SEQ4}', 'Manual journal number', 'Accounting', 'text'),
('backup_keep_days', '30', 'Keep daily database backups for (days)', 'System', 'number'),
('backup_folder', '', 'Backup folder (empty = storage/backups)', 'System', 'text')
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label), setting_group = VALUES(setting_group);
