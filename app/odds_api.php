<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/**
 * Client minimal pour The Odds API v4.
 * Privilégie /events (gratuit) pour les matchs à venir
 * et /scores (payant) uniquement pour les résultats, avec cache fichier.
 */
function oddsApiConfigured(): bool
{
    return ODDS_API_KEY !== '';
}

function oddsCachePath(string $key): string
{
    if (!is_dir(APP_CACHE_DIR)) {
        mkdir(APP_CACHE_DIR, 0755, true);
    }
    return APP_CACHE_DIR . '/' . preg_replace('/[^a-z0-9_\-\.]/i', '_', $key) . '.json';
}

function oddsCacheGet(string $key, int $ttlSeconds): ?array
{
    $path = oddsCachePath($key);
    if (!is_file($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['fetched_at'])) {
        return null;
    }
    if (time() - (int) $data['fetched_at'] > $ttlSeconds) {
        return null;
    }
    return $data['payload'] ?? null;
}

/**
 * Dernier payload en cache, même expiré (filet si l'API est morte / quota à 0).
 * @return array{payload:array,fetched_at:int}|null
 */
function oddsCacheGetStale(string $key): ?array
{
    $path = oddsCachePath($key);
    if (!is_file($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['payload']) || !is_array($data['payload'])) {
        return null;
    }

    return [
        'payload'    => $data['payload'],
        'fetched_at' => (int) ($data['fetched_at'] ?? 0),
    ];
}

function oddsCacheSet(string $key, array $payload): void
{
    $path = oddsCachePath($key);
    file_put_contents($path, json_encode([
        'fetched_at' => time(),
        'payload'    => $payload,
    ], JSON_UNESCAPED_UNICODE));
}

function oddsQuotaStatePath(): string
{
    return APP_CACHE_DIR . '/odds_api_quota.json';
}

/** Empreinte de la clé API courante — invalide le cache si tu changes de token. */
function oddsApiKeyFingerprint(): string
{
    return ODDS_API_KEY === '' ? '' : hash('sha256', ODDS_API_KEY);
}

/**
 * Dernier état connu du quota API (en-têtes x-requests-*).
 * @return array{remaining:?int,used:?int,last_cost:?int,updated_at:?int,last_error:?string,last_error_at:?int,api_key_fp:?string}
 */
function oddsQuotaState(): array
{
    $default = [
        'remaining'     => null,
        'used'          => null,
        'last_cost'     => null,
        'updated_at'    => null,
        'last_error'    => null,
        'last_error_at' => null,
        'api_key_fp'    => null,
    ];

    $raw = @file_get_contents(oddsQuotaStatePath());
    if ($raw === false) {
        return $default;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return $default;
    }

    $fp = oddsApiKeyFingerprint();
    // Nouvelle clé API → oublier le solde « 0 » de l'ancienne (sinon tout reste bloqué).
    if (($data['api_key_fp'] ?? null) !== $fp) {
        return array_merge($default, ['api_key_fp' => $fp]);
    }

    return array_merge($default, $data);
}

function oddsQuotaStateSave(array $patch): void
{
    if (!is_dir(APP_CACHE_DIR)) {
        @mkdir(APP_CACHE_DIR, 0755, true);
    }
    $patch['api_key_fp'] = oddsApiKeyFingerprint();
    $state = array_merge(oddsQuotaState(), $patch);
    @file_put_contents(oddsQuotaStatePath(), json_encode($state, JSON_UNESCAPED_UNICODE));
}

/**
 * Sonde live GET /v4/sports — 0 crédit (doc The Odds API).
 * Met à jour le cache local si la clé sondée = ODDS_API_KEY.
 *
 * @return array{ok:bool,remaining:?int,used:?int,last_cost:?int,sports_count:int,error:?string,key_mask:string,live:bool}
 */
