<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/**
 * Inscrit un nouvel utilisateur et le fait rejoindre automatiquement
 * la communauté "Générale". Lève une InvalidArgumentException avec
 * un message lisible en cas de problème (pseudo/email déjà pris...).
 */
function registerUser(PDO $pdo, string $pseudo, string $email, string $password, bool $privacyAccepted = false): int
{
    $pseudo = trim($pseudo);
    $email  = strtolower(trim($email));

    if (!$privacyAccepted) {
        throw new InvalidArgumentException(t('auth.err.accept_legal'));
    }

    if (mb_strlen($pseudo) < 3 || mb_strlen($pseudo) > 30) {
        throw new InvalidArgumentException(t('auth.err.pseudo_length'));
    }
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $pseudo)) {
        throw new InvalidArgumentException(t('auth.err.pseudo_chars'));
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException(t('auth.err.email_invalid'));
    }
    if (mb_strlen($password) < 8) {
        throw new InvalidArgumentException(t('auth.err.password_length'));
    }

    ensurePredictionHistorySchema($pdo);
    ensureMailPrefsSchema($pdo);

    $stmt = $pdo->prepare('SELECT id FROM users WHERE pseudo = ? OR email = ?');
    $stmt->execute([$pseudo, $email]);
    if ($stmt->fetch()) {
        throw new InvalidArgumentException(t('auth.err.taken'));
    }

    $lang = currentLang();
    if (!in_array($lang, APP_LANGS, true)) {
        $lang = guessMailLangFromEmail($email);
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO users (pseudo, email, password_hash, privacy_accepted_at, preferred_lang) VALUES (?, ?, ?, NOW(), ?)'
        );
        $stmt->execute([$pseudo, $email, password_hash($password, PASSWORD_DEFAULT), $lang]);
        $userId = (int) $pdo->lastInsertId();

        // Rejoint automatiquement la communauté "Générale"
        $stmtGenerale = $pdo->query('SELECT id FROM communities WHERE est_generale = 1 LIMIT 1');
        $generale = $stmtGenerale->fetch();
        if ($generale) {
            $stmt = $pdo->prepare(
                'INSERT INTO community_members (community_id, user_id, role) VALUES (?, ?, "membre")'
            );
            $stmt->execute([$generale['id'], $userId]);
        }

        $pdo->commit();
        return $userId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Tente une connexion. Retourne true si succès (session ouverte),
 * false sinon (identifiants incorrects).
 */
function loginUser(PDO $pdo, string $identifiant, string $password): bool
{
    $identifiant = trim($identifiant);

    $stmt = $pdo->prepare(
        'SELECT id, pseudo, password_hash FROM users WHERE (email = ? OR pseudo = ?) AND actif = 1'
    );
    $stmt->execute([strtolower($identifiant), $identifiant]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['pseudo']  = $user['pseudo'];
    unset($_SESSION['_last_seen_touch']);
    try {
        touchUserLastSeen($pdo, (int) $user['id']);
    } catch (Throwable $e) {
        // ignore
    }

    return true;
}

/** Redirige vers la connexion si personne n'est authentifié */
function requireLogin(?string $returnTo = null): void
{
    if (empty($_SESSION['user_id'])) {
        if ($returnTo) {
            $_SESSION['redirect_after_login'] = $returnTo;
        }
        header('Location: ' . url('auth/login.php'));
        exit;
    }
    try {
        touchUserLastSeen(getPDO(), (int) $_SESSION['user_id']);
    } catch (Throwable $e) {
        // ignore
    }
}

/** Redirection interne sûre (bloque les URLs externes). */
function safeRedirectUrl(string $dest, string $fallback = 'index.php'): string
{
    $dest = trim($dest);
    if ($dest === '') {
        return url($fallback);
    }

    if (preg_match('#^https?://#i', $dest)) {
        $appUrl = rtrim(env('APP_URL', ''), '/');
        if ($appUrl !== '' && str_starts_with($dest, $appUrl)) {
            $path = substr($dest, strlen($appUrl));
            $dest = ltrim($path, '/');
        } else {
            return url($fallback);
        }
    }

    $dest = ltrim($dest, '/');
    if ($dest === '' || str_contains($dest, '..') || str_contains($dest, '//')) {
        return url($fallback);
    }

    return url($dest);
}

/** URL de redirection après connexion/inscription */
function redirectAfterAuth(): void
{
    $dest = $_SESSION['redirect_after_login'] ?? 'index.php';
    unset($_SESSION['redirect_after_login']);
    header('Location: ' . safeRedirectUrl((string) $dest));
    exit;
}

/** Récupère la ligne complète de l'utilisateur connecté (ou null) */
function currentUser(PDO $pdo): ?array
{
    static $user = null;
    if (!empty($GLOBALS['__prognoz_clear_current_user'])) {
        $user = null;
        unset($GLOBALS['__prognoz_clear_current_user']);
    }
    if ($user !== null) {
        return $user;
    }
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

/** Invalide le cache request de currentUser() (après update profil / avatar). */
function clearCurrentUserCache(): void
{
    $GLOBALS['__prognoz_clear_current_user'] = true;
}

function requestPasswordReset(PDO $pdo, string $email): bool
{
    ensurePredictionHistorySchema($pdo);
    ensureMailPrefsSchema($pdo);
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT id, pseudo, email, preferred_lang FROM users WHERE LOWER(email) = ? AND actif = 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        return true;
    }

    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);
    $resetUrl = absoluteUrl('auth/reset-password.php?token=' . urlencode($token));

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([(int) $user['id']]);
        $pdo->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 24 HOUR))'
        )->execute([(int) $user['id'], $hash]);

        if (!sendPasswordResetMail($user['email'], $user, $resetUrl)) {
            $pdo->rollBack();
            $detail = lastMailError();
            throw new RuntimeException(
                t('auth.err.mail_fail')
                . ($detail !== '' ? ' ' . $detail : ' Vérifiez SMTP (mot de passe entre guillemets dans .env).')
            );
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof RuntimeException) {
            throw $e;
        }
        throw new RuntimeException(t('auth.err.reset_fail'));
    }

    return true;
}

