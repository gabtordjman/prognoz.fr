<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

function homeHighlightCacheTtl(): int
{
    return 180;
}

function homeHighlightCachePath(): string
{
    return APP_CACHE_DIR . '/home_highlight.json';
}

function homeHighlightLimit(): int
{
    return 3;
}

/**
 * @param array<string,mixed> $row
 * @return array{id:int,pseudo:string,pts_24h:int,serie:int,avatar_url:?string,equipped_name:string}
 */
function normalizeHomeHighlightRow(array $row): array
{
    return [
        'id'         => (int) ($row['id'] ?? 0),
        'pseudo'     => function_exists('userDisplayName') ? userDisplayName($row) : (string) ($row['pseudo'] ?? ''),
        'pts_24h'    => (int) ($row['pts_24h'] ?? 0),
        'serie'      => (int) ($row['serie'] ?? 0),
        'avatar_url' => isset($row['avatar_url']) && $row['avatar_url'] !== '' && $row['avatar_url'] !== null
            ? (string) $row['avatar_url']
            : null,
        'equipped_name' => (string) ($row['equipped_name'] ?? ''),
    ];
}

/**
 * Top perfs récentes (0–N).
 *
 * @return list<array{id:int,pseudo:string,pts_24h:int,serie:int,avatar_url:?string,equipped_name:string}>
 */
