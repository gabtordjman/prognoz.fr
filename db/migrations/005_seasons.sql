-- Migration saisons — Prognoz
-- À exécuter une fois sur la BDD prod (phpMyAdmin ou mysql CLI).
-- Calendrier : saison en cours se termine le 06/08/2026 à 23:59:59 (heure serveur / Paris).

USE pronosocial;

-- Tables (no-op si déjà créées via schema.sql)
CREATE TABLE IF NOT EXISTS seasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    debut DATETIME NOT NULL,
    fin DATETIME NOT NULL,
    cloturee TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS season_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    season_id INT NOT NULL,
    community_id INT NOT NULL,
    user_id INT NOT NULL,
    points INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_score (season_id, community_id, user_id),
    KEY idx_season_community (season_id, community_id, points)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS season_rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    season_id INT NOT NULL,
    community_id INT NOT NULL,
    user_id INT NOT NULL,
    classement INT NOT NULL COMMENT '1, 2 ou 3',
    recompense VARCHAR(100) NOT NULL,
    KEY idx_user_season (user_id, season_id)
) ENGINE=InnoDB;

-- 1) Clôturer les saisons déjà expirées
UPDATE seasons SET cloturee = 1 WHERE cloturee = 0 AND fin <= NOW();

-- 2) S'il reste plusieurs saisons « ouvertes », ne garder que la plus récente
UPDATE seasons SET cloturee = 1
WHERE cloturee = 0 AND fin > NOW()
  AND id NOT IN (
    SELECT id FROM (
        SELECT id FROM seasons WHERE cloturee = 0 AND fin > NOW() ORDER BY debut DESC LIMIT 1
    ) AS keep_one
);

-- 3) Ajuster la fin de la saison active → dans 10 jours pile (ou créer la saison)
UPDATE seasons s
INNER JOIN (
    SELECT id FROM seasons WHERE cloturee = 0 AND fin > NOW() ORDER BY debut DESC LIMIT 1
) active ON active.id = s.id
SET s.fin = DATE_ADD(NOW(), INTERVAL 10 DAY);

INSERT INTO seasons (debut, fin, cloturee)
SELECT NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 0
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM seasons WHERE cloturee = 0 AND fin > NOW()
);

-- Vérification
SELECT id, debut, fin, cloturee,
       TIMESTAMPDIFF(DAY, NOW(), fin) AS jours_restants
FROM seasons
ORDER BY id DESC
LIMIT 3;
