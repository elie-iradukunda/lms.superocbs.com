-- How a company takes money, decided by the company.
--
-- The ways to pay were a fixed list in the code, and each one was mapped to a
-- hard-coded account number in `Posting`. A company banking with a different
-- bank, or taking MoMo on a second number, or adding a new wallet, could not say
-- so — and finance could not tell which account a receipt had actually landed
-- in, because the mapping was invisible.
--
-- A payment method is a record now. It names the account in the chart of
-- accounts that money moves through, carries the details the customer needs in
-- order to pay, and can be retired without touching the payments already filed
-- under it.

CREATE TABLE IF NOT EXISTS payment_methods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- What the payments table stores. Kept stable so history never re-reads.
    method_key VARCHAR(40) NOT NULL,
    method_name VARCHAR(80) NOT NULL,
    -- Where the money lands. A receipt debits this; a payment out credits it.
    gl_account_id INT UNSIGNED NULL,
    -- The bank name, account number or MoMo code printed on an invoice.
    payment_details VARCHAR(255) NULL,
    instructions VARCHAR(255) NULL,
    direction ENUM('in', 'out', 'both') NOT NULL DEFAULT 'both',
    show_on_invoice TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payment_method (method_key),
    INDEX idx_payment_method_order (sort_order, method_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE payment_methods DROP FOREIGN KEY IF EXISTS fk_payment_method_account;
ALTER TABLE payment_methods
    ADD CONSTRAINT fk_payment_method_account FOREIGN KEY (gl_account_id) REFERENCES gl_accounts(id) ON DELETE SET NULL;

-- The ways the code already knew about, now as editable records pointing at the
-- accounts Posting used to hard-code.
INSERT INTO payment_methods (method_key, method_name, gl_account_id, payment_details, direction, show_on_invoice, sort_order)
SELECT v.k, v.n, a.id, v.d, v.dir, v.inv, v.o
FROM (
              SELECT 'bank_transfer' k, 'Bank transfer' n, '1020' code, 'Account name and number go here' d, 'both' dir, 1 inv, 10 o
    UNION ALL SELECT 'mobile_money',  'Mobile money',  '1030', 'MoMo pay code goes here',            'both', 1, 20
    UNION ALL SELECT 'cash',          'Cash',          '1010', 'Paid at the office',                 'both', 1, 30
    UNION ALL SELECT 'cheque',        'Cheque',        '1020', 'Made payable to the company',        'both', 1, 40
    UNION ALL SELECT 'card',          'Card',          '1020', NULL,                                 'both', 0, 50
    UNION ALL SELECT 'fuel_card',     'Fuel card',     '2000', 'Billed monthly by the fuel company', 'out',  0, 60
) v
LEFT JOIN gl_accounts a ON a.account_code = v.code AND a.deleted_at IS NULL
ON DUPLICATE KEY UPDATE method_name = VALUES(method_name);

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('payment_methods', 'Payment methods', 'Accounting', 340)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'payment_methods', 1, 1, 1, 1, 1 FROM roles r WHERE r.role_key IN ('super_admin', 'finance')
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'payment_methods', 1, 0, 0, 0, 0 FROM roles r WHERE r.role_key = 'management'
ON DUPLICATE KEY UPDATE can_view = 1;
