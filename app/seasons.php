<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/**
 * Horloge « métier » des saisons = fuseau app (Europe/Paris), pas MySQL NOW().
 * Sur beaucoup de VPS, MySQL est en UTC : comparer fin à NOW() retardait la
 * clôture de 2 h (minuit Paris = 22:00 UTC la veille).
 */
function seasonClockNow(): string
{
    return (new DateTimeImmutable('now', appTimezone()))->format('Y-m-d H:i:s');
}

/** @return array{id:int,debut:string,fin:string,cloturee:int}|null */
function getActiveSeason(PDO $pdo): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, debut, fin, cloturee FROM seasons
         WHERE cloturee = 0 AND fin > ?
         ORDER BY debut DESC LIMIT 1'
    );
    $stmt->execute([seasonClockNow()]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/** Clôture les saisons expirées puis garantit une saison active. */
function maintainSeasons(PDO $pdo): ?array
{
    ensureSeasonSchema($pdo);

    $now  = seasonClockNow();
    $stmt = $pdo->prepare(
        'SELECT id FROM seasons WHERE cloturee = 0 AND fin <= ? ORDER BY fin ASC'
    );
    $stmt->execute([$now]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $seasonId) {
        closeSeason($pdo, (int) $seasonId);
    }

    $active = getActiveSeason($pdo);
    if ($active) {
        return $active;
    }

    $debut = $now;
    $fin   = (new DateTimeImmutable($now, appTimezone()))
        ->modify('+' . SAISON_DUREE_JOURS . ' days')
        ->format('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO seasons (debut, fin, cloturee) VALUES (?, ?, 0)')->execute([$debut, $fin]);

    return getActiveSeason($pdo);
}

function ensureSeasonSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo->query('SELECT 1 FROM seasons LIMIT 1');
    } catch (Throwable $e) {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS seasons (
                id INT AUTO_INCREMENT PRIMARY KEY,
                debut DATETIME NOT NULL,
                fin DATETIME NOT NULL,
                cloturee TINYINT(1) NOT NULL DEFAULT 0,
                shop_locked TINYINT(1) NOT NULL DEFAULT 0
            ) ENGINE=InnoDB'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS season_scores (
                id INT AUTO_INCREMENT PRIMARY KEY,
                season_id INT NOT NULL,
                community_id INT NOT NULL,
                user_id INT NOT NULL,
                points INT NOT NULL DEFAULT 0,
                UNIQUE KEY uq_score (season_id, community_id, user_id),
                KEY idx_season_community (season_id, community_id, points)
            ) ENGINE=InnoDB'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS season_rewards (
                id INT AUTO_INCREMENT PRIMARY KEY,
                season_id INT NOT NULL,
                community_id INT NOT NULL,
                user_id INT NOT NULL,
                classement INT NOT NULL,
                recompense VARCHAR(100) NOT NULL,
                KEY idx_user_season (user_id, season_id)
            ) ENGINE=InnoDB'
        );
    }
}

/** Retourne l'id de la saison active, en en créant une si besoin. */
function ensureActiveSeason(PDO $pdo): int
{
    $season = maintainSeasons($pdo);
    if ($season) {
        return (int) $season['id'];
    }

    $debut = date('Y-m-d H:i:s');
    $fin   = date('Y-m-d H:i:s', strtotime('+' . SAISON_DUREE_JOURS . ' days'));
    $pdo->prepare('INSERT INTO seasons (debut, fin, cloturee) VALUES (?, ?, 0)')->execute([$debut, $fin]);

    return (int) $pdo->lastInsertId();
}

function getGeneralCommunityId(PDO $pdo): ?int
{
    static $id = null;
    if ($id !== null) {
        return $id ?: null;
    }
    $row = $pdo->query('SELECT id FROM communities WHERE est_generale = 1 LIMIT 1')->fetch();
    $id = $row ? (int) $row['id'] : 0;

    return $id ?: null;
}

