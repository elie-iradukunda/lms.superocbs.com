-- Money in more than one country.
--
-- The company works across Rwanda, Kenya and Tanzania, so a driver buys fuel in
-- shillings at Namanga and a customer is invoiced in francs in Kigali. Until now
-- every amount was assumed to be in one currency, which meant a KES figure typed
-- into an expense was added to RWF figures as though the two were the same
-- number. The books were wrong by whatever the exchange rate happened to be.
--
-- The rule from here on is the one accountants use: a record keeps BOTH figures.
-- The original amount in the currency it was actually spent or charged in, so
-- the receipt and the record agree; and the same amount converted to the
-- company's own currency at the rate of the day, because a set of books can only
-- add up in one currency.
--
--     amount            what was written on the receipt      KES 12,500
--     currency          what the receipt was in              KES
--     exchange_rate     francs per shilling on that day      12.35
--     base_amount       what goes in the books               RWF 154,375
--
-- The ledger only ever reads base_amount. Nothing else changes about how the
-- books work, and a single-country installation simply leaves everything at the
-- base currency with a rate of 1.

CREATE TABLE IF NOT EXISTS currencies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code CHAR(3) NOT NULL COMMENT 'ISO 4217: RWF, KES, TZS, USD',
    currency_name VARCHAR(60) NOT NULL,
    symbol VARCHAR(8) NULL,
    country VARCHAR(60) NULL,
    decimals TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'RWF and TZS are whole units; USD and KES take cents',
    -- Exactly one currency is the base. Everything in the books is in it.
    is_base TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_currency_code (code),
    INDEX idx_currency_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A rate is a fact about a day, not a property of a currency: the rate used on
-- an old record must never change because today's rate moved. Each row is the
-- rate from one day onward, and a record takes the latest rate on or before its
-- own date.
CREATE TABLE IF NOT EXISTS currency_rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    currency_code CHAR(3) NOT NULL,
    effective_from DATE NOT NULL,
    -- How many units of the base currency one unit of this currency buys.
    rate DECIMAL(18,8) NOT NULL,
    source VARCHAR(80) NULL COMMENT 'where the rate came from: BNR, the bank, an agreement',
    notes VARCHAR(255) NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rate_day (currency_code, effective_from),
    INDEX idx_rate_lookup (currency_code, effective_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The three countries the company works in, plus the dollar that cross-border
-- freight is often quoted in. Rates are left for finance to enter: a made-up
-- rate in a set of books is worse than no rate at all.
INSERT INTO currencies (code, currency_name, symbol, country, decimals, is_base, sort_order) VALUES
('RWF', 'Rwandan franc',      'FRw', 'Rwanda',        0, 1, 10),
('KES', 'Kenyan shilling',    'KSh', 'Kenya',         2, 0, 20),
('TZS', 'Tanzanian shilling', 'TSh', 'Tanzania',      0, 0, 30),
('UGX', 'Ugandan shilling',   'USh', 'Uganda',        0, 0, 40),
('USD', 'US dollar',          '$',   'International', 2, 0, 50)
ON DUPLICATE KEY UPDATE currency_name = VALUES(currency_name), symbol = VALUES(symbol), country = VALUES(country), decimals = VALUES(decimals);

-- The base currency converts to itself, always, at one.
INSERT INTO currency_rates (currency_code, effective_from, rate, source, notes)
SELECT 'RWF', '2000-01-01', 1.00000000, 'Base currency', 'The books are kept in this currency, so it never converts.'
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT currency_code FROM currency_rates) r WHERE r.currency_code = 'RWF');

-- ------------------------------------------------- the records that hold money
-- Each keeps what was actually written on the paper, and what that came to in
-- the books. base_amount is what every report and the ledger read.
ALTER TABLE expenses
    ADD COLUMN IF NOT EXISTS currency CHAR(3) NOT NULL DEFAULT 'RWF' AFTER amount,
    ADD COLUMN IF NOT EXISTS exchange_rate DECIMAL(18,8) NOT NULL DEFAULT 1.00000000 AFTER currency,
    ADD COLUMN IF NOT EXISTS base_amount DECIMAL(14,2) NULL AFTER exchange_rate;

ALTER TABLE fuel_records
    ADD COLUMN IF NOT EXISTS currency CHAR(3) NOT NULL DEFAULT 'RWF' AFTER unit_price,
    ADD COLUMN IF NOT EXISTS exchange_rate DECIMAL(18,8) NOT NULL DEFAULT 1.00000000 AFTER currency,
    ADD COLUMN IF NOT EXISTS base_amount DECIMAL(14,2) NULL AFTER exchange_rate;

ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS currency CHAR(3) NOT NULL DEFAULT 'RWF' AFTER total_amount,
    ADD COLUMN IF NOT EXISTS exchange_rate DECIMAL(18,8) NOT NULL DEFAULT 1.00000000 AFTER currency,
    ADD COLUMN IF NOT EXISTS base_amount DECIMAL(14,2) NULL AFTER exchange_rate;

ALTER TABLE payments
    ADD COLUMN IF NOT EXISTS currency CHAR(3) NOT NULL DEFAULT 'RWF' AFTER amount,
    ADD COLUMN IF NOT EXISTS exchange_rate DECIMAL(18,8) NOT NULL DEFAULT 1.00000000 AFTER currency,
    ADD COLUMN IF NOT EXISTS base_amount DECIMAL(14,2) NULL AFTER exchange_rate;

ALTER TABLE purchase_requests
    ADD COLUMN IF NOT EXISTS currency CHAR(3) NOT NULL DEFAULT 'RWF' AFTER amount,
    ADD COLUMN IF NOT EXISTS exchange_rate DECIMAL(18,8) NOT NULL DEFAULT 1.00000000 AFTER currency,
    ADD COLUMN IF NOT EXISTS base_amount DECIMAL(14,2) NULL AFTER exchange_rate;

-- Everything recorded before today was in the base currency at a rate of one,
-- so its base amount is simply what it already said.
UPDATE expenses          SET base_amount = amount       WHERE base_amount IS NULL;
-- A fill-up has no stored total: it is litres times the price at the pump.
UPDATE fuel_records      SET base_amount = ROUND(litres * unit_price, 2) WHERE base_amount IS NULL;
UPDATE invoices          SET base_amount = total_amount WHERE base_amount IS NULL;
UPDATE payments          SET base_amount = amount       WHERE base_amount IS NULL;
UPDATE purchase_requests SET base_amount = amount       WHERE base_amount IS NULL;

-- ---------------------------------------------------------------- permissions
INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('currencies', 'Currencies', 'Accounting', 360),
('currency_rates', 'Exchange rates', 'Accounting', 370)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.k, 1, 1, 1, 1, 1
  FROM roles r JOIN (SELECT 'currencies' k UNION ALL SELECT 'currency_rates') p
 WHERE r.role_key IN ('super_admin', 'finance')
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

-- Everyone who records money needs to be able to read the list of currencies,
-- or the drop-down on their own form would be empty.
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'currencies', 1, 0, 0, 0, 0
  FROM roles r WHERE r.role_key IN ('logistics_manager', 'fleet_manager', 'warehouse_manager', 'management', 'driver')
ON DUPLICATE KEY UPDATE can_view = 1;

INSERT INTO company_settings (setting_key, setting_value, setting_label, setting_group, input_type) VALUES
('base_currency', 'RWF', 'The currency the books are kept in', 'Company', 'text')
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label);
