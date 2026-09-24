USE logistics_mvc;

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Data for everything added in the 2026-09-20 migrations: customers, rate
-- cards, invoices, shipments, multi-stop routes, vehicle compliance documents,
-- the stock ledger, maintenance parts and purchase lines.
--
-- Every statement is idempotent, so this file can be re-run safely.
-- ---------------------------------------------------------------------------

START TRANSACTION;

-- ------------------------------------------------------------------ customers
INSERT INTO customers (customer_code, customer_name, customer_type, contact_name, phone, email, address, district, tin_number, payment_terms_days, credit_limit, status, notes) VALUES
('CUS-2026-0001', 'Rwanda Education Board',            'government', 'Claudine Uwimana', '+250 788 301 220', 'logistics@reb.gov.rw',      'KG 9 Ave, Kacyiru',   'Gasabo',   '101234567', 45, 25000000.00, 'active',  'School feeding programme; deliveries must arrive before 10:00.'),
('CUS-2026-0002', 'Kigali Supermarket Group',          'corporate',  'Patrick Ngabo',    '+250 788 442 118', 'orders@ksgroup.rw',         'KN 4 Ave, Nyarugenge','Nyarugenge','102345678', 30, 12000000.00, 'active',  'Three drop points in Kigali; cold chain for dairy.'),
('CUS-2026-0003', 'Huye District Hospital',            'government', 'Dr Jeanne Mukasine','+250 788 550 907','supply@huyehospital.rw',    'Huye Town',           'Huye',     '103456789', 60,  8000000.00, 'active',  'Medical cold chain, 2 to 8 degrees, priority handling.'),
('CUS-2026-0004', 'Rubavu Fresh Traders',              'corporate',  'Emmanuel Bizimana','+250 783 220 445', 'info@rubavufresh.rw',       'Gisenyi Market',      'Rubavu',   '104567890', 15,  4500000.00, 'active',  'Pays on 15 days; high volume in the dry season.'),
('CUS-2026-0005', 'Nyagatare Farmers Cooperative',     'ngo',        'Alice Mutoni',     '+250 782 118 663', 'coop@nyagatarefarmers.rw',  'Nyagatare Centre',    'Nyagatare','105678901', 30,  3000000.00, 'on_hold', 'On hold pending settlement of INV-2026-0004.')
ON DUPLICATE KEY UPDATE
    customer_name = VALUES(customer_name), customer_type = VALUES(customer_type), contact_name = VALUES(contact_name),
    phone = VALUES(phone), email = VALUES(email), address = VALUES(address), district = VALUES(district),
    tin_number = VALUES(tin_number), payment_terms_days = VALUES(payment_terms_days), credit_limit = VALUES(credit_limit),
    status = VALUES(status), notes = VALUES(notes);

-- ----------------------------------------------------------------- rate cards
INSERT INTO rate_cards (rate_code, customer_id, origin, destination, vehicle_type, rate_type, rate_amount, minimum_charge, effective_from, effective_to, status, notes)
SELECT v.code, c.id, v.origin, v.destination, v.vtype, v.rtype, v.amount, v.minimum, v.eff_from, v.eff_to, v.status, v.notes
FROM (
              SELECT 'RATE-2026-0001' code, 'CUS-2026-0001' cust, 'Kigali' origin, 'Huye' destination, 'Refrigerated truck' vtype, 'per_trip' rtype, 420000.00 amount, 380000.00 minimum, '2026-01-01' eff_from, '2026-12-31' eff_to, 'active' status, 'Cold-chain school feeding route.' notes
    UNION ALL SELECT 'RATE-2026-0002', 'CUS-2026-0001', 'Kigali', 'Musanze', 'Delivery truck', 'per_trip', 380000.00, 340000.00, '2026-01-01', '2026-12-31', 'active', 'Standard ambient route.'
    UNION ALL SELECT 'RATE-2026-0003', 'CUS-2026-0002', 'Kigali', 'Kigali', 'Van', 'per_package', 1800.00, 90000.00, '2026-03-01', '2026-12-31', 'active', 'City distribution, priced per crate.'
    UNION ALL SELECT 'RATE-2026-0004', 'CUS-2026-0003', 'Kigali', 'Huye', 'Refrigerated truck', 'per_kg', 260.00, 300000.00, '2026-02-01', NULL, 'active', 'Medical cold chain; minimum charge applies.'
    UNION ALL SELECT 'RATE-2026-0005', 'CUS-2026-0004', 'Kigali', 'Rubavu', 'Box truck', 'per_trip', 455000.00, NULL, '2026-01-15', '2026-06-30', 'expired', 'Superseded by the 2026 second-half tariff.'
) v
INNER JOIN customers c ON c.customer_code = v.cust
ON DUPLICATE KEY UPDATE
    origin = VALUES(origin), destination = VALUES(destination), vehicle_type = VALUES(vehicle_type),
    rate_type = VALUES(rate_type), rate_amount = VALUES(rate_amount), minimum_charge = VALUES(minimum_charge),
    effective_from = VALUES(effective_from), effective_to = VALUES(effective_to), status = VALUES(status), notes = VALUES(notes);

