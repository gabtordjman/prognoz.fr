-- Historique pronos, reset MDP, consentement inscription
-- Compatible MySQL 8 (pas de IF NOT EXISTS sur ADD COLUMN — syntaxe MariaDB)
USE pronosocial;

-- predictions.resolved_at
SET @sql = (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'predictions'
              AND COLUMN_NAME = 'resolved_at'
        ),
        'SELECT ''predictions.resolved_at déjà présente''',
        'ALTER TABLE predictions ADD COLUMN resolved_at DATETIME NULL AFTER points_gagnes'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- predictions.result_notified
SET @sql = (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'predictions'
              AND COLUMN_NAME = 'result_notified'
        ),
        'SELECT ''predictions.result_notified déjà présente''',
        'ALTER TABLE predictions ADD COLUMN result_notified TINYINT(1) NOT NULL DEFAULT 0 AFTER resolved_at'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- users.privacy_accepted_at
SET @sql = (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = 'privacy_accepted_at'
        ),
        'SELECT ''users.privacy_accepted_at déjà présente''',
        'ALTER TABLE users ADD COLUMN privacy_accepted_at DATETIME NULL AFTER created_at'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_reset_token (token_hash),
    INDEX idx_reset_user (user_id)
) ENGINE=InnoDB;
