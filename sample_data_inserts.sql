-- Example data for the LMS: two client companies, one shipment each.
--   1. Akagera Coffee Exporters Ltd    - coffee, Kigali -> Dar es Salaam via Rusumo (RWF, delivered, paid)
--   2. Virunga Cement Distributors Ltd - cement, Kigali -> Goma via La Corniche (USD, in transit, part paid)
--
-- Import this into the EXISTING database (phpMyAdmin > database > Import).
-- It only adds rows; it creates no tables and changes no existing rows.
-- It uses existing users 2, 3, 4, 5, 6, 44 and 157, border posts 2 and 5, and the chart of accounts.
-- Tables are filled parents first, so it works with foreign key checks on.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET NAMES utf8mb4;
START TRANSACTION;

-- warehouses
INSERT INTO `warehouses` (`id`, `warehouse_code`, `warehouse_name`, `location`, `manager_id`, `phone`, `capacity_m3`, `is_cold_chain`, `status`, `deleted_at`) VALUES
(1, 'WH-2026-0001', 'ITEC Kigali Main Warehouse', 'Masaka, Kicukiro, Kigali', 5, '+250 782 110 554', 4500.00, 0, 'active', NULL),
(2, 'WH-2026-0002', 'ITEC Rubavu Cross-border Depot', 'Rubavu, Western Province', 5, '+250 782 110 555', 1200.00, 0, 'active', NULL);

-- suppliers
INSERT INTO `suppliers` (`id`, `supplier_code`, `supplier_name`, `category`, `contact_name`, `phone`, `email`, `tin_number`, `address`, `payment_terms_days`, `rating`, `status`, `deleted_at`, `created_at`) VALUES
(1, 'SUP-2026-0001', 'Kigali Truck Parts & Service Ltd', 'Spare parts', 'Olivier Habineza', '+250 788 552 671', 'sales@kigalitruckparts.rw', '103998877', 'KK 15 Rd, Gikondo, Kigali', 30, 4, 'active', NULL, '2026-09-01 10:00:00'),
(2, 'SUP-2026-0002', 'Great Lakes Clearing & Forwarding Ltd', 'Services', 'Joseph Kamanzi', '+250 785 118 204', 'ops@greatlakescf.rw', '104556612', 'Rusumo Border, Kirehe', 15, 5, 'active', NULL, '2026-09-01 10:10:00');

-- customers
INSERT INTO `customers` (`id`, `customer_code`, `customer_name`, `customer_type`, `contact_name`, `phone`, `email`, `address`, `district`, `tin_number`, `payment_terms_days`, `credit_limit`, `currency`, `status`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'CUS-2026-0001', 'Akagera Coffee Exporters Ltd', 'corporate', 'Diane Uwase', '+250 788 314 552', 'logistics@akageracoffee.rw', 'KG 11 Ave, Kimihurura', 'Gasabo', '102345678', 30, 25000000.00, 'RWF', 'active', 'Green coffee exporter. Ships to Dar es Salaam port.', NULL, '2026-09-05 09:10:00'),
(2, 'CUS-2026-0002', 'Virunga Cement Distributors Ltd', 'corporate', 'Patrick Nshimiyimana', '+250 783 220 918', 'orders@virungacement.rw', 'Gikondo Industrial Zone', 'Kicukiro', '108765432', 30, 20000.00, 'USD', 'active', 'Cement distributor supplying Goma, DR Congo. Billed in USD.', NULL, '2026-09-20 10:30:00');

-- vehicles
INSERT INTO `vehicles` (`id`, `plate_number`, `vehicle_type`, `make`, `model`, `manufacture_year`, `chassis_number`, `capacity_kg`, `capacity_m3`, `fuel_type`, `ownership`, `acquired_on`, `has_cooling_unit`, `assigned_driver_id`, `mileage`, `status`, `next_service_date`, `notes`, `created_at`, `deleted_at`) VALUES
(1, 'RAD 482 K', 'Trailer', 'Scania', 'R450', 2021, 'YS2R4X20005123456', 30000.00, 90.000, 'diesel', 'owned', '2021-06-15', 0, 1, 184250, 'available', '2026-12-15', 'Tractor and flatbed trailer for the central corridor.', '2026-09-05 08:00:00', NULL),
(2, 'RAE 219 C', 'Delivery truck', 'Mercedes-Benz', 'Actros 2645', 2022, 'WDB9634031L987654', 28000.00, 70.000, 'diesel', 'leased', '2022-03-01', 0, 2, 96410, 'on_trip', '2026-11-05', 'Rigid truck used on the Rubavu / Goma run.', '2026-09-05 08:05:00', NULL);

