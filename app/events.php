<?php
/**
 * Événements site (multiplicateurs, thèmes, bannière).
 */
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

function ensureSiteEventsSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS site_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(120) NOT NULL,
                message VARCHAR(280) NOT NULL,
                type VARCHAR(32) NOT NULL,
                config_json TEXT NULL,
                theme VARCHAR(32) NOT NULL DEFAULT 'default',
                starts_at DATETIME NOT NULL,
                ends_at DATETIME NOT NULL,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                published TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                KEY idx_events_window (enabled, published, starts_at, ends_at),
                KEY idx_events_type (type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (PDOException $e) {
        // ignore
    }

    try {
        $col = $pdo->query('SHOW COLUMNS FROM site_events LIKE "published"')->fetch();
        if (!$col) {
            $pdo->exec(
                'ALTER TABLE site_events
                 ADD COLUMN published TINYINT(1) NOT NULL DEFAULT 0 AFTER enabled'
            );
            // Événements déjà créés avant cette colonne : on les considère publiés.
            $pdo->exec('UPDATE site_events SET published = 1');
        }
    } catch (PDOException $e) {
        // ignore
    }
}

function ensureUserProfileExtrasSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $col = $pdo->query('SHOW COLUMNS FROM users LIKE "bio"')->fetch();
        if (!$col) {
            $pdo->exec(
                'ALTER TABLE users ADD COLUMN bio VARCHAR(200) NULL DEFAULT NULL AFTER avatar_url'
            );
        }
    } catch (PDOException $e) {
        // ignore
    }
    try {
        $col = $pdo->query('SHOW COLUMNS FROM users LIKE "sport_favori"')->fetch();
        if (!$col) {
            $pdo->exec(
                "ALTER TABLE users ADD COLUMN sport_favori VARCHAR(20) NULL DEFAULT NULL AFTER bio"
            );
        }
    } catch (PDOException $e) {
        // ignore
    }
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
}

/**
 * Types d’événements proposés en admin.
 *
 * @return array<string,array{label:string,hint:string,icon:string}>
 */
function siteEventTypeCatalog(): array
{
    return [
        'points_multiplier' => [
            'label' => 'Points multipliés',
            'hint'  => 'Tous les bons pronos rapportent ×1.5 / ×2 / ×3 pendant la période.',
            'icon'  => 'fa-bolt',
        ],
        'featured_sport' => [
            'label' => 'Sport en vedette',
            'hint'  => 'Uniquement foot, basket ou tennis : multiplicateur sur ce sport.',
            'icon'  => 'fa-star',
        ],
        'streak_shield' => [
            'label' => 'Série protégée',
            'hint'  => 'Un prono 1/N/2 raté ne remet pas la série à zéro.',
            'icon'  => 'fa-shield-halved',
        ],
        'happy_hour' => [
            'label' => 'Happy hour (multiplicateur)',
            'hint'  => 'Comme points multipliés — idéal pour une soirée courte.',
            'icon'  => 'fa-champagne-glasses',
        ],
    ];
}

/**
 * Thèmes visuels (classe CSS event-theme-*).
 *
 * @return array<string,string>
 */
function siteEventThemeOptions(): array
{
    return [
        'default' => 'Défaut (léger)',
        'double'  => 'Or / points doublés',
        'night'   => 'Nuit feutrée',
        'fest'    => 'Festif laiton',
    ];
}

/**
 * @return array<string,mixed>
 */
function decodeSiteEventConfig(?string $json): array
{
    if ($json === null || trim($json) === '') {
        return [];
    }
    $data = json_decode($json, true);

    return is_array($data) ? $data : [];
}

/**
 * @param array<string,mixed> $config
 */
function encodeSiteEventConfig(array $config): string
{
    return (string) json_encode($config, JSON_UNESCAPED_UNICODE);
}

/**
 * @return list<array<string,mixed>>
 */
function listSiteEvents(PDO $pdo, int $limit = 40): array
{
    ensureSiteEventsSchema($pdo);
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->query(
        "SELECT * FROM site_events
         ORDER BY starts_at DESC, id DESC
         LIMIT {$limit}"
    );

    return $stmt->fetchAll() ?: [];
}

/**
 * Événements publics actuellement actifs (publiés + fenêtre + enabled).
 *
 * @return list<array<string,mixed>>
 */
