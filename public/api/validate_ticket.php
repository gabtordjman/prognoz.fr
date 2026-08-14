<?php
require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => t('api.login_required')]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => t('api.method_not_allowed')]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['ok' => false, 'error' => t('api.bad_request')]);
    exit;
}

$csrf = $input['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => t('api.session_expired')]);
    exit;
}

$picks = $input['picks'] ?? [];
if (!is_array($picks) || empty($picks)) {
    echo json_encode(['ok' => false, 'error' => t('api.no_picks')]);
    exit;
}

$pdo    = getPDO();
$userId = (int) $_SESSION['user_id'];
releaseSession();

try {
    $result = validatePredictionsBatch($pdo, $userId, $picks);
} catch (Throwable $e) {
    error_log('Prognoz validate_ticket: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => t('api.validate_failed')]);
    exit;
}

if ($result['saved'] === 0 && !empty($result['errors'])) {
    echo json_encode([
        'ok'    => false,
        'error' => $result['errors'][0],
    ]);
    exit;
}

echo json_encode([
    'ok'      => true,
    'saved'   => $result['saved'],
    'skipped' => $result['skipped'],
    'ticket'  => $result['ticket'],
    'gain'    => $result['gain'],
    'errors'  => $result['errors'],
]);