function probeOddsApiQuota(?string $apiKey = null): array
{
    $key = trim((string) ($apiKey !== null && $apiKey !== '' ? $apiKey : (defined('ODDS_API_KEY') ? ODDS_API_KEY : '')));
    $mask = $key === '' ? '(vide)' : (strlen($key) <= 8
        ? substr($key, 0, 2) . '…' . substr($key, -2)
        : substr($key, 0, 4) . '…' . substr($key, -4) . ' (' . strlen($key) . ' car.)');

    if ($key === '') {
        return [
            'ok' => false,
            'remaining' => null,
            'used' => null,
            'last_cost' => null,
            'sports_count' => 0,
            'error' => 'ODDS_API_KEY manquante',
            'key_mask' => $mask,
            'live' => false,
        ];
    }

    $url = 'https://api.the-odds-api.com/v4/sports?' . http_build_query([
        'apiKey' => $key,
        '_' => (string) time(),
    ]);
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 12,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nUser-Agent: PrognozOps/1.0\r\nCache-Control: no-cache\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    $headers = [];
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $line) {
            if (str_contains($line, ':')) {
                [$h, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($h))] = trim($v);
            }
        }
    }

    $remaining = isset($headers['x-requests-remaining']) ? (int) $headers['x-requests-remaining'] : null;
    $used = isset($headers['x-requests-used']) ? (int) $headers['x-requests-used'] : null;
    $last = isset($headers['x-requests-last']) ? (int) $headers['x-requests-last'] : null;
    $sports = is_string($raw) ? json_decode($raw, true) : null;
    $statusLine = $http_response_header[0] ?? '';
    $okHttp = str_contains($statusLine, ' 200');

    $envKey = defined('ODDS_API_KEY') ? (string) ODDS_API_KEY : '';
    if ($okHttp && $remaining !== null && hash_equals($envKey, $key)) {
        oddsQuotaStateSave([
            'remaining' => $remaining,
            'used' => $used,
            'last_cost' => $last,
            'updated_at' => time(),
            'last_error' => null,
            'last_error_at' => null,
        ]);
    }

    if (!$okHttp) {
        return [
            'ok' => false,
            'remaining' => $remaining,
            'used' => $used,
            'last_cost' => $last,
            'sports_count' => 0,
            'error' => 'Échec sonde : ' . ($statusLine !== '' ? $statusLine : 'connexion'),
            'key_mask' => $mask,
            'live' => true,
        ];
    }

    return [
        'ok' => true,
        'remaining' => $remaining,
        'used' => $used,
        'last_cost' => $last,
        'sports_count' => is_array($sports) ? count($sports) : 0,
        'error' => null,
        'key_mask' => $mask,
        'live' => true,
    ];
}

/** Crédits restants connus, ou null si aucun relevé encore. */
function oddsQuotaRemaining(): ?int
{
    $state = oddsQuotaState();
    if ($state['remaining'] === null) {
        return null;
    }

    return (int) $state['remaining'];
}

/**
 * Le quota Odds API se remet à zéro le 1er de chaque mois (FAQ officielle).
 * Si notre cache local date d'un mois précédent, il est périmé — il faut
 * autoriser un appel « sonde » pour relire les en-têtes x-requests-*.
 */
function oddsQuotaLikelyMonthlyReset(): bool
{
    $state   = oddsQuotaState();
    $updated = (int) ($state['updated_at'] ?? 0);
    if ($updated <= 0) {
        return true;
    }

    $tz   = new DateTimeZone('UTC');
    $last = (new DateTimeImmutable('@' . $updated))->setTimezone($tz);
    $now  = new DateTimeImmutable('now', $tz);

    return $last->format('Y-m') !== $now->format('Y-m');
}

/**
 * Cache figé à 0 (ex. 401 répétés après un faux « reset ») : retenter une sonde
 * toutes les 6 h. Un 401 ne consomme en général pas de crédit.
 */
function oddsQuotaShouldReprobe(): bool
{
    $remaining = oddsQuotaRemaining();
    if ($remaining === null || $remaining > 0) {
        return false;
    }
    $updated = (int) (oddsQuotaState()['updated_at'] ?? 0);

    return $updated <= 0 || (time() - $updated) >= 6 * 3600;
}

/** Quota épuisé (mois en cours) : inutile de brûler des appels voués à l'échec. */
function oddsQuotaExhausted(): bool
{
    if (oddsQuotaLikelyMonthlyReset() || oddsQuotaShouldReprobe()) {
        return false;
    }
    $remaining = oddsQuotaRemaining();

    return $remaining !== null && $remaining <= 0;
}