-- --------------------------------------------------- vehicle asset attributes
UPDATE vehicles SET make = 'Toyota',     capacity_kg = 3500,  capacity_m3 = 14.000, fuel_type = 'diesel', ownership = 'owned',  has_cooling_unit = 1, manufacture_year = 2019, chassis_number = 'JT-DYNA-482D' WHERE plate_number = 'RAC 482D';
UPDATE vehicles SET make = 'Toyota',     capacity_kg = 1200,  capacity_m3 =  4.500, fuel_type = 'diesel', ownership = 'owned',  has_cooling_unit = 0, manufacture_year = 2020, chassis_number = 'JT-HILUX-118K' WHERE plate_number = 'RAB 118K';
UPDATE vehicles SET make = 'Isuzu',      capacity_kg = 5000,  capacity_m3 = 22.000, fuel_type = 'diesel', ownership = 'owned',  has_cooling_unit = 0, manufacture_year = 2017, chassis_number = 'IS-NPR-901P' WHERE plate_number = 'RAC 901P';
UPDATE vehicles SET make = 'Mitsubishi', capacity_kg = 3000,  capacity_m3 = 12.000, fuel_type = 'diesel', ownership = 'leased', has_cooling_unit = 1, manufacture_year = 2021, chassis_number = 'MI-CANTER-332M' WHERE plate_number = 'RAB 332M';
UPDATE vehicles SET make = 'Toyota',     capacity_kg =  900,  capacity_m3 =  6.000, fuel_type = 'petrol', ownership = 'owned',  has_cooling_unit = 0, manufacture_year = 2018, chassis_number = 'JT-HIACE-774F' WHERE plate_number = 'RAC 774F';
UPDATE vehicles SET make = 'Ford',       capacity_kg = 1100,  capacity_m3 =  4.200, fuel_type = 'diesel', ownership = 'owned',  has_cooling_unit = 0, manufacture_year = 2019, chassis_number = 'FD-RANGER-640C' WHERE plate_number = 'RAB 640C';
UPDATE vehicles SET make = 'Hino',       capacity_kg = 4200,  capacity_m3 = 18.000, fuel_type = 'diesel', ownership = 'owned',  has_cooling_unit = 0, manufacture_year = 2015, chassis_number = 'HN-300-208L' WHERE plate_number = 'RAC 208L';
UPDATE vehicles SET make = 'TVS',        capacity_kg =  150,  capacity_m3 =  0.400, fuel_type = 'petrol', ownership = 'owned',  has_cooling_unit = 0, manufacture_year = 2022, chassis_number = 'TV-KING-519T' WHERE plate_number = 'RAB 519T';
UPDATE vehicles SET make = 'Fuso',       capacity_kg = 6000,  capacity_m3 = 26.000, fuel_type = 'diesel', ownership = 'owned',  has_cooling_unit = 1, manufacture_year = 2020, chassis_number = 'FS-FIGHTER-863N' WHERE plate_number = 'RAC 863N';
UPDATE vehicles SET make = 'Nissan',     capacity_kg =  950,  capacity_m3 =  5.800, fuel_type = 'diesel', ownership = 'rented', has_cooling_unit = 0, manufacture_year = 2016, chassis_number = 'NS-CARAVAN-407G' WHERE plate_number = 'RAB 407G';

-- ------------------------------------------------------- compliance documents
INSERT INTO vehicle_documents (document_code, vehicle_id, document_type, document_number, provider_name, issued_on, expires_on, cost, status, notes)
SELECT d.code, v.id, d.dtype, d.number, d.provider, d.issued, d.expires, d.cost, d.status, d.notes
FROM (
              SELECT 'DOC-2026-0001' code, 'RAC 482D' plate, 'insurance' dtype, 'SON-2026-88412' number, 'SONARWA General' provider, '2026-01-10' issued, '2027-01-09' expires, 780000.00 cost, 'valid' status, 'Comprehensive cover including cold-chain unit.' notes
    UNION ALL SELECT 'DOC-2026-0002', 'RAC 482D', 'inspection', 'INS-2026-2210', 'Rwanda Inspection Centre', '2026-03-02', '2026-09-30', 45000.00, 'expiring', 'Half-yearly road-worthiness test.'
    UNION ALL SELECT 'DOC-2026-0003', 'RAB 118K', 'insurance', 'RAD-2026-11208', 'Radiant Insurance', '2026-02-14', '2027-02-13', 410000.00, 'valid', NULL
    UNION ALL SELECT 'DOC-2026-0004', 'RAC 901P', 'inspection', 'INS-2026-1904', 'Rwanda Inspection Centre', '2025-09-12', '2026-09-11', 45000.00, 'expired', 'Overdue: vehicle is in maintenance.'
    UNION ALL SELECT 'DOC-2026-0005', 'RAB 332M', 'insurance', 'SAN-2026-44120', 'Sanlam Rwanda', '2026-04-01', '2027-03-31', 690000.00, 'valid', 'Leased vehicle; insurer billed to the lessor.'
    UNION ALL SELECT 'DOC-2026-0006', 'RAC 863N', 'road_license', 'RL-2026-7781', 'RURA', '2026-01-05', '2026-12-31', 120000.00, 'valid', 'Goods transport licence.'
    UNION ALL SELECT 'DOC-2026-0007', 'RAC 774F', 'registration', 'REG-774F-2018', 'Rwanda Revenue Authority', '2018-07-20', '2028-07-19', 0.00, 'valid', NULL
    UNION ALL SELECT 'DOC-2026-0008', 'RAB 640C', 'inspection', 'INS-2026-3340', 'Rwanda Inspection Centre', '2026-04-18', '2026-10-17', 45000.00, 'expiring', NULL
) d
INNER JOIN vehicles v ON v.plate_number = d.plate
ON DUPLICATE KEY UPDATE
    document_type = VALUES(document_type), document_number = VALUES(document_number), provider_name = VALUES(provider_name),
    issued_on = VALUES(issued_on), expires_on = VALUES(expires_on), cost = VALUES(cost), status = VALUES(status), notes = VALUES(notes);

