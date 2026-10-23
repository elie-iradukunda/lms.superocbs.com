-- Go-live controls: approvals by department, clearance before the truck leaves,
-- live tracking, notifications each person reads for themselves, and a
-- Clearance Officer role.
--
-- Safe to run more than once: every table is created only if missing, every
-- column is added only if missing, and every seeded row is keyed.

-- ------------------------------------------------------------------ roles
INSERT IGNORE INTO roles (role_key, role_name) VALUES ('clearance_officer', 'Clearance Officer');

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('approvals', 'Approvals inbox', 'Overview', 12),
('approval_policies', 'Approval rules', 'Administration', 212),
('tracking', 'Live tracking', 'Transport', 72)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

-- Everyone who signs in has an approvals inbox; it only ever lists their own steps.
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'approvals', 1, 0, 0, 0, 0 FROM roles r
ON DUPLICATE KEY UPDATE can_view = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.permission_key, 1, 1, 1, 1, 1 FROM roles r
INNER JOIN (SELECT 'approval_policies' AS permission_key UNION ALL SELECT 'tracking') p
WHERE r.role_key = 'super_admin'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'tracking', 1, 0, 0, 0, 0 FROM roles r
WHERE r.role_key IN ('logistics_manager', 'fleet_manager', 'management', 'clearance_officer', 'finance')
ON DUPLICATE KEY UPDATE can_view = 1;

-- What a clearance officer works with: the clearance files and every paper on
-- them, the shipments and trips those belong to, and the border posts.
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.permission_key, 1, p.can_create, p.can_edit, 0, p.can_approve FROM roles r
INNER JOIN (
    SELECT 'dashboard' AS permission_key, 0 AS can_create, 0 AS can_edit, 0 AS can_approve UNION ALL
    SELECT 'crossings', 1, 1, 1 UNION ALL
    SELECT 'clearance_documents', 1, 1, 1 UNION ALL
    SELECT 'clearance_inspections', 1, 1, 0 UNION ALL
    SELECT 'clearance_payments', 1, 1, 0 UNION ALL
    SELECT 'clearance_releases', 1, 1, 0 UNION ALL
    SELECT 'shipment_documents', 1, 1, 1 UNION ALL
    SELECT 'pre_dispatch_checks', 0, 0, 0 UNION ALL
    SELECT 'shipments', 0, 1, 0 UNION ALL
    SELECT 'trips', 0, 0, 1 UNION ALL
    SELECT 'containers', 0, 1, 0 UNION ALL
    SELECT 'border_posts', 1, 1, 0 UNION ALL
    SELECT 'customers', 0, 0, 0 UNION ALL
    SELECT 'suppliers', 0, 0, 0 UNION ALL
    SELECT 'reports', 0, 0, 0
) p
WHERE r.role_key = 'clearance_officer'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = VALUES(can_create), can_edit = VALUES(can_edit), can_approve = VALUES(can_approve);

