-- The ERI group runs on one small set of roles, the same in every company:
-- Admin, Operational Manager, Finance Manager, Managing Director, Cashier,
-- Store & garage keeper, Driver, Clearing agent (Declaration Manager), plus
-- the Supplier and Customer portal logins.
--
-- The earlier build also had HR, a separate Mechanic/Garage, a Sister-company
-- supervisor, and a row of clearance desk roles (front office, customer care,
-- accounts receivable/payable, shipping clerk, declaration assistant, port
-- clerk). Those are folded in: HR and sister requests into the Operational
-- Manager, the mechanic into the Store & garage keeper, and the clearance desk
-- into the Clearing agent. This moves any people on the retired roles across
-- and removes the roles. The default permissions are then rewritten by the
-- permission-matrix migration that runs last.
--
-- Safe to run more than once.

-- Move people off the retired roles onto the one that now does their work.
UPDATE users u
    INNER JOIN roles old ON old.id = u.role_id
    INNER JOIN roles new ON new.role_key = CASE old.role_key
        WHEN 'workshop' THEN 'warehouse_manager'
        WHEN 'hr' THEN 'logistics_manager'
        WHEN 'sister_supervisor' THEN 'logistics_manager'
        WHEN 'front_office' THEN 'clearance_officer'
        WHEN 'customer_care' THEN 'clearance_officer'
        WHEN 'accounts_receivable' THEN 'finance'
        WHEN 'accounts_payable' THEN 'finance'
        WHEN 'shipping_clerk' THEN 'clearance_officer'
        WHEN 'declaration_assistant' THEN 'clearance_officer'
        WHEN 'port_clerk' THEN 'clearance_officer'
    END
    SET u.role_id = new.id
    WHERE old.role_key IN ('workshop','hr','sister_supervisor','front_office','customer_care','accounts_receivable','accounts_payable','shipping_clerk','declaration_assistant','port_clerk');

-- Drop the retired roles' permissions, then the roles themselves.
DELETE rp FROM role_permissions rp INNER JOIN roles r ON r.id = rp.role_id
    WHERE r.role_key IN ('workshop','hr','sister_supervisor','front_office','customer_care','accounts_receivable','accounts_payable','shipping_clerk','declaration_assistant','port_clerk');

DELETE FROM roles
    WHERE role_key IN ('workshop','hr','sister_supervisor','front_office','customer_care','accounts_receivable','accounts_payable','shipping_clerk','declaration_assistant','port_clerk');

-- The storekeeper now keeps the garage too.
UPDATE roles SET role_name = 'Store & garage keeper' WHERE role_key = 'warehouse_manager';