-- ---------------------------------------------------------- driver HR details
UPDATE drivers SET license_class = 'C', national_id = '1198780012345678', hired_on = '2021-03-15', emergency_contact = 'Alphonsine Niyonzima', emergency_phone = '+250 788 632 200', address = 'Kicukiro, Kigali' WHERE license_number = 'RWA-DL-0912';
UPDATE drivers SET license_class = 'B', national_id = '1199180023456789', hired_on = '2022-06-01', emergency_contact = 'Olivier Uwase',        emergency_phone = '+250 788 120 500', address = 'Gasabo, Kigali'   WHERE license_number = 'RWA-DL-1028';
UPDATE drivers SET license_class = 'C', national_id = '1198580034567890', hired_on = '2019-11-04', emergency_contact = 'Chantal Murenzi',      emergency_phone = '+250 783 210 100', address = 'Nyarugenge, Kigali' WHERE license_number = 'RWA-DL-0744';
UPDATE drivers SET license_class = 'D', national_id = '1199380045678901', hired_on = '2023-01-09', emergency_contact = 'Yves Kayitesi',        emergency_phone = '+250 788 400 300', address = 'Kicukiro, Kigali' WHERE license_number = 'RWA-DL-1102';
UPDATE drivers SET license_class = 'C', national_id = '1198280056789012', hired_on = '2018-05-21', emergency_contact = 'Grace Habimana',       emergency_phone = '+250 788 512 100', address = 'Huye'            WHERE license_number = 'RWA-DL-0987';

-- ------------------------------------------- planned arrival on existing trips
UPDATE trips SET planned_departure_at = departure_at,
                 planned_arrival_at = DATE_ADD(departure_at, INTERVAL 5 HOUR)
 WHERE departure_at IS NOT NULL AND planned_arrival_at IS NULL;

UPDATE trips t
  INNER JOIN customers c ON c.customer_code = 'CUS-2026-0001'
    SET t.customer_id = c.id
  WHERE t.reference_code IN ('TRP-0248', 'TRP-0247', 'TRP-0244') AND t.customer_id IS NULL;

UPDATE trips t
  INNER JOIN customers c ON c.customer_code = 'CUS-2026-0004'
    SET t.customer_id = c.id
  WHERE t.reference_code = 'TRP-0246' AND t.customer_id IS NULL;

