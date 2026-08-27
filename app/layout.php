<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

function layoutHead(string $title, bool $withIcons = true, array $seo = []): void
{
    $documentTitle = $seo['title'] ?? ($title . ' — ' . APP_NAME);
    $description   = $seo['description'] ?? APP_OG_DESCRIPTION;
    $ogTitle       = $seo['og_title'] ?? $documentTitle;
    $ogImage       = $seo['og_image'] ?? 'assets/img/og-prognoz.png';
    $ogType        = $seo['og_type'] ?? 'website';
    $canonical     = $seo['canonical'] ?? null;
    if ($canonical === null && !empty($seo['path'])) {
        $canonical = absoluteUrl($seo['path']);
    }
    $ogUrl    = $seo['og_url'] ?? ($canonical ?? absoluteUrl('index.php'));
    $robots   = $seo['robots'] ?? 'index,follow';
    $retroUi  = function_exists('wantsRetroUi') && wantsRetroUi();
    ?>
    <meta charset="UTF-8">
    <?php if ($retroUi): ?>
    <script>document.documentElement.className+=(document.documentElement.className?" ":"")+"theme-retro"</script>
    <?php endif; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($description) ?>">
    <meta name="robots" content="<?= e($robots) ?>">
    <meta name="theme-color" content="<?= $retroUi ? '#145a32' : '#0f1a14' ?>">
    <title><?= e($documentTitle) ?></title>
    <?php if ($canonical): ?>
    <link rel="canonical" href="<?= e($canonical) ?>">
    <?php endif; ?>
    <link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= e(url('assets/img/apple-touch-icon.svg')) ?>">
    <meta property="og:site_name" content="<?= e(APP_NAME) ?>">
    <meta property="og:type" content="<?= e($ogType) ?>">
    <meta property="og:title" content="<?= e($ogTitle) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e($ogUrl) ?>">
    <meta property="og:image" content="<?= e(absoluteUrl($ogImage)) ?>">
    <meta property="og:locale" content="<?= e(ogLocale()) ?>">
    <meta property="og:locale:alternate" content="<?= e(currentLang() === 'en' ? 'fr_FR' : 'en_US') ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($ogTitle) ?>">
    <meta name="twitter:description" content="<?= e($description) ?>">
    <meta name="twitter:image" content="<?= e(absoluteUrl($ogImage)) ?>">
    <?php
    if (!empty($seo['json_ld'])) {
        $blocks = $seo['json_ld'];
        if (isset($blocks['@context'])) {
            $blocks = [$blocks];
        }
        foreach ($blocks as $block) {
            echo '<script type="application/ld+json">'
                . json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                . "</script>\n    ";
        }
    }
    if ($retroUi): ?>
    <script src="<?= e(assetUrl('assets/js/legacy-polyfill.js')) ?>"></script>
    <link href="<?= e(assetUrl('assets/css/retro.css')) ?>" rel="stylesheet">
    <?php else: ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,400..800;1,400..800&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <?php if ($withIcons): ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <?php endif; ?>
    <link href="<?= e(assetUrl('assets/css/style.css')) ?>" rel="stylesheet">
    <?php endif; ?>
    <?php
}

/** Page statut (404, maintenance…) — même charte visuelle. */
function layoutStatusPage(
    string $tag,
    string $heading,
    string $lead,
    string $primaryHref,
    string $primaryLabel,
    int $httpCode = 404
): void {
    http_response_code($httpCode);
    $user = null;
    try {
        $pdo  = getPDO();
        $user = currentUser($pdo);
    } catch (Throwable $e) {
        $user = null;
    }
    ?>
    <!DOCTYPE html>
    <html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
    <head>
        <?php layoutHead($heading, true, [
            'description' => $lead,
            'og_title'    => APP_NAME . ' — ' . $heading,
            'robots'      => 'noindex,nofollow',
        ]); ?>
    </head>
    <body>
    <?php layoutTopbar($user); ?>
    <main class="app-main">
        <div class="status-page">
            <div class="status-card">
                <div class="status-ball" aria-hidden="true"><span>8</span></div>
                <p class="status-tag"><?= e($tag) ?></p>
                <h1 class="status-title"><?= e($heading) ?></h1>
                <p class="status-lead"><?= e($lead) ?></p>
                <a href="<?= e($primaryHref) ?>" class="btn btn-primary"><?= e($primaryLabel) ?></a>
            </div>
        </div>
    </main>
    <?php layoutFooter(); ?>
    </body>
    </html>
    <?php
}