/**
 * Autorise-t-on un appel payant pour ce motif ?
 * - scores : autorisé tant qu'il reste > ODDS_QUOTA_RESERVE_SCORES
 * - odds / scorers : interdits sous ODDS_QUOTA_RESERVE_ODDS (crédits réservés aux résultats)
 * - après le 1er du mois / nouvelle clé / reprobe : sonde autorisée même si le cache dit 0
 */
function oddsQuotaAllows(string $purpose): bool
{
    if (!oddsApiConfigured()) {
        return false;
    }

    // Pas encore de relevé, nouveau mois, ou nouvelle clé : laisser passer.
    if (oddsQuotaRemaining() === null || oddsQuotaLikelyMonthlyReset()) {
        return true;
    }

    // Bloqué à 0 : uniquement les scores (sonde budget), pas les cotes.
    if (oddsQuotaShouldReprobe()) {
        return $purpose === 'scores';
    }

    $remaining = oddsQuotaRemaining();

    if ($remaining <= (int) ODDS_QUOTA_RESERVE_SCORES) {
        return false;
    }

    if ($purpose === 'scores') {
        return true;
    }

    // Cotes et buteurs = luxe : on les coupe tôt pour garder de la marge aux résultats.
    return $remaining > (int) ODDS_QUOTA_RESERVE_ODDS;
}

/** Coût estimé d'un appel /scores (daysFrom > 0 → 2 crédits). */
function oddsScoresCreditCost(int $daysFrom): int
{
    return $daysFrom > 0 ? 2 : 1;
}

/**
 * Appel HTTP GET vers l'API.
 * Retourne null sur erreur HTTP — une réponse d'erreur JSON ne doit jamais
 * être confondue avec une liste de résultats (sinon elle finit en cache).
 */