function getUserSeasonPoints(PDO $pdo, int $userId, int $communityId, ?int $seasonId = null): int
{
    $seasonId = $seasonId ?? (getActiveSeason($pdo)['id'] ?? ensureActiveSeason($pdo));
    $stmt = $pdo->prepare(
        'SELECT points FROM season_scores WHERE season_id = ? AND community_id = ? AND user_id = ?'
    );
    $stmt->execute([$seasonId, $communityId, $userId]);

    return (int) ($stmt->fetchColumn() ?: 0);
}

function getUserGeneralSeasonPoints(PDO $pdo, int $userId, ?int $seasonId = null): int
{
    $generalId = getGeneralCommunityId($pdo);
    if (!$generalId) {
        return 0;
    }

    return getUserSeasonPoints($pdo, $userId, $generalId, $seasonId);
}

/** @return list<array{id:int,pseudo:string,serie_en_cours:int,points:int}> */
function fetchCommunitySeasonLeaderboard(PDO $pdo, int $communityId, ?int $seasonId = null): array
{
    $seasonId = $seasonId ?? (int) (getActiveSeason($pdo)['id'] ?? ensureActiveSeason($pdo));
    $stmt = $pdo->prepare(
        'SELECT u.id, u.pseudo, u.surnom, u.serie_en_cours, u.avatar_url, u.equipped_name, COALESCE(ss.points, 0) AS points
         FROM community_members cm
         INNER JOIN users u ON u.id = cm.user_id
         LEFT JOIN season_scores ss
           ON ss.user_id = u.id AND ss.community_id = cm.community_id AND ss.season_id = ?
         WHERE cm.community_id = ?
         ORDER BY points DESC, u.pseudo ASC'
    );
    $stmt->execute([$seasonId, $communityId]);

    return $stmt->fetchAll();
}

/**
 * Planifie la fin de la saison active (sans la clôturer tout de suite).
 * maintainSeasons() s'occupera du podium + ouverture de la suivante à l'échéance.
 */
function scheduleActiveSeasonEnd(PDO $pdo, string $finDatetime): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $finDatetime)) {
        throw new InvalidArgumentException('Date de fin invalide.');
    }

    $fin = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $finDatetime, appTimezone());
    if (!$fin) {
        throw new InvalidArgumentException('Date de fin invalide.');
    }

    $now = new DateTimeImmutable('now', appTimezone());
    if ($fin <= $now) {
        throw new InvalidArgumentException('La fin doit être dans le futur (sinon clôturez maintenant).');
    }

    ensureSeasonSchema($pdo);
    $active = $pdo->query(
        'SELECT id, debut, fin, cloturee FROM seasons WHERE cloturee = 0 ORDER BY debut DESC LIMIT 1'
    )->fetch();

    if (!$active) {
        $debut = $now->format('Y-m-d H:i:s');
        $pdo->prepare('INSERT INTO seasons (debut, fin, cloturee) VALUES (?, ?, 0)')
            ->execute([$debut, $finDatetime]);
        $active = getActiveSeason($pdo);
    } else {
        $pdo->prepare('UPDATE seasons SET fin = ? WHERE id = ? AND cloturee = 0')
            ->execute([$finDatetime, (int) $active['id']]);
        $active = getActiveSeason($pdo);
    }

    if (!$active) {
        throw new RuntimeException('Saison introuvable après mise à jour.');
    }

    $nextEnd = $fin->modify('+' . SAISON_DUREE_JOURS . ' days');

    return [
        'season'   => $active,
        'next_end' => $nextEnd->format('Y-m-d H:i:s'),
    ];
}

/** Prochain 1er du mois à 00:00 (fuseau app) — aligné sur le reset crédits Odds API. */
function nextMonthStartDatetime(): string
{
    $now  = new DateTimeImmutable('now', appTimezone());
    $next = $now->modify('first day of next month')->setTime(0, 0, 0);

    return $next->format('Y-m-d H:i:s');
}

/**
 * Clôture immédiate de la saison ouverte (podium + push) puis ouvre la suivante.
 * @return array{id:int,debut:string,fin:string,cloturee:int}|null saison active après coup
 */
function forceCloseActiveSeason(PDO $pdo): ?array
{
    ensureSeasonSchema($pdo);
    $now = seasonClockNow();
    $pdo->prepare('UPDATE seasons SET fin = ? WHERE cloturee = 0 AND fin > ?')
        ->execute([$now, $now]);

    return maintainSeasons($pdo);
}