-- ------------------------------------------------------------------ shipments
INSERT INTO shipments (shipment_code, trip_id, customer_id, consignee_name, consignee_phone, origin, destination, cargo_type, cargo_description, packages_count, weight_kg, volume_m3, temperature_min_c, temperature_max_c, is_hazardous, declared_value, special_instructions, status, booked_at)
SELECT s.code, t.id, c.id, s.consignee, s.phone, s.origin, s.destination, s.ctype, s.descr, s.packages, s.weight, s.volume, s.tmin, s.tmax, s.hazard, s.value, s.instructions, s.status, s.booked
FROM (
              SELECT 'SHP-2026-0001' code, 'TRP-0248' trip, 'CUS-2026-0001' cust, 'Huye Depot Store' consignee, '+250 788 550 900' phone, 'Kigali Central Warehouse' origin, 'Huye Depot' destination, 'cold_chain' ctype, 'Fortified maize flour and chilled dairy, 40 sacks and 12 crates' descr, 52 packages, 1320.00 weight, 6.400 volume, 2.00 tmin, 8.00 tmax, 0 hazard, 4200000.00 value, 'Keep the cooling unit running at every stop. Do not stack crates more than three high.' instructions, 'in_transit' status, '2026-09-18 06:10:00' booked
    UNION ALL SELECT 'SHP-2026-0002', 'TRP-0247', 'CUS-2026-0001', 'Musanze Warehouse', '+250 788 441 020', 'Kigali Central Warehouse', 'Musanze Depot', 'general', 'School stationery and safety vests, 30 boxes', 30, 640.00, 3.200, NULL, NULL, 0, 1150000.00, NULL, 'delivered', '2026-09-18 05:40:00'
    UNION ALL SELECT 'SHP-2026-0003', 'TRP-0246', 'CUS-2026-0004', 'Rubavu Branch Store', '+250 783 220 445', 'Kigali Central Warehouse', 'Rubavu Depot', 'perishable', 'Fresh produce, 24 crates', 24, 880.00, 4.100, 4.00, 10.00, 0, 1980000.00, 'Deliver before 11:00; produce is for the same-day market.', 'loaded', '2026-09-18 09:20:00'
    UNION ALL SELECT 'SHP-2026-0004', 'TRP-0244', 'CUS-2026-0001', 'Nyagatare Branch', '+250 782 118 663', 'Kigali Central Warehouse', 'Nyagatare Depot', 'general', 'Dry goods, 45 sacks', 45, 1125.00, 5.600, NULL, NULL, 0, 2300000.00, NULL, 'delivered', '2026-09-17 07:30:00'
    UNION ALL SELECT 'SHP-2026-0005', 'TRP-0245', 'CUS-2026-0003', 'Huye District Hospital Pharmacy', '+250 788 550 907', 'Kigali Central Warehouse', 'Huye', 'cold_chain', 'Vaccine cold boxes, 8 units', 8, 210.00, 1.100, 2.00, 8.00, 0, 8800000.00, 'Temperature logger must travel with the consignment.', 'booked', '2026-09-18 06:00:00'
) s
INNER JOIN trips t ON t.reference_code = s.trip
INNER JOIN customers c ON c.customer_code = s.cust
ON DUPLICATE KEY UPDATE
    consignee_name = VALUES(consignee_name), consignee_phone = VALUES(consignee_phone), origin = VALUES(origin),
    destination = VALUES(destination), cargo_type = VALUES(cargo_type), cargo_description = VALUES(cargo_description),
    packages_count = VALUES(packages_count), weight_kg = VALUES(weight_kg), volume_m3 = VALUES(volume_m3),
    temperature_min_c = VALUES(temperature_min_c), temperature_max_c = VALUES(temperature_max_c),
    declared_value = VALUES(declared_value), special_instructions = VALUES(special_instructions), status = VALUES(status);

-- ------------------------------------------------------ multi-stop trip routes
DELETE ts FROM trip_stops ts INNER JOIN trips t ON t.id = ts.trip_id WHERE t.reference_code IN ('TRP-0248', 'TRP-0246');

INSERT INTO trip_stops (trip_id, stop_sequence, stop_type, location_name, contact_name, contact_phone, planned_arrival_at, actual_arrival_at, status, notes)
SELECT t.id, s.seq, s.stype, s.location, s.contact, s.phone, s.planned, s.actual, s.status, s.notes
FROM (
              SELECT 'TRP-0248' trip, 1 seq, 'pickup' stype, 'Kigali Central Warehouse' location, 'Nadine Tuyisenge' contact, '+250 782 110 554' phone, '2026-09-18 08:00:00' planned, '2026-09-18 08:05:00' actual, 'completed' status, 'Cold-chain unit pre-cooled to 4 degrees before loading.' notes
    UNION ALL SELECT 'TRP-0248', 2, 'checkpoint', 'Muhanga weighbridge', NULL, NULL, '2026-09-18 10:00:00', '2026-09-18 10:12:00', 'completed', 'Axle load within limit.'
    UNION ALL SELECT 'TRP-0248', 3, 'dropoff', 'Huye Depot', 'Huye Depot Store', '+250 788 550 900', '2026-09-18 13:30:00', NULL, 'pending', 'Main consignment.'
    UNION ALL SELECT 'TRP-0248', 4, 'dropoff', 'Huye District Hospital', 'Dr Jeanne Mukasine', '+250 788 550 907', '2026-09-18 14:30:00', NULL, 'pending', 'Vaccine cold boxes; hand over to the pharmacy directly.'
    UNION ALL SELECT 'TRP-0246', 1, 'pickup', 'Kigali Central Warehouse', 'Nadine Tuyisenge', '+250 782 110 554', '2026-09-18 10:00:00', NULL, 'pending', NULL
    UNION ALL SELECT 'TRP-0246', 2, 'dropoff', 'Rubavu Depot', 'Emmanuel Bizimana', '+250 783 220 445', '2026-09-18 16:00:00', NULL, 'pending', 'Market delivery; call 30 minutes before arrival.'
) s
INNER JOIN trips t ON t.reference_code = s.trip;

-- ----------------------------------------------------------- delivery details
UPDATE deliveries d
  INNER JOIN shipments s ON s.shipment_code = 'SHP-2026-0001'
    SET d.shipment_id = s.id, d.recipient_phone = '+250 788 550 900', d.planned_at = '2026-09-18 13:30:00'
  WHERE d.delivery_code = 'DEL-0218';