function fetchHomeHighlights(PDO $pdo, ?int $limit = null): array
{
    $limit = $limit ?? homeHighlightLimit();
    $limit = max(1, min(5, $limit));
    $ptsMin = defined('HIGHLIGHT_POINTS_24H') ? (int) HIGHLIGHT_POINTS_24H : 50;
    $serieMin = defined('HIGHLIGHT_STREAK_MIN') ? (int) HIGHLIGHT_STREAK_MIN : 5;
    $ttl = homeHighlightCacheTtl();

    if (function_exists('ensureAppCacheDir') && ensureAppCacheDir()) {
        $path = homeHighlightCachePath();
        if (is_file($path) && (time() - (int) filemtime($path)) < $ttl) {
            $raw = @file_get_contents($path);
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if ($decoded === null && ($raw === 'null' || $raw === '[]')) {
                    return [];
                }
                if (is_array($decoded)) {
                    // Ancien cache : un seul objet
                    if (isset($decoded['pseudo'])) {
                        return [normalizeHomeHighlightRow($decoded)];
                    }
                    $out = [];
                    foreach ($decoded as $row) {
                        if (is_array($row) && isset($row['pseudo'])) {
                            $out[] = normalizeHomeHighlightRow($row);
                        }
                    }

                    return $out;
                }
            }
        }
    }

    $hits = [];
    try {
        $hasAvatar = false;
        try {
            $hasAvatar = (bool) $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar_url'")->fetch();
        } catch (PDOException $e) {
            $hasAvatar = false;
        }
        $avatarCol = $hasAvatar ? 'u.avatar_url' : 'NULL AS avatar_url';
        $hasNameStyle = false;
        try {
            $hasNameStyle = (bool) $pdo->query("SHOW COLUMNS FROM users LIKE 'equipped_name'")->fetch();
        } catch (PDOException $e) {
            $hasNameStyle = false;
        }
        $nameCol = $hasNameStyle ? 'u.equipped_name' : "'' AS equipped_name";
        $hasSurnom = false;
        try {
            $hasSurnom = (bool) $pdo->query("SHOW COLUMNS FROM users LIKE 'surnom'")->fetch();
        } catch (PDOException $e) {
            $hasSurnom = false;
        }
        $surnomCol = $hasSurnom ? 'u.surnom' : "NULL AS surnom";

        $sql = "SELECT u.id, u.pseudo, {$surnomCol}, u.serie_en_cours AS serie, {$avatarCol},
                       {$nameCol},
                       COALESCE(s.pts_24h, 0) AS pts_24h
                FROM users u
                LEFT JOIN (
                    SELECT user_id, SUM(points_gagnes) AS pts_24h
                    FROM predictions
                    WHERE statut = 'correct'
                      AND points_gagnes > 0
                      AND resolved_at IS NOT NULL
                      AND resolved_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)
                    GROUP BY user_id
                ) s ON s.user_id = u.id
                WHERE u.actif = 1
                  AND (
                      COALESCE(s.pts_24h, 0) >= {$ptsMin}
                      OR COALESCE(u.serie_en_cours, 0) >= {$serieMin}
                  )
                ORDER BY COALESCE(s.pts_24h, 0) DESC, COALESCE(u.serie_en_cours, 0) DESC, u.id ASC
                LIMIT {$limit}";
        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $hits[] = normalizeHomeHighlightRow($row);
        }
    } catch (PDOException $e) {
        $hits = [];
    }

    if (function_exists('ensureAppCacheDir') && ensureAppCacheDir()) {
        @file_put_contents(
            homeHighlightCachePath(),
            json_encode($hits, JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    return $hits;
}

/** @return array{id:int,pseudo:string,pts_24h:int,serie:int,avatar_url:?string}|null */
function fetchHomeHighlight(PDO $pdo): ?array
{
    $list = fetchHomeHighlights($pdo, 1);

    return $list[0] ?? null;
}

/**
 * @param array{id:int,pseudo:string,pts_24h:int,serie:int,avatar_url:?string} $h
 */
function formatHomeHighlightStats(array $h): string
{
    $pts = (int) ($h['pts_24h'] ?? 0);
    $serie = (int) ($h['serie'] ?? 0);
    $serieMin = defined('HIGHLIGHT_STREAK_MIN') ? (int) HIGHLIGHT_STREAK_MIN : 5;
    $bits = [];
    if ($pts > 0) {
        $bits[] = t('home.highlight_pts', ['n' => $pts]);
    }
    if ($serie >= $serieMin) {
        $bits[] = t('home.highlight_streak', ['n' => $serie]);
    }

    return implode(t('home.highlight_dot'), $bits);
}

/**
 * @param list<array{id:int,pseudo:string,pts_24h:int,serie:int,avatar_url:?string}>|null $list
 */
function renderHomeHighlightBanner($list = null): void
{
    if ($list === null) {
        return;
    }
    // Compat appel avec un seul hit
    if (isset($list['pseudo'])) {
        $list = [$list];
    }
    if (!is_array($list) || $list === []) {
        return;
    }

    $items = [];
    foreach ($list as $h) {
        if (!is_array($h) || trim((string) ($h['pseudo'] ?? '')) === '') {
            continue;
        }
        $stats = formatHomeHighlightStats($h);
        if ($stats === '') {
            continue;
        }
        $items[] = [
            'pseudo' => (string) $h['pseudo'],
            'name_style' => (string) ($h['equipped_name'] ?? ''),
            'stats'  => $stats,
            'line'   => t('home.highlight_msg', [
                'pseudo' => (string) $h['pseudo'],
                'stats'  => $stats,
            ]),
        ];
    }
    if ($items === []) {
        return;
    }

    // Deux passes identiques (items + séparateur) pour boucle fluide avec repère visuel.
    $halfHtml = static function (array $items): void {
        foreach ($items as $it) {
            ?>
            <span class="perf-highlight-item">
                <strong class="perf-highlight-pseudo"><?php
                    if (function_exists('renderCosmeticPseudo')) {
                        renderCosmeticPseudo((string) $it['pseudo'], (string) ($it['name_style'] ?? ''));
                    } else {
                        echo e((string) $it['pseudo']);
                    }
                ?></strong>
                <span class="perf-highlight-stats"><?= e($it['stats']) ?></span>
            </span>
            <?php
        }
        ?>
        <span class="perf-highlight-gap" aria-hidden="true">
            <span class="perf-highlight-gap-line"></span>
            <span class="perf-highlight-gap-mark">◆</span>
            <span class="perf-highlight-gap-line"></span>
        </span>
        <?php
    };
    $duration = max(20, count($items) * 12);
    ?>
    <aside class="perf-highlight" role="status" aria-label="<?= e(t('home.highlight_title')) ?>">
        <div class="perf-highlight-rail">
            <span class="perf-highlight-badge">
                <i class="fa-solid fa-star" aria-hidden="true"></i>
                <?= e(t('home.highlight_title')) ?>
            </span>
            <div class="perf-highlight-viewport">
                <div class="perf-highlight-track" style="--perf-duration: <?= (int) $duration ?>s">
                    <?php $halfHtml($items); $halfHtml($items); ?>
                </div>
            </div>
        </div>
    </aside>
    <?php
}
