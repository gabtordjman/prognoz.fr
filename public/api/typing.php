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

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = [];
}

$communityId = (int) ($payload['community_id'] ?? 0);
$active      = !empty($payload['typing']);
$csrf        = (string) ($payload['csrf_token'] ?? '');
$userId      = (int) $_SESSION['user_id'];
$pseudo      = (string) ($_SESSION['pseudo'] ?? '');

if (empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Session expirée, rechargez la page.']);
    exit;
}

releaseSession();

if ($communityId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Communauté invalide.']);
    exit;
}

$pdo = getPDO();
$stmt = $pdo->prepare('SELECT 1 FROM community_members WHERE community_id = ? AND user_id = ?');
$stmt->execute([$communityId, $userId]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Vous n\'êtes pas membre de cette communauté.']);
    exit;
}

if ($active) {
    chatTypingSet($communityId, $userId, $pseudo);
} else {
    chatTypingClear($communityId, $userId);
}

echo json_encode(['success' => true]);
