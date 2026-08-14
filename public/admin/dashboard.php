<?php
require __DIR__ . '/../../app/bootstrap.php';
requireAdminLogin();

$pdo = getPDO();
$pending = countPendingPredictions($pdo);
$quota = oddsQuotaState();
$season = getActiveSeason($pdo);
$users = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE actif = 1')->fetchColumn();
$communities = (int) $pdo->query('SELECT COUNT(*) FROM communities')->fetchColumn();
$messages = (int) $pdo->query('SELECT COUNT(*) FROM community_messages WHERE supprime = 0')->fetchColumn();
$matchesUpcoming = (int) $pdo->query(
    "SELECT COUNT(*) FROM matches WHERE statut = 'a_venir' AND date_match > UTC_TIMESTAMP()"
)->fetchColumn();

$needScore = listStuckMatchesForManualScore($pdo, 40);
$needPoints = listMatchesAwaitingLocalScore($pdo, 40);
$voidedMatches = listVoidedMatchesForManualScore($pdo, 40);
$postponedMatches = listPostponedMatchesForAdmin($pdo, 80);
$stuckMatchCount = count($needScore);
$localMatchCount = count($needPoints);
$voidedCount = count($voidedMatches);
$postponedCount = count($postponedMatches);

adminLayoutStart('Vue d’ensemble', 'dashboard');
?>
<div class="ops-grid ops-grid--5">
    <div class="ops-stat">
        <span class="ops-stat-label">Joueurs actifs</span>
        <span class="ops-stat-value"><?= $users ?></span>
    </div>
    <a class="ops-stat-link" href="<?= e(url('admin/scores.php')) ?>#sans-score">
        <div class="ops-stat <?= $stuckMatchCount > 0 ? 'ops-stat--alert' : '' ?>">
            <span class="ops-stat-label">Sans score API</span>
            <span class="ops-stat-value"><?= $stuckMatchCount ?></span>
        </div>
    </a>
    <a class="ops-stat-link" href="<?= e(url('admin/scores.php')) ?>#donnees-indisponibles">
        <div class="ops-stat <?= $voidedCount > 0 ? 'ops-stat--alert' : '' ?>">
            <span class="ops-stat-label">Données indisponibles</span>
            <span class="ops-stat-value"><?= $voidedCount ?></span>
        </div>
    </a>
    <a class="ops-stat-link" href="<?= e(url('admin/scores.php')) ?>#points-locaux">
        <div class="ops-stat <?= $localMatchCount > 0 ? 'ops-stat--alert' : '' ?>">
            <span class="ops-stat-label">Points à donner</span>
            <span class="ops-stat-value"><?= $localMatchCount ?></span>
        </div>
    </a>
    <a class="ops-stat-link" href="<?= e(url('admin/ops.php')) ?>">
        <div class="ops-stat">
            <span class="ops-stat-label">Crédits API restants</span>
            <span class="ops-stat-value"><?= $quota['remaining'] !== null ? (int) $quota['remaining'] : '—' ?></span>
        </div>
    </a>
</div>

<div class="ops-panel">
    <div class="ops-panel-head">État du système</div>
    <div class="ops-panel-body">
        <p class="ops-muted">
            Pronos encore « en attente » : <span class="ops-mono"><?= (int) $pending['pending'] ?></span>
            (dont match déjà joué : <span class="ops-mono"><?= (int) $pending['stuck'] ?></span>)
            · Matchs à venir : <span class="ops-mono"><?= $matchesUpcoming ?></span>
            · Reportés : <span class="ops-mono"><?= $postponedCount ?></span>
            <?php if ($postponedCount > 0): ?>
                (<a href="<?= e(url('admin/scores.php')) ?>#reportes">voir</a>)
            <?php endif; ?>
            · Communautés : <span class="ops-mono"><?= $communities ?></span>
            · Messages : <span class="ops-mono"><?= $messages ?></span>
            <?php if ($season): ?>
                · Saison #<?= (int) $season['id'] ?> → <?= e(formatSeasonFin($season['fin'] ?? '')) ?>
            <?php endif; ?>
        </p>
        <p class="ops-muted" style="margin-bottom:0">
            Raccourcis :
            <a href="<?= e(url('admin/scores.php')) ?>">Résultats &amp; scores manuels</a>
            ·
            <a href="<?= e(url('admin/reports.php')) ?>">Rapports e-mail</a>
            (alertes → <?= e(adminNotifyEmail()) ?>).
        </p>
    </div>
