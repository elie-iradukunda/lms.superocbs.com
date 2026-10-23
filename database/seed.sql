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

-- The companies of the group (specification, section 3). Admin edits their
-- details, logo and stamp under Administration > Companies, and adds the
-- fourth sister company once its name is confirmed.
INSERT INTO companies (id, company_code, company_name, short_name, country, city, address, phone, email, base_currency, sort_order) VALUES
(1, 'ERI-RW', 'ERI RWANDA LTD', 'ERI Rwanda', 'Rwanda', 'Kigali', 'KIGALI-RWANDA', '+250 252 502 437', 'info@eri-rwanda.com', 'RWF', 10),
(2, 'ERI-KE', 'ERI KENYA LTD', 'ERI Kenya', 'Kenya', 'Mombasa', 'New Cannon Tower, 9th floor, Moi Avenue, Mombasa', '+254 720 051668', 'info@eri-kenya.com', 'KES', 20),
(3, 'ERI-TZ', 'ERI TANZANIA LTD', 'ERI Tanzania', 'Tanzania', 'Dar es Salaam', NULL, NULL, NULL, 'TZS', 30)
ON DUPLICATE KEY UPDATE company_code = company_code;

-- The departments every company starts with; Admin adds others.
INSERT IGNORE INTO departments (company_id, department_name)
SELECT c.id, d.name FROM companies c
INNER JOIN (
    SELECT 'Operations' AS name UNION ALL SELECT 'Finance' UNION ALL SELECT 'Management' UNION ALL
    SELECT 'Cash office' UNION ALL SELECT 'Store' UNION ALL SELECT 'Garage / Workshop' UNION ALL SELECT 'HR'
) d;

-- One small set of roles, the same in every company (Rwanda, Kenya, Tanzania,
-- and any the Admin adds). The Store & garage keeper also keeps the job cards;
-- the Operational Manager also handles drivers/HR and raises sister-company
-- requests; the Clearing agent does the whole clearance file.
INSERT INTO roles (role_key, role_name) VALUES
('super_admin', 'Admin'),
('management', 'Managing Director'),
('logistics_manager', 'Operational Manager'),
('finance', 'Finance Manager'),
('cashier', 'Cashier'),
('warehouse_manager', 'Store & garage keeper'),
('driver', 'Driver'),
('clearance_officer', 'Declaration Manager'),
('supplier', 'Supplier'),
('customer', 'Customer')
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
SELECT id, 'Patrick Habimana', 'patrick.workshop@itec.rw', '$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6', '+250 785 330 912', 'Store & garage', 'active', '2026-09-16 08:10:00'
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
SELECT id, 'Grace Uwase', 'grace.clearance@itec.rw', '$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6', '+250 788 410 226', 'Clearance', 'active', '2026-09-16 08:20:00'
FROM roles WHERE role_key = 'clearance_officer'
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

INSERT INTO users (role_id, company_id, full_name, email, password_hash, phone, department, status)
SELECT id, 1, 'Eric Murenzi', 'eric@itec.rw', '$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6', '+250 783 210 084', 'Operations', 'active'
FROM roles WHERE role_key = 'logistics_manager'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), company_id = VALUES(company_id), full_name = VALUES(full_name), department = VALUES(department);

INSERT INTO users (role_id, company_id, full_name, email, password_hash, phone, department, status)
SELECT id, 1, 'Patrick Nkusi', 'patrick.finance@itec.rw', '$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6', '+250 788 300 410', 'Finance', 'active'
FROM roles WHERE role_key = 'finance'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), company_id = VALUES(company_id), full_name = VALUES(full_name), department = VALUES(department);

INSERT INTO users (role_id, company_id, full_name, email, password_hash, phone, department, status)
SELECT id, 1, 'Diane Uwera', 'cashier@itec.rw', '$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6', '+250 788 300 411', 'Cash office', 'active'
FROM roles WHERE role_key = 'cashier'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), company_id = VALUES(company_id), full_name = VALUES(full_name), department = VALUES(department);

-- Shomari is the Operational Manager of ERI Tanzania (company 3); he raises
-- sister-company requests to ERI Rwanda from there.
INSERT INTO users (role_id, company_id, full_name, email, password_hash, phone, department, status)
SELECT id, 3, 'Shomari Mwinyi', 'shomari@itec.rw', '$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6', '+255 754 300 413', 'Operations', 'active'
FROM roles WHERE role_key = 'logistics_manager'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), company_id = VALUES(company_id), full_name = VALUES(full_name), department = VALUES(department);

-- Every office login belongs to ERI Rwanda until Admin moves it.
UPDATE users SET company_id = 1 WHERE company_id IS NULL;
UPDATE users u
INNER JOIN departments d ON d.company_id = u.company_id AND d.department_name = CASE u.department
    WHEN 'Fleet' THEN 'Operations' WHEN 'Warehouse' THEN 'Store' WHEN 'Workshop' THEN 'Garage / Workshop'
    WHEN 'Administration' THEN 'Management' ELSE u.department END
SET u.department_id = d.id
WHERE u.department_id IS NULL;

COMMIT;
