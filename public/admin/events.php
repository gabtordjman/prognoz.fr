<?php
require __DIR__ . '/../../app/bootstrap.php';
requireAdminLogin();

$pdo = getPDO();
ensureSiteEventsSchema($pdo);

$editId = (int) ($_GET['edit'] ?? 0);
$editing = $editId > 0 ? fetchSiteEvent($pdo, $editId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        adminFlash('error', 'Session expirée.');
        header('Location: ' . url('admin/events.php'));
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $payload = [
                'title'      => $_POST['title'] ?? '',
                'message'    => $_POST['message'] ?? '',
                'type'       => $_POST['type'] ?? '',
                'theme'      => $_POST['theme'] ?? 'default',
                'starts_at'  => $_POST['starts_at'] ?? '',
                'ends_at'    => $_POST['ends_at'] ?? '',
                'enabled'    => !empty($_POST['enabled']),
                'published'  => !empty($_POST['published']),
                'multiplier' => $_POST['multiplier'] ?? '2',
                'sport'      => $_POST['sport'] ?? '',
            ];
            if ($id > 0) {
                updateSiteEvent($pdo, $id, $payload);
                $msg = 'Événement #' . $id . ' mis à jour.';
                $ev = fetchSiteEvent($pdo, $id);
                if ($ev && !empty($_POST['notify_push']) && siteEventIsLive($ev)) {
                    $msg .= ' · ' . formatSiteEventNotifyFlash(notifySiteEventPush($pdo, $ev));
                }
                adminFlash('success', $msg);
            } else {
                $newId = createSiteEvent($pdo, $payload);
                $msg = 'Événement #' . $newId . ' créé'
                    . (empty($payload['published']) ? ' (brouillon)' : '') . '.';
                $ev = fetchSiteEvent($pdo, $newId);
                $wantNotify = !isset($_POST['notify_push']) || !empty($_POST['notify_push']);
                if ($ev && $wantNotify && siteEventIsLive($ev)) {
                    $msg .= ' · ' . formatSiteEventNotifyFlash(notifySiteEventPush($pdo, $ev));
                }
                adminFlash('success', $msg);
            }
            header('Location: ' . url('admin/events.php'));
            exit;
        }
        if ($action === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            $ev = fetchSiteEvent($pdo, $id);
            if (!$ev) {
                throw new InvalidArgumentException('Événement introuvable.');
            }
            $enable = empty($ev['enabled']);
            setSiteEventEnabled($pdo, $id, $enable);
            $msg = $enable ? 'Événement activé.' : 'Événement désactivé.';
            if ($enable) {
                $ev = fetchSiteEvent($pdo, $id);
                if ($ev && siteEventIsLive($ev)) {
                    $msg .= ' · ' . formatSiteEventNotifyFlash(notifySiteEventPush($pdo, $ev));
                }
            }
            adminFlash('success', $msg);
            header('Location: ' . url('admin/events.php'));
            exit;
        }
        if ($action === 'publish') {
            $id = (int) ($_POST['id'] ?? 0);
            $ev = fetchSiteEvent($pdo, $id);
            if (!$ev) {
                throw new InvalidArgumentException('Événement introuvable.');
            }
            $publish = empty($ev['published']);
            setSiteEventPublished($pdo, $id, $publish);
            $msg = $publish ? 'Événement publié (visible joueurs).' : 'Remis en brouillon.';
            if ($publish) {
                $ev = fetchSiteEvent($pdo, $id);
                if ($ev && siteEventIsLive($ev) && !empty($_POST['notify_on_publish'])) {
                    $msg .= ' · ' . formatSiteEventNotifyFlash(notifySiteEventPush($pdo, $ev));
                }
            }
            adminFlash('success', $msg);
            header('Location: ' . url('admin/events.php'));
            exit;
        }
        if ($action === 'notify') {
            $id = (int) ($_POST['id'] ?? 0);
            $ev = fetchSiteEvent($pdo, $id);
            if (!$ev) {
                throw new InvalidArgumentException('Événement introuvable.');
            }
            if (!siteEventIsLive($ev)) {
                throw new InvalidArgumentException(
                    'L’événement doit être activé et dans sa fenêtre de dates pour notifier.'
                );
            }
            adminFlash('success', formatSiteEventNotifyFlash(notifySiteEventPush($pdo, $ev)));
            header('Location: ' . url('admin/events.php'));
            exit;
        }
        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            deleteSiteEvent($pdo, $id);
            adminFlash('success', 'Événement supprimé.');
            header('Location: ' . url('admin/events.php'));
            exit;
        }
    } catch (InvalidArgumentException $e) {
        adminFlash('error', $e->getMessage());
        header('Location: ' . url('admin/events.php') . ($editId ? '?edit=' . $editId : ''));
        exit;
    } catch (Throwable $e) {
        adminFlash('error', 'Erreur technique.');
        header('Location: ' . url('admin/events.php'));
        exit;
    }
}

$events = listSiteEvents($pdo, 50);
$active = getActiveSiteEvents($pdo);
$types = siteEventTypeCatalog();
$themes = siteEventThemeOptions();

