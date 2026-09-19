<?php
require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

releaseSession();

$pdo = getPDO();
$force = !empty($_GET['sync']) || !empty($_POST['sync']);

// Sync API réservée au cron / clé : le navigateur lit seulement le cache.
if ($force) {
    $cronKey = (string) ($_GET['key'] ?? $_POST['key'] ?? '');
    $authorized = CRON_SECRET !== '' && hash_equals(CRON_SECRET, $cronKey);
    if (!$authorized) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit;
    }
    $sync = syncLiveFootballScores($pdo, true);
    $payload = liveFootballPublicPayload($pdo);
    $payload['sync'] = $sync;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// Soft refresh : 1 appel API max / intervalle, partagé entre tous les visiteurs.
$cache = liveFootballReadCache();
$stale = (time() - (int) ($cache['fetched_at'] ?? 0)) >= (int) LIVE_FOOTBALL_SYNC_INTERVAL_SECONDS;
if ($stale && liveFootballConfigured() && getSoccerMatchesTrackedForLive($pdo) !== []) {
    syncLiveFootballScores($pdo, false);
}

echo json_encode(liveFootballPublicPayload($pdo), JSON_UNESCAPED_UNICODE);