function layoutTopbar(?array $user, string $active = ''): void
{
    $unseenResults = 0;
    $seasonPoints  = 0;
    $seasonLabel   = '';
    if ($user) {
        try {
            $pdoTop = getPDO();
            $unseenResults = countUnnotifiedWins($pdoTop, (int) $user['id']);
            $activeSeason = getActiveSeason($pdoTop);
            if ($activeSeason) {
                $seasonPoints = getUserGeneralSeasonPoints($pdoTop, (int) $user['id'], (int) $activeSeason['id']);
                $seasonLabel  = seasonCountdownLabel($activeSeason);
            }
        } catch (Throwable $e) {
            $unseenResults = 0;
        }
    }
    if (function_exists('wantsRetroUi') && wantsRetroUi()): ?>
    <div class="retro-banner" role="status">
        <?= e(t('retro.banner')) ?>
    </div>
    <?php endif; ?>
    <?php if (function_exists('renderSiteEventBanner')) {
        renderSiteEventBanner();
    } ?>
    <div class="topbar">
        <div class="topbar-inner">
            <div class="topbar-side topbar-side-left">
                <?php if ($user): ?>
                    <button type="button" class="pill-points pill-points-btn" id="pointsHelpBtn" aria-haspopup="dialog" aria-controls="pointsHelpModal" title="<?= $seasonLabel !== '' ? e(t('nav.season_pts')) . ' · ' . e($seasonLabel) . ' · ' : '' ?><?= e(t('nav.total_pts', ['n' => (int) $user['points_totaux']])) ?>">
                        <?php if (!(function_exists('wantsRetroUi') && wantsRetroUi())): ?><i class="fa-solid fa-coins"></i> <?php endif; ?><?= $seasonPoints ?> <?= e(t('common.pts')) ?>
                    </button>
                <?php endif; ?>
            </div>

            <div class="topbar-center">
                <a href="<?= e(url('index.php')) ?>" class="topbar-brand"><?= e(APP_NAME) ?></a>
                <nav class="topbar-nav">
                    <a href="<?= e(url('index.php')) ?>" class="nav-link<?= $active === 'matchs' ? ' active' : '' ?>"><?= e(t('nav.matches')) ?></a>
                    <?php if ($user): ?>
                        <a href="<?= e(url('account/dashboard.php')) ?>" class="nav-link nav-link-badge-wrap<?= $active === 'dashboard' ? ' active' : '' ?>">
                            <?= e(t('nav.dashboard')) ?>
                            <?php if ($unseenResults > 0): ?>
                                <span class="nav-badge" title="<?= e($unseenResults > 1 ? t('nav.new_results_plural', ['n' => $unseenResults]) : t('nav.new_results', ['n' => $unseenResults])) ?>"><?= $unseenResults > 9 ? '9+' : (int) $unseenResults ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="<?= e(url('account/friends.php')) ?>" class="nav-link<?= $active === 'friends' ? ' active' : '' ?>"><?= e(t('nav.friends')) ?></a>
                        <a href="<?= e(url('communities/index.php')) ?>" class="nav-link<?= $active === 'communities' ? ' active' : '' ?>"><?= e(t('nav.communities')) ?></a>
                    <?php else: ?>
                        <a href="<?= e(url('legal/comment-ca-marche.php')) ?>" class="nav-link"><?= e(t('nav.howto')) ?></a>
                    <?php endif; ?>
                </nav>
            </div>

            <div class="topbar-side topbar-side-right">
                <?php layoutLangSwitcher(); ?>
                <?php if ($user): ?>
                    <span class="topbar-user">
                        <?php renderUserAvatar($user['pseudo'], 'sm', $user['avatar_url'] ?? null); ?>
                        <?= e($user['pseudo']) ?>
                        <?php if (isSiteAdminUser((int) $user['id'])): ?>
                            <?= adminBadgeHtml() ?>
                        <?php endif; ?>
                    </span>
                    <a href="<?= e(url('auth/logout.php')) ?>" class="btn btn-ghost btn-sm"><?= e(t('nav.logout')) ?></a>
                <?php else: ?>
                    <a href="<?= e(url('auth/login.php')) ?>" class="btn btn-ghost btn-sm"><?= e(t('nav.login')) ?></a>
                    <a href="<?= e(url('auth/register.php')) ?>" class="btn btn-primary btn-sm"><?= e(t('nav.register')) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php layoutI18nScript(); ?>
    <?php layoutBetaRibbon(); ?>
    <?php layoutBetaWelcome(); ?>
    <?php if ($user): layoutPointsHelp(); layoutPointToasts(); endif; ?>
    <?php
}

