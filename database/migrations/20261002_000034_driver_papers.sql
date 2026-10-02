-- Driver papers (passport, licence, national ID, permits) become a page of
-- their own under Fleet management, so they can be added from the screen.
-- The fleet manager keeps them; a driver sees only his own.
-- Safe to run more than once.

INSERT INTO permissions (permission_key, permission_label, permission_group, sort_order) VALUES
    ('driver_documents', 'Driver papers', 'Fleet', 35)
ON DUPLICATE KEY UPDATE permission_label = VALUES(permission_label), permission_group = VALUES(permission_group), sort_order = VALUES(sort_order);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'driver_documents', 1, 1, 1, 1, 1 FROM roles r WHERE r.role_key IN ('super_admin', 'fleet_manager')
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'driver_documents', 1, 1, 1, 0, 0 FROM roles r WHERE r.role_key = 'logistics_manager'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'driver_documents', 1, 0, 0, 0, 0 FROM roles r WHERE r.role_key = 'driver'
ON DUPLICATE KEY UPDATE can_view = 1;
