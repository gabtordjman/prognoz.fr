<?php
require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

$pdo    = getPDO();
$userId = (int) $_SESSION['user_id'];
releaseSession();

$cursorsRaw = $_GET['cursors'] ?? '{}';
$cursors    = json_decode($cursorsRaw, true);
if (!is_array($cursors)) {
    $cursors = [];
}

$normalized = [];
foreach ($cursors as $cid => $mid) {
    $normalized[(string) (int) $cid] = (int) $mid;
}

$excludeCommunityId = isset($_GET['exclude_community_id'])
    ? (int) $_GET['exclude_community_id']
    : null;

if (!empty($_GET['bootstrap'])) {
    echo json_encode([
        'ok'      => true,
        'cursors' => chatNotificationBootstrap($pdo, $userId),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = fetchChatNotifications($pdo, $userId, $normalized, $excludeCommunityId ?: null);

echo json_encode([
    'ok'       => true,
    'messages' => $result['messages'],
    'cursors'  => $result['cursors'],
], JSON_UNESCAPED_UNICODE);
