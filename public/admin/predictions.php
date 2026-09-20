<?php
require __DIR__ . '/../../app/bootstrap.php';
requireAdminLogin();

$pdo = getPDO();
$min = max(1, min(20, (int) ($_GET['min'] ?? 5)));
$data = adminQueryPredictionsOverview($pdo, $min, 50);
$ov = $data['overview'];

adminLayoutStart('Pronostics', 'predictions');
?>
<div class="ops-grid ops-grid--5">
    <div class="ops-stat">
        <span class="ops-stat-label">Ouverts</span>
        <span class="ops-stat-value"><?= (int) $ov['pending'] ?></span>
    </div>
    <div class="ops-stat">
        <span class="ops-stat-label">Résolus</span>
        <span class="ops-stat-value"><?= (int) $ov['resolved'] ?></span>
    </div>
    <div class="ops-stat">
        <span class="ops-stat-label">Réussite globale</span>
        <span class="ops-stat-value"><?= e((string) $ov['rate']) ?>%</span>
    </div>
    <div class="ops-stat">
        <span class="ops-stat-label">Pts distribués</span>
        <span class="ops-stat-value"><?= (int) $ov['points'] ?></span>
    </div>
    <div class="ops-stat">
        <span class="ops-stat-label">Joueurs ayant prono</span>
        <span class="ops-stat-value"><?= (int) $ov['players'] ?></span>
    </div>
</div>

<div class="ops-panel">
    <div class="ops-panel-head">Par marché</div>
    <div class="ops-panel-body">
        <?php if (empty($data['by_market'])): ?>
            <p class="ops-muted">Pas encore de pronos résolus.</p>
        <?php else: ?>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Marché</th>
                        <th>Total</th>
                        <th>OK</th>
                        <th>Ratés</th>
                        <th>Réussite</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['by_market'] as $row): ?>
                    <tr>
                        <td><?= e(marketTypeLabel((string) $row['type'])) ?></td>
                        <td class="ops-mono"><?= (int) $row['total'] ?></td>
                        <td class="ops-mono"><?= (int) $row['wins'] ?></td>
                        <td class="ops-mono"><?= (int) $row['losses'] ?></td>
                        <td class="ops-mono"><?= e((string) $row['rate']) ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="ops-panel">
    <div class="ops-panel-head">Classement réussite</div>
    <div class="ops-panel-body">
        <form method="get" class="ops-form-row" style="margin-bottom:0.85rem">
            <label class="ops-muted">Minimum de pronos résolus</label>
            <input class="ops-input ops-input-sm" type="number" name="min" min="1" max="20" value="<?= (int) $min ?>">
            <button type="submit" class="ops-btn ops-btn-ghost ops-btn-sm">Filtrer</button>
        </form>
        <?php if (empty($data['leaders'])): ?>
            <p class="ops-muted">Pas assez de données (seuil = <?= (int) $min ?>).</p>
        <?php else: ?>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Joueur</th>
                        <th>Réussite</th>
                        <th>OK / total</th>
                        <th>Pts pronos</th>
                        <th>Pts compte</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['leaders'] as $i => $u): ?>
                    <tr>
                        <td class="ops-mono"><?= $i + 1 ?></td>
                        <td>
                            <?= e($u['pseudo']) ?>
                            <?php if (empty($u['actif'])): ?>
                                <span class="ops-badge ops-badge--off">off</span>
                            <?php endif; ?>
                        </td>
                        <td class="ops-mono"><strong><?= e((string) $u['rate']) ?>%</strong></td>
                        <td class="ops-mono"><?= (int) $u['wins'] ?> / <?= (int) $u['total'] ?></td>
                        <td class="ops-mono"><?= (int) $u['pred_points'] ?></td>
                        <td class="ops-mono"><?= (int) $u['points_totaux'] ?></td>
                        <td>
                            <a class="ops-btn ops-btn-ghost ops-btn-sm" href="<?= e(url('admin/player.php?id=' . (int) $u['id'])) ?>">Dossier</a>
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
    <div class="ops-panel-head">Pronos ouverts récents</div>
    <div class="ops-panel-body">
        <?php if (empty($data['pending_recent'])): ?>
            <p class="ops-muted">Aucun prono en attente.</p>
        <?php else: ?>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Joueur</th>
                        <th>Match</th>
                        <th>Marché</th>
                        <th>Choix</th>
                        <th>Coup d’envoi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['pending_recent'] as $p): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('admin/player.php?id=' . (int) $p['user_id'])) ?>"><?= e((string) $p['pseudo']) ?></a>
                        </td>
                        <td>
                            <?= e((string) $p['equipe_home']) ?> – <?= e((string) $p['equipe_away']) ?>
                            <div class="ops-muted"><?= e((string) ($p['competition'] ?? '')) ?></div>
                        </td>
                        <td><?= e(marketTypeLabel((string) $p['market_type'])) ?></td>
                        <td class="ops-mono"><?= e(formatPredictionPick($p)) ?></td>
                        <td class="ops-mono"><?= e(adminFmtWhen($p['date_match'] ?? null)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php adminLayoutEnd(); ?>
