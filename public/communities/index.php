<?php
require __DIR__ . '/../../app/bootstrap.php';
requireLogin();

$pdo = getPDO();
$user = currentUser($pdo);
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'creer_communaute') {
    if (!csrfCheck()) {
        $erreur = t('common.session_expired');
    } else {
        $nom = trim($_POST['nom'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (mb_strlen($nom) < 3 || mb_strlen($nom) > 60) {
            $erreur = t('com.name_length');
        } elseif (!encryptionConfigured()) {
            $erreur = t('com.encrypt_missing');
        } else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO communities (nom, description, est_generale, createur_id) VALUES (?, ?, 0, ?)'
                );
                $stmt->execute([
                    encryptSensitive($nom),
                    $description !== '' ? encryptSensitive($description) : '',
                    $user['id'],
                ]);
                $communityId = (int) $pdo->lastInsertId();

                $stmt = $pdo->prepare(
                    'INSERT INTO community_members (community_id, user_id, role) VALUES (?, ?, "moderateur")'
                );
                $stmt->execute([$communityId, $user['id']]);

                do {
                    $code = strtoupper(bin2hex(random_bytes(8)));
                    $check = $pdo->prepare('SELECT COUNT(*) FROM community_invites WHERE code_invite = ?');
                    $check->execute([$code]);
                } while ((int) $check->fetchColumn() > 0);

                $stmt = $pdo->prepare(
                    'INSERT INTO community_invites (community_id, code_invite, cree_par, usages_max) VALUES (?, ?, ?, 0)'
                );
                $stmt->execute([$communityId, $code, $user['id']]);

                $pdo->commit();
                flash('success', t('com.created'));
                header('Location: ' . url('communities/view.php?id=' . $communityId));
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $erreur = t('com.create_error');
            }
        }
    }
}

$stmt = $pdo->prepare(
    "SELECT c.id, c.nom, c.description, c.est_generale,
            (SELECT COUNT(*) FROM community_members cm2 WHERE cm2.community_id = c.id) AS nb_membres
     FROM community_members cm
     INNER JOIN communities c ON c.id = cm.community_id
     WHERE cm.user_id = ?
     ORDER BY c.est_generale DESC, c.id ASC"
);
$stmt->execute([$user['id']]);
$mesCommunautes = array_map(function (array $row): array {
    return decryptCommunityRow($row, false);
}, $stmt->fetchAll());
?>
<!DOCTYPE html>
<html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
<head>
    <?php layoutHead(t('com.title'), true, seoPage('communities')); ?>
</head>
<body>

<?php layoutTopbar($user, 'communities'); ?>

<div class="app-main">
    <?php layoutFlashes(); ?>
    <?php if ($erreur): ?><div class="alert alert-error"><?= e($erreur) ?></div><?php endif; ?>

    <h2 class="page-title"><?= e(t('com.title')) ?></h2>

    <div class="grid-2">
        <div class="panel">
            <div class="panel-head"><?= e(t('com.mine')) ?></div>
            <div class="panel-body">
                <?php foreach ($mesCommunautes as $c): ?>
                    <a href="<?= e(url('communities/view.php?id=' . (int) $c['id'])) ?>" class="community-card">
                        <div>
                            <div class="cc-name"><?= e(!empty($c['est_generale']) ? t('com.general_name') : $c['nom']) ?><?php if ($c['est_generale']): ?><span class="community-badge-generale"><?= e(t('com.general')) ?></span><?php endif; ?></div>
                            <div class="cc-meta"><?= e(t('com.members_paren', ['n' => (int) $c['nb_membres']])) ?></div>
                        </div>
                        <i class="fa-solid fa-chevron-right" style="color:var(--muted);"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><?= e(t('com.create_private')) ?></div>
            <div class="panel-body">
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="creer_communaute">
                    <div class="field-group">
                        <label class="field-label"><?= e(t('com.name')) ?></label>
                        <input type="text" name="nom" class="field-input" required minlength="3" maxlength="60" placeholder="<?= e(t('com.name_placeholder')) ?>">
                    </div>
                    <div class="field-group">
                        <label class="field-label"><?= e(t('com.desc_optional')) ?></label>
                        <textarea name="description" class="field-textarea" rows="2" maxlength="255"></textarea>
                    </div>
                    <button type="submit" class="btn btn-accent btn-block"><?= e(t('com.create_submit')) ?></button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php layoutFooter(); ?>
</body>
</html>
