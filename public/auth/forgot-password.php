<?php
require __DIR__ . '/../../app/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . url('account/settings.php'));
    exit;
}

$sent = false;
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        $erreur = t('common.session_expired');
    } elseif (!rateLimitAllow('auth_forgot:' . clientIp(), 5, 3600)) {
        $erreur = t('common.rate_limited');
    } else {
        $email = trim($_POST['email'] ?? '');
        try {
            $pdo = getPDO();
            requestPasswordReset($pdo, $email);
            $sent = true;
        } catch (Throwable $e) {
            error_log('Prognoz forgot-password: ' . $e->getMessage());
            $erreur = $e instanceof RuntimeException
                ? $e->getMessage()
                : t('auth.forgot.tech_error');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
<head>
    <?php layoutHead(t('auth.forgot.title'), false, seoPage('login')); ?>
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
            <div class="auth-sub"><?= e(t('auth.forgot.sub2')) ?></div>

            <?php if ($sent): ?>
                <div class="alert alert-success">
                    <?= e(t('auth.forgot.sent_long')) ?>
                </div>
                <a href="<?= e(url('auth/login.php')) ?>" class="btn btn-ghost btn-block"><?= e(t('auth.forgot.back')) ?></a>
            <?php else: ?>
                <?php if ($erreur): ?><div class="alert alert-error"><?= e($erreur) ?></div><?php endif; ?>
                <form method="post">
                    <?= csrfField() ?>
                    <div class="field-group">
                        <label class="field-label"><?= e(t('auth.forgot.email_label')) ?></label>
                        <input type="email" name="email" class="field-input" required autofocus
                               value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block"><?= e(t('auth.forgot.submit')) ?></button>
                </form>
            <?php endif; ?>
            <?php layoutAuthFooter(); ?>
        </div>
    </div>
</div>
</body>
</html>