function oddsApiRequest(string $path, array $query = []): ?array
{
    if (!oddsApiConfigured()) {
        return null;
    }

    $query['apiKey'] = ODDS_API_KEY;
    $url = rtrim(ODDS_API_BASE, '/') . $path . '?' . http_build_query($query);

    $ctx = stream_context_create([
        'http' => [
            'method'          => 'GET',
            'timeout'         => ODDS_API_TIMEOUT,
            'ignore_errors'   => true,
            'header'          => "Accept: application/json\r\nConnection: close\r\n",
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $body    = @file_get_contents($url, false, $ctx);
    $headers = $http_response_header ?? [];

    $status = 0;
    $quota  = [];
    foreach ($headers as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $header, $m)) {
            $status = (int) $m[1];
            continue;
        }
        if (preg_match('/^x-requests-(remaining|used|last):\s*(.+)$/i', trim($header), $m)) {
            $quota[strtolower($m[1])] = (int) trim($m[2]);
        }
    }

    if (!empty($quota)) {
        oddsQuotaStateSave([
            'remaining'  => $quota['remaining'] ?? null,
            'used'       => $quota['used'] ?? null,
            'last_cost'  => $quota['last'] ?? null,
            'updated_at' => time(),
        ]);
    }

    if ($body === false) {
        oddsQuotaStateSave(['last_error' => 'connexion impossible', 'last_error_at' => time()]);
        return null;
    }

    $decoded = json_decode($body, true);

    if ($status < 200 || $status >= 300) {
        $message = is_array($decoded)
            ? (string) ($decoded['message'] ?? $decoded['error_code'] ?? 'HTTP ' . $status)
            : 'HTTP ' . $status;
        oddsQuotaStateSave([
            'last_error'    => 'HTTP ' . $status . ' — ' . $message,
            'last_error_at' => time(),
        ]);
        return null;
    }

    if (!is_array($decoded)) {
        return null;
    }

    oddsQuotaStateSave(['last_error' => null, 'last_error_at' => null]);

    return $decoded;
}

/**
 * Sports actuellement en saison.
 * GET /v4/sports — ne consomme PAS de quota.
 */
function oddsFetchSportsList(bool $forceRefresh = false): array
{
    if (!$forceRefresh) {
        $cached = oddsCacheGet('sports_list', ODDS_CACHE_TTL_SPORTS);
        if ($cached !== null) {
            return $cached;
        }
    }

    $data = oddsApiRequest('/v4/sports');
    if (!is_array($data)) {
        if (!$forceRefresh) {
            return [];
        }
        $stale = oddsCacheGet('sports_list', ODDS_CACHE_TTL_SPORTS * 100);

        return is_array($stale) ? $stale : [];
    }

    oddsCacheSet('sports_list', $data);
    return $data;
}

/** Sports actifs filtrés par groupe (Tennis, Soccer…), sans outrights / vainqueurs tournoi. */
function oddsSportIsBettable(array $sport): bool
{
    if (empty($sport['active']) || empty($sport['key'])) {
        return false;
    }
    if (!empty($sport['has_outrights'])) {
        return false;
    }
    $key = strtolower($sport['key']);
    if (strpos($key, 'winner') !== false || strpos($key, 'championship_winner') !== false) {
        return false;
    }

    return true;
}

function oddsSportPriorityWeight(string $sportKey, string $group): int
{
    $priorities = ODDS_SPORT_PRIORITY[$group] ?? [];
    $weight = count($priorities);
    foreach ($priorities as $i => $prefix) {
        if ($sportKey === $prefix || strncmp($sportKey, $prefix . '_', strlen($prefix) + 1) === 0) {
            return $weight - $i;
        }
    }

    return 0;
}

/** Sports actifs filtrés par groupe (Tennis, Soccer…). */
function oddsFetchActiveSportsByGroup(bool $forceRefresh = false): array
{
    $allowed = array_flip(ODDS_SPORT_GROUPS);
    $sports  = [];

    foreach (oddsFetchSportsList($forceRefresh) as $sport) {
        if (!oddsSportIsBettable($sport)) {
            continue;
        }
        $group = $sport['group'] ?? '';
        if (!isset($allowed[$group])) {
            continue;
        }
        $sports[] = $sport;
    }

    usort($sports, static function ($a, $b) {
        $ga = $a['group'] ?? '';
        $gb = $b['group'] ?? '';
        if ($ga !== $gb) {
            return strcmp($ga, $gb);
        }
        $wa = oddsSportPriorityWeight($a['key'] ?? '', $ga);
        $wb = oddsSportPriorityWeight($b['key'] ?? '', $gb);
        if ($wa !== $wb) {
            return $wb <=> $wa;
        }

        return strcmp($a['key'] ?? '', $b['key'] ?? '');
    });

    return $sports;
}

/** Clés outrights / all-star — pas des matchs réguliers à importer. */
function oddsIsSeasonMarketKey(string $sportKey): bool
{
    $k = strtolower($sportKey);
    if (str_contains($k, 'winner') || str_contains($k, 'championship_winner')) {
        return true;
    }
    // All-Star / exhibition : pas de calendrier utile. Preseason NBA = vrais matchs.
    if (str_contains($k, '_all_stars')) {
        return true;
    }

    return false;
}

/** Tous les sports tennis/basket/foot du catalogue API (même inactifs — sonde /events ensuite). */
function oddsCatalogSportsForGroups(array $groups, bool $forceRefresh = false): array
{
    $out = [];
    foreach (oddsFetchSportsList($forceRefresh) as $sport) {
        $group = $sport['group'] ?? '';
        if (!in_array($group, $groups, true)) {
            continue;
        }
        $key = $sport['key'] ?? '';
        if ($key === '' || oddsIsSeasonMarketKey($key)) {
            continue;
        }
        $out[$key] = $sport;
    }

    return $out;
}

/** Liste de sports à synchroniser : catalogue API + tournois/ligues avec matchs à venir. */
function oddsSportsForSync(bool $forceRefresh = false, bool $allowProbe = true): array
{
    $byKey = [];

    foreach (oddsFetchActiveSportsByGroup($forceRefresh) as $sport) {
        $byKey[$sport['key']] = $sport;
    }

    foreach (oddsCatalogSportsForGroups(['Tennis', 'Basketball', 'Soccer'], $forceRefresh) as $key => $sport) {
        $byKey[$key] = $sport;
    }

    foreach (['Tennis', 'Basketball', 'Soccer'] as $group) {
        foreach (ODDS_SPORT_PRIORITY[$group] ?? [] as $key) {
            if (!isset($byKey[$key])) {
                $byKey[$key] = [
                    'key'           => $key,
                    'group'         => $group,
                    'title'         => $key,
                    'active'        => true,
                    'has_outrights' => false,
                ];
            }
        }
    }

    $selected = [];
    $probes   = ['Tennis' => 0, 'Basketball' => 0, 'Soccer' => 0];

    foreach ($byKey as $key => $meta) {
        $group = $meta['group'] ?? '';

        if ($group !== 'Tennis' && $group !== 'Basketball' && $group !== 'Soccer') {
            continue;
        }

        if (!empty($meta['active']) && oddsSportIsBettable($meta)) {
            $selected[$key] = $meta;
            continue;
        }

        if (!$allowProbe) {
            continue;
        }

        if ($probes[$group] >= SYNC_PROBE_MAX_PER_GROUP) {
            continue;
        }
        $probes[$group]++;

        if (!empty(oddsFetchEvents($key, $forceRefresh))) {
            $selected[$key] = $meta;
        }
    }

    $sports = array_values($selected);
    usort($sports, static function ($a, $b) {
        $ga = $a['group'] ?? '';
        $gb = $b['group'] ?? '';
        $order = array_flip(ODDS_SPORT_GROUPS);
        $oa = $order[$ga] ?? 99;
        $ob = $order[$gb] ?? 99;
        if ($oa !== $ob) {
            return $oa <=> $ob;
        }
        $wa = oddsSportPriorityWeight($a['key'] ?? '', $ga);
        $wb = oddsSportPriorityWeight($b['key'] ?? '', $gb);
        if ($wa !== $wb) {
            return $wb <=> $wa;
        }

        return strcmp($a['key'] ?? '', $b['key'] ?? '');
    });

    $byGroup = [];
    foreach (ODDS_SPORT_GROUPS as $group) {
        $byGroup[$group] = [];
    }
    foreach ($sports as $sport) {
        $group = $sport['group'] ?? '';
        if (isset($byGroup[$group])) {
            $byGroup[$group][] = $sport;
        }
    }

    $out = [];
    foreach (ODDS_SPORT_GROUPS as $group) {
        $cap = ($group === 'Soccer') ? SYNC_MAX_SOCCER_SPORTS : count($byGroup[$group]);
        $out = array_merge($out, array_slice($byGroup[$group], 0, $cap));
    }

    return $out;
}

/**
 * Coupe une liste de sports en gardant un mix Tennis / Basket / Foot.
 * Sans ça, le tennis (dizaines de tournois) mange tout SYNC_FORCE_MAX_SPORTS.
 *
 * @param list<array<string,mixed>> $sports
 * @return list<array<string,mixed>>
 */
function oddsLimitSportsBalanced(array $sports, int $max): array
{
    if ($max <= 0 || count($sports) <= $max) {
        return $sports;
    }

    $buckets = [];
    foreach (ODDS_SPORT_GROUPS as $group) {
        $buckets[$group] = [];
    }
    foreach ($sports as $sport) {
        $group = $sport['group'] ?? '';
        if (isset($buckets[$group])) {
            $buckets[$group][] = $sport;
        }
    }

    // Soft caps : laisser de la place au foot (beaucoup de ligues) + basket.
    $soft = [
        'Tennis'     => 12,
        'Basketball' => 12,
        'Soccer'     => 20,
    ];

    $picked = [];
    foreach (ODDS_SPORT_GROUPS as $group) {
        $room = $max - count($picked);
        if ($room <= 0) {
            break;
        }
        $take = min(count($buckets[$group]), (int) ($soft[$group] ?? 0), $room);
        if ($take > 0) {
            $picked = array_merge($picked, array_slice($buckets[$group], 0, $take));
            $buckets[$group] = array_slice($buckets[$group], $take);
        }
    }

    // Compléter en round-robin s'il reste de la place.
    while (count($picked) < $max) {
        $added = false;
        foreach (ODDS_SPORT_GROUPS as $group) {
            if (count($picked) >= $max) {
                break;
            }
            if ($buckets[$group] === []) {
                continue;
            }
            $picked[] = array_shift($buckets[$group]);
            $added = true;
        }
        if (!$added) {
            break;
        }
    }

    return $picked;
}

/**
 * DÉSACTIVÉ — endpoint payant (/upcoming/odds, 1–2 crédits).
 * L'import calendrier utilise /events (gratuit). Ne jamais réactiver sans budget payant.
 */
function oddsFetchCrossSportUpcoming(bool $forceRefresh = false): array
{
    if (!$forceRefresh) {
        $cached = oddsCacheGet('cross_upcoming', 900);
        if ($cached !== null) {
            return $cached;
        }
    }
    $stale = oddsCacheGetStale('cross_upcoming');

    return $stale['payload'] ?? [];
}

function oddsFetchUpcomingEvents(bool $allowStale = false, bool $forceRefresh = false): array
{
    if ($allowStale) {
        $path = oddsCachePath('events_upcoming');
        if (is_file($path)) {
            $raw = file_get_contents($path);
            $data = json_decode($raw, true);
            if (is_array($data['payload'] ?? null)) {
                return $data['payload'];
            }
        }
        return [];
    }

    if (!$forceRefresh) {
        $cached = oddsCacheGet('events_upcoming', ODDS_CACHE_TTL_EVENTS);
        if ($cached !== null) {
            return $cached;
        }
    }

    $data = oddsApiRequest('/v4/sports/upcoming/events');
    if (!is_array($data)) {
        return [];
    }

    oddsCacheSet('events_upcoming', $data);
    return $data;
}

/**
 * Matchs à venir pour un sport.
 * GET /v4/sports/{sport}/events — ne consomme PAS de quota.
 */
function oddsFetchEvents(string $sportKey, bool $forceRefresh = false): array
{
    static $mem = [];
    $memKey = $sportKey . ($forceRefresh ? ':f' : ':n');
    if (isset($mem[$memKey])) {
        return $mem[$memKey];
    }

    $cacheKey = 'events_' . $sportKey;
    if (!$forceRefresh) {
        $cached = oddsCacheGet($cacheKey, ODDS_CACHE_TTL_EVENTS);
        if ($cached !== null) {
            $mem[$memKey] = $cached;
            return $cached;
        }
    }

    // Doc API : pas de filtre temporel côté API — on filtre à l'import (importWindowBounds).
    $data = oddsApiRequest('/v4/sports/' . rawurlencode($sportKey) . '/events');
    if (!oddsIsEventList($data)) {
        $data = oddsReadEventsCache($sportKey, true);
    } else {
        oddsCacheSet($cacheKey, $data);
    }

    $mem[$memKey] = $data;
    return $data;
}

/** Lit le cache événements même expiré (rotation locale sans appel API). */
function oddsReadEventsCache(string $sportKey, bool $allowStale = false): array
{
    if (!$allowStale) {
        return oddsFetchEvents($sportKey);
    }

    $path = oddsCachePath('events_' . $sportKey);
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['payload']) || !is_array($data['payload'])) {
        return [];
    }
    return $data['payload'];
}

