-- Email attachments, and the LPO form. A message in the outbox can carry files, such as the LPO
-- form sent to a customer after they accept a price offer. Each attachment is
-- recorded as {"filename": ..., "path": ...} with the path under the uploads
-- folder, and read and attached when the message is delivered or retried.
-- Safe to run more than once.

ALTER TABLE email_outbox
    ADD COLUMN IF NOT EXISTS attachments TEXT NULL AFTER body_text;

-- When the LPO form went to the customer, shown on the price offer.
ALTER TABLE price_offers
    ADD COLUMN IF NOT EXISTS lpo_sent_at DATETIME NULL AFTER accepted_at;

-- The company seal (stamp), uploaded on Company settings and printed on issued
-- invoices, delivery notes, goods received notes and gate passes.
INSERT IGNORE INTO company_settings (setting_key, setting_value, setting_label, setting_group, input_type) VALUES
    ('company_seal', '', 'Company seal (stamp)', 'Company', 'image');

-- The logo is uploaded like the seal, and the report colour is picked from
-- swatches or a colour picker; both sit with the company details.
UPDATE company_settings SET input_type = 'image', setting_label = 'Company logo', setting_group = 'Company' WHERE setting_key = 'company_logo';
UPDATE company_settings SET input_type = 'color', setting_label = 'Company colour (documents and reports)', setting_group = 'Company' WHERE setting_key = 'report_brand_color';

-- Each user's own signature (drawn or uploaded on My profile), printed on the
-- documents where the system recorded that person's action: the trip they
-- dispatched, the invoice they issued, the gate pass they authorised.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS signature_file VARCHAR(255) NULL AFTER avatar_path;

-- Who pressed Dispatch, recorded on the trip itself, so the trip report sent
-- at that moment can name and show the signature of the dispatcher.
ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS dispatched_by INT UNSIGNED NULL AFTER dispatched_at;
