-- Worked examples, for looking around rather than working in.
--
-- A fleet, its drivers, a few warehouses and suppliers, and a week of trips,
-- deliveries, fuel and expenses on top of them. Loaded only by
-- `php scripts/migrate.php --demo`, never by a plain migration, so a real
-- company never has to delete someone else's data before it starts.

USE logistics_mvc;

SET NAMES utf8mb4;

START TRANSACTION;
INSERT INTO vehicles (plate_number, vehicle_type, model, mileage, status, next_service_date) VALUES
('RAC 482D', 'Delivery truck', 'Toyota Dyna', 84210, 'on_trip', '2026-10-12'),
('RAB 118K', 'Pickup', 'Toyota Hilux', 62100, 'available', '2026-10-18'),
('RAC 901P', 'Box truck', 'Isuzu NPR', 119800, 'maintenance', '2026-09-18'),
('RAB 332M', 'Delivery truck', 'Mitsubishi Canter', 50500, 'available', '2026-09-22'),
('RAC 774F', 'Van', 'Toyota Hiace', 73100, 'on_trip', '2026-11-04'),
('RAB 640C', 'Pickup', 'Ford Ranger', 46800, 'available', '2026-09-28'),
('RAC 208L', 'Box truck', 'Hino 300', 140200, 'inactive', '2026-12-15'),
('RAB 519T', 'Motorcycle', 'TVS King', 28200, 'on_trip', '2026-10-30'),
('RAC 863N', 'Delivery truck', 'Fuso Fighter', 93200, 'available', '2026-11-07'),
('RAB 407G', 'Van', 'Nissan Caravan', 88400, 'maintenance', '2026-09-18')
ON DUPLICATE KEY UPDATE
    vehicle_type = VALUES(vehicle_type),
    model = VALUES(model),
    mileage = VALUES(mileage),
    status = VALUES(status),
    next_service_date = VALUES(next_service_date);

INSERT INTO drivers (full_name, phone, license_number, license_expiry, status) VALUES
('Samuel Niyonzima', '+250 788 632 119', 'RWA-DL-0912', '2026-12-04', 'on_trip'),
('Marie Uwase', '+250 788 120 442', 'RWA-DL-1028', '2027-03-18', 'available'),
('Eric Murenzi', '+250 783 210 084', 'RWA-DL-0744', '2027-01-29', 'off_duty'),
('Aurore Kayitesi', '+250 788 400 210', 'RWA-DL-1102', '2027-05-10', 'available'),
('Jean Pierre Habimana', '+250 788 512 030', 'RWA-DL-0987', '2026-11-22', 'on_trip'),
('Patrick Rukundo', '+250 783 291 006', 'RWA-DL-0861', '2027-02-14', 'available'),
('Claudine Mukamana', '+250 783 440 301', 'RWA-DL-1168', '2027-06-02', 'available'),
('David Habimana', '+250 788 990 778', 'RWA-DL-1214', '2027-07-11', 'inactive'),
('Nadine Tuyisenge', '+250 782 110 554', 'RWA-DL-1190', '2027-04-23', 'off_duty'),
('Emmanuel Safari', '+250 788 705 881', 'RWA-DL-1255', '2027-08-19', 'available')
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    phone = VALUES(phone),
    license_expiry = VALUES(license_expiry),
    status = VALUES(status);

UPDATE vehicles SET assigned_driver_id = (SELECT id FROM drivers WHERE license_number = 'RWA-DL-0912') WHERE plate_number = 'RAC 482D';
UPDATE vehicles SET assigned_driver_id = (SELECT id FROM drivers WHERE license_number = 'RWA-DL-1028') WHERE plate_number = 'RAB 118K';
UPDATE vehicles SET assigned_driver_id = (SELECT id FROM drivers WHERE license_number = 'RWA-DL-0744') WHERE plate_number = 'RAC 901P';
UPDATE vehicles SET assigned_driver_id = NULL WHERE plate_number = 'RAB 332M';
UPDATE vehicles SET assigned_driver_id = (SELECT id FROM drivers WHERE license_number = 'RWA-DL-0987') WHERE plate_number = 'RAC 774F';
UPDATE vehicles SET assigned_driver_id = (SELECT id FROM drivers WHERE license_number = 'RWA-DL-1102') WHERE plate_number = 'RAB 640C';
UPDATE vehicles SET assigned_driver_id = (SELECT id FROM drivers WHERE license_number = 'RWA-DL-1214') WHERE plate_number = 'RAC 208L';
UPDATE vehicles SET assigned_driver_id = (SELECT id FROM drivers WHERE license_number = 'RWA-DL-0861') WHERE plate_number = 'RAB 519T';
UPDATE vehicles SET assigned_driver_id = NULL WHERE plate_number = 'RAC 863N';
UPDATE vehicles SET assigned_driver_id = (SELECT id FROM drivers WHERE license_number = 'RWA-DL-1168') WHERE plate_number = 'RAB 407G';