-- drivers
INSERT INTO `drivers` (`id`, `user_id`, `full_name`, `national_id`, `date_of_birth`, `phone`, `address`, `license_number`, `license_class`, `license_expiry`, `emergency_contact`, `emergency_phone`, `hired_on`, `status`, `notes`, `created_at`, `deleted_at`) VALUES
(1, 3, 'Samuel Niyonzima', '1198780012345678', '1987-04-12', '+250 788 632 119', 'Kicukiro, Kigali', 'RW-DL-0456789', 'C/D', '2028-03-31', 'Grace Uwimana', '+250 788 441 220', '2019-02-01', 'available', 'Long-haul driver on the central corridor (Dar es Salaam).', '2026-09-05 08:00:00', NULL),
(2, 44, 'Claude Bizimana', '1199080023456789', '1990-08-23', '+250 788 401 003', 'Rubavu, Western Province', 'RW-DL-0561234', 'C/D', '2026-12-15', 'Alice Mukeshimana', '+250 782 330 114', '2021-05-10', 'on_trip', 'Covers the Rubavu / Goma run. Licence due for renewal in December.', '2026-09-05 08:05:00', NULL);

-- currency_rates
INSERT INTO `currency_rates` (`id`, `currency_code`, `effective_from`, `rate`, `source`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'USD', '2026-09-01', 1450.00000000, 'BNR', 'BNR reference rate, September 2026', NULL, '2026-09-01 08:00:00'),
(2, 'TZS', '2026-09-01', 0.53700000, 'BNR', 'Used for fuel and charges paid in Tanzania', NULL, '2026-09-01 08:00:00');

-- reports
INSERT INTO `reports` (`id`, `report_key`, `report_name`, `description`, `period_label`, `owner_name`, `last_generated_at`, `format_label`, `action_label`, `deleted_at`) VALUES
(1, 'trip_profitability', 'Trip profitability', 'Invoiced revenue, net of VAT, against trip cost (expenses plus fuel) for each trip, and the resulting margin.', 'Monthly', 'Emmanuel Safari', '2026-09-30 08:00:00', 'CSV', 'View', NULL),
(2, 'fuel_consumption', 'Fuel consumption', 'Litres and money spent per vehicle, with the number of fill-ups and the average price paid.', 'Monthly', 'Eric Murenzi', '2026-09-30 08:05:00', 'CSV', 'View', NULL);

-- rate_cards
INSERT INTO `rate_cards` (`id`, `rate_code`, `customer_id`, `origin_warehouse_id`, `destination_warehouse_id`, `origin`, `destination`, `vehicle_type`, `full_load_kg`, `full_load_price`, `rate_type`, `rate_amount`, `currency`, `minimum_charge`, `effective_from`, `effective_to`, `status`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'RATE-2026-0001', 1, 1, NULL, 'Kigali', 'Dar es Salaam', 'Trailer', 30000.00, 8500000.00, 'per_kg', 300.00, 'RWF', 3000000.00, '2026-01-01', '2026-12-31', 'active', 'Contract rate for Akagera Coffee Exporters, central corridor.', NULL, '2026-09-05 09:30:00'),
(2, 'RATE-2026-0002', 2, NULL, NULL, 'Kigali', 'Goma', 'Delivery truck', 28000.00, 2400.00, 'per_kg', 0.09, 'USD', 800.00, '2026-09-01', '2027-08-31', 'active', 'Cross-border rate for Virunga Cement Distributors, billed in USD.', NULL, '2026-09-20 10:45:00');

-- inventory_items
INSERT INTO `inventory_items` (`id`, `warehouse_id`, `sku`, `item_name`, `category`, `unit_of_measure`, `quantity`, `minimum_level`, `reorder_quantity`, `unit_cost`, `batch_number`, `expiry_date`, `storage_temperature`, `status`, `deleted_at`, `created_at`) VALUES
(1, 1, 'SKU-2026-0001', 'Engine oil 15W-40 (20 L drum)', 'Spare parts', 'Unit', 16.00, 6.00, 12.00, 95000.00, 'SH-2609-15W40', '2029-06-30', NULL, 'in_stock', NULL, '2026-09-02 09:00:00'),
(2, 1, 'SKU-2026-0002', 'Tarpaulin cover 8 m x 10 m', 'Equipment', 'Unit', 3.00, 5.00, 10.00, 85000.00, NULL, NULL, NULL, 'reorder', NULL, '2026-09-02 09:05:00');

