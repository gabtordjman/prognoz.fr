<?php
require __DIR__ . '/../../app/bootstrap.php';
requireAdminLogin();

$pdo = getPDO();
ensureAvatarSchema($pdo);
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        adminFlash('error', 'Session expirée.');
        header('Location: ' . url('admin/users.php'));
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    $qKeep = trim((string) ($_POST['q'] ?? ''));
    $redir = url('admin/users.php') . ($qKeep !== '' ? '?q=' . rawurlencode($qKeep) : '');

    try {
        if ($action === 'grant_points') {
            $target = findUserByPseudo($pdo, trim((string) ($_POST['pseudo'] ?? '')));
            if (!$target) {
                throw new InvalidArgumentException('Pseudo introuvable.');
            }
            $r = grantUserPoints(
                $pdo,
                (int) $target['id'],
                (int) ($_POST['delta'] ?? 0),
                !empty($_POST['to_season'])
            );
            $sign = $r['delta'] > 0 ? '+' . $r['delta'] : (string) $r['delta'];
            adminFlash(
                'success',
                $r['pseudo'] . ' : ' . $sign . ' pt(s)'
                . ($r['season'] ? ' (saison + total)' : ' (total)')
                . ' → ' . $r['points_totaux'] . ' pts totaux.'
            );
            $redir = url('admin/users.php');
        } elseif ($action === 'remove_avatar') {
            $targetId = (int) ($_POST['user_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT id, pseudo, avatar_url FROM users WHERE id = ?');
            $stmt->execute([$targetId]);
            $target = $stmt->fetch();
            if (!$target) {
                throw new InvalidArgumentException('Joueur introuvable.');
            }
            if (empty($target['avatar_url'])) {
                throw new InvalidArgumentException('Pas de photo sur ce compte.');
            }
            removeUserAvatar($pdo, $targetId);
            adminFlash('success', 'Photo retirée pour ' . $target['pseudo'] . '.');
        } elseif ($action === 'set_active') {
            $targetId = (int) ($_POST['user_id'] ?? 0);
            $active = !empty($_POST['actif']) ? 1 : 0;
            $stmt = $pdo->prepare('SELECT id, pseudo FROM users WHERE id = ?');
            $stmt->execute([$targetId]);
            $target = $stmt->fetch();
            if (!$target) {
                throw new InvalidArgumentException('Joueur introuvable.');
            }
            $pdo->prepare('UPDATE users SET actif = ? WHERE id = ?')->execute([$active, $targetId]);
            adminFlash(
                'success',
                $target['pseudo'] . ($active ? ' réactivé.' : ' désactivé (ne peut plus se connecter).')
            );
        } elseif ($action === 'set_mail_opt_out') {
            ensureMailPrefsSchema($pdo);
            $targetId = (int) ($_POST['user_id'] ?? 0);
            $optOut = !empty($_POST['mail_opt_out']) ? 1 : 0;
            $stmt = $pdo->prepare('SELECT id, pseudo FROM users WHERE id = ?');
            $stmt->execute([$targetId]);
            $target = $stmt->fetch();
            if (!$target) {
                throw new InvalidArgumentException('Joueur introuvable.');
            }
            setUserMailOptOut($pdo, $targetId, (bool) $optOut);
            adminFlash(
                'success',
                $target['pseudo'] . ($optOut
                    ? ' désinscrit des e-mails (rappels / bilans).'
                    : ' réinscrit aux e-mails.')
            );
        } elseif ($action === 'reset_password') {
            $targetId = (int) ($_POST['user_id'] ?? 0);
            $pass = (string) ($_POST['new_password'] ?? '');
            if (strlen($pass) < 8) {
                throw new InvalidArgumentException('Mot de passe : 8 caractères minimum.');
            }
            $stmt = $pdo->prepare('SELECT id, pseudo FROM users WHERE id = ?');
            $stmt->execute([$targetId]);
            $target = $stmt->fetch();
            if (!$target) {
                throw new InvalidArgumentException('Joueur introuvable.');
            }
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $targetId]);
            adminFlash('success', 'Mot de passe réinitialisé pour ' . $target['pseudo'] . '.');
        }
    } catch (InvalidArgumentException $e) {
        adminFlash('error', $e->getMessage());
        header('Location: ' . $redir);
        exit;
    } catch (Throwable $e) {
        adminFlash('error', 'Erreur technique.');
        header('Location: ' . $redir);
        exit;
    }

    if (in_array($action, ['grant_points', 'remove_avatar', 'set_active', 'reset_password', 'set_mail_opt_out'], true)) {
        header('Location: ' . $redir);
        exit;
    }
}

$q = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
ensureMailPrefsSchema($pdo);
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $pdo->prepare(
        'SELECT id, pseudo, email, actif, mail_opt_out, points_totaux, avatar_url, created_at
         FROM users
         WHERE pseudo LIKE ? OR email LIKE ?
         ORDER BY pseudo ASC
         LIMIT 40'
    );
    $stmt->execute([$like, $like]);
    $results = $stmt->fetchAll();
}

$avatarUsers = listUsersWithAvatars($pdo, 40);

adminLayoutStart('Joueurs', 'users');
?>
<div class="ops-panel">
    <div class="ops-panel-head">Attribuer / retirer des points</div>
    <div class="ops-panel-body">
        <p class="ops-muted">Bonus, malus, concours. Plancher à 0. Cochez « Saison » pour le classement en cours.</p>
        <form method="post" class="ops-form-row">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="grant_points">
            <input class="ops-input ops-input-lg" name="pseudo" placeholder="Pseudo exact" required maxlength="30">
            <input class="ops-input ops-input-md" type="number" name="delta" value="10" required title="+ ou −">
            <label class="ops-check">
                <input type="checkbox" name="to_season" value="1" checked> Saison
            </label>
            <button type="submit" class="ops-btn ops-btn-primary">Appliquer</button>
        </form>
    </div>
