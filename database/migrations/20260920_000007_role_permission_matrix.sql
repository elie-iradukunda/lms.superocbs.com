-- Moves the role/route matrix out of bootstrap.php and into the database so the
-- "Users and permissions" screen can actually change what a role may do.

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, p.permission_key, 1, 1, 1, 1, 1
FROM roles r CROSS JOIN permissions p
WHERE r.role_key = 'super_admin'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1, can_delete = 1, can_approve = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, v.k, v.vw, v.cr, v.ed, v.dl, v.ap FROM roles r JOIN (
              SELECT 'dashboard' k, 1 vw, 0 cr, 0 ed, 0 dl, 0 ap
    UNION ALL SELECT 'vehicles', 1, 1, 1, 0, 0
    UNION ALL SELECT 'drivers', 1, 1, 1, 0, 0
    UNION ALL SELECT 'maintenance', 1, 1, 1, 0, 1
    UNION ALL SELECT 'vehicle_documents', 1, 1, 1, 0, 0
    UNION ALL SELECT 'requests', 1, 1, 1, 1, 1
    UNION ALL SELECT 'trips', 1, 1, 1, 1, 1
    UNION ALL SELECT 'shipments', 1, 1, 1, 1, 0
    UNION ALL SELECT 'deliveries', 1, 1, 1, 1, 0
    UNION ALL SELECT 'customers', 1, 1, 1, 0, 0
    UNION ALL SELECT 'rates', 1, 0, 0, 0, 0
    UNION ALL SELECT 'fuel', 1, 1, 1, 0, 0
    UNION ALL SELECT 'expenses', 1, 1, 1, 0, 0
    UNION ALL SELECT 'warehouse', 1, 1, 1, 0, 0
    UNION ALL SELECT 'movements', 1, 1, 0, 0, 0
    UNION ALL SELECT 'procurement', 1, 1, 1, 0, 0
    UNION ALL SELECT 'suppliers', 1, 1, 1, 0, 0
    UNION ALL SELECT 'reports', 1, 1, 1, 0, 0
    UNION ALL SELECT 'audit', 1, 0, 0, 0, 0
) v WHERE r.role_key = 'logistics_manager'
ON DUPLICATE KEY UPDATE can_view = VALUES(can_view), can_create = VALUES(can_create), can_edit = VALUES(can_edit), can_delete = VALUES(can_delete), can_approve = VALUES(can_approve);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, v.k, v.vw, v.cr, v.ed, v.dl, v.ap FROM roles r JOIN (
              SELECT 'dashboard' k, 1 vw, 0 cr, 0 ed, 0 dl, 0 ap
    UNION ALL SELECT 'vehicles', 1, 1, 1, 1, 0
    UNION ALL SELECT 'drivers', 1, 1, 1, 1, 0
    UNION ALL SELECT 'maintenance', 1, 1, 1, 1, 1
    UNION ALL SELECT 'vehicle_documents', 1, 1, 1, 1, 0
    UNION ALL SELECT 'fuel', 1, 1, 1, 0, 0
    UNION ALL SELECT 'trips', 1, 0, 0, 0, 0
    UNION ALL SELECT 'reports', 1, 0, 0, 0, 0
) v WHERE r.role_key = 'fleet_manager'
ON DUPLICATE KEY UPDATE can_view = VALUES(can_view), can_create = VALUES(can_create), can_edit = VALUES(can_edit), can_delete = VALUES(can_delete), can_approve = VALUES(can_approve);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, v.k, v.vw, v.cr, v.ed, v.dl, v.ap FROM roles r JOIN (
              SELECT 'dashboard' k, 1 vw, 0 cr, 0 ed, 0 dl, 0 ap
    UNION ALL SELECT 'warehouse', 1, 1, 1, 1, 0
    UNION ALL SELECT 'movements', 1, 1, 0, 0, 0
    UNION ALL SELECT 'procurement', 1, 1, 1, 1, 0
    UNION ALL SELECT 'suppliers', 1, 1, 1, 1, 0
    UNION ALL SELECT 'requests', 1, 1, 1, 0, 0
    UNION ALL SELECT 'shipments', 1, 1, 1, 0, 0
    UNION ALL SELECT 'reports', 1, 0, 0, 0, 0
) v WHERE r.role_key = 'warehouse_manager'
ON DUPLICATE KEY UPDATE can_view = VALUES(can_view), can_create = VALUES(can_create), can_edit = VALUES(can_edit), can_delete = VALUES(can_delete), can_approve = VALUES(can_approve);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, v.k, v.vw, v.cr, v.ed, v.dl, v.ap FROM roles r JOIN (
              SELECT 'dashboard' k, 1 vw, 0 cr, 0 ed, 0 dl, 0 ap
    UNION ALL SELECT 'trips', 1, 0, 1, 0, 0
    -- A driver closes their own delivery, so they need the approve right that
    -- gates the "Mark delivered" and "Record failure" buttons.
    UNION ALL SELECT 'deliveries', 1, 0, 1, 0, 1
    UNION ALL SELECT 'shipments', 1, 0, 0, 0, 0
    UNION ALL SELECT 'fuel', 1, 1, 0, 0, 0
) v WHERE r.role_key = 'driver'
ON DUPLICATE KEY UPDATE can_view = VALUES(can_view), can_create = VALUES(can_create), can_edit = VALUES(can_edit), can_delete = VALUES(can_delete), can_approve = VALUES(can_approve);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, v.k, v.vw, v.cr, v.ed, v.dl, v.ap FROM roles r JOIN (
              SELECT 'dashboard' k, 1 vw, 0 cr, 0 ed, 0 dl, 0 ap
    UNION ALL SELECT 'fuel', 1, 0, 1, 0, 0
    UNION ALL SELECT 'expenses', 1, 1, 1, 1, 1
    UNION ALL SELECT 'procurement', 1, 0, 1, 0, 1
    UNION ALL SELECT 'maintenance', 1, 0, 0, 0, 1
    UNION ALL SELECT 'customers', 1, 1, 1, 0, 0
    UNION ALL SELECT 'rates', 1, 1, 1, 1, 0
    UNION ALL SELECT 'invoices', 1, 1, 1, 1, 1
    UNION ALL SELECT 'suppliers', 1, 0, 0, 0, 0
    UNION ALL SELECT 'reports', 1, 1, 1, 0, 0
) v WHERE r.role_key = 'finance'
ON DUPLICATE KEY UPDATE can_view = VALUES(can_view), can_create = VALUES(can_create), can_edit = VALUES(can_edit), can_delete = VALUES(can_delete), can_approve = VALUES(can_approve);

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, v.k, v.vw, v.cr, v.ed, v.dl, v.ap FROM roles r JOIN (
              SELECT 'dashboard' k, 1 vw, 0 cr, 0 ed, 0 dl, 0 ap
    UNION ALL SELECT 'reports', 1, 0, 0, 0, 0
    UNION ALL SELECT 'customers', 1, 0, 0, 0, 0
    UNION ALL SELECT 'invoices', 1, 0, 0, 0, 0
    UNION ALL SELECT 'trips', 1, 0, 0, 0, 0
    UNION ALL SELECT 'audit', 1, 0, 0, 0, 0
) v WHERE r.role_key = 'management'
ON DUPLICATE KEY UPDATE can_view = VALUES(can_view), can_create = VALUES(can_create), can_edit = VALUES(can_edit), can_delete = VALUES(can_delete), can_approve = VALUES(can_approve);
