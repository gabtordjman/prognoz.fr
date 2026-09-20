<?php
require __DIR__ . '/../../app/bootstrap.php';
requireAdminLogin();

$pdo = getPDO();
$userId = (int) ($_GET['id'] ?? 0);
$dossier = adminQueryUserDossier($pdo, $userId);
if (!$dossier) {
    adminFlash('error', 'Joueur introuvable.');
    header('Location: ' . url('admin/predictions.php'));
    exit;
}

$user = $dossier['user'];
$stats = $dossier['pred_stats'];
$history = getUserPredictionHistory($pdo, $userId, 50);
$pending = adminQueryUserPendingPredictions($pdo, $userId, 50);
$thumb = avatarPublicUrl($user['avatar_url'] ?? null);

adminLayoutStart('Dossier · ' . (string) $user['pseudo'], 'predictions');
?>
<p class="ops-muted" style="margin-top:0">
    <a href="<?= e(url('admin/predictions.php')) ?>">← Pronostics</a>
    ·
    <a href="<?= e(url('admin/users.php?q=' . rawurlencode((string) $user['pseudo']))) ?>">Gérer le compte</a>
</p>

<div class="ops-panel">
    <div class="ops-panel-head">Joueur</div>
    <div class="ops-panel-body">
        <div class="ops-form-row" style="align-items:center;gap:1rem">
            <?php if ($thumb): ?>
                <img src="<?= e($thumb) ?>" alt="" class="ops-avatar-thumb" width="56" height="56">
            <?php endif; ?>
            <div>
                <strong><?= e((string) $user['pseudo']) ?></strong>
                <span class="ops-badge <?= !empty($user['actif']) ? 'ops-badge--ok' : 'ops-badge--off' ?>">
                    <?= !empty($user['actif']) ? 'actif' : 'off' ?>
                </span>
                <div class="ops-muted">
                    #<?= (int) $user['id'] ?>
                    · <?= e((string) ($user['email'] ?? '')) ?>
                    · <?= (int) ($user['points_totaux'] ?? 0) ?> pts totaux
                    <?php if (!empty($dossier['season'])): ?>
                        · saison <?= (int) $dossier['season_pts'] ?> pts
                    <?php endif; ?>
                    · <?= (int) $dossier['friends'] ?> ami(s)
                </div>
            </div>
        </div>
    </div>
</div>

<div class="ops-grid ops-grid--5">
    <div class="ops-stat">
        <span class="ops-stat-label">Pronos résolus</span>
        <span class="ops-stat-value"><?= (int) $stats['total'] ?></span>
    </div>
    <div class="ops-stat">
        <span class="ops-stat-label">Réussis</span>
        <span class="ops-stat-value"><?= (int) $stats['wins'] ?></span>
    </div>
    <div class="ops-stat">
        <span class="ops-stat-label">Ratés</span>
        <span class="ops-stat-value"><?= (int) $stats['losses'] ?></span>
    </div>
    <div class="ops-stat">
        <span class="ops-stat-label">Réussite</span>
        <span class="ops-stat-value"><?= e((string) $stats['rate']) ?>%</span>
    </div>
    <div class="ops-stat">
        <span class="ops-stat-label">Pts gagnés (pronos)</span>
        <span class="ops-stat-value"><?= (int) $stats['points'] ?></span>
    </div>
</div>

<div class="ops-panel">
    <div class="ops-panel-head">Pronos ouverts (<?= count($pending) ?>)</div>
    <div class="ops-panel-body">
        <?php if (empty($pending)): ?>
            <p class="ops-muted">Rien en attente.</p>
        <?php else: ?>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Match</th>
                        <th>Marché</th>
                        <th>Choix</th>
                        <th>Coup d’envoi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $p): ?>
                    <tr>
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

<div class="ops-panel">
    <div class="ops-panel-head">Historique (<?= count($history) ?>)</div>
    <div class="ops-panel-body">
        <?php if (empty($history)): ?>
            <p class="ops-muted">Pas encore d’historique résolu.</p>
        <?php else: ?>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Match</th>
                        <th>Marché</th>
                        <th>Choix</th>
                        <th>Résultat</th>
                        <th>Statut</th>
                        <th>Pts</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h):
                        $pres = predictionHistoryPresentation($h);
                    ?>
                    <tr>
                        <td>
                            <?= e((string) $h['equipe_home']) ?> – <?= e((string) $h['equipe_away']) ?>
                            <div class="ops-muted"><?= e(adminFmtWhen($h['date_match'] ?? null)) ?></div>
                        </td>
                        <td><?= e(marketTypeLabel((string) ($h['market_type'] ?? ''))) ?></td>
                        <td class="ops-mono"><?= e(formatPredictionPick($h)) ?></td>
                        <td class="ops-mono"><?= e(formatMatchResultLine($h)) ?></td>
                        <td>
                            <span class="ops-badge <?= ($h['statut'] ?? '') === 'correct' ? 'ops-badge--ok' : (($h['statut'] ?? '') === 'annule' ? 'ops-badge--warn' : 'ops-badge--off') ?>">
                                <?= e((string) ($pres['badge_label'] ?? $h['statut'])) ?>
                            </span>
                        </td>
                        <td class="ops-mono"><?= (int) ($h['points_gagnes'] ?? 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php adminLayoutEnd(); ?>
