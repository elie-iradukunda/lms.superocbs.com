-- prvg: 1 = privileged (may switch into any role), 2 = standard (default, cannot switch).
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS prvg TINYINT UNSIGNED NOT NULL DEFAULT 2 AFTER status;

ALTER TABLE users
    ADD CONSTRAINT IF NOT EXISTS chk_users_prvg CHECK (prvg IN (1, 2));

UPDATE users SET prvg = 1 WHERE email = 'admin@itec.rw';