UPDATE warehouses
SET location = 'Kigali', status = 'active'
WHERE warehouse_name = 'Kigali Central Warehouse';
INSERT INTO warehouses (warehouse_name, location, status)
SELECT 'Kigali Central Warehouse', 'Kigali', 'active'
WHERE NOT EXISTS (SELECT 1 FROM warehouses WHERE warehouse_name = 'Kigali Central Warehouse');

UPDATE warehouses
SET location = 'Huye', status = 'active'
WHERE warehouse_name = 'Huye Depot';
INSERT INTO warehouses (warehouse_name, location, status)
SELECT 'Huye Depot', 'Huye', 'active'
WHERE NOT EXISTS (SELECT 1 FROM warehouses WHERE warehouse_name = 'Huye Depot');

UPDATE warehouses
SET location = 'Musanze', status = 'active'
WHERE warehouse_name = 'Musanze Depot';
INSERT INTO warehouses (warehouse_name, location, status)
SELECT 'Musanze Depot', 'Musanze', 'active'
WHERE NOT EXISTS (SELECT 1 FROM warehouses WHERE warehouse_name = 'Musanze Depot');

UPDATE warehouses
SET location = 'Rubavu', status = 'active'
WHERE warehouse_name = 'Rubavu Depot';
INSERT INTO warehouses (warehouse_name, location, status)
SELECT 'Rubavu Depot', 'Rubavu', 'active'
WHERE NOT EXISTS (SELECT 1 FROM warehouses WHERE warehouse_name = 'Rubavu Depot');

UPDATE suppliers
SET contact_name = 'Jean Bosco', phone = '+250 788 101 010', email = 'sales@kigaliautocare.rw', status = 'active'
WHERE supplier_name = 'Kigali Auto Care';
INSERT INTO suppliers (supplier_name, contact_name, phone, email, status)
SELECT 'Kigali Auto Care', 'Jean Bosco', '+250 788 101 010', 'sales@kigaliautocare.rw', 'active'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE supplier_name = 'Kigali Auto Care');

UPDATE suppliers
SET contact_name = 'Aline Mukamana', phone = '+250 788 202 020', email = 'orders@securerwanda.rw', status = 'active'
WHERE supplier_name = 'Secure Rwanda';
INSERT INTO suppliers (supplier_name, contact_name, phone, email, status)
SELECT 'Secure Rwanda', 'Aline Mukamana', '+250 788 202 020', 'orders@securerwanda.rw', 'active'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE supplier_name = 'Secure Rwanda');

UPDATE suppliers
SET contact_name = 'David Habyarimana', phone = '+250 788 303 030', email = 'sales@lubricants.rw', status = 'active'
WHERE supplier_name = 'Lubricants Ltd';
INSERT INTO suppliers (supplier_name, contact_name, phone, email, status)
SELECT 'Lubricants Ltd', 'David Habyarimana', '+250 788 303 030', 'sales@lubricants.rw', 'active'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE supplier_name = 'Lubricants Ltd');

UPDATE suppliers
SET contact_name = 'Operations desk', phone = '+250 788 404 040', email = 'workshop@itec.rw', status = 'active'
WHERE supplier_name = 'Fleet Workshop';
INSERT INTO suppliers (supplier_name, contact_name, phone, email, status)
SELECT 'Fleet Workshop', 'Operations desk', '+250 788 404 040', 'workshop@itec.rw', 'active'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE supplier_name = 'Fleet Workshop');

