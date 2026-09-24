-- Say which money it is, everywhere money is written.
--
-- A credit limit of 20,000,000 means one thing in francs and quite another in
-- shillings, and a rate card for a Nairobi route is agreed in the money the
-- customer actually pays in. Leaving the currency unsaid made every one of
-- these figures depend on the reader guessing right.
--
-- These are agreements and limits, not postings: nothing here reaches the
-- ledger, so nothing is converted. The figure is stored exactly as it was
-- agreed, in the currency it was agreed in, and shown that way. Conversion
-- belongs only where amounts have to be added together — the expenses, invoices,
-- payments and purchases that the books are made of, which already carry a rate.

ALTER TABLE customers
    ADD COLUMN IF NOT EXISTS currency CHAR(3) NOT NULL DEFAULT 'RWF' AFTER credit_limit;

ALTER TABLE rate_cards
    ADD COLUMN IF NOT EXISTS currency CHAR(3) NOT NULL DEFAULT 'RWF' AFTER rate_amount;

ALTER TABLE shipments
    ADD COLUMN IF NOT EXISTS currency CHAR(3) NOT NULL DEFAULT 'RWF' AFTER declared_value;

-- A quote inherits the currency of the card it came from, so a Nairobi route
-- priced in shillings stays in shillings all the way to the customer.
UPDATE shipments s
  INNER JOIN rate_cards r ON r.id = s.rate_card_id
    SET s.currency = r.currency
  WHERE s.rate_card_id IS NOT NULL;

UPDATE transport_requests t
  INNER JOIN rate_cards r ON r.id = t.rate_card_id
    SET t.currency = r.currency
  WHERE t.rate_card_id IS NOT NULL;
