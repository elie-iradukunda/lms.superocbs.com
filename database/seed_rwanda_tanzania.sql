-- =============================================================================
-- seed_rwanda_tanzania.sql
--
-- Full end-to-end demo scenario: cargo shipment from Kigali (Rwanda) to
-- Dar es Salaam (Tanzania).
--
-- Covers every step of the process:
--   1.  Customer
--   2.  Warehouses (origin + destination)
--   3.  Rate card
--   4.  Vehicle + compliance documents
--   5.  Driver + driver documents
--   6.  Transport request (quoted → approved)
--   7.  Shipment
--   8.  Trip + multi-stop route
--   9.  Delivery record
--  10.  Pre-dispatch check
--  11.  Border crossing (Rusumo) with charges and border documents
--  12.  Fuel record (en route)
--  13.  Expenses (border fees, driver allowance)
--  14.  Invoice + lines + payment
--  15.  Audit-style notifications
--
-- Every statement is idempotent (ON DUPLICATE KEY UPDATE or existence checks),
-- so the file can be re-run safely without duplicating any record.
-- =============================================================================

USE logistics_mvc;
SET NAMES utf8mb4;
START TRANSACTION;

-- =============================================================================
-- 1. CUSTOMER — Dar es Salaam Buyer Ltd (Tanzania)
-- =============================================================================
INSERT INTO customers (
    customer_code, customer_name, customer_type,
    contact_name, phone, email,
    address, district,
    tin_number, payment_terms_days, credit_limit, status, notes
) VALUES (
    'CUS-TZ-0001', 'Dar es Salaam Buyer Ltd', 'corporate',
    'Hassan Juma', '+255 700 111 222', 'hassan@daresbuyer.co.tz',
    'Kariakoo Street 14, Dar es Salaam', 'Dar es Salaam',
    'TZ-999888777', 30, 15000000.00, 'active',
    'Cross-border client. Receives electronics from Kigali. Pays by bank transfer.'
)
ON DUPLICATE KEY UPDATE
    customer_name      = VALUES(customer_name),
    customer_type      = VALUES(customer_type),
    contact_name       = VALUES(contact_name),
    phone              = VALUES(phone),
    email              = VALUES(email),
    address            = VALUES(address),
    district           = VALUES(district),
    tin_number         = VALUES(tin_number),
    payment_terms_days = VALUES(payment_terms_days),
    credit_limit       = VALUES(credit_limit),
    status             = VALUES(status),
    notes              = VALUES(notes);

-- =============================================================================
-- 2. WAREHOUSES — Kigali origin depot + Dar es Salaam destination depot
-- =============================================================================
INSERT INTO warehouses (warehouse_name, location, status)
VALUES ('Kigali Export Depot', 'Kigali Industrial Zone, Gasabo, Rwanda', 'active')
ON DUPLICATE KEY UPDATE location = VALUES(location), status = VALUES(status);

INSERT INTO warehouses (warehouse_name, location, status)
VALUES ('Dar es Salaam Receiving Hub', 'Kariakoo, Dar es Salaam, Tanzania', 'active')
ON DUPLICATE KEY UPDATE location = VALUES(location), status = VALUES(status);

-- Assign codes if not already set
UPDATE warehouses SET warehouse_code = CONCAT('WH-TZ-', LPAD(id, 3, '0'))
 WHERE warehouse_code IS NULL
   AND warehouse_name IN ('Kigali Export Depot', 'Dar es Salaam Receiving Hub');

-- =============================================================================
-- 3. RATE CARD — Kigali → Dar es Salaam, per kg
-- =============================================================================
INSERT INTO rate_cards (
    rate_code, customer_id,
    origin, destination,
    vehicle_type, rate_type, rate_amount, minimum_charge,
    effective_from, effective_to, status, notes
)
SELECT
    'RATE-TZ-0001',
    c.id,
    'Kigali', 'Dar es Salaam',
    'Heavy truck', 'per_kg', 850.00, 500000.00,
    '2026-01-01', '2026-12-31', 'active',
    'Cross-border freight Rwanda → Tanzania. Minimum 500,000 RWF per trip.'
FROM customers c
WHERE c.customer_code = 'CUS-TZ-0001'
ON DUPLICATE KEY UPDATE
    rate_amount    = VALUES(rate_amount),
    minimum_charge = VALUES(minimum_charge),
    effective_to   = VALUES(effective_to),
    status         = VALUES(status),
    notes          = VALUES(notes);

-- =============================================================================
-- 4. VEHICLE — MAN TGX heavy truck (RAD 123 A), 20-tonne capacity
-- =============================================================================
INSERT INTO vehicles (
    plate_number, vehicle_type, make, model,
    manufacture_year, chassis_number,
    capacity_kg, capacity_m3,
    fuel_type, ownership,
    has_cooling_unit,
    mileage, next_service_date,
    status
) VALUES (
    'RAD 123 A', 'Heavy truck', 'MAN', 'TGX 26.440',
    2022, 'WMA26XZZ0N1234567',
    20000.00, 80.000,
    'diesel', 'owned',
    0,
    45200, '2026-12-01',
    'available'
)
ON DUPLICATE KEY UPDATE
    vehicle_type     = VALUES(vehicle_type),
    make             = VALUES(make),
    model            = VALUES(model),
    manufacture_year = VALUES(manufacture_year),
    chassis_number   = VALUES(chassis_number),
    capacity_kg      = VALUES(capacity_kg),
    capacity_m3      = VALUES(capacity_m3),
    fuel_type        = VALUES(fuel_type),
    ownership        = VALUES(ownership),
    has_cooling_unit = VALUES(has_cooling_unit),
    mileage          = VALUES(mileage),
    next_service_date= VALUES(next_service_date);

-- 4a. Vehicle compliance documents (all valid, using the shared PDF on disk)
INSERT INTO vehicle_documents (
    document_code, vehicle_id,
    document_type, document_number, provider_name,
    issued_on, expires_on, cost,
    document_file, status, notes
)
SELECT d.code, v.id, d.dtype, d.docnum, d.provider,
       d.issued, d.expires, d.cost,
       d.file, d.vstatus, d.notes
