-- ERI specification, section 19: notifications. Every hand-off goes to the
-- in-app inbox (the bell) and to email with the full PDF; WhatsApp carries a
-- short message with a link, sent from the company's WhatsApp Business
-- number. Each user chooses which updates they also want on WhatsApp.
--
-- Safe to run more than once.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS whatsapp_topics VARCHAR(255) NULL COMMENT 'comma-separated topics also sent on WhatsApp; blank: the defaults of the role' AFTER whatsapp_number;

CREATE TABLE IF NOT EXISTS whatsapp_outbox (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    message_key VARCHAR(120) NOT NULL,
    user_id INT UNSIGNED NULL,
    to_number VARCHAR(40) NOT NULL,
    topic VARCHAR(30) NULL,
    body TEXT NOT NULL,
    link VARCHAR(255) NULL,
    status ENUM('queued','sent','failed','skipped') NOT NULL DEFAULT 'queued',
    provider_id VARCHAR(120) NULL,
    error VARCHAR(255) NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    sent_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_whatsapp_key (message_key),
    KEY idx_whatsapp_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO company_settings (setting_key, setting_value, setting_label, setting_group, input_type) VALUES
('whatsapp_enabled', '0', 'Send WhatsApp messages (1 = yes)', 'WhatsApp', 'number'),
('whatsapp_business_number', '', 'Company WhatsApp Business number', 'WhatsApp', 'text'),
('whatsapp_phone_number_id', '', 'WhatsApp Cloud API: phone number ID', 'WhatsApp', 'text'),
('whatsapp_access_token', '', 'WhatsApp Cloud API: access token', 'WhatsApp', 'text'),
('whatsapp_api_version', 'v19.0', 'WhatsApp Cloud API version', 'WhatsApp', 'text'),
('whatsapp_template', '', 'Approved message template (blank: plain text, only inside 24 h of the user writing)', 'WhatsApp', 'text'),
('whatsapp_template_language', 'en', 'Template language code', 'WhatsApp', 'text'),
('whatsapp_country_code', '250', 'Country code added to numbers written without one', 'WhatsApp', 'text')
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label), setting_group = VALUES(setting_group);

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('whatsapp_outbox', 'WhatsApp outbox', 'Administration', 221)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label);
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'whatsapp_outbox', 1, 0, 1, 0, 0 FROM roles r WHERE r.role_key = 'super_admin'
ON DUPLICATE KEY UPDATE can_view = 1, can_edit = 1;

-- The four currencies of the specification (section 14).
UPDATE currencies SET is_active = 0 WHERE code NOT IN ('RWF', 'USD', 'KES', 'TZS');
UPDATE permissions SET permission_label = 'Exchange rates', permission_group = 'Accounting' WHERE permission_key = 'currency_rates';
