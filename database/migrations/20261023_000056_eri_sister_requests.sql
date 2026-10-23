-- ERI specification, section 17: sister-company requests (F11).
--
-- A sister company (e.g. ERI Tanzania) raises F11 for its own truck; the
-- receiving company (e.g. ERI Rwanda) confirms it and forwards it, which opens
-- a job card and a stores issue note there (section 12). When the job closes,
-- an inter-company invoice is made: a receivable and income in the company
-- that did the work, an expense on the truck and a payable in the company that
-- asked.
--
-- Safe to run more than once.

CREATE TABLE IF NOT EXISTS intercompany_invoices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(40) NOT NULL,
    from_company_id INT UNSIGNED NOT NULL COMMENT 'the company that did the work and is owed',
    to_company_id INT UNSIGNED NOT NULL COMMENT 'the company that asked and owes',
    request_id INT UNSIGNED NULL COMMENT 'the F11',
    job_card_id INT UNSIGNED NULL,
    vehicle_id INT UNSIGNED NULL COMMENT 'the sister truck',
    currency CHAR(3) NOT NULL,
    parts_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    labour_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    status ENUM('issued','paid','cancelled') NOT NULL DEFAULT 'issued',
    from_entry_id INT UNSIGNED NULL COMMENT 'the receivable/income entry in the doing company',
    to_entry_id INT UNSIGNED NULL COMMENT 'the expense/payable entry in the asking company',
    to_bill_id INT UNSIGNED NULL COMMENT 'the supplier bill raised in the asking company',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ici_from (from_company_id),
    KEY idx_ici_to (to_company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO company_settings (setting_key, setting_value, setting_label, setting_group, input_type) VALUES
('intercompany_invoice_format', 'ICI-{YEAR}-{SEQ4}', 'Inter-company invoice number', 'Accounting', 'text'),
('intercompany_markup_percent', '0', 'Markup a company adds on work done for a sister company (%)', 'Accounting', 'number')
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label), setting_group = VALUES(setting_group);

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('intercompany', 'Inter-company invoices', 'Accounting', 168)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label);
