-- The lists a company decides for itself.
--
-- Vehicle types, expense categories, units of measure and the rest were written
-- into the code, so adding one meant editing a PHP file. They live here now,
-- and the Reference lists page adds, renames, reorders and retires them.
--
-- What stays in code is the handful of lists the system reasons about rather
-- than displays: statuses, priorities, cargo types. Adding "Half delivered" to
-- a status list would not teach the dispatch rules what to do with it, so those
-- remain database enums.

CREATE TABLE IF NOT EXISTS lookup_values (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    list_key VARCHAR(40) NOT NULL,
    -- What is stored on the records that use it. Renaming an entry rewrites
    -- this on every record that carried the old one, so nothing is orphaned.
    value VARCHAR(120) NOT NULL,
    label VARCHAR(120) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(255) NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_lookup_entry (list_key, value),
    INDEX idx_lookup_list (list_key, sort_order),
    INDEX idx_lookup_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('lookups', 'Reference lists', 'Administration', 215)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'lookups', 1, 1, 1, 1, 1 FROM roles r WHERE r.role_key = 'super_admin'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

-- The people who use a list day to day may extend it.
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'lookups', 1, 1, 1, 0, 0 FROM roles r WHERE r.role_key IN ('logistics_manager', 'fleet_manager', 'warehouse_manager', 'finance')
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1;

-- The lists a new installation starts with. Every one of them can be added
-- to, renamed, reordered and retired from the Reference lists page; these are
-- a starting point, not a limit.
INSERT IGNORE INTO lookup_values (list_key, value, label, sort_order, is_active) VALUES
('vehicle_type', 'Delivery truck', 'Delivery truck', 10, 1),
('vehicle_type', 'Box truck', 'Box truck', 20, 1),
('vehicle_type', 'Refrigerated truck', 'Refrigerated truck', 30, 1),
('vehicle_type', 'Pickup', 'Pickup', 40, 1),
('vehicle_type', 'Van', 'Van', 50, 1),
('vehicle_type', 'Tanker', 'Tanker', 60, 1),
('vehicle_type', 'Trailer', 'Trailer', 70, 1),
('vehicle_type', 'Motorcycle', 'Motorcycle', 80, 1),
('licence_class', 'A', 'A', 10, 1),
('licence_class', 'B', 'B', 20, 1),
('licence_class', 'C', 'C', 30, 1),
('licence_class', 'D', 'D', 40, 1),
('licence_class', 'E', 'E', 50, 1),
('licence_class', 'B/C', 'B/C', 60, 1),
('licence_class', 'C/D', 'C/D', 70, 1),
('expense_category', 'Fuel', 'Fuel', 10, 1),
('expense_category', 'Toll', 'Toll', 20, 1),
('expense_category', 'Repair', 'Repair', 30, 1),
('expense_category', 'Allowance', 'Allowance', 40, 1),
('expense_category', 'Parking', 'Parking', 50, 1),
('expense_category', 'Insurance', 'Insurance', 60, 1),
('expense_category', 'Loading', 'Loading', 70, 1),
('expense_category', 'Permit', 'Permit', 80, 1),
('item_category', 'Food', 'Food', 10, 1),
('item_category', 'Packaging', 'Packaging', 20, 1),
('item_category', 'Spare parts', 'Spare parts', 30, 1),
('item_category', 'Consumables', 'Consumables', 40, 1),
('item_category', 'Cold chain', 'Cold chain', 50, 1),
('item_category', 'Equipment', 'Equipment', 60, 1),
('item_category', 'Stationery', 'Stationery', 70, 1),
('unit_of_measure', 'Unit', 'Unit', 10, 1),
('unit_of_measure', 'Kg', 'Kg', 20, 1),
('unit_of_measure', 'Litre', 'Litre', 30, 1),
('unit_of_measure', 'Box', 'Box', 40, 1),
('unit_of_measure', 'Sack', 'Sack', 50, 1),
('unit_of_measure', 'Crate', 'Crate', 60, 1),
('unit_of_measure', 'Pallet', 'Pallet', 70, 1),
('unit_of_measure', 'Carton', 'Carton', 80, 1),
('supplier_category', 'Spare parts', 'Spare parts', 10, 1),
('supplier_category', 'Fuel', 'Fuel', 20, 1),
('supplier_category', 'Packaging', 'Packaging', 30, 1),
('supplier_category', 'Cold chain', 'Cold chain', 40, 1),
('supplier_category', 'Equipment', 'Equipment', 50, 1),
('supplier_category', 'Services', 'Services', 60, 1),
('supplier_category', 'Consumables', 'Consumables', 70, 1),
('procurement_category', 'Spare parts', 'Spare parts', 10, 1),
('procurement_category', 'Fuel', 'Fuel', 20, 1),
('procurement_category', 'Packaging', 'Packaging', 30, 1),
('procurement_category', 'Cold chain', 'Cold chain', 40, 1),
('procurement_category', 'Equipment', 'Equipment', 50, 1),
('procurement_category', 'Services', 'Services', 60, 1),
('procurement_category', 'Food', 'Food', 70, 1),
('department', 'Operations', 'Operations', 10, 1),
('department', 'Fleet', 'Fleet', 20, 1),
('department', 'Warehouse', 'Warehouse', 30, 1),
('department', 'Finance', 'Finance', 40, 1),
('department', 'Management', 'Management', 50, 1),
('department', 'Administration', 'Administration', 60, 1),
('report_period', 'Daily', 'Daily', 10, 1),
('report_period', 'Weekly', 'Weekly', 20, 1),
('report_period', 'Monthly', 'Monthly', 30, 1),
('report_period', 'Quarterly', 'Quarterly', 40, 1),
('report_period', 'Yearly', 'Yearly', 50, 1);
