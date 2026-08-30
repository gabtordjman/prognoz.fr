<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

require_once __DIR__ . '/scoring.php';

if (!defined('FAV_TEAMS_MAX')) {
    define('FAV_TEAMS_MAX', 3); // sélections nationales max
}

/**
 * Sélections nationales (noms Odds API / anglais) — toujours proposées même hors compétition.
 *
 * @return list<string>
 */
function curatedNationalTeamNames(): array
{
    return [
        'Argentina', 'Australia', 'Austria', 'Belgium', 'Brazil', 'Cameroon',
        'Canada', 'Chile', 'China', 'Colombia', 'Croatia', 'Czech Republic',
        'Denmark', 'Ecuador', 'Egypt', 'England', 'Finland', 'France',
        'Germany', 'Ghana', 'Greece', 'Hungary', 'Iceland', 'Iran',
        'Ireland', 'Italy', 'Ivory Coast', 'Japan', 'Mexico', 'Morocco',
        'Netherlands', 'Nigeria', 'Northern Ireland', 'Norway', 'Poland',
        'Portugal', 'Romania', 'Russia', 'Saudi Arabia', 'Scotland', 'Senegal',
        'Serbia', 'Slovakia', 'Slovenia', 'South Africa', 'South Korea',
        'Spain', 'Sweden', 'Switzerland', 'Tunisia', 'Turkey', 'Ukraine',
        'United States', 'Uruguay', 'Wales',
        // Alias fréquents Odds API
        'USA', 'Korea Republic', 'Republic of Ireland', "Cote d'Ivoire",
    ];
}

/** @return array<string,true> clés normalizeTeamName */
function nationalTeamNameSet(): array
{
    static $set = null;
    if ($set !== null) {
        return $set;
    }
    $set = [];
    foreach (curatedNationalTeamNames() as $name) {
        $k = normalizeTeamName($name);
        if ($k !== '') {
            $set[$k] = true;
        }
    }

    return $set;
}

function isNationalTeamName(string $name): bool
{
    $k = normalizeTeamName($name);

    return $k !== '' && isset(nationalTeamNameSet()[$k]);
}

function ensureFavoriteTeamSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $col = $pdo->query('SHOW COLUMNS FROM users LIKE "equipe_favorie"')->fetch();
        if (!$col) {
            $pdo->exec(
                'ALTER TABLE users ADD COLUMN equipe_favorie VARCHAR(150) NULL DEFAULT NULL AFTER sport_favori'
            );
        }
    } catch (PDOException $e) {
        // ignore
    }

    try {
        $col = $pdo->query('SHOW COLUMNS FROM users LIKE "equipes_favorites"')->fetch();
        if (!$col) {
            $pdo->exec(
                'ALTER TABLE users ADD COLUMN equipes_favorites JSON NULL DEFAULT NULL AFTER equipe_favorie'
            );
        }
    } catch (PDOException $e) {
        // MySQL sans JSON : TEXT
        try {
            $col = $pdo->query('SHOW COLUMNS FROM users LIKE "equipes_favorites"')->fetch();
            if (!$col) {
                $pdo->exec(
                    'ALTER TABLE users ADD COLUMN equipes_favorites TEXT NULL DEFAULT NULL AFTER equipe_favorie'
                );
            }
        } catch (PDOException $e2) {
            // ignore
        }
    }

    // Ne plus copier le club dans equipes_favorites.
    // Répare les profils mélangés : club → equipe_favorie, sélections → equipes_favorites.
    try {
        migrateFavoriteClubAndNationals($pdo);
    } catch (Throwable $e) {
        // ignore
    }

    try {
        $col = $pdo->query("SHOW COLUMNS FROM prediction_markets LIKE 'type'")->fetch(PDO::FETCH_ASSOC);
        $typeDef = (string) ($col['Type'] ?? '');
        if ($typeDef !== '' && stripos($typeDef, 'fav_team') === false) {
            $pdo->exec(
                "ALTER TABLE prediction_markets
                 MODIFY COLUMN type ENUM('1x2', 'buteur', 'score_exact', 'fav_team') NOT NULL"
            );
        }
    } catch (PDOException $e) {
        // ignore
    }

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS fav_team_notified (
                user_id INT NOT NULL,
                match_id INT NOT NULL,
                notified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, match_id),
                KEY idx_fav_notif_match (match_id),
                CONSTRAINT fk_fav_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_fav_notif_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (PDOException $e) {
        // ignore
    }
}

/**
 * Une fois : sépare club (equipe_favorie) et sélections (equipes_favorites).
 */
