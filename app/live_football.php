<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/**
 * Scores live football (API-Football) — économie :
 * uniquement les matchs soccer du jour avec ≥1 prono en_attente,
 * 1 appel /fixtures?live=all quand nécessaire, cache fichier pour le front.
 * N’écrit jamais resultat_1x2 / scoreMatch — affichage seulement.
 */

function liveFootballConfigured(): bool
{
    if (LIVE_FOOTBALL_MOCK) {
        return true;
    }

    return API_FOOTBALL_KEY !== '';
}

function liveFootballCachePath(): string
{
    if (!is_dir(APP_CACHE_DIR)) {
        @mkdir(APP_CACHE_DIR, 0755, true);
    }

    return APP_CACHE_DIR . '/live_football.json';
}

function liveFootballLastSyncPath(): string
{
    return APP_CACHE_DIR . '/last_live_football_sync.txt';
}

function liveFootballQuotaPath(): string
{
    return APP_CACHE_DIR . '/live_football_quota.json';
}

function liveFootballLockPath(): string
{
    return APP_CACHE_DIR . '/live_football_sync.lock';
}

/** @return array{date:string,used:int,budget:int,remaining:int} */
function liveFootballQuotaState(): array
{
    $budget = (int) LIVE_FOOTBALL_DAILY_BUDGET;
    $today = gmdate('Y-m-d');
    $used = 0;
    $path = liveFootballQuotaPath();
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($data) && ($data['date'] ?? '') === $today) {
            $used = max(0, (int) ($data['used'] ?? 0));
        }
    }

    return [
        'date'      => $today,
        'used'      => $used,
        'budget'    => $budget,
        'remaining' => max(0, $budget - $used),
    ];
}

function liveFootballQuotaAllow(): bool
{
    if (LIVE_FOOTBALL_MOCK) {
        return true;
    }
    $state = liveFootballQuotaState();

    return $state['remaining'] > 0;
}

function liveFootballQuotaConsume(int $n = 1): void
{
    if (LIVE_FOOTBALL_MOCK || $n < 1) {
        return;
    }
    if (!ensureAppCacheDir()) {
        return;
    }
    $path = liveFootballQuotaPath();
    $today = gmdate('Y-m-d');
    $fp = @fopen($path, 'c+');
    if ($fp === false) {
        return;
    }
    try {
        if (!flock($fp, LOCK_EX)) {
            return;
        }
        $raw = stream_get_contents($fp);
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        $used = 0;
        if (is_array($data) && ($data['date'] ?? '') === $today) {
            $used = max(0, (int) ($data['used'] ?? 0));
        }
        $used += $n;
        $payload = json_encode(['date' => $today, 'used' => $used], JSON_UNESCAPED_UNICODE);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) $payload);
        fflush($fp);
        flock($fp, LOCK_UN);
    } finally {
        fclose($fp);
    }
}

/**
 * Verrou court anti double-appel (plusieurs onglets / workers FPM).
 * @return resource|false
 */
function liveFootballAcquireLock()
{
    if (!ensureAppCacheDir()) {
        return false;
    }
    $fp = @fopen(liveFootballLockPath(), 'c+');
    if ($fp === false) {
        return false;
    }
    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        fclose($fp);

        return false;
    }

    return $fp;
}

function liveFootballReleaseLock($fp): void
{
    if ($fp === false || $fp === null) {
        return;
    }
    flock($fp, LOCK_UN);
    fclose($fp);
}

/**
 * Normalise un nom d’équipe pour appariement Odds ↔ API-Football.
 * Réutilise normalizeTeamName puis retire les suffixes club courants.
 */
function liveFootballNormalizeTeam(string $name): string
{
    $base = normalizeTeamName($name);
    if ($base === '') {
        return '';
    }

    $suffixes = [
        'footballclub', 'futbolclub', 'soccerclub', 'sportingclub',
        'fc', 'cf', 'sc', 'ac', 'as', 'ss', 'ud', 'cd', 'rc', 'afc', 'cfc',
    ];
    foreach ($suffixes as $sfx) {
        $len = strlen($sfx);
        if (strlen($base) > $len + 2 && str_ends_with($base, $sfx)) {
            $base = substr($base, 0, -$len);
            break;
        }
    }
    foreach ($suffixes as $sfx) {
        $len = strlen($sfx);
        if (strlen($base) > $len + 2 && str_starts_with($base, $sfx)) {
            $base = substr($base, $len);
            break;
        }
    }

    return $base;
}

