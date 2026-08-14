<?php
require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$cronKey = (string) ($_GET['key'] ?? $_POST['key'] ?? '');
$authorizedByCron = CRON_SECRET !== '' && hash_equals(CRON_SECRET, $cronKey);
$sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
$authorizedByAdmin = false;

releaseSession();

$pdo = getPDO();
if ($sessionUserId > 0) {
    $authorizedByAdmin = userCanForceSync($pdo, $sessionUserId);
}
$authorizedBySession = $sessionUserId > 0;

ensureMatchProbColumns($pdo);
maintainSeasons($pdo);

/** Mode cron léger : scores + rotation cache, 0 crédit si cache chaud (pas de cotes/buteurs). */
$cronLight = !empty($_GET['cron']) || (($_GET['mode'] ?? '') === 'cron');
$browserLight = (($_GET['mode'] ?? '') === 'light');

if ($cronLight) {
    if (!$authorizedByCron) {
        syncForbiddenResponse('cron_key');
    }
    @set_time_limit(120);
    // Cron léger = scores (budget) + cache local. JAMAIS de cotes ici :
    // les cotes sont un luxe (admin / mode=odds), pas un besoin toutes les heures.
    $lifecycle = maintainMatchLifecycle($pdo, false);
    $reminders = maybeSendDailyMatchReminders($pdo);
    echo json_encode([
        'ok'              => true,
        'mode'            => 'cron',
        'cache_refreshed' => $lifecycle['cache'],
        'scores_synced'   => $lifecycle['scores'],
        'closed'          => $lifecycle['closed'],
        'reminders'       => $reminders,
        'quota_remaining' => oddsQuotaRemaining(),
    ]);
    exit;
}

/**
 * Mode navigateur : rattrapage points UNIQUEMENT (0 crédit API).
 * Les appels /scores sont réservés au cron / admin — un onglet ouvert
 * ne doit jamais brûler le quota mensuel.
 */
if ($browserLight) {
    if (!$authorizedBySession && !$authorizedByCron) {
        syncForbiddenResponse('session');
    }
    $scored = maybeScorePendingFinishedMatches($pdo);
    $closed = closeExpiredMatches($pdo);
    echo json_encode([
        'ok'      => true,
        'mode'    => 'light',
        'scored'  => $scored,
        'closed'  => $closed,
        'api'     => false,
    ]);
    exit;
}

/**
 * Scoring local uniquement (matchs déjà « termine » en BDD) — 0 crédit.
 * Utilisé par la console admin Python après saisie manuelle de scores.
 */
$scoreLocal = (($_GET['mode'] ?? '') === 'score_local');
if ($scoreLocal) {
    if (!$authorizedByCron && !$authorizedByAdmin) {
        syncForbiddenResponse('admin');
    }
    $scored = scorePendingFinishedMatches($pdo);
    $closed = closeExpiredMatches($pdo);
    echo json_encode([
        'ok'     => true,
        'mode'   => 'score_local',
        'scored' => $scored,
        'closed' => $closed,
        'api'    => false,
    ]);
    exit;
}

