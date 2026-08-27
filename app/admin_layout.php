<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/**
 * @param 'dashboard'|'scores'|'users'|'ops'|'seasons'|'messages'|'reports'|'events' $active
 */
function adminLayoutStart(string $title, string $active = 'dashboard'): void
{
    $user = (string) ($_SESSION['admin_username'] ?? 'admin');
    header('X-Robots-Tag: noindex, nofollow');

    $navGroups = [
        [
            'label' => 'Accueil',
            'items' => [
                ['id' => 'dashboard', 'label' => 'Vue d’ensemble', 'href' => url('admin/dashboard.php')],
                ['id' => 'reports', 'label' => 'Rapports e-mail', 'href' => url('admin/reports.php')],
            ],
        ],
        [
            'label' => 'Matchs & résultats',
            'items' => [
                ['id' => 'scores', 'label' => 'Résultats & scores manuels', 'href' => url('admin/scores.php')],
                ['id' => 'ops', 'label' => 'Sync API & crédits', 'href' => url('admin/ops.php')],
            ],
        ],
        [
            'label' => 'Communauté',
            'items' => [
                ['id' => 'messages', 'label' => 'Modération chat', 'href' => url('admin/messages.php')],
                ['id' => 'users', 'label' => 'Joueurs & points', 'href' => url('admin/users.php')],
            ],
        ],
        [
            'label' => 'Compétition',
            'items' => [
                ['id' => 'seasons', 'label' => 'Saisons', 'href' => url('admin/seasons.php')],
                ['id' => 'events', 'label' => 'Événements', 'href' => url('admin/events.php')],
            ],
        ],
    ];
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#0f1a14">
    <title><?= e($title) ?> · PROGNOZ Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,400..800;1,400..800&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(assetUrl('assets/css/admin.css')) ?>">
</head>
<body class="ops-body">
<div class="ops-shell">
    <header class="ops-topbar">
        <div class="ops-topbar-row">
            <a class="ops-brand" href="<?= e(url('admin/dashboard.php')) ?>">Prognoz</a>
            <span class="ops-topbar-meta">Admin · v<?= e(APP_VERSION) ?> · <?= e($user) ?></span>
        </div>
    </header>
    <div class="ops-layout">
        <aside class="ops-sidebar" aria-label="Menu admin">
            <?php foreach ($navGroups as $group): ?>
                <div class="ops-nav-group">
                    <div class="ops-nav-group-label"><?= e($group['label']) ?></div>
                    <?php foreach ($group['items'] as $item): ?>
                        <a class="ops-nav-link <?= $active === $item['id'] ? 'is-active' : '' ?>"
                           href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <div class="ops-nav-group ops-nav-group--foot">
                <a class="ops-nav-link" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">Ouvrir le site</a>
                <a class="ops-nav-link" href="<?= e(url('admin/logout.php')) ?>">Déconnexion</a>
            </div>
        </aside>
        <main class="ops-main">
            <h1 class="ops-title"><?= e($title) ?></h1>
            <div class="ops-content">
    <?php
    $flash = adminTakeFlash();
    if ($flash): ?>
                <div class="ops-alert ops-alert--<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif;
}

function adminLayoutEnd(): void
{
    ?>
            </div>
        </main>
    </div>
    <footer class="ops-footerbar">
        <span>Prognoz Admin · v<?= e(APP_VERSION) ?></span>
        <a href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">Site public</a>
    </footer>
</div>
</body>
</html>
    <?php
}

function adminLayoutLogin(string $title, string $error = ''): void
{
    header('X-Robots-Tag: noindex, nofollow');
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#0f1a14">
    <title><?= e($title) ?> · PROGNOZ Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,400..800;1,400..800&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(assetUrl('assets/css/admin.css')) ?>">
</head>
<body class="ops-body ops-body--login">
    <div class="ops-login-card">
        <div class="ops-login-brand">
            <strong>Prognoz</strong>
            <small>Connexion administrateur</small>
        </div>
        <?php if ($error !== ''): ?>
            <div class="ops-alert ops-alert--error"><?= e($error) ?></div>
        <?php endif; ?>
    <?php
}