function getActiveSiteEvents(PDO $pdo): array
{
    ensureSiteEventsSchema($pdo);
    $stmt = $pdo->query(
        "SELECT * FROM site_events
         WHERE enabled = 1
           AND published = 1
           AND starts_at <= UTC_TIMESTAMP()
           AND ends_at > UTC_TIMESTAMP()
         ORDER BY starts_at ASC, id ASC"
    );

    return $stmt->fetchAll() ?: [];
}

/**
 * Prochains événements publiés (pas encore commencés).
 *
 * @return list<array<string,mixed>>
 */
function getUpcomingSiteEvents(PDO $pdo, int $limit = 5): array
{
    ensureSiteEventsSchema($pdo);
    $limit = max(1, min(20, $limit));
    $stmt = $pdo->query(
        "SELECT * FROM site_events
         WHERE enabled = 1
           AND published = 1
           AND starts_at > UTC_TIMESTAMP()
         ORDER BY starts_at ASC, id ASC
         LIMIT {$limit}"
    );

    return $stmt->fetchAll() ?: [];
}

/** Prochain événement planifié (publié), ou null. */
function getNextUpcomingSiteEvent(PDO $pdo): ?array
{
    $list = getUpcomingSiteEvents($pdo, 1);

    return $list[0] ?? null;
}

/** Admin connecté (panel) ou compte site admin → peut prévisualiser un brouillon. */
function canPreviewSiteEvents(): bool
{
    if (!empty($_SESSION['admin_authenticated'])) {
        return true;
    }
    try {
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        if ($uid > 0 && function_exists('isSiteAdminUser') && isSiteAdminUser($uid)) {
            return true;
        }
    } catch (Throwable $e) {
        // ignore
    }

    return false;
}

/** ID d’événement en prévisualisation (?preview_event=), ou 0. */
function siteEventPreviewId(): int
{
    if (!canPreviewSiteEvents()) {
        return 0;
    }

    return max(0, (int) ($_GET['preview_event'] ?? 0));
}

/**
 * Événement à afficher (bannière / thème) : preview admin, sinon live, sinon prochain planifié.
 */
function getDisplaySiteEvent(PDO $pdo): ?array
{
    $previewId = siteEventPreviewId();
    if ($previewId > 0) {
        $ev = fetchSiteEvent($pdo, $previewId);
        if ($ev) {
            $ev['_preview'] = true;
            $ev['_phase'] = siteEventIsLive($ev) ? 'live' : 'preview';
            return $ev;
        }
    }

    $live = getPrimarySiteEvent($pdo);
    if ($live) {
        $live['_phase'] = 'live';
        return $live;
    }

    $upcoming = getNextUpcomingSiteEvent($pdo);
    if ($upcoming) {
        $upcoming['_phase'] = 'upcoming';
        $upcoming['_upcoming'] = true;
        return $upcoming;
    }

    return null;
}

/** Événement principal public pour bannière / thème. */
function getPrimarySiteEvent(PDO $pdo): ?array
{
    $events = getActiveSiteEvents($pdo);
    if ($events === []) {
        return null;
    }
    usort($events, static function (array $a, array $b): int {
        return strcmp((string) $b['starts_at'], (string) $a['starts_at']);
    });

    return $events[0];
}

function fetchSiteEvent(PDO $pdo, int $id): ?array
{
    ensureSiteEventsSchema($pdo);
    if ($id <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM site_events WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * Multiplicateur à appliquer (1.0 = rien). Prend le max des événements actifs.
 * featured_sport : seulement si le match correspond.
 */
function eventPointsMultiplier(PDO $pdo, ?array $match = null): float
{
    $events = getActiveSiteEvents($pdo);
    $max = 1.0;
    $sportCat = $match ? sportCategory((string) ($match['sport'] ?? '')) : '';

    foreach ($events as $ev) {
        $type = (string) ($ev['type'] ?? '');
        if (!in_array($type, ['points_multiplier', 'happy_hour', 'featured_sport'], true)) {
            continue;
        }
        $cfg = decodeSiteEventConfig($ev['config_json'] ?? null);
        $mult = (float) ($cfg['multiplier'] ?? 2);
        if ($mult < 1.1 || $mult > 5) {
            continue;
        }
        if ($type === 'featured_sport') {
            $want = (string) ($cfg['sport'] ?? '');
            if ($want === '' || $sportCat === '' || $want !== $sportCat) {
                continue;
            }
        }
        $max = max($max, $mult);
    }

    return $max;
}

function eventHasStreakShield(PDO $pdo): bool
{
    foreach (getActiveSiteEvents($pdo) as $ev) {
        if (($ev['type'] ?? '') === 'streak_shield') {
            return true;
        }
    }

    return false;
}

/** Classe CSS thème (sans préfixe). Uniquement si l’événement est live (ou preview). */
function primaryEventThemeSlug(PDO $pdo): string
{
    $ev = getDisplaySiteEvent($pdo);
    if (!$ev || !empty($ev['_upcoming'])) {
        return '';
    }
    $theme = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($ev['theme'] ?? 'default'))) ?: 'default';
    if ($theme === 'default') {
        return 'default';
    }

    return $theme;
}

