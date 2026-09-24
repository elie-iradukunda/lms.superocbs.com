-- Routes between warehouses, and a load that finishes before the trip does.
--
-- Three things this fixes, all of which are how the business actually runs:
--
-- 1. A price belongs to a pair of warehouses, not to two typed place names.
--    "Kigali" and "kigali " and "Kigali Central" were three different routes as
--    far as pricing was concerned, so the same run could be quoted three ways.
--    A rate card now names the warehouse it leaves and the one it arrives at.
--
-- 2. A stop in the middle is a priced leg of its own. A truck running Kigali to
--    Rusumo that drops part of its load at Kayonza is doing two jobs, and the
--    customer whose goods stop at Kayonza pays the Kigali-to-Kayonza price, not
--    a share of the Rusumo one.
--
-- 3. A load is finished when it reaches its own warehouse, whatever the truck
--    does next. The driver hands it over at Kayonza and drives on to Rusumo;
--    that shipment is at its destination and is waiting only for the customer to
--    come and collect it. Until now a delivery could only be "in transit" or
--    "delivered", so a load sitting in a warehouse had to be called one or the
--    other, and both were wrong.
--
--        in_transit      on the truck
--        at_destination  dropped at its warehouse, waiting to be collected
--        delivered       the customer has it, and signed for it
--
-- Free-text origin and destination stay on every table. Not every place a truck
-- stops is a warehouse this company owns, and a customer's own yard never will
-- be.

-- ---------------------------------------------------------------- rate cards
ALTER TABLE rate_cards
    ADD COLUMN IF NOT EXISTS origin_warehouse_id INT UNSIGNED NULL AFTER customer_id,
    ADD COLUMN IF NOT EXISTS destination_warehouse_id INT UNSIGNED NULL AFTER origin_warehouse_id;

ALTER TABLE rate_cards DROP FOREIGN KEY IF EXISTS fk_rate_origin_wh;
ALTER TABLE rate_cards ADD CONSTRAINT fk_rate_origin_wh FOREIGN KEY (origin_warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL;
ALTER TABLE rate_cards DROP FOREIGN KEY IF EXISTS fk_rate_destination_wh;
ALTER TABLE rate_cards ADD CONSTRAINT fk_rate_destination_wh FOREIGN KEY (destination_warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL;

-- Where a card's typed place name already matches a warehouse, join them up, so
-- existing cards start pricing by warehouse without being retyped.
UPDATE rate_cards r
  INNER JOIN warehouses w ON w.deleted_at IS NULL AND (w.warehouse_name = r.origin OR w.location = r.origin)
    SET r.origin_warehouse_id = w.id
  WHERE r.origin_warehouse_id IS NULL;

UPDATE rate_cards r
  INNER JOIN warehouses w ON w.deleted_at IS NULL AND (w.warehouse_name = r.destination OR w.location = r.destination)
    SET r.destination_warehouse_id = w.id
  WHERE r.destination_warehouse_id IS NULL;

-- ----------------------------------------------------------------- shipments
ALTER TABLE shipments
    ADD COLUMN IF NOT EXISTS origin_warehouse_id INT UNSIGNED NULL AFTER origin,
    ADD COLUMN IF NOT EXISTS destination_warehouse_id INT UNSIGNED NULL AFTER destination;

ALTER TABLE shipments DROP FOREIGN KEY IF EXISTS fk_shipment_origin_wh;
ALTER TABLE shipments ADD CONSTRAINT fk_shipment_origin_wh FOREIGN KEY (origin_warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL;
ALTER TABLE shipments DROP FOREIGN KEY IF EXISTS fk_shipment_destination_wh;
ALTER TABLE shipments ADD CONSTRAINT fk_shipment_destination_wh FOREIGN KEY (destination_warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL;

UPDATE shipments s
  INNER JOIN warehouses w ON w.deleted_at IS NULL AND (w.warehouse_name = s.origin OR w.location = s.origin)
    SET s.origin_warehouse_id = w.id
  WHERE s.origin_warehouse_id IS NULL;

UPDATE shipments s
  INNER JOIN warehouses w ON w.deleted_at IS NULL AND (w.warehouse_name = s.destination OR w.location = s.destination)
    SET s.destination_warehouse_id = w.id
  WHERE s.destination_warehouse_id IS NULL;

-- ---------------------------------------------------------------- trip stops
-- A stop that is one of our warehouses can be named rather than typed, which is
-- what lets the system know a load has reached its own destination.
ALTER TABLE trip_stops
    ADD COLUMN IF NOT EXISTS warehouse_id INT UNSIGNED NULL AFTER stop_type;

ALTER TABLE trip_stops DROP FOREIGN KEY IF EXISTS fk_stop_warehouse;
ALTER TABLE trip_stops ADD CONSTRAINT fk_stop_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL;

UPDATE trip_stops t
  INNER JOIN warehouses w ON w.deleted_at IS NULL AND w.warehouse_name = t.location_name
    SET t.warehouse_id = w.id
  WHERE t.warehouse_id IS NULL;

-- ----------------------------------------------------------------- deliveries
-- The state that was missing: at its warehouse, not yet in the customer's hands.
ALTER TABLE deliveries
    MODIFY COLUMN status ENUM('loading', 'in_transit', 'at_destination', 'delivered', 'failed') NOT NULL DEFAULT 'loading';

ALTER TABLE deliveries
    ADD COLUMN IF NOT EXISTS destination_warehouse_id INT UNSIGNED NULL AFTER destination,
    ADD COLUMN IF NOT EXISTS dropped_at DATETIME NULL AFTER planned_at,
    ADD COLUMN IF NOT EXISTS collected_by VARCHAR(150) NULL AFTER delivered_by,
    ADD COLUMN IF NOT EXISTS collected_id_no VARCHAR(60) NULL AFTER collected_by;

ALTER TABLE deliveries DROP FOREIGN KEY IF EXISTS fk_delivery_warehouse;
ALTER TABLE deliveries ADD CONSTRAINT fk_delivery_warehouse FOREIGN KEY (destination_warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL;

UPDATE deliveries d
  INNER JOIN warehouses w ON w.deleted_at IS NULL AND (w.warehouse_name = d.destination OR w.location = d.destination)
    SET d.destination_warehouse_id = w.id
  WHERE d.destination_warehouse_id IS NULL;
