<?php
require __DIR__ . '/../../app/bootstrap.php';

$pdo  = getPDO();
$user = currentUser($pdo);
$catalog = guideCatalog();
?>
<!DOCTYPE html>
<html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
<head>
    <?php layoutHead(t('guide.hub.title'), false, seoPage('guide_hub')); ?>
</head>
<body>
<?php layoutTopbar($user); ?>

<main class="app-main app-main-wide legal-page">
    <h1 class="page-title"><?= e(t('guide.hub.title')) ?></h1>
    <p class="page-sub"><?= e(t('guide.hub.lead')) ?></p>
    <?php guideCrossNav('hub'); ?>

    <div class="guide-hub-grid">
        <?php foreach ($catalog as $item): ?>
            <article class="howto-card guide-hub-card">
                <h2><a href="<?= e($item['url']) ?>"><?= e($item['title']) ?></a></h2>
                <p><?= e($item['desc']) ?></p>
                <p class="guide-hub-more">
                    <a href="<?= e($item['url']) ?>"><?= e(t('guide.hub.read')) ?></a>
                </p>
            </article>
        <?php endforeach; ?>
    </div>

    <p class="howto-cta">
        <a href="<?= e(url('legal/comment-ca-marche.php')) ?>" class="btn btn-ghost"><?= e(t('nav.howto')) ?></a>
        <?php if ($user): ?>
            <a href="<?= e(url('index.php')) ?>" class="btn btn-primary"><?= e(t('howto.cta_matches')) ?></a>
        <?php else: ?>
            <a href="<?= e(url('auth/register.php')) ?>" class="btn btn-primary"><?= e(t('howto.cta_register')) ?></a>
        <?php endif; ?>
    </p>
</main>

<?php layoutFooter(); ?>
</body>
</html>