/**
 * Scores récents (live + terminés sur ~3 jours avec daysFrom).
 * GET /v4/sports/{sport}/scores — coûte 2 crédits si daysFrom > 0, sinon 1.
 */
/** Vraie liste d'événements (et non un objet d'erreur renvoyé par l'API). */
function oddsIsEventList($data): bool
{
    if (!is_array($data)) {
        return false;
    }
    if ($data === []) {
        return true;
    }

    return array_keys($data) === range(0, count($data) - 1);
}

function oddsFetchScores(string $sportKey, int $daysFrom = 1, bool $bypassCache = false): array
{
    $cacheKey = 'scores_' . $sportKey . '_d' . $daysFrom;
    if (!$bypassCache) {
        $cached = oddsCacheGet($cacheKey, ODDS_CACHE_TTL_SCORES);
        if ($cached !== null) {
            return $cached;
        }
    }

    // Cache froid : refuse si pas assez de crédits — sert le stale plutôt que [].
    if (!oddsQuotaAllows('scores')) {
        $stale = oddsCacheGetStale($cacheKey);
        return $stale['payload'] ?? [];
    }
    $remaining = oddsQuotaRemaining();
    if ($remaining !== null && $remaining < oddsScoresCreditCost($daysFrom)) {
        $stale = oddsCacheGetStale($cacheKey);
        return $stale['payload'] ?? [];
    }

    $data = oddsApiRequest('/v4/sports/' . rawurlencode($sportKey) . '/scores', [
        'daysFrom'   => $daysFrom,
        'dateFormat' => 'iso',
    ]);
    if (!oddsIsEventList($data)) {
        // Échec API / 401 : ne pas brûler une passe suivante pour rien — réutiliser le dernier cache.
        $stale = oddsCacheGetStale($cacheKey);
        return $stale['payload'] ?? [];
    }

    oddsCacheSet($cacheKey, $data);
    return $data;
}

