-- vendor_name (20260921_000017_vendor_branding.sql) made the footer credit
-- editable per installation. It is fixed in code instead now (see
-- bootstrap.php::vendor_name()), so the row and its "Branding" group are
-- dead weight — nothing reads them any more.
DELETE FROM company_settings WHERE setting_key = 'vendor_name';
