-- ERI specification, sections 5 and 6: vehicles, trailers, drivers.
--
-- A truck can be used only when its documents are uploaded and in date, it is
-- not due for service, and its load is within its maximum. The document types
-- are a list Admin keeps, not code: which ones a truck, a trailer or a driver
-- must carry, which only matter across a border, which only for tankers, and
-- how many days before expiry people are warned.
--
-- Safe to run more than once.

-- ------------------------------------------------------------ document types
CREATE TABLE IF NOT EXISTS document_types (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL COMMENT 'blank: the whole group',
    type_code VARCHAR(40) NOT NULL,
    type_name VARCHAR(120) NOT NULL,
    applies_to ENUM('vehicle','trailer','driver') NOT NULL,
    fleet ENUM('all','tanker','container') NOT NULL DEFAULT 'all' COMMENT 'only trucks/drivers of this fleet need it',
    required_for_trip TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'a trip cannot be planned without it in date',
    cross_border_only TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'only needed when the route crosses a border',
    alert_days VARCHAR(40) NOT NULL DEFAULT '30,14,3' COMMENT 'days before expiry people are warned',
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(255) NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_document_type (applies_to, type_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO document_types (type_code, type_name, applies_to, fleet, required_for_trip, cross_border_only, sort_order) VALUES
('registration', 'Registration certificate', 'vehicle', 'all', 1, 0, 10),
('insurance', 'Insurance', 'vehicle', 'all', 1, 0, 20),
('regional_insurance', 'Regional insurance (COMESA yellow card)', 'vehicle', 'all', 1, 1, 30),
('inspection', 'Technical inspection', 'vehicle', 'all', 1, 0, 40),
('transport_licence', 'Goods-transport licence', 'vehicle', 'all', 1, 0, 50),
('transit_permit', 'Cross-border / transit permit', 'vehicle', 'all', 1, 1, 60),
('tank_calibration', 'Tank calibration certificate', 'vehicle', 'tanker', 1, 0, 70),
('registration', 'Registration certificate', 'trailer', 'all', 1, 0, 10),
('insurance', 'Insurance', 'trailer', 'all', 1, 0, 20),
('inspection', 'Technical inspection', 'trailer', 'all', 1, 0, 30),
('tank_calibration', 'Tank calibration certificate', 'trailer', 'tanker', 1, 0, 40),
('driving_licence', 'Driving licence (heavy-truck category)', 'driver', 'all', 1, 0, 10),
('national_id', 'National ID', 'driver', 'all', 0, 0, 20),
('passport', 'Passport', 'driver', 'all', 1, 1, 30),
('hazmat_training', 'Hazardous-goods training', 'driver', 'tanker', 1, 0, 40)
ON DUPLICATE KEY UPDATE type_name = type_name;

-- ------------------------------------------------------------------ trailers
CREATE TABLE IF NOT EXISTS trailers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    trailer_number VARCHAR(32) NOT NULL COMMENT 'RL 9465 /BX',
    trailer_type ENUM('tanker','flatbed','skeleton','box','lowbed','other') NOT NULL DEFAULT 'flatbed',
    make VARCHAR(80) NULL,
    manufacture_year SMALLINT UNSIGNED NULL,
    chassis_number VARCHAR(80) NULL,
    max_load_t DECIMAL(8,2) NULL COMMENT 'payload in tonnes',
    tank_capacity_l DECIMAL(10,0) NULL COMMENT 'tanker trailers: cargo tank in litres',
    compartments TINYINT UNSIGNED NULL,
    container_sizes SET('1x20','1x40','2x20') NULL,
    vehicle_id INT UNSIGNED NULL COMMENT 'the truck it is usually coupled to',
    status ENUM('available','in_use','in_garage','off_road','inactive') NOT NULL DEFAULT 'available',
    photo_file VARCHAR(255) NULL,
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_trailer_number (trailer_number),
    KEY idx_trailers_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------ vehicles
ALTER TABLE vehicles
    ADD COLUMN IF NOT EXISTS fleet ENUM('tanker','container','other') NOT NULL DEFAULT 'container' COMMENT 'decides which OM manages it' AFTER vehicle_type,
    ADD COLUMN IF NOT EXISTS trailer_id INT UNSIGNED NULL AFTER fleet,
    ADD COLUMN IF NOT EXISTS engine_number VARCHAR(80) NULL AFTER chassis_number,
    ADD COLUMN IF NOT EXISTS max_payload_t DECIMAL(8,2) NULL COMMENT 'payload in tonnes' AFTER capacity_m3,
    ADD COLUMN IF NOT EXISTS fuel_tank_l DECIMAL(8,0) NULL COMMENT 'own fuel tank, litres (1,200)' AFTER max_payload_t,
    ADD COLUMN IF NOT EXISTS cargo_tank_l DECIMAL(10,0) NULL COMMENT 'tankers: cargo tank in litres' AFTER fuel_tank_l,
    ADD COLUMN IF NOT EXISTS container_sizes SET('1x20','1x40','2x20') NULL COMMENT 'container trucks: what it may carry' AFTER cargo_tank_l,
    ADD COLUMN IF NOT EXISTS normal_l_per_100km DECIMAL(6,2) NULL COMMENT 'normal consumption, for the fuel check' AFTER container_sizes,
    ADD COLUMN IF NOT EXISTS gps_provider_id INT UNSIGNED NULL AFTER gps_device_id,
    ADD COLUMN IF NOT EXISTS odometer_source ENUM('gps','manual') NOT NULL DEFAULT 'manual' AFTER mileage,
    ADD COLUMN IF NOT EXISTS service_interval_km INT UNSIGNED NULL COMMENT 'blank: the company setting (7,000 km)' AFTER odometer_source,
    ADD COLUMN IF NOT EXISTS last_service_km INT UNSIGNED NULL AFTER service_interval_km,
    ADD COLUMN IF NOT EXISTS next_service_km INT UNSIGNED NULL AFTER last_service_km,
    ADD COLUMN IF NOT EXISTS service_first_on_return TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'left with a service due: first in line when back' AFTER next_service_km,
    ADD COLUMN IF NOT EXISTS off_road_reason VARCHAR(255) NULL AFTER status,
    ADD COLUMN IF NOT EXISTS photo_front VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS photo_back VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS photo_left VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS photo_right VARCHAR(255) NULL;

-- The truck statuses that replace the Excel status list.
ALTER TABLE vehicles MODIFY COLUMN status ENUM('available','assigned','loading','on_road','offloading','returning','in_garage','off_road','inactive','on_trip','maintenance') NOT NULL DEFAULT 'available';
UPDATE vehicles SET status = 'on_road' WHERE status = 'on_trip';
UPDATE vehicles SET status = 'in_garage' WHERE status = 'maintenance';
ALTER TABLE vehicles MODIFY COLUMN status ENUM('available','assigned','loading','on_road','offloading','returning','in_garage','off_road','inactive') NOT NULL DEFAULT 'available';

-- -------------------------------------------- documents of trucks and trailers
ALTER TABLE vehicle_documents
    ADD COLUMN IF NOT EXISTS owner_type ENUM('vehicle','trailer') NOT NULL DEFAULT 'vehicle' AFTER document_code,
    ADD COLUMN IF NOT EXISTS trailer_id INT UNSIGNED NULL AFTER vehicle_id;
ALTER TABLE vehicle_documents MODIFY COLUMN vehicle_id INT UNSIGNED NULL;
ALTER TABLE vehicle_documents MODIFY COLUMN document_type VARCHAR(40) NOT NULL DEFAULT 'insurance';
UPDATE vehicle_documents SET document_type = 'transport_licence' WHERE document_type = 'road_license';
UPDATE vehicle_documents SET document_type = 'transit_permit' WHERE document_type = 'permit';

-- ------------------------------------------------------------------- drivers
ALTER TABLE drivers
    ADD COLUMN IF NOT EXISTS nationality VARCHAR(60) NULL AFTER full_name,
    ADD COLUMN IF NOT EXISTS passport_number VARCHAR(40) NULL AFTER national_id,
    ADD COLUMN IF NOT EXISTS email VARCHAR(190) NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS whatsapp_number VARCHAR(40) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS photo_file VARCHAR(255) NULL AFTER whatsapp_number,
    ADD COLUMN IF NOT EXISTS fleet ENUM('all','tanker','container') NOT NULL DEFAULT 'all' AFTER photo_file,
    ADD COLUMN IF NOT EXISTS payment_method ENUM('momo','cash') NOT NULL DEFAULT 'momo' COMMENT 'cash: foreign drivers without MoMo' AFTER fleet,
    ADD COLUMN IF NOT EXISTS momo_number VARCHAR(40) NULL AFTER payment_method,
    ADD COLUMN IF NOT EXISTS momo_name VARCHAR(150) NULL COMMENT 'the name MTN has registered on the number' AFTER momo_number,
    ADD COLUMN IF NOT EXISTS default_vehicle_id INT UNSIGNED NULL AFTER momo_name,
    ADD COLUMN IF NOT EXISTS contract_type ENUM('permanent','fixed_term','casual','probation') NULL AFTER hired_on,
    ADD COLUMN IF NOT EXISTS salary DECIMAL(14,2) NULL COMMENT 'seen by HR only' AFTER contract_type,
    ADD COLUMN IF NOT EXISTS salary_currency CHAR(3) NULL AFTER salary,
    ADD COLUMN IF NOT EXISTS left_on DATE NULL AFTER salary_currency,
    ADD COLUMN IF NOT EXISTS left_reason VARCHAR(255) NULL AFTER left_on,
    ADD COLUMN IF NOT EXISTS last_seen_at DATETIME NULL COMMENT 'the app was last reached from the phone' AFTER left_reason;

-- Employment status as HR speaks of it.
ALTER TABLE drivers MODIFY COLUMN status ENUM('available','on_trip','off_duty','inactive','active','on_leave','suspended','left') NOT NULL DEFAULT 'active';
UPDATE drivers SET status = 'active' WHERE status = 'available';
UPDATE drivers SET status = 'on_leave' WHERE status = 'off_duty';
UPDATE drivers SET status = 'left' WHERE status = 'inactive';
ALTER TABLE drivers MODIFY COLUMN status ENUM('active','on_trip','on_leave','suspended','left') NOT NULL DEFAULT 'active';

ALTER TABLE driver_documents
    ADD COLUMN IF NOT EXISTS document_code VARCHAR(32) NULL AFTER id,
    ADD COLUMN IF NOT EXISTS notes VARCHAR(255) NULL AFTER document_file,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER notes;
ALTER TABLE driver_documents MODIFY COLUMN document_type VARCHAR(40) NOT NULL;
UPDATE driver_documents SET document_type = 'national_id' WHERE document_type = 'driver_id';
UPDATE driver_documents SET document_type = 'transit_permit' WHERE document_type = 'permit';

-- A change of MoMo number is used for payment only once the OM approves it
-- (section 6 and control 12).
CREATE TABLE IF NOT EXISTS driver_momo_changes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    driver_id INT UNSIGNED NOT NULL,
    old_number VARCHAR(40) NULL,
    old_name VARCHAR(150) NULL,
    new_number VARCHAR(40) NOT NULL,
    new_name VARCHAR(150) NOT NULL,
    new_phone VARCHAR(40) NULL,
    requested_by INT UNSIGNED NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    decided_by INT UNSIGNED NULL,
    decided_at DATETIME NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_momo_driver (driver_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- permissions
INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('trailers', 'Trailers', 'Fleet', 21),
('document_types', 'Document types', 'Administration', 215),
('driver_momo_changes', 'MoMo number changes', 'Fleet', 32)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.permission_key, 1, 1, 1, 1, 1 FROM roles r
INNER JOIN (SELECT 'trailers' AS permission_key UNION ALL SELECT 'document_types' UNION ALL SELECT 'driver_momo_changes') p
WHERE r.role_key = 'super_admin'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.permission_key, 1, 1, 1, 0, p.approve FROM roles r
INNER JOIN (SELECT 'trailers' AS permission_key, 0 AS approve UNION ALL SELECT 'driver_momo_changes', 1) p
WHERE r.role_key = 'logistics_manager'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_approve = VALUES(can_approve);

-- HR registers drivers and keeps their papers (section 21).
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.permission_key, 1, 1, 1, 0, 0 FROM roles r
INNER JOIN (SELECT 'drivers' AS permission_key UNION ALL SELECT 'driver_documents') p
WHERE r.role_key = 'hr'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1;

-- ------------------------------------------------------------------ settings
INSERT INTO company_settings (setting_key, setting_value, setting_label, setting_group, input_type) VALUES
('service_interval_km', '7000', 'Service every (km): oil and filters', 'Fleet', 'number'),
('service_warning_km', '500', 'Warn this many km before a service is due', 'Fleet', 'number'),
('document_alert_days_list', '30,14,3', 'Warn this many days before a document expires', 'Fleet', 'text'),
('driver_unreachable_hours', '24', 'Alert when a driver''s phone has not been heard from for (hours)', 'Fleet', 'number'),
('parked_without_handover_hours', '12', 'Alert when a truck is parked with no handover for (hours)', 'Fleet', 'number')
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label), setting_group = VALUES(setting_group);

-- The licence is one of the driver's documents, with its scan and expiry; the
-- number on the driver record is a convenience and may be left for later.
ALTER TABLE drivers MODIFY COLUMN license_number VARCHAR(80) NULL;

-- The place for GPS is ready from day one (section 15): each truck names its
-- provider and device; Admin adds the API access when it is connected.
CREATE TABLE IF NOT EXISTS gps_providers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    provider_name VARCHAR(120) NOT NULL,
    api_type VARCHAR(40) NOT NULL DEFAULT 'generic_json' COMMENT 'which adapter reads it',
    base_url VARCHAR(255) NULL,
    api_key VARCHAR(255) NULL,
    api_username VARCHAR(120) NULL,
    api_password VARCHAR(255) NULL,
    poll_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    field_map TEXT NULL COMMENT 'JSON: where device, time, lat, lng, speed and odometer sit in the answer',
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    last_polled_at DATETIME NULL,
    last_error VARCHAR(255) NULL,
    notes VARCHAR(255) NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('gps_providers', 'GPS providers', 'Administration', 216)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label);
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'gps_providers', 1, 1, 1, 1, 0 FROM roles r WHERE r.role_key = 'super_admin'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1;
