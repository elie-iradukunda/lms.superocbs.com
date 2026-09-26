-- Complete Kenya delivery demonstration: booking through customs release and delivery.
-- Loaded by: php scripts/migrate.php --demo
USE logistics_mvc;
SET NAMES utf8mb4;
START TRANSACTION;

INSERT INTO customers (customer_code, customer_name, customer_type, contact_name, phone, email, address, district, tin_number, payment_terms_days, credit_limit, currency, status, notes)
VALUES ('CUS-KEN-001', 'Nairobi Trade & Distribution Ltd', 'corporate', 'Mary Wanjiku', '+254 712 555 410', 'imports@nairobi-trade.example', 'Industrial Area, Nairobi', 'Nairobi', 'P051234567Z', 30, 25000000, 'KES', 'active', 'Kenya import customer used by the full customs-clearance demonstration.')
ON DUPLICATE KEY UPDATE customer_name = VALUES(customer_name), contact_name = VALUES(contact_name), phone = VALUES(phone), email = VALUES(email), currency = VALUES(currency), status = VALUES(status), notes = VALUES(notes);

INSERT INTO vehicles (plate_number, vehicle_type, make, model, manufacture_year, chassis_number, capacity_kg, capacity_m3, fuel_type, ownership, has_cooling_unit, mileage, status, next_service_date, notes)
VALUES ('RAG 642K', 'Box truck', 'Isuzu', 'FRR 90', 2024, 'JALFTR90KEN2026001', 6500, 32.500, 'diesel', 'owned', 0, 28450, 'available', '2027-02-15', 'Kenya corridor demo vehicle.')
ON DUPLICATE KEY UPDATE vehicle_type = VALUES(vehicle_type), make = VALUES(make), model = VALUES(model), capacity_kg = VALUES(capacity_kg), capacity_m3 = VALUES(capacity_m3), mileage = VALUES(mileage), status = VALUES(status), next_service_date = VALUES(next_service_date), notes = VALUES(notes);

INSERT INTO drivers (full_name, national_id, phone, address, license_number, license_class, license_expiry, emergency_contact, emergency_phone, hired_on, status, notes)
VALUES ('Jean Bosco Mugisha', '1199060045678901', '+250 788 234 611', 'Kicukiro, Kigali', 'RWA-DL-KEN-2601', 'C', '2028-07-31', 'Chantal Mugisha', '+250 788 234 612', '2024-01-15', 'available', 'Kenya corridor demo driver.')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), phone = VALUES(phone), license_class = VALUES(license_class), license_expiry = VALUES(license_expiry), status = VALUES(status), notes = VALUES(notes);

UPDATE vehicles v INNER JOIN drivers d ON d.license_number = 'RWA-DL-KEN-2601'
   SET v.assigned_driver_id = d.id WHERE v.plate_number = 'RAG 642K';

INSERT INTO vehicle_documents (document_code, vehicle_id, document_type, document_number, provider_name, issued_on, expires_on, cost, document_file, status, notes)
SELECT x.code, v.id, x.type, x.number, x.provider, x.issued, x.expires, x.cost, 'vehicle_documents/20260921-004424-2ab8a739bdc1.pdf', 'valid', 'Kenya corridor compliance document.'
FROM vehicles v JOIN (
    SELECT 'DOC-KEN-REG' code, 'registration' type, 'RAG642K-REG-2026' number, 'Rwanda Transport Development Agency' provider, '2026-01-10' issued, '2027-01-09' expires, 0 cost
    UNION ALL SELECT 'DOC-KEN-INS', 'insurance', 'SONARWA-GIT-2026-642K', 'SONARWA', '2026-01-10', '2027-01-09', 785000
    UNION ALL SELECT 'DOC-KEN-INSP', 'inspection', 'RICA-2026-642K', 'Rwanda Inspection Centre', '2026-02-15', '2027-02-14', 45000
    UNION ALL SELECT 'DOC-KEN-ROAD', 'road_license', 'RTDA-ROAD-642K', 'RTDA', '2026-01-10', '2027-01-09', 120000
) x WHERE v.plate_number = 'RAG 642K'
ON DUPLICATE KEY UPDATE document_number = VALUES(document_number), expires_on = VALUES(expires_on), document_file = VALUES(document_file), status = VALUES(status), notes = VALUES(notes);

