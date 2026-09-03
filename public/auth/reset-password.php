<?php
require __DIR__ . '/../../app/bootstrap.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$done = false;
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        $erreur = t('common.session_expired');
    } elseif (!rateLimitAllow('auth_reset:' . clientIp(), 8, 3600)) {
        $erreur = t('common.rate_limited');
    } else {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';
        if ($password !== $confirm) {
            $erreur = t('auth.reset.mismatch');
        } else {
            try {
                $pdo = getPDO();
                resetPasswordWithToken($pdo, $token, $password);
                $done = true;
            } catch (InvalidArgumentException $e) {
                $erreur = $e->getMessage();
            } catch (Throwable $e) {
                $erreur = t('auth.reset.error');
            }
        }
    }
}

if ($token === '' && !$done) {
    $erreur = t('auth.reset.invalid_link');
}
?>
<!DOCTYPE html>
<html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
<head>
    <?php layoutHead(t('auth.reset.title'), false, seoPage('login')); ?>
</head>
<body>
<?php layoutBetaRibbon(); ?>
<div class="auth-shell">
    <div class="auth-wrap">
        <?php layoutAuthBack('auth/login.php', t('auth.forgot.back')); ?>
        <div class="auth-card">
            <div class="auth-brand">
                <?php renderBrandMark(); ?>
                <span class="sr-only"><?= e(APP_NAME) ?></span>
            </div>

            <?php if ($done): ?>
                <div class="alert alert-success"><?= e(t('auth.reset.success')) ?></div>
                <a href="<?= e(url('auth/login.php')) ?>" class="btn btn-primary btn-block"><?= e(t('auth.login.submit')) ?></a>
            <?php elseif ($erreur && $token === ''): ?>
                <div class="alert alert-error"><?= e($erreur) ?></div>
                <a href="<?= e(url('auth/forgot-password.php')) ?>" class="btn btn-primary btn-block"><?= e(t('auth.reset.request_new')) ?></a>
            <?php else: ?>
                <div class="auth-sub"><?= e(t('auth.reset.choose')) ?></div>
                <?php if ($erreur): ?><div class="alert alert-error"><?= e($erreur) ?></div><?php endif; ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <div class="field-group">
                        <label class="field-label"><?= e(t('auth.reset.password')) ?></label>
                        <input type="password" name="password" class="field-input" required minlength="8">
                    </div>
                    <div class="field-group">
                        <label class="field-label"><?= e(t('auth.reset.confirm_label')) ?></label>
                        <input type="password" name="password_confirm" class="field-input" required minlength="8">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block"><?= e(t('auth.reset.submit')) ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
