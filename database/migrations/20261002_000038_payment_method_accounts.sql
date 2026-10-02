-- Each payment method is one real account, with all its details: Bank of Kigali
-- RWF and Bank of Kigali USD are two methods, each with its bank, branch,
-- account number, account holder, SWIFT, currency and ledger account.
-- A bank statement is for one of those accounts, so it names the method, and
-- every payment shows the account the money left from or arrived in.
-- Safe to run more than once.

ALTER TABLE payment_methods
    ADD COLUMN IF NOT EXISTS currency CHAR(3) NULL AFTER gl_account_id;

ALTER TABLE bank_statements
    ADD COLUMN IF NOT EXISTS payment_method_id INT UNSIGNED NULL AFTER account_id;

-- Statements already written point at the bank transfer method of their ledger account.
UPDATE bank_statements bs
   INNER JOIN payment_methods pm ON pm.gl_account_id = bs.account_id AND pm.method_key = 'bank_transfer'
   SET bs.payment_method_id = pm.id
 WHERE bs.payment_method_id IS NULL;

-- The two placeholders written by an earlier update are not details: blank
-- them so the forms ask for the real bank instead of showing "Your bank".
UPDATE payment_methods SET provider_name = NULL WHERE provider_name IN ('Your bank', 'Your mobile network');