INSERT INTO driver_documents (driver_id, document_type, document_number, issue_date, expiry_date, status, document_file)
SELECT d.id, x.type, x.number, x.issued, x.expires, 'valid', 'driver_documents/20260921-004424-2ab8a739bdc1.pdf'
FROM drivers d JOIN (
    SELECT 'driver_id' type, '1199060045678901' number, '2020-06-15' issued, '2030-06-14' expires
    UNION ALL SELECT 'driving_licence', 'RWA-DL-KEN-2601', '2023-08-01', '2028-07-31'
    UNION ALL SELECT 'passport', 'PC4820196', '2024-03-20', '2034-03-19'
) x WHERE d.license_number = 'RWA-DL-KEN-2601'
ON DUPLICATE KEY UPDATE document_number = VALUES(document_number), expiry_date = VALUES(expiry_date), status = VALUES(status), document_file = VALUES(document_file);

INSERT INTO rate_cards (rate_code, customer_id, origin, destination, vehicle_type, full_load_kg, full_load_price, rate_type, rate_amount, currency, minimum_charge, effective_from, status, notes)
SELECT 'RATE-KEN-001', c.id, 'Kigali, Rwanda', 'Nairobi, Kenya', 'Box truck', 6500, 485000, 'per_trip', 485000, 'KES', 485000, '2026-01-01', 'active', 'Kigali to Nairobi full-load rate, including corridor planning but excluding customs duty.'
FROM customers c WHERE c.customer_code = 'CUS-KEN-001'
ON DUPLICATE KEY UPDATE rate_amount = VALUES(rate_amount), full_load_price = VALUES(full_load_price), currency = VALUES(currency), status = VALUES(status), notes = VALUES(notes);

INSERT INTO transport_requests (reference_code, customer_id, requested_by_contact, pickup_location, destination, required_date, priority, cargo_description, weight_kg, packages_count, status, approved_by, approved_at, notes)
SELECT 'REQ-KEN-001', c.id, 'Mary Wanjiku', 'Kigali, Rwanda', 'Nairobi, Kenya', '2026-09-18', 'high', 'Consumer electronics: 120 sealed cartons of small appliances', 4850, 120, 'assigned', u.id, '2026-09-10 10:15:00', 'Kenya import shipment. Customs clearance pack required before dispatch.'
FROM customers c LEFT JOIN users u ON u.email = 'aline@itec.rw' WHERE c.customer_code = 'CUS-KEN-001'
ON DUPLICATE KEY UPDATE cargo_description = VALUES(cargo_description), weight_kg = VALUES(weight_kg), packages_count = VALUES(packages_count), status = VALUES(status), approved_at = VALUES(approved_at), notes = VALUES(notes);

INSERT INTO trips (reference_code, request_id, customer_id, vehicle_id, driver_id, trip_type, pickup_location, destination, planned_departure_at, planned_arrival_at, departure_at, arrival_at, cargo_summary, status, dispatched_at, completed_at, notes)
SELECT 'TRP-KEN-001', r.id, c.id, v.id, d.id, 'delivery', 'Kigali, Rwanda', 'Nairobi, Kenya', '2026-09-15 06:00:00', '2026-09-18 15:00:00', '2026-09-15 06:20:00', '2026-09-18 14:35:00', '120 cartons of consumer electronics for Nairobi Trade & Distribution Ltd', 'delivered', '2026-09-15 06:20:00', '2026-09-18 14:35:00', 'Completed Kenya corridor demo via Gatuna/Katuna and Kampala.'
FROM transport_requests r JOIN customers c ON c.customer_code = 'CUS-KEN-001' JOIN vehicles v ON v.plate_number = 'RAG 642K' JOIN drivers d ON d.license_number = 'RWA-DL-KEN-2601'
WHERE r.reference_code = 'REQ-KEN-001'
ON DUPLICATE KEY UPDATE vehicle_id = VALUES(vehicle_id), driver_id = VALUES(driver_id), status = VALUES(status), departure_at = VALUES(departure_at), arrival_at = VALUES(arrival_at), notes = VALUES(notes);

UPDATE transport_requests r INNER JOIN trips t ON t.reference_code = 'TRP-KEN-001' SET r.trip_id = t.id WHERE r.reference_code = 'REQ-KEN-001';

