<?php
require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non connecté.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = [];
}
$communityId = (int) ($payload['community_id'] ?? 0);
$contenu     = trim((string) ($payload['contenu'] ?? ''));
$csrf        = $payload['csrf_token'] ?? '';
$userId      = (int) $_SESSION['user_id'];
$pseudo      = (string) ($_SESSION['pseudo'] ?? '');
$avatarUrl   = avatarPublicUrl($_SESSION['avatar_url'] ?? null);

if (empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Session expirée, rechargez la page.']);
    exit;
}

releaseSession();

// Envoi = plus en train d'écrire
chatTypingClear($communityId, $userId);

if ($contenu === '' || mb_strlen($contenu) > 500) {
    echo json_encode(['success' => false, 'message' => 'Message vide ou trop long (500 caractères max).']);
    exit;
}

if (!encryptionConfigured()) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Chiffrement non configuré : ajoutez APP_ENCRYPTION_KEY dans le .env du serveur.',
    ]);
    exit;
}

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare('SELECT 1 FROM community_members WHERE community_id = ? AND user_id = ?');
    $stmt->execute([$communityId, $userId]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Vous n\'êtes pas membre de cette communauté.']);
        exit;
    }

    $encrypted = encryptSensitive($contenu);
    $stmt = $pdo->prepare(
        'INSERT INTO community_messages (community_id, user_id, contenu) VALUES (?, ?, ?)'
    );
    $stmt->execute([$communityId, $userId, $encrypted]);
    $messageId = (int) $pdo->lastInsertId();

    $communityName = 'Communauté';
    try {
        $stmt = $pdo->prepare('SELECT nom FROM communities WHERE id = ?');
        $stmt->execute([$communityId]);
        $communityRow = decryptCommunityRow($stmt->fetch() ?: [], false);
        $communityName = (string) ($communityRow['nom'] ?? 'Communauté');
        notifyCommunityChatPush($pdo, $communityId, $userId, $pseudo, $contenu, $communityName);
    } catch (Throwable $e) {
        // Push / déchiffrement nom : ne bloque pas l'envoi du message
    }

    echo json_encode([
        'success' => true,
        'message' => [
            'id'            => $messageId,
            'contenu'       => $contenu,
            'pseudo'        => $pseudo,
            'user_id'       => $userId,
            'avatar_url'    => $avatarUrl,
            'is_site_admin' => isSiteAdminUser($userId),
            'equipped_name' => shopEquippedName(currentUser($pdo) ?? []),
            'created_at'    => gmdate('Y-m-d H:i:s'),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Envoi impossible, réessayez.']);
}