function layoutBetaWelcome(): void
{
    if (!APP_BETA) {
        return;
    }
    $privacy = url('legal/confidentialite.php');
    ?>
    <div class="beta-welcome" id="betaWelcome" role="dialog" aria-modal="true" aria-labelledby="betaWelcomeTitle" hidden>
        <div class="beta-welcome-backdrop" id="betaWelcomeBackdrop"></div>
        <div class="beta-welcome-card">
            <button type="button" class="beta-welcome-close" id="betaWelcomeClose" aria-label="<?= e(t('common.close')) ?>">&times;</button>
            <p class="beta-welcome-tag"><?= e(t('beta.tag')) ?></p>
            <h2 class="beta-welcome-title" id="betaWelcomeTitle"><?= e(t('beta.title', ['app' => APP_NAME])) ?></h2>
            <p class="beta-welcome-text">
                <?= e(t('beta.text')) ?>
            </p>
            <a href="<?= e($privacy) ?>" class="beta-welcome-link"><?= e(t('beta.privacy_link')) ?></a>
            <button type="button" class="btn btn-primary btn-block btn-sm" id="betaWelcomeOk"><?= e(t('beta.ok')) ?></button>
        </div>
    </div>
    <script>
    (function () {
        var KEY = 'prognoz_beta_welcome_dismissed';
        var el = document.getElementById('betaWelcome');
        if (!el) return;
        try {
            if (localStorage.getItem(KEY) === '1') return;
        } catch (e) { /* ignore */ }
        el.hidden = false;
        function closeWelcome() {
            el.hidden = true;
            try { localStorage.setItem(KEY, '1'); } catch (e) { /* ignore */ }
        }
        var closeBtn = document.getElementById('betaWelcomeClose');
        var okBtn = document.getElementById('betaWelcomeOk');
        var backdrop = document.getElementById('betaWelcomeBackdrop');
        if (closeBtn) closeBtn.addEventListener('click', closeWelcome);
        if (okBtn) okBtn.addEventListener('click', closeWelcome);
        if (backdrop) backdrop.addEventListener('click', closeWelcome);
    })();
    </script>
    <?php
}

function layoutBetaRibbon(): void
{
    if (!APP_BETA) {
        return;
    }
    ?>
    <a class="beta-ribbon" href="<?= e(url('legal/confidentialite.php')) ?>" title="<?= e(t('beta.title_attr')) ?>">
        <span class="beta-ribbon-text"><?= e(t('beta.ribbon')) ?></span>
    </a>
    <?php
}

function layoutBetaBanner(): void
{
    layoutBetaRibbon();
}

function layoutFooter(): void
{
    ?>
    <footer class="site-footer">
        <div class="site-footer-inner">
            <span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?></span>
            <nav class="site-footer-nav">
                <a href="<?= e(url('legal/comment-ca-marche.php')) ?>"><?= e(t('nav.howto')) ?></a>
                <a href="<?= e(url('legal/cgu.php')) ?>"><?= e(t('common.cgu')) ?></a>
                <a href="<?= e(url('legal/confidentialite.php')) ?>"><?= e(t('common.privacy')) ?></a>
            </nav>
        </div>
    </footer>
    <?php layoutI18nScript(); ?>
    <script>try{sessionStorage.removeItem('prognoz_sync_reload');}catch(e){}</script>
    <?php if (!(function_exists('wantsRetroUi') && wantsRetroUi())): ?>
    <script src="<?= e(url('assets/js/theme-time.js')) ?>"></script>
    <?php endif; ?>
    <?php if (function_exists('renderSiteEventStarRain')) {
        renderSiteEventStarRain();
    } ?>
    <?php
}