</div>

<div class="ops-panel">
    <div class="ops-panel-head">Rechercher un joueur</div>
    <div class="ops-panel-body">
        <p class="ops-muted">
            Cherche par pseudo ou e-mail. Bouton <strong>Désinscrire mails</strong> :
            plus de rappels / bilans (reset MDP reste possible).
        </p>
        <form method="get" class="ops-form-row" style="margin-bottom:1rem">
            <input class="ops-input ops-input-lg" name="q" value="<?= e($q) ?>" placeholder="Pseudo ou e-mail" required>
            <button type="submit" class="ops-btn ops-btn-ghost">Chercher</button>
        </form>
        <?php if ($q !== '' && empty($results)): ?>
            <p class="ops-muted">Aucun résultat.</p>
        <?php elseif (!empty($results)): ?>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>ID</th>
                        <th>Pseudo</th>
                        <th>E-mail</th>
                        <th>État</th>
                        <th>Mails</th>
                        <th>Pts</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $u):
                        $thumb = avatarPublicUrl($u['avatar_url'] ?? null);
                        $isActive = !empty($u['actif']);
                        $mailOff = !empty($u['mail_opt_out']);
                    ?>
                    <tr>
                        <td>
                            <?php if ($thumb): ?>
                                <img src="<?= e($thumb) ?>" alt="" class="ops-avatar-thumb ops-avatar-thumb-sm" width="36" height="36">
                            <?php else: ?>
                                <span class="ops-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="ops-mono"><?= (int) $u['id'] ?></td>
                        <td><?= e($u['pseudo']) ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td>
                            <span class="ops-badge <?= $isActive ? 'ops-badge--ok' : 'ops-badge--off' ?>">
                                <?= $isActive ? 'actif' : 'off' ?>
                            </span>
                        </td>
                        <td>
                            <span class="ops-badge <?= $mailOff ? 'ops-badge--off' : 'ops-badge--ok' ?>">
                                <?= $mailOff ? 'désinscrit' : 'oui' ?>
                            </span>
                        </td>
                        <td class="ops-mono"><?= (int) $u['points_totaux'] ?></td>
                        <td>
                            <div class="ops-actions">
                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="set_mail_opt_out">
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <input type="hidden" name="mail_opt_out" value="<?= $mailOff ? '0' : '1' ?>">
                                    <input type="hidden" name="q" value="<?= e($q) ?>">
                                    <button type="submit" class="ops-btn ops-btn-ghost ops-btn-sm">
                                        <?= $mailOff ? 'Réinscrire mails' : 'Désinscrire mails' ?>
                                    </button>
                                </form>
                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="set_active">
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <input type="hidden" name="actif" value="<?= $isActive ? '0' : '1' ?>">
                                    <input type="hidden" name="q" value="<?= e($q) ?>">
                                    <button type="submit" class="ops-btn ops-btn-ghost ops-btn-sm">
                                        <?= $isActive ? 'Désactiver' : 'Réactiver' ?>
                                    </button>
                                </form>
                                <?php if ($thumb): ?>
                                <form method="post" onsubmit="return confirm('Retirer la photo ?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="remove_avatar">
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <input type="hidden" name="q" value="<?= e($q) ?>">
                                    <button type="submit" class="ops-btn ops-btn-ghost ops-btn-danger ops-btn-sm">Photo</button>
                                </form>
                                <?php endif; ?>
                            </div>
                            <form method="post" class="ops-form-row" style="margin-top:0.35rem"
                                  onsubmit="return confirm('Réinitialiser le mot de passe de <?= e($u['pseudo']) ?> ?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <input type="hidden" name="q" value="<?= e($q) ?>">
                                <input class="ops-input ops-input-md" type="text" name="new_password" placeholder="Nouveau MDP" minlength="8" required>
                                <button type="submit" class="ops-btn ops-btn-sm">Reset MDP</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="ops-panel">
    <div class="ops-panel-head">Photos de profil</div>
    <div class="ops-panel-body">
        <p class="ops-muted">Retirez une photo offensante. Le joueur revient aux initiales.</p>
        <?php if (empty($avatarUsers)): ?>
            <p class="ops-muted">Aucune photo uploadée pour l’instant.</p>
        <?php else: ?>
        <div class="ops-avatar-grid">
            <?php foreach ($avatarUsers as $au):
                $src = avatarPublicUrl($au['avatar_url'] ?? null);
                if ($src === null) {
                    continue;
                }
            ?>
            <div class="ops-avatar-card">
                <img src="<?= e($src) ?>" alt="" class="ops-avatar-thumb" width="72" height="72" loading="lazy">
                <div class="ops-avatar-meta">
                    <strong><?= e($au['pseudo']) ?></strong>
                    <span class="ops-mono">#<?= (int) $au['id'] ?></span>
                </div>
                <form method="post" onsubmit="return confirm(<?= json_encode('Retirer la photo de ' . $au['pseudo'] . ' ?', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) ?>);">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="remove_avatar">
                    <input type="hidden" name="user_id" value="<?= (int) $au['id'] ?>">
                    <button type="submit" class="ops-btn ops-btn-ghost ops-btn-danger">Retirer</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php adminLayoutEnd(); ?>