-- ------------------------------------------------------------ approvals
-- The rules: which department signs which kind of record, in what order, and
-- from what amount. A step with no amount applies to every record; a step with
-- an amount applies only to records in that currency from that amount up.
CREATE TABLE IF NOT EXISTS approval_policies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    policy_code VARCHAR(40) NOT NULL,
    module VARCHAR(50) NOT NULL,
    action VARCHAR(40) NOT NULL DEFAULT 'approve',
    step_no TINYINT UNSIGNED NOT NULL DEFAULT 1,
    step_label VARCHAR(120) NOT NULL,
    approver_role VARCHAR(50) NOT NULL,
    applies_when VARCHAR(30) NOT NULL DEFAULT 'always' COMMENT 'always, cross_border',
    currency CHAR(3) NULL COMMENT 'with min_amount: only records in this currency',
    min_amount DECIMAL(14,2) NULL COMMENT 'only records of this amount or more',
    sla_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_approval_policy_code (policy_code),
    KEY idx_approval_policy (module, action, is_active, step_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per step a record has to pass, in rounds: a record sent back and
-- raised again starts a new round, and the old one stays as history.
CREATE TABLE IF NOT EXISTS approval_steps (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(50) NOT NULL,
    action VARCHAR(40) NOT NULL DEFAULT 'approve',
    record_id INT UNSIGNED NOT NULL,
    record_code VARCHAR(64) NULL,
    round_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    step_no TINYINT UNSIGNED NOT NULL,
    step_label VARCHAR(120) NOT NULL,
    approver_role VARCHAR(50) NOT NULL,
    status ENUM('waiting','pending','approved','rejected','cancelled') NOT NULL DEFAULT 'waiting',
    amount DECIMAL(14,2) NULL,
    currency CHAR(3) NULL,
    requested_by INT UNSIGNED NULL,
    decided_by INT UNSIGNED NULL,
    decided_at DATETIME NULL,
    comment VARCHAR(255) NULL,
    sla_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
    activated_at DATETIME NULL,
    due_at DATETIME NULL,
    reminded_at DATETIME NULL,
    escalated_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_approval_record (module, record_id, action, round_no),
    KEY idx_approval_inbox (status, approver_role),
    KEY idx_approval_due (status, due_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The roles on these steps need the approve right on the module, or the
-- button never reaches them.
INSERT INTO approval_policies (policy_code, module, action, step_no, step_label, approver_role, applies_when, currency, min_amount, sla_hours, notes) VALUES
('REQ-1', 'requests', 'approve', 1, 'Operations approval', 'logistics_manager', 'always', NULL, NULL, 8, 'The route, the truck type and the price make sense.'),
('REQ-2-USD', 'requests', 'approve', 2, 'Management approval', 'management', 'always', 'USD', 10000, 24, 'Large jobs are signed by management.'),
('REQ-2-RWF', 'requests', 'approve', 2, 'Management approval', 'management', 'always', 'RWF', 12000000, 24, NULL),
('REQ-2-KES', 'requests', 'approve', 2, 'Management approval', 'management', 'always', 'KES', 1200000, 24, NULL),
('REQ-2-TZS', 'requests', 'approve', 2, 'Management approval', 'management', 'always', 'TZS', 25000000, 24, NULL),
('REQ-2-UGX', 'requests', 'approve', 2, 'Management approval', 'management', 'always', 'UGX', 35000000, 24, NULL),

('TRP-1', 'trips', 'approve', 1, 'Clearance ready', 'clearance_officer', 'cross_border', NULL, NULL, 12, 'Every border on the route has its papers lodged.'),
('TRP-2', 'trips', 'approve', 2, 'Release the truck', 'logistics_manager', 'always', NULL, NULL, 4, 'Operations releases the truck to the driver.'),

('DOC-1', 'shipment_documents', 'verify', 1, 'Operations check', 'logistics_manager', 'always', NULL, NULL, 8, 'The paper matches the booking: names, weights, counts.'),
('DOC-2', 'shipment_documents', 'verify', 2, 'Customs compliance', 'clearance_officer', 'cross_border', NULL, NULL, 8, 'The paper will be accepted at the border.'),
('CDOC-1', 'clearance_documents', 'verify', 1, 'Clearance check', 'clearance_officer', 'always', NULL, NULL, 8, NULL),

('EXP-1', 'expenses', 'approve', 1, 'Operations check', 'logistics_manager', 'always', NULL, NULL, 12, 'The cost belongs to this trip.'),
('EXP-2', 'expenses', 'approve', 2, 'Finance approval', 'finance', 'always', NULL, NULL, 24, NULL),
('EXP-3-USD', 'expenses', 'approve', 3, 'Management approval', 'management', 'always', 'USD', 2000, 24, NULL),
('EXP-3-RWF', 'expenses', 'approve', 3, 'Management approval', 'management', 'always', 'RWF', 2500000, 24, NULL),
('EXP-3-KES', 'expenses', 'approve', 3, 'Management approval', 'management', 'always', 'KES', 250000, 24, NULL),
('EXP-3-TZS', 'expenses', 'approve', 3, 'Management approval', 'management', 'always', 'TZS', 5000000, 24, NULL),
('EXP-3-UGX', 'expenses', 'approve', 3, 'Management approval', 'management', 'always', 'UGX', 7000000, 24, NULL),

('PRC-1', 'procurement', 'approve', 1, 'Finance budget check', 'finance', 'always', NULL, NULL, 24, NULL),
('PRC-2-USD', 'procurement', 'approve', 2, 'Management approval', 'management', 'always', 'USD', 5000, 48, NULL),
('PRC-2-RWF', 'procurement', 'approve', 2, 'Management approval', 'management', 'always', 'RWF', 6000000, 48, NULL),
('PRC-2-KES', 'procurement', 'approve', 2, 'Management approval', 'management', 'always', 'KES', 600000, 48, NULL),
('PRC-2-TZS', 'procurement', 'approve', 2, 'Management approval', 'management', 'always', 'TZS', 12000000, 48, NULL),
('PRC-2-UGX', 'procurement', 'approve', 2, 'Management approval', 'management', 'always', 'UGX', 18000000, 48, NULL),

('BIL-1', 'supplier_bills', 'approve', 1, 'Finance approval', 'finance', 'always', NULL, NULL, 24, NULL),
('BIL-2-USD', 'supplier_bills', 'approve', 2, 'Management approval', 'management', 'always', 'USD', 5000, 48, NULL),
('BIL-2-RWF', 'supplier_bills', 'approve', 2, 'Management approval', 'management', 'always', 'RWF', 6000000, 48, NULL),
('BIL-2-KES', 'supplier_bills', 'approve', 2, 'Management approval', 'management', 'always', 'KES', 600000, 48, NULL),
('BIL-2-TZS', 'supplier_bills', 'approve', 2, 'Management approval', 'management', 'always', 'TZS', 12000000, 48, NULL),
('BIL-2-UGX', 'supplier_bills', 'approve', 2, 'Management approval', 'management', 'always', 'UGX', 18000000, 48, NULL),

('INV-1', 'invoices', 'approve', 1, 'Finance issues the invoice', 'finance', 'always', NULL, NULL, 24, NULL)
ON DUPLICATE KEY UPDATE policy_code = policy_code;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT DISTINCT r.id, ap.module, 1, 0, 0, 0, 1
  FROM approval_policies ap
  INNER JOIN roles r ON r.role_key = ap.approver_role
ON DUPLICATE KEY UPDATE can_view = 1, can_approve = 1;

-- ---------------------------------------------------------- notifications
-- A notification sent to a role is one row for everybody in it. Who has read
-- it is kept per person, so one accountant reading it does not clear it for
-- the other three.
CREATE TABLE IF NOT EXISTS notification_reads (
    notification_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    read_at DATETIME NOT NULL,
    PRIMARY KEY (notification_id, user_id),
    KEY idx_notification_reads_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------- tracking
ALTER TABLE vehicles
    ADD COLUMN IF NOT EXISTS gps_device_id VARCHAR(64) NULL COMMENT 'the tracker IMEI or unique id' AFTER chassis_number;

CREATE TABLE IF NOT EXISTS vehicle_positions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT UNSIGNED NOT NULL,
    trip_id INT UNSIGNED NULL,
    driver_id INT UNSIGNED NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    speed_kmh DECIMAL(6,1) NULL,
    heading SMALLINT NULL,
    accuracy_m DECIMAL(8,1) NULL,
    source ENUM('device','phone') NOT NULL DEFAULT 'phone',
    recorded_at DATETIME NOT NULL,
    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_position_vehicle (vehicle_id, recorded_at),
    KEY idx_position_trip (trip_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Places a truck can be recognised at: a border post, and any stop given its
-- coordinates. Within the radius, the stop is reached without anybody pressing.
ALTER TABLE border_posts
    ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL AFTER country_b,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL AFTER latitude,
    ADD COLUMN IF NOT EXISTS geofence_m INT UNSIGNED NOT NULL DEFAULT 1500 AFTER longitude;

ALTER TABLE trip_stops
    ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL AFTER location_name,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL AFTER latitude,
    ADD COLUMN IF NOT EXISTS arrival_source VARCHAR(20) NULL COMMENT 'driver, gps' AFTER arrival_condition;

-- Where the driver stood when the goods were handed over.
ALTER TABLE deliveries
    ADD COLUMN IF NOT EXISTS pod_latitude DECIMAL(10,7) NULL AFTER recipient_signature,
    ADD COLUMN IF NOT EXISTS pod_longitude DECIMAL(10,7) NULL AFTER pod_latitude;

-- Approximate coordinates of the main posts on the Northern and Central
-- corridors, so the geofence works on day one. Only filled where nobody has
-- set them; check each on the map and correct it on the border post.
UPDATE border_posts SET latitude = -2.3828, longitude = 30.7837, geofence_m = 3000 WHERE latitude IS NULL AND post_name LIKE '%Rusumo%';
UPDATE border_posts SET latitude = -1.4300, longitude = 30.0110, geofence_m = 3000 WHERE latitude IS NULL AND (post_name LIKE '%Gatuna%' OR post_name LIKE '%Katuna%');
UPDATE border_posts SET latitude = 0.6360, longitude = 34.2810, geofence_m = 3000 WHERE latitude IS NULL AND post_name LIKE '%Malaba%';
UPDATE border_posts SET latitude = 0.4640, longitude = 34.0920, geofence_m = 3000 WHERE latitude IS NULL AND post_name LIKE '%Busia%';
UPDATE border_posts SET latitude = -2.5440, longitude = 36.7880, geofence_m = 3000 WHERE latitude IS NULL AND post_name LIKE '%Namanga%';
UPDATE border_posts SET latitude = -1.0570, longitude = 30.4560, geofence_m = 3000 WHERE latitude IS NULL AND (post_name LIKE '%Kagitumba%' OR post_name LIKE '%Mirama%');
UPDATE border_posts SET latitude = -1.6850, longitude = 29.2420, geofence_m = 3000 WHERE latitude IS NULL AND (post_name LIKE '%Corniche%' OR post_name LIKE '%Goma%');
UPDATE border_posts SET latitude = -2.4870, longitude = 28.9000, geofence_m = 3000 WHERE latitude IS NULL AND (post_name LIKE '%Rusizi%' OR post_name LIKE '%Bukavu%');

-- --------------------------------------------------------- requests
ALTER TABLE transport_requests
    ADD COLUMN IF NOT EXISTS source ENUM('office','website') NOT NULL DEFAULT 'office' AFTER reference_code,
    ADD COLUMN IF NOT EXISTS contact_email VARCHAR(190) NULL AFTER requested_by_contact,
    ADD COLUMN IF NOT EXISTS contact_phone VARCHAR(40) NULL AFTER contact_email,
    ADD COLUMN IF NOT EXISTS company_name VARCHAR(180) NULL AFTER contact_phone;

-- -------------------------------------------------------------- settings
INSERT INTO company_settings (setting_key, setting_value, setting_label, setting_group, input_type) VALUES
('clearance_before_dispatch', '1', 'Clearance lodged before a cross-border truck leaves (1 = yes)', 'Operations', 'number'),
('document_pack_always', '1', 'Every shipment needs its document pack before dispatch (1 = yes)', 'Operations', 'number'),
('clearance_alert_hours', '24', 'Alert when a clearance has not moved for (hours)', 'Operations', 'number'),
('gps_silence_hours', '3', 'Alert when a truck on the road has not reported for (hours)', 'Operations', 'number'),
('gps_api_token', '', 'Tracker key (sent by the GPS server as X-Tracker-Key)', 'Operations', 'text'),
('approval_separation', '1', 'Nobody approves a record they raised (1 = yes)', 'Operations', 'number'),
('public_requests', '1', 'Customers may send transport requests from the website (1 = yes)', 'Operations', 'number')
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label), setting_group = VALUES(setting_group);

-- The checklist papers a clearance file starts with.
INSERT IGNORE INTO lookup_values (list_key, value, label, sort_order, is_active) VALUES
('border_document', 'Export declaration', 'Export declaration', 12, 1),
('border_document', 'Import declaration', 'Import declaration', 14, 1),
('border_document', 'Regional transit guarantee (RCTG)', 'Regional transit guarantee (RCTG)', 22, 1),
('border_document', 'Electronic cargo tracking seal', 'Electronic cargo tracking seal', 24, 1),
('border_document', 'Delivery order', 'Delivery order', 65, 1),
('border_document', 'Import permit', 'Import permit', 95, 1),
('border_document', 'Tax payment receipt', 'Tax payment receipt', 120, 1),
('border_document', 'Customs release order', 'Customs release order', 130, 1);