function layoutAuthFooter(): void
{
    ?>
    <nav class="auth-footer" aria-label="<?= e(t('common.legal_nav')) ?>">
        <a href="<?= e(url('legal/cgu.php')) ?>"><?= e(t('common.cgu')) ?></a>
        <span class="auth-footer-sep" aria-hidden="true">·</span>
        <a href="<?= e(url('legal/confidentialite.php')) ?>"><?= e(t('common.privacy')) ?></a>
    </nav>
    <div class="auth-lang-wrap"><?php layoutLangSwitcher(); ?></div>
    <?php layoutI18nScript(); ?>
    <?php
}

function layoutAuthBack(string $href = 'index.php', ?string $label = null): void
{
    if ($label === null) {
        $label = t('common.back_matches');
    }
    ?>
    <a href="<?= e(url($href)) ?>" class="auth-back btn btn-ghost btn-sm">&larr; <?= e($label) ?></a>
    <?php
}

function layoutPointsHelp(): void
{
    ?>
    <div class="points-help" id="pointsHelpModal" role="dialog" aria-modal="true" aria-labelledby="pointsHelpTitle" hidden>
        <div class="points-help-backdrop" id="pointsHelpBackdrop"></div>
        <div class="points-help-card">
            <button type="button" class="points-help-close" id="pointsHelpClose" aria-label="<?= e(t('common.close')) ?>">&times;</button>
            <h2 class="points-help-title" id="pointsHelpTitle"><?= e(t('points.title')) ?></h2>
            <h3 class="points-help-subtitle"><?= e(t('points.subtitle_why')) ?></h3>
            <ul class="points-help-list">
                <li><strong><?= e(t('points.season_rank')) ?></strong> — <?= e(t('points.season_reset', ['n' => (int) SAISON_DUREE_JOURS])) ?></li>
                <li><strong><?= e(t('points.podium')) ?></strong> — <?= e(t('points.podium_bonus', ['a' => (int) SEASON_PODIUM_BONUS[1], 'b' => (int) SEASON_PODIUM_BONUS[2], 'c' => (int) SEASON_PODIUM_BONUS[3]])) ?></li>
                <li><strong><?= e(t('points.streak')) ?></strong> — <?= e(t('points.streak_desc')) ?></li>
                <li><strong><?= e(t('points.badges')) ?></strong> — <?= e(t('points.badges_desc')) ?></li>
            </ul>
            <div class="points-help-badges" aria-hidden="true">
                <span class="lb-pill lb-pill-gold"><?= e(t('points.1st')) ?></span>
                <span class="lb-pill lb-pill-silver"><?= e(t('points.2nd')) ?></span>
                <span class="lb-pill lb-pill-bronze"><?= e(t('points.3rd')) ?></span>
                <span class="lb-pill lb-pill-fire">×5</span>
            </div>
            <h3 class="points-help-subtitle"><?= e(t('points.subtitle_how')) ?></h3>
            <ul class="points-help-list points-help-list-compact">
                <li><strong>+<?= (int) POINTS_1X2 ?> <?= e(t('common.pt')) ?></strong> — <?= e(t('points.earn_1x2')) ?></li>
                <li><strong>+<?= (int) POINTS_BUTEUR ?> <?= e(t('common.pts')) ?></strong> — <?= e(t('points.earn_scorer')) ?></li>
                <li><strong>+<?= (int) POINTS_SCORE_EXACT ?> <?= e(t('common.pts')) ?></strong> — <?= e(t('points.earn_score')) ?></li>
            </ul>
            <p class="points-help-foot"><?= e(t('points.foot')) ?></p>
        </div>
    </div>
    <script>
    (function () {
        var btn = document.getElementById('pointsHelpBtn');
        var modal = document.getElementById('pointsHelpModal');
        if (!btn || !modal) return;
        function openHelp() {
            modal.hidden = false;
            requestAnimationFrame(function () {
                modal.classList.add('is-open');
            });
        }
        function closeHelp() {
            modal.classList.remove('is-open');
            window.setTimeout(function () {
                modal.hidden = true;
            }, 280);
        }
        btn.addEventListener('click', openHelp);
        btn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openHelp(); }
        });
        var closeBtn = document.getElementById('pointsHelpClose');
        var backdrop = document.getElementById('pointsHelpBackdrop');
        if (closeBtn) closeBtn.addEventListener('click', closeHelp);
        if (backdrop) backdrop.addEventListener('click', closeHelp);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hidden) closeHelp();
        });
    })();
    </script>
    <?php
}