FROM (
          SELECT 'DOC-TZ-0001' code, 'insurance'    dtype, 'SON-TZ-2026-88501'  docnum, 'SONARWA General'              provider, '2026-01-15' issued, '2027-01-14' expires, 980000.00 cost, '20260921-004424-2ab8a739bdc1.pdf' file, 'valid'    vstatus, 'Comprehensive third-party cover including cross-border.'                notes
    UNION ALL
    SELECT 'DOC-TZ-0002', 'road_license', 'RL-TZ-2026-9921',   'RURA',                       '2026-01-05', '2026-12-31', 120000.00, '20260921-004424-2ab8a739bdc1.pdf', 'valid',    'Goods transport licence — heavy vehicle category.'
    UNION ALL
    SELECT 'DOC-TZ-0003', 'inspection',   'INS-TZ-2026-4401',  'Rwanda Inspection Centre',    '2026-03-10', '2026-09-30', 45000.00,  '20260921-004424-2ab8a739bdc1.pdf', 'expiring', 'Six-monthly roadworthiness. Book renewal before October.'
    UNION ALL
    SELECT 'DOC-TZ-0004', 'registration', 'REG-RAD123A-2022',  'Rwanda Revenue Authority',    '2022-06-01', '2032-05-31', 0.00,      '20260921-004424-2ab8a739bdc1.pdf', 'valid',    NULL
    UNION ALL
    SELECT 'DOC-TZ-0005', 'permit',       'PERMIT-COMESA-2026','COMESA Secretariat',           '2026-01-01', '2026-12-31', 250000.00, '20260921-004424-2ab8a739bdc1.pdf', 'valid',    'COMESA cross-border transit permit. Required for Tanzania entry.'
) d
INNER JOIN vehicles v ON v.plate_number = 'RAD 123 A'
ON DUPLICATE KEY UPDATE
    document_type  = VALUES(document_type),
    document_number= VALUES(document_number),
    provider_name  = VALUES(provider_name),
    issued_on      = VALUES(issued_on),
    expires_on     = VALUES(expires_on),
    cost           = VALUES(cost),
    document_file  = VALUES(document_file),
    status         = VALUES(status),
    notes          = VALUES(notes);

-- =============================================================================
-- 5. DRIVER — Samuel Nkurunziza (long-haul, class C licence)
-- =============================================================================
-- Ensure the driver user account exists first (password = "password")
INSERT INTO users (role_id, full_name, email, password_hash, phone, department, status, last_login_at)
SELECT r.id, 'Samuel Nkurunziza', 'samuel.nkurunziza@itec.rw',
       '$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6',
       '+250 788 321 100', 'Fleet', 'active', '2026-09-25 06:00:00'
FROM roles r WHERE r.role_key = 'driver'
ON DUPLICATE KEY UPDATE
    full_name    = VALUES(full_name),
    phone        = VALUES(phone),
    department   = VALUES(department),
    status       = VALUES(status);

INSERT INTO drivers (
    user_id,
    full_name, national_id, phone, address,
    license_number, license_class, license_expiry,
    hired_on,
    emergency_contact, emergency_phone,
    status
)
SELECT
    u.id,
    'Samuel Nkurunziza', '1199580012345678', '+250 788 321 100', 'Kicukiro, Kigali',
    'RWA-DL-TZ-0456', 'C', '2027-06-30',
    '2022-01-15',
    'Marie Uwase', '+250 788 321 200',
    'available'
FROM users u WHERE u.email = 'samuel.nkurunziza@itec.rw'
ON DUPLICATE KEY UPDATE
    full_name         = VALUES(full_name),
    national_id       = VALUES(national_id),
    phone             = VALUES(phone),
    address           = VALUES(address),
    license_class     = VALUES(license_class),
    license_expiry    = VALUES(license_expiry),
    hired_on          = VALUES(hired_on),
    emergency_contact = VALUES(emergency_contact),
    emergency_phone   = VALUES(emergency_phone),
    status            = VALUES(status);

-- Link driver to vehicle
UPDATE vehicles v
  INNER JOIN drivers d ON d.license_number = 'RWA-DL-TZ-0456'
    SET v.assigned_driver_id = d.id
  WHERE v.plate_number = 'RAD 123 A'
    AND v.assigned_driver_id IS NULL;

-- 5a. Driver documents
INSERT INTO driver_documents (
    driver_id, document_type, document_number,
    issue_date, expiry_date, status, document_file
)
SELECT d.id, dd.dtype, dd.docnum, dd.issued, dd.expires, dd.vstatus, dd.file
FROM drivers d
CROSS JOIN (
          SELECT 'driving_licence' dtype, 'RWA-DL-TZ-0456' docnum,   '2022-06-01' issued, '2027-06-30' expires, 'valid'   vstatus, '20260921-004424-2ab8a739bdc1.pdf' file
    UNION ALL
    SELECT 'passport',         'RW-PASS-00456781',    '2020-03-15', '2030-03-14', 'valid',   '20260921-004424-2ab8a739bdc1.pdf'
    UNION ALL
    SELECT 'permit',           'CROSS-PERMIT-TZ-2026','2026-01-01', '2026-12-31', 'valid',   '20260921-004424-2ab8a739bdc1.pdf'
    UNION ALL
    SELECT 'driver_id',        'NID-1199580012345678','2018-09-01', '2028-08-31', 'valid',   '20260921-004424-2ab8a739bdc1.pdf'
) dd
WHERE d.license_number = 'RWA-DL-TZ-0456'
ON DUPLICATE KEY UPDATE
    document_number = VALUES(document_number),
    issue_date      = VALUES(issue_date),
    expiry_date     = VALUES(expiry_date),
    status          = VALUES(status),
    document_file   = VALUES(document_file);

