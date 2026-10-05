-- Price offers: the company keeps general rate cards (one per route, for all
-- customers), and when a customer asks for prices the office picks the cards
-- that fit and sends them as one offer. Once the customer accepts, the transport
-- request is raised from the offer and goes through approval as before.
-- Safe to run more than once.

CREATE TABLE IF NOT EXISTS price_offers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    offer_code VARCHAR(32) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    valid_until DATE NULL,
    message TEXT NULL,
    status ENUM('draft','sent','accepted','declined') NOT NULL DEFAULT 'draft',
    sent_at DATETIME NULL,
    accepted_by VARCHAR(150) NULL,
    accepted_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_price_offer (offer_code),
    KEY idx_price_offer_customer (customer_id, status),
    CONSTRAINT fk_price_offer_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS price_offer_lines (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    offer_id INT UNSIGNED NOT NULL,
    rate_card_id INT UNSIGNED NOT NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_price_offer_line (offer_id),
    CONSTRAINT fk_price_offer_line_offer FOREIGN KEY (offer_id) REFERENCES price_offers(id) ON DELETE CASCADE,
    CONSTRAINT fk_price_offer_line_rate FOREIGN KEY (rate_card_id) REFERENCES rate_cards(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A rate card is either the whole truck for one customer, or a place on a truck
-- shared by several customers. Cards written before default from their basis:
-- a price per trip is a full truck, a price by weight or volume is shared.
ALTER TABLE rate_cards
    ADD COLUMN IF NOT EXISTS service_type ENUM('full_truck','shared') NULL AFTER customer_id;
UPDATE rate_cards SET service_type = IF(rate_type IN ('per_trip','per_day'), 'full_truck', 'shared') WHERE service_type IS NULL;

-- A price per kg worked out from a full load (7,000 for 28,000 kg) needs more
-- than two decimals: 0.25 is exact, 0.0857 is not.
ALTER TABLE rate_cards MODIFY COLUMN rate_amount DECIMAL(14,4) NOT NULL;

ALTER TABLE transport_requests
    ADD COLUMN IF NOT EXISTS price_offer_id INT UNSIGNED NULL AFTER customer_id;

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
    ('price_offers', 'Price offers', 'Commercial', 115)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'price_offers', 1, 1, 1, 1, 1 FROM roles r WHERE r.role_key IN ('super_admin', 'logistics_manager')
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'price_offers', 1, 0, 0, 0, 0 FROM roles r WHERE r.role_key IN ('finance', 'management')
ON DUPLICATE KEY UPDATE can_view = 1;
