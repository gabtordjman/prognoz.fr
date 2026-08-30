<?php
require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non connecté.']);
    exit;
}

$communityId = (int) ($_GET['community_id'] ?? 0);
$depuisId    = (int) ($_GET['depuis_id'] ?? 0);
$initial     = !empty($_GET['initial']);
$userId      = (int) $_SESSION['user_id'];

releaseSession();

$pdo = getPDO();

$stmt = $pdo->prepare('SELECT 1 FROM community_members WHERE community_id = ? AND user_id = ?');
$stmt->execute([$communityId, $userId]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Vous n\'êtes pas membre de cette communauté.']);
    exit;
}

if ($initial && $depuisId === 0) {
    $stmt = $pdo->prepare(
        "SELECT cm.id, cm.contenu, cm.created_at, u.id AS user_id, u.pseudo, u.avatar_url, u.equipped_name
         FROM community_messages cm
         INNER JOIN users u ON u.id = cm.user_id
         WHERE cm.community_id = ? AND cm.supprime = 0
         ORDER BY cm.id DESC
         LIMIT 50"
    );
    $stmt->execute([$communityId]);
    $messages = array_reverse(array_map('decryptMessageRow', $stmt->fetchAll()));
} else {
    $stmt = $pdo->prepare(
        "SELECT cm.id, cm.contenu, cm.created_at, u.id AS user_id, u.pseudo, u.avatar_url, u.equipped_name
         FROM community_messages cm
         INNER JOIN users u ON u.id = cm.user_id
         WHERE cm.community_id = ? AND cm.id > ? AND cm.supprime = 0
         ORDER BY cm.id ASC
         LIMIT 100"
    );
    $stmt->execute([$communityId, $depuisId]);
    $messages = array_map('decryptMessageRow', $stmt->fetchAll());
}

foreach ($messages as &$msg) {
    $msg['avatar_url'] = avatarPublicUrl($msg['avatar_url'] ?? null);
    $msg['is_site_admin'] = isSiteAdminUser((int) ($msg['user_id'] ?? 0));
}
unset($msg);

echo json_encode([
    'success'  => true,
    'messages' => $messages,
    'typing'   => chatTypingList($communityId, $userId),
]);
