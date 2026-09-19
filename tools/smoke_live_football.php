<?php
/**
 * Smoke test API-Football (serveur) — 1 crédit max.
 * Usage :
 *   php tools/smoke_live_football.php
 *   php tools/smoke_live_football.php --sync   # force sync + affiche cache
 *
 * Ne committe jamais la clé. Lit uniquement .env.
 */
declare(strict_types=1);

define('APP_BOOT', true);

require dirname(__DIR__) . '/app/bootstrap.php';

$doSync = in_array('--sync', $argv ?? [], true);

echo "=== smoke live football ===\n";
echo 'API_FOOTBALL_KEY : ' . (API_FOOTBALL_KEY !== '' ? 'oui (' . strlen(API_FOOTBALL_KEY) . ' car.)' : 'NON') . "\n";
echo 'MOCK             : ' . (LIVE_FOOTBALL_MOCK ? 'oui' : 'non') . "\n";
echo 'configured       : ' . (liveFootballConfigured() ? 'oui' : 'non') . "\n";

if (!liveFootballConfigured()) {
    echo "Abandon : mets API_FOOTBALL_KEY dans le .env serveur.\n";
    exit(1);
}

$pdo = getPDO();
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
