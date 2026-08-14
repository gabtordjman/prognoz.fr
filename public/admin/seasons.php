<?php
require __DIR__ . '/../../app/bootstrap.php';
requireAdminLogin();

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        adminFlash('error', 'Session expirée.');
        header('Location: ' . url('admin/seasons.php'));
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'close_now') {
            $season = forceCloseActiveSeason($pdo);
            adminFlash(
                'success',
                $season
                    ? 'Saison clôturée. Nouvelle active #' . (int) $season['id'] . ' → ' . formatSeasonFin($season['fin'])
                    : 'Clôture effectuée (aucune saison active).'
            );
        } elseif ($action === 'schedule_month') {
            $at = nextMonthStartDatetime();
            $r = scheduleActiveSeasonEnd($pdo, $at);
            adminFlash(
                'success',
                'Fin planifiée au ' . formatSeasonFin($r['season']['fin'])
                . ' (saison #' . (int) $r['season']['id'] . ').'
            );
        } elseif ($action === 'schedule_custom') {
            $raw = trim((string) ($_POST['fin'] ?? ''));
            // datetime-local → Y-m-d H:i:s
            $raw = str_replace('T', ' ', $raw);
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw)) {
                $raw .= ':00';
            }
            $r = scheduleActiveSeasonEnd($pdo, $raw);
            adminFlash(
                'success',
                'Fin planifiée au ' . formatSeasonFin($r['season']['fin'])
                . ' (saison #' . (int) $r['season']['id'] . ').'
            );
        }
    } catch (InvalidArgumentException $e) {
        adminFlash('error', $e->getMessage());
    } catch (Throwable $e) {
        adminFlash('error', 'Erreur : ' . $e->getMessage());
    }
    header('Location: ' . url('admin/seasons.php'));
    exit;
}

$active = getActiveSeason($pdo);
$history = $pdo->query(
    'SELECT id, debut, fin, cloturee FROM seasons ORDER BY id DESC LIMIT 12'
)->fetchAll();
$monthTarget = nextMonthStartDatetime();

adminLayoutStart('Saisons', 'seasons');
?>
<div class="ops-panel">
    <div class="ops-panel-head">Saison active</div>
    <div class="ops-panel-body">
        <?php if ($active): ?>
            <p class="ops-muted">
                Saison <span class="ops-mono">#<?= (int) $active['id'] ?></span>
                · <?= e(formatSeasonFin($active['debut'])) ?>
                → <?= e(formatSeasonFin($active['fin'])) ?>
                · <?= e(seasonCountdownLabel($active)) ?>
            </p>
        <?php else: ?>
            <p class="ops-muted">Aucune saison active (maintainSeasons en créera une au prochain passage).</p>
        <?php endif; ?>
        <div class="ops-actions">
            <form method="post" onsubmit="return confirm('Clôturer maintenant ? Podium + badges + nouvelle saison.');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="close_now">
                <button type="submit" class="ops-btn ops-btn-danger">Clôturer maintenant</button>
            </form>
            <form method="post" onsubmit="return confirm(<?= json_encode('Planifier la fin au ' . formatSeasonFin($monthTarget) . ' ?', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) ?>);">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="schedule_month">
                <button type="submit" class="ops-btn ops-btn-primary">Fin → 1er du mois</button>
            </form>
        </div>
        <form method="post" class="ops-form-row" style="margin-top:0.85rem">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="schedule_custom">
            <label class="ops-label" for="fin" style="margin:0">Fin personnalisée</label>
            <input class="ops-input" id="fin" name="fin" type="datetime-local" required>
            <button type="submit" class="ops-btn ops-btn-ghost">Planifier</button>
        </form>
        <p class="ops-muted" style="margin-top:0.65rem;margin-bottom:0">
            Cible 1er du mois : <span class="ops-mono"><?= e($monthTarget) ?></span> (fuseau app).
        </p>
    </div>
</div>

<div class="ops-panel">
    <div class="ops-panel-head">Historique</div>
    <div class="ops-panel-body ops-table-wrap">
        <table class="ops-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Début</th>
                    <th>Fin</th>
                    <th>État</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $s): ?>
                <tr>
                    <td class="ops-mono">#<?= (int) $s['id'] ?></td>
                    <td class="ops-mono"><?= e($s['debut']) ?></td>
                    <td class="ops-mono"><?= e($s['fin']) ?></td>
                    <td>
                        <?php if (!empty($s['cloturee'])): ?>
                            <span class="ops-badge">clôturée</span>
                        <?php else: ?>
                            <span class="ops-badge ops-badge--ok">active</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php adminLayoutEnd(); ?>
