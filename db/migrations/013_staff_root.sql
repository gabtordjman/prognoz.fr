-- Thème Root (admin, non achetable). Aussi appliqué au boot via grantStaffShopTheme().

INSERT IGNORE INTO user_cosmetics (user_id, cosmetic_id) VALUES
    (2, 'bg_root'),
    (2, 'name_root');

UPDATE users SET equipped_bg = 'bg_root', equipped_name = 'name_root' WHERE id = 2;

INSERT INTO shop_ledger (user_id, amount, reason, ref)
SELECT 2, 0, 'staff', 'bg_root'
WHERE NOT EXISTS (
    SELECT 1 FROM shop_ledger WHERE user_id = 2 AND reason = 'staff' AND ref = 'bg_root'
);