/** Similarité 0–100 entre deux noms normalisés. */
function liveFootballTeamSimilarity(string $a, string $b): float
{
    $a = liveFootballNormalizeTeam($a);
    $b = liveFootballNormalizeTeam($b);
    if ($a === '' || $b === '') {
        return 0.0;
    }
    if ($a === $b) {
        return 100.0;
    }
    if (str_contains($a, $b) || str_contains($b, $a)) {
        $shorter = min(strlen($a), strlen($b));
        $longer = max(strlen($a), strlen($b));

        return 70.0 + (30.0 * ($shorter / max(1, $longer)));
    }
    similar_text($a, $b, $pct);

    return (float) $pct;
}

/**
 * Apparie un fixture API-Football à nos équipes domicile/extérieur.
 *
 * @return array{home:int,away:int,confidence:float}|null
 *   home/away = buts orientés selon NOTRE domicile
 */
function liveFootballMatchFixtureTeams(
    string $dbHome,
    string $dbAway,
    string $apiHome,
    string $apiAway,
    ?int $goalsHome,
    ?int $goalsAway
): ?array {
    if ($goalsHome === null || $goalsAway === null) {
        return null;
    }

    $hh = liveFootballTeamSimilarity($dbHome, $apiHome);
    $aa = liveFootballTeamSimilarity($dbAway, $apiAway);
    $ha = liveFootballTeamSimilarity($dbHome, $apiAway);
    $ah = liveFootballTeamSimilarity($dbAway, $apiHome);

    $straight = min($hh, $aa);
    $swapped = min($ha, $ah);
    $threshold = 72.0;

    if ($straight >= $threshold && $straight >= $swapped) {
        return [
            'home' => (int) $goalsHome,
            'away' => (int) $goalsAway,
            'confidence' => $straight,
        ];
    }
    if ($swapped >= $threshold) {
        return [
            'home' => (int) $goalsAway,
            'away' => (int) $goalsHome,
            'confidence' => $swapped,
        ];
    }

    return null;
}

/**
 * Libellé minute / statut pour l’UI.
 *
 * @param array{short?:string,elapsed?:int|null,extra?:int|null} $status
 */
function liveFootballFormatClock(array $status): string
{
    $short = strtoupper(trim((string) ($status['short'] ?? '')));
    $elapsed = isset($status['elapsed']) ? (int) $status['elapsed'] : null;
    $extra = isset($status['extra']) ? (int) $status['extra'] : null;

    return match ($short) {
        'HT' => 'MT',
        'BT' => 'Pause',
        'FT', 'AET', 'PEN' => 'Fin',
        'NS', 'TBD', 'PST', 'CANC', 'ABD', 'AWD', 'WO' => '',
        '1H', '2H', 'ET', 'P', 'LIVE' => liveFootballMinuteLabel($elapsed, $extra),
        default => liveFootballMinuteLabel($elapsed, $extra),
    };
}

function liveFootballMinuteLabel(?int $elapsed, ?int $extra): string
{
    if ($elapsed === null || $elapsed < 0) {
        return 'Live';
    }
    if ($extra !== null && $extra > 0) {
        return $elapsed . '+' . $extra . "'";
    }

    return $elapsed . "'";
}

function liveFootballIsFinishedStatus(string $short): bool
{
    $short = strtoupper(trim($short));

    return in_array($short, ['FT', 'AET', 'PEN'], true);
}

function liveFootballIsInPlayStatus(string $short): bool
{
    $short = strtoupper(trim($short));

    return in_array($short, ['1H', 'HT', '2H', 'ET', 'BT', 'P', 'LIVE'], true);
}

/**
 * Parse une entrée /fixtures API-Football vers un snapshot live.
 *
 * @return array{
 *   api_home:string,api_away:string,goals_home:?int,goals_away:?int,
 *   status_short:string,elapsed:?int,extra:?int,clock:string,finished:bool,in_play:bool
 * }|null
 */