INSERT INTO trips (reference_code, vehicle_id, driver_id, pickup_location, destination, departure_at, arrival_at, status) VALUES
('TRP-0248', (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), (SELECT id FROM drivers WHERE license_number = 'RWA-DL-0912'), 'Kigali', 'Huye', '2026-09-18 08:30:00', NULL, 'in_transit'),
('TRP-0247', (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), (SELECT id FROM drivers WHERE license_number = 'RWA-DL-1028'), 'Kigali', 'Musanze', '2026-09-18 07:00:00', '2026-09-18 11:45:00', 'delivered'),
('TRP-0246', (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), (SELECT id FROM drivers WHERE license_number = 'RWA-DL-0744'), 'Kigali', 'Rubavu', '2026-09-18 10:15:00', NULL, 'loading'),
('TRP-0245', (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), (SELECT id FROM drivers WHERE license_number = 'RWA-DL-0987'), 'Kigali', 'Rusizi', '2026-09-18 06:45:00', NULL, 'approved'),
('TRP-0244', (SELECT id FROM vehicles WHERE plate_number = 'RAB 640C'), (SELECT id FROM drivers WHERE license_number = 'RWA-DL-1102'), 'Kigali', 'Nyagatare', '2026-09-17 09:00:00', '2026-09-17 13:20:00', 'delivered'),
('TRP-0243', (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), (SELECT id FROM drivers WHERE license_number = 'RWA-DL-0861'), 'Huye', 'Kigali', '2026-09-17 14:30:00', NULL, 'cancelled'),
('TRP-0242', (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), (SELECT id FROM drivers WHERE license_number = 'RWA-DL-0861'), 'Kigali', 'Muhanga', '2026-09-16 11:00:00', '2026-09-16 12:30:00', 'delivered'),
('TRP-0241', (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), (SELECT id FROM drivers WHERE license_number = 'RWA-DL-1214'), 'Kigali', 'Kayonza', '2026-09-16 08:00:00', NULL, 'in_transit'),
('TRP-0240', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), (SELECT id FROM drivers WHERE license_number = 'RWA-DL-1255'), 'Musanze', 'Kigali', '2026-09-15 13:15:00', '2026-09-15 16:35:00', 'delivered'),
('TRP-0239', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), (SELECT id FROM drivers WHERE license_number = 'RWA-DL-1168'), 'Kigali', 'Rwamagana', '2026-09-15 07:30:00', NULL, 'loading')
ON DUPLICATE KEY UPDATE
    vehicle_id = VALUES(vehicle_id),
    driver_id = VALUES(driver_id),
    pickup_location = VALUES(pickup_location),
    destination = VALUES(destination),
    departure_at = VALUES(departure_at),
    arrival_at = VALUES(arrival_at),
    status = VALUES(status);

