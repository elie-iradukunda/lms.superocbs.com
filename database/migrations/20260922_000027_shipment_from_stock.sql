-- A load that comes out of our own shed.
--
-- Two kinds of consignment leave a depot and they behave differently:
--
--   the customer's own goods   they brought them to us, we carry them, they
--                              collect them. The cargo register follows them
--                              in and out and nothing is bought or sold.
--
--   goods we hold              the customer orders 8 tyres we have in Kigali.
--                              Carrying them away is not just a movement of
--                              cargo: it is stock leaving the company, and the
--                              shed has 8 fewer tyres afterwards.
--
-- Until now only the first existed, so an order against our own stock left the
-- balance untouched: the system went on saying there were 16 tyres in Kigali
-- long after 8 of them had been driven to Nairobi. The next person to order
-- against that number was ordering something that was not there.
--
-- Naming the item on the shipment is what closes it. When the truck is
-- dispatched, the quantity is taken out of the depot through the ordinary stock
-- ledger — the same movements, the same running balance, the same audit trail
-- as any other issue — with the trip as the document behind it.
--
-- Both columns stay empty on a normal consignment, which is most of them.

ALTER TABLE shipments
    ADD COLUMN IF NOT EXISTS stock_item_id INT UNSIGNED NULL AFTER destination_warehouse_id,
    -- How much of that item this consignment is carrying, in the item's own
    -- unit of measure: 8 tyres, 200 litres, 40 sacks.
    ADD COLUMN IF NOT EXISTS stock_quantity DECIMAL(12, 2) NULL AFTER stock_item_id,
    -- Set once the movement has been written, so a trip dispatched twice cannot
    -- issue the same goods twice.
    ADD COLUMN IF NOT EXISTS stock_issued_at DATETIME NULL AFTER stock_quantity;

ALTER TABLE shipments
    DROP FOREIGN KEY IF EXISTS fk_shipment_stock_item;

ALTER TABLE shipments
    ADD CONSTRAINT fk_shipment_stock_item FOREIGN KEY (stock_item_id) REFERENCES inventory_items (id) ON DELETE SET NULL;

-- The same request may be raised against our stock, so it can be priced and
-- approved before anybody books a truck for it.
ALTER TABLE transport_requests
    ADD COLUMN IF NOT EXISTS stock_item_id INT UNSIGNED NULL AFTER destination_warehouse_id,
    ADD COLUMN IF NOT EXISTS stock_quantity DECIMAL(12, 2) NULL AFTER stock_item_id;

ALTER TABLE transport_requests
    DROP FOREIGN KEY IF EXISTS fk_request_stock_item;

ALTER TABLE transport_requests
    ADD CONSTRAINT fk_request_stock_item FOREIGN KEY (stock_item_id) REFERENCES inventory_items (id) ON DELETE SET NULL;
