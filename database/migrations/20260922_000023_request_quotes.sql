-- Quoting a customer before the truck moves.
--
-- A request said what somebody wanted carried. It could not say what it would
-- cost, so the price was agreed on the phone and appeared for the first time on
-- an invoice weeks later — by which time nobody could show what had been agreed
-- or who agreed it.
--
-- A request now carries its own quote, worked out from the rate card for that
-- pair of warehouses and that weight, and a record of the customer accepting it.
-- The status tells the whole story:
--
--     pending    the customer has asked; nobody has priced it
--     quoted     a price has been worked out and sent to them
--     approved   the customer accepted that price; it may be planned into a trip
--     assigned   a trip exists for it
--
-- The accepted figure is kept on the request. Renegotiating the rate card next
-- month must not change what this customer was told and agreed to today.

ALTER TABLE transport_requests
    MODIFY COLUMN status ENUM('pending', 'quoted', 'approved', 'assigned', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending';

ALTER TABLE transport_requests
    ADD COLUMN IF NOT EXISTS origin_warehouse_id INT UNSIGNED NULL AFTER pickup_location,
    ADD COLUMN IF NOT EXISTS destination_warehouse_id INT UNSIGNED NULL AFTER destination,
    ADD COLUMN IF NOT EXISTS rate_card_id INT UNSIGNED NULL AFTER weight_kg,
    ADD COLUMN IF NOT EXISTS quoted_amount DECIMAL(14,2) NULL AFTER rate_card_id,
    -- In words, so the customer who asks "why that much?" gets the working.
    ADD COLUMN IF NOT EXISTS quote_basis VARCHAR(160) NULL AFTER quoted_amount,
    ADD COLUMN IF NOT EXISTS currency CHAR(3) NOT NULL DEFAULT 'RWF' AFTER quote_basis,
    ADD COLUMN IF NOT EXISTS quoted_at DATETIME NULL AFTER currency,
    ADD COLUMN IF NOT EXISTS accepted_at DATETIME NULL AFTER quoted_at,
    ADD COLUMN IF NOT EXISTS accepted_by VARCHAR(150) NULL AFTER accepted_at;

ALTER TABLE transport_requests DROP FOREIGN KEY IF EXISTS fk_request_origin_wh;
ALTER TABLE transport_requests ADD CONSTRAINT fk_request_origin_wh FOREIGN KEY (origin_warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL;
ALTER TABLE transport_requests DROP FOREIGN KEY IF EXISTS fk_request_destination_wh;
ALTER TABLE transport_requests ADD CONSTRAINT fk_request_destination_wh FOREIGN KEY (destination_warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL;
ALTER TABLE transport_requests DROP FOREIGN KEY IF EXISTS fk_request_rate_card;
ALTER TABLE transport_requests ADD CONSTRAINT fk_request_rate_card FOREIGN KEY (rate_card_id) REFERENCES rate_cards(id) ON DELETE SET NULL;

UPDATE transport_requests r
  INNER JOIN warehouses w ON w.deleted_at IS NULL AND (w.warehouse_name = r.pickup_location OR w.location = r.pickup_location)
    SET r.origin_warehouse_id = w.id
  WHERE r.origin_warehouse_id IS NULL;

UPDATE transport_requests r
  INNER JOIN warehouses w ON w.deleted_at IS NULL AND (w.warehouse_name = r.destination OR w.location = r.destination)
    SET r.destination_warehouse_id = w.id
  WHERE r.destination_warehouse_id IS NULL;
