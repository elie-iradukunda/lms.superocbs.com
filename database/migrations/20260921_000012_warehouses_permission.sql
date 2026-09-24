-- Warehouses became a page, so it needs a permission.
--
-- Stock items always belonged to a warehouse, but the warehouses themselves
-- arrived with the seed data and there was no way to add one. Now that there is
-- a page for them, the roles that run the stores can use it, and the roles that
-- only read stock can see where it is held.

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
('warehouses', 'Warehouses', 'Warehouse', 405)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'warehouses', 1, 1, 1, 1, 1 FROM roles r WHERE r.role_key = 'super_admin'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

-- The warehouse manager opens and closes stores.
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'warehouses', 1, 1, 1, 1, 0 FROM roles r WHERE r.role_key = 'warehouse_manager'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1;

-- Logistics plans around them and may add one; finance and management read only.
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'warehouses', 1, 1, 1, 0, 0 FROM roles r WHERE r.role_key = 'logistics_manager'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'warehouses', 1, 0, 0, 0, 0 FROM roles r WHERE r.role_key IN ('finance', 'management')
ON DUPLICATE KEY UPDATE can_view = 1;
