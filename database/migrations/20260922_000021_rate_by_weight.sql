-- Pricing a load by what it weighs, not just by the truck.
--
-- The way the business actually quotes is: "Kigali to Rusumo, a full truck is
-- 500 kg and costs 100,000." A customer with 100 kg for the same destination
-- pays a fifth of that — 20,000 — because they are taking a fifth of the truck.
--
-- A per-kilogram figure on its own cannot be checked by anyone: nobody knows
-- whether 200 a kilo is the right price. The pair it came from can be checked at
-- a glance, and it is how the price was agreed in the first place, so the card
-- keeps the pair and works the rate out from it.
--
--     full_load_kg     500          what a full truck carries on this route
--     full_load_price  100,000      what a full truck costs on this route
--     rate_amount      200          per kilogram, worked out from those two
--
-- Both ways of writing a card still work. A card with no full-load pair keeps
-- whatever rate was typed, which is right for per-trip and per-day work where
-- weight has nothing to do with the price.

ALTER TABLE rate_cards
    ADD COLUMN IF NOT EXISTS full_load_kg DECIMAL(12,2) NULL AFTER vehicle_type,
    ADD COLUMN IF NOT EXISTS full_load_price DECIMAL(14,2) NULL AFTER full_load_kg;

-- What a shipment was quoted, and which card said so.
--
-- Storing the figure rather than working it out on the fly is deliberate: a rate
-- card can be renegotiated next month, and a load already quoted at last month's
-- price must keep that price. The card it came from is kept beside it so anyone
-- asking "why 20,000?" can be shown the answer.
ALTER TABLE shipments
    ADD COLUMN IF NOT EXISTS rate_card_id INT UNSIGNED NULL AFTER declared_value,
    ADD COLUMN IF NOT EXISTS quoted_amount DECIMAL(14,2) NULL AFTER rate_card_id,
    -- In words: how the figure was arrived at, for the customer who asks.
    ADD COLUMN IF NOT EXISTS quote_basis VARCHAR(160) NULL AFTER quoted_amount;

ALTER TABLE shipments DROP FOREIGN KEY IF EXISTS fk_shipment_rate_card;
ALTER TABLE shipments ADD CONSTRAINT fk_shipment_rate_card FOREIGN KEY (rate_card_id) REFERENCES rate_cards(id) ON DELETE SET NULL;

-- Where a card was written as a full load, fill the pair in from what is there,
-- so an existing per-trip card reads the same way as a new one.
UPDATE rate_cards
   SET full_load_price = rate_amount
 WHERE full_load_price IS NULL
   AND rate_type = 'per_trip'
   AND rate_amount > 0;