/**
 * Heure / date locales pour annonces d’événement (ex. 20h · 31/08).
 *
 * @return array{time:string,date:string,when:string}
 */
function formatSiteEventSchedule(string $datetimeUtc): array
{
    $dt = parseUtcDatetime($datetimeUtc);
    if (!$dt) {
        return ['time' => '', 'date' => '', 'when' => $datetimeUtc];
    }
    $local = $dt->setTimezone(appTimezone());
    $lang = function_exists('currentLang') ? currentLang() : 'fr';
    if ($lang === 'en') {
        $time = $local->format('g:i A');
    } else {
        $time = $local->format('G') . 'h' . ($local->format('i') === '00' ? '' : $local->format('i'));
    }
    $date = $local->format('d/m');

    return [
        'time' => $time,
        'date' => $date,
        'when' => formatMatchWhen($datetimeUtc),
    ];
}

function siteEventTypeIcon(string $type): string
{
    $catalog = siteEventTypeCatalog();

    return (string) ($catalog[$type]['icon'] ?? 'fa-bolt');
}

function renderSiteEventBanner(?PDO $pdo = null): void
{
    try {
        $pdo = $pdo ?? getPDO();
    } catch (Throwable $e) {
        return;
    }

    $banners = [];
    $previewId = siteEventPreviewId();

    if ($previewId > 0) {
        $ev = fetchSiteEvent($pdo, $previewId);
        if ($ev) {
            $ev['_preview'] = true;
            $ev['_phase'] = siteEventIsLive($ev) ? 'live' : (strcmp(gmdate('Y-m-d H:i:s'), (string) ($ev['starts_at'] ?? '')) < 0 ? 'upcoming' : 'preview');
            if (($ev['_phase'] ?? '') === 'upcoming') {
                $ev['_upcoming'] = true;
            }
            $banners[] = $ev;
        }
    } else {
        $live = getPrimarySiteEvent($pdo);
        if ($live) {
            $live['_phase'] = 'live';
            $banners[] = $live;
        }
        $upcoming = getNextUpcomingSiteEvent($pdo);
        if ($upcoming) {
            $liveId = $live ? (int) ($live['id'] ?? 0) : 0;
            if ((int) ($upcoming['id'] ?? 0) !== $liveId) {
                $upcoming['_phase'] = 'upcoming';
                $upcoming['_upcoming'] = true;
                $banners[] = $upcoming;
            }
        }
    }

    foreach ($banners as $ev) {
        renderOneSiteEventBanner($ev);
    }
}

/**
 * @param array<string,mixed> $ev
 */
