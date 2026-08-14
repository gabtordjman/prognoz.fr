<?php
require __DIR__ . '/../../app/bootstrap.php';

$pdo  = getPDO();
$user = currentUser($pdo);
?>
<!DOCTYPE html>
<html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
<head>
    <?php layoutHead(t('howto.title'), false, seoPage('howto')); ?>
</head>
<body>
<?php layoutTopbar($user, 'matchs'); ?>

<main class="app-main app-main-wide legal-page">
    <h1 class="page-title"><?= e(t('howto.title')) ?></h1>

    <div class="howto-grid">
        <article class="howto-card">
            <span class="howto-num">1</span>
            <h2><?= e(t('howto.s1_title')) ?></h2>
            <p><?= e(t('howto.s1_text')) ?></p>
        </article>
        <article class="howto-card">
            <span class="howto-num">2</span>
            <h2><?= e(t('howto.s2_title')) ?></h2>
            <p><?= e(t('howto.s2_text')) ?></p>
        </article>
        <article class="howto-card">
            <span class="howto-num">3</span>
            <h2><?= e(t('howto.s3_title')) ?></h2>
            <p><?= e(t('howto.s3_text')) ?></p>
        </article>
        <article class="howto-card">
            <span class="howto-num">4</span>
            <h2><?= e(t('howto.s4_title')) ?></h2>
            <p><?= e(t('howto.s4_text')) ?></p>
        </article>
    </div>

    <p class="howto-cta">
        <?php if ($user): ?>
            <a href="<?= e(url('index.php')) ?>" class="btn btn-primary"><?= e(t('howto.cta_matches')) ?></a>
        <?php else: ?>
            <a href="<?= e(url('auth/register.php')) ?>" class="btn btn-primary"><?= e(t('howto.cta_register')) ?></a>
            <a href="<?= e(url('index.php')) ?>" class="btn btn-ghost"><?= e(t('howto.cta_browse')) ?></a>
        <?php endif; ?>
    </p>
</main>

<?php layoutFooter(); ?>
</body>
</html>
