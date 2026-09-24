-- What a new installation starts with.
--
-- Only the two things a system cannot run without: the roles, and the logins
-- that hold them. No vehicles, no trips, no stock — a company puts its own in.
--
-- Everything else that used to live here is now in seed_demo_base.sql, loaded
-- only when scripts/migrate.php is run with --demo.
USE logistics_mvc;

SET NAMES utf8mb4;

START TRANSACTION;

INSERT INTO roles (role_key, role_name) VALUES
('super_admin', 'Super Admin'),
('logistics_manager', 'Logistics Manager'),
('fleet_manager', 'Fleet Manager'),
('warehouse_manager', 'Warehouse Manager'),
('driver', 'Driver'),
('finance', 'Finance'),
('management', 'Management')
ON DUPLICATE KEY UPDATE role_name = VALUES(role_name);

INSERT INTO users (role_id, full_name, email, password_hash, phone, department, status, prvg, last_login_at)
SELECT id, 'Admin User', 'admin@itec.rw', '$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6', '+250 788 000 001', 'Administration', 'active', 1, '2026-09-18 09:00:00'
FROM roles WHERE role_key = 'super_admin'
ON DUPLICATE KEY UPDATE
    prvg = VALUES(prvg),
    role_id = VALUES(role_id),
    full_name = VALUES(full_name),
    password_hash = VALUES(password_hash),
    phone = VALUES(phone),
    department = VALUES(department),
    status = VALUES(status),
    last_login_at = VALUES(last_login_at);

INSERT INTO users (role_id, full_name, email, password_hash, phone, department, status, last_login_at)
SELECT id, 'Aline Mukamana', 'aline@itec.rw', '$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6', '+250 788 202 020', 'Operations', 'active', '2026-09-18 08:42:00'
FROM roles WHERE role_key = 'logistics_manager'
ON DUPLICATE KEY UPDATE
    role_id = VALUES(role_id),
    full_name = VALUES(full_name),
    password_hash = VALUES(password_hash),
    phone = VALUES(phone),
    department = VALUES(department),
    status = VALUES(status),
    last_login_at = VALUES(last_login_at);

INSERT INTO users (role_id, full_name, email, password_hash, phone, department, status, last_login_at)
SELECT id, 'Samuel Niyonzima', 'samuel@itec.rw', '$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6', '+250 788 632 119', 'Fleet', 'active', '2026-09-18 06:18:00'
FROM roles WHERE role_key = 'driver'
ON DUPLICATE KEY UPDATE
    role_id = VALUES(role_id),
    full_name = VALUES(full_name),
    password_hash = VALUES(password_hash),
    phone = VALUES(phone),
    department = VALUES(department),
    status = VALUES(status),
    last_login_at = VALUES(last_login_at);

INSERT INTO users (role_id, full_name, email, password_hash, phone, department, status, last_login_at)
SELECT id, 'Eric Murenzi', 'eric@itec.rw', '$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6', '+250 783 210 084', 'Fleet', 'active', '2026-09-17 16:20:00'
FROM roles WHERE role_key = 'fleet_manager'
ON DUPLICATE KEY UPDATE
    role_id = VALUES(role_id),
    full_name = VALUES(full_name),
    password_hash = VALUES(password_hash),
    phone = VALUES(phone),
    department = VALUES(department),
    status = VALUES(status),
    last_login_at = VALUES(last_login_at);

INSERT INTO users (role_id, full_name, email, password_hash, phone, department, status, last_login_at)
SELECT id, 'Nadine Tuyisenge', 'nadine@itec.rw', '$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6', '+250 782 110 554', 'Warehouse', 'active', '2026-09-16 09:35:00'
FROM roles WHERE role_key = 'warehouse_manager'
ON DUPLICATE KEY UPDATE
    role_id = VALUES(role_id),
    full_name = VALUES(full_name),
    password_hash = VALUES(password_hash),
    phone = VALUES(phone),
    department = VALUES(department),
    status = VALUES(status),
    last_login_at = VALUES(last_login_at);

INSERT INTO users (role_id, full_name, email, password_hash, phone, department, status, last_login_at)
SELECT id, 'Emmanuel Safari', 'emmanuel@itec.rw', '$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6', '+250 788 705 881', 'Finance', 'active', '2026-09-15 11:05:00'
FROM roles WHERE role_key = 'finance'
ON DUPLICATE KEY UPDATE
    role_id = VALUES(role_id),
    full_name = VALUES(full_name),
    password_hash = VALUES(password_hash),
    phone = VALUES(phone),
    department = VALUES(department),
    status = VALUES(status),
    last_login_at = VALUES(last_login_at);

INSERT INTO users (role_id, full_name, email, password_hash, phone, department, status, last_login_at)
SELECT id, 'Jean Pierre Habimana', 'jeanpierre@itec.rw', '$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6', '+250 788 512 030', 'Management', 'active', '2026-09-14 14:10:00'
FROM roles WHERE role_key = 'management'
ON DUPLICATE KEY UPDATE
    role_id = VALUES(role_id),
    full_name = VALUES(full_name),
    password_hash = VALUES(password_hash),
    phone = VALUES(phone),
    department = VALUES(department),
    status = VALUES(status),
    last_login_at = VALUES(last_login_at);

COMMIT;
