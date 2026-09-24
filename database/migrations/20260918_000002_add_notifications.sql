CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    notification_key VARCHAR(80) NOT NULL UNIQUE,
    user_id INT UNSIGNED NULL,
    role_key VARCHAR(50) NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    link_route VARCHAR(80) NULL,
    severity ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (role_key),
    INDEX (is_read)
);
