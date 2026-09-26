-- Customs clearance is an extension of the existing shipment-linked border
-- crossing record. Existing crossings are retained and translated into the
-- fuller clearance state model below.

ALTER TABLE border_crossings
    ADD COLUMN IF NOT EXISTS submitted_at DATETIME NULL AFTER lodged_at,
    ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL AFTER submitted_at,
    ADD COLUMN IF NOT EXISTS released_at DATETIME NULL AFTER cleared_at,
    ADD COLUMN IF NOT EXISTS customs_country VARCHAR(60) NULL AFTER border_post_id,
    ADD INDEX IF NOT EXISTS idx_crossing_shipment (shipment_id);

ALTER TABLE border_crossings
    MODIFY COLUMN status ENUM(
        'expected', 'at_border', 'lodged', 'held', 'cleared', 'departed',
        'draft', 'documents_pending', 'declaration_submitted', 'under_review',
        'inspection', 'duties_pending', 'payment_pending', 'released', 'rejected'
    ) NOT NULL DEFAULT 'draft';

UPDATE border_crossings
   SET status = CASE status
       WHEN 'expected' THEN 'draft'
       WHEN 'at_border' THEN 'under_review'
       WHEN 'lodged' THEN 'declaration_submitted'
       WHEN 'held' THEN 'documents_pending'
       WHEN 'cleared' THEN 'cleared'
       WHEN 'departed' THEN 'released'
       ELSE 'draft'
   END;

ALTER TABLE border_crossings
    MODIFY COLUMN status ENUM(
        'draft', 'documents_pending', 'declaration_submitted', 'under_review',
        'inspection', 'duties_pending', 'payment_pending', 'cleared', 'released', 'rejected'
    ) NOT NULL DEFAULT 'draft';

ALTER TABLE shipments
    MODIFY COLUMN status ENUM(
        'draft', 'booked', 'loaded', 'cleared', 'released', 'in_transit',
        'delivered', 'returned', 'cancelled'
    ) NOT NULL DEFAULT 'draft';

UPDATE border_crossings
   SET submitted_at = COALESCE(submitted_at, lodged_at),
       approved_at = COALESCE(approved_at, cleared_at),
       released_at = COALESCE(released_at, departed_at);

ALTER TABLE border_documents
    ADD COLUMN IF NOT EXISTS status ENUM('pending', 'valid', 'rejected', 'expired') NOT NULL DEFAULT 'pending' AFTER is_received,
    ADD COLUMN IF NOT EXISTS uploaded_by INT UNSIGNED NULL AFTER document_file,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD INDEX IF NOT EXISTS idx_border_document_status (crossing_id, status);

UPDATE border_documents
   SET status = CASE
       WHEN expires_on IS NOT NULL AND expires_on < CURDATE() THEN 'expired'
       WHEN is_received = 1 THEN 'valid'
       ELSE 'pending'
   END;

ALTER TABLE border_documents
    DROP FOREIGN KEY IF EXISTS fk_border_document_uploader;
ALTER TABLE border_documents
    ADD CONSTRAINT fk_border_document_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE border_charges
    ADD COLUMN IF NOT EXISTS tax_rate DECIMAL(8,2) NULL AFTER receipt_no,
    ADD COLUMN IF NOT EXISTS taxable_amount DECIMAL(14,2) NULL AFTER tax_rate,
    ADD COLUMN IF NOT EXISTS currency CHAR(3) NOT NULL DEFAULT 'RWF' AFTER amount,
    ADD COLUMN IF NOT EXISTS status ENUM('pending', 'paid', 'waived') NOT NULL DEFAULT 'pending' AFTER currency,
    ADD INDEX IF NOT EXISTS idx_border_charge_status (crossing_id, status);

UPDATE border_charges bc
INNER JOIN border_crossings c ON c.id = bc.crossing_id
   SET bc.currency = c.currency
 WHERE bc.currency = 'RWF' AND c.currency <> 'RWF';

CREATE TABLE IF NOT EXISTS clearance_inspections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    crossing_id INT UNSIGNED NOT NULL,
    inspection_type VARCHAR(100) NOT NULL,
    scheduled_at DATETIME NULL,
    location VARCHAR(150) NULL,
    inspector VARCHAR(120) NULL,
    result ENUM('pending', 'passed', 'failed') NOT NULL DEFAULT 'pending',
    remarks TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_clearance_inspection (crossing_id, result),
    CONSTRAINT fk_clearance_inspection_crossing FOREIGN KEY (crossing_id) REFERENCES border_crossings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clearance_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    crossing_id INT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'RWF',
    payment_method VARCHAR(80) NULL,
    reference VARCHAR(80) NULL,
    payment_date DATE NULL,
    status ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_clearance_payment (crossing_id, status),
    CONSTRAINT fk_clearance_payment_crossing FOREIGN KEY (crossing_id) REFERENCES border_crossings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clearance_releases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    crossing_id INT UNSIGNED NOT NULL,
    release_number VARCHAR(80) NOT NULL,
    release_date DATE NOT NULL,
    released_by INT UNSIGNED NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_clearance_release_crossing (crossing_id),
    UNIQUE KEY uq_clearance_release_number (release_number),
    CONSTRAINT fk_clearance_release_crossing FOREIGN KEY (crossing_id) REFERENCES border_crossings(id) ON DELETE CASCADE,
    CONSTRAINT fk_clearance_release_user FOREIGN KEY (released_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
    ('clearance_documents', 'Clearance documents', 'Transport', 251),
    ('clearance_inspections', 'Clearance inspections', 'Transport', 252),
    ('clearance_payments', 'Clearance payments', 'Transport', 253),
    ('clearance_releases', 'Clearance releases', 'Transport', 254)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.permission_key, 1, 1, 1, 1, 1
  FROM roles r
 CROSS JOIN (
     SELECT 'clearance_documents' AS permission_key
     UNION ALL SELECT 'clearance_inspections'
     UNION ALL SELECT 'clearance_payments'
     UNION ALL SELECT 'clearance_releases'
 ) p
 WHERE r.role_key IN ('super_admin', 'logistics_manager')
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.permission_key, 1, 1, 1, 0, 0
  FROM roles r
 CROSS JOIN (
     SELECT 'clearance_documents' AS permission_key
     UNION ALL SELECT 'clearance_payments'
 ) p
 WHERE r.role_key = 'finance'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 0, can_approve = 0;

INSERT INTO lookup_values (list_key, value, label, sort_order, is_active) VALUES
    ('border_document', 'Air waybill', 'Air waybill', 115, 1),
    ('border_document', 'Import permit', 'Import permit', 120, 1),
    ('border_document', 'Export permit', 'Export permit', 125, 1),
    ('border_document', 'Insurance certificate', 'Insurance certificate', 130, 1),
    ('border_document', 'Other', 'Other', 999, 1)
ON DUPLICATE KEY UPDATE label = VALUES(label), is_active = VALUES(is_active);
