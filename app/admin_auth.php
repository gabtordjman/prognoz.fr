<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/** Panel admin configuré (slug + user + hash). */
function adminPanelConfigured(): bool
{
    return ADMIN_PANEL_SLUG !== ''
        && ADMIN_USERNAME !== ''
        && ADMIN_PASSWORD_HASH !== '';
}

function adminPanelBaseUrl(): string
{
    return url('admin/index.php');
}

/** URL secrète complète (à garder hors Git). */
function adminPanelSecretUrl(): string
{
    if (!adminPanelConfigured()) {
        return '';
    }
    $base = rtrim((string) env('APP_URL', ''), '/');

    return ($base !== '' ? $base : '') . '/admin/?s=' . rawurlencode(ADMIN_PANEL_SLUG);
}

function adminGateUnlocked(): bool
{
    return !empty($_SESSION['admin_gate_ok'])
        && hash_equals(ADMIN_PANEL_SLUG, (string) ($_SESSION['admin_gate_slug'] ?? ''));
}

/**
 * Ouvre le portail si ?s=SLUG correct. Sinon 404 opaque.
 * À appeler en tête de chaque page /admin/.
 */
function requireAdminGate(): void
{
    if (!adminPanelConfigured()) {
        adminDenyNotFound();
    }

    $provided = (string) ($_GET['s'] ?? '');
    if ($provided !== '' && hash_equals(ADMIN_PANEL_SLUG, $provided)) {
        $_SESSION['admin_gate_ok'] = true;
        $_SESSION['admin_gate_slug'] = ADMIN_PANEL_SLUG;

        // Retirer le slug de l’URL après déverrouillage (reste en session).
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/admin/');
        $path = strtok($uri, '?') ?: '/admin/';
        if (str_contains($path, '/admin/')) {
            header('Location: ' . $path);
            exit;
        }
        return;
    }

    if (adminGateUnlocked()) {
        return;
    }

    adminDenyNotFound();
}

function adminDenyNotFound(): never
{
    http_response_code(404);
    header('X-Robots-Tag: noindex, nofollow');
    if (is_file(dirname(__DIR__) . '/public/404.php')) {
        // Message générique — ne pas révéler l’existence du panel
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>404</title></head>'
            . '<body style="font-family:system-ui;background:#111;color:#ccc;display:flex;'
            . 'min-height:100vh;align-items:center;justify-content:center">'
            . '<p>Page introuvable.</p></body></html>';
    } else {
        echo '404';
    }
    exit;
}

function adminLoggedIn(): bool
{
    return adminGateUnlocked()
        && !empty($_SESSION['admin_authenticated'])
        && hash_equals(ADMIN_USERNAME, (string) ($_SESSION['admin_username'] ?? ''));
}

function requireAdminLogin(): void
{
    requireAdminGate();
    if (!adminLoggedIn()) {
        header('Location: ' . url('admin/login.php'));
        exit;
    }
}

function adminLoginThrottleOk(): bool
{
    $fails = (int) ($_SESSION['admin_login_fails'] ?? 0);
    $lockedUntil = (int) ($_SESSION['admin_login_lock'] ?? 0);
    if ($lockedUntil > time()) {
        return false;
    }
    if ($fails >= 8) {
        $_SESSION['admin_login_lock'] = time() + 900; // 15 min
        $_SESSION['admin_login_fails'] = 0;
        return false;
    }

    return true;
}

function adminLoginRecordFailure(): void
{
    $_SESSION['admin_login_fails'] = (int) ($_SESSION['admin_login_fails'] ?? 0) + 1;
}

function adminLoginClearFailures(): void
{
    unset($_SESSION['admin_login_fails'], $_SESSION['admin_login_lock']);
}

/**
 * @return true|string true si OK, sinon message d’erreur
 */
function adminAttemptLogin(string $username, string $password): bool|string
{
    if (!adminPanelConfigured()) {
        return 'Panel non configuré.';
    }
    if (!adminGateUnlocked()) {
        return 'Accès refusé.';
    }
    if (!adminLoginThrottleOk()) {
        return 'Trop de tentatives — réessayez dans 15 minutes.';
    }

    $userOk = hash_equals(ADMIN_USERNAME, $username);
    $passOk = $userOk && password_verify($password, ADMIN_PASSWORD_HASH);

    // Comparaison constante approximative si mauvais user
    if (!$userOk) {
        password_verify($password, ADMIN_PASSWORD_HASH ?: '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUV');
    }

    if (!$passOk) {
        adminLoginRecordFailure();
        return 'Identifiants incorrects.';
    }

    adminLoginClearFailures();
    session_regenerate_id(true);
    $_SESSION['admin_authenticated'] = true;
    $_SESSION['admin_username'] = ADMIN_USERNAME;
    $_SESSION['admin_login_at'] = time();

    return true;
}

function adminLogout(): void
{
    unset(
        $_SESSION['admin_authenticated'],
        $_SESSION['admin_username'],
        $_SESSION['admin_login_at'],
        $_SESSION['admin_gate_ok'],
        $_SESSION['admin_gate_slug']
    );
}

function adminFlash(string $type, string $message): void
{
    $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
}

/** @return array{type:string,message:string}|null */
function adminTakeFlash(): ?array
{
    $f = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);
    return is_array($f) ? $f : null;
}
