ALTER TABLE drivers
    ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED NULL AFTER id;

CREATE UNIQUE INDEX IF NOT EXISTS uq_drivers_user_id ON drivers (user_id);
