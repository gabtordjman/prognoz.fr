<?php
/**
 * Résolution des résultats et attribution des points — CLI.
 * Usage : php tools/resolve_results.php [--force]
 *
 * --force ignore le throttle de SCORES_SYNC_INTERVAL_SECONDS.
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

$force = in_array('--force', $argv ?? [], true);

try {
    $pdo = getPDO();
    ensureMatchProbColumns($pdo);
    ensurePredictionHistorySchema($pdo);
    maintainSeasons($pdo);

    $result = resolveMatchResults($pdo, $force);
    $result['closed'] = closeExpiredMatches($pdo);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
