USE logistics_mvc;

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- A chart of accounts for a Rwandan road-freight operator, and the opening
-- balances the books start from.
--
-- Idempotent: re-running replaces the same accounts and the same opening
-- journal entry rather than duplicating them.
-- ---------------------------------------------------------------------------

START TRANSACTION;

INSERT INTO gl_accounts
    (account_code, account_name, account_type, normal_balance, is_header, is_contra, depth, report_section, section_order, cash_flow_class, is_bank, description)
VALUES
-- assets -------------------------------------------------------------------
('1000', 'Cash and bank',                    'asset',        'debit',  1, 0, 0, 'Cash and bank',           10, 'cash',      0, 'Every account the company can pay from.'),
('1010', 'Petty cash',                       'asset',        'debit',  0, 0, 1, 'Cash and bank',           10, 'cash',      1, 'Cash held at the Kigali office.'),
('1020', 'Bank - main account',              'asset',        'debit',  0, 0, 1, 'Cash and bank',           10, 'cash',      1, 'The operating current account.'),
('1030', 'Mobile money',                     'asset',        'debit',  0, 0, 1, 'Cash and bank',           10, 'cash',      1, 'Driver float and field payments.'),
('1100', 'Accounts receivable',              'asset',        'debit',  0, 0, 0, 'Accounts receivable',     20, 'operating', 0, 'Invoices issued to customers and not yet settled.'),
('1200', 'Inventory',                        'asset',        'debit',  0, 0, 0, 'Other current assets',    30, 'operating', 0, 'Stock held in the warehouses.'),
('1250', 'Prepayments and deposits',         'asset',        'debit',  0, 0, 0, 'Other current assets',    30, 'operating', 0, 'Insurance and rent paid in advance.'),
('1500', 'Motor vehicles',                   'asset',        'debit',  0, 0, 0, 'Fixed assets',            40, 'investing', 0, 'The fleet, at cost.'),
('1510', 'Equipment and fittings',           'asset',        'debit',  0, 0, 0, 'Fixed assets',            40, 'investing', 0, 'Warehouse and office equipment, at cost.'),
('1590', 'Accumulated depreciation',         'asset',        'credit', 0, 1, 0, 'Fixed assets',            40, 'investing', 0, 'Depreciation charged to date on the fleet and equipment.'),

-- liabilities --------------------------------------------------------------
('2000', 'Accounts payable',                 'liability',    'credit', 0, 0, 0, 'Accounts payable',        50, 'operating', 0, 'Suppliers, workshops and fuel stations not yet paid.'),
('2100', 'VAT payable',                      'liability',    'credit', 0, 0, 0, 'Other current liabilities', 60, 'operating', 0, 'VAT charged on invoices and owed to RRA.'),
('2150', 'Accrued expenses',                 'liability',    'credit', 0, 0, 0, 'Other current liabilities', 60, 'operating', 0, 'Costs incurred but not yet invoiced by the supplier.'),
('2200', 'Payroll liabilities',              'liability',    'credit', 0, 0, 0, 'Other current liabilities', 60, 'operating', 0, 'Net pay, RSSB and PAYE owed.'),
('2500', 'Motor vehicle loans',              'liability',    'credit', 0, 0, 0, 'Long term liabilities',   70, 'financing', 0, 'Asset finance on the fleet.'),

-- equity -------------------------------------------------------------------
('3000', 'Share capital',                    'equity',       'credit', 0, 0, 0, 'Equity',                  80, 'financing', 0, 'Capital introduced by the owners.'),
('3100', 'Retained earnings',                'equity',       'credit', 0, 0, 0, 'Equity',                  80, 'financing', 0, 'Profit of earlier years kept in the business.'),
('3200', 'Drawings and dividends',           'equity',       'debit',  0, 0, 0, 'Equity',                  80, 'financing', 0, 'Profit taken out by the owners.'),

-- income -------------------------------------------------------------------
('4000', 'Freight revenue',                  'income',       'credit', 0, 0, 0, 'Income',                 100, 'operating', 0, 'Transport invoiced to customers.'),
('4100', 'Warehousing and handling revenue', 'income',       'credit', 0, 0, 0, 'Income',                 100, 'operating', 0, 'Storage, loading and handling charged out.'),
('4900', 'Other income',                     'income',       'credit', 0, 0, 0, 'Other income',           140, 'operating', 0, 'Anything outside the main trade.'),

