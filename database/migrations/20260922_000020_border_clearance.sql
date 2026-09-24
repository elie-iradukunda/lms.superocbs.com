-- Crossing a border, from the haulier's side of the counter.
--
-- This is not a tax portal and does not try to be one. The customs authority has
-- its own system; what a transport company needs is the other half of that
-- conversation: which truck is sitting at which post, which papers it is waiting
-- on, how much was paid to get it moving, and how long it stood there.
--
-- Three questions the office cannot answer today and will be able to answer:
--
--   "Where is RAC 482D?"            — at Gatuna since 06:40, waiting on the T1
--   "What did Rusumo cost us?"      — duty, agent, weighbridge, by trip and month
--   "Which post holds us up most?"  — average hours from arrival to release
--
-- Charges are recorded in the currency they were actually paid in, because a
-- truck crossing at Namanga pays in shillings, and converted for the books by
-- the same rules as every other amount.

-- ------------------------------------------------------------- border posts
-- The crossings this company actually uses. A post is a place with a country on
-- each side of it, which is what decides whether a run is an export or an import.
CREATE TABLE IF NOT EXISTS border_posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_name VARCHAR(120) NOT NULL,
    post_code VARCHAR(20) NULL COMMENT 'the customs office code, when there is one',
    country_a VARCHAR(60) NOT NULL COMMENT 'the side this company is usually on',
    country_b VARCHAR(60) NOT NULL,
    is_one_stop TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'both authorities under one roof',
    typical_hours DECIMAL(6,2) NULL COMMENT 'what a normal crossing takes here, for planning',
    contact_name VARCHAR(120) NULL,
    contact_phone VARCHAR(40) NULL,
    notes TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_border_post (post_name),
    INDEX idx_border_active (is_active, post_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------- border crossings
-- One truck, at one post, on one trip. The four timestamps are the whole reason
-- this table exists: the gap between them is where the money goes.
--
--   arrived_at    the truck joined the queue
--   lodged_at     the declaration went in
--   cleared_at    customs released it
--   departed_at   it actually drove off
CREATE TABLE IF NOT EXISTS border_crossings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(40) NOT NULL,
    trip_id INT UNSIGNED NULL,
    shipment_id INT UNSIGNED NULL,
    vehicle_id INT UNSIGNED NULL,
    driver_id INT UNSIGNED NULL,
    border_post_id INT UNSIGNED NOT NULL,

    direction ENUM('export', 'import', 'transit') NOT NULL DEFAULT 'export',
    -- The clearing agent is a supplier like any other, so their invoices and
    -- payment terms live where the rest of the supplier records are.
    clearing_agent_id INT UNSIGNED NULL,

    declaration_no VARCHAR(60) NULL COMMENT 'the customs declaration or entry number',
    transit_bond_no VARCHAR(60) NULL COMMENT 'T1 or regional bond reference',
    seal_no VARCHAR(60) NULL,
    weighbridge_kg DECIMAL(12,2) NULL COMMENT 'what the axle scale said',

    arrived_at DATETIME NULL,
    lodged_at DATETIME NULL,
    cleared_at DATETIME NULL,
    departed_at DATETIME NULL,

    status ENUM('expected', 'at_border', 'lodged', 'held', 'cleared', 'departed') NOT NULL DEFAULT 'expected',
    hold_reason VARCHAR(255) NULL COMMENT 'why it is not moving: papers, inspection, payment',

    charges_total DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'added up from the charge lines',
    currency CHAR(3) NOT NULL DEFAULT 'RWF',
    exchange_rate DECIMAL(18,8) NOT NULL DEFAULT 1.00000000,
    base_amount DECIMAL(14,2) NULL COMMENT 'what the charges come to in the books',

    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_crossing_reference (reference),
    INDEX idx_crossing_post (border_post_id, arrived_at),
    INDEX idx_crossing_trip (trip_id),
    INDEX idx_crossing_status (status),
    INDEX idx_crossing_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What was paid to get the truck through. Duty, VAT, the agent, the weighbridge,
-- parking, an escort — each its own line, because each is asked about separately
-- when somebody queries the cost of a route.
CREATE TABLE IF NOT EXISTS border_charges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    crossing_id INT UNSIGNED NOT NULL,
    charge_type VARCHAR(60) NOT NULL,
    description VARCHAR(255) NULL,
    receipt_no VARCHAR(60) NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    INDEX idx_border_charge (crossing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The papers a crossing waits on. Which ones are needed and which are in hand is
-- the single most common question at a border, and the answer changes by the hour.
CREATE TABLE IF NOT EXISTS border_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    crossing_id INT UNSIGNED NOT NULL,
    document_type VARCHAR(60) NOT NULL,
    document_no VARCHAR(80) NULL,
    issued_on DATE NULL,
    expires_on DATE NULL,
    is_received TINYINT(1) NOT NULL DEFAULT 0,
    document_file VARCHAR(255) NULL,
    notes VARCHAR(255) NULL,
    INDEX idx_border_document (crossing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE border_crossings DROP FOREIGN KEY IF EXISTS fk_crossing_post;
ALTER TABLE border_crossings ADD CONSTRAINT fk_crossing_post FOREIGN KEY (border_post_id) REFERENCES border_posts(id);
ALTER TABLE border_charges DROP FOREIGN KEY IF EXISTS fk_border_charge;
ALTER TABLE border_charges ADD CONSTRAINT fk_border_charge FOREIGN KEY (crossing_id) REFERENCES border_crossings(id) ON DELETE CASCADE;
ALTER TABLE border_documents DROP FOREIGN KEY IF EXISTS fk_border_document;
ALTER TABLE border_documents ADD CONSTRAINT fk_border_document FOREIGN KEY (crossing_id) REFERENCES border_crossings(id) ON DELETE CASCADE;

-- ------------------------------------------------------------------ accounts
-- Border money is not "other expenses": duty is a pass-through the customer is
-- usually rebilled for, and the agent's fee is a cost of running the route. They
-- are asked about separately, so they get their own accounts.
INSERT INTO gl_accounts (account_code, account_name, account_type, normal_balance, report_section, cash_flow_class, section_order, depth, is_header, is_active, description)
SELECT v.code, v.name, 'expense', 'debit', 'Operating expenses', 'operating', v.ord, 1, 0, 1, v.note
FROM (
              SELECT '5500' code, 'Customs duty and levies' name, 550 ord, 'Duty, VAT and levies paid at a border to release a load.' note
    UNION ALL SELECT '5600', 'Clearing and forwarding fees', 560, 'What clearing agents charge to lodge and follow a declaration.'
    UNION ALL SELECT '5700', 'Border and transit charges', 570, 'Weighbridge, escort, parking and transit fees at a crossing.'
) v
WHERE NOT EXISTS (SELECT 1 FROM (SELECT account_code FROM gl_accounts) a WHERE a.account_code = v.code);

-- --------------------------------------------------------------- charge types
-- Editable from the Reference lists page like every other list.
INSERT IGNORE INTO lookup_values (list_key, value, label, sort_order, is_active) VALUES
('border_charge', 'Customs duty', 'Customs duty', 10, 1),
('border_charge', 'Import VAT', 'Import VAT', 20, 1),
('border_charge', 'Withholding tax', 'Withholding tax', 30, 1),
('border_charge', 'Clearing agent fee', 'Clearing agent fee', 40, 1),
('border_charge', 'Transit bond', 'Transit bond', 50, 1),
('border_charge', 'Weighbridge', 'Weighbridge', 60, 1),
('border_charge', 'Escort fee', 'Escort fee', 70, 1),
('border_charge', 'Parking and storage', 'Parking and storage', 80, 1),
('border_charge', 'Road toll', 'Road toll', 90, 1),
('border_charge', 'Other border charge', 'Other border charge', 100, 1);

INSERT IGNORE INTO lookup_values (list_key, value, label, sort_order, is_active) VALUES
('border_document', 'Customs declaration', 'Customs declaration', 10, 1),
('border_document', 'T1 transit bond', 'T1 transit bond', 20, 1),
('border_document', 'Commercial invoice', 'Commercial invoice', 30, 1),
('border_document', 'Packing list', 'Packing list', 40, 1),
('border_document', 'Certificate of origin', 'Certificate of origin', 50, 1),
('border_document', 'Bill of lading', 'Bill of lading', 60, 1),
('border_document', 'Weighbridge ticket', 'Weighbridge ticket', 70, 1),
('border_document', 'COMESA yellow card', 'COMESA yellow card', 80, 1),
('border_document', 'Road transit permit', 'Road transit permit', 90, 1),
('border_document', 'Phytosanitary certificate', 'Phytosanitary certificate', 100, 1),
('border_document', 'Driver passport', 'Driver passport', 110, 1);

-- The posts on the corridors this company runs. Editable, and only a start.
INSERT IGNORE INTO border_posts (post_name, post_code, country_a, country_b, is_one_stop, typical_hours, notes) VALUES
('Gatuna / Katuna', 'GTN', 'Rwanda', 'Uganda', 1, 6.00, 'Northern corridor to Kampala and Mombasa.'),
('Rusumo', 'RSM', 'Rwanda', 'Tanzania', 1, 8.00, 'Central corridor to Dar es Salaam.'),
('Kagitumba / Mirama Hills', 'KGT', 'Rwanda', 'Uganda', 1, 5.00, NULL),
('Rusizi I / Bukavu', 'RSZ', 'Rwanda', 'DR Congo', 0, 4.00, NULL),
('La Corniche / Goma', 'GSN', 'Rwanda', 'DR Congo', 0, 4.00, NULL),
('Nemba / Gasenyi', 'NMB', 'Rwanda', 'Burundi', 0, 5.00, NULL),
('Namanga', 'NMG', 'Kenya', 'Tanzania', 1, 7.00, 'On the Nairobi to Arusha run.'),
('Malaba', 'MLB', 'Kenya', 'Uganda', 1, 10.00, 'The busiest post on the northern corridor.'),
('Busia', 'BSA', 'Kenya', 'Uganda', 1, 8.00, NULL),
('Holili / Taveta', 'HLL', 'Tanzania', 'Kenya', 1, 6.00, NULL);

-- ---------------------------------------------------------------- permissions
INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('crossings', 'Border crossings', 'Transport', 250),
('border_posts', 'Border posts', 'Transport', 260)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.k, 1, 1, 1, 1, 1
  FROM roles r JOIN (SELECT 'crossings' k UNION ALL SELECT 'border_posts') p
 WHERE r.role_key IN ('super_admin', 'logistics_manager')
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

-- Finance pays the charges, so it reads and edits them but does not run the queue.
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'crossings', 1, 1, 1, 0, 0 FROM roles r WHERE r.role_key = 'finance'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.k, 1, 0, 0, 0, 0
  FROM roles r JOIN (SELECT 'crossings' k UNION ALL SELECT 'border_posts') p
 WHERE r.role_key IN ('fleet_manager', 'management', 'finance')
ON DUPLICATE KEY UPDATE can_view = 1;

-- A driver sees the crossings on their own trips, and nobody else's.
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'crossings', 1, 0, 1, 0, 0 FROM roles r WHERE r.role_key = 'driver'
ON DUPLICATE KEY UPDATE can_view = 1, can_edit = 1;
