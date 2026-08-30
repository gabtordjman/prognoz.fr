<?php
require __DIR__ . '/../../app/bootstrap.php';
requireAdminLogin();

$pdo = getPDO();
$r = collectRetentionSnapshot($pdo);

adminLayoutStart('Rétention', 'retention');
?>
<div class="ops-panel">
    <div class="ops-panel-head">Boucle de feedback</div>
    <div class="ops-panel-body">
        <p class="ops-muted" style="margin-bottom:0">
            Basé sur <span class="ops-mono">last_seen_at</span> et les pronos.
            Objectif : est-ce que les joueurs reviennent après le match, et pas seulement le soir du prono.
        </p>
    </div>
</div>

<div class="ops-grid ops-grid--5">
    <div class="ops-stat">
        <span class="ops-stat-label">Vus (24 h)</span>
        <span class="ops-stat-value"><?= (int) $r['seen_24h'] ?></span>
    </div>
    <div class="ops-stat">
        <span class="ops-stat-label">Vus (7 j)</span>
        <span class="ops-stat-value"><?= (int) $r['seen_7d'] ?></span>
    </div>
    <div class="ops-stat">
        <span class="ops-stat-label">Ont prono (7 j)</span>
        <span class="ops-stat-value"><?= (int) $r['pickers_7d'] ?></span>
    </div>
    <div class="ops-stat">
        <span class="ops-stat-label">Pronos (24 h)</span>
        <span class="ops-stat-value"><?= (int) $r['picks_today'] ?></span>
    </div>
    <div class="ops-stat">
        <span class="ops-stat-label">Réguliers</span>
        <span class="ops-stat-value"><?= (int) $r['regulars_count'] ?></span>
    </div>
</div>

<div class="ops-panel">
    <div class="ops-panel-head">Retour après match (14 j)</div>
    <div class="ops-panel-body">
        <p class="ops-muted">
            Joueurs ayant un prono sur un match terminé, puis une visite
            <strong>après</strong> le coup d’envoi (+2 h) — proxy « est revenu voir les points ».
        </p>
        <p style="margin:0;font-size:1.35rem;font-weight:700">
            <?= (int) $r['returned_after_match'] ?>
            <span class="ops-muted" style="font-size:0.95rem;font-weight:500">
                / <?= (int) $r['had_finished_pick'] ?>
                <?php if ($r['return_rate_pct'] !== null): ?>
                    · <?= e((string) $r['return_rate_pct']) ?>&nbsp;%
                <?php endif; ?>
            </span>
        </p>
    </div>
</div>

<div class="ops-panel">
    <div class="ops-panel-head">Réguliers (14 j)</div>
    <div class="ops-panel-body">
        <p class="ops-muted">
            ≥ 2 jours distincts avec un prono, et vu dans les 48 dernières heures.
        </p>
        <?php if ($r['regulars'] === []): ?>
            <p class="ops-muted" style="margin-bottom:0">Pas encore assez d’historique — ou personne ne matche les critères.</p>
        <?php else: ?>
            <div class="ops-table-wrap">
                <table class="ops-table">
                    <thead>
                        <tr>
                            <th>Pseudo</th>
                            <th>Jours actifs</th>
                            <th>Pronos</th>
                            <th>Série</th>
                            <th>Points</th>
                            <th>Dernière vue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($r['regulars'] as $u): ?>
                        <tr>
                            <td><?= e((string) $u['pseudo']) ?></td>
                            <td class="ops-mono"><?= (int) $u['days_with_picks'] ?></td>
                            <td class="ops-mono"><?= (int) $u['picks_14d'] ?></td>
                            <td class="ops-mono"><?= (int) ($u['serie_en_cours'] ?? 0) ?></td>
                            <td class="ops-mono"><?= (int) $u['points_totaux'] ?></td>
                            <td><?= e(!empty($u['last_seen_at']) ? formatMatchWhen((string) $u['last_seen_at']) : '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="ops-panel">
    <div class="ops-panel-head">Actifs récents (7 j)</div>
    <div class="ops-panel-body">
        <?php if ($r['recent_active'] === []): ?>
            <p class="ops-muted" style="margin-bottom:0">Aucune visite récente (last_seen).</p>
        <?php else: ?>
            <div class="ops-table-wrap">
                <table class="ops-table">
                    <thead>
                        <tr>
                            <th>Pseudo</th>
                            <th>Pronos 7 j</th>
                            <th>Série</th>
                            <th>Points</th>
                            <th>Dernière vue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($r['recent_active'] as $u): ?>
                        <tr>
                            <td><?= e((string) $u['pseudo']) ?></td>
                            <td class="ops-mono"><?= (int) $u['picks_7d'] ?></td>
                            <td class="ops-mono"><?= (int) ($u['serie_en_cours'] ?? 0) ?></td>
                            <td class="ops-mono"><?= (int) $u['points_totaux'] ?></td>
                            <td><?= e(formatMatchWhen((string) $u['last_seen_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php adminLayoutEnd(); ?>
