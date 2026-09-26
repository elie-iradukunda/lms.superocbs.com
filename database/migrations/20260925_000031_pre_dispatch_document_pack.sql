-- A shipment's document pack is prepared before the truck leaves. Customs staff
-- are external to LMS; they inspect these documents, they do not log in here.

CREATE TABLE IF NOT EXISTS shipment_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT UNSIGNED NOT NULL,
    document_type VARCHAR(80) NOT NULL,
    document_number VARCHAR(80) NULL,
    issue_date DATE NULL,
    expiry_date DATE NULL,
    status ENUM('pending', 'valid', 'rejected', 'expired') NOT NULL DEFAULT 'pending',
    document_file VARCHAR(255) NULL,
    uploaded_by INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_shipment_document_type (shipment_id, document_type),
    KEY idx_shipment_document_status (shipment_id, status),
    CONSTRAINT fk_shipment_document_shipment FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    CONSTRAINT fk_shipment_document_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS driver_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    driver_id INT UNSIGNED NOT NULL,
    document_type ENUM('driver_id', 'driving_licence', 'passport', 'permit', 'other') NOT NULL,
    document_number VARCHAR(80) NULL,
    issue_date DATE NULL,
    expiry_date DATE NULL,
    status ENUM('pending', 'valid', 'rejected', 'expired') NOT NULL DEFAULT 'pending',
    document_file VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_driver_document_type (driver_id, document_type),
    CONSTRAINT fk_driver_document_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipment_pre_dispatch_checks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT UNSIGNED NOT NULL,
    cargo_loaded TINYINT(1) NOT NULL DEFAULT 0,
    quantity_verified TINYINT(1) NOT NULL DEFAULT 0,
    packaging_verified TINYINT(1) NOT NULL DEFAULT 0,
    documents_verified TINYINT(1) NOT NULL DEFAULT 0,
    vehicle_verified TINYINT(1) NOT NULL DEFAULT 0,
    driver_verified TINYINT(1) NOT NULL DEFAULT 0,
    route_verified TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pending', 'ready', 'not_ready') NOT NULL DEFAULT 'pending',
    checked_by INT UNSIGNED NULL,
    checked_at DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pre_dispatch_shipment (shipment_id),
    CONSTRAINT fk_pre_dispatch_shipment FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    CONSTRAINT fk_pre_dispatch_user FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
    ('shipment_documents', 'Shipment documents', 'Transport', 245),
    ('pre_dispatch_checks', 'Pre-dispatch checks', 'Transport', 246),
    ('driver_documents', 'Driver documents', 'Transport', 247)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.permission_key, 1, 1, 1, 1, 1
FROM roles r CROSS JOIN (
    SELECT 'shipment_documents' AS permission_key UNION ALL SELECT 'pre_dispatch_checks' UNION ALL SELECT 'driver_documents'
) p WHERE r.role_key IN ('super_admin', 'logistics_manager')
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.permission_key, 1, 1, 1, 0, 0
FROM roles r CROSS JOIN (SELECT 'shipment_documents' AS permission_key UNION ALL SELECT 'pre_dispatch_checks') p
WHERE r.role_key = 'warehouse_manager'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 0, can_approve = 0;