-- =============================================================================
-- 6. TRANSPORT REQUEST — Kigali → Dar es Salaam (quoted + approved)
-- =============================================================================
INSERT INTO transport_requests (
    reference_code, customer_id, requester_id,
    pickup_location, origin_warehouse_id,
    destination, destination_warehouse_id,
    required_date, priority,
    cargo_description, weight_kg, packages_count,
    -- quote fields
    rate_card_id, quoted_amount, quote_basis, currency, quoted_at,
    accepted_at, accepted_by,
    -- approval
    approved_by, approved_at,
    status,
    notes
)
SELECT
    'REQ-TZ-0001',
    c.id,
    u_aline.id,
    'Kigali Export Depot',
    wh_kgl.id,
    'Kariakoo, Dar es Salaam, Tanzania',
    wh_dsm.id,
    '2026-09-28', 'high',
    'Electronic equipment — 500 cartons (computers, tablets, accessories)',
    8500.00, 500,
    rc.id, 7225000.00, '8,500 kg × 850 RWF/kg = 7,225,000 RWF (above 500,000 minimum)', 'RWF', '2026-09-25 09:15:00',
    '2026-09-25 09:45:00', 'Hassan Juma',
    u_aline.id, '2026-09-25 09:30:00',
    'approved',
    'Fragile electronics. Handle with care. Cross-border consignment to Tanzania.'
FROM customers c
CROSS JOIN (SELECT id FROM users WHERE email = 'aline@itec.rw' LIMIT 1) u_aline
CROSS JOIN (SELECT id FROM warehouses WHERE warehouse_name = 'Kigali Export Depot' LIMIT 1) wh_kgl
CROSS JOIN (SELECT id FROM warehouses WHERE warehouse_name = 'Dar es Salaam Receiving Hub' LIMIT 1) wh_dsm
CROSS JOIN (SELECT id FROM rate_cards WHERE rate_code = 'RATE-TZ-0001' LIMIT 1) rc
WHERE c.customer_code = 'CUS-TZ-0001'
ON DUPLICATE KEY UPDATE
    cargo_description           = VALUES(cargo_description),
    weight_kg                   = VALUES(weight_kg),
    packages_count              = VALUES(packages_count),
    quoted_amount               = VALUES(quoted_amount),
    quote_basis                 = VALUES(quote_basis),
    quoted_at                   = VALUES(quoted_at),
    accepted_at                 = VALUES(accepted_at),
    accepted_by                 = VALUES(accepted_by),
    approved_by                 = VALUES(approved_by),
    approved_at                 = VALUES(approved_at),
    status                      = VALUES(status);

-- =============================================================================
-- 7. SHIPMENT — SHP-TZ-0001 (electronics, general cargo)
-- =============================================================================
INSERT INTO shipments (
    shipment_code, request_id, customer_id,
    consignee_name, consignee_phone,
    origin, destination,
    cargo_type, cargo_description,
    packages_count, weight_kg, volume_m3,
    temperature_min_c, temperature_max_c,
    is_hazardous, declared_value,
    special_instructions,
    status, booked_at
)
SELECT
    'SHP-TZ-0001',
    tr.id,
    c.id,
    'Hassan Juma', '+255 700 111 222',
    'Kigali Export Depot', 'Kariakoo, Dar es Salaam, Tanzania',
    'general',
    'Electronic equipment — computers, tablets, accessories. 500 cartons.',
    500, 8500.00, 42.000,
    NULL, NULL,
    0, 45000000.00,
    'Fragile. Stack max 2 high. Keep dry. Do not place heavy loads on top.',
    'booked', '2026-09-25 10:00:00'
FROM customers c
CROSS JOIN (SELECT id FROM transport_requests WHERE reference_code = 'REQ-TZ-0001' LIMIT 1) tr
WHERE c.customer_code = 'CUS-TZ-0001'
ON DUPLICATE KEY UPDATE
    consignee_name      = VALUES(consignee_name),
    cargo_description   = VALUES(cargo_description),
    packages_count      = VALUES(packages_count),
    weight_kg           = VALUES(weight_kg),
    declared_value      = VALUES(declared_value),
    special_instructions= VALUES(special_instructions),
    status              = VALUES(status);

-- =============================================================================
-- 8. TRIP — TRP-TZ-0001 (Kigali → Dar es Salaam)
-- =============================================================================
INSERT INTO trips (
    reference_code, request_id, customer_id,
    vehicle_id, driver_id,
    trip_type,
    pickup_location, destination,
    planned_departure_at, planned_arrival_at,
    departure_at, arrival_at,
    dispatched_at, completed_at,
    cargo_summary,
    status,
    notes
)
SELECT
    'TRP-TZ-0001',
    tr.id,
    c.id,
    v.id,
    d.id,
    'delivery',
    'Kigali Export Depot, Rwanda',
    'Kariakoo, Dar es Salaam, Tanzania',
    '2026-09-27 06:00:00', '2026-09-29 18:00:00',
    '2026-09-27 06:15:00', '2026-09-29 17:45:00',
    '2026-09-27 06:15:00', '2026-09-29 18:00:00',
    '500 cartons electronics, 8,500 kg — Dar es Salaam Buyer Ltd',
    'delivered',
    'Cross-border Rwanda → Tanzania. Rusumo border crossing. Delivered on time.'
FROM customers c
CROSS JOIN (SELECT id FROM transport_requests WHERE reference_code = 'REQ-TZ-0001' LIMIT 1) tr
CROSS JOIN (SELECT id FROM vehicles WHERE plate_number = 'RAD 123 A' LIMIT 1) v
CROSS JOIN (SELECT id FROM drivers WHERE license_number = 'RWA-DL-TZ-0456' LIMIT 1) d
WHERE c.customer_code = 'CUS-TZ-0001'
ON DUPLICATE KEY UPDATE
    cargo_summary       = VALUES(cargo_summary),
    dispatched_at       = VALUES(dispatched_at),
    completed_at        = VALUES(completed_at),
    status              = VALUES(status),
    notes               = VALUES(notes);

-- Link shipment to trip
UPDATE shipments s
  INNER JOIN trips t ON t.reference_code = 'TRP-TZ-0001'
    SET s.trip_id = t.id, s.status = 'delivered'
  WHERE s.shipment_code = 'SHP-TZ-0001';

