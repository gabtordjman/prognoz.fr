<?php
require __DIR__ . '/../../app/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . url('index.php'));
    exit;
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        $erreur = t('common.session_expired');
    } elseif (!rateLimitAllow('auth_login:' . clientIp(), 12, 900)) {
        $erreur = t('common.rate_limited');
    } else {
        $identifiant = trim($_POST['identifiant'] ?? '');
        $password    = $_POST['password'] ?? '';

        $pdo = getPDO();
        if (loginUser($pdo, $identifiant, $password)) {
            if (!empty($_SESSION['pending_invite'])) {
                header('Location: ' . url('communities/invite.php?code=' . urlencode($_SESSION['pending_invite'])));
                exit;
            }
            redirectAfterAuth();
        }
        $erreur = t('auth.login.bad_creds');
    }
}

if (!empty($_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect'];
}
?>
<!DOCTYPE html>
<html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
<head>
    <?php layoutHead(t('auth.login.title'), false, seoPage('login')); ?>
</head>
<body>
<?php layoutBetaRibbon(); ?>
<?php layoutBetaWelcome(); ?>
<div class="auth-shell">
    <div class="auth-wrap">
        <?php layoutAuthBack(); ?>
        <div class="auth-card">
        <div class="auth-brand">
            <?php renderBrandMark(); ?>
            <span class="sr-only"><?= e(APP_NAME) ?></span>
        </div>
        <p class="auth-sub"><?= e(t('auth.login.sub')) ?></p>

        <?php if ($erreur): ?><div class="alert alert-error"><?= e($erreur) ?></div><?php endif; ?>
        <?php if (!empty($_SESSION['pending_invite'])): ?>
            <div class="alert alert-info"><?= e(t('auth.login.invite_info')) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= csrfField() ?>
            <div class="field-group">
                <label class="field-label"><?= e(t('auth.login.ident')) ?></label>
                <input type="text" name="identifiant" class="field-input" required autofocus>
            </div>
            <div class="field-group">
                <label class="field-label"><?= e(t('auth.login.password')) ?></label>
                <input type="password" name="password" class="field-input" required>
                <p class="auth-forgot">
                    <a href="<?= e(url('auth/forgot-password.php')) ?>"><?= e(t('auth.login.forgot')) ?></a>
                </p>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><?= e(t('auth.login.submit')) ?></button>
        </form>
        <p class="auth-links">
            <?= e(t('auth.login.no_account')) ?> <a href="<?= e(url('auth/register.php')) ?>" class="auth-links-strong"><?= e(t('auth.login.create')) ?></a>
        </p>
        <?php layoutAuthFooter(); ?>
        </div>
    </div>
</div>
</body>
</html>
