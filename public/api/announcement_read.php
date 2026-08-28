<?php
require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

$pdo = getPDO();
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $payload = siteAnnouncementsClientPayload($pdo, $userId);
    echo json_encode([
        'ok'           => true,
        'unread_count' => $payload['unread_count'],
        'items'        => $payload['items'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => t('api.method_not_allowed')]);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '{}', true);
if (!is_array($body) || !csrfCheckJson($body)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => t('api.session_expired')]);
    exit;
}

if (!empty($body['mark_all'])) {
    markAllSiteAnnouncementsRead($pdo, $userId);
} else {
    $id = (int) ($body['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => t('api.bad_request')]);
        exit;
    }
    markSiteAnnouncementRead($pdo, $userId, $id);
}

$payload = siteAnnouncementsClientPayload($pdo, $userId);

echo json_encode([
    'ok'           => true,
    'unread_count' => $payload['unread_count'],
    'items'        => $payload['items'],
], JSON_UNESCAPED_UNICODE);