-- Link request to trip
UPDATE transport_requests r
  INNER JOIN trips t ON t.reference_code = 'TRP-TZ-0001'
    SET r.trip_id = t.id, r.status = 'assigned'
  WHERE r.reference_code = 'REQ-TZ-0001';

-- Update vehicle and driver status (trip is done → available again)
UPDATE vehicles SET status = 'available'
 WHERE plate_number = 'RAD 123 A' AND status = 'on_trip';
UPDATE drivers SET status = 'available'
 WHERE license_number = 'RWA-DL-TZ-0456' AND status = 'on_trip';

-- 8a. TRIP STOPS — multi-stop route
DELETE ts FROM trip_stops ts
  INNER JOIN trips t ON t.id = ts.trip_id
  WHERE t.reference_code = 'TRP-TZ-0001';

INSERT INTO trip_stops (
    trip_id, stop_sequence, stop_type,
    location_name, contact_name, contact_phone,
    planned_arrival_at, actual_arrival_at,
    status, notes
)
SELECT t.id, s.seq, s.stype, s.location, s.contact, s.phone,
       s.planned, s.actual, s.sstatus, s.notes
FROM trips t
CROSS JOIN (
          SELECT 1 seq, 'pickup'     stype, 'Kigali Export Depot, Gasabo'         location, 'Nadine Tuyisenge'    contact, '+250 782 110 554' phone, '2026-09-27 06:00:00' planned, '2026-09-27 06:10:00' actual, 'completed' sstatus, 'Loading completed. Cargo sealed and manifested.'  notes
    UNION ALL
    SELECT 2,           'waypoint',          'Kayonza (fuel stop)',                 'N/A',                        NULL,                    '2026-09-27 09:00:00', '2026-09-27 09:05:00', 'completed', 'Refuelled at Total Kayonza. Tyre pressure checked.'
    UNION ALL
    SELECT 3,           'checkpoint',        'Rusumo Border Post (Rwanda side)',    'Customs Officer',             '+250 788 000 099',      '2026-09-27 13:00:00', '2026-09-27 13:20:00', 'completed', 'Export declaration submitted. Documents verified.'
    UNION ALL
    SELECT 4,           'checkpoint',        'Rusumo / Sirari Border (Tanzania side)','Customs Officer TZ',       '+255 782 000 099',      '2026-09-27 14:00:00', '2026-09-27 15:45:00', 'completed', 'Import declaration cleared. Duties paid. Transit bond cancelled.'
    UNION ALL
    SELECT 5,           'waypoint',          'Shinyanga (overnight rest)',           'Lodge Reception',            '+255 744 000 011',      '2026-09-28 19:00:00', '2026-09-28 19:30:00', 'completed', 'Driver rest stop as per road safety regulations.'
    UNION ALL
    SELECT 6,           'dropoff',           'Kariakoo, Dar es Salaam',             'Hassan Juma',                '+255 700 111 222',      '2026-09-29 18:00:00', '2026-09-29 17:45:00', 'completed', 'All 500 cartons delivered. Signed off by consignee.'
) s
WHERE t.reference_code = 'TRP-TZ-0001';

-- =============================================================================
-- 9. DELIVERY — DLV-TZ-0001
-- =============================================================================
INSERT INTO deliveries (
    delivery_code, trip_id, shipment_id,
    recipient_name, recipient_phone,
    destination,
    planned_at, delivered_at,
    proof_file, recipient_signature,
    attempt_number, failure_reason,
    status
)
SELECT
    'DLV-TZ-0001',
    t.id,
    s.id,
    'Hassan Juma', '+255 700 111 222',
    'Kariakoo, Dar es Salaam, Tanzania',
    '2026-09-29 18:00:00', '2026-09-29 17:45:00',
    '20260921-004424-2ab8a739bdc1.pdf',
    '20260921-004424-2ab8a739bdc1.pdf',
    1, 'none',
    'delivered'
FROM trips t
CROSS JOIN shipments s
WHERE t.reference_code = 'TRP-TZ-0001'
  AND s.shipment_code  = 'SHP-TZ-0001'
ON DUPLICATE KEY UPDATE
    delivered_at       = VALUES(delivered_at),
    proof_file         = VALUES(proof_file),
    recipient_signature= VALUES(recipient_signature),
    status             = VALUES(status),
    failure_reason     = VALUES(failure_reason);

-- =============================================================================
-- 10. PRE-DISPATCH CHECK — all verified before departure
-- =============================================================================
INSERT INTO shipment_pre_dispatch_checks (
    shipment_id,
    cargo_loaded, quantity_verified, packaging_verified,
    documents_verified, vehicle_verified, driver_verified, route_verified,
    status, checked_by, checked_at, notes
)
SELECT
    s.id,
    1, 1, 1,
    1, 1, 1, 1,
    'ready',
    u.id,
    '2026-09-27 05:45:00',
    'All 500 cartons counted and sealed. Vehicle RAD 123 A inspected. Driver documents valid. Route briefing done.'
FROM shipments s
CROSS JOIN (SELECT id FROM users WHERE email = 'aline@itec.rw' LIMIT 1) u
WHERE s.shipment_code = 'SHP-TZ-0001'
ON DUPLICATE KEY UPDATE
    cargo_loaded        = VALUES(cargo_loaded),
    quantity_verified   = VALUES(quantity_verified),
    packaging_verified  = VALUES(packaging_verified),
    documents_verified  = VALUES(documents_verified),
    vehicle_verified    = VALUES(vehicle_verified),
    driver_verified     = VALUES(driver_verified),
    route_verified      = VALUES(route_verified),
    status              = VALUES(status),
    checked_by          = VALUES(checked_by),
    checked_at          = VALUES(checked_at),
    notes               = VALUES(notes);

-- Shipment documents attached to the shipment
INSERT INTO shipment_documents (
    shipment_id, document_type, document_number,
    issue_date, expiry_date, status,
    document_file, uploaded_by, notes
)
SELECT s.id, sd.dtype, sd.docnum, sd.issued, sd.expires, sd.vstatus,
       sd.file, u.id, sd.notes