-- transport_requests
INSERT INTO `transport_requests` (`id`, `reference_code`, `requester_id`, `customer_id`, `requested_by_contact`, `trip_id`, `pickup_location`, `origin_warehouse_id`, `destination`, `destination_warehouse_id`, `stock_item_id`, `stock_quantity`, `cargo_description`, `weight_kg`, `rate_card_id`, `quoted_amount`, `quote_basis`, `currency`, `quoted_at`, `accepted_at`, `accepted_by`, `packages_count`, `required_date`, `priority`, `status`, `approved_by`, `approved_at`, `rejection_reason`, `notes`, `created_at`, `deleted_at`) VALUES
(1, 'REQ-2026-0001', 2, 1, 'Diane Uwase', NULL, 'ITEC Kigali Main Warehouse, Masaka', 1, 'Dar es Salaam Port, Tanzania', NULL, NULL, NULL, '300 bags green coffee (60 kg each)', 18000.00, 1, 5400000.00, '18,000 kg x RWF 300/kg (RATE-2026-0001)', 'RWF', '2026-09-08 10:15:00', '2026-09-08 15:40:00', 'Diane Uwase', 300, '2026-09-15', 'high', 'assigned', 2, '2026-09-09 08:30:00', NULL, 'Vessel cut-off at Dar es Salaam on 15 September.', '2026-09-08 09:30:00', NULL),
(2, 'REQ-2026-0002', 157, 2, 'Patrick Nshimiyimana', NULL, 'Virunga Cement yard, Gikondo, Kigali', NULL, 'Goma, DR Congo', NULL, NULL, NULL, '500 bags cement CEM II 42.5N (50 kg each)', 25000.00, 2, 2250.00, '25,000 kg x USD 0.09/kg (RATE-2026-0002)', 'USD', '2026-09-26 11:00:00', '2026-09-26 16:20:00', 'Patrick Nshimiyimana', 500, '2026-09-30', 'normal', 'assigned', 157, '2026-09-27 09:00:00', NULL, 'Customer pays USD 1,000 in advance, balance on delivery.', '2026-09-26 10:20:00', NULL);

-- trips
INSERT INTO `trips` (`id`, `reference_code`, `request_id`, `customer_id`, `trip_type`, `vehicle_id`, `driver_id`, `pickup_location`, `destination`, `planned_departure_at`, `planned_arrival_at`, `departure_at`, `arrival_at`, `status`, `cargo_summary`, `dispatched_at`, `completed_at`, `created_by`, `notes`, `created_at`, `deleted_at`) VALUES
(1, 'TRP-2026-0001', 1, 1, 'delivery', 1, 1, 'ITEC Kigali Main Warehouse, Masaka', 'Dar es Salaam Port, Tanzania', '2026-09-10 06:00:00', '2026-09-13 18:00:00', '2026-09-10 06:40:00', '2026-09-13 16:20:00', 'delivered', '300 bags green coffee, 18,000 kg', '2026-09-10 06:40:00', '2026-09-13 17:05:00', 2, 'Via Rusumo. Delivered ahead of vessel cut-off.', '2026-09-09 09:00:00', NULL),
(2, 'TRP-2026-0002', 2, 2, 'delivery', 2, 2, 'Virunga Cement yard, Gikondo, Kigali', 'Goma, DR Congo', '2026-09-29 07:00:00', '2026-09-30 14:00:00', '2026-09-29 07:25:00', NULL, 'in_transit', '500 bags cement, 25,000 kg', '2026-09-29 07:25:00', NULL, 157, 'Via Rubavu depot and La Corniche border.', '2026-09-28 11:00:00', NULL);

-- link each request to the trip that now exists
UPDATE `transport_requests` SET `trip_id` = 1 WHERE `id` = 1;
UPDATE `transport_requests` SET `trip_id` = 2 WHERE `id` = 2;

