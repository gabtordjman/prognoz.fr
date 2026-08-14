<?php
require __DIR__ . '/../../app/bootstrap.php';
requireAdminLogin();

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        adminFlash('error', 'Session expirée.');
        header('Location: ' . url('admin/messages.php'));
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    $msgId = (int) ($_POST['message_id'] ?? 0);
    $communityId = (int) ($_POST['community_id'] ?? 0);
    $includeDeleted = !empty($_POST['include_deleted']);
    $q = trim((string) ($_POST['q'] ?? ''));
    $redir = url('admin/messages.php') . '?' . http_build_query(array_filter([
        'community_id' => $communityId ?: null,
        'include_deleted' => $includeDeleted ? '1' : null,
        'q' => $q !== '' ? $q : null,
    ]));

    try {
        if ($msgId < 1) {
            throw new InvalidArgumentException('Message invalide.');
        }
        if ($action === 'soft_delete') {
            $stmt = $pdo->prepare('UPDATE community_messages SET supprime = 1 WHERE id = ?');
            $stmt->execute([$msgId]);
            if ($stmt->rowCount() < 1) {
                throw new InvalidArgumentException('Message introuvable.');
            }
            adminFlash('success', 'Message masqué sur le site.');
        } elseif ($action === 'restore') {
            $stmt = $pdo->prepare('UPDATE community_messages SET supprime = 0 WHERE id = ?');
            $stmt->execute([$msgId]);
            if ($stmt->rowCount() < 1) {
                throw new InvalidArgumentException('Message introuvable.');
            }
            adminFlash('success', 'Message restauré.');
        } elseif ($action === 'hard_delete') {
            $stmt = $pdo->prepare('DELETE FROM community_messages WHERE id = ?');
            $stmt->execute([$msgId]);
            if ($stmt->rowCount() < 1) {
                throw new InvalidArgumentException('Message introuvable ou déjà effacé.');
            }
            adminFlash('success', 'Message effacé définitivement.');
        }
    } catch (InvalidArgumentException $e) {
        adminFlash('error', $e->getMessage());
    } catch (Throwable $e) {
        adminFlash('error', 'Erreur technique.');
    }
    header('Location: ' . $redir);
    exit;
}

$communityId = (int) ($_GET['community_id'] ?? 0);
$includeDeleted = !empty($_GET['include_deleted']);
$q = trim((string) ($_GET['q'] ?? ''));

$communitiesRaw = $pdo->query(
    'SELECT c.id, c.nom, c.est_generale,
            (SELECT COUNT(*) FROM community_messages m
             WHERE m.community_id = c.id AND m.supprime = 0) AS msg_count
     FROM communities c
     ORDER BY c.est_generale DESC, c.id ASC'
)->fetchAll();

$communities = [];
foreach ($communitiesRaw as $row) {
    $dec = decryptCommunityRow($row, false);
    $communities[] = [
        'id' => (int) $row['id'],
        'nom' => (string) ($dec['nom'] ?? ('#' . $row['id'])),
        'est_generale' => !empty($row['est_generale']),
        'msg_count' => (int) $row['msg_count'],
    ];
}

if ($communityId < 1 && $communities !== []) {
    $communityId = (int) $communities[0]['id'];
}

$messages = [];
if ($communityId > 0) {
    $sql = 'SELECT m.id, m.community_id, m.user_id, m.contenu, m.supprime, m.created_at, u.pseudo
            FROM community_messages m
            INNER JOIN users u ON u.id = m.user_id
            WHERE m.community_id = ?';
    $params = [$communityId];
    if (!$includeDeleted) {
        $sql .= ' AND m.supprime = 0';
    }
    $sql .= ' ORDER BY m.created_at DESC LIMIT 200';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $search = mb_strtolower($q);
    foreach ($rows as $row) {
        $row = decryptMessageRow($row);
        $contenu = (string) ($row['contenu'] ?? '');
        if ($search !== '') {
            $hay = mb_strtolower(($row['pseudo'] ?? '') . ' ' . $contenu);
            if (!str_contains($hay, $search)) {
                continue;
            }
        }
        $messages[] = $row;
    }
}

adminLayoutStart('Messages', 'messages');
?>
<div class="ops-panel">
    <div class="ops-panel-head">Modération chat</div>
    <div class="ops-panel-body">
        <form method="get" class="ops-form-row" style="margin-bottom:0.85rem">
            <select class="ops-select" name="community_id">
                <?php foreach ($communities as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $communityId === (int) $c['id'] ? 'selected' : '' ?>>
                        <?= e($c['nom']) ?> (<?= (int) $c['msg_count'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <input class="ops-input ops-input-lg" name="q" value="<?= e($q) ?>" placeholder="Filtrer pseudo / texte">
            <label class="ops-check">
                <input type="checkbox" name="include_deleted" value="1" <?= $includeDeleted ? 'checked' : '' ?>>
                Inclure masqués
            </label>
            <button type="submit" class="ops-btn ops-btn-primary">Afficher</button>
        </form>
        <p class="ops-muted">Masquer = caché sur le site. Effacer = suppression définitive en BDD.</p>

        <?php if ($messages === []): ?>
            <p class="ops-muted">Aucun message.</p>
        <?php else: ?>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Quand</th>
                        <th>Auteur</th>
                        <th>Message</th>
                        <th>État</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $m): ?>
                    <tr>
                        <td class="ops-mono"><?= e(substr((string) $m['created_at'], 0, 16)) ?></td>
                        <td><?= e($m['pseudo'] ?? '?') ?></td>
                        <td class="ops-msg-preview"><?= e((string) ($m['contenu'] ?? '')) ?></td>
                        <td>
                            <?php if (!empty($m['supprime'])): ?>
                                <span class="ops-badge ops-badge--off">masqué</span>
                            <?php else: ?>
                                <span class="ops-badge ops-badge--ok">visible</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="ops-actions">
                                <?php if (empty($m['supprime'])): ?>
                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="soft_delete">
                                    <input type="hidden" name="message_id" value="<?= (int) $m['id'] ?>">
                                    <input type="hidden" name="community_id" value="<?= $communityId ?>">
                                    <input type="hidden" name="q" value="<?= e($q) ?>">
                                    <?php if ($includeDeleted): ?><input type="hidden" name="include_deleted" value="1"><?php endif; ?>
                                    <button type="submit" class="ops-btn ops-btn-ghost ops-btn-sm">Masquer</button>
                                </form>
                                <?php else: ?>
                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="restore">
                                    <input type="hidden" name="message_id" value="<?= (int) $m['id'] ?>">
                                    <input type="hidden" name="community_id" value="<?= $communityId ?>">
                                    <input type="hidden" name="q" value="<?= e($q) ?>">
                                    <input type="hidden" name="include_deleted" value="1">
                                    <button type="submit" class="ops-btn ops-btn-ghost ops-btn-sm">Restaurer</button>
                                </form>
                                <?php endif; ?>
                                <form method="post" onsubmit="return confirm('Effacer définitivement ce message ?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="hard_delete">
                                    <input type="hidden" name="message_id" value="<?= (int) $m['id'] ?>">
                                    <input type="hidden" name="community_id" value="<?= $communityId ?>">
                                    <input type="hidden" name="q" value="<?= e($q) ?>">
                                    <?php if ($includeDeleted): ?><input type="hidden" name="include_deleted" value="1"><?php endif; ?>
                                    <button type="submit" class="ops-btn ops-btn-danger ops-btn-sm">Effacer</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php adminLayoutEnd(); ?>
