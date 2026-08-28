<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

function ensureSiteAnnouncementsSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS site_announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(120) NOT NULL,
            body TEXT NOT NULL,
            published TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            published_at DATETIME NULL,
            KEY idx_ann_published (published, published_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS site_announcement_reads (
            user_id INT NOT NULL,
            announcement_id INT NOT NULL,
            read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, announcement_id),
            KEY idx_ann_reads_ann (announcement_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/**
 * @return list<array<string,mixed>>
 */
function listSiteAnnouncements(PDO $pdo, bool $publishedOnly = false): array
{
    ensureSiteAnnouncementsSchema($pdo);
    $sql = 'SELECT * FROM site_announcements';
    if ($publishedOnly) {
        $sql .= ' WHERE published = 1';
    }
    $sql .= ' ORDER BY COALESCE(published_at, created_at) DESC, id DESC';

    return $pdo->query($sql)->fetchAll() ?: [];
}

function fetchSiteAnnouncement(PDO $pdo, int $id): ?array
{
    ensureSiteAnnouncementsSchema($pdo);
    $stmt = $pdo->prepare('SELECT * FROM site_announcements WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * @param array{title?:string,body?:string,published?:bool|int} $input
 */
function createSiteAnnouncement(PDO $pdo, array $input): int
{
    ensureSiteAnnouncementsSchema($pdo);
    $title = trim((string) ($input['title'] ?? ''));
    $body = trim((string) ($input['body'] ?? ''));
    $published = !empty($input['published']) ? 1 : 0;

    if ($title === '' || mb_strlen($title) > 120) {
        throw new InvalidArgumentException('Titre invalide (1–120 caractères).');
    }
    if ($body === '' || mb_strlen($body) > 2000) {
        throw new InvalidArgumentException('Message invalide (1–2000 caractères).');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO site_announcements (title, body, published, published_at)
         VALUES (?, ?, ?, ' . ($published ? 'UTC_TIMESTAMP()' : 'NULL') . ')'
    );
    $stmt->execute([$title, $body, $published]);

    return (int) $pdo->lastInsertId();
}

/**
 * @param array{title?:string,body?:string,published?:bool|int} $input
 */
function updateSiteAnnouncement(PDO $pdo, int $id, array $input): void
{
    ensureSiteAnnouncementsSchema($pdo);
    $existing = fetchSiteAnnouncement($pdo, $id);
    if (!$existing) {
        throw new InvalidArgumentException('Annonce introuvable.');
    }

    $title = array_key_exists('title', $input) ? trim((string) $input['title']) : (string) $existing['title'];
    $body = array_key_exists('body', $input) ? trim((string) $input['body']) : (string) $existing['body'];
    $published = array_key_exists('published', $input)
        ? (!empty($input['published']) ? 1 : 0)
        : (int) $existing['published'];

    if ($title === '' || mb_strlen($title) > 120) {
        throw new InvalidArgumentException('Titre invalide (1–120 caractères).');
    }
    if ($body === '' || mb_strlen($body) > 2000) {
        throw new InvalidArgumentException('Message invalide (1–2000 caractères).');
    }

    $wasPublished = !empty($existing['published']);
    $setPublishedAt = '';
    if ($published && !$wasPublished) {
        $setPublishedAt = ', published_at = UTC_TIMESTAMP()';
    } elseif (!$published) {
        $setPublishedAt = ', published_at = NULL';
    }

    $stmt = $pdo->prepare(
        'UPDATE site_announcements SET title = ?, body = ?, published = ?' . $setPublishedAt . ' WHERE id = ?'
    );
    $stmt->execute([$title, $body, $published, $id]);
}

function deleteSiteAnnouncement(PDO $pdo, int $id): void
{
    ensureSiteAnnouncementsSchema($pdo);
    $pdo->prepare('DELETE FROM site_announcement_reads WHERE announcement_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM site_announcements WHERE id = ?')->execute([$id]);
}

function setSiteAnnouncementPublished(PDO $pdo, int $id, bool $published): void
{
    updateSiteAnnouncement($pdo, $id, ['published' => $published]);
}

/**
 * @return list<array<string,mixed>>
 */
function listUnreadSiteAnnouncements(PDO $pdo, int $userId): array
{
    ensureSiteAnnouncementsSchema($pdo);
    $stmt = $pdo->prepare(
        'SELECT a.*
         FROM site_announcements a
         LEFT JOIN site_announcement_reads r
           ON r.announcement_id = a.id AND r.user_id = ?
         WHERE a.published = 1 AND r.user_id IS NULL
         ORDER BY COALESCE(a.published_at, a.created_at) DESC, a.id DESC'
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll() ?: [];
}

function countUnreadSiteAnnouncements(PDO $pdo, int $userId): int
{
    return count(listUnreadSiteAnnouncements($pdo, $userId));
}

function markSiteAnnouncementRead(PDO $pdo, int $userId, int $announcementId): void
{
    ensureSiteAnnouncementsSchema($pdo);
    $ann = fetchSiteAnnouncement($pdo, $announcementId);
    if (!$ann || empty($ann['published'])) {
        return;
    }
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO site_announcement_reads (user_id, announcement_id, read_at)
         VALUES (?, ?, UTC_TIMESTAMP())'
    );
    $stmt->execute([$userId, $announcementId]);
}

/**
 * Données pour le front (badge + popup).
 *
 * @return array{unread_count:int,latest:?array{id:int,title:string,body:string}}
 */
function siteAnnouncementsClientPayload(PDO $pdo, int $userId): array
{
    $unread = listUnreadSiteAnnouncements($pdo, $userId);
    $latest = $unread[0] ?? null;

    return [
        'unread_count' => count($unread),
        'latest'       => $latest ? [
            'id'    => (int) $latest['id'],
            'title' => (string) $latest['title'],
            'body'  => (string) $latest['body'],
        ] : null,
    ];
}