INSERT INTO transport_requests (reference_code, requester_id, pickup_location, destination, required_date, priority, status, notes) VALUES
('REQ-0312', (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'Kigali', 'Huye', '2026-09-20', 'high', 'pending', 'Transport request for procurement deliveries.'),
('REQ-0311', (SELECT id FROM users WHERE email = 'nadine@itec.rw'), 'Kigali', 'Rubavu', '2026-09-21', 'normal', 'approved', 'Warehouse replenishment route.'),
('REQ-0310', (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'Kigali', 'Musanze', '2026-09-22', 'normal', 'assigned', 'Assigned to TRP-0247 after approval.'),
('REQ-0309', (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'Kigali', 'Rusizi', '2026-09-23', 'urgent', 'pending', 'Finance documents and secure parcel.')
ON DUPLICATE KEY UPDATE
    requester_id = VALUES(requester_id),
    pickup_location = VALUES(pickup_location),
    destination = VALUES(destination),
    required_date = VALUES(required_date),
    priority = VALUES(priority),
    status = VALUES(status),
    notes = VALUES(notes);

INSERT INTO deliveries (delivery_code, trip_id, recipient_name, destination, status, proof_file, recipient_signature, delivered_at) VALUES
('DEL-0218', (SELECT id FROM trips WHERE reference_code = 'TRP-0248'), 'Huye depot', 'Huye', 'in_transit', NULL, NULL, NULL),
('DEL-0217', (SELECT id FROM trips WHERE reference_code = 'TRP-0247'), 'Musanze warehouse', 'Musanze', 'delivered', 'proofs/del-0217.pdf', 'signatures/del-0217.png', '2026-09-18 11:45:00'),
('DEL-0216', (SELECT id FROM trips WHERE reference_code = 'TRP-0246'), 'Rubavu branch', 'Rubavu', 'loading', NULL, NULL, NULL),
('DEL-0215', (SELECT id FROM trips WHERE reference_code = 'TRP-0244'), 'Nyagatare branch', 'Nyagatare', 'delivered', 'proofs/del-0215.pdf', 'signatures/del-0215.png', '2026-09-17 13:20:00')
ON DUPLICATE KEY UPDATE
    trip_id = VALUES(trip_id),
    recipient_name = VALUES(recipient_name),
    destination = VALUES(destination),
    status = VALUES(status),
    proof_file = VALUES(proof_file),
    recipient_signature = VALUES(recipient_signature),
    delivered_at = VALUES(delivered_at);

INSERT INTO inventory_items (warehouse_id, sku, item_name, quantity, minimum_level, unit_cost, status) VALUES
((SELECT id FROM warehouses WHERE warehouse_name = 'Kigali Central Warehouse' LIMIT 1), 'SP-BRK-001', 'Brake pads', 24, 10, 28500, 'in_stock'),
((SELECT id FROM warehouses WHERE warehouse_name = 'Kigali Central Warehouse' LIMIT 1), 'SP-OIL-015', 'Engine oil 15W40', 8, 12, 18500, 'reorder'),
((SELECT id FROM warehouses WHERE warehouse_name = 'Huye Depot' LIMIT 1), 'EQ-VST-004', 'Safety vest', 146, 50, 4500, 'in_stock'),
((SELECT id FROM warehouses WHERE warehouse_name = 'Musanze Depot' LIMIT 1), 'SP-AIR-020', 'Air filters', 0, 8, 22000, 'out_of_stock'),
((SELECT id FROM warehouses WHERE warehouse_name = 'Rubavu Depot' LIMIT 1), 'EQ-TRI-007', 'Warning triangles', 18, 10, 12500, 'in_stock')
ON DUPLICATE KEY UPDATE
    warehouse_id = VALUES(warehouse_id),
    item_name = VALUES(item_name),
    quantity = VALUES(quantity),
    minimum_level = VALUES(minimum_level),
    unit_cost = VALUES(unit_cost),
    status = VALUES(status);

INSERT INTO maintenance_orders (work_order_code, vehicle_id, service_name, provider_name, priority, estimated_cost, status, due_date, completed_at) VALUES
('MNT-0081', (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), 'Brake inspection', 'Kigali Auto Care', 'urgent', 245000, 'in_progress', '2026-09-18', NULL),
('MNT-0080', (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), 'Oil and filter', 'Fleet Workshop', 'normal', 95000, 'scheduled', '2026-09-22', NULL),
('MNT-0079', (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), 'Preventive service', 'Fleet Workshop', 'normal', 180000, 'open', '2026-10-12', NULL),
('MNT-0078', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), 'Transmission check', 'Kigali Auto Care', 'high', 320000, 'open', '2026-09-20', NULL)
ON DUPLICATE KEY UPDATE
    vehicle_id = VALUES(vehicle_id),
    service_name = VALUES(service_name),
    provider_name = VALUES(provider_name),
    priority = VALUES(priority),
    estimated_cost = VALUES(estimated_cost),
    status = VALUES(status),
    due_date = VALUES(due_date),
    completed_at = VALUES(completed_at);

INSERT INTO purchase_requests (request_code, supplier_id, requested_by, description, amount, status) VALUES
('PR-0091', (SELECT id FROM suppliers WHERE supplier_name = 'Kigali Auto Care' LIMIT 1), (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'Brake pads and filters', 680000, 'quotation'),
('PR-0090', (SELECT id FROM suppliers WHERE supplier_name = 'Secure Rwanda' LIMIT 1), (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'Safety equipment', 420000, 'approved'),
('PR-0089', (SELECT id FROM suppliers WHERE supplier_name = 'Lubricants Ltd' LIMIT 1), (SELECT id FROM users WHERE email = 'nadine@itec.rw'), 'Engine oil', 310000, 'received'),
('PR-0088', (SELECT id FROM suppliers WHERE supplier_name = 'Fleet Workshop' LIMIT 1), (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'Workshop tools', 255000, 'draft')
ON DUPLICATE KEY UPDATE
    supplier_id = VALUES(supplier_id),
    requested_by = VALUES(requested_by),
    description = VALUES(description),
    amount = VALUES(amount),
    status = VALUES(status);

UPDATE fuel_records
SET reference_code = 'FUE-0198'
WHERE reference_code IS NULL
  AND vehicle_id = (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D')
  AND station_name = 'SP Kigali'
  AND purchased_at = '2026-09-18 07:55:00';

UPDATE fuel_records
SET reference_code = 'FUE-0197'
WHERE reference_code IS NULL
  AND vehicle_id = (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K')
  AND station_name = 'Kobil Remera'
  AND purchased_at = '2026-09-18 06:25:00';

UPDATE fuel_records
SET reference_code = 'FUE-0196'
WHERE reference_code IS NULL
  AND vehicle_id = (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P')
  AND station_name = 'SP Nyabugogo'
  AND purchased_at = '2026-09-17 16:45:00';

INSERT INTO fuel_records (reference_code, vehicle_id, station_name, litres, unit_price, mileage, purchased_at, receipt_file)
SELECT 'FUE-0198', (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), 'SP Kigali', 82.00, 1540.00, 84210, '2026-09-18 07:55:00', 'receipts/fue-0198.pdf'
WHERE NOT EXISTS (
    SELECT 1 FROM fuel_records
    WHERE reference_code = 'FUE-0198'
);

INSERT INTO fuel_records (reference_code, vehicle_id, station_name, litres, unit_price, mileage, purchased_at, receipt_file)
SELECT 'FUE-0197', (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), 'Kobil Remera', 54.00, 1540.00, 62100, '2026-09-18 06:25:00', 'receipts/fue-0197.pdf'
WHERE NOT EXISTS (
    SELECT 1 FROM fuel_records
    WHERE reference_code = 'FUE-0197'
);

INSERT INTO fuel_records (reference_code, vehicle_id, station_name, litres, unit_price, mileage, purchased_at, receipt_file)
SELECT 'FUE-0196', (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), 'SP Nyabugogo', 68.00, 1540.00, 119800, '2026-09-17 16:45:00', 'receipts/fue-0196.pdf'
WHERE NOT EXISTS (
    SELECT 1 FROM fuel_records
    WHERE reference_code = 'FUE-0196'
);

UPDATE expenses
SET reference_code = 'EXP-0441', submitted_by = (SELECT id FROM users WHERE email = 'aline@itec.rw'), status = 'approved'
WHERE reference_code IS NULL AND notes LIKE 'EXP-0441%';

UPDATE expenses
SET reference_code = 'EXP-0440', submitted_by = (SELECT id FROM users WHERE email = 'samuel@itec.rw'), status = 'pending'
WHERE reference_code IS NULL AND notes LIKE 'EXP-0440%';

UPDATE expenses
SET reference_code = 'EXP-0439', submitted_by = (SELECT id FROM users WHERE email = 'eric@itec.rw'), status = 'approved'
WHERE reference_code IS NULL AND notes LIKE 'EXP-0439%';

UPDATE expenses
SET reference_code = 'EXP-0438', submitted_by = (SELECT id FROM users WHERE email = 'samuel@itec.rw'), status = 'approved'
WHERE reference_code IS NULL AND notes LIKE 'EXP-0438%';

INSERT INTO expenses (reference_code, trip_id, vehicle_id, category, amount, submitted_by, status, expense_date, notes)
SELECT 'EXP-0441', NULL, (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), 'Fuel', 126280.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'approved', '2026-09-18', 'Approved fuel posting for RAC 482D.'
WHERE NOT EXISTS (SELECT 1 FROM expenses WHERE reference_code = 'EXP-0441');

INSERT INTO expenses (reference_code, trip_id, vehicle_id, category, amount, submitted_by, status, expense_date, notes)
SELECT 'EXP-0440', (SELECT id FROM trips WHERE reference_code = 'TRP-0247'), NULL, 'Toll', 8000.00, (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'pending', '2026-09-18', 'Toll submitted for TRP-0247.'
WHERE NOT EXISTS (SELECT 1 FROM expenses WHERE reference_code = 'EXP-0440');

INSERT INTO expenses (reference_code, trip_id, vehicle_id, category, amount, submitted_by, status, expense_date, notes)
SELECT 'EXP-0439', NULL, (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), 'Repair', 245000.00, (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'approved', '2026-09-17', 'Brake repair work order support.'
WHERE NOT EXISTS (SELECT 1 FROM expenses WHERE reference_code = 'EXP-0439');

INSERT INTO expenses (reference_code, trip_id, vehicle_id, category, amount, submitted_by, status, expense_date, notes)
SELECT 'EXP-0438', (SELECT id FROM trips WHERE reference_code = 'TRP-0248'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), 'Allowance', 45000.00, (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'approved', '2026-09-18', 'Driver route allowance for TRP-0248.'
WHERE NOT EXISTS (SELECT 1 FROM expenses WHERE reference_code = 'EXP-0438');

INSERT INTO reports (report_name, period_label, owner_name, last_generated_at, format_label, action_label) VALUES
('Vehicle utilization', 'September 2026', 'Fleet manager', '2026-09-18 09:42:00', 'PDF', 'View'),
('Fuel consumption', 'September 2026', 'Finance', '2026-09-17 16:20:00', 'Excel', 'View'),
('Delivery performance', 'Q3 2026', 'Operations', '2026-09-15 10:00:00', 'PDF', 'View'),
('Maintenance cost', 'September 2026', 'Fleet manager', '2026-09-18 11:00:00', 'PDF', 'View'),
('Driver performance', 'September 2026', 'Fleet manager', '2026-09-18 11:05:00', 'Excel', 'View'),
('Inventory movement', 'September 2026', 'Warehouse', '2026-09-18 11:10:00', 'Excel', 'View'),
('Trip profitability', 'September 2026', 'Finance', '2026-09-18 11:15:00', 'PDF', 'View'),
('Open requests', 'September 2026', 'Operations', '2026-09-18 11:20:00', 'CSV', 'View'),
('Expense summary', 'September 2026', 'Finance', '2026-09-18 11:25:00', 'Excel', 'View')
ON DUPLICATE KEY UPDATE
    period_label = VALUES(period_label),
    owner_name = VALUES(owner_name),
    last_generated_at = VALUES(last_generated_at),
    format_label = VALUES(format_label),
    action_label = VALUES(action_label);

INSERT INTO notifications (notification_key, user_id, role_key, title, message, link_route, severity, is_read, created_at) VALUES
('NOTIF-SUPER-ADMIN-ACCESS', (SELECT id FROM users WHERE email = 'admin@itec.rw'), 'super_admin', 'User access review ready', 'All seeded roles are active. Review users and permissions before handing over the workspace.', 'users', 'info', 0, '2026-09-18 09:30:00'),
('NOTIF-LOGISTICS-REQUESTS', (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'logistics_manager', 'High priority transport requests', 'Two urgent or high priority transport requests need operations review today.', 'requests', 'warning', 0, '2026-09-18 09:35:00'),
('NOTIF-FLEET-SERVICE', (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'fleet_manager', 'Fleet service attention', 'Vehicles RAC 901P and RAB 407G need maintenance follow-up before dispatch.', 'maintenance', 'warning', 0, '2026-09-18 09:40:00'),
('NOTIF-WAREHOUSE-REORDER', (SELECT id FROM users WHERE email = 'nadine@itec.rw'), 'warehouse_manager', 'Inventory reorder needed', 'Engine oil and air filters are below minimum stock level and need replenishment.', 'warehouse', 'warning', 0, '2026-09-18 09:45:00'),
('NOTIF-DRIVER-TRIP', (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'driver', 'Assigned trip update', 'Trip TRP-0248 is in transit. Keep delivery status and proof records current.', 'trips', 'info', 0, '2026-09-18 09:50:00'),
('NOTIF-FINANCE-APPROVALS', (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'finance', 'Expense approvals pending', 'Fuel, toll and allowance records are ready for finance review.', 'expenses', 'warning', 0, '2026-09-18 09:55:00'),
('NOTIF-MANAGEMENT-REPORTS', (SELECT id FROM users WHERE email = 'jeanpierre@itec.rw'), 'management', 'Management reports updated', 'Vehicle utilization, delivery performance and expense summaries are ready.', 'reports', 'success', 0, '2026-09-18 10:00:00')
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    role_key = VALUES(role_key),
    title = VALUES(title),
    message = VALUES(message),
    link_route = VALUES(link_route),
    severity = VALUES(severity),
    is_read = VALUES(is_read);

INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata)
SELECT (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'seed.created', 'database', 'seed-2026-09-18', 'Initial logistics seed data loaded.', '{"source":"database/seed.sql"}'
WHERE NOT EXISTS (
    SELECT 1 FROM audit_logs
    WHERE action_name = 'seed.created'
      AND entity_type = 'database'
      AND entity_id = 'seed-2026-09-18'
);

-- Link the driver profile to the driver login so the driver dashboard can show personal data.
UPDATE drivers
SET user_id = (SELECT id FROM users WHERE email = 'samuel@itec.rw')
WHERE license_number = 'RWA-DL-0912'
  AND user_id IS NULL;


COMMIT;
