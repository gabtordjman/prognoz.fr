<?php
require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Non connecté.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$pdo    = getPDO();
$userId = (int) $_SESSION['user_id'];

$body = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($body) || !csrfCheckJson($body)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Session expirée.']);
    exit;
}

releaseSession();

$action = (string) ($body['action'] ?? 'subscribe');

if ($action === 'unsubscribe') {
    $endpoint = trim((string) ($body['endpoint'] ?? ''));
    if ($endpoint !== '') {
        deletePushSubscription($pdo, $userId, $endpoint);
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!pushIsConfigured()) {
    echo json_encode([
        'ok'      => false,
        'message' => 'Push non configuré sur le serveur (VAPID + composer install).',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$subscription = $body['subscription'] ?? $body;
if (!is_array($subscription)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Abonnement invalide.']);
    exit;
}

$ok = savePushSubscription($pdo, $userId, $subscription);

echo json_encode([
    'ok'      => $ok,
    'message' => $ok ? 'Abonnement enregistré.' : 'Abonnement refusé.',
], JSON_UNESCAPED_UNICODE);
