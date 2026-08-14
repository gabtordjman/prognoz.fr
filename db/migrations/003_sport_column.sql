-- Clés sport API (soccer_epl, etc.) dépassent 30 caractères
ALTER TABLE matches MODIFY sport VARCHAR(100) NOT NULL;
