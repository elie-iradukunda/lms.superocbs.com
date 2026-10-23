-- Freight forwarding: the sea and air leg before the truck.
--
-- A freight job is the file a forwarder opens when a customer's cargo is
-- booked: how it travels (sea, air, road), what it is (FCL, LCL, loose,
-- units, out of gauge, project cargo), the carrier, the vessel or flight, the
-- bills of lading, the Kenyan references (IDF, KRA entry, KEBS), the cargo
-- insurance, and the milestones from booking to delivery that the customer is
-- told about. The consignment it moves is an ordinary shipment, so the papers,
-- the clearance, the container and the truck all hang off it as before.
--
-- Safe to run more than once.

CREATE TABLE IF NOT EXISTS freight_jobs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    job_code VARCHAR(32) NOT NULL,
    customer_id INT UNSIGNED NULL,
    request_id INT UNSIGNED NULL,
    shipment_id INT UNSIGNED NULL,
    direction ENUM('import','export','transit') NOT NULL DEFAULT 'import',
    mode ENUM('sea','air','road') NOT NULL DEFAULT 'sea',
    scope ENUM('door_to_door','clearing_forwarding','clearing_only','freight_only','transport_only') NOT NULL DEFAULT 'clearing_forwarding',
    load_type ENUM('fcl','lcl','loose','units','out_of_gauge','project') NOT NULL DEFAULT 'fcl',
    incoterm VARCHAR(10) NULL,
    commodity VARCHAR(255) NOT NULL,
    hs_code VARCHAR(20) NULL,
    packages INT UNSIGNED NULL,
    weight_kg DECIMAL(12,2) NULL,
    volume_m3 DECIMAL(12,3) NULL,
    containers_count SMALLINT UNSIGNED NULL,
    container_numbers VARCHAR(500) NULL,
    consolidation_ref VARCHAR(60) NULL COMMENT 'the consolidation an LCL load travels in',
    origin VARCHAR(150) NULL,
    port_of_loading VARCHAR(120) NULL,
    port_of_discharge VARCHAR(120) NULL,
    final_destination VARCHAR(150) NULL,
    carrier_id INT UNSIGNED NULL COMMENT 'shipping line or airline (suppliers)',
    vessel_flight VARCHAR(120) NULL,
    voyage_no VARCHAR(60) NULL,
    booking_no VARCHAR(60) NULL,
    master_bl VARCHAR(60) NULL COMMENT 'master bill of lading or air waybill',
    house_bl VARCHAR(60) NULL,
    idf_no VARCHAR(60) NULL COMMENT 'Kenya import declaration form',
    entry_no VARCHAR(60) NULL COMMENT 'customs entry number',
    kebs_coc_no VARCHAR(60) NULL,
    etd DATETIME NULL,
    departed_at DATETIME NULL,
    eta DATETIME NULL,
    arrived_at DATETIME NULL,
    customs_at DATETIME NULL,
    cleared_at DATETIME NULL,
    inland_at DATETIME NULL,
    delivered_at DATETIME NULL,
    closed_at DATETIME NULL,
    insured TINYINT(1) NOT NULL DEFAULT 0,
    insurer VARCHAR(150) NULL,
    insurance_cert_no VARCHAR(60) NULL,
    insured_value DECIMAL(14,2) NULL,
    premium DECIMAL(14,2) NULL,
    insurance_currency CHAR(3) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    quoted_amount DECIMAL(14,2) NULL,
    status ENUM('booked','departed','arrived','customs','cleared','inland','delivered','closed','cancelled') NOT NULL DEFAULT 'booked',
    notify_customer TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_freight_job_code (job_code),
    KEY idx_freight_job_status (status),
    KEY idx_freight_job_customer (customer_id),
    KEY idx_freight_job_eta (eta),
    KEY idx_freight_job_shipment (shipment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE shipments
    ADD COLUMN IF NOT EXISTS freight_job_id INT UNSIGNED NULL AFTER request_id,
    ADD INDEX IF NOT EXISTS idx_shipment_freight_job (freight_job_id);

-- What was lost or damaged, and what the insurer paid. Money stays in the
-- currency it was claimed in.
CREATE TABLE IF NOT EXISTS cargo_claims (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    claim_code VARCHAR(32) NOT NULL,
    freight_job_id INT UNSIGNED NULL,
    shipment_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    claim_type ENUM('damage','loss','theft','shortage','delay','other') NOT NULL DEFAULT 'damage',
    incident_date DATE NULL,
    incident_place VARCHAR(150) NULL,
    description TEXT NOT NULL,
    claimed_amount DECIMAL(14,2) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    insurer VARCHAR(150) NULL,
    insurer_ref VARCHAR(60) NULL,
    status ENUM('open','submitted','settled','rejected') NOT NULL DEFAULT 'open',
    settled_amount DECIMAL(14,2) NULL,
    settled_at DATE NULL,
    document_file VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cargo_claim_code (claim_code),
    KEY idx_cargo_claim_status (status),
    KEY idx_cargo_claim_job (freight_job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What the customer asked for on the website.
ALTER TABLE transport_requests
    ADD COLUMN IF NOT EXISTS service_type VARCHAR(30) NULL AFTER source,
    ADD COLUMN IF NOT EXISTS load_type VARCHAR(20) NULL AFTER service_type;

-- ---------------------------------------------------------- permissions
INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('freight_jobs', 'Freight jobs (sea and air)', 'Transport', 55),
('cargo_claims', 'Cargo claims', 'Commercial', 127)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.permission_key, 1, p.c, p.e, p.d, p.a FROM roles r
INNER JOIN (
    SELECT 'super_admin' AS role_key, 'freight_jobs' AS permission_key, 1 AS c, 1 AS e, 1 AS d, 1 AS a UNION ALL
    SELECT 'super_admin', 'cargo_claims', 1, 1, 1, 1 UNION ALL
    SELECT 'logistics_manager', 'freight_jobs', 1, 1, 0, 1 UNION ALL
    SELECT 'logistics_manager', 'cargo_claims', 1, 1, 0, 1 UNION ALL
    SELECT 'clearance_officer', 'freight_jobs', 1, 1, 0, 1 UNION ALL
    SELECT 'finance', 'freight_jobs', 0, 0, 0, 0 UNION ALL
    SELECT 'finance', 'cargo_claims', 1, 1, 0, 1 UNION ALL
    SELECT 'management', 'freight_jobs', 0, 0, 0, 0 UNION ALL
    SELECT 'management', 'cargo_claims', 0, 0, 0, 0 UNION ALL
    SELECT 'warehouse_manager', 'freight_jobs', 0, 0, 0, 0
) p ON p.role_key = r.role_key
ON DUPLICATE KEY UPDATE can_view = 1, can_create = GREATEST(can_create, VALUES(can_create)), can_edit = GREATEST(can_edit, VALUES(can_edit)),
                        can_delete = GREATEST(can_delete, VALUES(can_delete)), can_approve = GREATEST(can_approve, VALUES(can_approve));

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'tracking', 1, 0, 0, 0, 0 FROM roles r WHERE r.role_key = 'warehouse_manager'
ON DUPLICATE KEY UPDATE can_view = 1;

-- ------------------------------------------------------------- lists
INSERT IGNORE INTO lookup_values (list_key, value, label, sort_order, is_active) VALUES
('vehicle_type', 'Prime mover', 'Prime mover', 100, 1),
('vehicle_type', 'Skeletal trailer', 'Skeletal trailer', 110, 1),
('vehicle_type', 'Flatbed trailer', 'Flatbed trailer', 120, 1),
('vehicle_type', 'Low bed', 'Low bed', 130, 1),
('vehicle_type', 'Car carrier', 'Car carrier', 140, 1),
('supplier_category', 'Shipping line', 'Shipping line', 100, 1),
('supplier_category', 'Airline', 'Airline', 110, 1),
('supplier_category', 'Clearing agent', 'Clearing agent', 120, 1),
('supplier_category', 'Insurer', 'Insurer', 130, 1),
('supplier_category', 'Container freight station', 'Container freight station', 140, 1),
('border_document', 'Import Declaration Form (IDF)', 'Import Declaration Form (IDF)', 13, 1),
('border_document', 'KEBS certificate of conformity', 'KEBS certificate of conformity', 96, 1),
('border_document', 'Air waybill', 'Air waybill', 62, 1),
('border_document', 'Port charges receipt (KPA)', 'Port charges receipt (KPA)', 125, 1),
('border_charge', 'Port charges', 'Port charges', 85, 1),
('border_charge', 'Shipping line charges', 'Shipping line charges', 86, 1),
('border_charge', 'CFS charges', 'CFS charges', 87, 1);