function layoutFlashes(): void
{
    foreach (getFlashes() as $f) {
        echo '<div class="alert alert-' . e($f['type']) . '">' . e($f['message']) . '</div>';
    }
}

function renderTicketPanel(?array $user, array $ticketItems): void
{
    ?>
    <aside class="pronos-ticket ticket-slip" id="pronosTicket">
        <div class="ticket-slip-edge ticket-slip-edge-top" aria-hidden="true"></div>
        <button type="button" class="ticket-mobile-handle" id="ticketMobileHandle" aria-expanded="true" aria-controls="ticketMobileBody" aria-label="<?= e(t('ticket.toggle_aria')) ?>">
            <span class="ticket-handle-bar"></span>
        </button>
        <div class="ticket-slip-header" id="ticketHead">
            <span class="ticket-slip-brand">PROGNOZ</span>
            <div class="ticket-slip-title-row">
                <span class="ticket-head-title"><?= e(t('ticket.your_picks')) ?></span>
                <span class="ticket-head-summary" id="ticketHeadSummary" hidden></span>
                <span class="ticket-count" id="ticketCount">0</span>
            </div>
        </div>
        <div class="ticket-mobile-body" id="ticketMobileBody">
        <div class="ticket-body">
            <p class="ticket-empty" id="ticketEmpty">
                <?php if ($user): ?>
                    <?= e(t('ticket.empty_user')) ?><br>
                    <a href="<?= e(url('account/dashboard.php')) ?>"><?= e(t('ticket.my_space')) ?></a>
                <?php else: ?>
                    <?= e(t('ticket.empty_guest')) ?><br>
                    <a href="<?= e(url('auth/login.php?redirect=index.php')) ?>"><?= e(t('ticket.login_to_save')) ?></a> <?= e(t('ticket.login_suffix')) ?>
                <?php endif; ?>
            </p>
            <ul class="ticket-list" id="ticketList" hidden></ul>
            <div class="ticket-footer" id="ticketFooter" hidden>
                <div class="ticket-slip-tear" aria-hidden="true"></div>
                <div class="ticket-gain">
                    <span><?= e(t('ticket.max_gain')) ?></span>
                    <strong id="ticketGain">+0 <?= e(t('common.pt')) ?></strong>
                </div>
                <?php if ($user): ?>
                    <button type="button" class="btn btn-primary btn-block btn-sm ticket-validate-btn is-hidden" id="ticketValidate">
                        <?= e(t('ticket.validate')) ?>
                    </button>
                <?php else: ?>
                    <a href="<?= e(url('auth/login.php?redirect=index.php')) ?>" class="btn btn-primary btn-block btn-sm ticket-validate-btn is-hidden" id="ticketValidate">
                        <?= e(t('ticket.validate')) ?>
                    </a>
                <?php endif; ?>
                <p class="ticket-note ticket-flash" id="ticketFlash" hidden></p>
            </div>
        </div>
        </div>
        <div class="ticket-slip-edge ticket-slip-edge-bottom" aria-hidden="true"></div>
    </aside>
    <?php
}

