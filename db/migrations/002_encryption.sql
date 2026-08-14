-- Prognoz — préparation colonnes pour chiffrement (noms communautés + messages chat)
-- Le chiffrement des données existantes : php scripts/encrypt_sensitive_data.php

USE pronosocial;

ALTER TABLE communities
    MODIFY nom VARCHAR(512) NOT NULL COMMENT 'Nom chiffré (enc1:...)';

ALTER TABLE communities
    MODIFY description TEXT NULL COMMENT 'Description chiffrée (enc1:...)';

ALTER TABLE community_messages
    MODIFY contenu TEXT NOT NULL COMMENT 'Message chiffré (enc1:...)';
