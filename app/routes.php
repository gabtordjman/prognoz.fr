<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/** Chemin URL absolu depuis la racine public/ (ex. auth/login → /auth/login). */
function url(string $path = ''): string
{
    $pretty = prettyPublicPath($path);
    $base   = publicBasePath();

    if ($pretty === '') {
        return $base;
    }

    return $base . $pretty;
}

/** URL asset avec cache-bust (?v=filemtime) pour JS/CSS. */
function assetUrl(string $path): string
{
    $rel  = ltrim(str_replace('\\', '/', $path), '/');
    $full = dirname(__DIR__) . '/public/' . $rel;
    $ver  = is_file($full) ? (string) filemtime($full) : '1';

    return publicBasePath() . $rel . '?v=' . rawurlencode($ver);
}

/** Redirection 301 depuis une ancienne URL (compat déploiement). */
function legacyRedirect(string $path): void
{
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    $target = url($path);
    if ($qs !== '' && strpos($target, '?') === false) {
        $target .= '?' . $qs;
    }
    header('Location: ' . $target, true, 301);
    exit;
}