/** Ticket enregistré (Mon espace) — paris validés en attente de résultat. */
function renderSavedTicket(array $items, int $gainMax): void
{
    ?>
    <div class="saved-ticket ticket-slip ticket-slip-wide">
        <div class="ticket-slip-edge ticket-slip-edge-top" aria-hidden="true"></div>
        <div class="ticket-slip-header ticket-slip-header-saved">
            <span class="ticket-slip-brand">PROGNOZ</span>
            <div class="ticket-slip-title-row">
                <span class="ticket-head-title"><?= e(t('ticket.my_picks')) ?></span>
                <span class="ticket-count"><?= count($items) ?></span>
            </div>
        </div>
        <div class="ticket-body">
            <?php if (empty($items)): ?>
                <p class="ticket-empty saved-ticket-empty">
                    <?= e(t('ticket.none_pending')) ?><br>
                    <a href="<?= e(url('index.php')) ?>"><?= e(t('ticket.bet_today')) ?></a>
                </p>
            <?php else: ?>
                <ul class="ticket-list saved-ticket-list">
                    <?php foreach ($items as $item): ?>
                    <li class="ticket-item ticket-item-saved">
                        <div class="ticket-item-top">
                            <span class="ticket-sport"><?= e($item['competition']) ?></span>
                            <time class="ticket-date"><?= e($item['date']) ?></time>
                        </div>
                        <div class="ticket-match"><?= e($item['home']) ?> — <?= e($item['away']) ?></div>
                        <div class="ticket-pick">
                            <span class="ticket-type"><?= e($item['market_label']) ?></span>
                            <strong><?= e($item['pick_label']) ?></strong>
                        </div>
                        <div class="ticket-item-foot">
                            <span class="ticket-badge-validated"><?= e(t('common.validated')) ?></span>
                            <span class="ticket-pts">+<?= (int) $item['points'] ?> <?= e(t('common.pt')) ?></span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <div class="ticket-footer saved-ticket-footer">
                    <div class="ticket-slip-tear" aria-hidden="true"></div>
                    <div class="ticket-gain">
                        <span><?= e(t('ticket.max_gain_possible')) ?></span>
                        <strong>+<?= $gainMax ?> <?= e($gainMax > 1 ? t('common.pts') : t('common.pt')) ?></strong>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="ticket-slip-edge ticket-slip-edge-bottom" aria-hidden="true"></div>
    </div>
    <?php
}

function layoutPointToasts(): void
{
    ?>
    <script>
        window.PRONO_API = <?= json_encode(url('api/'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        window.PRONO_DASHBOARD_URL = <?= json_encode(url('account/dashboard.php'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        window.PRONO_COMMUNITIES_URL = <?= json_encode(url('communities/view.php?id='), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        window.PRONO_NOTIF_ICON = <?= json_encode(absoluteUrl('assets/img/apple-touch-icon.svg'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        window.PRONO_SW_URL = <?= json_encode(url('sw-notifications.js'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        window.PRONO_SW_SCOPE = <?= json_encode(publicBasePath(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        window.PRONO_CSRF = <?= json_encode(csrfToken(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    </script>
    <div class="point-toast-stack" id="pointToastStack" aria-live="polite" aria-atomic="true"></div>
    <div class="notify-prompt" id="notifyPrompt" hidden>
        <div class="notify-prompt-inner">
            <p class="notify-prompt-text">
                <i class="fa-regular fa-bell" aria-hidden="true"></i>
                <?= e(t('notify.prompt')) ?>
            </p>
            <div class="notify-prompt-actions">
                <button type="button" class="btn btn-primary btn-sm" id="notifyPromptEnable"><?= e(t('notify.allow')) ?></button>
                <button type="button" class="btn btn-ghost btn-sm" id="notifyPromptDismiss"><?= e(t('notify.later')) ?></button>
            </div>
        </div>
    </div>
    <script src="<?= e(assetUrl('assets/js/notifications.js')) ?>"></script>
    <script src="<?= e(assetUrl('assets/js/point-toast.js')) ?>"></script>
    <script src="<?= e(assetUrl('assets/js/site-notifications.js')) ?>"></script>
    <script>
    (function () {
        var prompt = document.getElementById('notifyPrompt');
        var btnEnable = document.getElementById('notifyPromptEnable');
        var btnDismiss = document.getElementById('notifyPromptDismiss');
        if (!prompt || !window.PrognozNotify) return;

        function refreshPrompt() {
            prompt.hidden = !PrognozNotify.shouldShowPrompt();
        }

        if (btnEnable) {
            btnEnable.addEventListener('click', function () {
                PrognozNotify.requestPermission().then(function () {
                    prompt.hidden = true;
                    PrognozNotify.dismissPrompt();
                });
            });
        }
        if (btnDismiss) {
            btnDismiss.addEventListener('click', function () {
                PrognozNotify.dismissPrompt();
                prompt.hidden = true;
            });
        }
        refreshPrompt();
    })();
    </script>
    <?php
}
