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

/** @return list<string> */
function appMaintenanceAllowIps(): array
{
    $raw = env('MAINTENANCE_ALLOW_IPS', '');
    if ($raw === '') {
        return [];
    }
    $out = [];
    foreach (explode(',', $raw) as $part) {
        $part = trim($part);
        if ($part !== '') {
            $out[] = $part;
        }
    }

    return $out;
}

function appMaintenanceIpAllowed(): bool
{
    $allow = appMaintenanceAllowIps();
    if ($allow === []) {
        return false;
    }
    $ip = clientIp();
    if (ipInAllowlist($ip, ['127.0.0.1', '::1'])) {
        return true;
    }

    return ipInAllowlist($ip, $allow);
}

/** Cron HTTP /api/sync?key=… doit continuer à tourner pendant la maintenance. */
function appMaintenanceCronExempt(): bool
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (!str_contains($script, '/api/sync')) {
        return false;
    }
    $secret = defined('CRON_SECRET') ? (string) CRON_SECRET : env('CRON_SECRET', '');
    $key = (string) ($_GET['key'] ?? $_POST['key'] ?? '');

    return $secret !== '' && $key !== '' && hash_equals($secret, $key);
}

/** Bypass via ?bypass=CLÉ (MAINTENANCE_BYPASS_KEY), mémorisé en session. */
function appMaintenanceBypass(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }

    if (PHP_SAPI === 'cli') {
        $ok = true;
        return true;
    }
    if (appMaintenanceCronExempt()) {
        $ok = true;
        return true;
    }
    if (appMaintenanceIpAllowed()) {
        $ok = true;
        return true;
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
    $title = function_exists('t') ? t('maintenance.title', ['name' => $name]) : ($name . ' est en maintenance');
    $lead = function_exists('t') ? t('maintenance.lead') : 'Le site est temporairement fermé pour une mise à jour. Merci de revenir un peu plus tard.';
    $tag = function_exists('t') ? t('maintenance.tag') : 'Maintenance';
    $retry = function_exists('t') ? t('maintenance.retry') : 'Réessayer';

    echo '<!DOCTYPE html><html lang="' . (function_exists('htmlLang') ? htmlspecialchars(htmlLang(), ENT_QUOTES, 'UTF-8') : 'fr') . '"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<meta name="robots" content="noindex,nofollow">';
    echo '<title>' . htmlspecialchars($tag . ' — ' . $name, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<link rel="icon" href="' . htmlspecialchars($icon, ENT_QUOTES) . '" type="image/svg+xml">';
    echo '<link href="' . htmlspecialchars($css, ENT_QUOTES) . '" rel="stylesheet">';
    echo '</head><body>';
    echo '<div class="status-page status-page-standalone">';
    echo '<div class="status-card">';
    echo '<div class="status-ball" aria-hidden="true"><span>8</span></div>';
    echo '<p class="status-tag">' . htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<h1 class="status-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<p class="status-lead">' . htmlspecialchars($lead, ENT_QUOTES, 'UTF-8') . '</p>';
    if ($contact !== '') {
        echo '<p class="status-meta">';
        echo htmlspecialchars(function_exists('t') ? t('maintenance.contact') : 'Question ?', ENT_QUOTES, 'UTF-8');
        echo ' <a href="mailto:' . htmlspecialchars($contact, ENT_QUOTES) . '">'
            . htmlspecialchars($contact, ENT_QUOTES) . '</a></p>';
    }
    echo '<a href="' . htmlspecialchars($home, ENT_QUOTES) . '" class="btn btn-primary">' . htmlspecialchars($retry, ENT_QUOTES, 'UTF-8') . '</a>';
    echo '</div></div></body></html>';
}