function migrateFavoriteClubAndNationals(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $flag = defined('APP_CACHE_DIR') ? (APP_CACHE_DIR . '/fav_club_nat_migrated.txt') : null;
    if ($flag && is_file($flag)) {
        return;
    }

    $rows = $pdo->query(
        'SELECT id, equipe_favorie, equipes_favorites FROM users'
    )->fetchAll() ?: [];
    $upd = $pdo->prepare(
        'UPDATE users SET equipe_favorie = ?, equipes_favorites = ? WHERE id = ?'
    );

    foreach ($rows as $row) {
        $club = null;
        $nats = [];
        $seenNat = [];

        $legacyClub = trim((string) ($row['equipe_favorie'] ?? ''));
        if ($legacyClub !== '') {
            if (isNationalTeamName($legacyClub)) {
                $canon = resolveNationalTeamCanonical($legacyClub) ?? $legacyClub;
                $k = normalizeTeamName($canon);
                if ($k !== '' && !isset($seenNat[$k])) {
                    $seenNat[$k] = true;
                    $nats[] = $canon;
                }
            } else {
                $club = $legacyClub;
            }
        }

        foreach (decodeFavoriteTeamsJson(
            isset($row['equipes_favorites']) ? (string) $row['equipes_favorites'] : null
        ) as $name) {
            if (isNationalTeamName($name)) {
                $canon = resolveNationalTeamCanonical($name) ?? $name;
                $k = normalizeTeamName($canon);
                if ($k === '' || isset($seenNat[$k])) {
                    continue;
                }
                $seenNat[$k] = true;
                $nats[] = $canon;
                if (count($nats) >= (int) FAV_TEAMS_MAX) {
                    break;
                }
            } elseif ($club === null) {
                // Ancien slot « équipe » qui était un club stocké dans le JSON
                $club = $name;
            }
        }

        $nats = array_slice($nats, 0, (int) FAV_TEAMS_MAX);
        $json = $nats === [] ? null : json_encode(array_values($nats), JSON_UNESCAPED_UNICODE);
        $upd->execute([$club, $json, (int) $row['id']]);
    }

    if ($flag && defined('APP_CACHE_DIR') && function_exists('ensureAppCacheDir') && ensureAppCacheDir()) {
        @file_put_contents($flag, (string) time());
    }
}

/** @return list<string> clubs only (hors sélections) */
function listFavoriteClubChoices(PDO $pdo, int $limit = 500): array
{
    $limit = max(80, min(900, $limit));
    $horizon = (int) MATCHS_IMPORT_HORIZON_JOURS;
    $stmt = $pdo->query(
        "SELECT DISTINCT equipe FROM (
            SELECT equipe_home AS equipe FROM matches
             WHERE equipe_home IS NOT NULL AND equipe_home != ''
               AND date_match >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 DAY)
               AND date_match <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL {$horizon} DAY)
            UNION
            SELECT equipe_away AS equipe FROM matches
             WHERE equipe_away IS NOT NULL AND equipe_away != ''
               AND date_match >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 DAY)
               AND date_match <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL {$horizon} DAY)
         ) t
         ORDER BY equipe ASC
         LIMIT {$limit}"
    );
    $seen = [];
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
        $name = trim((string) $name);
        if ($name === '' || isNationalTeamName($name)) {
            continue;
        }
        $key = normalizeTeamName($name);
        if ($key === '' || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $name;
    }
    usort($out, static fn (string $a, string $b): int => strcasecmp($a, $b));

    return $out;
}