/** @return array{days:int,hours:int,fin_label:string,urgent:bool} */
function seasonCountdownMeta(array $season): array
{
    $finTs = strtotime($season['fin'] ?? '');
    $now   = time();
    if ($finTs === false) {
        return ['days' => 0, 'hours' => 0, 'fin_label' => '', 'urgent' => true];
    }
    $secondsLeft = max(0, $finTs - $now);
    $days        = (int) floor($secondsLeft / 86400);
    $hours       = (int) floor(($secondsLeft % 86400) / 3600);

    return [
        'days'      => $days,
        'hours'     => $hours,
        'fin_label' => formatSeasonFin($season['fin'] ?? ''),
        'urgent'    => $days <= 2,
    ];
}

function formatSeasonFin(string $datetime): string
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datetime, appTimezone());
    if (!$dt) {
        return $datetime;
    }

    return $dt->format('d/m/Y à H:i');
}

function seasonCountdownLabel(array $season): string
{
    $meta = seasonCountdownMeta($season);
    if ($meta['days'] > 1) {
        return t('season.days_other', ['n' => $meta['days']]);
    }
    if ($meta['days'] === 1) {
        return t('season.days_one');
    }
    if ($meta['hours'] > 1) {
        return t('season.hours_other', ['n' => $meta['hours']]);
    }
    if ($meta['hours'] === 1) {
        return t('season.hours_one');
    }

    return t('season.soon');
}

function closeSeason(PDO $pdo, int $seasonId): bool
{
    $stmt = $pdo->prepare('SELECT id, cloturee FROM seasons WHERE id = ?');
    $stmt->execute([$seasonId]);
    $season = $stmt->fetch();
    if (!$season || !empty($season['cloturee'])) {
        return false;
    }

    awardSeasonPodium($pdo, $seasonId);
    if (function_exists('lockSeasonShopPoints')) {
        lockSeasonShopPoints($pdo, $seasonId);
    }
    notifySeasonClosedPush($pdo, $seasonId);

    $pdo->prepare('UPDATE seasons SET cloturee = 1 WHERE id = ?')->execute([$seasonId]);

    return true;
}

function awardSeasonPodium(PDO $pdo, int $seasonId): void
{
    $communities = $pdo->query('SELECT id FROM communities')->fetchAll(PDO::FETCH_COLUMN);
    $bonusByRank = SEASON_PODIUM_BONUS;
    $labels      = SEASON_REWARD_LABELS;

    $rankStmt = $pdo->prepare(
        'SELECT user_id, points FROM season_scores
         WHERE season_id = ? AND community_id = ?
         ORDER BY points DESC, user_id ASC
         LIMIT 3'
    );
    $bonusStmt = $pdo->prepare(
        'UPDATE season_scores SET points = points + ? WHERE season_id = ? AND community_id = ? AND user_id = ?'
    );
    $rewardCheck = $pdo->prepare(
        'SELECT id FROM season_rewards WHERE season_id = ? AND community_id = ? AND user_id = ? AND classement = ?'
    );
    $rewardInsert = $pdo->prepare(
        'INSERT INTO season_rewards (season_id, community_id, user_id, classement, recompense) VALUES (?, ?, ?, ?, ?)'
    );

    foreach ($communities as $communityId) {
        $communityId = (int) $communityId;
        $rankStmt->execute([$seasonId, $communityId]);
        $rows = $rankStmt->fetchAll();
        $rank = 0;
        foreach ($rows as $row) {
            $rank++;
            if ($rank > 3 || (int) $row['points'] <= 0) {
                break;
            }
            $userId = (int) $row['user_id'];
            $bonus  = (int) ($bonusByRank[$rank] ?? 0);
            if ($bonus > 0) {
                $bonusStmt->execute([$bonus, $seasonId, $communityId, $userId]);
            }
            $label = $labels[$rank] ?? ('Top ' . $rank);
            $rewardCheck->execute([$seasonId, $communityId, $userId, $rank]);
            if (!$rewardCheck->fetch()) {
                $rewardInsert->execute([$seasonId, $communityId, $userId, $rank, $label]);
            }
        }
    }
}

