-- Who does what in a cross-border job, as walked through for the client:
--   * the driver files the costs of his own trip (allowance, tolls, weighbridge),
--     and Finance approves them;
--   * the warehouse hands a customer's load over at the depot.
-- Safe to run more than once.

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'expenses', 1, 1, 0, 0, 0 FROM roles r WHERE r.role_key = 'driver'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1;

INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'deliveries', 1, 0, 1, 0, 1 FROM roles r WHERE r.role_key = 'warehouse_manager'
ON DUPLICATE KEY UPDATE can_view = 1, can_edit = 1, can_approve = 1;

-- The warehouse checks the papers that travel with a load, and finance pays the border.
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'crossings', 1, 0, 0, 0, 0 FROM roles r WHERE r.role_key = 'warehouse_manager'
ON DUPLICATE KEY UPDATE can_view = 1;