$cfg = $editing ? decodeSiteEventConfig($editing['config_json'] ?? null) : [];
$form = [
    'id'         => $editing ? (int) $editing['id'] : 0,
    'title'      => $editing['title'] ?? '',
    'message'    => $editing['message'] ?? '',
    'type'       => $editing['type'] ?? 'points_multiplier',
    'theme'      => $editing['theme'] ?? 'double',
    'starts_at'  => $editing ? matchDatetimeLocalValue((string) $editing['starts_at']) : matchDatetimeLocalValue(gmdate('Y-m-d H:i:s')),
    'ends_at'    => $editing ? matchDatetimeLocalValue((string) $editing['ends_at']) : matchDatetimeLocalValue(gmdate('Y-m-d H:i:s', time() + 2 * 86400)),
    'enabled'    => $editing ? !empty($editing['enabled']) : true,
    'published'  => $editing ? !empty($editing['published']) : false,
    'multiplier' => isset($cfg['multiplier']) ? (string) $cfg['multiplier'] : '2',
    'sport'      => (string) ($cfg['sport'] ?? 'soccer'),
];

adminLayoutStart('Événements', 'events');
?>
<div class="ops-panel">
    <div class="ops-panel-head">Événements en cours</div>
    <div class="ops-panel-body">
        <?php if ($active === []): ?>
            <p class="ops-muted" style="margin:0">Aucun événement actif. Les joueurs voient le thème normal.</p>
        <?php else: ?>
            <ul class="ops-muted" style="margin:0;padding-left:1.2rem">
                <?php foreach ($active as $ev): ?>
                    <li>
                        <strong><?= e((string) $ev['title']) ?></strong>
                        — <?= e(siteEventTypeLabel((string) $ev['type'])) ?>
                        · jusqu’à <?= e(formatMatchWhen((string) $ev['ends_at'])) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="ops-panel" id="form">
    <div class="ops-panel-head"><?= $form['id'] ? 'Modifier #' . (int) $form['id'] : 'Créer un événement' ?></div>
    <div class="ops-panel-body">
        <p class="ops-muted">
            Brouillon → Prévisualiser → Publier. Les ×N ne s’appliquent qu’aux événements publiés.
        </p>
        <form method="post" class="ops-event-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

            <div class="ops-form-grid">
                <label class="ops-field">
                    <span>Titre</span>
                    <input class="ops-input" name="title" required maxlength="120" value="<?= e($form['title']) ?>"
                           placeholder="ex. Weekend points ×2">
                </label>
                <label class="ops-field">
                    <span>Type</span>
                    <select class="ops-select" name="type" id="eventType" required>
                        <?php foreach ($types as $code => $meta): ?>
                            <option value="<?= e($code) ?>" <?= $form['type'] === $code ? 'selected' : '' ?>>
                                <?= e($meta['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <label class="ops-field">
                <span>Message joueurs (bannière)</span>
                <input class="ops-input" name="message" required maxlength="280" value="<?= e($form['message']) ?>"
                       placeholder="ex. Tous les bons pronos rapportent le double jusqu’à dimanche !">
            </label>

            <div class="ops-form-grid" id="eventMultRow">
                <label class="ops-field">
                    <span>Multiplicateur</span>
                    <select class="ops-select" name="multiplier">
                        <option value="1.5" <?= $form['multiplier'] === '1.5' ? 'selected' : '' ?>>×1.5</option>
                        <option value="2" <?= $form['multiplier'] === '2' || $form['multiplier'] === '2.0' ? 'selected' : '' ?>>×2</option>
                        <option value="3" <?= $form['multiplier'] === '3' || $form['multiplier'] === '3.0' ? 'selected' : '' ?>>×3</option>
                    </select>
                </label>
                <label class="ops-field" id="eventSportField">
                    <span>Sport vedette</span>
                    <select class="ops-select" name="sport">
                        <option value="soccer" <?= $form['sport'] === 'soccer' ? 'selected' : '' ?>>Football</option>
                        <option value="basketball" <?= $form['sport'] === 'basketball' ? 'selected' : '' ?>>Basket</option>
                        <option value="tennis" <?= $form['sport'] === 'tennis' ? 'selected' : '' ?>>Tennis</option>
                    </select>
                </label>
            </div>

            <div class="ops-form-grid">
                <label class="ops-field">
                    <span>Thème visuel</span>
                    <select class="ops-select" name="theme">
                        <?php foreach ($themes as $code => $label): ?>
                            <option value="<?= e($code) ?>" <?= $form['theme'] === $code ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="ops-field ops-check" style="justify-content:flex-end;padding-top:1.4rem">
                    <input type="checkbox" name="enabled" value="1" <?= $form['enabled'] ? 'checked' : '' ?>>
                    <span>Activé (dates)</span>
                </label>
            </div>

            <label class="ops-field ops-check" style="margin-bottom:0.45rem">
                <input type="checkbox" name="published" value="1" <?= $form['published'] ? 'checked' : '' ?>>
                <span>Publié (visible des joueurs)</span>
            </label>

            <label class="ops-field ops-check" style="margin-bottom:0.75rem">
                <input type="checkbox" name="notify_push" value="1">
                <span>Notifier les joueurs (push) à l’enregistrement si déjà live</span>
            </label>

            <div class="ops-form-grid">
                <label class="ops-field">
                    <span>Début</span>
                    <input class="ops-input ops-input-datetime" type="datetime-local" name="starts_at" required value="<?= e($form['starts_at']) ?>">
                </label>
                <label class="ops-field">
                    <span>Fin</span>
                    <input class="ops-input ops-input-datetime" type="datetime-local" name="ends_at" required value="<?= e($form['ends_at']) ?>">
                </label>
            </div>

            <p class="ops-muted" id="eventTypeHint" style="margin-top:0.35rem"></p>

            <div class="ops-form-row" style="margin-top:0.75rem">
                <button type="submit" class="ops-btn ops-btn-primary"><?= $form['id'] ? 'Enregistrer' : 'Créer' ?></button>
                <?php if ($form['id']): ?>
                    <a class="ops-btn ops-btn-ghost" href="<?= e(url('admin/events.php')) ?>">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="ops-panel">
    <div class="ops-panel-head">Catalogue des types</div>
    <div class="ops-panel-body">
        <ul class="ops-muted" style="margin:0;padding-left:1.2rem">
            <?php foreach ($types as $meta): ?>
                <li><strong><?= e($meta['label']) ?></strong> — <?= e($meta['hint']) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="ops-panel">
    <div class="ops-panel-head">Tous les événements</div>
    <div class="ops-panel-body">
        <?php if ($events === []): ?>
            <p class="ops-muted" style="margin:0">Aucun événement pour l’instant.</p>
        <?php else: ?>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Titre</th>
                        <th>Type</th>
                        <th>Fenêtre</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $ev): ?>
                    <tr>
                        <td class="ops-mono"><?= (int) $ev['id'] ?></td>
                        <td>
                            <strong><?= e((string) $ev['title']) ?></strong>
                            <div class="ops-sub"><?= e((string) $ev['message']) ?></div>
                        </td>
                        <td><?= e(siteEventTypeLabel((string) $ev['type'])) ?></td>
                        <td class="ops-mono ops-nowrap">
                            <?= e(formatMatchWhen((string) $ev['starts_at'])) ?>
                            → <?= e(formatMatchWhen((string) $ev['ends_at'])) ?>
                        </td>
                        <td><?= e(siteEventStatusLabel($ev)) ?></td>
                        <td>
                            <div class="ops-form-row" style="gap:0.35rem;flex-wrap:wrap">
                                <a class="ops-btn ops-btn-sm" href="<?= e(url('admin/events.php?edit=' . (int) $ev['id'])) ?>">Éditer</a>
                                <a class="ops-btn ops-btn-sm" target="_blank" rel="noopener"
                                   href="<?= e(url('index.php?preview_event=' . (int) $ev['id'])) ?>">Prévisualiser</a>
                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="publish">
                                    <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
                                    <?php if (empty($ev['published'])): ?>
                                        <input type="hidden" name="notify_on_publish" value="1">
                                    <?php endif; ?>
                                    <button type="submit" class="ops-btn ops-btn-sm <?= empty($ev['published']) ? 'ops-btn-primary' : '' ?>">
                                        <?= empty($ev['published']) ? 'Publier' : 'Brouillon' ?>
                                    </button>
                                </form>
                                <?php if (siteEventIsLive($ev)): ?>
                                <form method="post" onsubmit="return confirm('Envoyer une notif push à tous les abonnés ?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="notify">
                                    <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
                                    <button type="submit" class="ops-btn ops-btn-sm">Notifier</button>
                                </form>
                                <?php endif; ?>
                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
                                    <button type="submit" class="ops-btn ops-btn-sm">
                                        <?= !empty($ev['enabled']) ? 'Désactiver' : 'Activer' ?>
                                    </button>
                                </form>
                                <form method="post" onsubmit="return confirm('Supprimer cet événement ?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
                                    <button type="submit" class="ops-btn ops-btn-danger ops-btn-sm">Suppr.</button>
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
<script>
(function () {
    var typeEl = document.getElementById('eventType');
    var hint = document.getElementById('eventTypeHint');
    var multRow = document.getElementById('eventMultRow');
    var sportField = document.getElementById('eventSportField');
    var hints = <?= json_encode(array_map(static fn ($m) => $m['hint'], $types), JSON_UNESCAPED_UNICODE) ?>;
    function sync() {
        var t = typeEl.value;
        hint.textContent = hints[t] || '';
        var needsMult = (t === 'points_multiplier' || t === 'happy_hour' || t === 'featured_sport');
        multRow.style.display = needsMult ? '' : 'none';
        sportField.style.display = t === 'featured_sport' ? '' : 'none';
    }
    typeEl.addEventListener('change', sync);
    sync();
})();
</script>
<?php adminLayoutEnd(); ?>