/** Cotes h2h des matchs affichés (1 appel API / sport, throttle 30 min). */
$oddsMode = (($_GET['mode'] ?? '') === 'odds');
if ($oddsMode) {
    if (!$authorizedByCron && !$authorizedByAdmin) {
        syncForbiddenResponse('admin');
    }
    @set_time_limit(60);
    $odds = maybeSyncOdds($pdo, !empty($_GET['force']));
    $coverage = countDisplayedOddsCoverage($pdo);
    echo json_encode([
        'ok'              => true,
        'mode'            => 'odds',
        'synced'          => !empty($odds['ran']),
        'match_updates'   => (int) ($odds['updated'] ?? 0),
        'sports'          => $odds['sports'] ?? [],
        'nothing_to_do'   => !empty($odds['nothing_to_do']),
        'skipped_quota'   => !empty($odds['skipped_quota']),
        'throttled'       => !empty($odds['throttled']),
        'coverage_with'   => (int) ($coverage['with'] ?? 0),
        'coverage_total'  => (int) ($coverage['total'] ?? 0),
        'quota_remaining' => oddsQuotaRemaining(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Libère un verrou sync orphelin (fichier resté après une sync finie). */
$clearLock = (($_GET['mode'] ?? '') === 'clear_sync_lock');
if ($clearLock) {
    if (!$authorizedByCron && !$authorizedByAdmin) {
        syncForbiddenResponse('admin');
    }
    $lock = clearIdleSyncLock();
    echo json_encode([
        'ok'      => !$lock['busy'],
        'mode'    => 'clear_sync_lock',
        'cleared' => $lock['cleared'],
        'busy'    => $lock['busy'],
        'hint'    => $lock['busy']
            ? 'Une sync détient encore le verrou — attends 1–2 min.'
            : 'Verrou libéré (ou déjà libre). Tu peux relancer « Rafraîchir matchs ».',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Purge BDD (options score_exact, buteurs vieux, matchs sans prono). 0 crédit API. */
$pruneMode = (($_GET['mode'] ?? '') === 'prune_db');
if ($pruneMode) {
    if (!$authorizedByCron && !$authorizedByAdmin) {
        syncForbiddenResponse('admin');
    }
    $pruned = pruneStaleMatchData($pdo);
    if (ensureAppCacheDir()) {
        @file_put_contents(pruneLastRunPath(), (string) time());
    }
    echo json_encode([
        'ok'     => true,
        'mode'   => 'prune_db',
        'pruned' => $pruned,
        'hint'   => 'Scores exacts = liste PHP (plus en BDD). Historique pronos intact.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Sync complet (import matchs) : CRON_SECRET ou compte admin. */
if (!$authorizedByCron && !$authorizedByAdmin) {
    syncForbiddenResponse($cronKey === '' ? 'no_key' : 'cron_key');
}

$forceRequested = !empty($_GET['force']) || !empty($_POST['force']);
$forceSync = $forceRequested && ($authorizedByCron || $authorizedByAdmin);
$refreshEvents = !empty($_GET['refresh']);

if ($forceSync && isSyncLockHeld()) {
    http_response_code(409);
    echo json_encode([
        'ok'    => false,
        'error' => 'sync_busy',
        'hint'  => 'Une synchronisation détient encore le verrou. Attends 1–2 min, '
            . 'ou appelle mode=clear_sync_lock si elle est finie (verrou fantôme).',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$waitInline = !empty($_GET['wait']);
if ($forceSync && !$waitInline && triggerBackgroundMatchSync($refreshEvents)) {
    echo json_encode([
        'ok'      => true,
        'queued'  => true,
        'refresh' => $refreshEvents,
        'hint'    => 'Sync lancée en arrière-plan (1–2 min). Rechargez les matchs ensuite.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

@set_time_limit(SYNC_FORCE_MAX_SECONDS + 15);

$lifecycle = $forceSync
    ? ['cache' => false, 'scores' => false, 'closed' => 0]
    : maintainMatchLifecycle($pdo, false);

$syncResult = runMatchImportSync($pdo, $forceSync, $refreshEvents);
$ran        = $syncResult['ran'];

if ($ran) {
    $lifecycle = maintainMatchLifecycle($pdo, false);
}

$byCat    = getUpcomingMatchesByCategory($pdo);
$display  = getUpcomingMatches($pdo);
$dbCounts = countDbUpcomingByCategory($pdo);

echo json_encode([
    'ok'                 => true,
    'cache_refreshed'    => $lifecycle['cache'],
    'scores_synced'      => $lifecycle['scores'],
    'synced'             => $ran,
    'force'              => $forceSync,
    'refresh'            => $refreshEvents,
    'sync_skip_reason'   => $syncResult['skip_reason'],
    'cache_writable'     => appCacheStatus()['writable'],
    'sports_checked'     => $syncResult['sports_checked'],
    'events_fetched'     => $syncResult['events_fetched'],
    'events_imported'    => $syncResult['events_imported'],
    'active_tennis'      => $syncResult['active_tennis'],
    'active_basketball'  => $syncResult['active_basketball'],
    'active_soccer'      => $syncResult['active_soccer'],
    'active_sport_keys'  => $syncResult['active_sport_keys'] ?? [],
    'fetched_by_sport'   => $syncResult['fetched_by_sport'] ?? [],
    'import_skips'       => $syncResult['import_skips'] ?? [],
    'db_soccer'          => $dbCounts['soccer'],
    'db_basketball'      => $dbCounts['basketball'],
    'db_tennis'          => $dbCounts['tennis'],
    'shown_soccer'       => count($byCat['soccer'] ?? []),
    'shown_basketball'   => count($byCat['basketball'] ?? []),
    'shown_tennis'       => count($byCat['tennis'] ?? []),
    'shown_total'        => count($display),
], JSON_UNESCAPED_UNICODE);
