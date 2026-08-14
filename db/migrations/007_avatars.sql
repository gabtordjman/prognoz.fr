-- Photos de profil (colonne déjà dans schema.sql — ajout défensif)
USE pronosocial;

SET @sql = (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = 'avatar_url'
        ),
        'SELECT ''users.avatar_url déjà présente''',
        'ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) NULL AFTER password_hash'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
