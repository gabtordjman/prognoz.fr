<?php
/**
 * Classes HTML globales (thèmes événement / fond boutique).
 * L’ancien mode « rétro » (UA, cookie prognoz_ui, retro.css) a été retiré.
 */
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/** Attribut class pour la balise <html> (vide si rien de spécial). */
function htmlUiClassAttr(): string
{
    $classes = [];
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
    try {
        if (function_exists('shopResolvedPageBackgroundCss')) {
            $pageBg = shopResolvedPageBackgroundCss();
            if ($pageBg !== '') {
                $classes[] = 'page-bg';
                $classes[] = 'page-bg--' . $pageBg;
            }
        }
    } catch (Throwable $e) {
        // ignore
    }

    return $classes === [] ? '' : ' class="' . e(implode(' ', $classes)) . '"';
}

/** Purge l’ancien cookie d’UI rétro s’il traîne encore chez un visiteur. */
function clearObsoleteRetroUiCookie(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    if (!isset($_COOKIE['prognoz_ui'])) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('prognoz_ui', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE['prognoz_ui']);
}