function liveFootballParseFixture(array $row): ?array
{
    $teams = $row['teams'] ?? null;
    $goals = $row['goals'] ?? null;
    $status = $row['fixture']['status'] ?? ($row['status'] ?? null);
    if (!is_array($teams) || !is_array($goals) || !is_array($status)) {
        return null;
    }

    $apiHome = (string) ($teams['home']['name'] ?? '');
    $apiAway = (string) ($teams['away']['name'] ?? '');
    if ($apiHome === '' || $apiAway === '') {
        return null;
    }

    $gh = $goals['home'];
    $ga = $goals['away'];
    $goalsHome = is_numeric($gh) ? (int) $gh : null;
    $goalsAway = is_numeric($ga) ? (int) $ga : null;

    $short = (string) ($status['short'] ?? '');
    $elapsed = isset($status['elapsed']) && is_numeric($status['elapsed'])
        ? (int) $status['elapsed']
        : null;
    $extra = isset($status['extra']) && is_numeric($status['extra'])
        ? (int) $status['extra']
        : null;

    return [
        'api_home'     => $apiHome,
        'api_away'     => $apiAway,
        'goals_home'   => $goalsHome,
        'goals_away'   => $goalsAway,
        'status_short' => $short,
        'elapsed'      => $elapsed,
        'extra'        => $extra,
        'clock'        => liveFootballFormatClock([
            'short'   => $short,
            'elapsed' => $elapsed,
            'extra'   => $extra,
        ]),
        'finished'     => liveFootballIsFinishedStatus($short),
        'in_play'      => liveFootballIsInPlayStatus($short),
    ];
}

/**
 * Matchs soccer à suivre : ≥1 prono en_attente, coup d’envoi passé,
 * pas encore de résultat officiel, dans la fenêtre live.
 *
 * @return list<array<string,mixed>>
 */
