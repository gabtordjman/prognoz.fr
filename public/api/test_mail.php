<?php
/**
 * Diagnostic SMTP — protégé par CRON_SECRET.
 * Exemple : /api/test_mail.php?key=VOTRE_CRON_SECRET&to=votre@email.fr
 *
 * Ne renvoie jamais host/user SMTP en clair.
 */
require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? '';
if (CRON_SECRET === '' || !hash_equals(CRON_SECRET, $key)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Clé invalide']);
    exit;
}

$to = trim($_GET['to'] ?? '');
$send = $to !== '';

$result = diagnoseSmtp($send ? $to : null);

echo json_encode([
    'ok'              => $result['ok'],
    'smtp_configured' => smtpConfigured(),
    'sent'            => $send && $result['ok'],
    'steps'           => $result['steps'],
    'error'           => $result['error'] ?: lastMailError(),
    'hint'            => $result['ok'] && !$send
        ? 'Ajoutez &to=votre@email.fr pour envoyer un mail test.'
        : '',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
