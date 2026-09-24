-- Optional demo history so dashboard charts have months of data.
-- Load with: php scripts/migrate.php --demo   (idempotent; safe to run repeatedly)
USE logistics_mvc;
SET NAMES utf8mb4;
START TRANSACTION;

INSERT INTO trips (reference_code, vehicle_id, driver_id, pickup_location, destination, departure_at, arrival_at, status) VALUES
('TRP-D001', (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), (SELECT id FROM drivers WHERE full_name = 'Aurore Kayitesi'), 'Kigali', 'Nyagatare', '2026-04-03 13:30:00', '2026-04-03 17:30:00', 'delivered'),
('TRP-D002', (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), (SELECT id FROM drivers WHERE full_name = 'Samuel Niyonzima'), 'Musanze', 'Kigali', '2026-04-04 09:15:00', '2026-04-04 13:15:00', 'delivered'),
('TRP-D003', (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), (SELECT id FROM drivers WHERE full_name = 'Patrick Rukundo'), 'Kigali', 'Huye', '2026-04-06 08:45:00', '2026-04-06 13:45:00', 'delivered'),
('TRP-D004', (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), (SELECT id FROM drivers WHERE full_name = 'David Habimana'), 'Kigali', 'Rusizi', '2026-04-09 08:30:00', '2026-04-09 11:30:00', 'delivered'),
('TRP-D005', (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), (SELECT id FROM drivers WHERE full_name = 'Samuel Niyonzima'), 'Kigali', 'Musanze', '2026-04-11 08:15:00', '2026-04-11 14:15:00', 'delivered'),
('TRP-D006', (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), (SELECT id FROM drivers WHERE full_name = 'David Habimana'), 'Kigali', 'Rusizi', '2026-04-13 08:00:00', '2026-04-13 11:00:00', 'delivered'),
('TRP-D007', (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), (SELECT id FROM drivers WHERE full_name = 'David Habimana'), 'Kigali', 'Musanze', '2026-04-14 09:30:00', '2026-04-14 12:30:00', 'delivered'),
('TRP-D008', (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), (SELECT id FROM drivers WHERE full_name = 'Eric Murenzi'), 'Kigali', 'Rusizi', '2026-04-17 06:15:00', NULL, 'cancelled'),
('TRP-D009', (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), (SELECT id FROM drivers WHERE full_name = 'Eric Murenzi'), 'Kigali', 'Nyagatare', '2026-04-18 09:15:00', '2026-04-18 15:15:00', 'delivered'),
('TRP-D010', (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), (SELECT id FROM drivers WHERE full_name = 'Nadine Tuyisenge'), 'Kigali', 'Nyagatare', '2026-04-24 13:15:00', '2026-04-24 16:15:00', 'delivered'),
('TRP-D011', (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), (SELECT id FROM drivers WHERE full_name = 'Samuel Niyonzima'), 'Huye', 'Kigali', '2026-04-26 07:45:00', '2026-04-26 11:45:00', 'delivered'),
('TRP-D012', (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), (SELECT id FROM drivers WHERE full_name = 'Jean Pierre Habimana'), 'Kigali', 'Rubavu', '2026-04-27 07:30:00', '2026-04-27 11:30:00', 'delivered'),
('TRP-D013', (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), (SELECT id FROM drivers WHERE full_name = 'David Habimana'), 'Kigali', 'Nyagatare', '2026-04-28 09:15:00', '2026-04-28 12:15:00', 'delivered'),
('TRP-D014', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), (SELECT id FROM drivers WHERE full_name = 'Nadine Tuyisenge'), 'Huye', 'Kigali', '2026-04-29 13:45:00', '2026-04-29 17:45:00', 'delivered'),
('TRP-D015', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), (SELECT id FROM drivers WHERE full_name = 'Eric Murenzi'), 'Kigali', 'Huye', '2026-05-01 09:15:00', '2026-05-01 12:15:00', 'delivered'),
('TRP-D016', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), (SELECT id FROM drivers WHERE full_name = 'Nadine Tuyisenge'), 'Kigali', 'Musanze', '2026-05-06 09:00:00', '2026-05-06 12:00:00', 'delivered'),
('TRP-D017', (SELECT id FROM vehicles WHERE plate_number = 'RAB 640C'), (SELECT id FROM drivers WHERE full_name = 'Samuel Niyonzima'), 'Kigali', 'Nyagatare', '2026-05-07 09:00:00', '2026-05-07 12:00:00', 'delivered'),
('TRP-D018', (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), (SELECT id FROM drivers WHERE full_name = 'David Habimana'), 'Musanze', 'Kigali', '2026-05-08 06:45:00', '2026-05-08 09:45:00', 'delivered'),
('TRP-D019', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), (SELECT id FROM drivers WHERE full_name = 'David Habimana'), 'Kigali', 'Rubavu', '2026-05-11 13:15:00', '2026-05-11 18:15:00', 'delivered'),
('TRP-D020', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), (SELECT id FROM drivers WHERE full_name = 'Claudine Mukamana'), 'Kigali', 'Nyagatare', '2026-05-14 07:30:00', '2026-05-14 11:30:00', 'delivered'),
('TRP-D021', (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), (SELECT id FROM drivers WHERE full_name = 'Claudine Mukamana'), 'Kigali', 'Huye', '2026-05-17 09:45:00', '2026-05-17 14:45:00', 'delivered'),
('TRP-D022', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), (SELECT id FROM drivers WHERE full_name = 'Patrick Rukundo'), 'Kigali', 'Huye', '2026-05-22 07:30:00', '2026-05-22 10:30:00', 'delivered'),
('TRP-D023', (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), (SELECT id FROM drivers WHERE full_name = 'Claudine Mukamana'), 'Kigali', 'Musanze', '2026-05-24 08:15:00', '2026-05-24 14:15:00', 'delivered'),
('TRP-D024', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), (SELECT id FROM drivers WHERE full_name = 'Claudine Mukamana'), 'Kigali', 'Rusizi', '2026-05-27 07:15:00', '2026-05-27 11:15:00', 'delivered'),
('TRP-D025', (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), (SELECT id FROM drivers WHERE full_name = 'Samuel Niyonzima'), 'Kigali', 'Rubavu', '2026-05-29 09:15:00', '2026-05-29 14:15:00', 'delivered'),
('TRP-D026', (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), (SELECT id FROM drivers WHERE full_name = 'Patrick Rukundo'), 'Kigali', 'Rubavu', '2026-06-03 13:45:00', '2026-06-03 19:45:00', 'delivered'),
('TRP-D027', (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), (SELECT id FROM drivers WHERE full_name = 'Marie Uwase'), 'Kigali', 'Nyagatare', '2026-06-12 13:30:00', '2026-06-12 16:30:00', 'delivered'),
('TRP-D028', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), (SELECT id FROM drivers WHERE full_name = 'Jean Pierre Habimana'), 'Kigali', 'Huye', '2026-06-17 08:30:00', '2026-06-17 11:30:00', 'delivered'),
('TRP-D029', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), (SELECT id FROM drivers WHERE full_name = 'Nadine Tuyisenge'), 'Musanze', 'Kigali', '2026-06-20 07:45:00', '2026-06-20 12:45:00', 'delivered'),
('TRP-D030', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), (SELECT id FROM drivers WHERE full_name = 'Claudine Mukamana'), 'Kigali', 'Nyagatare', '2026-06-22 09:30:00', '2026-06-22 12:30:00', 'delivered'),
('TRP-D031', (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), (SELECT id FROM drivers WHERE full_name = 'Emmanuel Safari'), 'Kigali', 'Huye', '2026-06-23 08:00:00', '2026-06-23 11:00:00', 'delivered'),
('TRP-D032', (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), (SELECT id FROM drivers WHERE full_name = 'Samuel Niyonzima'), 'Musanze', 'Kigali', '2026-06-24 07:00:00', '2026-06-24 12:00:00', 'delivered'),
('TRP-D033', (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), (SELECT id FROM drivers WHERE full_name = 'Nadine Tuyisenge'), 'Kigali', 'Rubavu', '2026-06-26 13:45:00', '2026-06-26 18:45:00', 'delivered'),
('TRP-D034', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), (SELECT id FROM drivers WHERE full_name = 'Aurore Kayitesi'), 'Huye', 'Kigali', '2026-06-27 07:00:00', '2026-06-27 11:00:00', 'delivered'),
('TRP-D035', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), (SELECT id FROM drivers WHERE full_name = 'Jean Pierre Habimana'), 'Kigali', 'Rubavu', '2026-06-30 08:15:00', '2026-06-30 13:15:00', 'delivered'),
('TRP-D036', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), (SELECT id FROM drivers WHERE full_name = 'Nadine Tuyisenge'), 'Kigali', 'Rubavu', '2026-07-06 06:30:00', '2026-07-06 09:30:00', 'delivered'),
('TRP-D037', (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), (SELECT id FROM drivers WHERE full_name = 'Claudine Mukamana'), 'Kigali', 'Musanze', '2026-07-11 13:45:00', NULL, 'cancelled'),
('TRP-D038', (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), (SELECT id FROM drivers WHERE full_name = 'Patrick Rukundo'), 'Huye', 'Kigali', '2026-07-12 09:45:00', '2026-07-12 14:45:00', 'delivered'),
('TRP-D039', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), (SELECT id FROM drivers WHERE full_name = 'Samuel Niyonzima'), 'Kigali', 'Musanze', '2026-07-13 07:45:00', NULL, 'cancelled'),
('TRP-D040', (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), (SELECT id FROM drivers WHERE full_name = 'Nadine Tuyisenge'), 'Kigali', 'Huye', '2026-07-14 08:45:00', '2026-07-14 12:45:00', 'delivered'),
('TRP-D041', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), (SELECT id FROM drivers WHERE full_name = 'Eric Murenzi'), 'Huye', 'Kigali', '2026-07-17 08:15:00', '2026-07-17 13:15:00', 'delivered'),
('TRP-D042', (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), (SELECT id FROM drivers WHERE full_name = 'Nadine Tuyisenge'), 'Kigali', 'Rubavu', '2026-07-18 09:00:00', '2026-07-18 14:00:00', 'delivered'),
('TRP-D043', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), (SELECT id FROM drivers WHERE full_name = 'Samuel Niyonzima'), 'Kigali', 'Rubavu', '2026-07-24 07:00:00', '2026-07-24 12:00:00', 'delivered'),
('TRP-D044', (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), (SELECT id FROM drivers WHERE full_name = 'Aurore Kayitesi'), 'Kigali', 'Rubavu', '2026-07-25 09:00:00', '2026-07-25 15:00:00', 'delivered'),
('TRP-D045', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), (SELECT id FROM drivers WHERE full_name = 'Claudine Mukamana'), 'Kigali', 'Nyagatare', '2026-07-27 06:00:00', '2026-07-27 11:00:00', 'delivered'),
('TRP-D046', (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), (SELECT id FROM drivers WHERE full_name = 'Marie Uwase'), 'Kigali', 'Nyagatare', '2026-08-01 06:45:00', '2026-08-01 09:45:00', 'delivered'),
('TRP-D047', (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), (SELECT id FROM drivers WHERE full_name = 'Eric Murenzi'), 'Kigali', 'Nyagatare', '2026-08-02 13:15:00', '2026-08-02 19:15:00', 'delivered'),
('TRP-D048', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), (SELECT id FROM drivers WHERE full_name = 'Claudine Mukamana'), 'Kigali', 'Rubavu', '2026-08-03 13:15:00', '2026-08-03 16:15:00', 'delivered'),
('TRP-D049', (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), (SELECT id FROM drivers WHERE full_name = 'Aurore Kayitesi'), 'Huye', 'Kigali', '2026-08-05 13:15:00', '2026-08-05 16:15:00', 'delivered'),
('TRP-D050', (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), (SELECT id FROM drivers WHERE full_name = 'Claudine Mukamana'), 'Kigali', 'Huye', '2026-08-06 06:00:00', '2026-08-06 10:00:00', 'delivered'),
('TRP-D051', (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), (SELECT id FROM drivers WHERE full_name = 'David Habimana'), 'Musanze', 'Kigali', '2026-08-07 09:00:00', '2026-08-07 12:00:00', 'delivered'),
('TRP-D052', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), (SELECT id FROM drivers WHERE full_name = 'Nadine Tuyisenge'), 'Kigali', 'Rubavu', '2026-08-08 06:45:00', '2026-08-08 09:45:00', 'delivered'),
('TRP-D053', (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), (SELECT id FROM drivers WHERE full_name = 'Jean Pierre Habimana'), 'Kigali', 'Huye', '2026-08-09 13:00:00', '2026-08-09 19:00:00', 'delivered'),
('TRP-D054', (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), (SELECT id FROM drivers WHERE full_name = 'Marie Uwase'), 'Kigali', 'Musanze', '2026-08-10 07:15:00', '2026-08-10 13:15:00', 'delivered'),
('TRP-D055', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), (SELECT id FROM drivers WHERE full_name = 'Patrick Rukundo'), 'Kigali', 'Rusizi', '2026-08-11 08:00:00', '2026-08-11 12:00:00', 'delivered'),
('TRP-D056', (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), (SELECT id FROM drivers WHERE full_name = 'Jean Pierre Habimana'), 'Kigali', 'Rubavu', '2026-08-12 08:15:00', '2026-08-12 11:15:00', 'delivered'),
('TRP-D057', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), (SELECT id FROM drivers WHERE full_name = 'Jean Pierre Habimana'), 'Huye', 'Kigali', '2026-08-13 06:15:00', '2026-08-13 12:15:00', 'delivered'),
('TRP-D058', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), (SELECT id FROM drivers WHERE full_name = 'Aurore Kayitesi'), 'Kigali', 'Rusizi', '2026-08-14 09:45:00', NULL, 'cancelled'),
('TRP-D059', (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), (SELECT id FROM drivers WHERE full_name = 'Nadine Tuyisenge'), 'Kigali', 'Rubavu', '2026-08-15 06:45:00', '2026-08-15 09:45:00', 'delivered'),
('TRP-D060', (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), (SELECT id FROM drivers WHERE full_name = 'Marie Uwase'), 'Kigali', 'Rusizi', '2026-08-17 08:45:00', '2026-08-17 12:45:00', 'delivered'),
('TRP-D061', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), (SELECT id FROM drivers WHERE full_name = 'Emmanuel Safari'), 'Kigali', 'Nyagatare', '2026-08-18 06:15:00', NULL, 'cancelled'),
('TRP-D062', (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), (SELECT id FROM drivers WHERE full_name = 'David Habimana'), 'Musanze', 'Kigali', '2026-08-19 13:30:00', '2026-08-19 16:30:00', 'delivered'),
('TRP-D063', (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), (SELECT id FROM drivers WHERE full_name = 'David Habimana'), 'Kigali', 'Rusizi', '2026-08-20 09:00:00', '2026-08-20 13:00:00', 'delivered'),
('TRP-D064', (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), (SELECT id FROM drivers WHERE full_name = 'Marie Uwase'), 'Kigali', 'Rusizi', '2026-08-21 08:15:00', '2026-08-21 14:15:00', 'delivered'),
('TRP-D065', (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), (SELECT id FROM drivers WHERE full_name = 'Marie Uwase'), 'Musanze', 'Kigali', '2026-08-22 08:00:00', '2026-08-22 13:00:00', 'delivered'),
('TRP-D066', (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), (SELECT id FROM drivers WHERE full_name = 'Claudine Mukamana'), 'Kigali', 'Musanze', '2026-08-25 06:30:00', '2026-08-25 11:30:00', 'delivered'),
('TRP-D067', (SELECT id FROM vehicles WHERE plate_number = 'RAB 640C'), (SELECT id FROM drivers WHERE full_name = 'Samuel Niyonzima'), 'Musanze', 'Kigali', '2026-08-26 13:00:00', NULL, 'cancelled'),
('TRP-D068', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), (SELECT id FROM drivers WHERE full_name = 'Aurore Kayitesi'), 'Kigali', 'Rubavu', '2026-08-27 06:00:00', '2026-08-27 11:00:00', 'delivered'),
('TRP-D069', (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), (SELECT id FROM drivers WHERE full_name = 'Samuel Niyonzima'), 'Kigali', 'Rubavu', '2026-08-28 09:30:00', '2026-08-28 13:30:00', 'delivered'),
('TRP-D070', (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), (SELECT id FROM drivers WHERE full_name = 'David Habimana'), 'Musanze', 'Kigali', '2026-08-29 09:15:00', '2026-08-29 12:15:00', 'delivered'),
('TRP-D071', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), (SELECT id FROM drivers WHERE full_name = 'Eric Murenzi'), 'Kigali', 'Nyagatare', '2026-08-30 07:30:00', '2026-08-30 13:30:00', 'delivered'),
('TRP-D072', (SELECT id FROM vehicles WHERE plate_number = 'RAB 640C'), (SELECT id FROM drivers WHERE full_name = 'Jean Pierre Habimana'), 'Kigali', 'Musanze', '2026-09-01 09:45:00', '2026-09-01 14:45:00', 'delivered'),
('TRP-D073', (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), (SELECT id FROM drivers WHERE full_name = 'Marie Uwase'), 'Kigali', 'Rusizi', '2026-09-02 07:30:00', '2026-09-02 13:30:00', 'delivered'),
('TRP-D074', (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), (SELECT id FROM drivers WHERE full_name = 'Nadine Tuyisenge'), 'Kigali', 'Musanze', '2026-09-04 07:00:00', '2026-09-04 11:00:00', 'delivered'),
('TRP-D075', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), (SELECT id FROM drivers WHERE full_name = 'Aurore Kayitesi'), 'Kigali', 'Musanze', '2026-09-05 09:30:00', '2026-09-05 15:30:00', 'delivered'),
('TRP-D076', (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), (SELECT id FROM drivers WHERE full_name = 'Aurore Kayitesi'), 'Kigali', 'Musanze', '2026-09-06 06:15:00', '2026-09-06 11:15:00', 'delivered'),
('TRP-D077', (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), (SELECT id FROM drivers WHERE full_name = 'Claudine Mukamana'), 'Kigali', 'Rubavu', '2026-09-07 08:15:00', '2026-09-07 11:15:00', 'delivered'),
('TRP-D078', (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), (SELECT id FROM drivers WHERE full_name = 'David Habimana'), 'Kigali', 'Rusizi', '2026-09-08 13:15:00', '2026-09-08 19:15:00', 'delivered'),
('TRP-D079', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), (SELECT id FROM drivers WHERE full_name = 'Aurore Kayitesi'), 'Kigali', 'Rubavu', '2026-09-12 13:30:00', '2026-09-12 17:30:00', 'delivered'),
('TRP-D080', (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), (SELECT id FROM drivers WHERE full_name = 'Claudine Mukamana'), 'Kigali', 'Huye', '2026-09-13 08:15:00', '2026-09-13 14:15:00', 'delivered'),
('TRP-D081', (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), (SELECT id FROM drivers WHERE full_name = 'Emmanuel Safari'), 'Kigali', 'Rubavu', '2026-09-14 06:15:00', NULL, 'loading')
ON DUPLICATE KEY UPDATE vehicle_id = VALUES(vehicle_id), driver_id = VALUES(driver_id), pickup_location = VALUES(pickup_location), destination = VALUES(destination), departure_at = VALUES(departure_at), arrival_at = VALUES(arrival_at), status = VALUES(status);

