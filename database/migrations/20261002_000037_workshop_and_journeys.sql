-- Batch C: the garage, and journeys longer than one truck.
--   * Work orders become job cards: in our own workshop or outside, the
--     mechanic, what the driver reported, what was found, what was done, the
--     hours; part lines can come from our stock and are issued on completion.
--   * A journey is one customer movement made of several legs (trips): Dar es
--     Salaam to Rusumo on one truck, Rusumo to Kigali on another. The cargo is
--     handed from leg to leg and its path is kept.
-- Safe to run more than once.

ALTER TABLE maintenance_orders
    ADD COLUMN IF NOT EXISTS workshop ENUM('in_house','external') NOT NULL DEFAULT 'external' AFTER maintenance_type,
    ADD COLUMN IF NOT EXISTS supplier_id INT UNSIGNED NULL AFTER provider_name,
    ADD COLUMN IF NOT EXISTS mechanic_id INT UNSIGNED NULL AFTER supplier_id,
    ADD COLUMN IF NOT EXISTS reported_by_driver_id INT UNSIGNED NULL AFTER mechanic_id,
    ADD COLUMN IF NOT EXISTS complaint TEXT NULL AFTER reported_by_driver_id,
    ADD COLUMN IF NOT EXISTS diagnosis TEXT NULL AFTER complaint,
    ADD COLUMN IF NOT EXISTS work_done TEXT NULL AFTER diagnosis,
    ADD COLUMN IF NOT EXISTS labour_hours DECIMAL(8,2) NULL AFTER work_done,
    ADD COLUMN IF NOT EXISTS next_service_km INT UNSIGNED NULL AFTER odometer_reading,
    ADD COLUMN IF NOT EXISTS next_service_date DATE NULL AFTER next_service_km,
    ADD COLUMN IF NOT EXISTS stock_issued_at DATETIME NULL AFTER completed_at;

ALTER TABLE maintenance_parts
    ADD COLUMN IF NOT EXISTS item_id INT UNSIGNED NULL AFTER line_type;

CREATE TABLE IF NOT EXISTS journeys (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    journey_code VARCHAR(32) NOT NULL,
    customer_id INT UNSIGNED NULL,
    request_id INT UNSIGNED NULL,
    origin VARCHAR(150) NOT NULL,
    final_destination VARCHAR(150) NOT NULL,
    route_via VARCHAR(255) NULL,
    planned_start DATE NULL,
    planned_end DATE NULL,
    status ENUM('planned','in_progress','completed','cancelled') NOT NULL DEFAULT 'planned',
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_journey (journey_code),
    KEY idx_journey_status (status),
    CONSTRAINT fk_journey_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_journey_request FOREIGN KEY (request_id) REFERENCES transport_requests(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS journey_id INT UNSIGNED NULL AFTER request_id,
    ADD COLUMN IF NOT EXISTS leg_no TINYINT UNSIGNED NULL AFTER journey_id,
    ADD INDEX IF NOT EXISTS idx_trip_journey (journey_id, leg_no);

ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS journey_id INT UNSIGNED NULL AFTER trip_id;

-- Every truck a consignment has been on, in order, so a load that changed
-- trucks twice still answers "where has it been".
CREATE TABLE IF NOT EXISTS shipment_legs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT UNSIGNED NOT NULL,
    trip_id INT UNSIGNED NOT NULL,
    leg_no TINYINT UNSIGNED NULL,
    handed_over_at DATETIME NULL COMMENT 'when it left this truck for the next one',
    handover_point VARCHAR(150) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_shipment_leg (shipment_id, trip_id),
    CONSTRAINT fk_shipment_leg_shipment FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    CONSTRAINT fk_shipment_leg_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
    ('journeys', 'Journeys (multi-leg)', 'Transport', 52)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'journeys', 1, 1, 1, 1, 1 FROM roles r WHERE r.role_key IN ('super_admin', 'logistics_manager')
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'journeys', 1, 0, 0, 0, 0 FROM roles r WHERE r.role_key IN ('finance', 'management', 'fleet_manager')
ON DUPLICATE KEY UPDATE can_view = 1;

-- The people in the garage: they open and fill job cards; the fleet manager
-- approves the cost.
INSERT IGNORE INTO roles (role_key, role_name) VALUES ('workshop', 'Workshop');

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.k, 1, p.c, p.c, 0, 0 FROM roles r
CROSS JOIN (SELECT 'dashboard' AS k, 0 AS c UNION ALL SELECT 'maintenance', 1 UNION ALL SELECT 'vehicles', 0
            UNION ALL SELECT 'warehouse', 0 UNION ALL SELECT 'movements', 0 UNION ALL SELECT 'vehicle_documents', 0) p
WHERE r.role_key = 'workshop'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = VALUES(can_create), can_edit = VALUES(can_edit);
