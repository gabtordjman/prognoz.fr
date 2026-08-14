-- Changement pseudo / mot de passe avec délai de 3 semaines
USE pronosocial;

SET @sql = (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = 'pseudo_changed_at'
        ),
        'SELECT ''users.pseudo_changed_at déjà présente''',
        'ALTER TABLE users ADD COLUMN pseudo_changed_at DATETIME NULL AFTER privacy_accepted_at'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = 'password_changed_at'
        ),
        'SELECT ''users.password_changed_at déjà présente''',
        'ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL AFTER pseudo_changed_at'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
