<?php
require __DIR__ . '/../../app/bootstrap.php';

$pdo  = getPDO();
$user = currentUser($pdo);
?>
<!DOCTYPE html>
<html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
<head>
    <?php layoutHead(t('legal.privacy.h1'), false, seoPage('privacy')); ?>
</head>
<body>
<?php layoutTopbar($user); ?>

<main class="app-main app-main-wide legal-page">
    <h1 class="page-title"><?= e(t('legal.privacy.h1')) ?></h1>
    <p class="page-sub"><?= e(legalUpdatedLabel()) ?></p>
    <?php if (currentLang() === 'en'): ?>
    <p class="page-sub"><?= e(t('legal.note')) ?></p>
    <?php endif; ?>
    <?php legalCrossNav('privacy'); ?>

    <article class="panel legal-panel">
        <div class="panel-body legal-body">
            <?= legalDocumentHtml('privacy') ?>
        </div>
    </article>
</main>

<?php layoutFooter(); ?>
</body>
</html>
