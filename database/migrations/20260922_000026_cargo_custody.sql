-- What is physically sitting in a depot, and when it stopped being there.
--
-- A depot holds two different things and they must never be added together:
--
--   the company's own stock  spare parts, tyres, oil. Bought, valued, consumed.
--                            That is `inventory_items` and it already works.
--
--   a customer's cargo       200 bundles belonging to Nairobi Trading Company,
--                            waiting for a truck. We never own it, never value
--                            it in our books, and it leaves as it arrived.
--
-- Until now only the first was recorded, so a depot full of cargo read as empty
-- and nobody could answer the two questions a depot manager is actually asked:
-- what is in my shed, and where did the load that was here yesterday go.
--
-- This table answers both. It is a movement log, not a balance: every arrival
-- and every departure is a row, each carrying the document that caused it, so
-- "it left on TRP-2026-0003 at 06:14, signed out by Aline" is always available.
-- What is on hand is the arrivals minus the departures, worked out from these
-- rows rather than stored anywhere that could drift out of step with them.

CREATE TABLE IF NOT EXISTS warehouse_cargo (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    movement_code VARCHAR(40) NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    shipment_id INT UNSIGNED NOT NULL,
    -- `in` is cargo arriving at this depot; `out` is cargo leaving it.
    direction ENUM('in', 'out') NOT NULL,
    -- Why it moved. Received: brought in by the customer. Loaded: onto a truck.
    -- Arrived: off a truck at its destination depot. Collected: taken away by
    -- the customer. Returned: brought back because the delivery failed.
    reason ENUM('received', 'loaded', 'arrived', 'collected', 'returned') NOT NULL,
    packages INT UNSIGNED NULL,
    weight_kg DECIMAL(12, 2) NOT NULL DEFAULT 0,
    trip_id INT UNSIGNED NULL,
    delivery_id INT UNSIGNED NULL,
    moved_at DATETIME NOT NULL,
    performed_by INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_cargo_movement (movement_code),
    -- One shipment cannot arrive at the same depot twice for the same reason,
    -- which is what makes a repeated dispatch or a double-pressed button safe.
    UNIQUE KEY uniq_cargo_step (shipment_id, warehouse_id, reason),
    KEY idx_cargo_warehouse (warehouse_id, moved_at),
    KEY idx_cargo_shipment (shipment_id, moved_at),
    CONSTRAINT fk_cargo_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE CASCADE,
    CONSTRAINT fk_cargo_shipment FOREIGN KEY (shipment_id) REFERENCES shipments (id) ON DELETE CASCADE,
    CONSTRAINT fk_cargo_trip FOREIGN KEY (trip_id) REFERENCES trips (id) ON DELETE SET NULL,
    CONSTRAINT fk_cargo_delivery FOREIGN KEY (delivery_id) REFERENCES deliveries (id) ON DELETE SET NULL,
    CONSTRAINT fk_cargo_user FOREIGN KEY (performed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The register needs no permission of its own. It is read on the depot page and
-- on the consignment page, each of which is already governed by the permission
-- that page carries; inventing a third would be a switch on the permissions
-- screen that turns nothing off.
DELETE FROM role_permissions WHERE permission_key = 'cargo';

-- Cargo already booked out of a depot before this table existed is written in
-- as having been received there, so the register does not start with loads that
-- leave a depot they were never recorded as entering.
INSERT IGNORE INTO warehouse_cargo
    (movement_code, warehouse_id, shipment_id, direction, reason, packages, weight_kg, trip_id, moved_at, notes)
SELECT CONCAT('CGO-BF-', LPAD(s.id, 6, '0')),
       s.origin_warehouse_id,
       s.id,
       'in',
       'received',
       s.packages_count,
       COALESCE(s.weight_kg, 0),
       s.trip_id,
       COALESCE(s.booked_at, s.created_at, NOW()),
       'Brought forward when the cargo register was added.'
  FROM shipments s
 WHERE s.origin_warehouse_id IS NOT NULL
   AND s.deleted_at IS NULL;
