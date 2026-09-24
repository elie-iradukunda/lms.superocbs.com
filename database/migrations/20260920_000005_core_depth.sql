-- Core logistics depth: customers, shipments, multi-stop routes, stock ledger,
-- vehicle compliance documents, maintenance costing, approvals and soft deletes.

CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_code VARCHAR(32) NOT NULL UNIQUE,
    customer_name VARCHAR(180) NOT NULL,
    customer_type ENUM('corporate','government','ngo','individual') NOT NULL DEFAULT 'corporate',
    contact_name VARCHAR(150) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    address VARCHAR(255) NULL,
    district VARCHAR(80) NULL,
    tin_number VARCHAR(40) NULL,
    payment_terms_days INT UNSIGNED NOT NULL DEFAULT 30,
    credit_limit DECIMAL(14,2) NULL,
    status ENUM('active','on_hold','inactive') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customers_status (status),
    INDEX idx_customers_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS request_id INT UNSIGNED NULL AFTER reference_code,
    ADD COLUMN IF NOT EXISTS customer_id INT UNSIGNED NULL AFTER request_id,
    ADD COLUMN IF NOT EXISTS trip_type ENUM('delivery','collection','transfer','return','shuttle') NOT NULL DEFAULT 'delivery' AFTER customer_id,
    ADD COLUMN IF NOT EXISTS planned_departure_at DATETIME NULL AFTER destination,
    ADD COLUMN IF NOT EXISTS planned_arrival_at DATETIME NULL AFTER planned_departure_at,
    ADD COLUMN IF NOT EXISTS cargo_summary VARCHAR(255) NULL AFTER status,
    ADD COLUMN IF NOT EXISTS dispatched_at DATETIME NULL AFTER cargo_summary,
    ADD COLUMN IF NOT EXISTS completed_at DATETIME NULL AFTER dispatched_at,
    ADD COLUMN IF NOT EXISTS created_by INT UNSIGNED NULL AFTER completed_at,
    ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER created_by,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER created_at;

ALTER TABLE trips DROP FOREIGN KEY IF EXISTS fk_trips_customer;
ALTER TABLE trips ADD CONSTRAINT fk_trips_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_trips_status ON trips (status);
CREATE INDEX IF NOT EXISTS idx_trips_departure ON trips (departure_at);
CREATE INDEX IF NOT EXISTS idx_trips_deleted ON trips (deleted_at);
CREATE INDEX IF NOT EXISTS idx_trips_request ON trips (request_id);

ALTER TABLE transport_requests
    ADD COLUMN IF NOT EXISTS customer_id INT UNSIGNED NULL AFTER requester_id,
    ADD COLUMN IF NOT EXISTS trip_id INT UNSIGNED NULL AFTER customer_id,
    ADD COLUMN IF NOT EXISTS cargo_description VARCHAR(255) NULL AFTER destination,
    ADD COLUMN IF NOT EXISTS weight_kg DECIMAL(12,2) NULL AFTER cargo_description,
    ADD COLUMN IF NOT EXISTS packages_count INT UNSIGNED NULL AFTER weight_kg,
    ADD COLUMN IF NOT EXISTS approved_by INT UNSIGNED NULL AFTER status,
    ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL AFTER approved_by,
    ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(255) NULL AFTER approved_at,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER created_at;

ALTER TABLE transport_requests DROP FOREIGN KEY IF EXISTS fk_requests_customer;
ALTER TABLE transport_requests ADD CONSTRAINT fk_requests_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;
ALTER TABLE transport_requests DROP FOREIGN KEY IF EXISTS fk_requests_trip;
ALTER TABLE transport_requests ADD CONSTRAINT fk_requests_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL;
ALTER TABLE transport_requests DROP FOREIGN KEY IF EXISTS fk_requests_approver;
ALTER TABLE transport_requests ADD CONSTRAINT fk_requests_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_requests_status ON transport_requests (status);
CREATE INDEX IF NOT EXISTS idx_requests_deleted ON transport_requests (deleted_at);