/** Cotes bulk (probas h2h). Coût = nb marchés × nb régions. Jamais d'appel si BDD déjà remplie. */
function oddsFetchSportOdds(string $sportKey, string $markets = 'h2h', ?string $regions = null, bool $bypassCache = false): array
{
    $regions  = $regions ?? ODDS_REGIONS;
    $cacheKey = 'odds_' . $sportKey . '_' . md5($markets . '|' . $regions);
    if (!$bypassCache) {
        $cached = oddsCacheGet($cacheKey, ODDS_CACHE_TTL_ODDS);
        if ($cached !== null) {
            return $cached;
        }
    }

    if (!oddsQuotaAllows('odds')) {
        $stale = oddsCacheGetStale($cacheKey);
        return $stale['payload'] ?? [];
    }

    $data = oddsApiRequest('/v4/sports/' . rawurlencode($sportKey) . '/odds', [
        'regions'    => $regions,
        'markets'    => $markets,
        'oddsFormat' => 'decimal',
    ]);
    if (!oddsIsEventList($data)) {
        $stale = oddsCacheGetStale($cacheKey);
        return $stale['payload'] ?? [];
    }

    oddsCacheSet($cacheKey, $data);
    return $data;
}

/**
 * Une seule région (ODDS_REGIONS). Le fallback UK est désactivé en prod :
 * un 2ᵉ appel vide double le coût pour souvent 0 cote utile.
 */
