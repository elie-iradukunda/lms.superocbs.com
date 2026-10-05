-- Taxes paid at a border on the customer's behalf (import VAT, duty, levies)
-- are not the company's cost. Each border charge says who it was paid for: our
-- own cost, or the customer's, to be recharged at cost on their invoice.
ALTER TABLE border_charges
    ADD COLUMN IF NOT EXISTS paid_for ENUM('us','customer') NOT NULL DEFAULT 'us' AFTER amount;

-- The disbursement a clearance raises for those charges points back at it, so
-- it is never booked twice: the clearance already put it in the books.
ALTER TABLE disbursements
    ADD COLUMN IF NOT EXISTS crossing_id INT UNSIGNED NULL AFTER shipment_id,
    ADD INDEX IF NOT EXISTS idx_disbursement_crossing (crossing_id);