INSERT INTO shipments (shipment_code, request_id, trip_id, customer_id, consignee_name, consignee_phone, origin, destination, cargo_type, cargo_description, packages_count, weight_kg, volume_m3, is_hazardous, declared_value, currency, rate_card_id, quoted_amount, quote_basis, special_instructions, status, booked_at)
SELECT 'SHP-KEN-001', r.id, t.id, c.id, 'Nairobi Trade & Distribution Receiving Team', '+254 712 555 410', 'Kigali, Rwanda', 'Nairobi, Kenya', 'general', 'Consumer electronics: 120 sealed cartons of small appliances', 120, 4850, 24.800, 0, 18500000, 'KES', rc.id, 485000, 'Full-load Kenya rate card', 'Keep cartons dry. Do not stack more than two pallets high. Present the clearance pack at every border.', 'delivered', '2026-09-12 09:00:00'
FROM transport_requests r JOIN trips t ON t.reference_code = 'TRP-KEN-001' JOIN customers c ON c.customer_code = 'CUS-KEN-001' JOIN rate_cards rc ON rc.rate_code = 'RATE-KEN-001'
WHERE r.reference_code = 'REQ-KEN-001'
ON DUPLICATE KEY UPDATE trip_id = VALUES(trip_id), customer_id = VALUES(customer_id), status = VALUES(status), quoted_amount = VALUES(quoted_amount), special_instructions = VALUES(special_instructions);

INSERT INTO shipment_documents (shipment_id, document_type, document_number, issue_date, status, document_file, notes)
SELECT s.id, x.type, x.number, '2026-09-12', 'valid', 'shipment_documents/20260921-004424-2ab8a739bdc1.pdf', 'Validated for driver clearance pack.'
FROM shipments s JOIN (
    SELECT 'Commercial invoice' type, 'INV-KEN-2026-001' number UNION ALL SELECT 'Packing list', 'PL-KEN-2026-001'
    UNION ALL SELECT 'Transport document', 'CMR-KEN-2026-001' UNION ALL SELECT 'Certificate of origin', 'COO-RW-2026-001'
    UNION ALL SELECT 'Customs declaration', 'RRA-IM4-2026-001' UNION ALL SELECT 'Insurance', 'GIT-KEN-2026-001'
) x WHERE s.shipment_code = 'SHP-KEN-001'
ON DUPLICATE KEY UPDATE document_number = VALUES(document_number), status = VALUES(status), document_file = VALUES(document_file), notes = VALUES(notes);

INSERT INTO shipment_pre_dispatch_checks (shipment_id, cargo_loaded, quantity_verified, packaging_verified, documents_verified, vehicle_verified, driver_verified, route_verified, status, checked_by, checked_at, notes)
SELECT s.id, 1, 1, 1, 1, 1, 1, 1, 'ready', u.id, '2026-09-15 05:45:00', 'All cargo, documents, truck, driver, Gatuna border and Kenya route checks passed.'
FROM shipments s LEFT JOIN users u ON u.email = 'aline@itec.rw' WHERE s.shipment_code = 'SHP-KEN-001'
ON DUPLICATE KEY UPDATE cargo_loaded = 1, quantity_verified = 1, packaging_verified = 1, documents_verified = 1, vehicle_verified = 1, driver_verified = 1, route_verified = 1, status = 'ready', checked_at = VALUES(checked_at), notes = VALUES(notes);

INSERT INTO border_crossings (reference, trip_id, shipment_id, vehicle_id, driver_id, border_post_id, direction, declaration_no, transit_bond_no, seal_no, arrived_at, lodged_at, submitted_at, approved_at, cleared_at, released_at, departed_at, status, charges_total, currency, exchange_rate, base_amount, customs_country, notes)
SELECT 'CLR-KEN-001', t.id, s.id, v.id, d.id, bp.id, 'export', 'RRA-IM4-2026-001', 'T1-KEN-2026-001', 'SEAL-882014', '2026-09-15 13:40:00', '2026-09-15 14:05:00', '2026-09-15 14:05:00', '2026-09-15 15:10:00', '2026-09-15 15:35:00', '2026-09-15 15:50:00', '2026-09-15 15:55:00', 'released', 128500, 'RWF', 1, 128500, 'Rwanda', 'Gatuna/Katuna customs process completed; truck continued to Nairobi.'
FROM trips t JOIN shipments s ON s.shipment_code = 'SHP-KEN-001' JOIN vehicles v ON v.plate_number = 'RAG 642K' JOIN drivers d ON d.license_number = 'RWA-DL-KEN-2601' JOIN border_posts bp ON bp.post_name = 'Gatuna / Katuna'
WHERE t.reference_code = 'TRP-KEN-001'
ON DUPLICATE KEY UPDATE status = VALUES(status), cleared_at = VALUES(cleared_at), released_at = VALUES(released_at), departed_at = VALUES(departed_at), charges_total = VALUES(charges_total), notes = VALUES(notes);

