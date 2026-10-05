-- Run once in phpMyAdmin on the online database (SQL tab). Safe to run twice.
ALTER TABLE payment_methods
    ADD COLUMN IF NOT EXISTS opening_balance DECIMAL(16,2) NULL AFTER currency,
    ADD COLUMN IF NOT EXISTS opening_date DATE NULL AFTER opening_balance;
INSERT IGNORE INTO migrations (migration) VALUES ('20261005_000043_payment_method_opening.sql');
