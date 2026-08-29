<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

const RETRO_UI_COOKIE = 'prognoz_ui';
const RETRO_UI_VALUE = 'retro';

/**
 * Vieux navigateurs → thème rétro ~2005 (IE ≤ 11, iOS ≤ 7, vieux mobiles…).
 * Ce n’est pas une mesure de sécurité : le UA se ment facilement.
 */
function isLegacyBrowser(?string $ua = null): bool
{
    $ua = $ua ?? (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '') {
        return false;
    }

    // Internet Explorer toutes versions jusqu’à IE11 (MSIE n, ou Trident + rv)
    if (preg_match('/\bMSIE\s+(\d+)/i', $ua, $m)) {
        return (int) $m[1] <= 11;
    }
    if (stripos($ua, 'Trident/') !== false) {
        if (preg_match('/\brv:(\d+)/i', $ua, $m) && (int) $m[1] <= 11) {
            return true;
        }
        // IE11 desktop : Trident/7.0 sans MSIE
        if (preg_match('/Trident\/[4-7]\./i', $ua)) {
            return true;
        }
    }

    // Vieux mobiles / feature phones / Windows Phone
    if (preg_match('/BlackBerry|BB10|Opera Mini|Opera Mobi|Symbian|Series60|Nokia|PalmOS|webOS\/[12]\b|Windows Phone OS [67]\b|Windows Phone 8\.0|Windows CE|IEMobile\/[0-9]+\./i', $ua)) {
        return true;
    }

    // Android Browser stock jusqu’à 4.x sans Chrome
    if (preg_match('/Android\s+[1-4]\./i', $ua) && stripos($ua, 'Chrome/') === false) {
        return true;
    }

    // iOS Safari ≤ 7 (iPhone 4 max = 7.1.2 ; iOS 6 inclus)
    // UA : "iPhone OS 6_1_6" / "CPU iPhone OS 6_0" / "CPU OS 6_0" (iPad)
    if (preg_match('/(?:iPhone OS|CPU(?: iPhone)? OS) ([1-7])[_\.]/i', $ua)) {
        return true;
    }

    return false;
}

function wantsRetroUi(): bool
{
    return isLegacyBrowser();
}

/** Attribut class pour la balise <html> (vide si site moderne). */
function htmlUiClassAttr(): string
{
    $classes = [];
    if (wantsRetroUi()) {
        $classes[] = 'theme-retro';
    }
    try {
        if (function_exists('getDisplaySiteEvent') && function_exists('primaryEventThemeSlug')) {
            $ev = getDisplaySiteEvent(getPDO());
            if ($ev && empty($ev['_upcoming'])) {
                $theme = primaryEventThemeSlug(getPDO());
                if ($theme === '' || $theme === 'default') {
                    $classes[] = 'event-theme-default';
                } else {
                    $classes[] = 'event-theme-' . $theme;
                }
            }
        }
    } catch (Throwable $e) {
        // ignore
    }

    return $classes === [] ? '' : ' class="' . e(implode(' ', $classes)) . '"';
}

function retroUiCookieIsSet(): bool
{
    return isset($_COOKIE[RETRO_UI_COOKIE]) && (string) $_COOKIE[RETRO_UI_COOKIE] === RETRO_UI_VALUE;
}

function setRetroUiCookie(): void
{
    if (headers_sent()) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(RETRO_UI_COOKIE, RETRO_UI_VALUE, [
        'expires'  => time() + 86400 * 30,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[RETRO_UI_COOKIE] = RETRO_UI_VALUE;
}

function clearRetroUiCookie(): void
{
    if (headers_sent()) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(RETRO_UI_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[RETRO_UI_COOKIE]);
}

/**
 * Gate UI rétro :
 * - moderne + ?ui=retro / chemin /retro → redirect site normal
 * - legacy → cookie rétro
 * - moderne + cookie rétro → purge cookie
 */
function enforceRetroUiGate(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    $legacy = isLegacyBrowser();
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string) (parse_url($uri, PHP_URL_PATH) ?? '');
    $query = [];
    parse_str((string) (parse_url($uri, PHP_URL_QUERY) ?? ''), $query);

    $forceRetroQuery = isset($query['ui']) && strtolower((string) $query['ui']) === 'retro';
    $forceRetroPath = (bool) preg_match('#(^|/)retro(/|$)#i', $path);

    if (!$legacy && ($forceRetroQuery || $forceRetroPath)) {
        if ($forceRetroQuery) {
            unset($query['ui']);
        }
        $cleanPath = $forceRetroPath
            ? preg_replace('#/retro(?=/|$)#i', '', $path) ?: '/'
            : $path;
        if ($cleanPath === '') {
            $cleanPath = '/';
        }
        $qs = http_build_query($query);
        $target = $cleanPath . ($qs !== '' ? '?' . $qs : '');
        header('Location: ' . $target, true, 302);
        exit;
    }

    if ($legacy) {
        setRetroUiCookie();
        return;
    }

    if (retroUiCookieIsSet()) {
        clearRetroUiCookie();
    }
}
