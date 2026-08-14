<?php
/**
 * Purge options score_exact / buteurs périmés / matchs orphelins.
 * Usage : php tools/prune_db.php
 */
define('APP_BOOT', true);

$root = dirname(__DIR__);
require_once $root . '/app/env.php';
loadEnvFile($root . '/.env');
require_once $root . '/app/config.php';
require_once $root . '/app/helpers.php';
require_once $root . '/app/db.php';
require_once $root . '/app/encryption.php';
require_once $root . '/app/odds_api.php';
require_once $root . '/app/matches.php';

try {
    $pdo = getPDO();
    $result = pruneStaleMatchData($pdo);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
