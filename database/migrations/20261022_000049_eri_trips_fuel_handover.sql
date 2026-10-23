-- ERI specification, sections 7, 11 and 16: trips, fuel, vehicle handover.
--
-- Every trip is independent: a truck back in Kigali gets a new trip with new
-- fuel, road toll and mileage, and no truck leaves before its driver has
-- received the money. Several trucks leaving together share one F1.
--
-- Safe to run more than once.

-- --------------------------------------------------------------------- trips
ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS route_id INT UNSIGNED NULL AFTER customer_id,
    ADD COLUMN IF NOT EXISTS trailer_id INT UNSIGNED NULL AFTER vehicle_id,
    ADD COLUMN IF NOT EXISTS trip_group VARCHAR(32) NULL COMMENT 'trucks planned together on one F1' AFTER reference_code,
    ADD COLUMN IF NOT EXISTS cargo_type ENUM('litres','tonnes','containers','other') NULL AFTER cargo_summary,
    ADD COLUMN IF NOT EXISTS cargo_quantity DECIMAL(14,2) NULL AFTER cargo_type,
    ADD COLUMN IF NOT EXISTS container_numbers VARCHAR(255) NULL AFTER cargo_quantity,
    ADD COLUMN IF NOT EXISTS container_size ENUM('1x20','1x40','2x20') NULL AFTER container_numbers,
    ADD COLUMN IF NOT EXISTS loaded_quantity DECIMAL(14,2) NULL AFTER container_size,
    ADD COLUMN IF NOT EXISTS delivered_quantity DECIMAL(14,2) NULL AFTER loaded_quantity,
    ADD COLUMN IF NOT EXISTS money_request_id INT UNSIGNED NULL COMMENT 'the F1 that pays this trip' AFTER delivered_quantity,
    ADD COLUMN IF NOT EXISTS money_confirmed_at DATETIME NULL COMMENT 'the driver confirmed the money arrived' AFTER money_request_id,
    ADD COLUMN IF NOT EXISTS service_due_reason VARCHAR(255) NULL COMMENT 'leaves with a service due: why, and serviced on return' AFTER money_confirmed_at,
    ADD COLUMN IF NOT EXISTS expected_back_at DATETIME NULL AFTER planned_arrival_at,
    ADD COLUMN IF NOT EXISTS turnaround_at DATETIME NULL COMMENT 'arrived where it loads and turns back' AFTER expected_back_at,
    ADD COLUMN IF NOT EXISTS return_due_at DATETIME NULL COMMENT 'turnaround + the route''s maximum return days' AFTER turnaround_at,
    ADD COLUMN IF NOT EXISTS late_alerted_at DATETIME NULL AFTER return_due_at,
    ADD COLUMN IF NOT EXISTS closed_at DATETIME NULL AFTER completed_at,
    ADD COLUMN IF NOT EXISTS km_driven INT UNSIGNED NULL AFTER end_mileage,
    ADD COLUMN IF NOT EXISTS fuel_given_l DECIMAL(10,2) NULL AFTER km_driven,
    ADD COLUMN IF NOT EXISTS fuel_expected_l DECIMAL(10,2) NULL AFTER fuel_given_l,
    ADD COLUMN IF NOT EXISTS fuel_flag VARCHAR(255) NULL COMMENT 'the consumption check at close' AFTER fuel_expected_l,
    ADD INDEX IF NOT EXISTS idx_trips_route (route_id),
    ADD INDEX IF NOT EXISTS idx_trips_group (trip_group);

-- The specification's trip statuses. The earlier ones stay readable for old
-- records; new trips use only these.
ALTER TABLE trips MODIFY COLUMN status ENUM(
    'planned','ready','on_road','at_turnaround','loading','offloading','returning','arrived','closed','cancelled',
    'requested','approved','in_transit','delivered'
) NOT NULL DEFAULT 'planned';