function oddsFetchSportOddsResilient(string $sportKey, string $markets = 'h2h', bool $bypassCache = false): array
{
    $data = oddsFetchSportOdds($sportKey, $markets, ODDS_REGIONS, $bypassCache);
    if (
        !empty($data)
        || ODDS_REGIONS_FALLBACK === ''
        || ODDS_REGIONS_FALLBACK === ODDS_REGIONS
        || !oddsQuotaAllows('odds')
    ) {
        return $data;
    }
    return oddsFetchSportOdds($sportKey, $markets, ODDS_REGIONS_FALLBACK, $bypassCache);
}

/**
 * Cotes d'un match (props joueurs). 1 crédit = 1 marché × 1 région.
 * Ne doit PAS être appelé en auto (cron / page) — trop cher pour 500 crédits/mois.
 */
function oddsFetchEventOdds(string $sportKey, string $eventId, string $markets, ?string $regions = null): array
{
    $regions  = $regions ?? ODDS_SCORER_REGIONS;
    $cacheKey = 'evodds_' . $sportKey . '_' . $eventId . '_' . md5($markets . '|' . $regions);
    $cached   = oddsCacheGet($cacheKey, ODDS_CACHE_TTL_ODDS);
    if ($cached !== null) {
        return $cached;
    }

    if (!oddsQuotaAllows('scorers')) {
        $stale = oddsCacheGetStale($cacheKey);
        return $stale['payload'] ?? [];
    }

    $path = '/v4/sports/' . rawurlencode($sportKey) . '/events/' . rawurlencode($eventId) . '/odds';
    $data = oddsApiRequest($path, [
        'regions'    => $regions,
        'markets'    => $markets,
        'oddsFormat' => 'decimal',
    ]);
    if (!is_array($data) || $data === []) {
        $stale = oddsCacheGetStale($cacheKey);
        return $stale['payload'] ?? [];
    }

    oddsCacheSet($cacheKey, $data);
    return $data;
}

function markSportOddsAvailability(string $sportKey, bool $available): void
{
    oddsCacheSet('odds_avail_' . $sportKey, ['available' => $available]);
}

/** null = inconnu, true/false = dernier fetch /odds pour ce sport. */
function sportOddsAvailable(string $sportKey): ?bool
{
    $cached = oddsCacheGet('odds_avail_' . $sportKey, 86400);
    if ($cached === null) {
        return null;
    }
    return !empty($cached['available']);
}
