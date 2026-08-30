-- ============================================================
-- Nouveau concept (nom à définir) — jeu de pronostics social, gratuit
-- Schéma MySQL v1 — base de discussion, à affiner ensemble
-- ============================================================

CREATE DATABASE IF NOT EXISTS pronosocial CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pronosocial;

-- ------------------------------------------------------------
-- Comptes utilisateurs (joueurs, PAS des gérants — l'admin passe
-- par l'API interne, jamais par une table "rôle" visible ici)
-- ------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pseudo VARCHAR(30) NOT NULL UNIQUE,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(255) NULL,
    points_totaux INT NOT NULL DEFAULT 0 COMMENT 'Cumul all-time, affiché sur le profil (indépendant des saisons)',
    shop_balance INT NOT NULL DEFAULT 0 COMMENT 'Points boutique (saisons verrouillées, cumulables)',
    equipped_bg VARCHAR(64) NOT NULL DEFAULT 'bg_default',
    equipped_name VARCHAR(64) NOT NULL DEFAULT 'name_default',
    serie_en_cours INT NOT NULL DEFAULT 0 COMMENT 'Nombre de pronostics corrects d''affilée, pour le bonus de série',
    actif TINYINT(1) NOT NULL DEFAULT 1,
    privacy_accepted_at DATETIME NULL,
    pseudo_changed_at DATETIME NULL,
    password_changed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE password_reset_tokens (
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

-- ------------------------------------------------------------
-- Amis (relation symétrique une fois acceptée)
-- ------------------------------------------------------------
CREATE TABLE friendships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ami_id INT NOT NULL,
    statut ENUM('en_attente', 'accepte', 'refuse') NOT NULL DEFAULT 'en_attente',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_friend_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_friend_ami FOREIGN KEY (ami_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_friend_pair (user_id, ami_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Communautés (privées par défaut ; une seule "Générale" publique
-- pour le classement global entre tous les joueurs)
-- ------------------------------------------------------------
CREATE TABLE communities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(512) NOT NULL COMMENT 'Nom chiffré (enc1:...)',
    description TEXT NULL COMMENT 'Description chiffrée (enc1:...)',
    est_generale TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Une seule ligne à 1 : la communauté globale',
    createur_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_community_createur FOREIGN KEY (createur_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE community_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    community_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('membre', 'moderateur') NOT NULL DEFAULT 'membre',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_member_community FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    CONSTRAINT fk_member_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_member (community_id, user_id)
) ENGINE=InnoDB;

-- Lien d'invitation à usage limité (rejoindre une communauté privée)
CREATE TABLE community_invites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    community_id INT NOT NULL,
    code_invite VARCHAR(20) NOT NULL UNIQUE,
    cree_par INT NOT NULL,
    usages_max INT NOT NULL DEFAULT 0 COMMENT '0 = illimité',
    usages_actuels INT NOT NULL DEFAULT 0,
    expire_le DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_invite_community FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    CONSTRAINT fk_invite_user FOREIGN KEY (cree_par) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Chat de communauté (simple, modérable)
-- ------------------------------------------------------------
CREATE TABLE community_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    community_id INT NOT NULL,
    user_id INT NOT NULL,
    contenu TEXT NOT NULL COMMENT 'Message chiffré (enc1:...)',
    supprime TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Soft-delete pour modération',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_msg_community FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    CONSTRAINT fk_msg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Matchs (importés depuis l'API externe, adaptateur à construire
-- comme l'était import_xml.php, mais orienté JSON)
-- ------------------------------------------------------------
CREATE TABLE matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    external_id VARCHAR(100) NULL UNIQUE COMMENT 'ID côté API externe, pour la synchro',
    sport VARCHAR(100) NOT NULL,
    competition VARCHAR(150) NULL,
    equipe_home VARCHAR(150) NOT NULL,
    equipe_away VARCHAR(150) NOT NULL,
    date_match DATETIME NOT NULL,
    statut ENUM('a_venir', 'en_cours', 'termine', 'annule') NOT NULL DEFAULT 'a_venir',
    resultat_1x2 ENUM('1', 'N', '2') NULL,
    score_home INT NULL,
    score_away INT NULL,
    buteur_reel VARCHAR(150) NULL COMMENT 'Nom du buteur retenu comme référence si marché "buteur" ouvert',
    prob_1 DECIMAL(5,1) NULL COMMENT 'Proba implicite domicile %',
    prob_n DECIMAL(5,1) NULL COMMENT 'Proba implicite nul %',
    prob_2 DECIMAL(5,1) NULL COMMENT 'Proba implicite extérieur %',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Marchés de pronostic proposés pour un match
-- (1 match peut avoir plusieurs marchés ouverts en parallèle)
-- ------------------------------------------------------------
CREATE TABLE prediction_markets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    type ENUM('1x2', 'buteur', 'score_exact') NOT NULL,
    points_si_correct INT NOT NULL COMMENT '1 pour 1x2, 2 pour buteur, 3 pour score exact (barème modifiable)',
    ferme_le DATETIME NOT NULL COMMENT 'Verrouillage des pronostics (coup d''envoi)',
    CONSTRAINT fk_market_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Options possibles pour un marché "buteur" (soit X, soit Y, soit Z...)
CREATE TABLE market_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    market_id INT NOT NULL,
    libelle VARCHAR(150) NOT NULL COMMENT 'Nom du joueur, ou "0-1", "2-1"... selon le type de marché',
    cote DECIMAL(6,2) NULL COMMENT 'Cote décimale (buteurs)',
    CONSTRAINT fk_option_market FOREIGN KEY (market_id) REFERENCES prediction_markets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Pronostics des utilisateurs (indépendants les uns des autres)
-- ------------------------------------------------------------
CREATE TABLE predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    market_id INT NOT NULL,
    reponse VARCHAR(150) NOT NULL COMMENT '"1"/"N"/"2", ou libellé du joueur, ou "2-1"...',
    statut ENUM('en_attente', 'correct', 'incorrect') NOT NULL DEFAULT 'en_attente',
    points_gagnes INT NOT NULL DEFAULT 0,
    resolved_at DATETIME NULL,
    result_notified TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pred_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_pred_market FOREIGN KEY (market_id) REFERENCES prediction_markets(id) ON DELETE CASCADE,
    UNIQUE KEY uq_pred (user_id, market_id) COMMENT 'Un seul pronostic par utilisateur et par marché'
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Saisons de classement (cycles de 14 jours, remise à zéro du classement)
-- Clôture auto + bonus podium (+5/+3/+1) — voir app/seasons.php
-- ------------------------------------------------------------
CREATE TABLE seasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    debut DATETIME NOT NULL,
    fin DATETIME NOT NULL,
    cloturee TINYINT(1) NOT NULL DEFAULT 0,
    shop_locked TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- Classement d'un utilisateur, par saison ET par communauté
-- (un même utilisateur a un score différent dans chaque communauté
--  où il est membre, plus un score dans la communauté "Générale")
CREATE TABLE season_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    season_id INT NOT NULL,
    community_id INT NOT NULL,
    user_id INT NOT NULL,
    points INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_score_season FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    CONSTRAINT fk_score_community FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    CONSTRAINT fk_score_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_score (season_id, community_id, user_id)
) ENGINE=InnoDB;

-- Récompenses attribuées en fin de saison (cosmétiques : badges, titres...)
CREATE TABLE season_rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    season_id INT NOT NULL,
    community_id INT NOT NULL,
    user_id INT NOT NULL,
    classement INT NOT NULL COMMENT '1, 2 ou 3',
    recompense VARCHAR(100) NOT NULL COMMENT 'Ex: "Badge Or", "Titre Oracle du mois"...',
    CONSTRAINT fk_reward_season FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    CONSTRAINT fk_reward_community FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    CONSTRAINT fk_reward_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Boutique : inventaire + journal des versements / achats
CREATE TABLE user_cosmetics (
    user_id INT NOT NULL,
    cosmetic_id VARCHAR(64) NOT NULL,
    purchased_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, cosmetic_id),
    KEY idx_user_cosmetics_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE shop_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount INT NOT NULL,
    reason VARCHAR(32) NOT NULL,
    ref VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_shop_ledger_user (user_id, created_at)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Clés API pour l'application d'administration externe (Python)
-- Jamais utilisées par le site public — uniquement par l'outil admin.
-- ------------------------------------------------------------
CREATE TABLE admin_api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(60) NOT NULL COMMENT 'Ex: "App admin bureau"',
    cle_hash VARCHAR(255) NOT NULL COMMENT 'Hash de la clé, jamais stockée en clair',
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    derniere_utilisation DATETIME NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Seed : la communauté "Générale", rejointe automatiquement par
-- chaque nouvel utilisateur au moment de l'inscription (voir
-- app/auth.php -> registerUser()).
-- ------------------------------------------------------------
INSERT INTO communities (nom, description, est_generale, createur_id)
VALUES ('Générale', 'Le classement global, tous les joueurs confondus.', 1, NULL);
