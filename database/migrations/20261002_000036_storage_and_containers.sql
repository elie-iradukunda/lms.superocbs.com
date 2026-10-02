-- Batch B: what a customer keeps in our warehouse, and the boxes we must give back.
--   * Storage lots: goods a customer leaves with us (a goods received note),
--     released a part at a time against a gate pass, billed per day after the
--     free days.
--   * Containers: the shipping line's box, from leaving the port full to coming
--     back empty, with the free days and the detention that runs after them.
-- Safe to run more than once.

CREATE TABLE IF NOT EXISTS storage_lots (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    lot_code VARCHAR(32) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    shipment_id INT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'Bags',
    quantity_received DECIMAL(12,2) NOT NULL,
    quantity_released DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    weight_kg DECIMAL(12,2) NULL,
    volume_m3 DECIMAL(12,3) NULL,
    bay_location VARCHAR(60) NULL,
    received_at DATETIME NOT NULL,
    received_from VARCHAR(150) NULL,
    vehicle_plate VARCHAR(30) NULL,
    condition_note VARCHAR(255) NULL,
    free_days INT UNSIGNED NOT NULL DEFAULT 7,
    charge_basis ENUM('per_unit_day','per_lot_day','per_m3_day','per_tonne_day') NOT NULL DEFAULT 'per_unit_day',
    daily_rate DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    currency CHAR(3) NOT NULL DEFAULT 'RWF',
    billed_until DATE NULL,
    status ENUM('in_store','partly_released','released') NOT NULL DEFAULT 'in_store',
    grn_file VARCHAR(255) NULL,
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_storage_lot (lot_code),
    KEY idx_storage_customer (customer_id, status),
    KEY idx_storage_warehouse (warehouse_id, status),
    CONSTRAINT fk_storage_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_storage_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
    CONSTRAINT fk_storage_shipment FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS storage_releases (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    gate_pass_no VARCHAR(32) NOT NULL,
    lot_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    released_at DATETIME NOT NULL,
    collected_by VARCHAR(150) NOT NULL,
    collected_id_no VARCHAR(60) NULL,
    vehicle_plate VARCHAR(30) NULL,
    trip_id INT UNSIGNED NULL,
    authorised_by INT UNSIGNED NULL,
    customer_order_ref VARCHAR(80) NULL,
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_gate_pass (gate_pass_no),
    KEY idx_release_lot (lot_id),
    CONSTRAINT fk_release_lot FOREIGN KEY (lot_id) REFERENCES storage_lots(id),
    CONSTRAINT fk_release_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS containers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tracking_code VARCHAR(32) NOT NULL,
    container_no VARCHAR(40) NOT NULL,
    container_size VARCHAR(20) NULL,
    shipping_line_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    shipment_id INT UNSIGNED NULL,
    trip_id INT UNSIGNED NULL,
    bill_of_lading VARCHAR(60) NULL,
    picked_up_at DATETIME NOT NULL COMMENT 'gate-out full from the port or depot; the free days run from here',
    picked_up_from VARCHAR(120) NULL,
    free_days INT UNSIGNED NOT NULL DEFAULT 14,
    detention_rate DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'per day after the free days',
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    unloaded_at DATETIME NULL,
    returned_at DATETIME NULL COMMENT 'gate-in empty at the return depot',
    return_depot VARCHAR(120) NULL,
    return_interchange_no VARCHAR(60) NULL,
    deposit_amount DECIMAL(14,2) NULL,
    deposit_refunded_at DATE NULL,
    status ENUM('out_full','unloaded','returned') NOT NULL DEFAULT 'out_full',
    disbursement_id INT UNSIGNED NULL,
    interchange_file VARCHAR(255) NULL,
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_container_tracking (tracking_code),
    KEY idx_container_no (container_no),
    KEY idx_container_status (status, picked_up_at),
    CONSTRAINT fk_container_line FOREIGN KEY (shipping_line_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    CONSTRAINT fk_container_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_container_shipment FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE SET NULL,
    CONSTRAINT fk_container_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
    ('storage_lots', 'Customer storage', 'Warehouse', 205),
    ('storage_releases', 'Gate passes', 'Warehouse', 206),
    ('containers', 'Containers', 'Transport', 95)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.k, 1, 1, 1, 1, 1 FROM roles r
CROSS JOIN (SELECT 'storage_lots' AS k UNION ALL SELECT 'storage_releases' UNION ALL SELECT 'containers') p
WHERE r.role_key = 'super_admin'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.k, 1, 1, 1, 0, 1 FROM roles r
CROSS JOIN (SELECT 'storage_lots' AS k UNION ALL SELECT 'storage_releases') p
WHERE r.role_key = 'warehouse_manager'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'containers', 1, 1, 1, 0, 1 FROM roles r WHERE r.role_key = 'logistics_manager'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.k, 1, 0, 0, 0, 0 FROM roles r
CROSS JOIN (SELECT 'storage_lots' AS k UNION ALL SELECT 'storage_releases' UNION ALL SELECT 'containers') p
WHERE r.role_key IN ('finance', 'management', 'logistics_manager')
ON DUPLICATE KEY UPDATE can_view = 1;
