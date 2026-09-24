-- A driver closes their own delivery from the road, so they need the approve
-- right that gates the "Mark delivered" and "Record failure" buttons.
--
-- The same grant is in 20260920_000007, but a database that already ran that
-- migration will never re-run it, so the change is repeated here for installs
-- that were set up before the fix.

UPDATE role_permissions rp
  INNER JOIN roles r ON r.id = rp.role_id
    SET rp.can_approve = 1
  WHERE r.role_key = 'driver'
    AND rp.permission_key = 'deliveries';

-- If the row is missing entirely (a very early install), create it.
INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
SELECT r.id, 'deliveries', 1, 0, 1, 0, 1
FROM roles r
WHERE r.role_key = 'driver'
ON DUPLICATE KEY UPDATE can_view = 1, can_edit = 1, can_approve = 1;