</div>

<div class="ops-panel">
    <div class="ops-panel-head">Actions rapides — rapports</div>
    <div class="ops-panel-body">
        <div class="ops-actions">
            <a class="ops-btn ops-btn-primary" href="<?= e(url('admin/scores.php')) ?>">Saisir un score</a>
            <a class="ops-btn ops-btn-ghost" href="<?= e(url('admin/reports.php')) ?>">Rapports e-mail</a>
        </div>
    </div>
</div>

<div class="ops-panel" id="apercu-sans-score">
    <div class="ops-panel-head">1 · Matchs sans score API (à saisir)</div>
    <div class="ops-panel-body">
        <p class="ops-muted">L’API n’a pas donné de résultat. Il faut saisir le score ou marquer le match vraiment annulé.</p>
        <?php if ($needScore === []): ?>
            <p class="ops-muted" style="margin-bottom:0">Aucun — tout est clair de ce côté.</p>
        <?php else: ?>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Match</th>
                        <th>Compétition</th>
                        <th>Quand</th>
                        <th>Pronos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($needScore as $m): ?>
                    <tr>
                        <td class="ops-mono">#<?= (int) $m['id'] ?></td>
                        <td><?= e($m['equipe_home'] . ' – ' . $m['equipe_away']) ?></td>
                        <td><?= e($m['competition'] ?: $m['sport']) ?></td>
                        <td class="ops-mono"><?= e(formatMatchWhen($m['date_match'])) ?></td>
                        <td class="ops-mono"><?= (int) $m['pending_count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p style="margin:0.75rem 0 0">
            <a class="ops-btn ops-btn-primary" href="<?= e(url('admin/scores.php')) ?>#sans-score">Traiter ces matchs →</a>
        </p>
        <?php endif; ?>
    </div>
</div>

<div class="ops-panel" id="apercu-indisponibles">
    <div class="ops-panel-head">2 · Données indisponibles (à corriger)</div>
    <div class="ops-panel-body">
        <p class="ops-muted">Pronos déjà annulés faute de résultat — saisissez le vrai score pour recalculer les points joueur.</p>
        <?php if ($voidedMatches === []): ?>
            <p class="ops-muted" style="margin-bottom:0">Aucun — pas de blocage « données indisponibles ».</p>
        <?php else: ?>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Match</th>
                        <th>Sport</th>
                        <th>Pronos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($voidedMatches as $m): ?>
                    <tr>
                        <td class="ops-mono">#<?= (int) $m['id'] ?></td>
                        <td><?= e($m['equipe_home'] . ' – ' . $m['equipe_away']) ?></td>
                        <td><?= e(sportCategoryLabel((string) ($m['sport'] ?? ''))) ?></td>
                        <td class="ops-mono"><?= (int) $m['voided_count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p style="margin:0.75rem 0 0">
            <a class="ops-btn ops-btn-primary" href="<?= e(url('admin/scores.php')) ?>#donnees-indisponibles">Corriger &amp; attribuer →</a>
        </p>
        <?php endif; ?>
    </div>
</div>

<div class="ops-panel" id="apercu-points">
    <div class="ops-panel-head">3 · Score déjà en base, points pas encore donnés</div>
    <div class="ops-panel-body">
        <p class="ops-muted">Le résultat est là, mais les pronos sont encore « en attente ». Un clic suffit (0 crédit API).</p>
        <?php if ($needPoints === []): ?>
            <p class="ops-muted" style="margin-bottom:0">Aucun — les points sont à jour.</p>
        <?php else: ?>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Match</th>
                        <th>Score</th>
                        <th>Pronos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($needPoints as $m): ?>
                    <tr>
                        <td class="ops-mono">#<?= (int) $m['id'] ?></td>
                        <td><?= e($m['equipe_home'] . ' – ' . $m['equipe_away']) ?></td>
                        <td class="ops-mono"><?= (int) $m['score_home'] ?>–<?= (int) $m['score_away'] ?></td>
                        <td class="ops-mono"><?= (int) $m['pending_count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p style="margin:0.75rem 0 0">
            <a class="ops-btn ops-btn-primary" href="<?= e(url('admin/scores.php')) ?>#points-locaux">Donner les points →</a>
        </p>
        <?php endif; ?>
    </div>
</div>

<?php adminLayoutEnd(); ?>
