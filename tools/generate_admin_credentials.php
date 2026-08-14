<?php
/**
 * Génère les identifiants du panel admin web.
 * Usage : php tools/generate_admin_credentials.php
 *         php tools/generate_admin_credentials.php monUser 'MotDePasseTresLong!!'
 */
define('APP_BOOT', true);

$root = dirname(__DIR__);
require_once $root . '/app/env.php';

$user = $argv[1] ?? 'ops-admin';
$pass = $argv[2] ?? '';

if ($pass === '') {
    $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*-_=+';
    $pass = '';
    for ($i = 0; $i < 24; $i++) {
        $pass .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
}

if (strlen($pass) < 16) {
    fwrite(STDERR, "Mot de passe trop court (16+ caractères recommandés).\n");
    exit(1);
}

$slug = bin2hex(random_bytes(16));
$hash = password_hash($pass, PASSWORD_DEFAULT);

$base = '';
if (is_file($root . '/.env')) {
    loadEnvFile($root . '/.env');
    $base = rtrim((string) (getenv('APP_URL') ?: ''), '/');
}
if ($base === '') {
    $base = 'https://www.prognoz.fr';
}

$url = $base . '/admin/?s=' . $slug;

echo "\n=== Prognoz Ops — à coller dans .env (serveur) ===\n\n";
echo "ADMIN_PANEL_SLUG={$slug}\n";
echo "ADMIN_USERNAME={$user}\n";
echo "ADMIN_PASSWORD_HASH={$hash}\n";
echo "\n=== Garder hors Git / notes personnelles ===\n\n";
echo "URL secrète : {$url}\n";
echo "Utilisateur  : {$user}\n";
echo "Mot de passe : {$pass}\n";
echo "\nOuvre l’URL une fois, puis connecte-toi. Ne partage jamais le slug.\n\n";