-- What happened on the trip, from the driver's taps, the OM or the GPS.
CREATE TABLE IF NOT EXISTS trip_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    trip_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(40) NOT NULL COMMENT 'money_received, departed, checkpoint, at_turnaround, loading, loaded, offloading, delivered, arrived, delay, problem, closed...',
    happened_at DATETIME NOT NULL,
    source ENUM('driver','om','gps','system') NOT NULL DEFAULT 'driver',
    place VARCHAR(150) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    odometer INT UNSIGNED NULL,
    quantity DECIMAL(14,2) NULL,
    note VARCHAR(255) NULL,
    photo_file VARCHAR(255) NULL,
    user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_trip_events (trip_id, happened_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A driver or truck replaced mid-trip: the trip keeps one record, with the
-- change, the reason and the time.
CREATE TABLE IF NOT EXISTS trip_changes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    trip_id INT UNSIGNED NOT NULL,
    change_type ENUM('driver','vehicle','trailer') NOT NULL,
    old_id INT UNSIGNED NULL,
    new_id INT UNSIGNED NOT NULL,
    reason VARCHAR(255) NOT NULL,
    odometer INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_trip_changes (trip_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- handover F14
CREATE TABLE IF NOT EXISTS vehicle_handovers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    handover_code VARCHAR(32) NULL,
    trip_id INT UNSIGNED NULL,
    vehicle_id INT UNSIGNED NOT NULL,
    driver_id INT UNSIGNED NULL,
    parked_at VARCHAR(150) NOT NULL COMMENT 'where the truck is parked',
    odometer INT UNSIGNED NOT NULL,
    fuel_level ENUM('empty','quarter','half','three_quarters','full') NOT NULL,
    key_received_by VARCHAR(150) NOT NULL,
    key_received_user_id INT UNSIGNED NULL,
    photo_front VARCHAR(255) NULL,
    photo_back VARCHAR(255) NULL,
    photo_left VARCHAR(255) NULL,
    photo_right VARCHAR(255) NULL,
    notes TEXT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    status ENUM('submitted','confirmed','rejected') NOT NULL DEFAULT 'submitted',
    confirmed_by INT UNSIGNED NULL,
    confirmed_at DATETIME NULL,
    rejection_reason VARCHAR(255) NULL,
    submitted_by INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_handover_trip (trip_id),
    KEY idx_handover_vehicle (vehicle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------- fuel orders
-- Fuel ordered to a station or ERI Kenya (with F1 at departure, F2 as a
-- top-up), the litres the station confirms it gave, and its price once the
-- invoice is matched (section 11).
CREATE TABLE IF NOT EXISTS fuel_orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    order_code VARCHAR(60) NULL,
    request_id INT UNSIGNED NULL COMMENT 'the F1 or F2 it came from',
    trip_id INT UNSIGNED NULL,
    vehicle_id INT UNSIGNED NOT NULL,
    driver_id INT UNSIGNED NULL,
    supplier_id INT UNSIGNED NULL,
    fuel_point VARCHAR(120) NULL,
    litres_ordered DECIMAL(10,2) NOT NULL,
    standard_litres DECIMAL(10,2) NULL,
    litres_delivered DECIMAL(10,2) NULL,
    unit_price DECIMAL(12,4) NULL,
    currency CHAR(3) NULL,
    delivered_at DATETIME NULL,
    confirmed_by INT UNSIGNED NULL COMMENT 'the station login, or whoever recorded it',
    status ENUM('pending','ordered','delivered','invoiced','cancelled') NOT NULL DEFAULT 'pending',
    supplier_invoice_id INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fuel_orders_trip (trip_id),
    KEY idx_fuel_orders_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------- permissions
INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('trip_planner', 'Plan trips (New trip)', 'Transport', 69),
('vehicle_handovers', 'Vehicle handovers', 'Transport', 74),
('fuel_orders', 'Fuel orders', 'Transport', 75)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.k, 1, 1, 1, p.del, 1 FROM roles r
INNER JOIN (SELECT 'trip_planner' AS k, 0 AS del UNION ALL SELECT 'vehicle_handovers', 0 UNION ALL SELECT 'fuel_orders', 0
            UNION ALL SELECT 'routes', 0 UNION ALL SELECT 'route_rates', 0 UNION ALL SELECT 'service_rates', 0 UNION ALL SELECT 'form_requests', 0) p
WHERE r.role_key IN ('super_admin', 'logistics_manager')
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_approve = 1;

INSERT INTO company_settings (setting_key, setting_value, setting_label, setting_group, input_type) VALUES
('trip_number_format', 'TRP-{YEAR}-{SEQ4}', 'Trip number', 'Trips', 'text'),
('fuel_variance_percent', '3', 'Flag a trip when fuel used differs from normal by more than (%)', 'Trips', 'number'),
('handover_number_format', 'HO-{YEAR}-{SEQ4}', 'Vehicle handover number', 'Trips', 'text')
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label), setting_group = VALUES(setting_group);

-- A trip is released by its money (F1 authorized, paid and confirmed), not by
-- the earlier approval rules.
UPDATE approval_policies SET is_active = 0 WHERE module = 'trips';
UPDATE approval_steps SET status = 'cancelled' WHERE module = 'trips' AND status IN ('waiting', 'pending');

-- Driver support (section 16): photos and location from the phone, the OM's
-- decision, who it was forwarded to, and the F10 when money is needed.
ALTER TABLE support_requests
    ADD COLUMN IF NOT EXISTS photos TEXT NULL COMMENT 'JSON list of photo files' AFTER attachment,
    ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL AFTER location,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL AFTER latitude,
    ADD COLUMN IF NOT EXISTS decided_by INT UNSIGNED NULL AFTER assigned_to,
    ADD COLUMN IF NOT EXISTS decided_at DATETIME NULL AFTER decided_by,
    ADD COLUMN IF NOT EXISTS forwarded_role VARCHAR(50) NULL COMMENT 'Finance, garage, management, HR...' AFTER decided_at,
    ADD COLUMN IF NOT EXISTS request_id INT UNSIGNED NULL COMMENT 'the F10 when money is needed' AFTER forwarded_role,
    ADD COLUMN IF NOT EXISTS escalated_at DATETIME NULL AFTER request_id;

INSERT INTO company_settings (setting_key, setting_value, setting_label, setting_group, input_type) VALUES
('support_backup_after_minutes', '60', 'An urgent support request not handled goes to a backup after (minutes)', 'Trips', 'number'),
('support_backup_role', 'management', 'Role that backs up the OM on urgent support requests', 'Trips', 'text')
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label);