function getSoccerMatchesTrackedForLive(PDO $pdo): array
{
    $window = (int) LIVE_FOOTBALL_WINDOW_MINUTES;
    $now = matchSqlNow();

    $stmt = $pdo->query(
        "SELECT m.*
         FROM matches m
         WHERE m.sport LIKE 'soccer_%'
           AND m.resultat_1x2 IS NULL
           AND m.statut NOT IN ('annule')
           AND m.date_match <= {$now}
           AND m.date_match > DATE_SUB({$now}, INTERVAL {$window} MINUTE)
           AND EXISTS (
               SELECT 1
               FROM prediction_markets pm
               INNER JOIN predictions p ON p.market_id = pm.id
               WHERE pm.match_id = m.id
                 AND p.statut = 'en_attente'
           )
         ORDER BY m.date_match ASC"
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
}

/**
 * Matchs en direct pour UN joueur (ses pronos en_attente uniquement).
 *
 * @return list<array<string,mixed>>
 */
function getSoccerMatchesLiveForUser(PDO $pdo, int $userId): array
{
    if ($userId < 1) {
        return [];
    }
    $window = (int) LIVE_FOOTBALL_WINDOW_MINUTES;
    $now = matchSqlNow();

    $stmt = $pdo->prepare(
        "SELECT DISTINCT m.*
         FROM matches m
         INNER JOIN prediction_markets pm ON pm.match_id = m.id
         INNER JOIN predictions p ON p.market_id = pm.id
         WHERE p.user_id = ?
           AND p.statut = 'en_attente'
           AND m.sport LIKE 'soccer_%'
           AND m.resultat_1x2 IS NULL
           AND m.statut NOT IN ('annule')
           AND m.date_match <= {$now}
           AND m.date_match > DATE_SUB({$now}, INTERVAL {$window} MINUTE)
         ORDER BY m.date_match ASC"
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
}

/**
 * Empreinte légère des scores (détecter un but côté front sans recharger la page).
 *
 * @param array<string,array<string,mixed>> $matches
 */
function liveFootballScoreFingerprint(array $matches): string
{
    if ($matches === []) {
        return 'empty';
    }
    ksort($matches);
    $parts = [];
    foreach ($matches as $id => $snap) {
        if (!is_array($snap)) {
            continue;
        }
        $parts[] = $id . ':' . ($snap['home'] ?? 'x') . '-' . ($snap['away'] ?? 'x')
            . ':' . ($snap['status'] ?? '') . ':' . ($snap['clock'] ?? '');
    }

    return substr(hash('sha256', implode('|', $parts)), 0, 16);
}

/**
 * Intervalle de poll front recommandé (lecture cache — ne brûle pas l’API).
 *
 * @param array<string,array<string,mixed>> $matches
 */
function liveFootballSuggestedPollMs(array $matches): int
{
    if ($matches === []) {
        return 60000;
    }
    $phase = liveFootballCachePhase($matches);
    return match ($phase) {
        'playing', 'mixed' => 15000,
        'break' => 45000,
        'finished' => 60000,
        default => 30000,
    };
}

/**
 * Phase du cache live : playing | break | finished | mixed | empty.
 *
 * @param array<string,array<string,mixed>> $cachedMatches
 */
function liveFootballCachePhase(array $cachedMatches): string
{
    if ($cachedMatches === []) {
        return 'empty';
    }
    $playing = 0;
    $break = 0;
    $finished = 0;
    foreach ($cachedMatches as $snap) {
        if (!is_array($snap) || !empty($snap['pending'])) {
            continue;
        }
        $st = strtoupper((string) ($snap['status'] ?? ''));
        if (in_array($st, ['1H', '2H', 'ET', 'P', 'LIVE'], true)) {
            $playing++;
        } elseif (in_array($st, ['HT', 'BT'], true)) {
            $break++;
        } elseif (in_array($st, ['FT', 'AET', 'PEN'], true) || !empty($snap['finished'])) {
            $finished++;
        }
    }
    if ($playing > 0 && $break > 0) {
        return 'mixed';
    }
    if ($playing > 0) {
        return 'playing';
    }
    if ($break > 0) {
        return 'break';
    }
    if ($finished > 0) {
        return 'finished';
    }

    return 'empty';
}

/**
 * Intervalle API effectif : 5 min en jeu, 10 min si uniquement mi-temps.
 *
 * @param array<string,array<string,mixed>> $cachedMatches
 */
function liveFootballEffectiveSyncIntervalSeconds(array $cachedMatches): int
{
    $normal = (int) LIVE_FOOTBALL_SYNC_INTERVAL_SECONDS;
    $ht = (int) LIVE_FOOTBALL_HT_SYNC_SECONDS;
    $phase = liveFootballCachePhase($cachedMatches);

    return match ($phase) {
        'break' => max($normal, $ht),
        'finished' => max($normal, $ht),
        default => $normal,
    };
}

/**
 * Fusionne les matchs live suivis dans la liste soccer affichée (sans doublon).
 *
 * @param list<array<string,mixed>> $upcoming
 * @param list<array<string,mixed>> $liveTracked
 * @return list<array<string,mixed>>
 */
function mergeLiveSoccerMatchesForDisplay(array $upcoming, array $liveTracked): array
{
    $liveIds = [];
    foreach ($liveTracked as $m) {
        $liveIds[(int) ($m['id'] ?? 0)] = true;
    }

    $out = [];
    $seen = [];

    foreach ($liveTracked as $m) {
        $id = (int) ($m['id'] ?? 0);
        if ($id <= 0 || isset($seen[$id])) {
            continue;
        }
        $m['live_track'] = true;
        $out[] = $m;
        $seen[$id] = true;
    }

    foreach ($upcoming as $m) {
        $id = (int) ($m['id'] ?? 0);
        if ($id <= 0 || isset($seen[$id])) {
            if (isset($seen[$id]) && !empty($liveIds[$id])) {
                // déjà ajouté depuis liveTracked
            }
            continue;
        }
        if (!empty($liveIds[$id])) {
            $m['live_track'] = true;
        }
        $out[] = $m;
        $seen[$id] = true;
    }

    return $out;
}

/** @return array{fetched_at:int,matches:array<string,array<string,mixed>>} */
function liveFootballReadCache(): array
{
    $path = liveFootballCachePath();
    if (!is_file($path)) {
        return ['fetched_at' => 0, 'matches' => []];
    }
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return ['fetched_at' => 0, 'matches' => []];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['fetched_at' => 0, 'matches' => []];
    }

    return [
        'fetched_at' => (int) ($data['fetched_at'] ?? 0),
        'matches'    => is_array($data['matches'] ?? null) ? $data['matches'] : [],
    ];
}

/** @param array<string,array<string,mixed>> $matches */
function liveFootballWriteCache(array $matches): void
{
    if (!ensureAppCacheDir()) {
        return;
    }
    $payload = [
        'fetched_at' => time(),
        'matches'    => $matches,
    ];
    @file_put_contents(
        liveFootballCachePath(),
        json_encode($payload, JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

/**
 * @param list<array<string,mixed>> $fixtures
 * @param list<array<string,mixed>> $tracked
 * @return array<string,array<string,mixed>>
 */
function liveFootballBuildSnapshots(array $fixtures, array $tracked): array
{
    $parsed = [];
    foreach ($fixtures as $row) {
        if (!is_array($row)) {
            continue;
        }
        $p = liveFootballParseFixture($row);
        if ($p !== null && ($p['in_play'] || $p['finished'])) {
            $parsed[] = $p;
        }
    }

    $out = [];
    foreach ($tracked as $match) {
        $id = (int) ($match['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $dbHome = (string) ($match['equipe_home'] ?? '');
        $dbAway = (string) ($match['equipe_away'] ?? '');
        $best = null;
        $bestConf = 0.0;

        foreach ($parsed as $p) {
            $linked = liveFootballMatchFixtureTeams(
                $dbHome,
                $dbAway,
                $p['api_home'],
                $p['api_away'],
                $p['goals_home'],
                $p['goals_away']
            );
            if ($linked === null) {
                continue;
            }
            if ($linked['confidence'] > $bestConf) {
                $bestConf = $linked['confidence'];
                $best = [
                    'home'     => $linked['home'],
                    'away'     => $linked['away'],
                    'minute'   => $p['elapsed'],
                    'extra'    => $p['extra'],
                    'status'   => $p['status_short'],
                    'clock'    => $p['clock'],
                    'finished' => $p['finished'],
                    'in_play'  => $p['in_play'],
                ];
            }
        }

        if ($best !== null) {
            $out[(string) $id] = $best;
        }
    }

    return $out;
}

/**
 * @return list<array<string,mixed>>|null
 */
function liveFootballFetchLiveFixtures(): ?array
{
    if (LIVE_FOOTBALL_MOCK) {
        return liveFootballMockFixtures();
    }
    if (!liveFootballConfigured()) {
        return null;
    }

    $url = rtrim(API_FOOTBALL_BASE, '/') . '/fixtures?live=all';
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'timeout'       => API_FOOTBALL_TIMEOUT,
            'ignore_errors' => true,
            'header'        => "x-apisports-key: " . API_FOOTBALL_KEY . "\r\n"
                . "Accept: application/json\r\nConnection: close\r\n",
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $ctx);
    $headers = $http_response_header ?? [];
    $status = 0;
    foreach ($headers as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $header, $m)) {
            $status = (int) $m[1];
        }
    }
    if ($body === false || $status < 200 || $status >= 300) {
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data) || !isset($data['response']) || !is_array($data['response'])) {
        return null;
    }

    return $data['response'];
}

/** Fixtures factices pour tests / lab (LIVE_FOOTBALL_MOCK=1). */
function liveFootballMockFixtures(): array
{
    $path = APP_CACHE_DIR . '/live_football_mock.json';
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($data) && isset($data['response']) && is_array($data['response'])) {
            return $data['response'];
        }
    }

    return [];
}

