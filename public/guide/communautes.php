<?php
require __DIR__ . '/../../app/bootstrap.php';

$pdo  = getPDO();
$user = currentUser($pdo);
?>
<!DOCTYPE html>
<html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
<head>
    <?php layoutHead(t('guide.communities.title'), false, seoPage('guide_communities')); ?>
</head>
<body>
<?php layoutTopbar($user); ?>

<main class="app-main app-main-wide legal-page">
    <h1 class="page-title"><?= e(t('guide.communities.title')) ?></h1>
    <p class="page-sub"><?= e(guideUpdatedLabel()) ?></p>
    <?php guideCrossNav('communities'); ?>

    <article class="panel legal-panel">
        <div class="panel-body legal-body">
            <?= guideDocumentHtml('communities') ?>
        </div>
    </article>
</main>

<?php layoutFooter(); ?>
</body>
</html>
