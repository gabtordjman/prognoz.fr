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
    echo json_encode(['ok' => false, 'message' => t('api.method_not_allowed')]);
    exit;
}

$pdo = getPDO();
$userId = (int) $_SESSION['user_id'];

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '{}', true);
if (!is_array($body) || !csrfCheckJson($body)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => t('api.session_expired')]);
    exit;
}

try {
    $jersey = (string) ($body['jersey'] ?? '');
    $shorts = (string) ($body['shorts'] ?? '');
    $prop = (string) ($body['prop'] ?? '');
    saveUserKit($pdo, $userId, $jersey, $shorts, $prop);
    clearCurrentUserCache();

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Prognoz kit_save: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => t('kit.err.generic')], JSON_UNESCAPED_UNICODE);
}