-- trip_stops
INSERT INTO `trip_stops` (`id`, `trip_id`, `stop_sequence`, `stop_type`, `warehouse_id`, `location_name`, `contact_name`, `contact_phone`, `planned_arrival_at`, `actual_arrival_at`, `status`, `notes`, `created_at`) VALUES
(1, 1, 1, 'checkpoint', NULL, 'Rusumo One Stop Border Post', 'Joseph Kamanzi', '+250 785 118 204', '2026-09-10 12:00:00', '2026-09-10 12:50:00', 'completed', 'Export declaration lodged and cleared the same day.', '2026-09-09 09:00:00'),
(2, 2, 1, 'waypoint', 2, 'ITEC Rubavu Cross-border Depot', 'Nadine Tuyisenge', '+250 782 110 554', '2026-09-29 12:00:00', '2026-09-29 12:40:00', 'completed', 'Driver rest and document check before the border.', '2026-09-28 11:00:00');

-- shipments
INSERT INTO `shipments` (`id`, `shipment_code`, `request_id`, `trip_id`, `customer_id`, `consignee_name`, `consignee_phone`, `origin`, `origin_warehouse_id`, `destination`, `destination_warehouse_id`, `stock_item_id`, `stock_quantity`, `stock_issued_at`, `cargo_type`, `cargo_description`, `packages_count`, `weight_kg`, `volume_m3`, `temperature_min_c`, `temperature_max_c`, `is_hazardous`, `declared_value`, `currency`, `rate_card_id`, `quoted_amount`, `quote_basis`, `special_instructions`, `status`, `booked_at`, `deleted_at`, `created_at`) VALUES
(1, 'SHP-2026-0001', 1, 1, 1, 'Bahari Freight Agents Ltd', '+255 754 210 330', 'Kigali', 1, 'Dar es Salaam Port, Tanzania', NULL, NULL, NULL, NULL, 'general', 'Green Arabica coffee, fully washed, grade A1 - 300 jute bags x 60 kg', 300, 18000.00, 32.400, NULL, NULL, 0, 156600000.00, 'RWF', 1, 5400000.00, '18,000 kg x RWF 300/kg (RATE-2026-0001)', 'Keep dry. Cover with tarpaulin. Do not stack other cargo on top.', 'delivered', '2026-09-09 09:00:00', NULL, '2026-09-09 09:00:00'),
(2, 'SHP-2026-0002', 2, 2, 2, 'Virunga Cement Distributors - Goma depot', '+243 997 120 455', 'Gikondo, Kigali', NULL, 'Goma, DR Congo', NULL, NULL, NULL, NULL, 'general', 'Portland cement CEM II 42.5N - 500 bags x 50 kg', 500, 25000.00, 20.000, NULL, NULL, 0, 5750.00, 'USD', 2, 2250.00, '25,000 kg x USD 0.09/kg (RATE-2026-0002)', 'Keep away from rain. Count bags at loading and at drop.', 'in_transit', '2026-09-28 11:00:00', NULL, '2026-09-28 11:00:00');

