-- Prognoz — préparation colonnes pour chiffrement (noms communautés + messages chat)
-- Exécuter dans phpMyAdmin ou : mysql -u root pronosocial < sql/migrate_encryption.sql
--
-- IMPORTANT : le chiffrement des données existantes se fait avec le script PHP
--   php scripts/encrypt_sensitive_data.php
-- (SQL seul ne peut pas chiffrer — il faut la clé APP_ENCRYPTION_KEY du .env)

USE pronosocial;

ALTER TABLE communities
    MODIFY nom VARCHAR(512) NOT NULL COMMENT 'Nom chiffré (enc1:...)';

ALTER TABLE communities
    MODIFY description TEXT NULL COMMENT 'Description chiffrée (enc1:...)';

ALTER TABLE community_messages
    MODIFY contenu TEXT NOT NULL COMMENT 'Message chiffré (enc1:...)';
