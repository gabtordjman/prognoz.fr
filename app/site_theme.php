<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

const SITE_THEME_DEFAULT = 'classic';

function ensureUserThemeSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'ui_theme'");
    if ($check && $check->fetch()) {
        return;
    }
    $pdo->exec(
        "ALTER TABLE users ADD COLUMN ui_theme VARCHAR(32) NOT NULL DEFAULT 'classic'"
    );
}

/**
 * Catalogue des thèmes (préférence par utilisateur).
 *
 * @return array<string, array{
 *   id:string,
 *   name_key:string,
 *   desc_key:string,
 *   theme_color:string,
 *   preview:list<string>,
 *   font_display:?string,
 *   google_fonts:?string
 * }>
 */
function siteThemeCatalog(): array
{
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }

    $catalog = [
        'classic' => [
            'id' => 'classic',
            'name_key' => 'settings.theme.classic.name',
            'desc_key' => 'settings.theme.classic.desc',
            'theme_color' => '#0f1a14',
            'preview' => ['#1a3226', '#e4d9c4', '#9a7420', '#2d6b48'],
            'font_display' => null,
            'google_fonts' => null,
        ],
        'floodlight' => [
            'id' => 'floodlight',
            'name_key' => 'settings.theme.floodlight.name',
            'desc_key' => 'settings.theme.floodlight.desc',
            'theme_color' => '#0a0c0e',
            'preview' => ['#0a0c0e', '#1a1f24', '#3dff8a', '#e8eef2'],
            'font_display' => "'Bebas Neue', Impact, sans-serif",
            'google_fonts' => 'Bebas+Neue',
        ],
        'chalk' => [
            'id' => 'chalk',
            'name_key' => 'settings.theme.chalk.name',
            'desc_key' => 'settings.theme.chalk.desc',
            'theme_color' => '#1c2420',
            'preview' => ['#2a3830', '#e8e4d8', '#7ec8a0', '#4a6b58'],
            'font_display' => "'Special Elite', 'Courier New', monospace",
            'google_fonts' => 'Special+Elite',
        ],
        'pressbox' => [
            'id' => 'pressbox',
            'name_key' => 'settings.theme.pressbox.name',
            'desc_key' => 'settings.theme.pressbox.desc',
            'theme_color' => '#12161c',
            'preview' => ['#1a2230', '#d8dde6', '#f0a020', '#5a7a9a'],
            'font_display' => "'Barlow Condensed', 'Arial Narrow', sans-serif",
            'google_fonts' => 'Barlow+Condensed:wght@500;600;700',
        ],
        'derby' => [
            'id' => 'derby',
            'name_key' => 'settings.theme.derby.name',
            'desc_key' => 'settings.theme.derby.desc',
            'theme_color' => '#140c0c',
            'preview' => ['#1a1010', '#2a1414', '#c45a3a', '#c9a227'],
            'font_display' => "'Oswald', 'Arial Narrow', sans-serif",
            'google_fonts' => 'Oswald:wght@500;600;700',
        ],
    ];

    return $catalog;
}

/** @return list<string> */
function siteThemeSlugs(): array
{
    return array_keys(siteThemeCatalog());
}

function isValidSiteTheme(string $slug): bool
{
    return isset(siteThemeCatalog()[$slug]);
}

/**
 * Thème UI du joueur connecté (visiteur = classic).
 *
 * @param array<string,mixed>|null $user
 */
function resolveUserSiteTheme(?array $user): string
{
    if (!is_array($user)) {
        return SITE_THEME_DEFAULT;
    }
    $slug = trim((string) ($user['ui_theme'] ?? ''));

    return isValidSiteTheme($slug) ? $slug : SITE_THEME_DEFAULT;
}

function getCurrentUserSiteTheme(?PDO $pdo = null): string
{
    if (array_key_exists('_user_site_theme', $GLOBALS) && is_string($GLOBALS['_user_site_theme'])) {
        return $GLOBALS['_user_site_theme'];
    }

    $theme = SITE_THEME_DEFAULT;
    try {
        $pdo = $pdo ?? getPDO();
        ensureUserThemeSchema($pdo);
        if (function_exists('currentUser')) {
            $user = currentUser($pdo);
            $theme = resolveUserSiteTheme(is_array($user) ? $user : null);
        }
    } catch (Throwable $e) {
        $theme = SITE_THEME_DEFAULT;
    }

    $GLOBALS['_user_site_theme'] = $theme;

    return $theme;
}

function setUserSiteTheme(PDO $pdo, int $userId, string $slug): void
{
    $slug = trim($slug);
    if (!isValidSiteTheme($slug)) {
        throw new InvalidArgumentException('Thème invalide.');
    }
    if ($userId <= 0) {
        throw new InvalidArgumentException('Compte invalide.');
    }

    ensureUserThemeSchema($pdo);
    $stmt = $pdo->prepare('UPDATE users SET ui_theme = ? WHERE id = ?');
    $stmt->execute([$slug, $userId]);
    $GLOBALS['_user_site_theme'] = $slug;
    if (function_exists('clearCurrentUserCache')) {
        clearCurrentUserCache();
    }
}

/** @deprecated Utiliser getCurrentUserSiteTheme() */
function getGlobalSiteTheme(?PDO $pdo = null): string
{
    return getCurrentUserSiteTheme($pdo);
}

function siteThemeHtmlClass(?PDO $pdo = null): string
{
    return 'site-theme-' . getCurrentUserSiteTheme($pdo);
}

function siteThemeAllowsShopPageBg(?PDO $pdo = null): bool
{
    return getCurrentUserSiteTheme($pdo) === SITE_THEME_DEFAULT;
}

/**
 * @return array{
 *   id:string,
 *   name_key:string,
 *   desc_key:string,
 *   theme_color:string,
 *   preview:list<string>,
 *   font_display:?string,
 *   google_fonts:?string
 * }
 */
function siteThemeMeta(?PDO $pdo = null): array
{
    $slug = getCurrentUserSiteTheme($pdo);
    $catalog = siteThemeCatalog();

    return $catalog[$slug] ?? $catalog[SITE_THEME_DEFAULT];
}

function siteThemeColorMeta(?PDO $pdo = null): string
{
    return (string) (siteThemeMeta($pdo)['theme_color'] ?? '#0f1a14');
}

/** Lien Google Fonts additionnel pour le thème actif (vide si aucun). */
function siteThemeGoogleFontsHref(?PDO $pdo = null): string
{
    $meta = siteThemeMeta($pdo);
    $family = trim((string) ($meta['google_fonts'] ?? ''));
    if ($family === '') {
        return '';
    }

    return 'https://fonts.googleapis.com/css2?family=' . $family . '&display=swap';
}
