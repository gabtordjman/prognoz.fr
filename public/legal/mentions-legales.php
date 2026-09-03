<?php
require __DIR__ . '/../../app/bootstrap.php';

$pdo  = getPDO();
$user = currentUser($pdo);
$pub  = legalPublisher();
$host = legalHost();
?>
<!DOCTYPE html>
<html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
<head>
    <?php layoutHead(t('legal.mentions.h1'), false, seoPage('mentions')); ?>
</head>
<body>
<?php layoutTopbar($user); ?>

<main class="app-main app-main-wide legal-page">
    <h1 class="page-title"><?= e(t('legal.mentions.h1')) ?></h1>
    <p class="page-sub"><?= e(legalUpdatedLabel()) ?></p>
    <?php if (currentLang() === 'en'): ?>
    <p class="page-sub"><?= e(t('legal.note')) ?></p>
    <?php endif; ?>
    <?php legalCrossNav('mentions'); ?>

    <article class="panel legal-panel">
        <div class="panel-body legal-body">
            <p><?= e(t('legal.mentions.intro', ['app' => APP_NAME])) ?></p>

            <h2><?= e(t('legal.mentions.editor_title')) ?></h2>
            <ul>
                <li>
                    <strong><?= e(t('legal.mentions.site')) ?></strong>
                    <?= e($pub['url']) ?>
                    (<?= e(APP_NAME) ?>)
                </li>
                <li>
                    <strong><?= e(t('legal.mentions.kind')) ?></strong>
                    <?= e($pub['kind'] === 'company' ? t('legal.mentions.kind_company') : t('legal.mentions.kind_individual')) ?>
                </li>
                <li>
                    <strong><?= e(t('legal.mentions.publisher')) ?></strong>
                    <?php if ($pub['name'] !== ''): ?>
                        <?= e($pub['name']) ?>
                    <?php else: ?>
                        <?= e(t('legal.mentions.publisher_unnamed', ['app' => APP_NAME])) ?>
                    <?php endif; ?>
                </li>
                <li>
                    <strong><?= e(t('legal.mentions.address')) ?></strong>
                    <?php if ($pub['address'] !== ''): ?>
                        <?= e($pub['address']) ?>
                    <?php else: ?>
                        <?= t('legal.mentions.address_on_request', ['email' => '<a href="mailto:' . e($pub['email']) . '">' . e($pub['email']) . '</a>']) ?>
                    <?php endif; ?>
                </li>
                <?php if ($pub['phone'] !== ''): ?>
                <li>
                    <strong><?= e(t('legal.mentions.phone')) ?></strong>
                    <?= e($pub['phone']) ?>
                </li>
                <?php endif; ?>
                <?php if ($pub['siret'] !== ''): ?>
                <li><strong>SIRET</strong> <?= e($pub['siret']) ?></li>
                <?php endif; ?>
                <?php if ($pub['rcs'] !== ''): ?>
                <li><strong>RCS</strong> <?= e($pub['rcs']) ?></li>
                <?php endif; ?>
                <?php if ($pub['capital'] !== ''): ?>
                <li>
                    <strong><?= e(t('legal.mentions.capital')) ?></strong>
                    <?= e($pub['capital']) ?>
                </li>
                <?php endif; ?>
                <?php if ($pub['vat'] !== ''): ?>
                <li>
                    <strong><?= e(t('legal.mentions.vat')) ?></strong>
                    <?= e($pub['vat']) ?>
                </li>
                <?php endif; ?>
                <li>
                    <strong><?= e(t('legal.mentions.director')) ?></strong>
                    <?= e($pub['director'] !== '' ? $pub['director'] : t('legal.mentions.director_same')) ?>
                </li>
                <li>
                    <strong><?= e(t('legal.mentions.contact')) ?></strong>
                    <a href="mailto:<?= e($pub['email']) ?>"><?= e($pub['email']) ?></a>
                </li>
            </ul>
            <?php if ($pub['name'] === '' || $pub['address'] === ''): ?>
            <p><?= e(t('legal.mentions.identity_note')) ?></p>
            <?php endif; ?>

            <h2><?= e(t('legal.mentions.host_title')) ?></h2>
            <?php if ($host['name'] !== ''): ?>
            <ul>
                <li>
                    <strong><?= e(t('legal.mentions.host_name')) ?></strong>
                    <?= e($host['name']) ?>
                </li>
                <?php if ($host['address'] !== ''): ?>
                <li>
                    <strong><?= e(t('legal.mentions.address')) ?></strong>
                    <?= e($host['address']) ?>
                </li>
                <?php endif; ?>
                <?php if ($host['phone'] !== ''): ?>
                <li>
                    <strong><?= e(t('legal.mentions.phone')) ?></strong>
                    <?= e($host['phone']) ?>
                </li>
                <?php endif; ?>
                <?php if ($host['website'] !== ''): ?>
                <li>
                    <strong><?= e(t('legal.mentions.host_web')) ?></strong>
                    <a href="<?= e($host['website']) ?>" rel="noopener noreferrer"><?= e($host['website']) ?></a>
                </li>
                <?php endif; ?>
            </ul>
            <?php else: ?>
            <p><?= t('legal.mentions.host_on_request', ['email' => '<a href="mailto:' . e($pub['email']) . '">' . e($pub['email']) . '</a>']) ?></p>
            <?php endif; ?>
            <p><?= e(t('legal.mentions.cdn')) ?></p>

            <h2><?= e(t('legal.mentions.ip_title')) ?></h2>
            <p><?= e(t('legal.mentions.ip_text', ['app' => APP_NAME])) ?></p>
            <p><?= e(t('legal.mentions.brands')) ?></p>

            <h2><?= e(t('legal.mentions.personal_title')) ?></h2>
            <p>
                <?= t('legal.mentions.personal_text', [
                    'privacy' => '<a href="' . e(url('legal/confidentialite.php')) . '">' . e(t('legal.privacy.h1')) . '</a>',
                    'cgu'     => '<a href="' . e(url('legal/cgu.php')) . '">' . e(t('legal.cgu.h1')) . '</a>',
                ]) ?>
            </p>

            <h2><?= e(t('legal.mentions.law_title')) ?></h2>
            <p><?= e(t('legal.mentions.law_text')) ?></p>
        </div>
    </article>
</main>

<?php layoutFooter(); ?>
</body>
</html>