/**
 * Badges saison visibles pour un spectateur.
 * Générale : tout le monde. Privées : propriétaire du profil ou membre de la communauté
 * (pas d’exception admin).
 *
 * @return list<array{
 *   classement:int,recompense:string,season_id:int,community_id:int,
 *   community_name:string,est_generale:int,fin:string,streak?:int
 * }>
 */
function fetchUserSeasonRewards(PDO $pdo, int $userId, int $limit = 5, ?int $viewerId = null): array
{
    ensureSeasonSchema($pdo);
    $viewerId = $viewerId ?? $userId;
    $limit = max(1, min(50, $limit));

    $stmt = $pdo->prepare(
        'SELECT sr.classement, sr.recompense, sr.season_id, sr.community_id,
                c.nom AS community_name, c.est_generale, s.fin
         FROM season_rewards sr
         INNER JOIN communities c ON c.id = sr.community_id
         INNER JOIN seasons s ON s.id = sr.season_id
         WHERE sr.user_id = ?
         ORDER BY s.fin DESC, sr.classement ASC'
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    $viewerCommunities = [];
    if ($viewerId !== $userId) {
        $st = $pdo->prepare('SELECT community_id FROM community_members WHERE user_id = ?');
        $st->execute([$viewerId]);
        $viewerCommunities = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        $viewerCommunities = array_flip($viewerCommunities);
    }

    $visible = [];
    foreach ($rows as $row) {
        $isGeneral = (int) ($row['est_generale'] ?? 0) === 1;
        $cid = (int) ($row['community_id'] ?? 0);
        $canSee = $isGeneral
            || $viewerId === $userId
            || isset($viewerCommunities[$cid]);
        if (!$canSee) {
            continue;
        }
        $row['community_name'] = decryptSensitive($row['community_name'] ?? '');
        $row['classement'] = (int) ($row['classement'] ?? 0);
        $row['season_id'] = (int) ($row['season_id'] ?? 0);
        $row['community_id'] = $cid;
        $row['est_generale'] = $isGeneral ? 1 : 0;
        $visible[] = $row;
    }

    return array_slice(compactSeasonPodiumRewards($pdo, $visible), 0, $limit);
}

/**
 * Podium Or / Argent / Bronze : une pastille par (communauté, rang), avec ×N
 * d’affilée si la série tient depuis la dernière saison clôturée.
 * Si le rang n’est plus tenu à la saison qui vient de finir, la série disparaît.
 *
 * @param list<array> $rewards
 * @return list<array>
 */
function compactSeasonPodiumRewards(PDO $pdo, array $rewards): array
{
    $closedIds = [];
    foreach (fetchClosedSeasons($pdo, 40) as $season) {
        $closedIds[] = (int) $season['id'];
    }
    if ($closedIds === []) {
        return $rewards;
    }

    /** @var array<string, list<array>> $byKey */
    $byKey = [];
    $others = [];
    foreach ($rewards as $reward) {
        $rank = (int) ($reward['classement'] ?? 0);
        if ($rank >= 1 && $rank <= 3) {
            $cid = (int) ($reward['community_id'] ?? 0);
            $byKey[$cid . ':' . $rank][] = $reward;
            continue;
        }
        $others[] = $reward;
    }

    $compacted = [];
    foreach ($byKey as $group) {
        $bySeason = [];
        foreach ($group as $item) {
            $bySeason[(int) $item['season_id']] = $item;
        }
        $streak = 0;
        $latest = null;
        foreach ($closedIds as $seasonId) {
            if (!isset($bySeason[$seasonId])) {
                break;
            }
            $streak++;
            if ($latest === null) {
                $latest = $bySeason[$seasonId];
            }
        }
        if ($streak < 1 || $latest === null) {
            continue;
        }
        $latest['streak'] = $streak;
        $compacted[] = $latest;
    }

    $merged = array_merge($compacted, $others);
    usort($merged, static function (array $a, array $b): int {
        $finCmp = strcmp((string) ($b['fin'] ?? ''), (string) ($a['fin'] ?? ''));
        if ($finCmp !== 0) {
            return $finCmp;
        }

        return ((int) ($a['classement'] ?? 99)) <=> ((int) ($b['classement'] ?? 99));
    });

    return $merged;
}

/** @deprecated Alias — utiliser compactSeasonPodiumRewards. */
function compactSeasonGoldRewards(PDO $pdo, array $rewards): array
{
    return compactSeasonPodiumRewards($pdo, $rewards);
}

/** @return list<array{id:int,debut:string,fin:string,cloturee:int}> */
function fetchClosedSeasons(PDO $pdo, int $limit = 12): array
{
    ensureSeasonSchema($pdo);
    $stmt = $pdo->prepare(
        'SELECT id, debut, fin, cloturee FROM seasons WHERE cloturee = 1 ORDER BY fin DESC LIMIT ?'
    );
    $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/** @return array{id:int,debut:string,fin:string,cloturee:int}|null */
function getSeasonById(PDO $pdo, int $seasonId): ?array
{
    ensureSeasonSchema($pdo);
    $stmt = $pdo->prepare('SELECT id, debut, fin, cloturee FROM seasons WHERE id = ?');
    $stmt->execute([$seasonId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/** Ajoute des points au classement saisonnier de l'utilisateur dans chaque communauté. */
function addSeasonPoints(PDO $pdo, int $userId, int $points): void
{
    if ($points === 0) {
        return;
    }

    $seasonId = ensureActiveSeason($pdo);

    $stmt = $pdo->prepare('SELECT community_id FROM community_members WHERE user_id = ?');
    $stmt->execute([$userId]);
    $communities = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($communities)) {
        return;
    }

    $upsertDelta = $pdo->prepare(
        'INSERT INTO season_scores (season_id, community_id, user_id, points)
         VALUES (?, ?, ?, GREATEST(0, ?))
         ON DUPLICATE KEY UPDATE points = GREATEST(0, points + ?)'
    );

    foreach ($communities as $communityId) {
        $upsertDelta->execute([$seasonId, (int) $communityId, $userId, $points, $points]);
    }
}

/**
 * Attribution / retrait manuel de points (admin).
 * Met à jour points_totaux et, sauf opt-out, les scores de saison en cours.
 *
 * @return array{user_id:int,pseudo:string,delta:int,points_totaux:int,season:bool}
 */
function grantUserPoints(PDO $pdo, int $userId, int $delta, bool $toSeason = true): array
{
    if ($delta === 0) {
        throw new InvalidArgumentException('Indique un nombre de points non nul.');
    }
    if (abs($delta) > 10000) {
        throw new InvalidArgumentException('Maximum ±10 000 points par opération.');
    }

    $stmt = $pdo->prepare('SELECT id, pseudo, points_totaux FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new InvalidArgumentException('Joueur introuvable.');
    }

    $pdo->prepare(
        'UPDATE users SET points_totaux = GREATEST(0, points_totaux + ?) WHERE id = ?'
    )->execute([$delta, $userId]);

    if ($toSeason) {
        addSeasonPoints($pdo, $userId, $delta);
    }

    $stmt->execute([$userId]);
    $after = $stmt->fetch();

    return [
        'user_id'       => $userId,
        'pseudo'        => (string) ($user['pseudo'] ?? ''),
        'delta'         => $delta,
        'points_totaux' => (int) ($after['points_totaux'] ?? 0),
        'season'        => $toSeason,
    ];
}

function renderSeasonBanner(?array $season, string $context = 'default'): void
{
    if (!$season) {
        return;
    }
    $meta  = seasonCountdownMeta($season);
    $label = seasonCountdownLabel($season);
    ?>
    <div class="season-banner<?= $meta['urgent'] ? ' season-banner--urgent' : '' ?> season-banner--<?= e($context) ?>" role="status">
        <span class="season-banner-icon" aria-hidden="true"><i class="fa-solid fa-calendar-days"></i></span>
        <div class="season-banner-body">
            <strong><?= e(t('season.current')) ?></strong>
            <span class="season-banner-meta"><?= e(t('season.meta', ['label' => $label, 'date' => $meta['fin_label']])) ?></span>
        </div>
    </div>
    <?php
}
