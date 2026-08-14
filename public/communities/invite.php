<?php
require __DIR__ . '/../../app/bootstrap.php';

$code = trim($_GET['code'] ?? '');

if ($code === '') {
    header('Location: ' . url('index.php'));
    exit;
}

if (!rateLimitAllow('invite_lookup:' . clientIp(), 40, 300)) {
    http_response_code(429);
    ?>
    <!DOCTYPE html>
    <html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
    <head><?php layoutHead(t('com.invite_title'), true, seoPage('invite')); ?></head>
    <body>
    <?php layoutTopbar(currentUser(getPDO())); ?>
    <div class="app-main">
        <div class="alert alert-error"><?= e(t('com.rate_limit')) ?></div>
        <a href="<?= e(url('index.php')) ?>" class="btn btn-primary"><?= e(t('common.back_matches')) ?></a>
    </div>
    </body></html>
    <?php
    exit;
}

$pdo = getPDO();

$stmt = $pdo->prepare(
    "SELECT ci.*, c.nom AS community_nom
     FROM community_invites ci
     INNER JOIN communities c ON c.id = ci.community_id
     WHERE ci.code_invite = ?"
);
$stmt->execute([$code]);
$invite = decryptCommunityRow($stmt->fetch() ?: []);

if (!$invite || empty($invite['id'])) {
    ?>
    <!DOCTYPE html>
    <html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
    <head><?php layoutHead(t('com.invite_title'), true, seoPage('invite')); ?></head>
    <body>
    <?php layoutTopbar(currentUser($pdo)); ?>
    <div class="app-main">
        <div class="alert alert-error"><?= e(t('com.invite_missing')) ?></div>
        <a href="<?= e(url('index.php')) ?>" class="btn btn-primary"><?= e(t('common.back_matches')) ?></a>
    </div>
    </body></html>
    <?php
    exit;
}

$expire = $invite['expire_le'] && strtotime($invite['expire_le']) < time();
$epuise = $invite['usages_max'] > 0 && $invite['usages_actuels'] >= $invite['usages_max'];

if ($expire || $epuise) {
    ?>
    <!DOCTYPE html>
    <html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
    <head><?php layoutHead(t('com.invite_title'), true, seoPage('invite')); ?></head>
    <body>
    <?php layoutTopbar(currentUser($pdo)); ?>
    <div class="app-main">
        <div class="alert alert-error"><?= e(t('com.invite_expired')) ?></div>
        <a href="<?= e(url('index.php')) ?>" class="btn btn-primary"><?= e(t('common.back_matches')) ?></a>
    </div>
    </body></html>
    <?php
    exit;
}

if (empty($_SESSION['user_id'])) {
    $_SESSION['pending_invite'] = $code;
    ?>
    <!DOCTYPE html>
    <html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
    <head><?php layoutHead(t('com.invite_title'), false, seoPage('invite', [
        'title'       => t('com.invite_seo_title', ['name' => $invite['community_nom']]),
        'description' => t('com.invite_seo_desc', ['name' => $invite['community_nom']]),
        'path'        => 'communities/invite.php?code=' . rawurlencode($code),
    ])); ?></head>
    <body>
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-brand"><?= e(APP_NAME) ?></div>
            <div class="auth-sub"><?= e(t('com.invite_guest_sub')) ?><br><strong><?= e($invite['community_nom']) ?></strong></div>
            <a href="<?= e(url('auth/register.php')) ?>" class="btn btn-primary btn-block" style="margin-bottom:0.6rem;"><?= e(t('com.invite_register_join')) ?></a>
            <a href="<?= e(url('auth/login.php')) ?>" class="btn btn-ghost btn-block"><?= e(t('com.invite_have_account')) ?></a>
            <p style="text-align:center; margin-top:1rem; font-size:0.85rem;">
                <a href="<?= e(url('index.php')) ?>" style="color:var(--muted);"><?= e(t('com.invite_skip')) ?></a>
            </p>
        </div>
    </div>
    </body></html>
    <?php
    exit;
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT id FROM community_members WHERE community_id = ? AND user_id = ?');
$stmt->execute([$invite['community_id'], $userId]);

if (!$stmt->fetch()) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO community_members (community_id, user_id, role) VALUES (?, ?, "membre")'
        );
        $stmt->execute([$invite['community_id'], $userId]);

        $stmt = $pdo->prepare('UPDATE community_invites SET usages_actuels = usages_actuels + 1 WHERE id = ?');
        $stmt->execute([$invite['id']]);

        $pdo->commit();
        flash('success', t('com.invite_joined', ['name' => $invite['community_nom']]));
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', t('com.invite_fail'));
    }
} else {
    flash('info', t('com.invite_already'));
}

unset($_SESSION['pending_invite']);
header('Location: ' . url('communities/view.php?id=' . (int) $invite['community_id']));
exit;