DELETE bd FROM border_documents bd INNER JOIN border_crossings c ON c.id = bd.crossing_id WHERE c.reference = 'CLR-KEN-001';
INSERT INTO border_documents (crossing_id, document_type, document_no, issued_on, is_received, status, document_file, notes)
SELECT c.id, x.type, x.number, '2026-09-12', 1, 'valid', 'clearance_documents/20260921-004424-2ab8a739bdc1.pdf', 'Shown from the driver document pack at customs.'
FROM border_crossings c JOIN (
    SELECT 'Commercial invoice' type, 'INV-KEN-2026-001' number UNION ALL SELECT 'Packing list', 'PL-KEN-2026-001'
    UNION ALL SELECT 'Certificate of origin', 'COO-RW-2026-001' UNION ALL SELECT 'Customs declaration', 'RRA-IM4-2026-001'
    UNION ALL SELECT 'Road transit permit', 'T1-KEN-2026-001'
) x WHERE c.reference = 'CLR-KEN-001'
ON DUPLICATE KEY UPDATE document_no = VALUES(document_no), is_received = 1, status = 'valid', notes = VALUES(notes);

DELETE bc FROM border_charges bc INNER JOIN border_crossings c ON c.id = bc.crossing_id WHERE c.reference = 'CLR-KEN-001';
INSERT INTO border_charges (crossing_id, charge_type, description, receipt_no, tax_rate, taxable_amount, amount, currency, status)
SELECT c.id, x.type, x.descr, x.receipt, x.rate, x.taxable, x.amount, 'RWF', 'paid'
FROM border_crossings c JOIN (
    SELECT 'Customs duty' type, 'Export processing charge' descr, 'RRA-RCT-001' receipt, 0 rate, 0 taxable, 85000 amount
    UNION ALL SELECT 'Weighbridge', 'Gatuna axle scale' , 'GTN-WB-001', 0, 0, 18500
    UNION ALL SELECT 'Clearing agent fee', 'Declaration processing', 'AGT-001', 0, 0, 25000
) x WHERE c.reference = 'CLR-KEN-001';

DELETE cp FROM clearance_payments cp INNER JOIN border_crossings c ON c.id = cp.crossing_id WHERE c.reference = 'CLR-KEN-001';
INSERT INTO clearance_payments (crossing_id, amount, currency, payment_method, reference, payment_date, status, notes)
SELECT c.id, 128500, 'RWF', 'bank_transfer', 'PAY-KEN-001', '2026-09-15', 'paid', 'All Gatuna customs charges settled.' FROM border_crossings c WHERE c.reference = 'CLR-KEN-001'
;

INSERT INTO clearance_releases (crossing_id, release_number, release_date, released_by, remarks)
SELECT c.id, 'REL-KEN-001', '2026-09-15', u.id, 'Customs release recorded by operations after driver confirmation.'
FROM border_crossings c LEFT JOIN users u ON u.email = 'aline@itec.rw' WHERE c.reference = 'CLR-KEN-001'
ON DUPLICATE KEY UPDATE release_date = VALUES(release_date), released_by = VALUES(released_by), remarks = VALUES(remarks);

INSERT INTO deliveries (delivery_code, trip_id, shipment_id, recipient_name, recipient_phone, destination, planned_at, status, attempt_number, delivered_at, delivered_by, collected_by, collected_id_no)
SELECT 'DEL-KEN-001', t.id, s.id, 'Nairobi Trade & Distribution Receiving Team', '+254 712 555 410', 'Nairobi, Kenya', '2026-09-18 15:00:00', 'delivered', 1, '2026-09-18 14:35:00', d.id, 'Mary Wanjiku', 'KE-ID-29384401'
FROM trips t JOIN shipments s ON s.shipment_code = 'SHP-KEN-001' JOIN drivers d ON d.license_number = 'RWA-DL-KEN-2601' WHERE t.reference_code = 'TRP-KEN-001'
ON DUPLICATE KEY UPDATE status = 'delivered', delivered_at = VALUES(delivered_at), delivered_by = VALUES(delivered_by), collected_by = VALUES(collected_by), collected_id_no = VALUES(collected_id_no);

COMMIT;
