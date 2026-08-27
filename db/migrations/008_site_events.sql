-- Événements site + bio / sport favori (v1.1)
-- Appliqué aussi automatiquement au boot PHP (ensure*).

CREATE TABLE IF NOT EXISTS site_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(120) NOT NULL,
    message VARCHAR(280) NOT NULL,
    type VARCHAR(32) NOT NULL,
    config_json TEXT NULL,
    theme VARCHAR(32) NOT NULL DEFAULT 'default',
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    published TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_events_window (enabled, published, starts_at, ends_at),
    KEY idx_events_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
