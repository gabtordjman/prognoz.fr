<?php
require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$status = pushConfigStatus();

echo json_encode([
    'ok'         => $status['ok'],
    'publicKey'  => VAPID_PUBLIC_KEY,
    'has_vapid'  => $status['has_vapid'],
    'has_vendor' => $status['has_vendor'],
    'missing'    => $status['missing'],
], JSON_UNESCAPED_UNICODE);