ALTER TABLE trips DROP FOREIGN KEY IF EXISTS fk_trips_request;
ALTER TABLE trips ADD CONSTRAINT fk_trips_request FOREIGN KEY (request_id) REFERENCES transport_requests(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS shipments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipment_code VARCHAR(32) NOT NULL UNIQUE,
    request_id INT UNSIGNED NULL,
    trip_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    consignee_name VARCHAR(150) NOT NULL,
    consignee_phone VARCHAR(40) NULL,
    origin VARCHAR(150) NOT NULL,
    destination VARCHAR(150) NOT NULL,
    cargo_type ENUM('general','cold_chain','fragile','hazardous','bulk','liquid','perishable') NOT NULL DEFAULT 'general',
    cargo_description VARCHAR(255) NOT NULL,
    packages_count INT UNSIGNED NOT NULL DEFAULT 1,
    weight_kg DECIMAL(12,2) NULL,
    volume_m3 DECIMAL(12,3) NULL,
    temperature_min_c DECIMAL(5,2) NULL,
    temperature_max_c DECIMAL(5,2) NULL,
    is_hazardous TINYINT(1) NOT NULL DEFAULT 0,
    declared_value DECIMAL(14,2) NULL,
    special_instructions TEXT NULL,
    status ENUM('draft','booked','loaded','in_transit','delivered','returned','cancelled') NOT NULL DEFAULT 'draft',
    booked_at DATETIME NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES transport_requests(id) ON DELETE SET NULL,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    INDEX idx_shipments_status (status),
    INDEX idx_shipments_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trip_stops (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trip_id INT UNSIGNED NOT NULL,
    stop_sequence INT UNSIGNED NOT NULL DEFAULT 1,
    stop_type ENUM('pickup','dropoff','waypoint','checkpoint') NOT NULL DEFAULT 'dropoff',
    location_name VARCHAR(180) NOT NULL,
    contact_name VARCHAR(150) NULL,
    contact_phone VARCHAR(40) NULL,
    planned_arrival_at DATETIME NULL,
    actual_arrival_at DATETIME NULL,
    status ENUM('pending','arrived','completed','skipped','failed') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    INDEX idx_trip_stops_trip (trip_id, stop_sequence)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE deliveries
    ADD COLUMN IF NOT EXISTS shipment_id INT UNSIGNED NULL AFTER trip_id,
    ADD COLUMN IF NOT EXISTS recipient_phone VARCHAR(40) NULL AFTER recipient_name,
    ADD COLUMN IF NOT EXISTS planned_at DATETIME NULL AFTER destination,
    ADD COLUMN IF NOT EXISTS attempt_number TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER status,
    ADD COLUMN IF NOT EXISTS failure_reason ENUM('none','recipient_absent','address_wrong','goods_damaged','goods_refused','vehicle_breakdown','access_denied','weather','other') NOT NULL DEFAULT 'none' AFTER attempt_number,
    ADD COLUMN IF NOT EXISTS failure_notes VARCHAR(255) NULL AFTER failure_reason,
    ADD COLUMN IF NOT EXISTS rescheduled_at DATETIME NULL AFTER failure_notes,
    ADD COLUMN IF NOT EXISTS delivered_by INT UNSIGNED NULL AFTER delivered_at,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER delivered_by,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER deleted_at;

ALTER TABLE deliveries DROP FOREIGN KEY IF EXISTS fk_deliveries_shipment;
ALTER TABLE deliveries ADD CONSTRAINT fk_deliveries_shipment FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_deliveries_status ON deliveries (status);
CREATE INDEX IF NOT EXISTS idx_deliveries_deleted ON deliveries (deleted_at);

ALTER TABLE vehicles
    ADD COLUMN IF NOT EXISTS make VARCHAR(80) NULL AFTER vehicle_type,
    ADD COLUMN IF NOT EXISTS manufacture_year SMALLINT UNSIGNED NULL AFTER model,
    ADD COLUMN IF NOT EXISTS chassis_number VARCHAR(80) NULL AFTER manufacture_year,
    ADD COLUMN IF NOT EXISTS capacity_kg DECIMAL(12,2) NULL AFTER chassis_number,
    ADD COLUMN IF NOT EXISTS capacity_m3 DECIMAL(12,3) NULL AFTER capacity_kg,
    ADD COLUMN IF NOT EXISTS fuel_type ENUM('diesel','petrol','electric','hybrid','cng') NOT NULL DEFAULT 'diesel' AFTER capacity_m3,
    ADD COLUMN IF NOT EXISTS ownership ENUM('owned','leased','rented','subcontracted') NOT NULL DEFAULT 'owned' AFTER fuel_type,
    ADD COLUMN IF NOT EXISTS acquired_on DATE NULL AFTER ownership,
    ADD COLUMN IF NOT EXISTS has_cooling_unit TINYINT(1) NOT NULL DEFAULT 0 AFTER acquired_on,
    ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER next_service_date,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER created_at;

CREATE INDEX IF NOT EXISTS idx_vehicles_status ON vehicles (status);
CREATE INDEX IF NOT EXISTS idx_vehicles_deleted ON vehicles (deleted_at);

CREATE TABLE IF NOT EXISTS vehicle_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_code VARCHAR(32) NOT NULL UNIQUE,
    vehicle_id INT UNSIGNED NOT NULL,
    document_type ENUM('insurance','inspection','registration','road_license','permit','other') NOT NULL DEFAULT 'insurance',
    document_number VARCHAR(80) NULL,
    provider_name VARCHAR(150) NULL,
    issued_on DATE NULL,
    expires_on DATE NOT NULL,
    cost DECIMAL(12,2) NULL,
    document_file VARCHAR(255) NULL,
    status ENUM('valid','expiring','expired','cancelled') NOT NULL DEFAULT 'valid',
    notes TEXT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    INDEX idx_vehicle_docs_expiry (expires_on),
    INDEX idx_vehicle_docs_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE drivers
    ADD COLUMN IF NOT EXISTS national_id VARCHAR(40) NULL AFTER full_name,
    ADD COLUMN IF NOT EXISTS date_of_birth DATE NULL AFTER national_id,
    ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS license_class VARCHAR(20) NULL AFTER license_number,
    ADD COLUMN IF NOT EXISTS emergency_contact VARCHAR(150) NULL AFTER license_expiry,
    ADD COLUMN IF NOT EXISTS emergency_phone VARCHAR(40) NULL AFTER emergency_contact,
    ADD COLUMN IF NOT EXISTS hired_on DATE NULL AFTER emergency_phone,
    ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER created_at;

CREATE INDEX IF NOT EXISTS idx_drivers_status ON drivers (status);
CREATE INDEX IF NOT EXISTS idx_drivers_deleted ON drivers (deleted_at);

ALTER TABLE maintenance_orders
    ADD COLUMN IF NOT EXISTS maintenance_type ENUM('preventive','corrective','inspection','tyre','bodywork','emergency') NOT NULL DEFAULT 'preventive' AFTER service_name,
    ADD COLUMN IF NOT EXISTS actual_cost DECIMAL(12,2) NULL AFTER estimated_cost,
    ADD COLUMN IF NOT EXISTS odometer_reading INT UNSIGNED NULL AFTER actual_cost,
    ADD COLUMN IF NOT EXISTS started_at DATETIME NULL AFTER status,
    ADD COLUMN IF NOT EXISTS approved_by INT UNSIGNED NULL AFTER completed_at,
    ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL AFTER approved_by,
    ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER approved_at,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER notes,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER deleted_at;

CREATE INDEX IF NOT EXISTS idx_maintenance_status ON maintenance_orders (status);
CREATE INDEX IF NOT EXISTS idx_maintenance_deleted ON maintenance_orders (deleted_at);

CREATE TABLE IF NOT EXISTS maintenance_parts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    maintenance_id INT UNSIGNED NOT NULL,
    line_type ENUM('part','labour','service','consumable') NOT NULL DEFAULT 'part',
    part_name VARCHAR(180) NOT NULL,
    part_number VARCHAR(80) NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(14,2) AS (quantity * unit_cost) STORED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (maintenance_id) REFERENCES maintenance_orders(id) ON DELETE CASCADE,
    INDEX idx_maintenance_parts_order (maintenance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE fuel_records
    ADD COLUMN IF NOT EXISTS fuel_type ENUM('diesel','petrol','electric','cng') NOT NULL DEFAULT 'diesel' AFTER station_name,
    ADD COLUMN IF NOT EXISTS is_full_tank TINYINT(1) NOT NULL DEFAULT 1 AFTER litres,
    ADD COLUMN IF NOT EXISTS previous_mileage INT UNSIGNED NULL AFTER mileage,
    ADD COLUMN IF NOT EXISTS driver_id INT UNSIGNED NULL AFTER previous_mileage,
    ADD COLUMN IF NOT EXISTS trip_id INT UNSIGNED NULL AFTER driver_id,
    ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER receipt_file,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER notes,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER deleted_at;

ALTER TABLE fuel_records DROP FOREIGN KEY IF EXISTS fk_fuel_driver;
ALTER TABLE fuel_records ADD CONSTRAINT fk_fuel_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL;
ALTER TABLE fuel_records DROP FOREIGN KEY IF EXISTS fk_fuel_trip;
ALTER TABLE fuel_records ADD CONSTRAINT fk_fuel_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_fuel_purchased ON fuel_records (purchased_at);
CREATE INDEX IF NOT EXISTS idx_fuel_deleted ON fuel_records (deleted_at);

ALTER TABLE expenses
    ADD COLUMN IF NOT EXISTS approved_by INT UNSIGNED NULL AFTER status,
    ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL AFTER approved_by,
    ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(255) NULL AFTER approved_at,
    ADD COLUMN IF NOT EXISTS payment_method ENUM('cash','bank_transfer','mobile_money','fuel_card','cheque') NOT NULL DEFAULT 'cash' AFTER rejection_reason,
    ADD COLUMN IF NOT EXISTS receipt_file VARCHAR(255) NULL AFTER payment_method,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER notes,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER deleted_at;

ALTER TABLE expenses DROP FOREIGN KEY IF EXISTS fk_expenses_approver;
ALTER TABLE expenses ADD CONSTRAINT fk_expenses_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_expenses_date ON expenses (expense_date);
CREATE INDEX IF NOT EXISTS idx_expenses_status ON expenses (status);
CREATE INDEX IF NOT EXISTS idx_expenses_deleted ON expenses (deleted_at);

ALTER TABLE warehouses
    ADD COLUMN IF NOT EXISTS warehouse_code VARCHAR(32) NULL AFTER id,
    ADD COLUMN IF NOT EXISTS manager_id INT UNSIGNED NULL AFTER location,
    ADD COLUMN IF NOT EXISTS phone VARCHAR(40) NULL AFTER manager_id,
    ADD COLUMN IF NOT EXISTS capacity_m3 DECIMAL(12,2) NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS is_cold_chain TINYINT(1) NOT NULL DEFAULT 0 AFTER capacity_m3,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER status;

ALTER TABLE inventory_items
    ADD COLUMN IF NOT EXISTS category VARCHAR(80) NULL AFTER item_name,
    ADD COLUMN IF NOT EXISTS unit_of_measure VARCHAR(20) NOT NULL DEFAULT 'Unit' AFTER category,
    ADD COLUMN IF NOT EXISTS reorder_quantity DECIMAL(12,2) NULL AFTER minimum_level,
    ADD COLUMN IF NOT EXISTS batch_number VARCHAR(80) NULL AFTER unit_cost,
    ADD COLUMN IF NOT EXISTS expiry_date DATE NULL AFTER batch_number,
    ADD COLUMN IF NOT EXISTS storage_temperature VARCHAR(40) NULL AFTER expiry_date,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER status,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER deleted_at;

CREATE INDEX IF NOT EXISTS idx_inventory_status ON inventory_items (status);
CREATE INDEX IF NOT EXISTS idx_inventory_deleted ON inventory_items (deleted_at);

CREATE TABLE IF NOT EXISTS stock_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    movement_code VARCHAR(32) NOT NULL UNIQUE,
    item_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    movement_type ENUM('stock_in','stock_out','transfer_in','transfer_out','adjustment','damage','return') NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit_cost DECIMAL(12,2) NULL,
    balance_after DECIMAL(12,2) NULL,
    reference_type VARCHAR(40) NULL,
    reference_code VARCHAR(64) NULL,
    performed_by INT UNSIGNED NULL,
    moved_at DATETIME NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_movements_item (item_id, moved_at),
    INDEX idx_movements_date (moved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE suppliers
    ADD COLUMN IF NOT EXISTS supplier_code VARCHAR(32) NULL AFTER id,
    ADD COLUMN IF NOT EXISTS category VARCHAR(80) NULL AFTER supplier_name,
    ADD COLUMN IF NOT EXISTS tin_number VARCHAR(40) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL AFTER tin_number,
    ADD COLUMN IF NOT EXISTS payment_terms_days INT UNSIGNED NOT NULL DEFAULT 30 AFTER address,
    ADD COLUMN IF NOT EXISTS rating TINYINT UNSIGNED NULL AFTER payment_terms_days,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER status,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER deleted_at;

ALTER TABLE purchase_requests
    ADD COLUMN IF NOT EXISTS warehouse_id INT UNSIGNED NULL AFTER supplier_id,
    ADD COLUMN IF NOT EXISTS category VARCHAR(80) NULL AFTER description,
    ADD COLUMN IF NOT EXISTS expected_date DATE NULL AFTER amount,
    ADD COLUMN IF NOT EXISTS approved_by INT UNSIGNED NULL AFTER status,
    ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL AFTER approved_by,
    ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(255) NULL AFTER approved_at,
    ADD COLUMN IF NOT EXISTS received_at DATETIME NULL AFTER rejection_reason,
    ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER received_at,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER created_at;

ALTER TABLE purchase_requests DROP FOREIGN KEY IF EXISTS fk_purchase_warehouse;
ALTER TABLE purchase_requests ADD CONSTRAINT fk_purchase_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL;
ALTER TABLE purchase_requests DROP FOREIGN KEY IF EXISTS fk_purchase_approver;
ALTER TABLE purchase_requests ADD CONSTRAINT fk_purchase_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_purchase_status ON purchase_requests (status);
CREATE INDEX IF NOT EXISTS idx_purchase_deleted ON purchase_requests (deleted_at);

CREATE TABLE IF NOT EXISTS purchase_request_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_request_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NULL,
    item_name VARCHAR(180) NOT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit_of_measure VARCHAR(20) NOT NULL DEFAULT 'Unit',
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(14,2) AS (quantity * unit_price) STORED,
    received_quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_request_id) REFERENCES purchase_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE SET NULL,
    INDEX idx_pr_lines_request (purchase_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
