<?php
/**
 * Sync matchs en CLI — ne bloque pas les visiteurs du site.
 * Usage : php tools/sync_matches.php [--refresh]
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

$refresh = in_array('--refresh', $argv ?? [], true);

try {
    $pdo = getPDO();
    ensureMatchProbColumns($pdo);
    maintainSeasons($pdo);

    if (isSyncLockHeld()) {
        fwrite(STDERR, "Sync déjà en cours.\n");
        exit(2);
    }

    $result = runMatchImportSync($pdo, true, $refresh);
    maintainMatchLifecycle($pdo, false);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($result['ran'] ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
