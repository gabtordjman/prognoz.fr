<?php
/**
 * Génère une paire de clés VAPID pour Web Push (format minishlink/web-push).
 * Usage : php tools/generate_vapid.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI uniquement.\n");
    exit(1);
}

/** XAMPP Windows : OpenSSL a besoin d’un openssl.cnf pour les clés EC. */
function configureOpenSslForEc(): void
{
    if (getenv('OPENSSL_CONF')) {
        return;
    }
    $candidates = [
        dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf',
        dirname(PHP_BINARY) . '/extras/openssl/openssl.cnf',
        'C:/xampp/php/extras/ssl/openssl.cnf',
        'C:/xampp/php/extras/openssl/openssl.cnf',
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            putenv('OPENSSL_CONF=' . str_replace('\\', '/', $path));
            return;
        }
    }
}

configureOpenSslForEc();

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
    try {
        $keys = Minishlink\WebPush\VAPID::createVapidKeys();
        echo "Ajoutez dans .env :\n\n";
        echo 'VAPID_PUBLIC_KEY=' . $keys['publicKey'] . "\n";
        echo 'VAPID_PRIVATE_KEY=' . $keys['privateKey'] . "\n";
        echo "VAPID_SUBJECT=mailto:admin@prognoz.fr\n\n";
        echo "Puis : composer install (si pas déjà fait)\n";
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, "Génération via bibliothèque impossible, fallback OpenSSL…\n");
    }
}

if (!extension_loaded('openssl')) {
    fwrite(STDERR, "Extension openssl requise, ou lancez composer install puis ce script.\n");
    exit(1);
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

$key = openssl_pkey_new([
    'curve_name'       => 'prime256v1',
    'private_key_type' => OPENSSL_KEYTYPE_EC,
]);

if ($key === false) {
    fwrite(STDERR, "Impossible de générer la clé EC.\n");
    fwrite(STDERR, "Sur XAMPP Windows, lancez plutôt ce script sur le VPS Linux :\n");
    fwrite(STDERR, "  php tools/generate_vapid.php\n");
    while ($e = openssl_error_string()) {
        fwrite(STDERR, "  openssl: {$e}\n");
    }
    exit(1);
}

$details = openssl_pkey_get_details($key);
$x = $details['ec']['x'] ?? '';
$y = $details['ec']['y'] ?? '';
$d = $details['ec']['d'] ?? '';

if ($x === '' || $y === '' || $d === '') {
    fwrite(STDERR, "Détails EC invalides.\n");
    exit(1);
}

$publicKey  = base64url_encode("\x04" . $x . $y);
$privateKey = base64url_encode($d);

echo "Ajoutez dans .env :\n\n";
echo "VAPID_PUBLIC_KEY={$publicKey}\n";
echo "VAPID_PRIVATE_KEY={$privateKey}\n";
echo "VAPID_SUBJECT=mailto:admin@prognoz.fr\n\n";
echo "Puis sur le serveur : composer install\n";
