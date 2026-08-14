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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw ?: '{}', true);
    if (!is_array($body) || !csrfCheckJson($body)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Session expirée.']);
        exit;
    }

    releaseSession();

    if (!empty($body['mark_all_seen'])) {
        markUserResultsSeen($pdo, $userId);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ids = [];
    if (is_array($body['ids'] ?? null)) {
        $ids = array_map('intval', $body['ids']);
    }
    markPredictionsNotified($pdo, $userId, $ids);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

releaseSession();

$payload = buildPointNotificationPayload($pdo, $userId, false);

echo json_encode([
    'ok'           => true,
    'total_points' => $payload['total_points'],
    'items'        => $payload['items'],
], JSON_UNESCAPED_UNICODE);
