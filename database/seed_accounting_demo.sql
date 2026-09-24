-- The figures the books opened with, for the worked example.
--
-- A real company types its own opening balances into the Journal, so this entry
-- is loaded only by `php scripts/migrate.php --demo`. The chart of accounts it
-- posts into is seeded either way, and can be added to, renamed and retired from
-- the Chart of accounts page.

USE logistics_mvc;

SET NAMES utf8mb4;

START TRANSACTION;

-- ------------------------------------------------------------ opening balances
INSERT INTO gl_journal_entries (entry_no, entry_date, memo, reference, source_type, source_id, source_code, status, posted_by)
SELECT 'JRN-OPENING-001', CONCAT(YEAR(CURDATE()), '-01-01'), 'Opening balances brought forward', 'OB',
       'opening', 1, 'OPENING', 'posted', u.id
FROM users u WHERE u.email = 'emmanuel@itec.rw'
ON DUPLICATE KEY UPDATE memo = VALUES(memo), entry_date = VALUES(entry_date), status = 'posted';

DELETE l FROM gl_journal_lines l
  INNER JOIN gl_journal_entries e ON e.id = l.entry_id
 WHERE e.entry_no = 'JRN-OPENING-001';

INSERT INTO gl_journal_lines (entry_id, line_no, account_id, description, debit, credit)
SELECT e.id, v.n, a.id, v.descr, v.dr, v.cr
FROM gl_journal_entries e
JOIN (
              SELECT 1 n, '1010' code, 'Cash float at the Kigali office' descr,    500000.00 dr,        0.00 cr
    UNION ALL SELECT 2, '1020', 'Operating current account',                     12000000.00,         0.00
    UNION ALL SELECT 3, '1100', 'Invoices outstanding at the year start',         2000000.00,         0.00
    UNION ALL SELECT 4, '1200', 'Stock on hand at the year start',                5000000.00,         0.00
    UNION ALL SELECT 5, '1500', 'Fleet at cost',                                 85000000.00,         0.00
    UNION ALL SELECT 6, '1590', 'Depreciation charged to date',                          0.00, 12000000.00
    UNION ALL SELECT 7, '2000', 'Suppliers outstanding at the year start',               0.00,  3500000.00
    UNION ALL SELECT 8, '2500', 'Asset finance outstanding',                             0.00, 24000000.00
    UNION ALL SELECT 9, '3000', 'Capital introduced',                                    0.00, 50000000.00
    UNION ALL SELECT 10, '3100', 'Profit retained from earlier years',                   0.00, 15000000.00
) v
JOIN gl_accounts a ON a.account_code = v.code
WHERE e.entry_no = 'JRN-OPENING-001';

COMMIT;
