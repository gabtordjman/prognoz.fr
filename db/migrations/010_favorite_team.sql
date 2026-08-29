-- Équipe préférée (également ensureFavoriteTeamSchema au boot PHP).
-- MySQL 5.7+ : ALTER conditionnel via procedure légère côté PHP ; ce fichier est documentaire / manuel.

-- users.equipe_favorie
-- prediction_markets.type + fav_team
-- fav_team_notified

CREATE TABLE IF NOT EXISTS fav_team_notified (
    user_id INT NOT NULL,
    match_id INT NOT NULL,
    notified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, match_id),
    KEY idx_fav_notif_match (match_id),
    CONSTRAINT fk_fav_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_fav_notif_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