INSERT INTO deliveries (delivery_code, trip_id, recipient_name, destination, status, proof_file, recipient_signature, delivered_at) VALUES
('DEL-D001', (SELECT id FROM trips WHERE reference_code = 'TRP-D001'), 'Nyagatare branch', 'Nyagatare', 'delivered', NULL, NULL, '2026-04-03 17:30:00'),
('DEL-D002', (SELECT id FROM trips WHERE reference_code = 'TRP-D002'), 'Kigali branch', 'Kigali', 'delivered', NULL, NULL, '2026-04-04 13:15:00'),
('DEL-D003', (SELECT id FROM trips WHERE reference_code = 'TRP-D003'), 'Huye branch', 'Huye', 'failed', NULL, NULL, NULL),
('DEL-D004', (SELECT id FROM trips WHERE reference_code = 'TRP-D004'), 'Rusizi branch', 'Rusizi', 'failed', NULL, NULL, NULL),
('DEL-D005', (SELECT id FROM trips WHERE reference_code = 'TRP-D005'), 'Musanze branch', 'Musanze', 'delivered', NULL, NULL, '2026-04-11 14:15:00'),
('DEL-D006', (SELECT id FROM trips WHERE reference_code = 'TRP-D006'), 'Rusizi branch', 'Rusizi', 'delivered', NULL, NULL, '2026-04-13 11:00:00'),
('DEL-D007', (SELECT id FROM trips WHERE reference_code = 'TRP-D007'), 'Musanze branch', 'Musanze', 'delivered', NULL, NULL, '2026-04-14 12:30:00'),
('DEL-D009', (SELECT id FROM trips WHERE reference_code = 'TRP-D009'), 'Nyagatare branch', 'Nyagatare', 'delivered', NULL, NULL, '2026-04-18 15:15:00'),
('DEL-D010', (SELECT id FROM trips WHERE reference_code = 'TRP-D010'), 'Nyagatare branch', 'Nyagatare', 'delivered', NULL, NULL, '2026-04-24 16:15:00'),
('DEL-D011', (SELECT id FROM trips WHERE reference_code = 'TRP-D011'), 'Kigali branch', 'Kigali', 'delivered', NULL, NULL, '2026-04-26 11:45:00'),
('DEL-D012', (SELECT id FROM trips WHERE reference_code = 'TRP-D012'), 'Rubavu branch', 'Rubavu', 'failed', NULL, NULL, NULL),
('DEL-D013', (SELECT id FROM trips WHERE reference_code = 'TRP-D013'), 'Nyagatare branch', 'Nyagatare', 'delivered', NULL, NULL, '2026-04-28 12:15:00'),
('DEL-D014', (SELECT id FROM trips WHERE reference_code = 'TRP-D014'), 'Kigali branch', 'Kigali', 'delivered', NULL, NULL, '2026-04-29 17:45:00'),
('DEL-D015', (SELECT id FROM trips WHERE reference_code = 'TRP-D015'), 'Huye branch', 'Huye', 'delivered', NULL, NULL, '2026-05-01 12:15:00'),
('DEL-D016', (SELECT id FROM trips WHERE reference_code = 'TRP-D016'), 'Musanze branch', 'Musanze', 'delivered', NULL, NULL, '2026-05-06 12:00:00'),
('DEL-D017', (SELECT id FROM trips WHERE reference_code = 'TRP-D017'), 'Nyagatare branch', 'Nyagatare', 'delivered', NULL, NULL, '2026-05-07 12:00:00'),
('DEL-D018', (SELECT id FROM trips WHERE reference_code = 'TRP-D018'), 'Kigali branch', 'Kigali', 'delivered', NULL, NULL, '2026-05-08 09:45:00'),
('DEL-D019', (SELECT id FROM trips WHERE reference_code = 'TRP-D019'), 'Rubavu branch', 'Rubavu', 'delivered', NULL, NULL, '2026-05-11 18:15:00'),
('DEL-D020', (SELECT id FROM trips WHERE reference_code = 'TRP-D020'), 'Nyagatare branch', 'Nyagatare', 'delivered', NULL, NULL, '2026-05-14 11:30:00'),
('DEL-D021', (SELECT id FROM trips WHERE reference_code = 'TRP-D021'), 'Huye branch', 'Huye', 'delivered', NULL, NULL, '2026-05-17 14:45:00'),
('DEL-D022', (SELECT id FROM trips WHERE reference_code = 'TRP-D022'), 'Huye branch', 'Huye', 'delivered', NULL, NULL, '2026-05-22 10:30:00'),
('DEL-D023', (SELECT id FROM trips WHERE reference_code = 'TRP-D023'), 'Musanze branch', 'Musanze', 'delivered', NULL, NULL, '2026-05-24 14:15:00'),
('DEL-D024', (SELECT id FROM trips WHERE reference_code = 'TRP-D024'), 'Rusizi branch', 'Rusizi', 'delivered', NULL, NULL, '2026-05-27 11:15:00'),
('DEL-D025', (SELECT id FROM trips WHERE reference_code = 'TRP-D025'), 'Rubavu branch', 'Rubavu', 'failed', NULL, NULL, NULL),
('DEL-D026', (SELECT id FROM trips WHERE reference_code = 'TRP-D026'), 'Rubavu branch', 'Rubavu', 'delivered', NULL, NULL, '2026-06-03 19:45:00'),
('DEL-D027', (SELECT id FROM trips WHERE reference_code = 'TRP-D027'), 'Nyagatare branch', 'Nyagatare', 'delivered', NULL, NULL, '2026-06-12 16:30:00'),
('DEL-D028', (SELECT id FROM trips WHERE reference_code = 'TRP-D028'), 'Huye branch', 'Huye', 'delivered', NULL, NULL, '2026-06-17 11:30:00'),
('DEL-D029', (SELECT id FROM trips WHERE reference_code = 'TRP-D029'), 'Kigali branch', 'Kigali', 'delivered', NULL, NULL, '2026-06-20 12:45:00'),
('DEL-D030', (SELECT id FROM trips WHERE reference_code = 'TRP-D030'), 'Nyagatare branch', 'Nyagatare', 'delivered', NULL, NULL, '2026-06-22 12:30:00'),
('DEL-D031', (SELECT id FROM trips WHERE reference_code = 'TRP-D031'), 'Huye branch', 'Huye', 'delivered', NULL, NULL, '2026-06-23 11:00:00'),
('DEL-D032', (SELECT id FROM trips WHERE reference_code = 'TRP-D032'), 'Kigali branch', 'Kigali', 'failed', NULL, NULL, NULL),
('DEL-D033', (SELECT id FROM trips WHERE reference_code = 'TRP-D033'), 'Rubavu branch', 'Rubavu', 'delivered', NULL, NULL, '2026-06-26 18:45:00'),
('DEL-D034', (SELECT id FROM trips WHERE reference_code = 'TRP-D034'), 'Kigali branch', 'Kigali', 'delivered', NULL, NULL, '2026-06-27 11:00:00'),
('DEL-D035', (SELECT id FROM trips WHERE reference_code = 'TRP-D035'), 'Rubavu branch', 'Rubavu', 'delivered', NULL, NULL, '2026-06-30 13:15:00'),
('DEL-D036', (SELECT id FROM trips WHERE reference_code = 'TRP-D036'), 'Rubavu branch', 'Rubavu', 'delivered', NULL, NULL, '2026-07-06 09:30:00'),
('DEL-D038', (SELECT id FROM trips WHERE reference_code = 'TRP-D038'), 'Kigali branch', 'Kigali', 'delivered', NULL, NULL, '2026-07-12 14:45:00'),
('DEL-D040', (SELECT id FROM trips WHERE reference_code = 'TRP-D040'), 'Huye branch', 'Huye', 'failed', NULL, NULL, NULL),
('DEL-D041', (SELECT id FROM trips WHERE reference_code = 'TRP-D041'), 'Kigali branch', 'Kigali', 'delivered', NULL, NULL, '2026-07-17 13:15:00'),
('DEL-D042', (SELECT id FROM trips WHERE reference_code = 'TRP-D042'), 'Rubavu branch', 'Rubavu', 'delivered', NULL, NULL, '2026-07-18 14:00:00'),
('DEL-D043', (SELECT id FROM trips WHERE reference_code = 'TRP-D043'), 'Rubavu branch', 'Rubavu', 'delivered', NULL, NULL, '2026-07-24 12:00:00'),
('DEL-D044', (SELECT id FROM trips WHERE reference_code = 'TRP-D044'), 'Rubavu branch', 'Rubavu', 'delivered', NULL, NULL, '2026-07-25 15:00:00'),
('DEL-D045', (SELECT id FROM trips WHERE reference_code = 'TRP-D045'), 'Nyagatare branch', 'Nyagatare', 'delivered', NULL, NULL, '2026-07-27 11:00:00'),
('DEL-D046', (SELECT id FROM trips WHERE reference_code = 'TRP-D046'), 'Nyagatare branch', 'Nyagatare', 'delivered', NULL, NULL, '2026-08-01 09:45:00'),
('DEL-D047', (SELECT id FROM trips WHERE reference_code = 'TRP-D047'), 'Nyagatare branch', 'Nyagatare', 'delivered', NULL, NULL, '2026-08-02 19:15:00'),
('DEL-D048', (SELECT id FROM trips WHERE reference_code = 'TRP-D048'), 'Rubavu branch', 'Rubavu', 'delivered', NULL, NULL, '2026-08-03 16:15:00'),
('DEL-D049', (SELECT id FROM trips WHERE reference_code = 'TRP-D049'), 'Kigali branch', 'Kigali', 'delivered', NULL, NULL, '2026-08-05 16:15:00'),
('DEL-D050', (SELECT id FROM trips WHERE reference_code = 'TRP-D050'), 'Huye branch', 'Huye', 'delivered', NULL, NULL, '2026-08-06 10:00:00'),
('DEL-D051', (SELECT id FROM trips WHERE reference_code = 'TRP-D051'), 'Kigali branch', 'Kigali', 'delivered', NULL, NULL, '2026-08-07 12:00:00'),
('DEL-D052', (SELECT id FROM trips WHERE reference_code = 'TRP-D052'), 'Rubavu branch', 'Rubavu', 'delivered', NULL, NULL, '2026-08-08 09:45:00'),
('DEL-D053', (SELECT id FROM trips WHERE reference_code = 'TRP-D053'), 'Huye branch', 'Huye', 'delivered', NULL, NULL, '2026-08-09 19:00:00'),
('DEL-D054', (SELECT id FROM trips WHERE reference_code = 'TRP-D054'), 'Musanze branch', 'Musanze', 'delivered', NULL, NULL, '2026-08-10 13:15:00'),
('DEL-D055', (SELECT id FROM trips WHERE reference_code = 'TRP-D055'), 'Rusizi branch', 'Rusizi', 'delivered', NULL, NULL, '2026-08-11 12:00:00'),
('DEL-D056', (SELECT id FROM trips WHERE reference_code = 'TRP-D056'), 'Rubavu branch', 'Rubavu', 'delivered', NULL, NULL, '2026-08-12 11:15:00'),
('DEL-D057', (SELECT id FROM trips WHERE reference_code = 'TRP-D057'), 'Kigali branch', 'Kigali', 'delivered', NULL, NULL, '2026-08-13 12:15:00'),
('DEL-D059', (SELECT id FROM trips WHERE reference_code = 'TRP-D059'), 'Rubavu branch', 'Rubavu', 'delivered', NULL, NULL, '2026-08-15 09:45:00'),
('DEL-D060', (SELECT id FROM trips WHERE reference_code = 'TRP-D060'), 'Rusizi branch', 'Rusizi', 'delivered', NULL, NULL, '2026-08-17 12:45:00'),
('DEL-D062', (SELECT id FROM trips WHERE reference_code = 'TRP-D062'), 'Kigali branch', 'Kigali', 'delivered', NULL, NULL, '2026-08-19 16:30:00'),
('DEL-D063', (SELECT id FROM trips WHERE reference_code = 'TRP-D063'), 'Rusizi branch', 'Rusizi', 'delivered', NULL, NULL, '2026-08-20 13:00:00'),
('DEL-D064', (SELECT id FROM trips WHERE reference_code = 'TRP-D064'), 'Rusizi branch', 'Rusizi', 'delivered', NULL, NULL, '2026-08-21 14:15:00'),
('DEL-D065', (SELECT id FROM trips WHERE reference_code = 'TRP-D065'), 'Kigali branch', 'Kigali', 'delivered', NULL, NULL, '2026-08-22 13:00:00'),
('DEL-D066', (SELECT id FROM trips WHERE reference_code = 'TRP-D066'), 'Musanze branch', 'Musanze', 'delivered', NULL, NULL, '2026-08-25 11:30:00'),
('DEL-D068', (SELECT id FROM trips WHERE reference_code = 'TRP-D068'), 'Rubavu branch', 'Rubavu', 'delivered', NULL, NULL, '2026-08-27 11:00:00'),
('DEL-D069', (SELECT id FROM trips WHERE reference_code = 'TRP-D069'), 'Rubavu branch', 'Rubavu', 'delivered', NULL, NULL, '2026-08-28 13:30:00'),
('DEL-D070', (SELECT id FROM trips WHERE reference_code = 'TRP-D070'), 'Kigali branch', 'Kigali', 'delivered', NULL, NULL, '2026-08-29 12:15:00'),
('DEL-D071', (SELECT id FROM trips WHERE reference_code = 'TRP-D071'), 'Nyagatare branch', 'Nyagatare', 'delivered', NULL, NULL, '2026-08-30 13:30:00'),
('DEL-D072', (SELECT id FROM trips WHERE reference_code = 'TRP-D072'), 'Musanze branch', 'Musanze', 'delivered', NULL, NULL, '2026-09-01 14:45:00'),
('DEL-D073', (SELECT id FROM trips WHERE reference_code = 'TRP-D073'), 'Rusizi branch', 'Rusizi', 'delivered', NULL, NULL, '2026-09-02 13:30:00'),
('DEL-D074', (SELECT id FROM trips WHERE reference_code = 'TRP-D074'), 'Musanze branch', 'Musanze', 'delivered', NULL, NULL, '2026-09-04 11:00:00'),
('DEL-D075', (SELECT id FROM trips WHERE reference_code = 'TRP-D075'), 'Musanze branch', 'Musanze', 'delivered', NULL, NULL, '2026-09-05 15:30:00'),
('DEL-D076', (SELECT id FROM trips WHERE reference_code = 'TRP-D076'), 'Musanze branch', 'Musanze', 'delivered', NULL, NULL, '2026-09-06 11:15:00'),
('DEL-D077', (SELECT id FROM trips WHERE reference_code = 'TRP-D077'), 'Rubavu branch', 'Rubavu', 'delivered', NULL, NULL, '2026-09-07 11:15:00'),
('DEL-D078', (SELECT id FROM trips WHERE reference_code = 'TRP-D078'), 'Rusizi branch', 'Rusizi', 'delivered', NULL, NULL, '2026-09-08 19:15:00'),
('DEL-D079', (SELECT id FROM trips WHERE reference_code = 'TRP-D079'), 'Rubavu branch', 'Rubavu', 'delivered', NULL, NULL, '2026-09-12 17:30:00'),
('DEL-D080', (SELECT id FROM trips WHERE reference_code = 'TRP-D080'), 'Huye branch', 'Huye', 'delivered', NULL, NULL, '2026-09-13 14:15:00'),
('DEL-D081', (SELECT id FROM trips WHERE reference_code = 'TRP-D081'), 'Rubavu branch', 'Rubavu', 'loading', NULL, NULL, NULL)
ON DUPLICATE KEY UPDATE trip_id = VALUES(trip_id), recipient_name = VALUES(recipient_name), destination = VALUES(destination), status = VALUES(status), delivered_at = VALUES(delivered_at);