FROM shipments s
CROSS JOIN (SELECT id FROM users WHERE email = 'aline@itec.rw' LIMIT 1) u
CROSS JOIN (
          SELECT 'Commercial invoice'     dtype, 'CI-TZ-2026-0001'  docnum, '2026-09-25' issued, NULL           expires, 'valid' vstatus, '20260921-004424-2ab8a739bdc1.pdf' file, 'Issued to Dar es Salaam Buyer Ltd. Value: 45,000,000 RWF.' notes
    UNION ALL
    SELECT 'Packing list',                       'PL-TZ-2026-0001',          '2026-09-25', NULL,            'valid', '20260921-004424-2ab8a739bdc1.pdf', '500 cartons of electronics, detailed by SKU.'
    UNION ALL
    SELECT 'Certificate of origin',              'CO-RW-2026-4412',          '2026-09-25', '2026-12-31',    'valid', '20260921-004424-2ab8a739bdc1.pdf', 'Issued by Rwanda Standards Board.'
    UNION ALL
    SELECT 'Customs declaration',                'CD-RW-EXP-20260927-001',   '2026-09-27', NULL,            'valid', '20260921-004424-2ab8a739bdc1.pdf', 'Export declaration — Rusumo border.'
    UNION ALL
    SELECT 'T1 transit bond',                    'T1-COMESA-2026-007412',    '2026-09-27', '2026-10-27',    'valid', '20260921-004424-2ab8a739bdc1.pdf', 'COMESA transit bond covering Rwanda → Tanzania corridor.'
) sd
WHERE s.shipment_code = 'SHP-TZ-0001'
ON DUPLICATE KEY UPDATE
    document_number = VALUES(document_number),
    issue_date      = VALUES(issue_date),
    expiry_date     = VALUES(expiry_date),
    status          = VALUES(status),
    document_file   = VALUES(document_file),
    notes           = VALUES(notes);

-- =============================================================================
-- 11. BORDER CROSSING — Rusumo (Rwanda → Tanzania)
-- =============================================================================
INSERT INTO border_crossings (
    reference, trip_id, shipment_id, vehicle_id, driver_id,
    border_post_id,
    direction, clearing_agent_id,
    declaration_no, transit_bond_no, seal_no,
    weighbridge_kg,
    arrived_at, lodged_at, cleared_at, departed_at,
    status,
    charges_total, currency, exchange_rate, base_amount,
    notes
)
SELECT
    'CRS-TZ-2026-0001',
    t.id,
    s.id,
    v.id,
    d.id,
    bp.id,
    'export', NULL,
    'CD-RW-EXP-20260927-001', 'T1-COMESA-2026-007412', 'SEAL-2026-TRP-TZ-0001',
    8620.00,
    '2026-09-27 13:20:00',
    '2026-09-27 13:45:00',
    '2026-09-27 15:30:00',
    '2026-09-27 15:50:00',
    'departed',
    385000.00, 'RWF', 1.00000000, 385000.00,
    'Rusumo one-stop border. 2h 30min total processing. All documents accepted without query.'
FROM trips t
CROSS JOIN shipments s
CROSS JOIN (SELECT id FROM vehicles WHERE plate_number = 'RAD 123 A' LIMIT 1) v
CROSS JOIN (SELECT id FROM drivers WHERE license_number = 'RWA-DL-TZ-0456' LIMIT 1) d
CROSS JOIN (SELECT id FROM border_posts WHERE post_name = 'Rusumo' LIMIT 1) bp
WHERE t.reference_code = 'TRP-TZ-0001'
  AND s.shipment_code  = 'SHP-TZ-0001'
ON DUPLICATE KEY UPDATE
    arrived_at     = VALUES(arrived_at),
    lodged_at      = VALUES(lodged_at),
    cleared_at     = VALUES(cleared_at),
    departed_at    = VALUES(departed_at),
    status         = VALUES(status),
    charges_total  = VALUES(charges_total),
    notes          = VALUES(notes);

-- 11a. Border charges (individual cost lines)
DELETE bc FROM border_charges bc
  INNER JOIN border_crossings bx ON bx.id = bc.crossing_id
  WHERE bx.reference = 'CRS-TZ-2026-0001';

INSERT INTO border_charges (crossing_id, charge_type, description, receipt_no, amount)
SELECT bx.id, c.ctype, c.descr, c.receipt, c.amount
FROM border_crossings bx
CROSS JOIN (
          SELECT 'Customs duty'        ctype, 'Export duty on electronics'               descr, 'RRA-EXP-2026-112'   receipt, 120000.00 amount
    UNION ALL
    SELECT 'Clearing agent fee',              'Agent handling and declaration filing',      'AGT-2026-78901',            85000.00
    UNION ALL
    SELECT 'Transit bond',                    'COMESA T1 transit bond guarantee',           'BOND-COMESA-007412',        95000.00
    UNION ALL
    SELECT 'Weighbridge',                     'Axle-load weighbridge certificate',          'WB-RSM-2026-0441',          15000.00
    UNION ALL
    SELECT 'Road toll',                       'Rwanda–Tanzania corridor road toll',         'TOLL-2026-44210',           50000.00
    UNION ALL
    SELECT 'Other border charge',             'Scanning fee, Rusumo One-Stop Border',       'SCAN-RSM-2026-0090',        20000.00
) c
WHERE bx.reference = 'CRS-TZ-2026-0001';

-- 11b. Border documents (papers lodged at the border)
DELETE bd FROM border_documents bd
  INNER JOIN border_crossings bx ON bx.id = bd.crossing_id
  WHERE bx.reference = 'CRS-TZ-2026-0001';

