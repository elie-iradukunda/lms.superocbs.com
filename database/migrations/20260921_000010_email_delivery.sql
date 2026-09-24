-- Email delivery.
--
-- Every message the system means to send is written here first and only then
-- handed to the mail provider. That way nothing is lost when mail is switched
-- off or the provider is unreachable: the message is still on record, with the
-- reason it did not go, and can be retried.

CREATE TABLE IF NOT EXISTS email_outbox (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- A caller-supplied key makes a message idempotent: the same alert raised
    -- twice is stored and sent once.
    message_key VARCHAR(120) NOT NULL UNIQUE,
    category ENUM('notification','alert','password_reset','new_account','invoice','manual','test') NOT NULL DEFAULT 'notification',
    to_email VARCHAR(190) NOT NULL,
    to_name VARCHAR(150) NULL,
    user_id INT UNSIGNED NULL,
    subject VARCHAR(200) NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    body_text MEDIUMTEXT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id VARCHAR(64) NULL,
    status ENUM('queued','sent','failed','skipped') NOT NULL DEFAULT 'queued',
    provider_id VARCHAR(120) NULL,
    error VARCHAR(500) NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    sent_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_outbox_status (status, created_at),
    INDEX idx_outbox_recipient (to_email),
    INDEX idx_outbox_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Someone who does not want their inbox filled can be taken off email without
-- losing the on-screen bell.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS notify_by_email TINYINT(1) NOT NULL DEFAULT 1 AFTER must_change_password;

-- The outbox is its own page, so the permission is its own too.
INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('email', 'Email outbox', 'Administration', 225)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'email', 1, 1, 1, 1, 1 FROM roles r WHERE r.role_key = 'super_admin'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'email', 1, 0, 1, 0, 0 FROM roles r WHERE r.role_key = 'finance'
ON DUPLICATE KEY UPDATE can_view = 1, can_edit = 1;

INSERT INTO company_settings (setting_key, setting_value, setting_label, setting_group, input_type) VALUES
('email_enabled', '1', 'Send email notifications', 'Email', 'number'),
('email_signature', 'This message was sent by the LMS logistics system. Please do not reply to it directly.', 'Signature at the foot of every email', 'Email', 'text'),
('email_min_severity', 'info', 'Least important severity to email (info, success, warning, danger)', 'Email', 'text'),
('email_alerts_to_roles', '1', 'Email the operational alerts (expiring documents, low stock, overdue invoices)', 'Email', 'number')
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label), setting_group = VALUES(setting_group), input_type = VALUES(input_type);