UPDATE deliveries d
  INNER JOIN shipments s ON s.shipment_code = 'SHP-2026-0002'
    SET d.shipment_id = s.id, d.recipient_phone = '+250 788 441 020', d.planned_at = '2026-09-18 11:00:00'
  WHERE d.delivery_code = 'DEL-0217';

UPDATE deliveries SET failure_reason = 'recipient_absent', failure_notes = 'Store closed on arrival; no authorised receiver on site.', attempt_number = 1
 WHERE delivery_code = 'DEL-D003' AND failure_reason = 'none';
UPDATE deliveries SET failure_reason = 'vehicle_breakdown', failure_notes = 'Cooling unit failed near Nyanza; load returned to Kigali.', attempt_number = 1
 WHERE delivery_code = 'DEL-D004' AND failure_reason = 'none';

-- ----------------------------------------------------- opening stock movements
INSERT INTO stock_movements (movement_code, item_id, warehouse_id, movement_type, quantity, unit_cost, balance_after, reference_type, reference_code, performed_by, moved_at, notes)
SELECT m.code, i.id, i.warehouse_id, m.mtype, m.qty, i.unit_cost, m.balance, m.rtype, m.rcode, u.id, m.moved, m.notes
FROM (
              SELECT 'MOV-2026-0001' code, 'SP-BRK-001' sku, 'stock_in' mtype, 30.00 qty, 30.00 balance, 'purchase' rtype, 'PR-0091' rcode, 'nadine@itec.rw' email, '2026-08-05 09:00:00' moved, 'Opening stock received from Kigali Auto Care.' notes
    UNION ALL SELECT 'MOV-2026-0002', 'SP-BRK-001', 'stock_out',  6.00, 24.00, 'trip', 'MNT-0081', 'eric@itec.rw',   '2026-09-04 11:20:00', 'Issued to workshop for brake inspection.'
    UNION ALL SELECT 'MOV-2026-0003', 'SP-OIL-015', 'stock_in',  20.00, 20.00, 'purchase', 'PR-0089', 'nadine@itec.rw', '2026-08-12 08:30:00', 'Engine oil delivery.'
    UNION ALL SELECT 'MOV-2026-0004', 'SP-OIL-015', 'stock_out', 12.00,  8.00, 'trip', 'MNT-0080', 'eric@itec.rw',   '2026-09-10 14:00:00', 'Oil and filter service.'
    UNION ALL SELECT 'MOV-2026-0005', 'EQ-VST-004', 'stock_in', 150.00,150.00, 'purchase', 'PR-0090', 'nadine@itec.rw', '2026-07-22 10:00:00', 'Safety vests for the whole fleet.'
    UNION ALL SELECT 'MOV-2026-0006', 'EQ-VST-004', 'stock_out',  4.00,146.00, 'adjustment', NULL, 'nadine@itec.rw', '2026-09-01 16:45:00', 'Issued to new drivers.'
    UNION ALL SELECT 'MOV-2026-0007', 'SP-AIR-020', 'stock_in',  10.00, 10.00, 'purchase', 'PR-0091', 'nadine@itec.rw', '2026-08-05 09:10:00', 'Air filters received.'
    UNION ALL SELECT 'MOV-2026-0008', 'SP-AIR-020', 'stock_out', 10.00,  0.00, 'trip', 'MNT-0079', 'eric@itec.rw',   '2026-09-12 09:40:00', 'Consumed during preventive service; now out of stock.'
    UNION ALL SELECT 'MOV-2026-0009', 'KFF-COOL-001', 'stock_in', 80.00, 80.00, 'purchase', 'PR-KFF-001', 'nadine@itec.rw', '2026-09-19 08:00:00', 'Reusable cold-chain crates received.'
    UNION ALL SELECT 'MOV-2026-0010', 'KFF-COOL-001', 'stock_out', 18.00, 62.00, 'trip', 'TRP-KFF-001', 'nadine@itec.rw', '2026-09-20 07:30:00', 'Loaded onto the Huye run.'
    UNION ALL SELECT 'MOV-2026-0011', 'KFF-FLOUR-001', 'stock_in', 60.00, 60.00, 'purchase', 'PR-0089', 'nadine@itec.rw', '2026-09-01 09:00:00', 'Fortified maize flour intake.'
    UNION ALL SELECT 'MOV-2026-0012', 'KFF-FLOUR-001', 'stock_out', 42.00, 18.00, 'trip', 'TRP-KFF-001', 'nadine@itec.rw', '2026-09-20 07:35:00', 'Issued to Huye; balance is now below the minimum.'
) m
INNER JOIN inventory_items i ON i.sku = m.sku
LEFT JOIN users u ON u.email = m.email
ON DUPLICATE KEY UPDATE
    movement_type = VALUES(movement_type), quantity = VALUES(quantity), balance_after = VALUES(balance_after),
    reference_type = VALUES(reference_type), reference_code = VALUES(reference_code), moved_at = VALUES(moved_at), notes = VALUES(notes);