INSERT INTO expenses (reference_code, trip_id, vehicle_id, category, amount, submitted_by, status, expense_date, notes) VALUES
('EXP-D001', NULL, (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), 'Repair', 180500.00, (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'approved', '2026-04-04', 'Demo history'),
('EXP-D002', (SELECT id FROM trips WHERE reference_code = 'TRP-D058'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), 'Fuel', 67500.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'approved', '2026-04-05', 'Demo history'),
('EXP-D003', (SELECT id FROM trips WHERE reference_code = 'TRP-D005'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 640C'), 'Fuel', 127000.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-04-08', 'Demo history'),
('EXP-D004', (SELECT id FROM trips WHERE reference_code = 'TRP-D016'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), 'Fuel', 70000.00, (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'approved', '2026-04-12', 'Demo history'),
('EXP-D005', (SELECT id FROM trips WHERE reference_code = 'TRP-D056'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), 'Allowance', 49500.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'pending', '2026-04-13', 'Demo history'),
('EXP-D006', NULL, (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), 'Repair', 194000.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-04-15', 'Demo history'),
('EXP-D007', (SELECT id FROM trips WHERE reference_code = 'TRP-D052'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), 'Toll', 17500.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-04-16', 'Demo history'),
('EXP-D008', (SELECT id FROM trips WHERE reference_code = 'TRP-D009'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), 'Fuel', 67500.00, (SELECT id FROM users WHERE email = 'nadine@itec.rw'), 'approved', '2026-04-17', 'Demo history'),
('EXP-D009', (SELECT id FROM trips WHERE reference_code = 'TRP-D041'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 640C'), 'Fuel', 138500.00, (SELECT id FROM users WHERE email = 'nadine@itec.rw'), 'approved', '2026-04-18', 'Demo history'),
('EXP-D010', (SELECT id FROM trips WHERE reference_code = 'TRP-D030'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), 'Allowance', 34000.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-04-19', 'Demo history'),
('EXP-D011', (SELECT id FROM trips WHERE reference_code = 'TRP-D039'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), 'Toll', 5000.00, (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'pending', '2026-04-26', 'Demo history'),
('EXP-D012', (SELECT id FROM trips WHERE reference_code = 'TRP-D077'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), 'Parking', 10000.00, (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'approved', '2026-04-27', 'Demo history'),
('EXP-D013', (SELECT id FROM trips WHERE reference_code = 'TRP-D005'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), 'Allowance', 45500.00, (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'approved', '2026-04-28', 'Demo history'),
('EXP-D014', (SELECT id FROM trips WHERE reference_code = 'TRP-D034'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), 'Toll', 6500.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'rejected', '2026-04-30', 'Demo history'),
('EXP-D015', (SELECT id FROM trips WHERE reference_code = 'TRP-D023'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), 'Toll', 16000.00, (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'rejected', '2026-05-01', 'Demo history'),
('EXP-D016', NULL, (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), 'Repair', 252500.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'approved', '2026-05-02', 'Demo history'),
('EXP-D017', (SELECT id FROM trips WHERE reference_code = 'TRP-D034'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), 'Fuel', 94000.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-05-04', 'Demo history'),
('EXP-D018', (SELECT id FROM trips WHERE reference_code = 'TRP-D075'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), 'Fuel', 79500.00, (SELECT id FROM users WHERE email = 'nadine@itec.rw'), 'approved', '2026-05-05', 'Demo history'),
('EXP-D019', (SELECT id FROM trips WHERE reference_code = 'TRP-D030'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), 'Fuel', 91000.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-05-06', 'Demo history'),
('EXP-D020', (SELECT id FROM trips WHERE reference_code = 'TRP-D058'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), 'Toll', 18000.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'approved', '2026-05-08', 'Demo history'),
('EXP-D021', (SELECT id FROM trips WHERE reference_code = 'TRP-D075'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), 'Fuel', 136500.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'rejected', '2026-05-10', 'Demo history'),
('EXP-D022', (SELECT id FROM trips WHERE reference_code = 'TRP-D001'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), 'Parking', 10000.00, (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'approved', '2026-05-11', 'Demo history'),
('EXP-D023', (SELECT id FROM trips WHERE reference_code = 'TRP-D027'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 640C'), 'Fuel', 103500.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'approved', '2026-05-13', 'Demo history'),
('EXP-D024', (SELECT id FROM trips WHERE reference_code = 'TRP-D048'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), 'Fuel', 101500.00, (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'approved', '2026-05-16', 'Demo history'),
('EXP-D025', (SELECT id FROM trips WHERE reference_code = 'TRP-D009'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), 'Fuel', 123000.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'approved', '2026-05-17', 'Demo history'),
('EXP-D026', NULL, (SELECT id FROM vehicles WHERE plate_number = 'RAB 640C'), 'Repair', 247000.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-05-20', 'Demo history'),
('EXP-D027', (SELECT id FROM trips WHERE reference_code = 'TRP-D054'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), 'Fuel', 132500.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'pending', '2026-05-23', 'Demo history'),
('EXP-D028', (SELECT id FROM trips WHERE reference_code = 'TRP-D001'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), 'Fuel', 111500.00, (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'approved', '2026-05-26', 'Demo history'),
('EXP-D029', (SELECT id FROM trips WHERE reference_code = 'TRP-D017'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), 'Parking', 10000.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'approved', '2026-05-29', 'Demo history'),
('EXP-D030', NULL, (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), 'Repair', 174500.00, (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'approved', '2026-06-02', 'Demo history'),
('EXP-D031', (SELECT id FROM trips WHERE reference_code = 'TRP-D014'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), 'Fuel', 81500.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'pending', '2026-06-03', 'Demo history'),
('EXP-D032', (SELECT id FROM trips WHERE reference_code = 'TRP-D062'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), 'Allowance', 32500.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'rejected', '2026-06-07', 'Demo history'),
('EXP-D033', (SELECT id FROM trips WHERE reference_code = 'TRP-D029'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), 'Allowance', 40000.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-06-10', 'Demo history'),
('EXP-D034', (SELECT id FROM trips WHERE reference_code = 'TRP-D052'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), 'Toll', 14000.00, (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'approved', '2026-06-12', 'Demo history'),
('EXP-D035', (SELECT id FROM trips WHERE reference_code = 'TRP-D025'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), 'Fuel', 91500.00, (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'rejected', '2026-06-13', 'Demo history'),
('EXP-D036', (SELECT id FROM trips WHERE reference_code = 'TRP-D081'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 640C'), 'Toll', 12000.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-06-17', 'Demo history'),
('EXP-D037', (SELECT id FROM trips WHERE reference_code = 'TRP-D058'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), 'Fuel', 109500.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-06-18', 'Demo history'),
('EXP-D038', (SELECT id FROM trips WHERE reference_code = 'TRP-D058'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), 'Fuel', 122500.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-06-19', 'Demo history'),
('EXP-D039', (SELECT id FROM trips WHERE reference_code = 'TRP-D012'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), 'Fuel', 105500.00, (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'approved', '2026-06-22', 'Demo history'),
('EXP-D040', NULL, (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), 'Repair', 101000.00, (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'pending', '2026-06-24', 'Demo history'),
('EXP-D041', (SELECT id FROM trips WHERE reference_code = 'TRP-D018'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), 'Allowance', 54000.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'approved', '2026-06-25', 'Demo history'),
('EXP-D042', NULL, (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), 'Insurance', 212500.00, (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'approved', '2026-06-29', 'Demo history'),
('EXP-D043', (SELECT id FROM trips WHERE reference_code = 'TRP-D079'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 640C'), 'Fuel', 92000.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-06-30', 'Demo history'),
('EXP-D044', (SELECT id FROM trips WHERE reference_code = 'TRP-D076'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 640C'), 'Toll', 19500.00, (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'approved', '2026-07-01', 'Demo history'),
('EXP-D045', (SELECT id FROM trips WHERE reference_code = 'TRP-D036'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), 'Fuel', 83000.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-07-03', 'Demo history'),
('EXP-D046', (SELECT id FROM trips WHERE reference_code = 'TRP-D007'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), 'Allowance', 37000.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-07-04', 'Demo history'),
('EXP-D047', NULL, (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), 'Insurance', 230500.00, (SELECT id FROM users WHERE email = 'nadine@itec.rw'), 'pending', '2026-07-08', 'Demo history'),
('EXP-D048', (SELECT id FROM trips WHERE reference_code = 'TRP-D011'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), 'Fuel', 78500.00, (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'approved', '2026-07-09', 'Demo history'),
('EXP-D049', (SELECT id FROM trips WHERE reference_code = 'TRP-D067'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 640C'), 'Allowance', 33000.00, (SELECT id FROM users WHERE email = 'nadine@itec.rw'), 'approved', '2026-07-10', 'Demo history'),
('EXP-D050', (SELECT id FROM trips WHERE reference_code = 'TRP-D038'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), 'Fuel', 64000.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-07-15', 'Demo history'),
('EXP-D051', (SELECT id FROM trips WHERE reference_code = 'TRP-D030'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), 'Fuel', 66000.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'approved', '2026-07-16', 'Demo history'),
('EXP-D052', (SELECT id FROM trips WHERE reference_code = 'TRP-D067'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), 'Fuel', 105000.00, (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'approved', '2026-07-17', 'Demo history'),
('EXP-D053', NULL, (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), 'Repair', 230500.00, (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'approved', '2026-07-18', 'Demo history'),
('EXP-D054', (SELECT id FROM trips WHERE reference_code = 'TRP-D058'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), 'Fuel', 91000.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'approved', '2026-07-20', 'Demo history'),
('EXP-D055', (SELECT id FROM trips WHERE reference_code = 'TRP-D072'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), 'Fuel', 61000.00, (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'approved', '2026-07-24', 'Demo history'),
('EXP-D056', (SELECT id FROM trips WHERE reference_code = 'TRP-D004'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), 'Parking', 3500.00, (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'approved', '2026-07-29', 'Demo history'),
('EXP-D057', (SELECT id FROM trips WHERE reference_code = 'TRP-D071'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), 'Fuel', 73000.00, (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'approved', '2026-07-30', 'Demo history'),
('EXP-D058', (SELECT id FROM trips WHERE reference_code = 'TRP-D054'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), 'Toll', 15000.00, (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'approved', '2026-07-31', 'Demo history'),
('EXP-D059', NULL, (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), 'Repair', 265000.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'approved', '2026-08-02', 'Demo history'),
('EXP-D060', (SELECT id FROM trips WHERE reference_code = 'TRP-D058'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), 'Toll', 19500.00, (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'approved', '2026-08-03', 'Demo history'),
('EXP-D061', NULL, (SELECT id FROM vehicles WHERE plate_number = 'RAB 640C'), 'Repair', 111500.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'approved', '2026-08-05', 'Demo history'),
('EXP-D062', NULL, (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), 'Repair', 191500.00, (SELECT id FROM users WHERE email = 'nadine@itec.rw'), 'approved', '2026-08-06', 'Demo history'),
('EXP-D063', NULL, (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), 'Insurance', 177500.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'approved', '2026-08-07', 'Demo history'),
('EXP-D064', (SELECT id FROM trips WHERE reference_code = 'TRP-D042'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), 'Parking', 6000.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'rejected', '2026-08-08', 'Demo history'),
('EXP-D065', (SELECT id FROM trips WHERE reference_code = 'TRP-D068'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), 'Fuel', 128500.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'approved', '2026-08-09', 'Demo history'),
('EXP-D066', (SELECT id FROM trips WHERE reference_code = 'TRP-D073'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), 'Fuel', 139500.00, (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'approved', '2026-08-13', 'Demo history'),
('EXP-D067', (SELECT id FROM trips WHERE reference_code = 'TRP-D045'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), 'Fuel', 139500.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'pending', '2026-08-14', 'Demo history'),
('EXP-D068', (SELECT id FROM trips WHERE reference_code = 'TRP-D006'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), 'Fuel', 65000.00, (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'approved', '2026-08-15', 'Demo history'),
('EXP-D069', NULL, (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), 'Insurance', 218000.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'pending', '2026-08-17', 'Demo history'),
('EXP-D070', (SELECT id FROM trips WHERE reference_code = 'TRP-D012'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 640C'), 'Fuel', 74000.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-08-18', 'Demo history'),
('EXP-D071', (SELECT id FROM trips WHERE reference_code = 'TRP-D044'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), 'Fuel', 86000.00, (SELECT id FROM users WHERE email = 'nadine@itec.rw'), 'approved', '2026-08-19', 'Demo history'),
('EXP-D072', (SELECT id FROM trips WHERE reference_code = 'TRP-D048'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), 'Fuel', 96000.00, (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'approved', '2026-08-20', 'Demo history'),
('EXP-D073', (SELECT id FROM trips WHERE reference_code = 'TRP-D013'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), 'Toll', 11500.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-08-24', 'Demo history'),
('EXP-D074', (SELECT id FROM trips WHERE reference_code = 'TRP-D037'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), 'Parking', 4000.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-08-27', 'Demo history'),
('EXP-D075', (SELECT id FROM trips WHERE reference_code = 'TRP-D063'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), 'Fuel', 66500.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-08-28', 'Demo history'),
('EXP-D076', (SELECT id FROM trips WHERE reference_code = 'TRP-D015'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), 'Allowance', 44500.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-09-05', 'Demo history'),
('EXP-D077', (SELECT id FROM trips WHERE reference_code = 'TRP-D055'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), 'Toll', 11000.00, (SELECT id FROM users WHERE email = 'nadine@itec.rw'), 'pending', '2026-09-10', 'Demo history'),
('EXP-D078', (SELECT id FROM trips WHERE reference_code = 'TRP-D049'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), 'Fuel', 129500.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'pending', '2026-09-11', 'Demo history'),
('EXP-D079', NULL, (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), 'Repair', 256000.00, (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'pending', '2026-09-12', 'Demo history'),
('EXP-D080', (SELECT id FROM trips WHERE reference_code = 'TRP-D022'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), 'Fuel', 117500.00, (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'approved', '2026-09-13', 'Demo history'),
('EXP-D081', (SELECT id FROM trips WHERE reference_code = 'TRP-D031'), (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), 'Fuel', 102500.00, (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'approved', '2026-09-15', 'Demo history'),
('EXP-D082', (SELECT id FROM trips WHERE reference_code = 'TRP-D020'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), 'Allowance', 69500.00, (SELECT id FROM users WHERE email = 'nadine@itec.rw'), 'pending', '2026-09-16', 'Demo history'),
('EXP-D083', (SELECT id FROM trips WHERE reference_code = 'TRP-D014'), (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), 'Fuel', 84000.00, (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'approved', '2026-09-18', 'Demo history')
ON DUPLICATE KEY UPDATE trip_id = VALUES(trip_id), vehicle_id = VALUES(vehicle_id), category = VALUES(category), amount = VALUES(amount), submitted_by = VALUES(submitted_by), status = VALUES(status), expense_date = VALUES(expense_date);

INSERT INTO fuel_records (reference_code, vehicle_id, station_name, litres, unit_price, mileage, purchased_at, receipt_file) VALUES
('FUE-D001', (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), 'SP Kigali', 38.00, 1560.00, 58848, '2026-04-04 16:25:00', NULL),
('FUE-D002', (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), 'SP Kigali', 85.00, 1560.00, 54027, '2026-04-09 07:25:00', NULL),
('FUE-D003', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), 'Engen Huye', 65.00, 1490.00, 59478, '2026-04-19 12:25:00', NULL),
('FUE-D004', (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), 'Kobil Remera', 54.00, 1560.00, 95774, '2026-04-21 16:25:00', NULL),
('FUE-D005', (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), 'Engen Huye', 91.00, 1490.00, 78548, '2026-04-27 16:05:00', NULL),
('FUE-D006', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), 'Kobil Remera', 71.00, 1520.00, 65261, '2026-04-28 12:05:00', NULL),
('FUE-D007', (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), 'SP Nyabugogo', 71.00, 1490.00, 75412, '2026-05-01 12:25:00', NULL),
('FUE-D008', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), 'SP Nyabugogo', 78.00, 1490.00, 65801, '2026-05-05 06:25:00', NULL),
('FUE-D009', (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), 'SP Kigali', 64.00, 1490.00, 95955, '2026-05-06 16:40:00', NULL),
('FUE-D010', (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), 'Kobil Remera', 89.00, 1560.00, 59153, '2026-05-09 16:25:00', NULL),
('FUE-D011', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), 'Kobil Remera', 68.00, 1490.00, 60025, '2026-05-10 07:05:00', NULL),
('FUE-D012', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), 'Engen Huye', 60.00, 1520.00, 66283, '2026-05-16 07:25:00', NULL),
('FUE-D013', (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), 'SP Kigali', 80.00, 1590.00, 75781, '2026-05-26 12:05:00', NULL),
('FUE-D014', (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), 'SP Nyabugogo', 78.00, 1520.00, 54343, '2026-05-29 06:40:00', NULL),
('FUE-D015', (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), 'SP Nyabugogo', 76.00, 1540.00, 89485, '2026-05-30 06:40:00', NULL),
('FUE-D016', (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), 'SP Nyabugogo', 88.00, 1560.00, 59398, '2026-05-31 07:05:00', NULL),
('FUE-D017', (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), 'SP Nyabugogo', 50.00, 1490.00, 54893, '2026-06-03 16:40:00', NULL),
('FUE-D018', (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), 'SP Kigali', 73.00, 1560.00, 90014, '2026-06-04 06:25:00', NULL),
('FUE-D019', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), 'SP Kigali', 48.00, 1590.00, 60451, '2026-06-12 12:25:00', NULL),
('FUE-D020', (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), 'Engen Huye', 81.00, 1560.00, 76122, '2026-06-15 06:05:00', NULL),
('FUE-D021', (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), 'SP Kigali', 70.00, 1540.00, 55066, '2026-06-18 16:25:00', NULL),
('FUE-D022', (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), 'Kobil Remera', 59.00, 1560.00, 59915, '2026-06-20 06:40:00', NULL),
('FUE-D023', (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), 'SP Nyabugogo', 73.00, 1560.00, 96280, '2026-06-25 06:25:00', NULL),
('FUE-D024', (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), 'SP Nyabugogo', 93.00, 1540.00, 76478, '2026-06-26 12:05:00', NULL),
('FUE-D025', (SELECT id FROM vehicles WHERE plate_number = 'RAB 640C'), 'SP Kigali', 88.00, 1590.00, 78715, '2026-06-30 06:25:00', NULL),
('FUE-D026', (SELECT id FROM vehicles WHERE plate_number = 'RAB 640C'), 'SP Kigali', 50.00, 1490.00, 78920, '2026-07-04 16:40:00', NULL),
('FUE-D027', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), 'Engen Huye', 77.00, 1590.00, 66711, '2026-07-06 07:40:00', NULL),
('FUE-D028', (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), 'Kobil Remera', 44.00, 1560.00, 65491, '2026-07-10 07:05:00', NULL),
('FUE-D029', (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), 'SP Nyabugogo', 73.00, 1520.00, 65829, '2026-07-12 12:25:00', NULL),
('FUE-D030', (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), 'Engen Huye', 74.00, 1490.00, 66142, '2026-07-13 06:25:00', NULL),
('FUE-D031', (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), 'Engen Huye', 66.00, 1560.00, 90560, '2026-07-15 06:05:00', NULL),
('FUE-D032', (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), 'SP Kigali', 79.00, 1490.00, 96710, '2026-07-20 16:05:00', NULL),
('FUE-D033', (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), 'Kobil Remera', 93.00, 1490.00, 91149, '2026-07-24 06:05:00', NULL),
('FUE-D034', (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), 'Kobil Remera', 41.00, 1560.00, 55340, '2026-07-26 12:40:00', NULL),
('FUE-D035', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), 'Engen Huye', 80.00, 1560.00, 67224, '2026-07-30 12:05:00', NULL),
('FUE-D036', (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), 'SP Kigali', 62.00, 1590.00, 66625, '2026-08-01 12:25:00', NULL),
('FUE-D037', (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), 'SP Nyabugogo', 61.00, 1490.00, 76939, '2026-08-03 16:25:00', NULL),
('FUE-D038', (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), 'Engen Huye', 68.00, 1520.00, 91484, '2026-08-05 16:25:00', NULL),
('FUE-D039', (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), 'SP Nyabugogo', 93.00, 1590.00, 67093, '2026-08-09 06:05:00', NULL),
('FUE-D040', (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), 'Engen Huye', 81.00, 1560.00, 96986, '2026-08-11 16:40:00', NULL),
('FUE-D041', (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), 'SP Nyabugogo', 54.00, 1490.00, 77234, '2026-08-13 12:25:00', NULL),
('FUE-D042', (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), 'Kobil Remera', 55.00, 1520.00, 67390, '2026-08-14 16:25:00', NULL),
('FUE-D043', (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), 'Engen Huye', 83.00, 1560.00, 67886, '2026-08-22 07:25:00', NULL),
('FUE-D044', (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), 'SP Kigali', 72.00, 1590.00, 97371, '2026-08-24 12:05:00', NULL),
('FUE-D045', (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), 'SP Nyabugogo', 68.00, 1540.00, 55756, '2026-08-25 07:05:00', NULL),
('FUE-D046', (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), 'SP Nyabugogo', 74.00, 1540.00, 91726, '2026-08-26 12:25:00', NULL),
('FUE-D047', (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), 'SP Nyabugogo', 78.00, 1490.00, 77575, '2026-08-29 16:05:00', NULL),
('FUE-D048', (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), 'SP Nyabugogo', 71.00, 1540.00, 55921, '2026-08-30 06:05:00', NULL),
('FUE-D049', (SELECT id FROM vehicles WHERE plate_number = 'RAC 901P'), 'Kobil Remera', 54.00, 1590.00, 56319, '2026-08-31 12:25:00', NULL),
('FUE-D050', (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), 'Kobil Remera', 54.00, 1590.00, 78117, '2026-09-01 06:25:00', NULL),
('FUE-D051', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), 'SP Kigali', 41.00, 1490.00, 60794, '2026-09-02 06:40:00', NULL),
('FUE-D052', (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), 'SP Kigali', 80.00, 1520.00, 78987, '2026-09-12 16:05:00', NULL),
('FUE-D053', (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), 'Kobil Remera', 40.00, 1520.00, 60434, '2026-09-14 12:25:00', NULL),
('FUE-D054', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), 'SP Nyabugogo', 88.00, 1490.00, 67388, '2026-09-15 16:05:00', NULL),
('FUE-D055', (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), 'Kobil Remera', 81.00, 1490.00, 79523, '2026-09-16 12:40:00', NULL)
ON DUPLICATE KEY UPDATE vehicle_id = VALUES(vehicle_id), station_name = VALUES(station_name), litres = VALUES(litres), unit_price = VALUES(unit_price), mileage = VALUES(mileage), purchased_at = VALUES(purchased_at);

INSERT INTO maintenance_orders (work_order_code, vehicle_id, service_name, provider_name, priority, estimated_cost, status, due_date, completed_at) VALUES
('MNT-D001', (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), 'Battery replacement', 'Fleet Workshop', 'low', 125000, 'completed', '2026-04-08', '2026-04-10 15:00:00'),
('MNT-D002', (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), 'Tyre replacement', 'Kigali Auto Care', 'high', 340000, 'completed', '2026-04-19', '2026-04-22 15:00:00'),
('MNT-D003', (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), 'Brake service', 'Kigali Auto Care', 'normal', 220000, 'completed', '2026-05-01', '2026-05-01 15:00:00'),
('MNT-D004', (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), 'Brake service', 'Kigali Auto Care', 'normal', 275000, 'completed', '2026-05-14', '2026-05-15 15:00:00'),
('MNT-D005', (SELECT id FROM vehicles WHERE plate_number = 'RAB 407G'), 'Battery replacement', 'Fleet Workshop', 'low', 115000, 'completed', '2026-05-21', '2026-05-21 15:00:00'),
('MNT-D006', (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), 'Oil and filter', 'Fleet Workshop', 'high', 65000, 'completed', '2026-06-02', '2026-06-04 15:00:00'),
('MNT-D007', (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), 'Preventive service', 'Fleet Workshop', 'normal', 135000, 'completed', '2026-06-11', '2026-06-13 15:00:00'),
('MNT-D008', (SELECT id FROM vehicles WHERE plate_number = 'RAC 774F'), 'Oil and filter', 'Fleet Workshop', 'normal', 70000, 'completed', '2026-06-20', '2026-06-23 15:00:00'),
('MNT-D009', (SELECT id FROM vehicles WHERE plate_number = 'RAC 482D'), 'Tyre replacement', 'Kigali Auto Care', 'normal', 350000, 'completed', '2026-06-28', '2026-06-29 15:00:00'),
('MNT-D010', (SELECT id FROM vehicles WHERE plate_number = 'RAB 640C'), 'Tyre replacement', 'Kigali Auto Care', 'high', 400000, 'completed', '2026-07-04', '2026-07-05 15:00:00'),
('MNT-D011', (SELECT id FROM vehicles WHERE plate_number = 'RAC 208L'), 'Preventive service', 'Fleet Workshop', 'low', 135000, 'completed', '2026-07-14', '2026-07-17 15:00:00'),
('MNT-D012', (SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'), 'Brake service', 'Kigali Auto Care', 'normal', 230000, 'completed', '2026-07-21', '2026-07-24 15:00:00'),
('MNT-D013', (SELECT id FROM vehicles WHERE plate_number = 'RAB 519T'), 'Oil and filter', 'Fleet Workshop', 'high', 80000, 'completed', '2026-07-31', '2026-08-03 15:00:00'),
('MNT-D014', (SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'), 'Brake service', 'Kigali Auto Care', 'low', 185000, 'completed', '2026-08-10', '2026-08-12 15:00:00'),
('MNT-D015', (SELECT id FROM vehicles WHERE plate_number = 'RAB 640C'), 'Brake service', 'Kigali Auto Care', 'normal', 155000, 'completed', '2026-08-22', '2026-08-22 15:00:00'),
('MNT-D016', (SELECT id FROM vehicles WHERE plate_number = 'RAC 863N'), 'Battery replacement', 'Fleet Workshop', 'normal', 115000, 'completed', '2026-09-04', '2026-09-07 15:00:00')
ON DUPLICATE KEY UPDATE vehicle_id = VALUES(vehicle_id), service_name = VALUES(service_name), provider_name = VALUES(provider_name), priority = VALUES(priority), estimated_cost = VALUES(estimated_cost), status = VALUES(status), due_date = VALUES(due_date), completed_at = VALUES(completed_at);

INSERT INTO purchase_requests (request_code, supplier_id, requested_by, description, amount, status) VALUES
('PR-D001', (SELECT id FROM suppliers WHERE supplier_name = 'Kigali Auto Care' LIMIT 1), (SELECT id FROM users WHERE email = 'nadine@itec.rw'), 'Tyres set', 780000, 'received'),
('PR-D002', (SELECT id FROM suppliers WHERE supplier_name = 'Fleet Workshop' LIMIT 1), (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'Service kits', 350000, 'received'),
('PR-D003', (SELECT id FROM suppliers WHERE supplier_name = 'Secure Rwanda' LIMIT 1), (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'Reflective jackets', 190000, 'approved'),
('PR-D004', (SELECT id FROM suppliers WHERE supplier_name = 'Lubricants Ltd' LIMIT 1), (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'Lubricants bulk order', 520000, 'received'),
('PR-D005', (SELECT id FROM suppliers WHERE supplier_name = 'Secure Rwanda' LIMIT 1), (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'GPS trackers', 940000, 'quotation'),
('PR-D006', (SELECT id FROM suppliers WHERE supplier_name = 'Fleet Workshop' LIMIT 1), (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'Workshop consumables', 210000, 'draft'),
('PR-D007', (SELECT id FROM suppliers WHERE supplier_name = 'Secure Rwanda' LIMIT 1), (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'Fire extinguishers', 260000, 'approved'),
('PR-D008', (SELECT id FROM suppliers WHERE supplier_name = 'Fleet Workshop' LIMIT 1), (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'Hydraulic jack', 175000, 'rejected')
ON DUPLICATE KEY UPDATE supplier_id = VALUES(supplier_id), requested_by = VALUES(requested_by), description = VALUES(description), amount = VALUES(amount), status = VALUES(status);

INSERT INTO inventory_items (warehouse_id, sku, item_name, quantity, minimum_level, unit_cost, status) VALUES
((SELECT id FROM warehouses WHERE warehouse_name = 'Kigali Central Warehouse' LIMIT 1), 'DEMO-TYR-001', 'Truck tyre 295/80', 6, 12, 185000, 'reorder'),
((SELECT id FROM warehouses WHERE warehouse_name = 'Huye Depot' LIMIT 1), 'DEMO-BAT-002', 'Truck battery 12V', 14, 6, 96000, 'in_stock'),
((SELECT id FROM warehouses WHERE warehouse_name = 'Musanze Depot' LIMIT 1), 'DEMO-FIL-003', 'Fuel filter', 4, 15, 17500, 'reorder'),
((SELECT id FROM warehouses WHERE warehouse_name = 'Rubavu Depot' LIMIT 1), 'DEMO-LMP-004', 'Headlight bulbs', 72, 30, 3500, 'in_stock'),
((SELECT id FROM warehouses WHERE warehouse_name = 'Kigali Central Warehouse' LIMIT 1), 'DEMO-WPR-005', 'Wiper blades', 9, 10, 8200, 'reorder')
ON DUPLICATE KEY UPDATE warehouse_id = VALUES(warehouse_id), item_name = VALUES(item_name), quantity = VALUES(quantity), minimum_level = VALUES(minimum_level), unit_cost = VALUES(unit_cost), status = VALUES(status);

INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'record.created', 'vehicles', 'demo-001', NULL, NULL, '2026-09-10 09:53:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'vehicles' AND entity_id = 'demo-001');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'record.created', 'vehicles', 'demo-002', NULL, NULL, '2026-09-13 09:12:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'vehicles' AND entity_id = 'demo-002');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'record.created', 'vehicles', 'demo-003', NULL, NULL, '2026-08-30 16:46:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'vehicles' AND entity_id = 'demo-003');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'nadine@itec.rw'), 'record.created', 'trips', 'demo-004', NULL, NULL, '2026-09-03 10:08:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'trips' AND entity_id = 'demo-004');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'record.status_changed', 'trips', 'demo-005', NULL, NULL, '2026-08-30 11:12:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'trips' AND entity_id = 'demo-005');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'record.status_changed', 'trips', 'demo-006', NULL, NULL, '2026-09-18 15:26:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'trips' AND entity_id = 'demo-006');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'record.status_changed', 'trips', 'demo-007', NULL, NULL, '2026-08-23 12:21:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'trips' AND entity_id = 'demo-007');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'record.created', 'trips', 'demo-008', NULL, NULL, '2026-09-09 07:26:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'trips' AND entity_id = 'demo-008');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'record.created', 'trips', 'demo-009', NULL, NULL, '2026-08-25 17:17:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'trips' AND entity_id = 'demo-009');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'record.status_changed', 'expenses', 'demo-010', NULL, NULL, '2026-09-11 12:02:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'expenses' AND entity_id = 'demo-010');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'nadine@itec.rw'), 'record.status_changed', 'expenses', 'demo-011', NULL, NULL, '2026-09-13 16:54:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'expenses' AND entity_id = 'demo-011');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'nadine@itec.rw'), 'record.status_changed', 'expenses', 'demo-012', NULL, NULL, '2026-09-18 14:33:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'expenses' AND entity_id = 'demo-012');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'record.updated', 'expenses', 'demo-013', NULL, NULL, '2026-09-16 10:52:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'expenses' AND entity_id = 'demo-013');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'nadine@itec.rw'), 'record.status_changed', 'expenses', 'demo-014', NULL, NULL, '2026-08-23 13:36:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'expenses' AND entity_id = 'demo-014');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'record.updated', 'fuel', 'demo-015', NULL, NULL, '2026-08-25 08:46:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'fuel' AND entity_id = 'demo-015');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'record.status_changed', 'fuel', 'demo-016', NULL, NULL, '2026-09-03 07:33:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'fuel' AND entity_id = 'demo-016');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'record.created', 'fuel', 'demo-017', NULL, NULL, '2026-08-24 07:15:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'fuel' AND entity_id = 'demo-017');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'record.status_changed', 'deliveries', 'demo-018', NULL, NULL, '2026-09-16 09:10:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'deliveries' AND entity_id = 'demo-018');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'nadine@itec.rw'), 'record.updated', 'deliveries', 'demo-019', NULL, NULL, '2026-09-15 15:52:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'deliveries' AND entity_id = 'demo-019');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'record.created', 'deliveries', 'demo-020', NULL, NULL, '2026-09-18 10:16:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'deliveries' AND entity_id = 'demo-020');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'samuel@itec.rw'), 'record.status_changed', 'deliveries', 'demo-021', NULL, NULL, '2026-09-18 16:29:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'deliveries' AND entity_id = 'demo-021');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'record.status_changed', 'maintenance', 'demo-022', NULL, NULL, '2026-09-02 14:06:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'maintenance' AND entity_id = 'demo-022');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'record.status_changed', 'maintenance', 'demo-023', NULL, NULL, '2026-09-07 09:02:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'maintenance' AND entity_id = 'demo-023');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'aline@itec.rw'), 'record.updated', 'requests', 'demo-024', NULL, NULL, '2026-09-10 14:37:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'requests' AND entity_id = 'demo-024');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'nadine@itec.rw'), 'record.created', 'requests', 'demo-025', NULL, NULL, '2026-09-02 08:07:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'requests' AND entity_id = 'demo-025');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'record.status_changed', 'requests', 'demo-026', NULL, NULL, '2026-09-06 16:14:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'requests' AND entity_id = 'demo-026');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'eric@itec.rw'), 'record.created', 'users', 'demo-027', NULL, NULL, '2026-08-22 17:36:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'users' AND entity_id = 'demo-027');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'record.created', 'procurement', 'demo-028', NULL, NULL, '2026-09-04 07:40:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'procurement' AND entity_id = 'demo-028');
INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata, created_at)
SELECT (SELECT id FROM users WHERE email = 'emmanuel@itec.rw'), 'record.status_changed', 'procurement', 'demo-029', NULL, NULL, '2026-09-06 16:33:00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE entity_type = 'procurement' AND entity_id = 'demo-029');

COMMIT;
