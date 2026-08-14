<?php
require __DIR__ . '/../../app/bootstrap.php';

requireAdminGate();

if (adminLoggedIn()) {
    header('Location: ' . url('admin/dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        $error = 'Session expirée, réessayez.';
    } else {
        $result = adminAttemptLogin(
            trim((string) ($_POST['username'] ?? '')),
            (string) ($_POST['password'] ?? '')
        );
        if ($result === true) {
            header('Location: ' . url('admin/dashboard.php'));
            exit;
        }
        $error = is_string($result) ? $result : 'Échec de connexion.';
    }
}

adminLayoutLogin('Connexion', $error);
?>
        <form method="post" autocomplete="off">
            <?= csrfField() ?>
            <div class="ops-field">
                <label class="ops-label" for="username">Nom d’utilisateur</label>
                <input class="ops-input" id="username" name="username" required maxlength="64"
                       value="<?= e($_POST['username'] ?? '') ?>" autocomplete="username">
            </div>
            <div class="ops-field">
                <label class="ops-label" for="password">Mot de passe</label>
                <input class="ops-input" id="password" type="password" name="password" required
                       minlength="12" autocomplete="current-password">
            </div>
            <button type="submit" class="ops-btn ops-btn-primary">Se connecter</button>
        </form>
        <p class="ops-login-hint">Service Prognoz · accès réservé</p>
    </div>
</body>
</html>