-- ------------------------------------------------------- maintenance costings
DELETE mp FROM maintenance_parts mp INNER JOIN maintenance_orders m ON m.id = mp.maintenance_id WHERE m.work_order_code IN ('MNT-0081', 'MNT-0080', 'MNT-KFF-001');

INSERT INTO maintenance_parts (maintenance_id, line_type, part_name, part_number, quantity, unit_cost)
SELECT m.id, p.ltype, p.pname, p.pnumber, p.qty, p.cost
FROM (
              SELECT 'MNT-0081' wo, 'part' ltype, 'Brake pad set, front' pname, 'BP-FR-4421' pnumber, 2.00 qty, 62000.00 cost
    UNION ALL SELECT 'MNT-0081', 'part', 'Brake disc', 'BD-8810', 2.00, 48000.00
    UNION ALL SELECT 'MNT-0081', 'labour', 'Workshop labour, 3 hours', NULL, 3.00, 12000.00
    UNION ALL SELECT 'MNT-0080', 'consumable', 'Engine oil 15W40, 20 litres', 'OIL-15W40', 20.00, 3200.00
    UNION ALL SELECT 'MNT-0080', 'part', 'Oil filter', 'OF-2290', 1.00, 18000.00
    UNION ALL SELECT 'MNT-0080', 'labour', 'Service labour', NULL, 1.00, 15000.00
    UNION ALL SELECT 'MNT-KFF-001', 'service', 'Cold-chain unit inspection and gas top-up', NULL, 1.00, 95000.00
    UNION ALL SELECT 'MNT-KFF-001', 'labour', 'Technician call-out', NULL, 2.00, 18000.00
) p
INNER JOIN maintenance_orders m ON m.work_order_code = p.wo;

UPDATE maintenance_orders m
  SET m.actual_cost = (SELECT COALESCE(SUM(line_total), 0) FROM maintenance_parts WHERE maintenance_id = m.id),
      m.maintenance_type = CASE WHEN m.work_order_code = 'MNT-0081' THEN 'corrective' ELSE 'preventive' END
 WHERE m.work_order_code IN ('MNT-0081', 'MNT-0080', 'MNT-KFF-001');

-- ---------------------------------------------------------- purchase lines
DELETE prl FROM purchase_request_lines prl INNER JOIN purchase_requests pr ON pr.id = prl.purchase_request_id WHERE pr.request_code IN ('PR-0091', 'PR-0090', 'PR-KFF-001');

INSERT INTO purchase_request_lines (purchase_request_id, item_id, item_name, quantity, unit_of_measure, unit_price, received_quantity)
SELECT pr.id, i.id, l.item_name, l.qty, l.uom, l.price, l.received
FROM (
              SELECT 'PR-0091' req, 'SP-BRK-001' sku, 'Brake pad set' item_name, 20.00 qty, 'Unit' uom, 24000.00 price, 0.00 received
    UNION ALL SELECT 'PR-0091', 'SP-AIR-020', 'Air filter', 20.00, 'Unit', 10000.00, 0.00
    UNION ALL SELECT 'PR-0090', 'EQ-VST-004', 'Safety vest', 60.00, 'Unit', 7000.00, 0.00
    UNION ALL SELECT 'PR-KFF-001', 'KFF-COOL-001', 'Reusable cold-chain crate', 40.00, 'Crate', 14500.00, 40.00
) l
INNER JOIN purchase_requests pr ON pr.request_code = l.req
LEFT JOIN inventory_items i ON i.sku = l.sku;

UPDATE purchase_requests pr
  SET pr.amount = (SELECT COALESCE(SUM(line_total), pr.amount) FROM purchase_request_lines WHERE purchase_request_id = pr.id),
      pr.warehouse_id = COALESCE(pr.warehouse_id, 1),
      pr.category = COALESCE(pr.category, 'Spare parts')
 WHERE pr.request_code IN ('PR-0091', 'PR-0090', 'PR-KFF-001');

-- ------------------------------------------------------------------- invoices
INSERT INTO invoices (invoice_number, customer_id, trip_id, issue_date, due_date, tax_rate, status, notes)
SELECT i.number, c.id, t.id, i.issued, i.due, 18.00, i.status, i.notes
FROM (
              SELECT 'INV-2026-0001' number, 'CUS-2026-0001' cust, 'TRP-0247' trip, '2026-09-18' issued, '2026-11-02' due, 'paid' status, 'Musanze school feeding run.' notes
    UNION ALL SELECT 'INV-2026-0002', 'CUS-2026-0001', 'TRP-0248', '2026-09-18', '2026-11-02', 'issued', 'Huye cold-chain run.'
    UNION ALL SELECT 'INV-2026-0003', 'CUS-2026-0004', 'TRP-0246', '2026-09-18', '2026-10-03', 'draft', 'Awaiting proof of delivery before issue.'
    UNION ALL SELECT 'INV-2026-0004', 'CUS-2026-0005', 'TRP-0244', '2026-07-12', '2026-08-11', 'overdue', 'Unpaid; the customer is on hold.'
    UNION ALL SELECT 'INV-2026-0005', 'CUS-2026-0002', NULL, '2026-09-10', '2026-10-10', 'partially_paid', 'Kigali city distribution, first half of September.'
) i
INNER JOIN customers c ON c.customer_code = i.cust
LEFT JOIN trips t ON t.reference_code = i.trip
ON DUPLICATE KEY UPDATE issue_date = VALUES(issue_date), due_date = VALUES(due_date), notes = VALUES(notes);

