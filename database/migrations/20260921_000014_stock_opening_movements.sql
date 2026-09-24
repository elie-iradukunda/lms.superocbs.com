-- Give every stock item an opening movement, and rebuild the running balances.
--
-- `inventory_items.quantity` was meant to be the running total of the stock
-- ledger, but the quantity typed when an item was first created never became a
-- movement. Summing the movements therefore missed it: an item opened with 120
-- litres and issued 20 came out at minus 20, which was then clamped to zero.
--
-- Opening stock is a movement now, recorded when the item is created. This puts
-- one in for the items that were created before that, so the ledger accounts for
-- the whole balance instead of starting from a number with no history.
--
-- The opening quantity is reconstructed as (balance on the item) minus (what the
-- movements already explain). Where the clamp had already destroyed the figure,
-- the reconstruction cannot bring it back; those items come out low and need an
-- adjustment movement for the difference, which is what a stock count would
-- produce anyway.

INSERT INTO stock_movements
    (movement_code, item_id, warehouse_id, movement_type, quantity, unit_cost,
     balance_after, reference_type, reference_code, performed_by, moved_at, notes)
SELECT CONCAT('MOV-OPEN-', LPAD(i.id, 5, '0')),
       i.id,
       i.warehouse_id,
       'stock_in',
       GREATEST(0, i.quantity - COALESCE(m.moved, 0)),
       i.unit_cost,
       GREATEST(0, i.quantity - COALESCE(m.moved, 0)),
       'adjustment',
       'OPENING',
       NULL,
       COALESCE(i.created_at, NOW()),
       'Stock on hand when the item was first recorded.'
  FROM inventory_items i
  LEFT JOIN (
        SELECT item_id,
               SUM(CASE WHEN movement_type IN ('stock_in', 'transfer_in', 'return', 'adjustment')
                        THEN quantity ELSE -quantity END) AS moved
          FROM stock_movements
         GROUP BY item_id
  ) m ON m.item_id = i.id
 WHERE i.deleted_at IS NULL
   AND NOT EXISTS (
        SELECT 1 FROM stock_movements o
         WHERE o.item_id = i.id AND o.reference_code = 'OPENING'
   )
   AND GREATEST(0, i.quantity - COALESCE(m.moved, 0)) > 0;
