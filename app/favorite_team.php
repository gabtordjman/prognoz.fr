<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

require_once __DIR__ . '/scoring.php';

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

/** @return list<string> */
function listFavoriteTeamChoices(PDO $pdo, int $limit = 400): array
{
    $limit = max(50, min(800, $limit));
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
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
        $name = trim((string) $name);
        if ($name !== '') {
            $out[] = $name;
        }
    }

    return $out;
}

function userFavoriteTeam(?array $user): ?string
{
    if (!$user) {
        return null;
    }
    $team = trim((string) ($user['equipe_favorie'] ?? ''));

    return $team !== '' ? $team : null;
}

function matchIncludesFavoriteTeam(array $match, ?string $team): bool
{
    return favoriteTeamSide($match, $team) !== null;
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
    $team = trim($team);
    if ($team === '') {
        return false;
    }
    foreach (listFavoriteTeamChoices($pdo) as $choice) {
        if (normalizeTeamName($choice) === normalizeTeamName($team)) {
            return true;
        }
    }

    return false;
}

/**
 * Résout le nom canonique (casse BDD) pour une équipe choisie.
 */
function resolveFavoriteTeamCanonical(PDO $pdo, string $team): ?string
{
    $team = trim($team);
    if ($team === '') {
        return null;
    }
    $want = normalizeTeamName($team);
    foreach (listFavoriteTeamChoices($pdo) as $choice) {
        if (normalizeTeamName($choice) === $want) {
            return $choice;
        }
    }

    return null;
}

function fetchUserFavoriteTeam(PDO $pdo, int $userId): ?string
{
    $stmt = $pdo->prepare('SELECT equipe_favorie FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    return userFavoriteTeam($row ?: null);
}

/**
 * Prono fav_team gagnant ? W = mon équipe gagne, L = mon équipe perd. Nul = perdu.
 */
function favTeamPickIsCorrect(array $match, ?string $favTeam, string $reponse): bool
{
    $side = favoriteTeamSide($match, $favTeam);
    if ($side === null) {
        return false;
    }
    $r = (string) ($match['resultat_1x2'] ?? '');
    if ($r !== '1' && $r !== '2') {
        return false; // nul ou absent
    }
    $favWon = ($side === 'home' && $r === '1') || ($side === 'away' && $r === '2');
    $favLost = ($side === 'home' && $r === '2') || ($side === 'away' && $r === '1');

    if ($reponse === 'W') {
        return $favWon;
    }
    if ($reponse === 'L') {
        return $favLost;
    }

    return false;
}

/**
 * Notifie les joueurs qu’un match de leur équipe préférée est dispo.
 *
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
        "SELECT u.id, u.equipe_favorie, u.preferred_lang
         FROM users u
         WHERE u.actif = 1
           AND u.equipe_favorie IS NOT NULL
           AND u.equipe_favorie != ''
           AND EXISTS (
             SELECT 1 FROM push_subscriptions ps WHERE ps.user_id = u.id
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
        $fav = trim((string) ($user['equipe_favorie'] ?? ''));
        if ($fav === '') {
            continue;
        }
        foreach ($matches as $match) {
            if (!matchIncludesFavoriteTeam($match, $fav)) {
                continue;
            }
            $matchId = (int) $match['id'];
            $stats['candidates']++;
            $check->execute([$userId, $matchId]);
            if ($check->fetchColumn()) {
                $stats['skipped']++;
                continue;
            }
            $lang = resolveMailLang($user);
            [$title, $body] = withLang($lang, static function () use ($fav, $match): array {
                return [
                    t('fav.push_title'),
                    t('fav.push_body', [
                        'team' => $fav,
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

/**
 * Crée les marchés fav_team manquants sur les matchs encore ouverts.
 */
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
