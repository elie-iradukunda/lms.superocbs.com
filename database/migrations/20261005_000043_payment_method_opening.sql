-- The money each account held on the day the company started using the system.
ALTER TABLE payment_methods
    ADD COLUMN IF NOT EXISTS opening_balance DECIMAL(16,2) NULL AFTER currency,
    ADD COLUMN IF NOT EXISTS opening_date DATE NULL AFTER opening_balance;