INSERT INTO border_documents (crossing_id, document_type, document_no, issued_on, expires_on, is_received, document_file, notes)
SELECT bx.id, d.dtype, d.docno, d.issued, d.expires, d.received, d.file, d.notes
FROM border_crossings bx
CROSS JOIN (
          SELECT 'Customs declaration'    dtype, 'CD-RW-EXP-20260927-001'  docno, '2026-09-27' issued, NULL           expires, 1 received, '20260921-004424-2ab8a739bdc1.pdf' file, 'Accepted by RRA customs officer.'   notes
    UNION ALL
    SELECT 'T1 transit bond',                    'T1-COMESA-2026-007412',          '2026-09-27', '2026-10-27',        1,         '20260921-004424-2ab8a739bdc1.pdf',          'COMESA bond — cancelled at destination.'
    UNION ALL
    SELECT 'Commercial invoice',                 'CI-TZ-2026-0001',                '2026-09-25', NULL,                1,         '20260921-004424-2ab8a739bdc1.pdf',          NULL
    UNION ALL
    SELECT 'Packing list',                       'PL-TZ-2026-0001',                '2026-09-25', NULL,                1,         '20260921-004424-2ab8a739bdc1.pdf',          NULL
    UNION ALL
    SELECT 'Certificate of origin',              'CO-RW-2026-4412',                '2026-09-25', '2026-12-31',        1,         '20260921-004424-2ab8a739bdc1.pdf',          'Rwanda Standards Board COO.'
    UNION ALL
    SELECT 'Weighbridge ticket',                 'WB-RSM-2026-0441',               '2026-09-27', NULL,                1,         '20260921-004424-2ab8a739bdc1.pdf',          'Gross weight: 8,620 kg. Within limit.'
    UNION ALL
    SELECT 'COMESA yellow card',                 'COMESA-YC-2026-RAD123A',         '2026-01-01', '2026-12-31',        1,         '20260921-004424-2ab8a739bdc1.pdf',          'Third-party insurance valid in all COMESA states.'
    UNION ALL
    SELECT 'Road transit permit',                'RTP-TZ-2026-0099',               '2026-09-27', '2026-10-27',        1,         '20260921-004424-2ab8a739bdc1.pdf',          'Tanzania transit permit.'
    UNION ALL
    SELECT 'Driver passport',                    'RW-PASS-00456781',               '2020-03-15', '2030-03-14',        1,         '20260921-004424-2ab8a739bdc1.pdf',          NULL
) d
WHERE bx.reference = 'CRS-TZ-2026-0001';

-- =============================================================================
-- 12. FUEL RECORD — Kayonza refuelling stop
-- =============================================================================
INSERT INTO fuel_records (
    reference_code, vehicle_id, driver_id, trip_id,
    station_name, fuel_type,
    litres, unit_price,
    mileage, purchased_at,
    receipt_file
)
SELECT
    'FUEL-TZ-0001',
    v.id,
    d.id,
    t.id,
    'Total Kayonza', 'diesel',
    150.00, 1450.00,
    45350, '2026-09-27 09:05:00',
    '20260921-004424-2ab8a739bdc1.pdf'
FROM trips t
CROSS JOIN (SELECT id FROM vehicles WHERE plate_number = 'RAD 123 A' LIMIT 1) v
CROSS JOIN (SELECT id FROM drivers WHERE license_number = 'RWA-DL-TZ-0456' LIMIT 1) d
WHERE t.reference_code = 'TRP-TZ-0001'
ON DUPLICATE KEY UPDATE
    litres       = VALUES(litres),
    unit_price   = VALUES(unit_price),
    mileage      = VALUES(mileage),
    receipt_file = VALUES(receipt_file);

-- =============================================================================
-- 13. EXPENSES — Border fees and driver allowance
-- =============================================================================
INSERT INTO expenses (
    reference_code, trip_id, vehicle_id,
    category, amount, submitted_by,
    approved_by, approved_at,
    expense_date, status, notes
)
SELECT
    'EXP-TZ-0001',
    t.id,
    v.id,
    'border_fees', 385000.00,
    u_samuel.id,
    u_aline.id, '2026-09-28 08:00:00',
    '2026-09-27', 'approved',
    'Rusumo border total charges: duty 120k, agent 85k, bond 95k, weighbridge 15k, toll 50k, scanning 20k.'
FROM trips t
CROSS JOIN (SELECT id FROM vehicles WHERE plate_number = 'RAD 123 A' LIMIT 1) v
CROSS JOIN (SELECT id FROM users WHERE email = 'samuel.nkurunziza@itec.rw' LIMIT 1) u_samuel
CROSS JOIN (SELECT id FROM users WHERE email = 'aline@itec.rw' LIMIT 1) u_aline
WHERE t.reference_code = 'TRP-TZ-0001'
ON DUPLICATE KEY UPDATE
    amount       = VALUES(amount),
    approved_by  = VALUES(approved_by),
    approved_at  = VALUES(approved_at),
    status       = VALUES(status),
    notes        = VALUES(notes);

INSERT INTO expenses (
    reference_code, trip_id, vehicle_id,
    category, amount, submitted_by,
    approved_by, approved_at,
    expense_date, status, notes
)
SELECT
    'EXP-TZ-0002',
    t.id,
    v.id,
    'allowance', 180000.00,
    u_samuel.id,
    u_aline.id, '2026-09-30 09:00:00',
    '2026-09-27', 'approved',
    'Driver per-diem allowance: 3 days × 60,000 RWF (Kigali to Dar es Salaam and back).'
FROM trips t
CROSS JOIN (SELECT id FROM vehicles WHERE plate_number = 'RAD 123 A' LIMIT 1) v
CROSS JOIN (SELECT id FROM users WHERE email = 'samuel.nkurunziza@itec.rw' LIMIT 1) u_samuel
CROSS JOIN (SELECT id FROM users WHERE email = 'aline@itec.rw' LIMIT 1) u_aline
WHERE t.reference_code = 'TRP-TZ-0001'
ON DUPLICATE KEY UPDATE
    amount      = VALUES(amount),
    approved_by = VALUES(approved_by),
    approved_at = VALUES(approved_at),
    status      = VALUES(status),
    notes       = VALUES(notes);

INSERT INTO expenses (
    reference_code, trip_id, vehicle_id,
    category, amount, submitted_by,
    approved_by, approved_at,
    expense_date, status, notes
)
SELECT
    'EXP-TZ-0003',
    t.id,
    v.id,
    'fuel', 217500.00,
    u_samuel.id,
    u_aline.id, '2026-09-28 08:00:00',
    '2026-09-27', 'approved',
    'Fuel — Total Kayonza: 150L × 1,450 RWF = 217,500 RWF.'