-- deliveries
INSERT INTO `deliveries` (`id`, `delivery_code`, `trip_id`, `shipment_id`, `recipient_name`, `recipient_phone`, `destination`, `destination_warehouse_id`, `planned_at`, `dropped_at`, `status`, `attempt_number`, `failure_reason`, `failure_notes`, `rescheduled_at`, `proof_file`, `recipient_signature`, `delivered_at`, `delivered_by`, `collected_by`, `collected_id_no`, `deleted_at`, `created_at`) VALUES
(1, 'DEL-2026-0001', 1, 1, 'Bahari Freight Agents Ltd', '+255 754 210 330', 'Dar es Salaam Port, Tanzania', NULL, '2026-09-13 18:00:00', '2026-09-13 16:45:00', 'delivered', 1, 'none', NULL, NULL, NULL, NULL, '2026-09-13 17:05:00', 3, 'Hamisi Mwinyi', 'TZ-NIDA-19850612-00123', NULL, '2026-09-09 09:00:00'),
(2, 'DEL-2026-0002', 2, 2, 'Virunga Cement Distributors - Goma depot', '+243 997 120 455', 'Goma, DR Congo', NULL, '2026-09-30 14:00:00', NULL, 'in_transit', 1, 'none', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-28 11:00:00');

-- warehouse_cargo
INSERT INTO `warehouse_cargo` (`id`, `movement_code`, `warehouse_id`, `shipment_id`, `direction`, `reason`, `packages`, `weight_kg`, `trip_id`, `delivery_id`, `moved_at`, `performed_by`, `notes`, `created_at`) VALUES
(1, 'CGO-2026-0001', 1, 1, 'in', 'received', 300, 18000.00, NULL, NULL, '2026-09-09 15:00:00', 5, 'Coffee received from Akagera washing station, 300 bags counted.', '2026-09-09 15:00:00'),
(2, 'CGO-2026-0002', 1, 1, 'out', 'loaded', 300, 18000.00, 1, 1, '2026-09-10 05:50:00', 5, 'Loaded on RAD 482 K for TRP-2026-0001.', '2026-09-10 05:50:00');

-- border_crossings
INSERT INTO `border_crossings` (`id`, `reference`, `trip_id`, `shipment_id`, `vehicle_id`, `driver_id`, `border_post_id`, `direction`, `clearing_agent_id`, `declaration_no`, `transit_bond_no`, `seal_no`, `weighbridge_kg`, `arrived_at`, `lodged_at`, `cleared_at`, `departed_at`, `status`, `hold_reason`, `charges_total`, `currency`, `exchange_rate`, `base_amount`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'BDR-2026-0001', 1, 1, 1, 1, 2, 'export', 2, 'C 2026 RW 145872', 'T1-RSM-2026-08812', 'RWS-0045521', 33150.00, '2026-09-10 12:50:00', '2026-09-10 13:20:00', '2026-09-10 16:45:00', '2026-09-10 17:10:00', 'departed', NULL, 150000.00, 'RWF', 1.00000000, 150000.00, 'Coffee export for Akagera Coffee Exporters. Cleared same day.', NULL, '2026-09-10 12:50:00'),
(2, 'BDR-2026-0002', 2, 2, 2, 2, 5, 'export', 2, 'C 2026 RW 151230', NULL, 'RWS-0046108', 37200.00, '2026-09-29 15:30:00', '2026-09-29 16:10:00', NULL, NULL, 'lodged', NULL, 120.00, 'USD', 1450.00000000, 174000.00, 'Cement for Virunga Cement Distributors. Waiting for DGDA release.', NULL, '2026-09-29 15:30:00');

-- border_charges
INSERT INTO `border_charges` (`id`, `crossing_id`, `charge_type`, `description`, `receipt_no`, `amount`) VALUES
(1, 1, 'Clearing agent fee', 'Lodging export declaration for 300 bags coffee at Rusumo', 'GLCF-RSM-2026-0412', 150000.00),
(2, 2, 'Escort fee', 'Customs escort from Rubavu to Goma depot', 'DGDA-GOM-2026-7781', 120.00);

-- border_documents
INSERT INTO `border_documents` (`id`, `crossing_id`, `document_type`, `document_no`, `issued_on`, `expires_on`, `is_received`, `document_file`, `notes`) VALUES
(1, 1, 'Customs declaration', 'C 2026 RW 145872', '2026-09-10', NULL, 1, NULL, 'Stamped by RRA at Rusumo'),
(2, 2, 'Commercial invoice', 'VCD-EXP-2026-0093', '2026-09-27', NULL, 0, NULL, 'Original awaited from Virunga Cement Distributors');

-- fuel_records
INSERT INTO `fuel_records` (`id`, `reference_code`, `vehicle_id`, `station_name`, `fuel_type`, `litres`, `is_full_tank`, `unit_price`, `currency`, `exchange_rate`, `base_amount`, `mileage`, `previous_mileage`, `driver_id`, `trip_id`, `purchased_at`, `receipt_file`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'FUE-2026-0001', 1, 'Puma Energy Nyakanazi, Tanzania', 'diesel', 400.00, 1, 2950.00, 'TZS', 0.53700000, 633660.00, 183110, 182690, 1, 1, '2026-09-11 09:20:00', NULL, 'Refuelled in Tanzania on the way to Dar es Salaam.', NULL, '2026-09-11 09:30:00'),
(2, 'FUE-2026-0002', 2, 'SP Gisozi, Kigali', 'diesel', 300.00, 1, 1560.00, 'RWF', 1.00000000, 468000.00, 96180, 95540, 2, 2, '2026-09-29 06:50:00', NULL, 'Full tank before the Goma trip.', NULL, '2026-09-29 07:00:00');

-- expenses
INSERT INTO `expenses` (`id`, `reference_code`, `trip_id`, `vehicle_id`, `category`, `amount`, `currency`, `exchange_rate`, `base_amount`, `submitted_by`, `status`, `approved_by`, `approved_at`, `rejection_reason`, `payment_method`, `receipt_file`, `expense_date`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'EXP-2026-0001', 1, 1, 'Allowance', 180000.00, 'RWF', 1.00000000, 180000.00, 3, 'approved', 6, '2026-09-14 10:00:00', NULL, 'mobile_money', NULL, '2026-09-14', 'Night-out allowance, 4 nights x 45,000 on the Dar es Salaam run.', NULL, '2026-09-14 08:30:00'),
(2, 'EXP-2026-0002', 2, 2, 'Permit', 60.00, 'USD', 1450.00000000, 87000.00, 44, 'pending', NULL, NULL, NULL, 'cash', NULL, '2026-09-29', 'DR Congo road transit permit paid at La Corniche.', NULL, '2026-09-29 16:40:00');

-- invoices
INSERT INTO `invoices` (`id`, `invoice_number`, `customer_id`, `trip_id`, `issue_date`, `due_date`, `subtotal`, `tax_rate`, `tax_amount`, `total_amount`, `currency`, `exchange_rate`, `base_amount`, `amount_paid`, `status`, `issued_by`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'INV-2026-0001', 1, 1, '2026-09-14', '2026-10-14', 5400000.00, 18.00, 972000.00, 6372000.00, 'RWF', 1.00000000, 6372000.00, 6372000.00, 'paid', 6, 'Freight Kigali - Dar es Salaam, 18,000 kg green coffee.', NULL, '2026-09-14 11:00:00'),
(2, 'INV-2026-0002', 2, 2, '2026-09-29', '2026-10-29', 2250.00, 18.00, 405.00, 2655.00, 'USD', 1450.00000000, 3849750.00, 1000.00, 'partially_paid', 6, 'Freight Kigali - Goma, 25,000 kg cement. Balance due on delivery.', NULL, '2026-09-29 09:00:00');

-- invoice_lines
INSERT INTO `invoice_lines` (`id`, `invoice_id`, `shipment_id`, `description`, `quantity`, `unit_price`, `created_at`) VALUES
(1, 1, 1, 'Freight Kigali - Dar es Salaam, green coffee (per kg)', 18000.00, 300.00, '2026-09-14 11:00:00'),
(2, 2, 2, 'Freight Kigali - Goma, cement (per kg)', 25000.00, 0.09, '2026-09-29 09:00:00');

-- payments
INSERT INTO `payments` (`id`, `payment_code`, `invoice_id`, `amount`, `currency`, `exchange_rate`, `base_amount`, `method`, `reference`, `paid_at`, `recorded_by`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'PAY-2026-0001', 1, 6372000.00, 'RWF', 1.00000000, 6372000.00, 'bank_transfer', 'BK-TRF-20260925-44810', '2026-09-25 11:30:00', 6, 'Full settlement of INV-2026-0001.', NULL, '2026-09-25 11:35:00'),
(2, 'PAY-2026-0002', 2, 1000.00, 'USD', 1450.00000000, 1450000.00, 'bank_transfer', 'EQB-TT-20260930-5521', '2026-09-30 10:15:00', 6, 'Advance payment. Balance of USD 1,655 due on delivery.', NULL, '2026-09-30 10:20:00');

-- maintenance_orders
INSERT INTO `maintenance_orders` (`id`, `work_order_code`, `vehicle_id`, `service_name`, `maintenance_type`, `provider_name`, `priority`, `estimated_cost`, `actual_cost`, `odometer_reading`, `status`, `started_at`, `due_date`, `completed_at`, `approved_by`, `approved_at`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'MNT-2026-0001', 1, 'Full service after Dar es Salaam run (oil, filters, brakes)', 'preventive', 'Kigali Truck Parts & Service Ltd', 'normal', 1300000.00, 1245000.00, 184250, 'completed', '2026-09-15 08:00:00', '2026-09-16', '2026-09-16 15:30:00', 4, '2026-09-15 07:45:00', 'Paid by cheque CHQ-2026-0001.', NULL, '2026-09-14 16:00:00'),
(2, 'MNT-2026-0002', 2, 'Replace two front tyres', 'tyre', 'Kigali Truck Parts & Service Ltd', 'high', 1100000.00, NULL, NULL, 'scheduled', NULL, '2026-10-03', NULL, 4, '2026-09-28 15:00:00', 'Tread worn close to limit. Book in when truck returns from Goma.', NULL, '2026-09-28 14:30:00');

-- maintenance_parts
INSERT INTO `maintenance_parts` (`id`, `maintenance_id`, `line_type`, `part_name`, `part_number`, `quantity`, `unit_cost`, `created_at`) VALUES
(1, 1, 'service', 'Full service package (labour, oil, filters, brake adjustment)', 'KTP-SVC-FULL', 1.00, 1245000.00, '2026-09-16 15:30:00'),
(2, 2, 'part', 'Tyre 315/80 R22.5 steer axle', 'MIC-XMZ-31580', 2.00, 550000.00, '2026-09-28 14:30:00');

-- vehicle_documents
INSERT INTO `vehicle_documents` (`id`, `document_code`, `vehicle_id`, `document_type`, `document_number`, `provider_name`, `issued_on`, `expires_on`, `cost`, `document_file`, `status`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'DOC-2026-0001', 1, 'insurance', 'SNR-MV-2026-118420', 'SONARWA General Insurance', '2026-01-10', '2027-01-09', 2850000.00, NULL, 'valid', 'Comprehensive cover including COMESA yellow card.', NULL, '2026-09-05 08:10:00'),
(2, 'DOC-2026-0002', 2, 'inspection', 'CT-2026-RAE219C', 'Motor Vehicle Inspection Centre, Kigali', '2026-04-20', '2026-10-20', 50000.00, NULL, 'expiring', 'Book the next inspection before 20 October.', NULL, '2026-09-05 08:15:00');

-- purchase_requests
INSERT INTO `purchase_requests` (`id`, `request_code`, `supplier_id`, `warehouse_id`, `requested_by`, `description`, `category`, `amount`, `currency`, `exchange_rate`, `base_amount`, `expected_date`, `status`, `approved_by`, `approved_at`, `rejection_reason`, `received_at`, `notes`, `created_at`, `deleted_at`) VALUES
(1, 'PR-2026-0001', 1, 1, 5, 'Engine oil for fleet servicing', 'Spare parts', 1520000.00, 'RWF', 1.00000000, 1520000.00, '2026-09-05', 'received', 6, '2026-09-02 10:00:00', NULL, '2026-09-05 14:00:00', '16 drums received in full.', '2026-09-02 09:15:00', NULL),
(2, 'PR-2026-0002', 1, 1, 5, 'Tarpaulin covers to restock below minimum level', 'Equipment', 850000.00, 'RWF', 1.00000000, 850000.00, '2026-10-06', 'approved', 6, '2026-09-29 11:00:00', NULL, NULL, 'Needed for open trailers on the coffee and cement runs.', '2026-09-29 09:40:00', NULL);

-- purchase_request_lines
INSERT INTO `purchase_request_lines` (`id`, `purchase_request_id`, `item_id`, `item_name`, `quantity`, `unit_of_measure`, `unit_price`, `received_quantity`, `created_at`) VALUES
(1, 1, 1, 'Engine oil 15W-40 (20 L drum)', 16.00, 'Unit', 95000.00, 16.00, '2026-09-02 09:15:00'),
(2, 2, 2, 'Tarpaulin cover 8 m x 10 m', 10.00, 'Unit', 85000.00, 0.00, '2026-09-29 09:40:00');

-- stock_movements
INSERT INTO `stock_movements` (`id`, `movement_code`, `item_id`, `warehouse_id`, `movement_type`, `quantity`, `unit_cost`, `balance_after`, `reference_type`, `reference_code`, `performed_by`, `moved_at`, `notes`, `created_at`) VALUES
(1, 'MOV-2026-0001', 1, 1, 'stock_in', 16.00, 95000.00, 16.00, 'purchase_request', 'PR-2026-0001', 5, '2026-09-05 14:00:00', 'Received from Kigali Truck Parts & Service Ltd.', '2026-09-05 14:00:00'),
(2, 'MOV-2026-0002', 2, 1, 'stock_in', 3.00, 85000.00, 3.00, 'opening', NULL, 5, '2026-09-02 09:05:00', 'Opening balance counted at stock take.', '2026-09-02 09:05:00');

-- gl_budgets
INSERT INTO `gl_budgets` (`id`, `fiscal_year`, `account_id`, `month`, `amount`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 2026, 19, 0, 180000000.00, 'Freight revenue target for 2026', NULL, '2026-09-01 09:00:00'),
(2, 2026, 22, 0, 36000000.00, 'Annual fuel budget for the fleet', NULL, '2026-09-01 09:00:00');

-- gl_cheque_books
INSERT INTO `gl_cheque_books` (`id`, `bank_account_id`, `prefix`, `first_no`, `last_no`, `next_no`, `status`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 3, NULL, 100001, 100025, 100026, 'finished', 'First cheque book on the main account, all leaves used', NULL, '2026-01-05 09:00:00'),
(2, 3, NULL, 100026, 100075, 100028, 'active', 'Current cheque book on the main account', NULL, '2026-09-01 09:00:00');

-- gl_cheques
INSERT INTO `gl_cheques` (`id`, `reference`, `cheque_no`, `bank_account_id`, `cheque_book_id`, `payee_name`, `supplier_id`, `payee_address`, `cheque_date`, `amount`, `memo`, `status`, `entry_id`, `prepared_by`, `issued_by`, `issued_at`, `printed_at`, `presented_on`, `void_by`, `void_at`, `void_reason`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'CHQ-2026-0001', '100026', 3, 2, 'Kigali Truck Parts & Service Ltd', 1, 'KK 15 Rd, Gikondo, Kigali', '2026-09-17', 1245000.00, 'Service of RAD 482 K - MNT-2026-0001', 'presented', NULL, 6, 6, '2026-09-17 11:00:00', '2026-09-17 11:05:00', '2026-09-22', NULL, NULL, NULL, NULL, NULL, '2026-09-17 10:45:00'),
(2, 'CHQ-2026-0002', '100027', 3, 2, 'Great Lakes Clearing & Forwarding Ltd', 2, 'Rusumo Border, Kirehe', '2026-09-26', 150000.00, 'Clearing fee BDR-2026-0001 (Akagera coffee)', 'issued', NULL, 6, 6, '2026-09-26 14:00:00', '2026-09-26 14:02:00', NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-26 13:50:00');

-- gl_cheque_lines
INSERT INTO `gl_cheque_lines` (`id`, `cheque_id`, `account_id`, `description`, `amount`) VALUES
(1, 1, 27, 'Full service of RAD 482 K after Dar es Salaam run', 1245000.00),
(2, 2, 302, 'Clearing and forwarding at Rusumo, BDR-2026-0001', 150000.00);

-- gl_journal_entries
INSERT INTO `gl_journal_entries` (`id`, `entry_no`, `entry_date`, `currency`, `memo`, `reference`, `source_type`, `source_id`, `source_code`, `status`, `reversed_by`, `posted_by`, `deleted_at`, `created_at`) VALUES
(1, 'JRN-2026-0001', '2026-09-14', 'RWF', 'Invoice INV-2026-0001 to Akagera Coffee Exporters Ltd', 'TRP-2026-0001', 'invoice', 1, 'INV-2026-0001', 'posted', NULL, 6, NULL, '2026-09-14 11:00:00'),
(2, 'JRN-2026-0002', '2026-09-25', 'RWF', 'Payment PAY-2026-0001 from Akagera Coffee Exporters Ltd', 'INV-2026-0001', 'payment', 1, 'PAY-2026-0001', 'posted', NULL, 6, NULL, '2026-09-25 11:35:00');

-- gl_journal_lines
INSERT INTO `gl_journal_lines` (`id`, `entry_id`, `line_no`, `account_id`, `description`, `debit`, `credit`) VALUES
(1, 1, 1, 5, 'Akagera Coffee Exporters Ltd', 6372000.00, 0.00),
(2, 1, 2, 19, 'Freight invoiced to Akagera Coffee Exporters Ltd', 0.00, 5400000.00),
(3, 1, 3, 12, 'VAT on INV-2026-0001', 0.00, 972000.00),
(4, 2, 1, 3, 'Received against INV-2026-0001', 6372000.00, 0.00),
(5, 2, 2, 5, 'Akagera Coffee Exporters Ltd', 0.00, 6372000.00);

-- notifications
INSERT INTO `notifications` (`id`, `notification_key`, `user_id`, `role_key`, `title`, `message`, `link_route`, `entity_type`, `entity_id`, `severity`, `is_read`, `read_at`, `created_at`) VALUES
(54, 'payment-received-PAY-2026-0001', NULL, 'finance', 'Payment received', 'PAY-2026-0001: RWF 6,372,000 received from Akagera Coffee Exporters Ltd against INV-2026-0001.', 'payments', 'payments', '1', 'success', 1, '2026-09-25 12:00:00', '2026-09-25 11:35:00'),
(55, 'crossing-lodged-BDR-2026-0002', NULL, 'logistics_manager', 'Crossing lodged at La Corniche', 'BDR-2026-0002 (RAE 219 C, Virunga cement) is lodged at La Corniche / Goma and waiting for release.', 'crossings', 'border_crossings', '2', 'info', 0, NULL, '2026-09-29 16:10:00');

COMMIT;
