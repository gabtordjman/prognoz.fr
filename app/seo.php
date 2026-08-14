<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/** Métadonnées SEO par page (title, description, canonical, robots, sitemap). */
function seoPage(string $key, array $overrides = []): array
{
    $pages = [
        'home' => [
            'title'       => t('seo.home.title'),
            'description' => t('seo.home.desc'),
            'path'        => '',
            'og_title'    => t('seo.home.og'),
            'robots'      => 'index,follow',
            'sitemap'     => true,
            'priority'    => '1.0',
            'changefreq'  => 'daily',
            'json_ld'     => true,
        ],
        'privacy' => [
            'title'       => t('seo.privacy.title'),
            'description' => t('seo.privacy.desc'),
            'path'        => 'legal/confidentialite',
            'robots'      => 'index,follow',
            'sitemap'     => true,
            'priority'    => '0.3',
            'changefreq'  => 'yearly',
        ],
        'login' => [
            'title'       => t('seo.login.title'),
            'description' => t('seo.login.desc'),
            'path'        => 'auth/login',
            'robots'      => 'noindex,follow',
        ],
        'register' => [
            'title'       => t('seo.register.title'),
            'description' => t('seo.register.desc'),
            'path'        => 'auth/register',
            'robots'      => 'noindex,follow',
        ],
        'dashboard' => [
            'title'       => t('seo.dashboard.title'),
            'description' => t('seo.dashboard.desc'),
            'path'        => 'account/dashboard',
            'robots'      => 'noindex,nofollow',
        ],
        'communities' => [
            'title'       => t('seo.communities.title'),
            'description' => t('seo.communities.desc'),
            'path'        => 'communities/',
            'robots'      => 'noindex,nofollow',
        ],
        'community' => [
            'title'       => t('seo.community.title'),
            'description' => t('seo.community.desc'),
            'path'        => 'communities/view',
            'robots'      => 'noindex,nofollow',
        ],
        'invite' => [
            'title'       => t('seo.invite.title'),
            'description' => t('seo.invite.desc'),
            'path'        => 'communities/invite',
            'robots'      => 'noindex,nofollow',
        ],
        'howto' => [
            'title'       => t('seo.howto.title'),
            'description' => t('seo.howto.desc'),
            'path'        => 'legal/comment-ca-marche',
            'robots'      => 'index,follow',
            'sitemap'     => true,
            'priority'    => '0.7',
            'changefreq'  => 'monthly',
        ],
        'terms' => [
            'title'       => t('seo.terms.title'),
            'description' => t('seo.terms.desc'),
            'path'        => 'legal/cgu',
            'robots'      => 'index,follow',
            'sitemap'     => true,
            'priority'    => '0.3',
            'changefreq'  => 'yearly',
        ],
        'friends' => [
            'title'       => t('seo.friends.title'),
            'description' => t('seo.friends.desc'),
            'path'        => 'account/friends',
            'robots'      => 'noindex,nofollow',
        ],
        'profile' => [
            'title'       => t('seo.profile.title'),
            'description' => t('seo.profile.desc'),
            'path'        => 'account/profile',
            'robots'      => 'noindex,nofollow',
        ],
        'settings' => [
            'title'       => t('seo.settings.title'),
            'description' => t('seo.settings.desc'),
            'path'        => 'account/settings',
            'robots'      => 'noindex,nofollow',
        ],
    ];

    if (!isset($pages[$key])) {
        return $overrides;
    }

    $meta = array_merge($pages[$key], $overrides);
    $meta['canonical'] = absoluteUrl($meta['path']);
    $meta['og_url']    = $meta['og_url'] ?? $meta['canonical'];

    if (!empty($meta['json_ld']) && $meta['json_ld'] === true) {
        $meta['json_ld'] = seoJsonLdHome();
    }

    return $meta;
}

/** Données structurées JSON-LD pour la page d'accueil. */
function seoJsonLdHome(): array
{
    $siteUrl = absoluteUrl('');
    $lang = currentLang() === 'en' ? 'en-US' : 'fr-FR';

    return [
        [
            '@context'    => 'https://schema.org',
            '@type'       => 'WebSite',
            'name'        => APP_NAME,
            'url'         => $siteUrl,
            'description' => t('og.description'),
            'inLanguage'  => $lang,
        ],
        [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => APP_NAME,
            'url'      => $siteUrl,
            'logo'     => absoluteUrl('assets/img/favicon.svg'),
        ],
    ];
}

/** Entrées publiques pour le sitemap XML. */
function seoSitemapEntries(): array
{
    $keys = ['home', 'privacy', 'howto', 'terms'];
    $entries = [];

    foreach ($keys as $key) {
        $page = seoPage($key);
        if (empty($page['sitemap'])) {
            continue;
        }
        $entries[] = [
            'loc'        => $page['canonical'],
            'changefreq' => $page['changefreq'] ?? 'monthly',
            'priority'   => $page['priority'] ?? '0.5',
            'lastmod'    => date('Y-m-d'),
        ];
    }

    return $entries;
}

function seoRenderSitemapXml(): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach (seoSitemapEntries() as $entry) {
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . htmlspecialchars($entry['loc'], ENT_XML1) . "</loc>\n";
        $xml .= '    <lastmod>' . htmlspecialchars($entry['lastmod'], ENT_XML1) . "</lastmod>\n";
        $xml .= '    <changefreq>' . htmlspecialchars($entry['changefreq'], ENT_XML1) . "</changefreq>\n";
        $xml .= '    <priority>' . htmlspecialchars($entry['priority'], ENT_XML1) . "</priority>\n";
        $xml .= "  </url>\n";
    }

    $xml .= "</urlset>\n";

    return $xml;
}

function seoRenderRobotsTxt(): string
{
    $sitemap = absoluteUrl('sitemap.xml');

    $lines = [
        'User-agent: *',
        'Allow: /',
        'Disallow: /api/',
        'Disallow: /auth/',
        'Disallow: /account/',
        'Disallow: /admin/',
        'Disallow: /communities/',
        '',
        '# Pages publiques indexables : accueil et confidentialité',
        'Allow: /legal/',
        '',
        'Sitemap: ' . $sitemap,
    ];

    return implode("\n", $lines) . "\n";
}
