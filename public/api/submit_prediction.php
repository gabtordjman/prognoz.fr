<?php
require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Connectez-vous pour pronostiquer.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    echo json_encode(['ok' => false, 'error' => 'Requête invalide.']);
    exit;
}

$csrf = $input['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expirée, rechargez la page.']);
    exit;
}

$marketId = (int) ($input['market_id'] ?? 0);
$cancel   = !empty($input['cancel']);
$reponse  = isset($input['reponse']) ? trim((string) $input['reponse']) : '';

if ($marketId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Match invalide.']);
    exit;
}

try {
    $pdo = getPDO();
    $userId = (int) $_SESSION['user_id'];

    if ($cancel) {
        $result = cancelPrediction($pdo, $userId, $marketId);
    } else {
        if ($reponse === '') {
            echo json_encode(['ok' => false, 'error' => 'Choix manquant.']);
            exit;
        }
        $result = submitPrediction($pdo, $userId, $marketId, $reponse);
    }

    echo json_encode([
        'ok'     => true,
        'cancel' => $cancel || empty($result['item'] ?? null),
        'item'   => $result['item'] ?? null,
        'ticket' => $result['ticket'],
        'gain'   => $result['gain'],
    ]);
} catch (InvalidArgumentException $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erreur serveur.']);
}
