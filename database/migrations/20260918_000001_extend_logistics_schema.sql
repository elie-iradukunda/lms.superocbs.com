ALTER TABLE vehicles
    ADD COLUMN IF NOT EXISTS assigned_driver_id INT UNSIGNED NULL AFTER model;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS department VARCHAR(80) NULL AFTER phone;

ALTER TABLE fuel_records
    ADD COLUMN IF NOT EXISTS reference_code VARCHAR(32) NULL AFTER id;

ALTER TABLE expenses
    ADD COLUMN IF NOT EXISTS reference_code VARCHAR(32) NULL AFTER id,
    ADD COLUMN IF NOT EXISTS submitted_by INT UNSIGNED NULL AFTER amount,
    ADD COLUMN IF NOT EXISTS status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' AFTER submitted_by;

CREATE TABLE IF NOT EXISTS reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_name VARCHAR(150) NOT NULL UNIQUE,
    period_label VARCHAR(100) NOT NULL,
    owner_name VARCHAR(100) NOT NULL,
    last_generated_at DATETIME NULL,
    format_label VARCHAR(20) NOT NULL DEFAULT 'CSV',
    action_label VARCHAR(40) NOT NULL DEFAULT 'View'
);