DELETE il FROM invoice_lines il INNER JOIN invoices i ON i.id = il.invoice_id WHERE i.invoice_number LIKE 'INV-2026-%';

INSERT INTO invoice_lines (invoice_id, description, quantity, unit_price)
SELECT i.id, l.descr, l.qty, l.price
FROM (
              SELECT 'INV-2026-0001' inv, 'Transport Kigali to Musanze, delivery truck' descr, 1.00 qty, 380000.00 price
    UNION ALL SELECT 'INV-2026-0001', 'Loading and handling', 1.00, 45000.00
    UNION ALL SELECT 'INV-2026-0002', 'Cold-chain transport Kigali to Huye', 1.00, 420000.00
    UNION ALL SELECT 'INV-2026-0002', 'Second drop, Huye District Hospital', 1.00, 85000.00
    UNION ALL SELECT 'INV-2026-0002', 'Temperature monitoring', 1.00, 30000.00
    UNION ALL SELECT 'INV-2026-0003', 'Transport Kigali to Rubavu, box truck', 1.00, 455000.00
    UNION ALL SELECT 'INV-2026-0004', 'Transport Kigali to Nyagatare', 1.00, 390000.00
    UNION ALL SELECT 'INV-2026-0005', 'City distribution, 180 crates at 1800', 180.00, 1800.00
) l
INNER JOIN invoices i ON i.invoice_number = l.inv;

INSERT INTO payments (payment_code, invoice_id, amount, method, reference, paid_at, recorded_by, notes)
SELECT p.code, i.id, p.amount, p.method, p.ref, p.paid, u.id, p.notes
FROM (
              SELECT 'PAY-2026-0001' code, 'INV-2026-0001' inv, 501500.00 amount, 'bank_transfer' method, 'BK-778120' ref, '2026-09-19 10:30:00' paid, 'emmanuel@itec.rw' email, 'Settled in full.' notes
    UNION ALL SELECT 'PAY-2026-0002', 'INV-2026-0005', 200000.00, 'mobile_money', 'MOMO-4412098', '2026-09-16 15:05:00', 'emmanuel@itec.rw', 'Part payment on account.'
) p
INNER JOIN invoices i ON i.invoice_number = p.inv
LEFT JOIN users u ON u.email = p.email
ON DUPLICATE KEY UPDATE amount = VALUES(amount), method = VALUES(method), paid_at = VALUES(paid_at), notes = VALUES(notes);

-- Totals are derived, never typed: rebuild them from the lines and payments.
UPDATE invoices i
   SET i.subtotal = (SELECT COALESCE(SUM(line_total), 0) FROM invoice_lines WHERE invoice_id = i.id),
       i.amount_paid = (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = i.id AND deleted_at IS NULL)
 WHERE i.invoice_number LIKE 'INV-2026-%';

UPDATE invoices
   SET tax_amount = ROUND(subtotal * (tax_rate / 100), 2),
       total_amount = ROUND(subtotal + (subtotal * (tax_rate / 100)), 2)
 WHERE invoice_number LIKE 'INV-2026-%';

-- ------------------------------------------------- approvals already on record
UPDATE expenses e
  INNER JOIN users u ON u.email = 'emmanuel@itec.rw'
    SET e.approved_by = u.id, e.approved_at = '2026-09-19 09:15:00'
  WHERE e.status = 'approved' AND e.approved_by IS NULL;

UPDATE transport_requests r
  INNER JOIN users u ON u.email = 'aline@itec.rw'
    SET r.approved_by = u.id, r.approved_at = '2026-09-18 07:20:00'
  WHERE r.status IN ('approved', 'assigned') AND r.approved_by IS NULL;

UPDATE purchase_requests p
  INNER JOIN users u ON u.email = 'emmanuel@itec.rw'
    SET p.approved_by = u.id, p.approved_at = '2026-09-17 11:00:00'
  WHERE p.status IN ('approved', 'received') AND p.approved_by IS NULL;

