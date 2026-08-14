<?php
/**
 * Diagnostic des pronos bloqués en « en_attente ».
 * Usage : php tools/diagnose_results.php
 *
 * Lecture seule : aucun appel API, aucune écriture en base.
 */
define('APP_BOOT', true);

$root = dirname(__DIR__);
require_once $root . '/app/env.php';
loadEnvFile($root . '/.env');
require_once $root . '/app/config.php';
require_once $root . '/app/helpers.php';
require_once $root . '/app/db.php';
require_once $root . '/app/encryption.php';
require_once $root . '/app/push.php';
require_once $root . '/app/user_predictions.php';
require_once $root . '/app/seasons.php';
require_once $root . '/app/odds_api.php';
require_once $root . '/app/scoring.php';
require_once $root . '/app/matches.php';

function line(string $label, $value): void
{
    printf("  %-34s %s\n", $label, $value);
}

try {
    $pdo = getPDO();

    echo "=== Quota API ===\n";
    $quota = oddsQuotaState();
    line('Clé API configurée', oddsApiConfigured() ? 'oui' : 'NON');
    line('Requêtes restantes', $quota['remaining'] ?? 'inconnu (aucun appel enregistré)');
    line('Requêtes utilisées', $quota['used'] ?? 'inconnu');
    line('Coût du dernier appel', $quota['last_cost'] ?? 'inconnu');
    line(
        'Dernière mise à jour',
        $quota['updated_at'] ? date('Y-m-d H:i:s', (int) $quota['updated_at']) : 'jamais'
    );
    if (!empty($quota['last_error'])) {
        line('DERNIÈRE ERREUR', $quota['last_error']);
        line('Survenue le', date('Y-m-d H:i:s', (int) $quota['last_error_at']));
    }
    $budget = scoresSportsBudget();
    line('Budget scores cette passe', $budget . ' ligue(s) (= ' . ($budget * 2) . ' crédits max)');
    if ($quota['remaining'] !== null && (int) $quota['remaining'] <= 0) {
        echo "  >>> QUOTA MORT : saisie manuelle dans Paramètres (0 crédit).\n";
    } elseif ($quota['remaining'] !== null && (int) $quota['remaining'] <= (int) ODDS_QUOTA_RESERVE_ODDS) {
        echo "  >>> Mode économie : scores OK, cotes/buteurs coupés.\n";
    }

    echo "\n=== Pronos ===\n";
    $stats = countPendingPredictions($pdo);
    line('En attente (total)', $stats['pending']);
    line('Dont matchs déjà joués', $stats['stuck']);
    line('Joueurs concernés', $stats['users']);

    echo "\n=== Matchs joués sans résultat, par sport ===\n";
    $maxWait  = (int) RESULT_MAX_WAIT_DAYS;
    $readyMin = (int) MATCH_RESULT_READY_MINUTES;
    $stmt = $pdo->query(
        "SELECT m.sport,
                COUNT(DISTINCT m.id) AS matchs,
                COUNT(p.id) AS pronos,
                MIN(m.date_match) AS plus_ancien,
                MAX(m.date_match) AS plus_recent
         FROM matches m
         LEFT JOIN prediction_markets pm ON pm.match_id = m.id
         LEFT JOIN predictions p ON p.market_id = pm.id AND p.statut = 'en_attente'
         WHERE m.date_match < DATE_SUB(UTC_TIMESTAMP(), INTERVAL {$readyMin} MINUTE)
           AND (m.resultat_1x2 IS NULL OR m.resultat_1x2 = '')
         GROUP BY m.sport
         HAVING pronos > 0
         ORDER BY pronos DESC"
    );
    $rows = $stmt->fetchAll();
    if (empty($rows)) {
        echo "  Aucun — tout est résolu.\n";
    }
    foreach ($rows as $row) {
        $ageDays = (int) floor((time() - strtotime((string) $row['plus_ancien'])) / 86400);
        printf(
            "  %-38s %3d match(s)  %3d prono(s)  du %s au %s%s\n",
            $row['sport'],
            (int) $row['matchs'],
            (int) $row['pronos'],
            substr((string) $row['plus_ancien'], 0, 16),
            substr((string) $row['plus_recent'], 0, 16),
            $ageDays > $maxWait ? '  [HORS FENÊTRE API]' : ''
        );
    }

    echo "\n=== Ligues qui seront interrogées à la prochaine passe ===\n";
    $budget = scoresSportsBudget();
    $sports = $budget > 0 ? sportsAwaitingResults($pdo, $budget) : [];
    if ($budget <= 0) {
        echo "  Aucune — budget = 0 (quota insuffisant).\n";
    } elseif (empty($sports)) {
        echo "  Aucune.\n";
    }
    foreach ($sports as $sportKey) {
        printf("  %s\n", $sportKey);
    }

    echo "\n=== Dernières passes ===\n";
    foreach ([
        'Résultats / scores' => scoresSyncLastRunPath(),
        'Import matchs'      => syncLastRunPath(),
        'Cotes'              => oddsSyncLastRunPath(),
    ] as $label => $path) {
        $ts = is_file($path) ? (int) @file_get_contents($path) : 0;
        line($label, $ts > 0 ? date('Y-m-d H:i:s', $ts) . ' (il y a ' . round((time() - $ts) / 60) . ' min)' : 'jamais');
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
