CREATE DATABASE IF NOT EXISTS logistics_mvc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE logistics_mvc;

CREATE TABLE vehicles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(32) NOT NULL UNIQUE,
    vehicle_type VARCHAR(80) NOT NULL,
    model VARCHAR(80) NULL,
    assigned_driver_id INT UNSIGNED NULL,
    mileage INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('available','on_trip','maintenance','inactive') NOT NULL DEFAULT 'available',
    next_service_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE drivers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(40) NULL,
    license_number VARCHAR(80) NOT NULL UNIQUE,
    license_expiry DATE NULL,
    status ENUM('available','on_trip','off_duty','inactive') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE trips (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_code VARCHAR(32) NOT NULL UNIQUE,
    vehicle_id INT UNSIGNED NULL,
    driver_id INT UNSIGNED NULL,
    pickup_location VARCHAR(150) NOT NULL,
    destination VARCHAR(150) NOT NULL,
    departure_at DATETIME NULL,
    arrival_at DATETIME NULL,
    status ENUM('requested','approved','loading','in_transit','delivered','cancelled') NOT NULL DEFAULT 'requested',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL
);

CREATE TABLE expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_code VARCHAR(32) NULL UNIQUE,
    trip_id INT UNSIGNED NULL,
    vehicle_id INT UNSIGNED NULL,
    category VARCHAR(60) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    submitted_by INT UNSIGNED NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    expense_date DATE NOT NULL,
    notes TEXT NULL,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL
);

CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_key VARCHAR(50) NOT NULL UNIQUE,
    role_name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(40) NULL,
    department VARCHAR(80) NULL,
    status ENUM('active','inactive','locked') NOT NULL DEFAULT 'active',
    prvg TINYINT UNSIGNED NOT NULL DEFAULT 2,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_users_prvg CHECK (prvg IN (1, 2)),
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE transport_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_code VARCHAR(32) NOT NULL UNIQUE,
    requester_id INT UNSIGNED NULL,
    pickup_location VARCHAR(150) NOT NULL,
    destination VARCHAR(150) NOT NULL,
    required_date DATE NOT NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    status ENUM('pending','approved','assigned','rejected','cancelled') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE deliveries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    delivery_code VARCHAR(32) NOT NULL UNIQUE,
    trip_id INT UNSIGNED NULL,
    recipient_name VARCHAR(150) NOT NULL,
    destination VARCHAR(150) NOT NULL,
    status ENUM('loading','in_transit','delivered','failed') NOT NULL DEFAULT 'loading',
    proof_file VARCHAR(255) NULL,
    recipient_signature VARCHAR(255) NULL,
    delivered_at DATETIME NULL,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL
);

CREATE TABLE fuel_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_code VARCHAR(32) NULL UNIQUE,
    vehicle_id INT UNSIGNED NOT NULL,
    station_name VARCHAR(150) NOT NULL,
    litres DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    mileage INT UNSIGNED NULL,
    purchased_at DATETIME NOT NULL,
    receipt_file VARCHAR(255) NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
);

CREATE TABLE maintenance_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    work_order_code VARCHAR(32) NOT NULL UNIQUE,
    vehicle_id INT UNSIGNED NOT NULL,
    service_name VARCHAR(150) NOT NULL,
    provider_name VARCHAR(150) NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    estimated_cost DECIMAL(12,2) NULL,
    status ENUM('open','scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'open',
    due_date DATE NULL,
    completed_at DATETIME NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
);

CREATE TABLE warehouses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    warehouse_name VARCHAR(150) NOT NULL,
    location VARCHAR(150) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active'
);

CREATE TABLE inventory_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT UNSIGNED NOT NULL,
    sku VARCHAR(80) NOT NULL UNIQUE,
    item_name VARCHAR(150) NOT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    minimum_level DECIMAL(12,2) NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,2) NULL,
    status ENUM('in_stock','reorder','out_of_stock') NOT NULL DEFAULT 'in_stock',
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);

CREATE TABLE suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(150) NOT NULL,
    contact_name VARCHAR(150) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active'
);

CREATE TABLE purchase_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_code VARCHAR(32) NOT NULL UNIQUE,
    supplier_id INT UNSIGNED NULL,
    requested_by INT UNSIGNED NULL,
    description TEXT NOT NULL,
    amount DECIMAL(12,2) NULL,
    status ENUM('draft','quotation','approved','received','rejected') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_name VARCHAR(150) NOT NULL UNIQUE,
    period_label VARCHAR(100) NOT NULL,
    owner_name VARCHAR(100) NOT NULL,
    last_generated_at DATETIME NULL,
    format_label VARCHAR(20) NOT NULL DEFAULT 'CSV',
    action_label VARCHAR(40) NOT NULL DEFAULT 'View'
);

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    notification_key VARCHAR(80) NOT NULL UNIQUE,
    user_id INT UNSIGNED NULL,
    role_key VARCHAR(50) NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    link_route VARCHAR(80) NULL,
    severity ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (role_key),
    INDEX (is_read)
);

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action_name VARCHAR(100) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id VARCHAR(64) NULL,
    reason TEXT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO roles (role_key, role_name) VALUES
('super_admin', 'Super Admin'),
('logistics_manager', 'Logistics Manager'),
('fleet_manager', 'Fleet Manager'),
('warehouse_manager', 'Warehouse Manager'),
('driver', 'Driver'),
('finance', 'Finance'),
('management', 'Management')
ON DUPLICATE KEY UPDATE role_name = VALUES(role_name);
