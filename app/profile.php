<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/** URL publique du profil joueur. */
function userProfileUrl(int $userId): string
{
    return url('account/profile.php?id=' . max(0, $userId));
}

/**
 * Profil public (pas d'e-mail).
 * @return array{id:int,pseudo:string,points_totaux:int,serie_en_cours:int,avatar_url:?string,created_at:?string}|null
 */
function fetchPublicUserProfile(PDO $pdo, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT id, pseudo, points_totaux, serie_en_cours, avatar_url, created_at
         FROM users WHERE id = ? AND actif = 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * Relation d'amitié vue depuis $viewerId vers $targetId.
 * @return 'self'|'none'|'friends'|'pending_out'|'pending_in'
 */
function friendshipStatusWith(PDO $pdo, int $viewerId, int $targetId): string
{
    if ($viewerId <= 0 || $targetId <= 0) {
        return 'none';
    }
    if ($viewerId === $targetId) {
        return 'self';
    }
    $row = friendshipBetween($pdo, $viewerId, $targetId);
    if (!$row) {
        return 'none';
    }
    if ($row['statut'] === 'accepte') {
        return 'friends';
    }
    if ($row['statut'] === 'en_attente') {
        return (int) $row['user_id'] === $viewerId ? 'pending_out' : 'pending_in';
    }

    return 'none';
}

/** Avatar (+ pseudo optionnel) cliquable vers le profil. */
function renderUserProfileLink(int $userId, string $pseudo, string $size = 'sm', bool $showPseudo = true, ?string $avatarUrl = null): void
{
    if ($userId <= 0) {
        renderUserAvatar($pseudo, $size, $avatarUrl);
        if ($showPseudo) {
            echo ' <span class="user-link-pseudo">' . e($pseudo) . '</span>';
        }
        return;
    }
    ?>
    <a href="<?= e(userProfileUrl($userId)) ?>" class="user-link" title="Voir le profil de <?= e($pseudo) ?>">
        <?php renderUserAvatar($pseudo, $size, $avatarUrl); ?>
        <?php if ($showPseudo): ?>
            <span class="user-link-pseudo"><?= e($pseudo) ?></span>
        <?php endif; ?>
    </a>
    <?php
}
