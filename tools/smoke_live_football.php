<?php
/**
 * Smoke test API-Football (serveur) — 1 crédit max.
 *
 * Usage :
 *   php tools/smoke_live_football.php
 *   php tools/smoke_live_football.php --sync
 *
 * Ne committe jamais la clé. Lit uniquement .env.
 */
declare(strict_types=1);

define('APP_BOOT', true);

$root = dirname(__DIR__);
$doSync = in_array('--sync', $argv ?? [], true);

$needed = [
    'app/config.php',
    'app/live_football.php',
    'public/api/live_scores.php',
];
$missing = [];
foreach ($needed as $rel) {
    if (!is_file($root . '/' . $rel)) {
        $missing[] = $rel;
    }
}

echo "=== smoke live football ===\n";

if ($missing !== []) {
    echo "FAIL : fichiers feature absents sur ce serveur :\n";
    foreach ($missing as $rel) {
        echo "  - {$rel}\n";
    }
    echo "Le .env a la clé, mais le code live n’est pas déployé.\n";
    echo "Fichiers à déployer (entre autres) :\n";
    echo "  app/config.php  app/live_football.php  app/bootstrap.php\n";
    echo "  app/matches.php  public/index.php  public/api/live_scores.php\n";
    echo "  public/api/sync.php  public/assets/js/live-scores.js  public/assets/css/style.css\n";
    exit(1);
}

require_once $root . '/app/env.php';
loadEnvFile($root . '/.env');
require_once $root . '/app/config.php';
require_once $root . '/app/helpers.php';
require_once $root . '/app/db.php';
require_once $root . '/app/scoring.php';
require_once $root . '/app/odds_api.php';
require_once $root . '/app/matches.php';
require_once $root . '/app/live_football.php';

if (!defined('API_FOOTBALL_KEY')) {
    echo "FAIL : constante API_FOOTBALL_KEY absente dans app/config.php.\n";
    echo "Le config.php sur ce serveur n’est pas à jour (clé .env seule ne suffit pas).\n";
    exit(1);
}

$keyInEnv = trim((string) env('API_FOOTBALL_KEY', ''));
echo 'clé .env         : ' . ($keyInEnv !== '' ? 'oui (' . strlen($keyInEnv) . ' car.)' : 'NON') . "\n";
echo 'API_FOOTBALL_KEY : ' . (API_FOOTBALL_KEY !== '' ? 'oui (' . strlen(API_FOOTBALL_KEY) . ' car.)' : 'NON') . "\n";
echo 'MOCK             : ' . (LIVE_FOOTBALL_MOCK ? 'oui' : 'non') . "\n";
echo 'configured       : ' . (liveFootballConfigured() ? 'oui' : 'non') . "\n";
$q = liveFootballQuotaState();
echo 'budget jour      : ' . $q['used'] . '/' . $q['budget'] . ' (reste ' . $q['remaining'] . ")\n";
echo 'intervalle sync  : ' . LIVE_FOOTBALL_SYNC_INTERVAL_SECONDS . " s\n";

if (!liveFootballConfigured()) {
    echo "Abandon : mets API_FOOTBALL_KEY=... dans le .env serveur.\n";
    exit(1);
}

try {
    $pdo = getPDO();
} catch (Throwable $e) {
    fwrite(STDERR, 'DB : ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$tracked = getSoccerMatchesTrackedForLive($pdo);
echo 'matchs suivis    : ' . count($tracked) . "\n";
foreach (array_slice($tracked, 0, 8) as $m) {
    echo '  #' . (int) $m['id'] . ' '
        . ($m['equipe_home'] ?? '?') . ' – ' . ($m['equipe_away'] ?? '?')
        . ' @ ' . ($m['date_match'] ?? '') . "\n";
}

echo "Appel /fixtures?live=all …\n";
$t0 = microtime(true);
$fixtures = liveFootballFetchLiveFixtures();
$ms = (int) round((microtime(true) - $t0) * 1000);

if ($fixtures === null) {
    echo "FAIL : réponse API invalide ou erreur HTTP ({$ms} ms).\n";
    echo "Vérifie la clé dashboard api-football / quota journalier.\n";
    exit(1);
}

echo 'OK   : ' . count($fixtures) . " fixture(s) live ({$ms} ms)\n";
foreach (array_slice($fixtures, 0, 5) as $row) {
    $p = liveFootballParseFixture(is_array($row) ? $row : []);
    if ($p === null) {
        continue;
    }
    echo '  ' . $p['api_home'] . ' ' . ($p['goals_home'] ?? '-') . '–' . ($p['goals_away'] ?? '-')
        . ' ' . $p['api_away'] . ' · ' . $p['clock'] . ' [' . $p['status_short'] . "]\n";
}

if ($doSync) {
    echo "Sync forcée…\n";
    $sync = syncLiveFootballScores($pdo, true);
    echo '  ran=' . (!empty($sync['ran']) ? 'oui' : 'non')
        . ' tracked=' . (int) ($sync['tracked'] ?? 0)
        . ' matched=' . (int) ($sync['matched'] ?? 0)
        . ' skipped=' . ($sync['skipped'] ?? '-') . "\n";
    $payload = liveFootballPublicPayload($pdo);
    echo '  cache matches : ' . count($payload['matches']) . "\n";
    foreach ($payload['matches'] as $id => $snap) {
        $score = isset($snap['home'], $snap['away']) && $snap['home'] !== null
            ? ($snap['home'] . '–' . $snap['away'])
            : '—';
        echo "  #{$id} {$score} " . ($snap['clock'] ?? '') . "\n";
    }
}

echo "Done.\n";
exit(0);
