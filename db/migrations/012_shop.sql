-- Boutique : solde verrouillé en fin de saison + inventaire de cosmétiques.
-- Aussi appliqué au boot via ensureShopSchema() + maintainShopLocks().

ALTER TABLE users
    ADD COLUMN shop_balance INT NOT NULL DEFAULT 0 COMMENT 'Points boutique (saisons verrouillées, cumulables)' AFTER points_totaux,
    ADD COLUMN equipped_bg VARCHAR(64) NOT NULL DEFAULT 'bg_default' AFTER shop_balance,
    ADD COLUMN equipped_name VARCHAR(64) NOT NULL DEFAULT 'name_default' AFTER equipped_bg;

ALTER TABLE seasons
    ADD COLUMN shop_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER cloturee;

CREATE TABLE IF NOT EXISTS user_cosmetics (
    user_id INT NOT NULL,
    cosmetic_id VARCHAR(64) NOT NULL,
    purchased_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, cosmetic_id),
    KEY idx_user_cosmetics_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount INT NOT NULL,
    reason VARCHAR(32) NOT NULL,
    ref VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_shop_ledger_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