/**
 * Sync live — 0 appel si aucun match suivi, sinon 1× /fixtures?live=all
 * (throttle intervalle + budget journalier + verrou anti-doublon).
 *
 * @return array{ran:bool,tracked:int,matched:int,throttled:bool,skipped:string|null,quota:?array}
 */
function syncLiveFootballScores(PDO $pdo, bool $force = false): array
{
    $empty = [
        'ran'       => false,
        'tracked'   => 0,
        'matched'   => 0,
        'throttled' => false,
        'skipped'   => null,
        'quota'     => liveFootballQuotaState(),
    ];

    if (!liveFootballConfigured()) {
        $empty['skipped'] = 'not_configured';

        return $empty;
    }

    $tracked = getSoccerMatchesTrackedForLive($pdo);
    $empty['tracked'] = count($tracked);

    if ($tracked === []) {
        liveFootballWriteCache([]);
        $empty['skipped'] = 'nothing_to_track';

        return $empty;
    }

    if (!ensureAppCacheDir()) {
        $empty['skipped'] = 'cache_dir';

        return $empty;
    }

    if (!$force && !liveFootballQuotaAllow()) {
        $empty['skipped'] = 'daily_budget';
        $empty['quota'] = liveFootballQuotaState();

        return $empty;
    }

    $interval = liveFootballEffectiveSyncIntervalSeconds(
        is_array(($cachePeek = liveFootballReadCache())['matches'] ?? null)
            ? $cachePeek['matches']
            : []
    );
    $stampFile = liveFootballLastSyncPath();
    if (!$force && is_file($stampFile)) {
        $last = (int) @file_get_contents($stampFile);
        if ($last > 0 && (time() - $last) < $interval) {
            $empty['throttled'] = true;
            $empty['skipped'] = 'throttled';

            return $empty;
        }
    }

    // Force admin : respect quand même le budget (sauf mock).
    if ($force && !liveFootballQuotaAllow()) {
        $empty['skipped'] = 'daily_budget';
        $empty['quota'] = liveFootballQuotaState();

        return $empty;
    }

    $lock = liveFootballAcquireLock();
    if ($lock === false) {
        $empty['throttled'] = true;
        $empty['skipped'] = 'locked';

        return $empty;
    }

    try {
        // Re-check throttle after lock (autre worker vient de sync).
        if (!$force && is_file($stampFile)) {
            $last = (int) @file_get_contents($stampFile);
            if ($last > 0 && (time() - $last) < $interval) {
                $empty['throttled'] = true;
                $empty['skipped'] = 'throttled';

                return $empty;
            }
        }

        $fixtures = liveFootballFetchLiveFixtures();
        if ($fixtures === null) {
            $empty['skipped'] = 'api_error';

            return $empty;
        }

        liveFootballQuotaConsume(1);
        $snapshots = liveFootballBuildSnapshots($fixtures, $tracked);
        liveFootballWriteCache($snapshots);
        @file_put_contents($stampFile, (string) time());

        return [
            'ran'       => true,
            'tracked'   => count($tracked),
            'matched'   => count($snapshots),
            'throttled' => false,
            'skipped'   => null,
            'interval'  => $interval,
            'phase'     => liveFootballCachePhase($snapshots),
            'quota'     => liveFootballQuotaState(),
        ];
    } finally {
        liveFootballReleaseLock($lock);
    }
}

