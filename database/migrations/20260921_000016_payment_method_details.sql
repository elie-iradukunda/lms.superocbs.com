-- The account details behind a payment method.
--
-- One free-text line was not enough. A bank transfer needs the bank, the branch,
-- the name the account is held in and the number; mobile money needs the network,
-- the pay code and the phone it is registered to. Typed into a single box they
-- come out differently every time and a customer has to guess which part is the
-- account number.
--
-- Each part is its own field now, so the invoice can lay them out the same way
-- every time and finance can correct one of them without retyping the rest.

ALTER TABLE payment_methods
    ADD COLUMN IF NOT EXISTS provider_name VARCHAR(120) NULL AFTER gl_account_id,
    ADD COLUMN IF NOT EXISTS account_name VARCHAR(160) NULL AFTER provider_name,
    ADD COLUMN IF NOT EXISTS account_number VARCHAR(80) NULL AFTER account_name,
    ADD COLUMN IF NOT EXISTS branch_name VARCHAR(120) NULL AFTER account_number,
    ADD COLUMN IF NOT EXISTS swift_code VARCHAR(20) NULL AFTER branch_name,
    ADD COLUMN IF NOT EXISTS phone_number VARCHAR(40) NULL AFTER swift_code;

-- What was typed into the old single line is kept as the extra note, since it
-- may hold something none of the new fields covers.
UPDATE payment_methods
   SET instructions = TRIM(CONCAT(COALESCE(instructions, ''), ' ', COALESCE(payment_details, '')))
 WHERE payment_details IS NOT NULL
   AND payment_details <> ''
   AND payment_details NOT LIKE '%goes here%'
   AND payment_details NOT LIKE 'Paid at the office'
   AND payment_details NOT LIKE 'Made payable%'
   AND payment_details NOT LIKE 'Billed monthly%';

-- The starting rows described themselves in prose; give them the shape instead
-- so finance sees empty fields to fill rather than a sentence to replace.
UPDATE payment_methods SET provider_name = 'Your bank', payment_details = NULL WHERE method_key IN ('bank_transfer', 'cheque') AND provider_name IS NULL;
UPDATE payment_methods SET provider_name = 'Your mobile network', payment_details = NULL WHERE method_key = 'mobile_money' AND provider_name IS NULL;
UPDATE payment_methods SET payment_details = NULL WHERE method_key IN ('cash', 'card', 'fuel_card');
