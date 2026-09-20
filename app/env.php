<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/** Charge un fichier .env (clé=valeur, # commentaires). */
function loadEnvFile(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
            $value = trim($value, "\"'");
        }
        if ($key === '') {
            continue;
        }
        // Le fichier .env fait foi à chaque requête (toggle admin / FPM worker).
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function env(string $key, string $default = ''): string
{
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return (string) $_ENV[$key];
    }
    $v = getenv($key);
    return ($v !== false && $v !== '') ? (string) $v : $default;
}

function envBool(string $key, bool $default = false): bool
{
    $v = strtolower(env($key, $default ? '1' : '0'));
    return in_array($v, ['1', 'true', 'yes', 'on'], true);
}

/**
 * Met à jour une clé dans le fichier .env (et $_ENV / putenv pour le process courant).
 * Réservé à des clés allowlistées côté appelant.
 */
function writeEnvValue(string $key, string $value, ?string $envPath = null): bool
{
    $key = trim($key);
    if ($key === '' || !preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
        return false;
    }

    $path = $envPath ?? (dirname(__DIR__) . '/.env');
    if (!is_file($path) || !is_writable($path)) {
        return false;
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return false;
    }

    // Escape si espaces / caractères spéciaux.
    $needsQuotes = (bool) preg_match('/[\s#\'"\\\\]/', $value);
    $encoded = $needsQuotes
        ? '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"'
        : $value;

    $line = $key . '=' . $encoded;
    $pattern = '/^' . preg_quote($key, '/') . '\s*=.*$/m';
    if (preg_match($pattern, $raw)) {
        $next = preg_replace($pattern, $line, $raw, 1);
    } else {
        $next = rtrim($raw) . "\n" . $line . "\n";
    }
    if (!is_string($next)) {
        return false;
    }

    $fp = fopen($path, 'c+');
    if ($fp === false) {
        return false;
    }
    try {
        if (!flock($fp, LOCK_EX)) {
            return false;
        }
        ftruncate($fp, 0);
        rewind($fp);
        if (fwrite($fp, $next) === false) {
            return false;
        }
        fflush($fp);
        flock($fp, LOCK_UN);
    } finally {
        fclose($fp);
    }

    $_ENV[$key] = $value;
    putenv($key . '=' . $value);

    return true;
}
