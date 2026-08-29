<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

function adminQueryHasColumn(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $tbl = preg_replace('/[^a-z0-9_]/i', '', $table) ?: 'users';
        $like = $pdo->quote($column);
        $row = $pdo->query("SHOW COLUMNS FROM `{$tbl}` LIKE {$like}")->fetch();
        $cache[$key] = (bool) $row;
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

/** @return array<string,mixed> */
function adminQueryDashboard(PDO $pdo): array
{
    $pending = countPendingPredictions($pdo);
    $quota = oddsQuotaState();
    $season = getActiveSeason($pdo);
    $users = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE actif = 1')->fetchColumn();
    $usersAll = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $communities = (int) $pdo->query('SELECT COUNT(*) FROM communities')->fetchColumn();
    $messages = (int) $pdo->query('SELECT COUNT(*) FROM community_messages WHERE supprime = 0')->fetchColumn();
    $matchesUpcoming = (int) $pdo->query(
        "SELECT COUNT(*) FROM matches WHERE statut = 'a_venir' AND date_match > UTC_TIMESTAMP()"
    )->fetchColumn();

    $needScore = listStuckMatchesForManualScore($pdo, 40);
    $needPoints = listMatchesAwaitingLocalScore($pdo, 40);
    $voidedMatches = listVoidedMatchesForManualScore($pdo, 40);
    $postponedMatches = listPostponedMatchesForAdmin($pdo, 80);

    return [
        'pending'            => $pending,
        'quota'              => $quota,
        'season'             => $season,
        'users_active'       => $users,
        'users_all'          => $usersAll,
        'communities'        => $communities,
        'messages'           => $messages,
        'matches_upcoming'   => $matchesUpcoming,
        'need_score'         => $needScore,
        'need_points'        => $needPoints,
        'voided'             => $voidedMatches,
        'postponed'          => $postponedMatches,
        'stuck_count'        => count($needScore),
        'local_count'        => count($needPoints),
        'voided_count'       => count($voidedMatches),
        'postponed_count'    => count($postponedMatches),
        'notify_email'       => adminNotifyEmail(),
    ];
}

/**
 * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,per_page:int}
 */
function adminQueryUsers(PDO $pdo, string $q = '', string $position = '', int $page = 1, int $perPage = 16): array
{
    $perPage = max(5, min(40, $perPage));
    $page = max(1, $page);
    $empty = [
        'rows'     => [],
        'total'    => 0,
        'page'     => 1,
        'pages'    => 1,
        'per_page' => $perPage,
    ];

    try {
        ensureMailPrefsSchema($pdo);
        ensureUserLastSeenSchema($pdo);
        ensureUserProfileExtrasSchema($pdo);
    } catch (Throwable $e) {
        // colonnes optionnelles
    }

    try {
        $where = '1=1';
        $params = [];
        $q = trim($q);
        $position = trim($position);
        if ($q !== '') {
            $where .= ' AND (pseudo LIKE ? OR email LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        } elseif ($position !== '') {
            $where .= ' AND pseudo >= ?';
            $params[] = $position;
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * $perPage;

        $cols = 'id, pseudo, email, actif, points_totaux, avatar_url, created_at';
        foreach (['mail_opt_out', 'last_seen_at', 'bio', 'sport_favori', 'serie_en_cours'] as $col) {
            if (adminQueryHasColumn($pdo, 'users', $col)) {
                $cols .= ', ' . $col;
            }
        }

        try {
            $sql = "SELECT {$cols} FROM users WHERE {$where} ORDER BY pseudo ASC LIMIT {$perPage} OFFSET {$offset}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            $sql = "SELECT id, pseudo, email, actif, points_totaux, avatar_url, created_at
                    FROM users WHERE {$where} ORDER BY pseudo ASC LIMIT {$perPage} OFFSET {$offset}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll() ?: [];
        }

        return [
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'pages'    => $pages,
            'per_page' => $perPage,
        ];
    } catch (Throwable $e) {
        error_log('adminQueryUsers: ' . $e->getMessage());

        return $empty;
    }
}

/** @return array<string,mixed>|null */
function adminQueryUserDossier(PDO $pdo, int $userId): ?array
{
    if ($userId < 1) {
        return null;
    }
    try {
        ensureMailPrefsSchema($pdo);
        ensureUserLastSeenSchema($pdo);
        ensureUserProfileExtrasSchema($pdo);
    } catch (Throwable $e) {
        // colonnes optionnelles
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return null;
    }

    $season = getActiveSeason($pdo);
    $seasonPts = 0;
    if ($season) {
        try {
            $sp = $pdo->prepare(
                'SELECT COALESCE(SUM(points), 0) FROM season_scores WHERE user_id = ? AND season_id = ?'
            );
            $sp->execute([$userId, (int) $season['id']]);
            $seasonPts = (int) $sp->fetchColumn();
        } catch (Throwable $e) {
            $seasonPts = 0;
        }
    }

    $communities = [];
    try {
        $communitiesRaw = $pdo->prepare(
            'SELECT c.id, c.nom, c.est_generale, cm.role, cm.joined_at
             FROM community_members cm
             INNER JOIN communities c ON c.id = cm.community_id
             WHERE cm.user_id = ?
             ORDER BY c.est_generale DESC, c.id ASC'
        );
        $communitiesRaw->execute([$userId]);
        foreach ($communitiesRaw->fetchAll() ?: [] as $row) {
            try {
                $dec = decryptCommunityRow($row, false);
            } catch (Throwable $e) {
                $dec = $row;
            }
            $communities[] = [
                'id'           => (int) $row['id'],
                'nom'          => (string) ($dec['nom'] ?? ('#' . $row['id'])),
                'est_generale' => !empty($row['est_generale']),
                'role'         => (string) ($row['role'] ?? 'membre'),
                'joined_at'    => (string) ($row['joined_at'] ?? ''),
            ];
        }
    } catch (Throwable $e) {
        $communities = [];
    }

    $friends = 0;
    try {
        $fr = $pdo->prepare(
            "SELECT COUNT(*) FROM friendships
             WHERE statut = 'accepte' AND (user_id = ? OR ami_id = ?)"
        );
        $fr->execute([$userId, $userId]);
        $friends = (int) $fr->fetchColumn();
    } catch (Throwable $e) {
        $friends = 0;
    }

    try {
        $predStats = getUserPredictionStats($pdo, $userId);
    } catch (Throwable $e) {
        $predStats = ['total' => 0, 'wins' => 0, 'losses' => 0, 'rate' => 0.0, 'points' => 0];
    }
    try {
        $history = getUserPredictionHistory($pdo, $userId, 8);
    } catch (Throwable $e) {
        $history = [];
    }
    $rewards = [];
    try {
        $rewards = fetchUserSeasonRewards($pdo, $userId, 5, null);
    } catch (Throwable $e) {
        $rewards = [];
    }

    return [
        'user'        => $user,
        'season'      => $season,
        'season_pts'  => $seasonPts,
        'communities' => $communities,
        'friends'     => $friends,
        'pred_stats'  => $predStats,
        'history'     => $history,
        'rewards'     => $rewards,
        'has_avatar'  => !empty($user['avatar_url']),
    ];
}

/**
 * @return array{
 *   need_score:list<array<string,mixed>>,
 *   need_score_api:list<array<string,mixed>>,
 *   need_score_old:list<array<string,mixed>>,
 *   need_points:list<array<string,mixed>>,
 *   voided:list<array<string,mixed>>,
 *   postponed:list<array<string,mixed>>,
 *   stuck_summary:array<string,mixed>,
 *   preds_by_match:array<int,list<array<string,mixed>>>,
 *   search:list<array<string,mixed>>
 * }
 */
function adminQueryScores(PDO $pdo, string $searchHome = '', string $searchAway = '', string $searchSport = '', int $searchLimit = 25): array
{
    $needScore = listStuckMatchesForManualScore($pdo, 100);
    $needPoints = listMatchesAwaitingLocalScore($pdo, 40);
    $voidedMatches = listVoidedMatchesForManualScore($pdo, 40);
    $postponedMatches = listPostponedMatchesForAdmin($pdo, 80);
    $stuckSummary = summarizeStuckScoresQueue($pdo);
    $needScoreApi = [];
    $needScoreOld = [];
    foreach ($needScore as $m) {
        if (matchIsInScoresApiWindow($m)) {
            $needScoreApi[] = $m;
        } else {
            $needScoreOld[] = $m;
        }
    }

    $searchResults = [];
    if ($searchHome !== '' || $searchAway !== '' || $searchSport !== '') {
        $searchResults = searchMatchesForManualScore($pdo, $searchHome, $searchAway, $searchLimit, $searchSport);
    }

    $predMatchIds = array_values(array_unique(array_merge(
        array_map(static fn ($m) => (int) $m['id'], $needScore),
        array_map(static fn ($m) => (int) $m['id'], $voidedMatches),
        array_map(static fn ($m) => (int) $m['id'], $postponedMatches),
        array_map(static fn ($m) => (int) $m['id'], $needPoints),
        array_map(static fn ($m) => (int) $m['id'], $searchResults)
    )));
    $predsByMatch = fetchAdminMatchPredictions($pdo, $predMatchIds);

    return [
        'need_score'       => $needScore,
        'need_score_api'   => $needScoreApi,
        'need_score_old'   => $needScoreOld,
        'need_points'      => $needPoints,
        'voided'           => $voidedMatches,
        'postponed'        => $postponedMatches,
        'stuck_summary'    => $stuckSummary,
        'preds_by_match'   => $predsByMatch,
        'search'           => $searchResults,
    ];
}

/** @return array<string,mixed> */
function adminQueryOps(PDO $pdo): array
{
    $quota = oddsQuotaState();
    $pruneStats = staleMatchDataStats($pdo);
    $stuckSummary = summarizeStuckScoresQueue($pdo);
    $purgeTotal = (int) $pruneStats['score_options']
        + (int) $pruneStats['buteur_options']
        + (int) $pruneStats['empty_markets']
        + (int) $pruneStats['old_matches']
        + (int) ($pruneStats['junk_finished'] ?? 0);

    return [
        'quota'         => $quota,
        'prune'         => $pruneStats,
        'stuck'         => $stuckSummary,
        'purge_total'   => $purgeTotal,
        'buteur_days'   => (int) BUTEUR_OPTIONS_RETENTION_DAYS,
        'catchup_days'  => (int) SCORES_CATCHUP_DAYS,
    ];
}

/**
 * @return list<array{id:int,nom:string,est_generale:bool,msg_count:int}>
 */
function adminQueryCommunities(PDO $pdo): array
{
    $communitiesRaw = $pdo->query(
        'SELECT c.id, c.nom, c.est_generale,
                (SELECT COUNT(*) FROM community_messages m
                 WHERE m.community_id = c.id AND m.supprime = 0) AS msg_count
         FROM communities c
         ORDER BY c.est_generale DESC, c.id ASC'
    )->fetchAll() ?: [];

    $communities = [];
    foreach ($communitiesRaw as $row) {
        $dec = decryptCommunityRow($row, false);
        $communities[] = [
            'id'           => (int) $row['id'],
            'nom'          => (string) ($dec['nom'] ?? ('#' . $row['id'])),
            'est_generale' => !empty($row['est_generale']),
            'msg_count'    => (int) $row['msg_count'],
        ];
    }

    return $communities;
}

/**
 * @return array{community_id:int,messages:list<array<string,mixed>>,communities:list<array<string,mixed>>}
 */
function adminQueryMessages(PDO $pdo, int $communityId = 0, string $q = '', bool $includeDeleted = false, int $limit = 200): array
{
    $communities = adminQueryCommunities($pdo);
    if ($communityId < 1 && $communities !== []) {
        $communityId = (int) $communities[0]['id'];
    }

    $messages = [];
    if ($communityId > 0) {
        $sql = 'SELECT m.id, m.community_id, m.user_id, m.contenu, m.supprime, m.created_at, u.pseudo
                FROM community_messages m
                INNER JOIN users u ON u.id = m.user_id
                WHERE m.community_id = ?';
        $params = [$communityId];
        if (!$includeDeleted) {
            $sql .= ' AND m.supprime = 0';
        }
        $limit = max(1, min(400, $limit));
        $sql .= " ORDER BY m.created_at DESC LIMIT {$limit}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];
        $search = mb_strtolower($q);
        foreach ($rows as $row) {
            $row = decryptMessageRow($row);
            $contenu = (string) ($row['contenu'] ?? '');
            if ($search !== '') {
                $hay = mb_strtolower(($row['pseudo'] ?? '') . ' ' . $contenu);
                if (!str_contains($hay, $search)) {
                    continue;
                }
            }
            $messages[] = $row;
        }
    }

    return [
        'community_id' => $communityId,
        'messages'     => $messages,
        'communities'  => $communities,
    ];
}

/** @return array{active:?array,history:list<array<string,mixed>>,month_target:string} */
function adminQuerySeasons(PDO $pdo): array
{
    $active = getActiveSeason($pdo);
    $history = $pdo->query(
        'SELECT id, debut, fin, cloturee FROM seasons ORDER BY id DESC LIMIT 12'
    )->fetchAll() ?: [];

    return [
        'active'       => $active,
        'history'      => $history,
        'month_target' => nextMonthStartDatetime(),
    ];
}

/** @return array{events:list<array<string,mixed>>,active:list<array<string,mixed>>,editing:?array,form:array<string,mixed>} */
function adminQueryEvents(PDO $pdo, int $editId = 0): array
{
    ensureSiteEventsSchema($pdo);
    $editing = $editId > 0 ? fetchSiteEvent($pdo, $editId) : null;
    $cfg = $editing ? decodeSiteEventConfig($editing['config_json'] ?? null) : [];
    $form = [
        'id'         => $editing ? (int) $editing['id'] : 0,
        'title'      => $editing['title'] ?? '',
        'message'    => $editing['message'] ?? '',
        'type'       => $editing['type'] ?? 'points_multiplier',
        'theme'      => $editing['theme'] ?? 'double',
        'starts_at'  => $editing ? matchDatetimeLocalValue((string) $editing['starts_at']) : matchDatetimeLocalValue(gmdate('Y-m-d H:i:s')),
        'ends_at'    => $editing ? matchDatetimeLocalValue((string) $editing['ends_at']) : matchDatetimeLocalValue(gmdate('Y-m-d H:i:s', time() + 2 * 86400)),
        'enabled'    => $editing ? !empty($editing['enabled']) : true,
        'published'  => $editing ? !empty($editing['published']) : false,
        'multiplier' => isset($cfg['multiplier']) ? (string) $cfg['multiplier'] : '2',
        'sport'      => (string) ($cfg['sport'] ?? 'soccer'),
    ];

    return [
        'events'  => listSiteEvents($pdo, 50),
        'active'  => getActiveSiteEvents($pdo),
        'editing' => $editing,
        'form'    => $form,
        'types'   => siteEventTypeCatalog(),
        'themes'  => siteEventThemeOptions(),
    ];
}

/** @return array{all:list<array<string,mixed>>,form:array<string,mixed>} */
function adminQueryAnnouncements(PDO $pdo, int $editId = 0): array
{
    ensureSiteAnnouncementsSchema($pdo);
    $editing = $editId > 0 ? fetchSiteAnnouncement($pdo, $editId) : null;

    return [
        'all'  => listSiteAnnouncements($pdo, false),
        'form' => [
            'id'        => $editing ? (int) $editing['id'] : 0,
            'title'     => $editing ? (string) $editing['title'] : '',
            'body'      => $editing ? (string) $editing['body'] : '',
            'published' => $editing ? !empty($editing['published']) : true,
        ],
    ];
}

function adminFmtWhen(?string $dt): string
{
    $dt = trim((string) $dt);
    if ($dt === '') {
        return 'jamais';
    }
    try {
        return formatMatchWhen($dt);
    } catch (Throwable $e) {
        return $dt;
    }
}

function adminFmtShortWhen(?string $dt): string
{
    $dt = trim((string) $dt);
    if ($dt === '') {
        return '*NONE';
    }
    try {
        $ts = strtotime($dt . ' UTC');
        if ($ts === false) {
            $ts = strtotime($dt);
        }
        if ($ts === false) {
            return '*NONE';
        }

        return date('d/m H:i', $ts);
    } catch (Throwable $e) {
        return '*NONE';
    }
}

function adminTruncate(string $s, int $len): string
{
    $s = preg_replace('/\s+/', ' ', trim($s)) ?? '';
    if (mb_strlen($s) <= $len) {
        return $s;
    }

    return mb_substr($s, 0, max(0, $len - 1)) . '…';
}
