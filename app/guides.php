<?php
/**
 * Guides publics (contenu informatif indexable) — n’altère pas le jeu.
 */
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/**
 * @return list<array{id:string,url:string,title:string,desc:string}>
 */
function guideCatalog(): array
{
    return [
        [
            'id'    => 'about',
            'url'   => url('guide/a-propos.php'),
            'title' => t('guide.about.card_title'),
            'desc'  => t('guide.about.card_desc'),
        ],
        [
            'id'    => 'points',
            'url'   => url('guide/points.php'),
            'title' => t('guide.points.card_title'),
            'desc'  => t('guide.points.card_desc'),
        ],
        [
            'id'    => 'communities',
            'url'   => url('guide/communautes.php'),
            'title' => t('guide.communities.card_title'),
            'desc'  => t('guide.communities.card_desc'),
        ],
        [
            'id'    => 'faq',
            'url'   => url('guide/faq.php'),
            'title' => t('guide.faq.card_title'),
            'desc'  => t('guide.faq.card_desc'),
        ],
    ];
}

/** Placeholders des documents guides (valeurs alignées sur le jeu réel). */
function guidePlaceholders(): array
{
    $email = APP_CONTACT_EMAIL;
    $favWin = (int) (POINTS_FAV_TEAM * FAV_TEAM_WIN_MULTIPLIER);

    return array_merge(legalPlaceholders(), [
        'howto_url'        => e(url('legal/comment-ca-marche.php')),
        'guides_url'       => e(url('guide/')),
        'about_url'        => e(url('guide/a-propos.php')),
        'points_url'       => e(url('guide/points.php')),
        'communities_url'  => e(url('guide/communautes.php')),
        'faq_url'          => e(url('guide/faq.php')),
        'home_url'         => e(url('index.php')),
        'register_url'     => e(url('auth/register.php')),
        'pts_1x2'          => (string) (int) POINTS_1X2,
        'pts_score'        => (string) (int) POINTS_SCORE_EXACT,
        'pts_buteur'       => (string) (int) POINTS_BUTEUR,
        'pts_fav_base'     => (string) (int) POINTS_FAV_TEAM,
        'pts_fav_win'      => (string) $favWin,
        'fav_max'          => (string) (int) FAV_TEAMS_MAX,
        'season_days'      => (string) (int) SAISON_DUREE_JOURS,
        'podium_1'         => (string) (int) SEASON_PODIUM_BONUS[1],
        'podium_2'         => (string) (int) SEASON_PODIUM_BONUS[2],
        'podium_3'         => (string) (int) SEASON_PODIUM_BONUS[3],
        'match_horizon'    => (string) (int) MATCHS_HORIZON_JOURS,
        'contact_mailto'   => '<a href="mailto:' . e($email) . '">' . e($email) . '</a>',
    ]);
}

function guideLoadDocument(string $doc): string
{
    $doc = preg_replace('/[^a-z0-9\-]/', '', strtolower($doc)) ?? '';
    $lang = currentLang() === 'en' ? 'en' : 'fr';
    $path = dirname(__DIR__) . '/lang/guides/' . $doc . '.' . $lang . '.php';
    if (!is_file($path)) {
        $path = dirname(__DIR__) . '/lang/guides/' . $doc . '.fr.php';
    }
    if (!is_file($path)) {
        return '';
    }
    $html = require $path;

    return is_string($html) ? $html : '';
}

function guideDocumentHtml(string $doc): string
{
    $html = guideLoadDocument($doc);
    foreach (guidePlaceholders() as $key => $value) {
        $html = str_replace('{' . $key . '}', $value, $html);
    }

    return strip_tags($html, '<p><h2><h3><ul><ol><li><strong><em><a><br><span><code><sup>');
}

function guideCrossNav(string $current): void
{
    $items = [
        'hub'          => ['url' => url('guide/'), 'label' => t('guide.nav.hub')],
        'about'        => ['url' => url('guide/a-propos.php'), 'label' => t('guide.nav.about')],
        'points'       => ['url' => url('guide/points.php'), 'label' => t('guide.nav.points')],
        'communities'  => ['url' => url('guide/communautes.php'), 'label' => t('guide.nav.communities')],
        'faq'          => ['url' => url('guide/faq.php'), 'label' => t('guide.nav.faq')],
        'howto'        => ['url' => url('legal/comment-ca-marche.php'), 'label' => t('nav.howto')],
    ];
    ?>
    <nav class="legal-cross guide-cross" aria-label="<?= e(t('guide.nav.label')) ?>">
        <?php foreach ($items as $key => $item): ?>
            <?php if ($key === $current): ?>
                <span class="legal-cross-current" aria-current="page"><?= e($item['label']) ?></span>
            <?php else: ?>
                <a href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <?php
}

function guideUpdatedLabel(): string
{
    return t('guide.updated', ['date' => t('guide.date')]);
}
