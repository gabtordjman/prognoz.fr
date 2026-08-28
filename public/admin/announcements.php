<?php
require __DIR__ . '/../../app/bootstrap.php';
requireAdminLogin();

$pdo = getPDO();
ensureSiteAnnouncementsSchema($pdo);

$editId = (int) ($_GET['edit'] ?? 0);
$editing = $editId > 0 ? fetchSiteAnnouncement($pdo, $editId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        adminFlash('error', 'Session expirée.');
        header('Location: ' . url('admin/announcements.php'));
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $payload = [
                'title'     => $_POST['title'] ?? '',
                'body'      => $_POST['body'] ?? '',
                'published' => !empty($_POST['published']),
            ];
            if ($id > 0) {
                updateSiteAnnouncement($pdo, $id, $payload);
                adminFlash('success', 'Annonce #' . $id . ' mise à jour.');
            } else {
                $newId = createSiteAnnouncement($pdo, $payload);
                adminFlash(
                    'success',
                    'Annonce #' . $newId . ' créée'
                    . (empty($payload['published']) ? ' (brouillon)' : ' et publiée') . '.'
                );
            }
            header('Location: ' . url('admin/announcements.php'));
            exit;
        }
        if ($action === 'publish') {
            $id = (int) ($_POST['id'] ?? 0);
            $ev = fetchSiteAnnouncement($pdo, $id);
            if (!$ev) {
                throw new InvalidArgumentException('Annonce introuvable.');
            }
            $publish = empty($ev['published']);
            setSiteAnnouncementPublished($pdo, $id, $publish);
            adminFlash('success', $publish ? 'Annonce publiée.' : 'Annonce dépubliée.');
            header('Location: ' . url('admin/announcements.php'));
            exit;
        }
        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            deleteSiteAnnouncement($pdo, $id);
            adminFlash('success', 'Annonce #' . $id . ' supprimée.');
            header('Location: ' . url('admin/announcements.php'));
            exit;
        }
        adminFlash('error', 'Action inconnue.');
    } catch (Throwable $e) {
        adminFlash('error', $e->getMessage());
    }
    header('Location: ' . url('admin/announcements.php' . ($editId ? '?edit=' . $editId : '')));
    exit;
}

$all = listSiteAnnouncements($pdo, false);
$form = [
    'id'        => $editing ? (int) $editing['id'] : 0,
    'title'     => $editing ? (string) $editing['title'] : '',
    'body'      => $editing ? (string) $editing['body'] : '',
    'published' => $editing ? !empty($editing['published']) : true,
];

adminLayoutStart('Annonces', 'announcements');
?>
<div class="ops-panel">
    <div class="ops-panel-head"><?= $form['id'] ? 'Modifier #' . (int) $form['id'] : 'Nouvelle annonce' ?></div>
    <div class="ops-panel-body">
        <p class="ops-muted" style="margin-top:0">
            Publiée = point sur le micro + liste des news au clic (sans popup auto).
        </p>
        <form method="post" class="ops-event-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

            <label class="ops-field">
                <span>Titre</span>
                <input class="ops-input" name="title" required maxlength="120" value="<?= e($form['title']) ?>"
                       placeholder="ex. Nouveau : bonus de série">
            </label>

            <label class="ops-field">
                <span>Message</span>
                <textarea class="ops-input" name="body" required maxlength="2000" rows="5"
                          placeholder="Texte court pour les joueurs…"><?= e($form['body']) ?></textarea>
            </label>

            <label class="ops-check">
                <input type="checkbox" name="published" value="1" <?= $form['published'] ? 'checked' : '' ?>>
                Publier tout de suite
            </label>

            <div class="ops-actions">
                <button type="submit" class="ops-btn ops-btn-primary"><?= $form['id'] ? 'Enregistrer' : 'Créer' ?></button>
                <?php if ($form['id']): ?>
                    <a class="ops-btn" href="<?= e(url('admin/announcements.php')) ?>">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="ops-panel">
    <div class="ops-panel-head">Toutes les annonces (<?= count($all) ?>)</div>
    <div class="ops-panel-body" style="overflow-x:auto">
        <?php if ($all === []): ?>
            <p class="ops-muted" style="margin:0">Aucune annonce pour l’instant.</p>
        <?php else: ?>
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Titre</th>
                        <th>Statut</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($all as $row): ?>
                    <?php
                    $id = (int) $row['id'];
                    $isPub = !empty($row['published']);
                    $when = $row['published_at'] ?? $row['created_at'] ?? '';
                    ?>
                    <tr>
                        <td><?= $id ?></td>
                        <td>
                            <strong><?= e((string) $row['title']) ?></strong>
                            <div class="ops-muted" style="font-size:0.8rem;max-width:28rem">
                                <?= e(mb_strimwidth((string) $row['body'], 0, 120, '…')) ?>
                            </div>
                        </td>
                        <td><?= $isPub ? 'Publiée' : 'Brouillon' ?></td>
                        <td class="ops-muted"><?= $when !== '' ? e(formatMatchWhen((string) $when)) : '—' ?></td>
                        <td>
                            <div class="ops-inline-actions">
                            <a class="ops-btn ops-btn-sm" href="<?= e(url('admin/announcements.php?edit=' . $id)) ?>">Éditer</a>
                            <form method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="publish">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <button type="submit" class="ops-btn ops-btn-sm">
                                    <?= $isPub ? 'Dépublier' : 'Publier' ?>
                                </button>
                            </form>
                            <form method="post"
                                  onsubmit="return confirm('Supprimer cette annonce ?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <button type="submit" class="ops-btn ops-btn-sm ops-btn-danger">Suppr.</button>
                            </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php
adminLayoutEnd();