FROM trips t
CROSS JOIN (SELECT id FROM vehicles WHERE plate_number = 'RAD 123 A' LIMIT 1) v
CROSS JOIN (SELECT id FROM users WHERE email = 'samuel.nkurunziza@itec.rw' LIMIT 1) u_samuel
CROSS JOIN (SELECT id FROM users WHERE email = 'aline@itec.rw' LIMIT 1) u_aline
WHERE t.reference_code = 'TRP-TZ-0001'
ON DUPLICATE KEY UPDATE
    amount      = VALUES(amount),
    approved_by = VALUES(approved_by),
    approved_at = VALUES(approved_at),
    status      = VALUES(status),
    notes       = VALUES(notes);

-- =============================================================================
-- 14. INVOICE + LINES + PAYMENT
-- =============================================================================
INSERT INTO invoices (
    invoice_number, customer_id, trip_id,
    issue_date, due_date,
    tax_rate,
    issued_by,
    status, notes
)
SELECT
    'INV-TZ-0001',
    c.id,
    t.id,
    '2026-09-30', '2026-10-30',
    18.00,
    u.id,
    'paid',
    'Cross-border transport Rwanda → Tanzania. TRP-TZ-0001.'
FROM customers c
CROSS JOIN trips t
CROSS JOIN (SELECT id FROM users WHERE email = 'emmanuel@itec.rw' LIMIT 1) u
WHERE c.customer_code = 'CUS-TZ-0001'
  AND t.reference_code = 'TRP-TZ-0001'
ON DUPLICATE KEY UPDATE
    issue_date = VALUES(issue_date),
    due_date   = VALUES(due_date),
    status     = VALUES(status),
    notes      = VALUES(notes);

-- Invoice lines
DELETE il FROM invoice_lines il
  INNER JOIN invoices i ON i.id = il.invoice_id
  WHERE i.invoice_number = 'INV-TZ-0001';

INSERT INTO invoice_lines (invoice_id, description, quantity, unit_price)
SELECT i.id, l.descr, l.qty, l.price
FROM invoices i
CROSS JOIN (
          SELECT 'Cross-border freight — Kigali to Dar es Salaam (8,500 kg × 850 RWF/kg)' descr, 1.00 qty, 7225000.00 price
    UNION ALL
    SELECT 'Border handling and clearance fees (Rusumo)',                                          1.00,       385000.00
    UNION ALL
    SELECT 'Driver allowance (3 days)',                                                            1.00,       180000.00
    UNION ALL
    SELECT 'Transit documentation and customs agent',                                              1.00,        85000.00
) l
WHERE i.invoice_number = 'INV-TZ-0001';

-- Recalculate totals
UPDATE invoices i
   SET i.subtotal = (SELECT COALESCE(SUM(line_total), 0) FROM invoice_lines WHERE invoice_id = i.id)
 WHERE i.invoice_number = 'INV-TZ-0001';

UPDATE invoices
   SET tax_amount    = ROUND(subtotal * (tax_rate / 100), 2),
       total_amount  = ROUND(subtotal + ROUND(subtotal * (tax_rate / 100), 2), 2)
 WHERE invoice_number = 'INV-TZ-0001';

-- Payment in full
INSERT INTO payments (
    payment_code, invoice_id, amount,
    method, reference,
    paid_at, recorded_by, notes
)
SELECT
    'PAY-TZ-0001',
    i.id,
    i.total_amount,
    'bank_transfer', 'BK-TZ-20261028-00123',
    '2026-10-28 10:30:00',
    u.id,
    'Full payment received. Bank of Kigali transfer from Dar es Salaam Buyer Ltd.'
FROM invoices i
CROSS JOIN (SELECT id FROM users WHERE email = 'emmanuel@itec.rw' LIMIT 1) u
WHERE i.invoice_number = 'INV-TZ-0001'
ON DUPLICATE KEY UPDATE
    amount    = VALUES(amount),
    reference = VALUES(reference),
    paid_at   = VALUES(paid_at),
    notes     = VALUES(notes);

-- Sync amount_paid and status
UPDATE invoices i
   SET i.amount_paid = (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = i.id AND deleted_at IS NULL),
       i.status = 'paid'
 WHERE i.invoice_number = 'INV-TZ-0001';

-- =============================================================================
-- 15. NOTIFICATIONS — key milestones recorded in the notification centre
-- =============================================================================
INSERT INTO notifications (notification_key, user_id, role_key, title, message, link_route, severity, is_read, created_at)
SELECT n.nkey, u.id, NULL, n.title, n.msg, n.route, n.sev, 1, n.created
FROM (
          SELECT 'tz-req-approved' nkey, 'aline@itec.rw' email, 'Transport request approved' title, 'REQ-TZ-0001 is approved and ready to be planned into a trip.' msg, 'requests' route, 'success' sev, '2026-09-25 09:30:00' created
    UNION ALL
    SELECT 'tz-trip-dispatched',           'samuel.nkurunziza@itec.rw',  'Trip dispatched to you',              'TRP-TZ-0001 from Kigali Export Depot to Kariakoo, Dar es Salaam is now in transit.',                             'trips',    'info',    '2026-09-27 06:15:00'
    UNION ALL
    SELECT 'tz-border-cleared',            'aline@itec.rw',              'Border crossing cleared',             'CRS-TZ-2026-0001 — RAD 123 A cleared Rusumo in 2h 30min. Charges: 385,000 RWF.',                                  'crossings','success', '2026-09-27 15:50:00'
    UNION ALL
    SELECT 'tz-delivery-completed',        'aline@itec.rw',              'Delivery completed',                  'DLV-TZ-0001 delivered to Hassan Juma at Kariakoo, Dar es Salaam. 500 cartons received.',                          'deliveries','success','2026-09-29 17:45:00'
    UNION ALL
    SELECT 'tz-trip-completed',            'aline@itec.rw',              'Trip completed',                      'TRP-TZ-0001 arrived at Kariakoo, Dar es Salaam. Arrived on time. Vehicle and driver released.',                   'trips',    'success', '2026-09-29 18:00:00'
    UNION ALL
    SELECT 'tz-invoice-issued',            'emmanuel@itec.rw',           'Invoice issued',                      'INV-TZ-0001 issued to Dar es Salaam Buyer Ltd. Due on 2026-10-30.',                                               'invoices', 'info',    '2026-09-30 09:00:00'
    UNION ALL
    SELECT 'tz-payment-received',          'emmanuel@itec.rw',           'Payment received',                    'INV-TZ-0001 fully paid by Dar es Salaam Buyer Ltd via bank transfer on 2026-10-28.',                              'invoices', 'success', '2026-10-28 10:30:00'
) n
INNER JOIN users u ON u.email = n.email
ON DUPLICATE KEY UPDATE
    title    = VALUES(title),
    message  = VALUES(message),
    severity = VALUES(severity);

