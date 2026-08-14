-- Colonnes probabilités implicites (à exécuter une fois)
ALTER TABLE matches
    ADD COLUMN prob_1 DECIMAL(5,1) NULL COMMENT 'Proba domicile %',
    ADD COLUMN prob_n DECIMAL(5,1) NULL COMMENT 'Proba nul %',
    ADD COLUMN prob_2 DECIMAL(5,1) NULL COMMENT 'Proba extérieur %';