-- --------------------------------------------------- warehouse and supplier IDs
UPDATE warehouses SET warehouse_code = CONCAT('WH-', LPAD(id, 3, '0')) WHERE warehouse_code IS NULL;
UPDATE warehouses SET is_cold_chain = 1, capacity_m3 = 4200.00 WHERE warehouse_name = 'Kigali Central Warehouse';
UPDATE warehouses SET is_cold_chain = 1, capacity_m3 = 1600.00 WHERE warehouse_name = 'Huye Depot';
UPDATE suppliers SET supplier_code = CONCAT('SUP-', LPAD(id, 3, '0')) WHERE supplier_code IS NULL;
UPDATE suppliers SET category = 'Spare parts', rating = 4, payment_terms_days = 30 WHERE supplier_name = 'Kigali Auto Care' AND category IS NULL;
UPDATE suppliers SET category = 'Equipment',   rating = 5, payment_terms_days = 15 WHERE supplier_name = 'Secure Rwanda'   AND category IS NULL;
UPDATE suppliers SET category = 'Fuel',        rating = 3, payment_terms_days = 30 WHERE supplier_name = 'Lubricants Ltd'  AND category IS NULL;
UPDATE suppliers SET category = 'Services',    rating = 4, payment_terms_days = 45 WHERE supplier_name = 'Fleet Workshop'  AND category IS NULL;

-- ------------------------------------------------- inventory units and batches
UPDATE inventory_items SET unit_of_measure = 'Unit',  category = 'Spare parts' WHERE sku IN ('SP-BRK-001', 'SP-AIR-020', 'DEMO-FIL-003', 'DEMO-LMP-004', 'DEMO-WPR-005', 'DEMO-BAT-002', 'DEMO-TYR-001') AND category IS NULL;
UPDATE inventory_items SET unit_of_measure = 'Litre', category = 'Consumables' WHERE sku = 'SP-OIL-015' AND category IS NULL;
UPDATE inventory_items SET unit_of_measure = 'Unit',  category = 'Equipment'   WHERE sku IN ('EQ-VST-004', 'EQ-TRI-007') AND category IS NULL;
UPDATE inventory_items SET unit_of_measure = 'Crate', category = 'Cold chain', storage_temperature = '2 to 8 C' WHERE sku = 'KFF-COOL-001' AND category IS NULL;
UPDATE inventory_items SET unit_of_measure = 'Sack',  category = 'Food', expiry_date = '2027-03-31', batch_number = 'KFF-B-2026-09' WHERE sku = 'KFF-FLOUR-001' AND category IS NULL;

-- ------------------------------------------------- user security and job titles
UPDATE users SET job_title = 'System Administrator'   WHERE email = 'admin@itec.rw'       AND job_title IS NULL;
UPDATE users SET job_title = 'Head of Operations'     WHERE email = 'aline@itec.rw'       AND job_title IS NULL;
UPDATE users SET job_title = 'Fleet Supervisor'       WHERE email = 'eric@itec.rw'        AND job_title IS NULL;
UPDATE users SET job_title = 'Warehouse Supervisor'   WHERE email = 'nadine@itec.rw'      AND job_title IS NULL;
UPDATE users SET job_title = 'Long-haul Driver'       WHERE email = 'samuel@itec.rw'      AND job_title IS NULL;
UPDATE users SET job_title = 'Finance Officer'        WHERE email = 'emmanuel@itec.rw'    AND job_title IS NULL;
UPDATE users SET job_title = 'Managing Director'      WHERE email = 'jeanpierre@itec.rw'  AND job_title IS NULL;

-- ----------------------------------------------------- reports become runnable
INSERT INTO reports (report_key, report_name, description, period_label, owner_name, format_label, action_label) VALUES
('vehicle_utilization',  'Vehicle utilization',  'How many trips each vehicle ran and how much of the fleet time it used.',     'Monthly',   'Eric Murenzi',         'CSV', 'Run'),
('fuel_consumption',     'Fuel consumption',     'Litres and money spent per vehicle, with average price paid.',                'Monthly',   'Eric Murenzi',         'CSV', 'Run'),
('delivery_performance', 'Delivery performance', 'Deliveries made, failed and re-attempted, with the on-time rate.',            'Monthly',   'Aline Mukamana',       'CSV', 'Run'),
('maintenance_cost',     'Maintenance cost',     'Estimated against actual maintenance cost per vehicle.',                      'Quarterly', 'Eric Murenzi',         'CSV', 'Run'),
('driver_performance',   'Driver performance',   'Trips, deliveries, failures and on-time rate per driver.',                    'Monthly',   'Aline Mukamana',       'CSV', 'Run'),
('inventory_movement',   'Inventory movement',   'Stock in, stock out and closing balance per item.',                           'Monthly',   'Nadine Tuyisenge',     'CSV', 'Run'),
('trip_profitability',   'Trip profitability',   'Invoiced revenue against trip cost, and the resulting margin.',               'Monthly',   'Emmanuel Safari',      'CSV', 'Run'),
('expense_summary',      'Expense summary',      'Expense totals per category with approved, pending and rejected value.',      'Monthly',   'Emmanuel Safari',      'CSV', 'Run')
ON DUPLICATE KEY UPDATE
    report_key = VALUES(report_key), description = VALUES(description), period_label = VALUES(period_label),
    owner_name = VALUES(owner_name), format_label = VALUES(format_label), action_label = VALUES(action_label);

COMMIT;