function renderOneSiteEventBanner(array $ev): void
{
    $title = trim((string) ($ev['title'] ?? ''));
    $message = trim((string) ($ev['message'] ?? ''));
    $type = (string) ($ev['type'] ?? 'points_multiplier');
    $phase = (string) ($ev['_phase'] ?? (siteEventIsLive($ev) ? 'live' : 'upcoming'));
    $isPreview = !empty($ev['_preview']);
    $isUpcoming = $phase === 'upcoming' || !empty($ev['_upcoming']);

    if ($isUpcoming) {
        if ($title === '') {
            return;
        }
        $sched = formatSiteEventSchedule((string) ($ev['starts_at'] ?? ''));
        $message = t('event.starts_at', [
            'title' => $title,
            'time'  => $sched['time'],
            'date'  => $sched['date'],
        ]);
    } elseif ($title === '' && $message === '') {
        return;
    }

    $typeClass = 'site-event-banner--' . preg_replace('/[^a-z0-9_]/', '', $type);
    $icon = siteEventTypeIcon($type);
    $meta = '';
    if ($isUpcoming) {
        $meta = t('event.upcoming');
    } elseif ($phase === 'live' || siteEventIsLive($ev)) {
        $meta = t('event.until', ['when' => formatMatchWhen((string) ($ev['ends_at'] ?? ''))]);
    }
    ?>
    <?php if ($isPreview): ?>
    <div class="site-event-preview-bar" role="status">
        Prévisualisation admin — les joueurs ne voient pas encore cet affichage.
        <a href="<?= e(url('admin/events.php?edit=' . (int) ($ev['id'] ?? 0))) ?>">Retour admin</a>
    </div>
    <?php endif; ?>
    <div class="site-event-banner <?= e($typeClass) ?><?= $isUpcoming ? ' is-upcoming' : ' is-live' ?>" role="status">
        <div class="site-event-banner-inner">
            <span class="site-event-banner-icon" aria-hidden="true"><i class="fa-solid <?= e($icon) ?>"></i></span>
            <div class="site-event-banner-body">
                <?php if ($title !== ''): ?>
                    <strong class="site-event-banner-title"><?= e($title) ?></strong>
                <?php endif; ?>
                <?php if ($message !== ''): ?>
                    <span class="site-event-banner-msg"><?= e($message) ?></span>
                <?php endif; ?>
                <?php if ($meta !== ''): ?>
                    <span class="site-event-banner-meta"><?= e($meta) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/** Pluie d’étoiles légère une fois par chargement de page, si un événement live est affiché. */
function renderSiteEventStarRain(?PDO $pdo = null): void
{
    try {
        $pdo = $pdo ?? getPDO();
        $ev = getDisplaySiteEvent($pdo);
        if (!$ev || !empty($ev['_upcoming'])) {
            return;
        }
    } catch (Throwable $e) {
        return;
    }
    ?>
    <div class="event-star-rain" id="eventStarRain" aria-hidden="true"></div>
    <script src="<?= e(assetUrl('assets/js/event-stars.js')) ?>"></script>
    <?php
}

/**
 * @param array<string,mixed> $input
 * @return array{title:string,message:string,type:string,config:array,theme:string,starts_at:string,ends_at:string,enabled:int,published:int}
 */
function normalizeSiteEventInput(array $input): array
{
    $title = trim((string) ($input['title'] ?? ''));
    $message = trim((string) ($input['message'] ?? ''));
    $type = (string) ($input['type'] ?? '');
    $theme = (string) ($input['theme'] ?? 'default');
    $catalog = siteEventTypeCatalog();
    $themes = siteEventThemeOptions();

    if ($title === '' || mb_strlen($title) > 120) {
        throw new InvalidArgumentException('Titre invalide (1–120 caractères).');
    }
    if ($message === '' || mb_strlen($message) > 280) {
        throw new InvalidArgumentException('Message invalide (1–280 caractères).');
    }
    if (!isset($catalog[$type])) {
        throw new InvalidArgumentException('Type d’événement invalide.');
    }
    if (!isset($themes[$theme])) {
        $theme = 'default';
    }

    $startsAt = parseAdminMatchDatetime((string) ($input['starts_at'] ?? ''));
    $endsAt = parseAdminMatchDatetime((string) ($input['ends_at'] ?? ''));
    if (strcmp($endsAt, $startsAt) <= 0) {
        throw new InvalidArgumentException('La fin doit être après le début.');
    }

    $config = [];
    if (in_array($type, ['points_multiplier', 'happy_hour', 'featured_sport'], true)) {
        $allowedMult = ['1.5' => 1.5, '2' => 2.0, '2.0' => 2.0, '3' => 3.0, '3.0' => 3.0];
        $multKey = (string) ($input['multiplier'] ?? '2');
        if (!isset($allowedMult[$multKey])) {
            throw new InvalidArgumentException('Multiplicateur : 1.5, 2 ou 3.');
        }
        $config['multiplier'] = $allowedMult[$multKey];
    }
    if ($type === 'featured_sport') {
        $sport = (string) ($input['sport'] ?? '');
        if (!in_array($sport, ['soccer', 'basketball', 'tennis'], true)) {
            throw new InvalidArgumentException('Choisis un sport en vedette.');
        }
        $config['sport'] = $sport;
    }

    return [
        'title'     => $title,
        'message'   => $message,
        'type'      => $type,
        'config'    => $config,
        'theme'     => $theme,
        'starts_at' => $startsAt,
        'ends_at'   => $endsAt,
        'enabled'   => !empty($input['enabled']) ? 1 : 0,
        'published' => !empty($input['published']) ? 1 : 0,
    ];
}

/**
 * @param array<string,mixed> $input
 */
function createSiteEvent(PDO $pdo, array $input): int
{
    ensureSiteEventsSchema($pdo);
    $data = normalizeSiteEventInput($input);
    $pdo->prepare(
        'INSERT INTO site_events
         (title, message, type, config_json, theme, starts_at, ends_at, enabled, published, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    )->execute([
        $data['title'],
        $data['message'],
        $data['type'],
        encodeSiteEventConfig($data['config']),
        $data['theme'],
        $data['starts_at'],
        $data['ends_at'],
        $data['enabled'],
        $data['published'],
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * @param array<string,mixed> $input
 */
function updateSiteEvent(PDO $pdo, int $id, array $input): void
{
    ensureSiteEventsSchema($pdo);
    if (!fetchSiteEvent($pdo, $id)) {
        throw new InvalidArgumentException('Événement introuvable.');
    }
    $data = normalizeSiteEventInput($input);
    $pdo->prepare(
        'UPDATE site_events
         SET title = ?, message = ?, type = ?, config_json = ?, theme = ?,
             starts_at = ?, ends_at = ?, enabled = ?, published = ?, updated_at = UTC_TIMESTAMP()
         WHERE id = ?'
    )->execute([
        $data['title'],
        $data['message'],
        $data['type'],
        encodeSiteEventConfig($data['config']),
        $data['theme'],
        $data['starts_at'],
        $data['ends_at'],
        $data['enabled'],
        $data['published'],
        $id,
    ]);
}

function setSiteEventPublished(PDO $pdo, int $id, bool $published): void
{
    ensureSiteEventsSchema($pdo);
    $pdo->prepare(
        'UPDATE site_events SET published = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?'
    )->execute([$published ? 1 : 0, $id]);
}

function setSiteEventEnabled(PDO $pdo, int $id, bool $enabled): void
{
    ensureSiteEventsSchema($pdo);
    $pdo->prepare(
        'UPDATE site_events SET enabled = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?'
    )->execute([$enabled ? 1 : 0, $id]);
}

function deleteSiteEvent(PDO $pdo, int $id): void
{
    ensureSiteEventsSchema($pdo);
    $pdo->prepare('DELETE FROM site_events WHERE id = ?')->execute([$id]);
}

function siteEventTypeLabel(string $type): string
{
    $catalog = siteEventTypeCatalog();

    return $catalog[$type]['label'] ?? $type;
}

function siteEventStatusLabel(array $ev): string
{
    if (empty($ev['published'])) {
        return 'Brouillon';
    }
    if (empty($ev['enabled'])) {
        return 'Désactivé';
    }
    $now = gmdate('Y-m-d H:i:s');
    if (strcmp($now, (string) $ev['starts_at']) < 0) {
        return 'Planifié';
    }
    if (strcmp($now, (string) $ev['ends_at']) >= 0) {
        return 'Terminé';
    }

    return 'En cours';
}

/** True si publié, activé et dans la fenêtre de dates (UTC). */
function siteEventIsLive(array $ev): bool
{
    if (empty($ev['published']) || empty($ev['enabled'])) {
        return false;
    }
    $now = gmdate('Y-m-d H:i:s');

    return strcmp($now, (string) ($ev['starts_at'] ?? '')) >= 0
        && strcmp($now, (string) ($ev['ends_at'] ?? '')) < 0;
}

/**
 * Push Web à tous les joueurs abonnés (nouvel événement).
 *
 * @return array{users:int,sent:int,skipped:bool}
 */
function notifySiteEventPush(PDO $pdo, array $event): array
{
    $out = ['users' => 0, 'sent' => 0, 'skipped' => false];
    if (!function_exists('pushIsConfigured') || !pushIsConfigured()) {
        $out['skipped'] = true;
        return $out;
    }

    ensurePushSubscriptionSchema($pdo);
    $stmt = $pdo->query(
        'SELECT DISTINCT ps.user_id
         FROM push_subscriptions ps
         INNER JOIN users u ON u.id = ps.user_id AND u.actif = 1'
    );
    $userIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    $out['users'] = count($userIds);
    if ($userIds === []) {
        return $out;
    }

    @set_time_limit(120);
    $title = trim((string) ($event['title'] ?? ''));
    $body  = trim((string) ($event['message'] ?? ''));
    if ($title === '') {
        $title = APP_NAME;
    }
    if ($body === '') {
        $body = 'Un nouvel événement est en cours sur ' . APP_NAME . '.';
    }
    $eventId = (int) ($event['id'] ?? 0);
    $tag = $eventId > 0 ? ('prognoz-event-' . $eventId) : ('prognoz-event-' . time());

    foreach ($userIds as $uid) {
        if ($uid <= 0) {
            continue;
        }
        $out['sent'] += sendPushToUser($pdo, $uid, [
            'title' => $title,
            'body'  => $body,
            'url'   => url('account/dashboard.php'),
            'tag'   => $tag,
        ]);
    }

    return $out;
}

/** Libellé admin du résultat d’envoi push événement. */
function formatSiteEventNotifyFlash(array $stats): string
{
    if (!empty($stats['skipped'])) {
        return 'Push non configuré (VAPID / vendor) — aucune notif envoyée.';
    }
    $users = (int) ($stats['users'] ?? 0);
    $sent  = (int) ($stats['sent'] ?? 0);
    if ($users === 0) {
        return 'Aucun joueur abonné aux notifications push.';
    }

    return 'Notifications push : ' . $sent . ' envoyée(s) · ' . $users . ' abonné(s).';
}

/** Libellé joueur du sport favori. */
function userFavoriteSportLabel(?string $sport): string
{
    return match ($sport) {
        'soccer' => t('sport.soccer'),
        'basketball' => t('sport.basketball'),
        'tennis' => t('sport.tennis'),
        default => '',
    };
}

/**
 * @throws InvalidArgumentException
 */
function updateUserProfileExtras(
    PDO $pdo,
    int $userId,
    ?string $bio,
    ?string $sportFavori,
    $equipeFavorie = null,
    $equipesNationales = null
): void {
    ensureUserProfileExtrasSchema($pdo);
    if (function_exists('ensureFavoriteTeamSchema')) {
        ensureFavoriteTeamSchema($pdo);
    }
    $bio = $bio !== null ? trim($bio) : '';
    if (mb_strlen($bio) > 200) {
        throw new InvalidArgumentException(t('dash.bio_too_long'));
    }
    if ($bio === '') {
        $bio = null;
    }
    $sportFavori = $sportFavori !== null ? trim($sportFavori) : '';
    if ($sportFavori !== '' && !in_array($sportFavori, ['soccer', 'basketball', 'tennis'], true)) {
        throw new InvalidArgumentException(t('dash.sport_invalid'));
    }
    if ($sportFavori === '') {
        $sportFavori = null;
    }

    // Club (1) — string, ou 1er élément si ancien appel array mixte
    $clubRaw = null;
    $natsRaw = [];
    if (is_array($equipeFavorie) && $equipesNationales === null) {
        // Ancien format : tout dans un array — on tente de splitter
        foreach ($equipeFavorie as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            if (function_exists('isNationalTeamName') && isNationalTeamName($item)) {
                $natsRaw[] = $item;
            } elseif ($clubRaw === null) {
                $clubRaw = $item;
            }
        }
    } else {
        $clubRaw = is_string($equipeFavorie) ? $equipeFavorie : null;
        if (is_array($equipesNationales)) {
            $natsRaw = $equipesNationales;
        }
    }

    $club = function_exists('normalizeFavoriteClubInput')
        ? normalizeFavoriteClubInput($pdo, $clubRaw)
        : null;
    $nats = function_exists('normalizeFavoriteNationalsInput')
        ? normalizeFavoriteNationalsInput($natsRaw)
        : [];
    $json = $nats === [] ? null : json_encode(array_values($nats), JSON_UNESCAPED_UNICODE);

    $pdo->prepare(
        'UPDATE users SET bio = ?, sport_favori = ?, equipe_favorie = ?, equipes_favorites = ? WHERE id = ?'
    )->execute([$bio, $sportFavori, $club, $json, $userId]);
}
