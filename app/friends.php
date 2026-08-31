<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

function findUserByPseudo(PDO $pdo, string $pseudo): ?array
{
    $pseudo = trim($pseudo);
    if ($pseudo === '') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, pseudo, points_totaux FROM users WHERE pseudo = ? AND actif = 1');
    $stmt->execute([$pseudo]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function friendshipBetween(PDO $pdo, int $userA, int $userB): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM friendships
         WHERE (user_id = ? AND ami_id = ?) OR (user_id = ? AND ami_id = ?)
         LIMIT 1'
    );
    $stmt->execute([$userA, $userB, $userB, $userA]);

    return $stmt->fetch() ?: null;
}

function sendFriendRequest(PDO $pdo, int $fromId, string $targetPseudo): void
{
    if (mb_strtolower($targetPseudo) === mb_strtolower($_SESSION['pseudo'] ?? '')) {
        throw new InvalidArgumentException(t('friends.err.self'));
    }

    $target = findUserByPseudo($pdo, $targetPseudo);
    if (!$target) {
        throw new InvalidArgumentException(t('friends.err.not_found'));
    }

    $targetId = (int) $target['id'];
    if ($targetId === $fromId) {
        throw new InvalidArgumentException(t('friends.err.self'));
    }

    $existing = friendshipBetween($pdo, $fromId, $targetId);
    if ($existing) {
        if ($existing['statut'] === 'accepte') {
            throw new InvalidArgumentException(t('friends.err.already'));
        }
        if ($existing['statut'] === 'en_attente') {
            if ((int) $existing['user_id'] === $fromId) {
                throw new InvalidArgumentException(t('friends.err.pending'));
            }
            $pdo->prepare('UPDATE friendships SET statut = "accepte" WHERE id = ?')
                ->execute([(int) $existing['id']]);
            return;
        }
        if ($existing['statut'] === 'refuse') {
            $pdo->prepare(
                'UPDATE friendships SET statut = "en_attente", user_id = ?, ami_id = ?, created_at = NOW() WHERE id = ?'
            )->execute([$fromId, $targetId, (int) $existing['id']]);
            return;
        }
    }

    $pdo->prepare(
        'INSERT INTO friendships (user_id, ami_id, statut) VALUES (?, ?, "en_attente")'
    )->execute([$fromId, $targetId]);
}

function respondFriendRequest(PDO $pdo, int $userId, int $friendshipId, bool $accept): void
{
    $stmt = $pdo->prepare('SELECT * FROM friendships WHERE id = ? AND ami_id = ? AND statut = "en_attente"');
    $stmt->execute([$friendshipId, $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new InvalidArgumentException(t('friends.err.request_gone'));
    }

    $pdo->prepare(
        'UPDATE friendships SET statut = ? WHERE id = ?'
    )->execute([$accept ? 'accepte' : 'refuse', $friendshipId]);
}

function removeFriend(PDO $pdo, int $userId, int $friendshipId): void
{
    $stmt = $pdo->prepare(
        'SELECT * FROM friendships WHERE id = ?
         AND ((user_id = ? OR ami_id = ?) AND statut = "accepte")'
    );
    $stmt->execute([$friendshipId, $userId, $userId]);
    if (!$stmt->fetch()) {
        throw new InvalidArgumentException(t('friends.err.gone'));
    }
    $pdo->prepare('DELETE FROM friendships WHERE id = ?')->execute([$friendshipId]);
}

/**
 * Points saison (communauté générale) pour une liste d'user_id.
 * @param list<int> $userIds
 * @return array<int,int> user_id => points
 */
function friendSeasonPointsMap(PDO $pdo, array $userIds): array
{
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
    if ($userIds === []) {
        return [];
    }

    $generalId = getGeneralCommunityId($pdo);
    $season    = getActiveSeason($pdo);
    if (!$generalId || !$season) {
        return array_fill_keys($userIds, 0);
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT user_id, points FROM season_scores
         WHERE season_id = ? AND community_id = ? AND user_id IN ($placeholders)"
    );
    $stmt->execute(array_merge([(int) $season['id'], $generalId], $userIds));

    $map = array_fill_keys($userIds, 0);
    foreach ($stmt->fetchAll() as $row) {
        $map[(int) $row['user_id']] = (int) $row['points'];
    }

    return $map;
}

/** @return list<array<string,mixed>> */
function listAcceptedFriends(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT f.id AS friendship_id, u.id, u.pseudo, u.surnom, u.points_totaux, u.serie_en_cours, u.avatar_url, u.equipped_name
         FROM friendships f
         INNER JOIN users u ON u.id = IF(f.user_id = ?, f.ami_id, f.user_id)
         WHERE (f.user_id = ? OR f.ami_id = ?) AND f.statut = "accepte" AND u.actif = 1
         ORDER BY u.pseudo ASC'
    );
    $stmt->execute([$userId, $userId, $userId]);
    $friends = $stmt->fetchAll();
    $points  = friendSeasonPointsMap($pdo, array_column($friends, 'id'));
    foreach ($friends as &$f) {
        $f['points_saison'] = $points[(int) $f['id']] ?? 0;
    }
    unset($f);

    return $friends;
}

/** @return list<array<string,mixed>> */
function listPendingFriendRequests(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT f.id AS friendship_id, u.id, u.pseudo, u.surnom, u.points_totaux, u.avatar_url, u.equipped_name, f.created_at
         FROM friendships f
         INNER JOIN users u ON u.id = f.user_id
         WHERE f.ami_id = ? AND f.statut = "en_attente" AND u.actif = 1
         ORDER BY f.created_at DESC'
    );
    $stmt->execute([$userId]);
    $pending = $stmt->fetchAll();
    $points  = friendSeasonPointsMap($pdo, array_column($pending, 'id'));
    foreach ($pending as &$p) {
        $p['points_saison'] = $points[(int) $p['id']] ?? 0;
    }
    unset($p);

    return $pending;
}

/** @return list<array<string,mixed>> */
function listSentFriendRequests(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT f.id AS friendship_id, u.id, u.pseudo, u.surnom, u.avatar_url, u.equipped_name, f.created_at
         FROM friendships f
         INNER JOIN users u ON u.id = f.ami_id
         WHERE f.user_id = ? AND f.statut = "en_attente" AND u.actif = 1
         ORDER BY f.created_at DESC'
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

function countAcceptedFriends(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM friendships
         WHERE (user_id = ? OR ami_id = ?) AND statut = "accepte"'
    );
    $stmt->execute([$userId, $userId]);

    return (int) $stmt->fetchColumn();
}