function resetPasswordWithToken(PDO $pdo, string $token, string $newPassword): void
{
    ensurePredictionHistorySchema($pdo);
    if (mb_strlen($newPassword) < 8) {
        throw new InvalidArgumentException(t('auth.err.password_length'));
    }

    $hash = hash('sha256', trim($token));
    $stmt = $pdo->prepare(
        'SELECT t.id, t.user_id FROM password_reset_tokens t
         WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at > UTC_TIMESTAMP()
         LIMIT 1'
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new InvalidArgumentException(t('auth.err.link_invalid'));
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET password_hash = ?, password_changed_at = UTC_TIMESTAMP() WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $row['user_id']]);
        $pdo->prepare('UPDATE password_reset_tokens SET used_at = UTC_TIMESTAMP() WHERE id = ?')
            ->execute([(int) $row['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Délai minimum entre deux changements de pseudo ou de mot de passe (Paramètres). */
const PROFILE_CHANGE_COOLDOWN_DAYS = 21;

function profileChangeCooldownMessage(?string $changedAt, string $actionLabel): ?string
{
    if ($changedAt === null || $changedAt === '') {
        return null;
    }
    $nextAllowed = strtotime($changedAt) + PROFILE_CHANGE_COOLDOWN_DAYS * 86400;
    $remaining = $nextAllowed - time();
    if ($remaining <= 0) {
        return null;
    }
    $days = (int) ceil($remaining / 86400);

    return t('auth.err.cooldown', [
        'action' => $actionLabel,
        'n'      => $days,
        's'      => $days > 1 ? 's' : '',
    ]);
}

function validatePseudoFormat(string $pseudo): string
{
    $pseudo = trim($pseudo);
    if (mb_strlen($pseudo) < 3 || mb_strlen($pseudo) > 30) {
        throw new InvalidArgumentException(t('auth.err.pseudo_length'));
    }
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $pseudo)) {
        throw new InvalidArgumentException(t('auth.err.pseudo_chars'));
    }

    return $pseudo;
}

/** Changement de pseudo depuis Paramètres (mot de passe requis). */
function changeUserPseudo(PDO $pdo, int $userId, string $newPseudo, string $password): void
{
    ensurePredictionHistorySchema($pdo);
    $newPseudo = validatePseudoFormat($newPseudo);

    $user = currentUser($pdo);
    if (!$user || (int) $user['id'] !== $userId) {
        throw new InvalidArgumentException(t('auth.err.session_invalid'));
    }
    if (!password_verify($password, $user['password_hash'])) {
        throw new InvalidArgumentException(t('auth.err.password_wrong'));
    }

    $cooldown = profileChangeCooldownMessage($user['pseudo_changed_at'] ?? null, t('auth.action.change_pseudo'));
    if ($cooldown !== null) {
        throw new InvalidArgumentException($cooldown);
    }

    if ($newPseudo === $user['pseudo']) {
        throw new InvalidArgumentException(t('auth.err.pseudo_same'));
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE pseudo = ? AND id != ?');
    $stmt->execute([$newPseudo, $userId]);
    if ($stmt->fetch()) {
        throw new InvalidArgumentException(t('auth.err.pseudo_taken'));
    }

    $pdo->prepare('UPDATE users SET pseudo = ?, pseudo_changed_at = UTC_TIMESTAMP() WHERE id = ?')
        ->execute([$newPseudo, $userId]);
    $_SESSION['pseudo'] = $newPseudo;
}

/** Changement de mot de passe depuis Paramètres (utilisateur connecté). */
function changeUserPassword(PDO $pdo, int $userId, string $currentPassword, string $newPassword, string $confirmPassword): void
{
    ensurePredictionHistorySchema($pdo);
    if ($newPassword !== $confirmPassword) {
        throw new InvalidArgumentException(t('auth.err.password_mismatch'));
    }
    if (mb_strlen($newPassword) < 8) {
        throw new InvalidArgumentException(t('auth.err.password_length'));
    }

    $user = currentUser($pdo);
    if (!$user || (int) $user['id'] !== $userId) {
        throw new InvalidArgumentException(t('auth.err.session_invalid'));
    }
    if (!password_verify($currentPassword, $user['password_hash'])) {
        throw new InvalidArgumentException(t('auth.err.password_current_wrong'));
    }
    if (password_verify($newPassword, $user['password_hash'])) {
        throw new InvalidArgumentException(t('auth.err.password_same'));
    }

    $cooldown = profileChangeCooldownMessage($user['password_changed_at'] ?? null, t('auth.action.change_password'));
    if ($cooldown !== null) {
        throw new InvalidArgumentException($cooldown);
    }

    $pdo->prepare('UPDATE users SET password_hash = ?, password_changed_at = UTC_TIMESTAMP() WHERE id = ?')
        ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
}

function deleteUserAccount(PDO $pdo, int $userId, string $confirmPseudo, string $password): void
{
    $user = currentUser($pdo);
    if (!$user || (int) $user['id'] !== $userId) {
        throw new InvalidArgumentException(t('auth.err.session_invalid'));
    }
    if ($user['pseudo'] !== trim($confirmPseudo)) {
        throw new InvalidArgumentException(t('auth.err.confirm_pseudo'));
    }
    if (!password_verify($password, $user['password_hash'])) {
        throw new InvalidArgumentException(t('auth.err.password_wrong'));
    }

    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