-- cost of sales ------------------------------------------------------------
('5000', 'Fuel',                             'cost_of_sales','debit',  0, 0, 0, 'Cost of sales',          110, 'operating', 0, 'Diesel and petrol drawn against trips.'),
('5100', 'Driver allowances',                'cost_of_sales','debit',  0, 0, 0, 'Cost of sales',          110, 'operating', 0, 'Night-out and per-diem paid to drivers.'),
('5200', 'Tolls, parking and permits',       'cost_of_sales','debit',  0, 0, 0, 'Cost of sales',          110, 'operating', 0, 'Road charges incurred on a trip.'),
('5300', 'Subcontracted transport',          'cost_of_sales','debit',  0, 0, 0, 'Cost of sales',          110, 'operating', 0, 'Work given to another carrier.'),
('5400', 'Loading and handling',             'cost_of_sales','debit',  0, 0, 0, 'Cost of sales',          110, 'operating', 0, 'Casual labour at the warehouse and at drop points.'),

-- operating expenses -------------------------------------------------------
('6000', 'Vehicle maintenance and repairs',  'expense',      'debit',  0, 0, 0, 'Operating expenses',     120, 'operating', 0, 'Servicing, parts and workshop labour.'),
('6100', 'Insurance',                        'expense',      'debit',  0, 0, 0, 'Operating expenses',     120, 'operating', 0, 'Fleet and goods-in-transit cover.'),
('6200', 'Salaries and wages',               'expense',      'debit',  0, 0, 0, 'Operating expenses',     120, 'operating', 0, 'Staff pay other than driver allowances.'),
('6300', 'Office and administration',        'expense',      'debit',  0, 0, 0, 'Operating expenses',     120, 'operating', 0, 'Rent, communications, stationery and software.'),
('6400', 'Depreciation',                     'expense',      'debit',  0, 0, 0, 'Operating expenses',     120, 'operating', 0, 'Wear charged against the fleet and equipment.'),
('6500', 'Bank charges',                     'expense',      'debit',  0, 0, 0, 'Operating expenses',     120, 'operating', 0, 'Transfer fees and account charges.'),
('6900', 'Other expenses',                   'expense',      'debit',  0, 0, 0, 'Other expenses',         150, 'operating', 0, 'Anything that does not belong above.')
ON DUPLICATE KEY UPDATE
    account_name = VALUES(account_name), account_type = VALUES(account_type),
    normal_balance = VALUES(normal_balance), is_header = VALUES(is_header),
    is_contra = VALUES(is_contra), depth = VALUES(depth),
    report_section = VALUES(report_section), section_order = VALUES(section_order),
    cash_flow_class = VALUES(cash_flow_class), is_bank = VALUES(is_bank),
    description = VALUES(description), is_active = 1, deleted_at = NULL;

-- The three bank accounts sit under the "Cash and bank" header.
UPDATE gl_accounts c
  INNER JOIN gl_accounts p ON p.account_code = '1000'
    SET c.parent_id = p.id
  WHERE c.account_code IN ('1010', '1020', '1030');


-- --------------------------------------------------------------- the year's periods
INSERT INTO gl_fiscal_periods (period_label, starts_on, ends_on, status)
SELECT CONCAT(YEAR(CURDATE()), '-', LPAD(m.n, 2, '0')),
       DATE(CONCAT(YEAR(CURDATE()), '-', LPAD(m.n, 2, '0'), '-01')),
       LAST_DAY(CONCAT(YEAR(CURDATE()), '-', LPAD(m.n, 2, '0'), '-01')),
       'open'
FROM (
              SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
    UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
    UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
) m
ON DUPLICATE KEY UPDATE starts_on = VALUES(starts_on), ends_on = VALUES(ends_on);

-- ------------------------------------------- permissions added after migration 9
INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('payments', 'Payments received', 'Commercial', 125)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'payments', 1, 1, 1, 1, 1 FROM roles r WHERE r.role_key = 'super_admin'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'payments', 1, 1, 1, 1, 0 FROM roles r WHERE r.role_key = 'finance'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'payments', 1, 0, 0, 0, 0 FROM roles r WHERE r.role_key = 'management'
ON DUPLICATE KEY UPDATE can_view = 1;

-- --------------------------------------------------- how the exports are branded
INSERT INTO company_settings (setting_key, setting_value, setting_label, setting_group, input_type) VALUES
('report_brand_color', 'AD7D00', 'Report colour (hex, no #)', 'Accounting', 'text'),
('company_logo', '', 'Logo for exports (PNG or JPG under assets/img)', 'Accounting', 'text')
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label), setting_group = VALUES(setting_group), input_type = VALUES(input_type);

COMMIT;
