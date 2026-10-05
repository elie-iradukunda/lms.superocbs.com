-- The driver on the road: he starts the trip himself once the office has made
-- it ready, marks each stop as he reaches it and leaves it, and asks the
-- company for support (money, a breakdown, a border problem) from his phone,
-- every request tied to the trip and sent to the person he chooses.
-- Safe to run more than once.

ALTER TABLE trip_stops
    ADD COLUMN IF NOT EXISTS departed_at DATETIME NULL AFTER actual_arrival_at;

CREATE TABLE IF NOT EXISTS support_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_code VARCHAR(32) NOT NULL,
    trip_id INT UNSIGNED NOT NULL,
    driver_id INT UNSIGNED NULL,
    requested_by INT UNSIGNED NULL,
    assigned_to INT UNSIGNED NULL,
    support_type ENUM('money','breakdown','accident','police_border','documents','health','other') NOT NULL DEFAULT 'other',
    urgency ENUM('normal','urgent','emergency') NOT NULL DEFAULT 'normal',
    title VARCHAR(150) NOT NULL,
    details TEXT NULL,
    location VARCHAR(190) NULL,
    amount_requested DECIMAL(14,2) NULL,
    currency CHAR(3) NULL,
    attachment VARCHAR(255) NULL,
    status ENUM('open','in_progress','resolved','rejected') NOT NULL DEFAULT 'open',
    response TEXT NULL,
    amount_given DECIMAL(14,2) NULL,
    given_method VARCHAR(60) NULL,
    given_reference VARCHAR(100) NULL,
    responded_by INT UNSIGNED NULL,
    responded_at DATETIME NULL,
    resolved_at DATETIME NULL,
    rejection_reason VARCHAR(255) NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_support_request_code (request_code),
    KEY idx_support_trip (trip_id),
    KEY idx_support_assigned (assigned_to, status),
    CONSTRAINT fk_support_trip FOREIGN KEY (trip_id) REFERENCES trips(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
    ('support_requests', 'Driver support', 'Transport', 95)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

-- The office handles requests; finance sees the money ones too; a driver asks
-- for support and follows his own requests.
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'support_requests', 1, 1, 1, 1, 1 FROM roles r WHERE r.role_key IN ('super_admin', 'logistics_manager')
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'support_requests', 1, 0, 1, 0, 1 FROM roles r WHERE r.role_key IN ('fleet_manager', 'finance')
ON DUPLICATE KEY UPDATE can_view = 1, can_edit = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'support_requests', 1, 0, 0, 0, 0 FROM roles r WHERE r.role_key IN ('management')
ON DUPLICATE KEY UPDATE can_view = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'support_requests', 1, 1, 0, 0, 0 FROM roles r WHERE r.role_key IN ('driver')
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1;

-- A driver reads his trip; he does not plan or change it. Starting it and each
-- stop on the way are his buttons on My trips, and the system records them.
UPDATE role_permissions rp
  INNER JOIN roles r ON r.id = rp.role_id
   SET rp.can_create = 0, rp.can_edit = 0, rp.can_delete = 0, rp.can_approve = 0
 WHERE rp.permission_key = 'trips' AND r.role_key = 'driver';

-- What the driver says when he confirms reaching a stop: the kilometres on the
-- clock and how things are, alongside his note.
ALTER TABLE trip_stops
    ADD COLUMN IF NOT EXISTS arrival_mileage INT UNSIGNED NULL AFTER departed_at,
    ADD COLUMN IF NOT EXISTS arrival_condition VARCHAR(20) NULL AFTER arrival_mileage;

-- The kilometres on the clock when the trip started and when the truck reached
-- its destination, so the company sees how far it has driven at every stop.
ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS start_mileage INT UNSIGNED NULL AFTER departure_at,
    ADD COLUMN IF NOT EXISTS end_mileage INT UNSIGNED NULL AFTER start_mileage;

-- An email can copy other people and send replies to the person who wrote it:
-- the clearing instruction goes to the agent, and the agent's answer comes back
-- to the logistics manager who sent it, not to a no-reply address.
ALTER TABLE email_outbox
    ADD COLUMN IF NOT EXISTS cc_emails TEXT NULL AFTER to_name,
    ADD COLUMN IF NOT EXISTS reply_to VARCHAR(190) NULL AFTER cc_emails;

-- Clearing agents are suppliers like any other, filed under their own category
-- so the Clearing agent list on a customs clearance shows agents, not every
-- garage and fuel station.
INSERT INTO lookup_values (list_key, value, label, sort_order, is_active)
SELECT 'procurement_category', 'Clearing agent', 'Clearing agent', 65, 1
 WHERE NOT EXISTS (SELECT 1 FROM lookup_values WHERE list_key = 'procurement_category' AND value = 'Clearing agent');

-- A customs payment's method is picked from the payment methods, like every
-- other payment, instead of typed: older free-text entries are mapped across.
UPDATE clearance_payments SET payment_method = CASE
        WHEN LOWER(payment_method) LIKE '%mobile%' OR LOWER(payment_method) LIKE '%momo%' THEN 'mobile_money'
        WHEN LOWER(payment_method) LIKE '%bank%' OR LOWER(payment_method) LIKE '%transfer%' THEN 'bank_transfer'
        WHEN LOWER(payment_method) LIKE '%cheque%' OR LOWER(payment_method) LIKE '%check%' THEN 'cheque'
        WHEN LOWER(payment_method) LIKE '%card%' THEN 'card'
        WHEN LOWER(payment_method) LIKE '%cash%' THEN 'cash'
        ELSE payment_method END
 WHERE payment_method IS NOT NULL
   AND payment_method NOT IN ('bank_transfer', 'mobile_money', 'cash', 'cheque', 'card', 'fuel_card', 'cash_usd');

-- Shipping lines (Maersk, MSC, CMA CGM…) are suppliers too, in their own
-- category, so a container's Shipping line list shows lines, not garages.
INSERT INTO lookup_values (list_key, value, label, sort_order, is_active)
SELECT 'procurement_category', 'Shipping line', 'Shipping line', 66, 1
 WHERE NOT EXISTS (SELECT 1 FROM lookup_values WHERE list_key = 'procurement_category' AND value = 'Shipping line');
