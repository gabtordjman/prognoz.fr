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
    } elseif (!rateLimitAllow('auth_register:' . clientIp(), 5, 3600)) {
        $erreur = t('common.rate_limited');
    } else {
        $pseudo   = trim($_POST['pseudo'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        try {
            $pdo = getPDO();
            $acceptedLegal = !empty($_POST['privacy_accept']) && !empty($_POST['cgu_accept']);
            $userId = registerUser($pdo, $pseudo, $email, $password, $acceptedLegal);
            loginUser($pdo, $email, $password);
            flash('success', t('auth.register.welcome'));

            if (!empty($_SESSION['pending_invite'])) {
                header('Location: ' . url('communities/invite.php?code=' . urlencode($_SESSION['pending_invite'])));
                exit;
            }
            redirectAfterAuth();
        } catch (InvalidArgumentException $e) {
            $erreur = $e->getMessage();
        } catch (Throwable $e) {
            $erreur = t('common.error_retry');
        }
    }
}

if (!empty($_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect'];
}
?>
<!DOCTYPE html>
<html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
<head>
    <?php layoutHead(t('auth.register.title'), false, seoPage('register')); ?>
</head>
<body>
<?php layoutBetaRibbon(); ?>
<?php layoutBetaWelcome(); ?>
<div class="auth-shell">
    <div class="auth-wrap">
        <?php layoutAuthBack(); ?>
        <div class="auth-card">
        <div class="auth-brand"><?= e(APP_NAME) ?></div>
        <p class="auth-sub"><?= e(t('auth.register.sub')) ?></p>

        <?php if ($erreur): ?><div class="alert alert-error"><?= e($erreur) ?></div><?php endif; ?>
        <?php if (!empty($_SESSION['pending_invite'])): ?>
            <div class="alert alert-info"><?= e(t('auth.register.invite_info')) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= csrfField() ?>
            <div class="field-group">
                <label class="field-label"><?= e(t('auth.register.pseudo')) ?></label>
                <input type="text" name="pseudo" class="field-input" required minlength="3" maxlength="30" value="<?= e($_POST['pseudo'] ?? '') ?>">
            </div>
            <div class="field-group">
                <label class="field-label"><?= e(t('auth.register.email')) ?></label>
                <input type="email" name="email" class="field-input" required value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="field-group">
                <label class="field-label"><?= e(t('auth.register.password')) ?></label>
                <input type="password" name="password" class="field-input" required minlength="8">
            </div>
            <div class="auth-legal">
                <label class="field-check">
                    <input type="checkbox" name="privacy_accept" value="1" required <?= !empty($_POST['privacy_accept']) ? 'checked' : '' ?>>
                    <span class="field-check-text">
                        <?= t('auth.register.accept_privacy', ['link' => '<a href="' . e(url('legal/confidentialite.php')) . '" target="_blank" rel="noopener">' . e(t('auth.register.privacy_link')) . '</a>']) ?>
                    </span>
                </label>
                <label class="field-check">
                    <input type="checkbox" name="cgu_accept" value="1" required <?= !empty($_POST['cgu_accept']) ? 'checked' : '' ?>>
                    <span class="field-check-text">
                        <?= t('auth.register.accept_terms', ['link' => '<a href="' . e(url('legal/cgu.php')) . '" target="_blank" rel="noopener">' . e(t('auth.register.terms_link')) . '</a>']) ?>
                    </span>
                </label>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><?= e(t('auth.register.submit')) ?></button>
        </form>
        <p class="auth-links">
            <?= e(t('auth.register.has_account')) ?> <a href="<?= e(url('auth/login.php')) ?>" class="auth-links-strong"><?= e(t('auth.register.login')) ?></a>
        </p>
        <?php layoutAuthFooter(); ?>
        </div>
    </div>
</div>
</body>
</html>
