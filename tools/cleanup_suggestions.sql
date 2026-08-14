-- Prognoz — diagnostic + ménage (hors table users)
-- Généré d’après l’analyse du dump du 11/08/2026.
-- À exécuter sur une COPIE / après backup. Lire chaque bloc avant d’exécuter.

-- =============================================================================
-- 1) DIAGNOSTIC (lecture seule)
-- =============================================================================

-- Pronos encore « en_attente »
SELECT p.statut, COUNT(*) AS n
FROM predictions p
GROUP BY p.statut;

-- Les « 19 » : ouverts (ticket) vs match déjà terminé sans score
SELECT
  SUM(CASE
    WHEN m.statut IN ('a_venir','en_cours')
         AND m.date_match <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY)
    THEN 1 ELSE 0 END) AS ouverts_ticket,
  SUM(CASE
    WHEN NOT (
      m.statut IN ('a_venir','en_cours')
      AND m.date_match <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY)
    ) THEN 1 ELSE 0 END) AS hors_ticket_attente_resultat
FROM predictions p
JOIN prediction_markets pm ON pm.id = p.market_id
JOIN matches m ON m.id = pm.match_id
WHERE p.statut = 'en_attente';

-- Matchs terminés SANS score mais AVEC prono en attente (= ce que l’admin doit saisir)
SELECT m.id, m.equipe_home, m.equipe_away, m.date_match, m.statut,
       m.score_home, m.score_away, COUNT(p.id) AS pending
FROM matches m
JOIN prediction_markets pm ON pm.match_id = m.id
JOIN predictions p ON p.market_id = pm.id AND p.statut = 'en_attente'
WHERE m.statut = 'termine'
  AND (m.resultat_1x2 IS NULL OR m.resultat_1x2 = '')
GROUP BY m.id
ORDER BY m.date_match;

-- Volume BDD
SELECT
  (SELECT COUNT(*) FROM matches) AS matches,
  (SELECT COUNT(*) FROM prediction_markets) AS markets,
  (SELECT COUNT(*) FROM predictions) AS predictions,
  (SELECT COUNT(*) FROM market_options) AS market_options,
  (SELECT COUNT(*) FROM matches WHERE statut='termine' AND score_home IS NULL) AS termine_sans_score;

-- =============================================================================
-- 2) MÉNAGE SÛR — matchs terminés/annulés SANS aucun prono (> 7 jours)
--    (déchets de synchro API — ne touche PAS aux comptes joueurs)
-- =============================================================================

-- Aperçu
SELECT COUNT(*) AS a_supprimer
FROM matches m
WHERE m.statut IN ('termine','annule')
  AND m.date_match < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
  AND NOT EXISTS (
    SELECT 1 FROM prediction_markets pm
    JOIN predictions p ON p.market_id = pm.id
    WHERE pm.match_id = m.id
  );

-- Exécuter si le COUNT te convient :
-- DELETE m FROM matches m
-- WHERE m.statut IN ('termine','annule')
--   AND m.date_match < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
--   AND NOT EXISTS (
--     SELECT 1 FROM prediction_markets pm
--     JOIN predictions p ON p.market_id = pm.id
--     WHERE pm.match_id = m.id
--   );

-- Marchés orphelins (plus de match) — en théorie CASCADE, filet de sécurité
-- DELETE pm FROM prediction_markets pm
-- LEFT JOIN matches m ON m.id = pm.match_id
-- WHERE m.id IS NULL;

-- Options score_exact (régénérées au besoin) — déjà fait par la purge admin
-- DELETE mo FROM market_options mo
-- JOIN prediction_markets pm ON pm.id = mo.market_id
-- WHERE pm.type = 'score_exact';

-- =============================================================================
-- 3) OPTIONNEL — annuler les pronos bloqués trop vieux sans résultat API
--    (le code le fait déjà auto après RESULT_MAX_WAIT_DAYS = 4 jours)
--    Utile seulement si tu veux forcer maintenant.
-- =============================================================================

-- Aperçu (pronos en_attente, match > 4 j, pas de 1x2)
SELECT COUNT(*) AS a_annuler
FROM predictions p
JOIN prediction_markets pm ON pm.id = p.market_id
JOIN matches m ON m.id = pm.match_id
WHERE p.statut = 'en_attente'
  AND m.date_match < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 4 DAY)
  AND (m.resultat_1x2 IS NULL OR m.resultat_1x2 = '');

-- UPDATE predictions p
-- JOIN prediction_markets pm ON pm.id = p.market_id
-- JOIN matches m ON m.id = pm.match_id
-- SET p.statut = 'annule', p.points_gagnes = 0, p.resolved_at = UTC_TIMESTAMP()
-- WHERE p.statut = 'en_attente'
--   AND m.date_match < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 4 DAY)
--   AND (m.resultat_1x2 IS NULL OR m.resultat_1x2 = '');

-- =============================================================================
-- RECOMMANDATION MÉTIER pour tes 14 « hors ticket »
-- =============================================================================
-- Ce ne sont PAS des lignes à effacer : ce sont des matchs du jour (ex. Nijmegen,
-- Celje, Sturm Graz…) marqués termine SANS score API.
-- → Admin → Résultats & scores : saisis les scores, OU attends la synchro scores,
--   OU au bout de 4 jours le site annule automatiquement (0 pt).
