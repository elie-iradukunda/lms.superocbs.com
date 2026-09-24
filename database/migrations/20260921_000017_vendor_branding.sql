-- Who built the system, as opposed to who is using it.
--
-- The page footer read "Rwanda Cargo Link Ltd · Logistics Management System",
-- which told the company its own name and said nothing about where the software
-- came from. LMS is installed for more than one company, so the footer credits
-- the vendor instead. It is a setting rather than a constant, so whoever resells
-- or hosts it can put their own name there without editing a view.

INSERT INTO company_settings (setting_key, setting_value, setting_label, setting_group, input_type) VALUES
('vendor_name', 'ITEC Ltd', 'Credited in the page footer as the maker of the system', 'Branding', 'text')
ON DUPLICATE KEY UPDATE setting_label = VALUES(setting_label), setting_group = VALUES(setting_group), input_type = VALUES(input_type);

-- The email signature said the same thing the footer used to.
UPDATE company_settings
   SET setting_value = 'This message was sent by the LMS logistics system on behalf of the company named above. Please do not reply to it directly.'
 WHERE setting_key = 'email_signature'
   AND setting_value = 'This message was sent by the LMS logistics system. Please do not reply to it directly.';
