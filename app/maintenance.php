<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/** Mode maintenance actif (.env APP_MAINTENANCE=1). */
function appInMaintenanceMode(): bool
{
    return defined('APP_MAINTENANCE') && APP_MAINTENANCE;
}

/** Bypass admin via ?bypass=CLÉ (MAINTENANCE_BYPASS_KEY dans .env), mémorisé en session. */
function appMaintenanceBypass(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }

    $key = env('MAINTENANCE_BYPASS_KEY', '');
    if ($key === '') {
        $ok = false;
        return false;
    }

    if (
        session_status() === PHP_SESSION_ACTIVE
        && !empty($_SESSION['maintenance_bypass'])
        && hash_equals($key, (string) $_SESSION['maintenance_bypass'])
    ) {
        $ok = true;
        return true;
    }

    $provided = $_GET['bypass'] ?? '';
    if (is_string($provided) && $provided !== '' && hash_equals($key, $provided)) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['maintenance_bypass'] = $key;
        }
        $ok = true;
        return true;
    }

    $ok = false;
    return false;
}

function appSendMaintenanceResponse(): void
{
    http_response_code(503);
    header('Retry-After: 3600');
    header('Content-Type: text/html; charset=UTF-8');

    $css = function_exists('url') ? url('assets/css/style.css') : '/assets/css/style.css';
    $home = function_exists('url') ? url('index.php') : '/';
    $icon = function_exists('url') ? url('assets/img/favicon.svg') : '/assets/img/favicon.svg';
    $name = defined('APP_NAME') ? APP_NAME : 'Prognoz';
    $contact = defined('APP_CONTACT_EMAIL') ? APP_CONTACT_EMAIL : '';

    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<meta name="robots" content="noindex,nofollow">';
    echo '<title>Maintenance — ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<link rel="icon" href="' . htmlspecialchars($icon, ENT_QUOTES) . '" type="image/svg+xml">';
    echo '<link href="' . htmlspecialchars($css, ENT_QUOTES) . '" rel="stylesheet">';
    echo '</head><body>';
    echo '<div class="status-page status-page-standalone">';
    echo '<div class="status-card">';
    echo '<div class="status-ball" aria-hidden="true"><span>8</span></div>';
    echo '<p class="status-tag">Maintenance</p>';
    echo '<h1 class="status-title">' . htmlspecialchars($name, ENT_QUOTES) . ' revient très vite</h1>';
    echo '<p class="status-lead">Mise à jour en cours — pronostics et communautés indisponibles quelques minutes.</p>';
    if ($contact !== '') {
        echo '<p class="status-meta">Question ? <a href="mailto:' . htmlspecialchars($contact, ENT_QUOTES) . '">'
            . htmlspecialchars($contact, ENT_QUOTES) . '</a></p>';
    }
    echo '<a href="' . htmlspecialchars($home, ENT_QUOTES) . '" class="btn btn-primary">Réessayer</a>';
    echo '</div></div></body></html>';
}