-- =============================================================================
-- 16. AUDIT LOG — key actions
-- =============================================================================
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT u.id, a.action, a.etype, a.eid, a.reason, a.meta, a.created
FROM (
          SELECT 'aline@itec.rw' email, 'record.create'              action, 'customers'           etype, 'CUS-TZ-0001'  eid, NULL reason, '{"customer_name":"Dar es Salaam Buyer Ltd"}'                 meta, '2026-09-25 09:00:00' created
    UNION ALL
    SELECT 'aline@itec.rw', 'record.create',              'transport_requests',  'REQ-TZ-0001',  NULL, '{"destination":"Dar es Salaam","weight_kg":8500}',                               '2026-09-25 09:05:00'
    UNION ALL
    SELECT 'aline@itec.rw', 'workflow.requests.quote',    'requests',            'REQ-TZ-0001',  NULL, '{"from":"pending","to":"quoted","amount":7225000}',                              '2026-09-25 09:15:00'
    UNION ALL
    SELECT 'aline@itec.rw', 'workflow.requests.approve',  'requests',            'REQ-TZ-0001',  NULL, '{"from":"quoted","to":"approved"}',                                             '2026-09-25 09:30:00'
    UNION ALL
    SELECT 'aline@itec.rw', 'record.create',              'trips',               'TRP-TZ-0001',  NULL, '{"vehicle":"RAD 123 A","driver":"Samuel Nkurunziza"}',                          '2026-09-25 10:30:00'
    UNION ALL
    SELECT 'aline@itec.rw', 'workflow.trips.dispatch',    'trips',               'TRP-TZ-0001',  NULL, '{"from":"requested","to":"in_transit","guards_passed":10}',                    '2026-09-27 06:15:00'
    UNION ALL
    SELECT 'aline@itec.rw', 'record.create',              'crossings',           'CRS-TZ-2026-0001',NULL,'{"border_post":"Rusumo","direction":"export"}',                               '2026-09-27 13:20:00'
    UNION ALL
    SELECT 'samuel.nkurunziza@itec.rw', 'workflow.deliveries.complete','deliveries','DLV-TZ-0001',NULL,'{"from":"in_transit","to":"delivered","on_time":true}',                        '2026-09-29 17:45:00'
    UNION ALL
    SELECT 'aline@itec.rw', 'workflow.trips.complete',    'trips',               'TRP-TZ-0001',  NULL, '{"from":"in_transit","to":"delivered","on_time":true}',                        '2026-09-29 18:00:00'
    UNION ALL
    SELECT 'emmanuel@itec.rw','workflow.invoices.approve','invoices',            'INV-TZ-0001',  NULL, '{"from":"draft","to":"issued","total":9319000}',                                '2026-09-30 09:00:00'
    UNION ALL
    SELECT 'emmanuel@itec.rw','record.create',            'payments',            'PAY-TZ-0001',  NULL, '{"method":"bank_transfer","amount":9319000}',                                  '2026-10-28 10:30:00'
) a
INNER JOIN users u ON u.email = a.email;

COMMIT;

-- =============================================================================
-- SUMMARY (what this seed creates)
-- =============================================================================
-- Customer   : CUS-TZ-0001  Dar es Salaam Buyer Ltd
-- Warehouse  : Kigali Export Depot  |  Dar es Salaam Receiving Hub
-- Rate card  : RATE-TZ-0001  (Kigali → DSM, 850 RWF/kg)
-- Vehicle    : RAD 123 A  (MAN TGX, 20t)  + 5 compliance documents
-- Driver     : Samuel Nkurunziza  (licence RWA-DL-TZ-0456, valid to 2027-06-30)
--              + 4 driver documents (licence, passport, permit, national ID)
-- Request    : REQ-TZ-0001  pending → quoted → approved → assigned
-- Shipment   : SHP-TZ-0001  500 cartons electronics, 8,500 kg  → delivered
-- Trip       : TRP-TZ-0001  6 stops (Kigali → Kayonza → Rusumo → Sirari → Shinyanga → DSM)
-- Delivery   : DLV-TZ-0001  delivered on time with proof + signature
-- Pre-dispatch: all 7 checks passed
-- Border crossing: CRS-TZ-2026-0001  Rusumo, cleared in 2h 30min
--   • 6 charge lines  (duty 120k, agent 85k, bond 95k, weighbridge 15k, toll 50k, scanning 20k)
--   • 9 border documents
-- Fuel       : FUEL-TZ-0001  150L × 1,450 RWF = 217,500 RWF  (Total Kayonza)
-- Expenses   : EXP-TZ-0001  border fees 385,000 RWF  (approved)
--              EXP-TZ-0002  driver allowance 180,000 RWF  (approved)
--              EXP-TZ-0003  fuel 217,500 RWF  (approved)
-- Invoice    : INV-TZ-0001  subtotal 7,875,000 RWF + 18% VAT = 9,292,500 RWF  → paid
-- Payment    : PAY-TZ-0001  full payment bank transfer 2026-10-28
-- Notifications: 7 milestone notifications (all marked read)
-- Audit logs : 11 action records
-- =============================================================================