/** @return list<string> sélections (liste curated, noms canoniques uniques) */
function listFavoriteNationalChoices(): array
{
    $seen = [];
    $out = [];
    foreach (curatedNationalTeamNames() as $name) {
        $canon = resolveNationalTeamCanonical($name) ?? $name;
        $key = normalizeTeamName($canon);
        if ($key === '' || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $canon;
    }
    usort($out, static fn (string $a, string $b): int => strcasecmp($a, $b));

    return $out;
}

/**
 * Club + sélections (whitelist complète pour résolution de picks).
 *
 * @return list<string>
 */
function listFavoriteTeamChoices(PDO $pdo, int $limit = 500): array
{
    $out = listFavoriteClubChoices($pdo, $limit);
    $seen = [];
    foreach ($out as $name) {
        $seen[normalizeTeamName($name)] = true;
    }
    foreach (listFavoriteNationalChoices() as $nat) {
        $k = normalizeTeamName($nat);
        if ($k === '' || isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $out[] = $nat;
    }
    usort($out, static fn (string $a, string $b): int => strcasecmp($a, $b));

    return $out;
}

function resolveNationalTeamCanonical(string $team): ?string
{
    $team = trim($team);
    if ($team === '') {
        return null;
    }
    $want = normalizeTeamName($team);
    // Préférer le premier nom curated (ex. France plutôt qu’un alias)
    foreach (curatedNationalTeamNames() as $choice) {
        if (normalizeTeamName($choice) === $want) {
            return $choice;
        }
    }

    return null;
}

/**
 * @return list<string>
 */
function decodeFavoriteTeamsJson(?string $raw): array
{
    if ($raw === null || trim($raw) === '' || $raw === 'null') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    $seen = [];
    foreach ($decoded as $item) {
        if (!is_string($item) && !is_numeric($item)) {
            continue;
        }
        $name = trim((string) $item);
        if ($name === '') {
            continue;
        }
        $key = normalizeTeamName($name);
        if ($key === '' || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $name;
        if (count($out) >= (int) FAV_TEAMS_MAX) {
            break;
        }
    }

    return $out;
}

/** Club préféré (1 max). */
function userFavoriteClub(?array $user): ?string
{
    if (!$user) {
        return null;
    }
    $club = trim((string) ($user['equipe_favorie'] ?? ''));
    if ($club === '' || isNationalTeamName($club)) {
        return null;
    }

    return $club;
}

/**
 * Sélections nationales préférées (0–3).
 *
 * @return list<string>
 */
function userFavoriteNationals(?array $user): array
{
    if (!$user) {
        return [];
    }
    $out = [];
    $seen = [];
    foreach (decodeFavoriteTeamsJson(
        isset($user['equipes_favorites']) ? (string) $user['equipes_favorites'] : null
    ) as $name) {
        if (!isNationalTeamName($name)) {
            continue;
        }
        $canon = resolveNationalTeamCanonical($name) ?? $name;
        $k = normalizeTeamName($canon);
        if ($k === '' || isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $out[] = $canon;
        if (count($out) >= (int) FAV_TEAMS_MAX) {
            break;
        }
    }

    return $out;
}

/**
 * Toutes les équipes suivies (club + sélections) pour marchés / notifs.
 *
 * @return list<string>
 */
function userFavoriteTeams(?array $user): array
{
    $out = [];
    $seen = [];
    $club = userFavoriteClub($user);
    if ($club !== null) {
        $out[] = $club;
        $seen[normalizeTeamName($club)] = true;
    }
    foreach (userFavoriteNationals($user) as $nat) {
        $k = normalizeTeamName($nat);
        if ($k === '' || isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $out[] = $nat;
    }

    return $out;
}

/** Première équipe suivie (compat). */
function userFavoriteTeam(?array $user): ?string
{
    $teams = userFavoriteTeams($user);

    return $teams[0] ?? null;
}

/**
 * Favorites présentes dans le match (ordre profil), max 2.
 *
 * @param list<string> $teams
 * @return list<string>
 */
function matchFavoriteTeamsInMatch(array $match, array $teams): array
{
    $found = [];
    foreach ($teams as $team) {
        if (favoriteTeamSide($match, $team) !== null) {
            $found[] = $team;
        }
        if (count($found) >= 2) {
            break;
        }
    }

    return $found;
}

function matchIncludesFavoriteTeam(array $match, ?string $team): bool
{
    return favoriteTeamSide($match, $team) !== null;
}

function matchIncludesAnyFavoriteTeam(array $match, array $teams): bool
{
    return matchFavoriteTeamsInMatch($match, $teams) !== [];
}

/**
 * @return 'home'|'away'|null
 */
function favoriteTeamSide(array $match, ?string $team): ?string
{
    if ($team === null || trim($team) === '') {
        return null;
    }
    $want = normalizeTeamName($team);
    if ($want === '') {
        return null;
    }
    $home = normalizeTeamName((string) ($match['equipe_home'] ?? ''));
    $away = normalizeTeamName((string) ($match['equipe_away'] ?? ''));
    if ($home !== '' && $home === $want) {
        return 'home';
    }
    if ($away !== '' && $away === $want) {
        return 'away';
    }

    return null;
}

function isAllowedFavoriteTeam(PDO $pdo, string $team): bool
{
    return resolveFavoriteTeamCanonical($pdo, $team) !== null;
}

function resolveFavoriteTeamCanonical(PDO $pdo, string $team): ?string
{
    $team = trim($team);
    if ($team === '') {
        return null;
    }
    $nat = resolveNationalTeamCanonical($team);
    if ($nat !== null) {
        return $nat;
    }
    $want = normalizeTeamName($team);
    foreach (listFavoriteClubChoices($pdo) as $choice) {
        if (normalizeTeamName($choice) === $want) {
            return $choice;
        }
    }

    return null;
}

/**
 * @throws InvalidArgumentException
 */
function normalizeFavoriteClubInput(PDO $pdo, ?string $club): ?string
{
    $club = $club !== null ? trim($club) : '';
    if ($club === '') {
        return null;
    }
    if (isNationalTeamName($club)) {
        throw new InvalidArgumentException(t('dash.fav_club_not_national'));
    }
    $canonical = resolveFavoriteTeamCanonical($pdo, $club);
    if ($canonical === null || isNationalTeamName($canonical)) {
        throw new InvalidArgumentException(t('dash.fav_club_invalid'));
    }

    return $canonical;
}

/**
 * @param list<string> $teams
 * @return list<string>
 * @throws InvalidArgumentException
 */
function normalizeFavoriteNationalsInput(array $teams): array
{
    $out = [];
    $seen = [];
    foreach ($teams as $raw) {
        $raw = trim((string) $raw);
        if ($raw === '') {
            continue;
        }
        $canonical = resolveNationalTeamCanonical($raw);
        if ($canonical === null) {
            throw new InvalidArgumentException(t('dash.fav_national_invalid'));
        }
        $key = normalizeTeamName($canonical);
        if (isset($seen[$key])) {
            throw new InvalidArgumentException(t('dash.fav_team_duplicate'));
        }
        $seen[$key] = true;
        $out[] = $canonical;
        if (count($out) > (int) FAV_TEAMS_MAX) {
            throw new InvalidArgumentException(t('dash.fav_team_too_many'));
        }
    }

    return $out;
}

/**
 * Legacy helper (liste mixte) — préfère normalizeFavoriteClubInput / Nationals.
 *
 * @param list<string> $teams
 * @return list<string>
 */
function normalizeFavoriteTeamsInput(PDO $pdo, array $teams): array
{
    $out = [];
    $seen = [];
    foreach ($teams as $raw) {
        $raw = trim((string) $raw);
        if ($raw === '') {
            continue;
        }
        $canonical = resolveFavoriteTeamCanonical($pdo, $raw);
        if ($canonical === null) {
            throw new InvalidArgumentException(t('dash.fav_team_invalid'));
        }
        $key = normalizeTeamName($canonical);
        if (isset($seen[$key])) {
            throw new InvalidArgumentException(t('dash.fav_team_duplicate'));
        }
        $seen[$key] = true;
        $out[] = $canonical;
    }

    return $out;
}

/**
 * @return list<string>
 */
function fetchUserFavoriteTeams(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT equipe_favorie, equipes_favorites FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    return userFavoriteTeams($row ?: null);
}

function fetchUserFavoriteTeam(PDO $pdo, int $userId): ?string
{
    $teams = fetchUserFavoriteTeams($pdo, $userId);

    return $teams[0] ?? null;
}

/**
 * Parse W / L / W:Team / L:Team.
 *
 * @return array{outcome:string,team:?string}|null
 */
function parseFavTeamPick(string $reponse): ?array
{
    $reponse = trim($reponse);
    if (preg_match('/^([WL]):(.{1,120})$/u', $reponse, $m)) {
        $team = trim($m[2]);

        return $team !== '' ? ['outcome' => $m[1], 'team' => $team] : null;
    }
    if ($reponse === 'W' || $reponse === 'L') {
        return ['outcome' => $reponse, 'team' => null];
    }

    return null;
}

function encodeFavTeamPick(string $outcome, string $team): string
{
    $outcome = strtoupper($outcome) === 'L' ? 'L' : 'W';

    return $outcome . ':' . trim($team);
}

/**
 * Prono fav_team gagnant ? W = mon équipe gagne, L = mon équipe perd. Nul = perdu.
 *
 * @param list<string>|null $userTeams pour résoudre les anciens picks W/L
 */
function favTeamPickIsCorrect(array $match, ?string $favTeam, string $reponse, ?array $userTeams = null): bool
{
    $parsed = parseFavTeamPick($reponse);
    if ($parsed === null) {
        return false;
    }
    $team = $parsed['team'];
    if ($team === null || $team === '') {
        if ($favTeam) {
            $team = $favTeam;
        } elseif (is_array($userTeams) && $userTeams !== []) {
            $inMatch = matchFavoriteTeamsInMatch($match, $userTeams);
            $team = $inMatch[0] ?? null;
        }
    }
    if ($team === null || $team === '') {
        return false;
    }
    $side = favoriteTeamSide($match, $team);
    if ($side === null) {
        return false;
    }
    $r = (string) ($match['resultat_1x2'] ?? '');
    if ($r !== '1' && $r !== '2') {
        return false;
    }
    $favWon = ($side === 'home' && $r === '1') || ($side === 'away' && $r === '2');
    $favLost = ($side === 'home' && $r === '2') || ($side === 'away' && $r === '1');

    if ($parsed['outcome'] === 'W') {
        return $favWon;
    }
    if ($parsed['outcome'] === 'L') {
        return $favLost;
    }

    return false;
}

/**
 * @return array{candidates:int,sent:int,skipped:int}
 */
function maybeNotifyFavoriteTeamMatches(PDO $pdo): array
{
    $stats = ['candidates' => 0, 'sent' => 0, 'skipped' => 0];
    if (!function_exists('pushIsConfigured') || !pushIsConfigured()) {
        return $stats;
    }

    ensureFavoriteTeamSchema($pdo);
    $horizon = (int) MATCHS_HORIZON_JOURS;
    $closeMins = (int) MATCH_CLOSE_AFTER_MINUTES;
    $now = matchSqlNow();

    $matches = $pdo->query(
        "SELECT id, equipe_home, equipe_away, competition, date_match
         FROM matches
         WHERE statut = 'a_venir'
           AND external_id IS NOT NULL
           AND date_match > DATE_SUB({$now}, INTERVAL {$closeMins} MINUTE)
           AND date_match <= DATE_ADD({$now}, INTERVAL {$horizon} DAY)
         ORDER BY date_match ASC
         LIMIT 120"
    )->fetchAll() ?: [];

    if ($matches === []) {
        return $stats;
    }

    $users = $pdo->query(
        "SELECT u.id, u.equipe_favorie, u.equipes_favorites, u.preferred_lang
         FROM users u
         WHERE u.actif = 1
           AND EXISTS (
             SELECT 1 FROM push_subscriptions ps WHERE ps.user_id = u.id
           )
           AND (
             (u.equipes_favorites IS NOT NULL AND u.equipes_favorites != '' AND u.equipes_favorites != 'null' AND u.equipes_favorites != '[]')
             OR (u.equipe_favorie IS NOT NULL AND u.equipe_favorie != '')
           )"
    )->fetchAll() ?: [];

    if ($users === []) {
        return $stats;
    }

    $check = $pdo->prepare(
        'SELECT 1 FROM fav_team_notified WHERE user_id = ? AND match_id = ? LIMIT 1'
    );
    $mark = $pdo->prepare(
        'INSERT IGNORE INTO fav_team_notified (user_id, match_id, notified_at) VALUES (?, ?, UTC_TIMESTAMP())'
    );

    foreach ($users as $user) {
        $userId = (int) $user['id'];
        $favs = userFavoriteTeams($user);
        if ($favs === []) {
            continue;
        }
        foreach ($matches as $match) {
            $inMatch = matchFavoriteTeamsInMatch($match, $favs);
            if ($inMatch === []) {
                continue;
            }
            $matchId = (int) $match['id'];
            $stats['candidates']++;
            $check->execute([$userId, $matchId]);
            if ($check->fetchColumn()) {
                $stats['skipped']++;
                continue;
            }
            $teamLabel = implode(' / ', $inMatch);
            $lang = resolveMailLang($user);
            [$title, $body] = withLang($lang, static function () use ($teamLabel, $match): array {
                return [
                    t('fav.push_title'),
                    t('fav.push_body', [
                        'team' => $teamLabel,
                        'home' => (string) ($match['equipe_home'] ?? ''),
                        'away' => (string) ($match['equipe_away'] ?? ''),
                    ]),
                ];
            });
            $sent = sendPushToUser($pdo, $userId, [
                'title' => $title,
                'body'  => $body,
                'url'   => url('index.php'),
                'tag'   => 'prognoz-fav-' . $matchId,
            ]);
            $mark->execute([$userId, $matchId]);
            if ($sent > 0) {
                $stats['sent']++;
            } else {
                $stats['skipped']++;
            }
        }
    }

    return $stats;
}

function ensureFavTeamMarketsForOpenMatches(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ensureFavoriteTeamSchema($pdo);
    $pts = (int) POINTS_FAV_TEAM;
    try {
        $pdo->exec(
            "INSERT INTO prediction_markets (match_id, type, points_si_correct, ferme_le)
             SELECT m.id, 'fav_team', {$pts}, m.date_match
             FROM matches m
             WHERE m.statut IN ('a_venir', 'en_cours')
               AND NOT EXISTS (
                   SELECT 1 FROM prediction_markets pm
                   WHERE pm.match_id = m.id AND pm.type = 'fav_team'
               )"
        );
    } catch (PDOException $e) {
        // ENUM pas encore migré, etc.
    }
}