/**
 * Sync live si besoin (cron). Sur requête web : no-op API (le cache suffit).
 *
 * @return array{ran:bool,tracked:int,matched:int,throttled:bool,skipped:string|null}|false
 */
function maybeSyncLiveFootballScores(PDO $pdo, bool $webRequest = false): array|false
{
    if ($webRequest) {
        return false;
    }

    return syncLiveFootballScores($pdo, false);
}

/**
 * Payload JSON pour le front (matchs suivis + snapshots cache).
 *
 * @return array{ok:bool,enabled:bool,fetched_at:int,matches:array<string,array<string,mixed>>}
 */
function liveFootballPublicPayload(PDO $pdo): array
{
    $enabled = liveFootballConfigured();
    $cache = liveFootballReadCache();
    $tracked = getSoccerMatchesTrackedForLive($pdo);
    $trackedIds = [];
    foreach ($tracked as $m) {
        $trackedIds[(string) (int) $m['id']] = true;
    }

    $matches = [];
    foreach ($cache['matches'] as $id => $snap) {
        if (!isset($trackedIds[(string) $id])) {
            continue;
        }
        if (!is_array($snap)) {
            continue;
        }
        $matches[(string) $id] = [
            'home'     => (int) ($snap['home'] ?? 0),
            'away'     => (int) ($snap['away'] ?? 0),
            'clock'    => (string) ($snap['clock'] ?? ''),
            'status'   => (string) ($snap['status'] ?? ''),
            'finished' => !empty($snap['finished']),
            'in_play'  => !empty($snap['in_play']),
        ];
    }

    // Marqueurs « suivi » sans score encore (carte prête pour le poll).
    foreach ($trackedIds as $id => $_) {
        if (!isset($matches[$id])) {
            $matches[$id] = [
                'home'     => null,
                'away'     => null,
                'clock'    => '',
                'status'   => '',
                'finished' => false,
                'in_play'  => false,
                'pending'  => true,
            ];
        }
    }

    return [
        'ok'           => true,
        'enabled'      => $enabled,
        'fetched_at'   => (int) ($cache['fetched_at'] ?? 0),
        'fingerprint'  => liveFootballScoreFingerprint($matches),
        'poll_ms'      => liveFootballSuggestedPollMs($matches),
        'api_interval' => (int) LIVE_FOOTBALL_SYNC_INTERVAL_SECONDS,
        'quota'        => liveFootballQuotaState(),
        'matches'      => $matches,
    ];
}
