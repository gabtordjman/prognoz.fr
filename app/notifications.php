<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/** Dernier message connu par communauté pour initialiser les curseurs client. */
function chatNotificationBootstrap(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT cm.community_id, MAX(cm.id) AS last_id
         FROM community_messages cm
         INNER JOIN community_members mem ON mem.community_id = cm.community_id AND mem.user_id = ?
         WHERE cm.supprime = 0
         GROUP BY cm.community_id'
    );
    $stmt->execute([$userId]);

    $cursors = [];
    foreach ($stmt->fetchAll() as $row) {
        $cursors[(string) (int) $row['community_id']] = (int) $row['last_id'];
    }

    return $cursors;
}

/**
 * Nouveaux messages communauté depuis les curseurs client.
 *
 * @param array<string,int> $cursors community_id => last_seen_message_id
 * @return array{messages: list<array>, cursors: array<string,int>}
 */
function fetchChatNotifications(PDO $pdo, int $userId, array $cursors, ?int $excludeCommunityId = null): array
{
    $stmt = $pdo->prepare(
        'SELECT cm.community_id
         FROM community_members cm
         WHERE cm.user_id = ?'
    );
    $stmt->execute([$userId]);
    $communityIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (empty($communityIds)) {
        return ['messages' => [], 'cursors' => $cursors];
    }

    $messages = [];
    $updated  = $cursors;

    foreach ($communityIds as $communityId) {
        if ($excludeCommunityId !== null && $communityId === $excludeCommunityId) {
            continue;
        }

        $key     = (string) $communityId;
        $sinceId = (int) ($cursors[$key] ?? $cursors[$communityId] ?? -1);

        if ($sinceId < 0) {
            $boot = $pdo->prepare(
                'SELECT COALESCE(MAX(id), 0) FROM community_messages WHERE community_id = ? AND supprime = 0'
            );
            $boot->execute([$communityId]);
            $updated[$key] = (int) $boot->fetchColumn();
            continue;
        }

        $q = $pdo->prepare(
            "SELECT cm.id, cm.community_id, cm.contenu, cm.created_at, cm.user_id,
                    u.pseudo, c.nom AS community_name
             FROM community_messages cm
             INNER JOIN users u ON u.id = cm.user_id
             INNER JOIN communities c ON c.id = cm.community_id
             WHERE cm.community_id = ? AND cm.id > ? AND cm.supprime = 0 AND cm.user_id != ?
             ORDER BY cm.id ASC
             LIMIT 10"
        );
        $q->execute([$communityId, $sinceId, $userId]);

        foreach ($q->fetchAll() as $row) {
            $row = decryptMessageRow($row);
            $communityName = decryptSensitive($row['community_name'] ?? '');
            $messages[] = [
                'id'              => (int) $row['id'],
                'community_id'    => (int) $row['community_id'],
                'community_name'  => $communityName,
                'pseudo'          => (string) ($row['pseudo'] ?? ''),
                'contenu'         => (string) ($row['contenu'] ?? ''),
                'created_at'      => (string) ($row['created_at'] ?? ''),
            ];
            $updated[$key] = max($updated[$key] ?? 0, (int) $row['id']);
        }
    }

    usort($messages, static fn ($a, $b) => $a['id'] <=> $b['id']);

    return ['messages' => $messages, 'cursors' => $updated];
}
