<?php
require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$pdo    = getPDO();
$userId = (int) $_SESSION['user_id'];
releaseSession();

if (!pushIsConfigured()) {
    echo json_encode([
        'ok'      => false,
        'message' => 'Push non configuré (clés VAPID ou bibliothèque manquante).',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$sent = sendPushToUser($pdo, $userId, [
    'title' => 'Prognoz — test',
    'body'  => 'Les notifications fonctionnent ! Vous recevrez les messages communauté ici.',
    'url'   => url('account/settings.php'),
    'tag'   => 'prognoz-test-' . time(),
]);

echo json_encode([
    'ok'   => $sent > 0,
    'sent' => $sent,
    'message' => $sent > 0
        ? 'Notification test envoyée.'
        : 'Aucun appareil abonné — réautorisez les notifications.',
], JSON_UNESCAPED_UNICODE);
