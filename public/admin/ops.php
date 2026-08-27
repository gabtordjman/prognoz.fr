<?php
require __DIR__ . '/../../app/bootstrap.php';
requireAdminLogin();

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        adminFlash('error', 'Session expirée.');
        header('Location: ' . url('admin/ops.php'));
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'probe_quota') {
            $alt = trim((string) ($_POST['alt_key'] ?? ''));
            $probe = probeOddsApiQuota($alt !== '' ? $alt : null);
            if ($probe['ok']) {
                adminFlash(
                    'success',
                    'Sonde live (' . $probe['key_mask'] . ') : restants='
                    . ($probe['remaining'] ?? '?')
                    . ', utilisés=' . ($probe['used'] ?? '?')
                    . ', sports=' . (int) $probe['sports_count']
                    . ' — 0 crédit.'
                );
            } else {
                adminFlash('error', $probe['error'] ?? 'Sonde échouée.');
            }
        } elseif ($action === 'prune') {
            $pruned = pruneStaleMatchData($pdo);
            if (ensureAppCacheDir()) {
                @file_put_contents(pruneLastRunPath(), (string) time());
            }
            adminFlash(
                'success',
                'Purge : matchs mois préc.=' . (int) ($pruned['old_matches'] ?? 0)
                . ' (erreurs gardées=' . (int) ($pruned['kept_errors'] ?? 0) . ')'
                . ', junk terminés=' . (int) ($pruned['junk_finished'] ?? 0)
                . ', scores=' . (int) $pruned['score_options']
                . ', buteurs=' . (int) $pruned['buteur_options']
                . ', marchés vides=' . (int) ($pruned['empty_markets'] ?? 0)
            );
        } elseif ($action === 'clear_lock') {
            $lock = clearIdleSyncLock();
            adminFlash(
                $lock['busy'] ? 'error' : 'success',
                $lock['busy']
                    ? 'Sync encore active — attendez 1–2 min.'
                    : 'Verrou sync libéré (ou déjà libre).'
            );
        } elseif ($action === 'score_local') {
            $scored = scorePendingFinishedMatches($pdo);
            $closed = closeExpiredMatches($pdo);
            adminFlash(
                'success',
                'Points locaux : ' . $scored . ' match(s) scorés · fermés=' . $closed
            );
        } elseif ($action === 'cron') {
            @set_time_limit(180);
            // Appel direct PHP (plus d’HTTP vers APP_URL → plus de « réponse sync invalide »).
            $lifecycle = maintainMatchLifecycle($pdo, false);
            $reminders = maybeSendDailyMatchReminders($pdo);
            $summary = summarizeStuckScoresQueue($pdo);
            adminFlash(
                'success',
                'Cron scores (local) : scores_run='
                . (!empty($lifecycle['scores']) ? 'oui' : 'non/throttle')
                . ' · cache=' . (!empty($lifecycle['cache']) ? 'oui' : 'non')
                . ' · fermés=' . (int) ($lifecycle['closed'] ?? 0)
                . ' · rappels_push=' . (int) ($reminders['sent_push'] ?? 0)
                . ' · rappels_mail=' . (int) ($reminders['sent_mail'] ?? 0)
                . ' · encore sans score=' . (int) $summary['total']
                . ' (API≤3j=' . (int) $summary['api_window']
                . ', trop vieux=' . (int) $summary['too_old'] . ')'
                . ' · quota=' . (oddsQuotaRemaining() ?? '?')
            );
        } elseif ($action === 'catchup_scores') {
            @set_time_limit(240);
            $rec = catchUpMissingScoresFromApi($pdo);
            if (!empty($rec['quota_blocked'])) {
                adminFlash('error', 'Rattrapage bloqué : quota API trop bas.');
            } else {
                adminFlash(
                    'success',
                    'Rattrapage scores : '
                    . (int) $rec['sports_queried'] . ' ligue(s) (~'
                    . (int) $rec['credits_est'] . ' crédits) · '
                    . (int) $rec['resolved'] . ' score(s) appliqué(s) · '
                    . (int) $rec['scored'] . ' points locaux · '
                    . (int) $rec['voided'] . ' void/report · '
                    . 'reste ' . (int) $rec['still_stuck']
                    . ' (API=' . (int) $rec['still_api']
                    . ', vieux=' . (int) $rec['too_old'] . ')'
                    . ' · quota=' . ($rec['quota_remaining'] ?? '?')
                );
            }
        } elseif ($action === 'matches') {
            @set_time_limit(150);
            $syncResult = runMatchImportSync($pdo, true, true);
            adminFlash(
                !empty($syncResult['ran']) ? 'success' : 'info',
                'Import matchs : ran=' . (!empty($syncResult['ran']) ? 'oui' : 'non')
                . ' · skip=' . (string) ($syncResult['skip_reason'] ?? '—')
                . ' · sports=' . (int) ($syncResult['sports_checked'] ?? 0)
                . ' · events=' . (int) ($syncResult['events_fetched'] ?? 0)
                . ' · importés=' . (int) ($syncResult['events_imported'] ?? 0)
            );
        } elseif ($action === 'odds') {
            @set_time_limit(90);
            $odds = maybeSyncOdds($pdo, true);
            $coverage = countDisplayedOddsCoverage($pdo);
            adminFlash(
                'success',
                'Cotes : ran=' . (!empty($odds['ran']) ? 'oui' : 'non')
                . ' · maj=' . (int) ($odds['updated'] ?? 0)
                . ' · couverture=' . (int) ($coverage['with'] ?? 0) . '/' . (int) ($coverage['total'] ?? 0)
                . ' · quota=' . (oddsQuotaRemaining() ?? '?')
            );
        }
    } catch (InvalidArgumentException $e) {
        adminFlash('error', $e->getMessage());
    } catch (Throwable $e) {
        adminFlash('error', 'Erreur : ' . $e->getMessage());
    }
    header('Location: ' . url('admin/ops.php'));
    exit;
}

$quota = oddsQuotaState();
$pruneStats = staleMatchDataStats($pdo);
$stuckSummary = summarizeStuckScoresQueue($pdo);
$buteurDays = (int) BUTEUR_OPTIONS_RETENTION_DAYS;
$purgeTotal = (int) $pruneStats['score_options']
    + (int) $pruneStats['buteur_options']
    + (int) $pruneStats['empty_markets']
    + (int) $pruneStats['old_matches']
    + (int) ($pruneStats['junk_finished'] ?? 0);

adminLayoutStart('Sync API & crédits', 'ops');
?>
<div class="ops-panel">
    <div class="ops-panel-head">Crédits The Odds API</div>
    <div class="ops-panel-body">
        <p class="ops-muted">
            Cache local — restants : <span class="ops-mono"><?= $quota['remaining'] !== null ? (int) $quota['remaining'] : '—' ?></span>
            · Utilisés : <span class="ops-mono"><?= isset($quota['used']) ? (int) $quota['used'] : '—' ?></span>
            <?php if (!empty($quota['updated_at'])): ?>
                · Relevé : <?= e(date('d/m/Y H:i', (int) $quota['updated_at'])) ?>
            <?php endif; ?>
        </p>
        <form method="post" class="ops-form-row">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="probe_quota">
            <button class="ops-btn ops-btn-primary" type="submit">Vérifier quota (0 crédit)</button>
            <input class="ops-input ops-input-lg" name="alt_key" placeholder="Autre clé (optionnel)" autocomplete="off">
            <button class="ops-btn ops-btn-ghost" type="submit">Sonder cette clé</button>
        </form>
        <p class="ops-muted" style="margin-bottom:0;margin-top:0.55rem">
            Sonde = GET /v4/sports (gratuit). Sans clé alternative = ODDS_API_KEY du .env.
        </p>
    </div>
</div>

<div class="ops-panel">
    <div class="ops-panel-head">File scores (aperçu)</div>
    <div class="ops-panel-body">
        <p class="ops-muted">
            Sans score : <span class="ops-mono"><?= (int) $stuckSummary['total'] ?></span>
            · Récupérables API (≤ <?= (int) SCORES_CATCHUP_DAYS ?> j) :
            <span class="ops-mono"><?= (int) $stuckSummary['api_window'] ?></span>
            · Trop vieux : <span class="ops-mono"><?= (int) $stuckSummary['too_old'] ?></span>
            · Ligues à interroger : <span class="ops-mono"><?= (int) $stuckSummary['sports_api'] ?></span>
            (~<?= (int) $stuckSummary['credits_est'] ?> crédits)
        </p>
        <p class="ops-muted" style="margin-bottom:0">
            Le cron horaire ne fait plus qu’1 ligue si tout est calme ;
            en backlog il monte jusqu’à <?= (int) SCORES_MAX_SPORTS_BACKLOG ?> ligues / passe.
            Pour tout rattraper d’un coup → bouton ci-dessous ou page Résultats.
        </p>
    </div>
</div>

<div class="ops-panel">
    <div class="ops-panel-head">Actions serveur</div>
    <div class="ops-panel-body">
        <p class="ops-muted">Exécution <strong>locale PHP</strong> (plus d’appel HTTP vers APP_URL).</p>
        <div class="ops-actions">
            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="score_local">
                <button class="ops-btn ops-btn-ghost" type="submit">Points locaux (0 crédit)</button>
            </form>
            <form method="post" onsubmit="return confirm('Import matchs /events — 0 crédit, 1–2 min ?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="matches">
                <button class="ops-btn ops-btn-primary" type="submit">Rafraîchir matchs (0 crédit)</button>
            </form>
            <form method="post" onsubmit="return confirm('Peut coûter quelques crédits cotes ?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="odds">
                <button class="ops-btn ops-btn-ghost" type="submit">Cotes manquantes</button>
            </form>
            <form method="post" onsubmit="return confirm('Passe cron scores (budget auto / throttle) ?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="cron">
                <button class="ops-btn ops-btn-ghost" type="submit">Cron scores</button>
            </form>
            <form method="post"
                  onsubmit="return confirm('Rattrapage multi-ligues : jusqu’à <?= (int) min((int) SCORES_ADMIN_CATCHUP_MAX_SPORTS, max(1, (int) $stuckSummary['sports_api'])) ?> ligue(s) ≈ <?= (int) min((int) SCORES_ADMIN_CATCHUP_MAX_SPORTS, max(1, (int) $stuckSummary['sports_api'])) * 2 ?> crédits. Continuer ?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="catchup_scores">
                <button class="ops-btn ops-btn-primary" type="submit">Rattrapage scores (multi-ligues)</button>
            </form>
            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="clear_lock">
                <button class="ops-btn ops-btn-ghost" type="submit">Libérer verrou sync</button>
            </form>
        </div>
    </div>
</div>

<div class="ops-panel">
    <div class="ops-panel-head">Place en base de données</div>
    <div class="ops-panel-body">
        <p class="ops-muted">
            Purge les matchs <strong>avant le <?= e((string) $pruneStats['cutoff_label']) ?></strong>
            (mois précédents), y compris leurs pronos déjà résolus.
            <strong>Gardés</strong> : données indisponibles, score manquant, pronos encore en attente
            — tu corriges d’abord, tu re-purges ensuite. Options buteur : &gt; <?= $buteurDays ?> j.
        </p>
        <ul class="ops-muted" style="margin:0 0 0.85rem;padding-left:1.2rem">
            <li>Matchs mois précédents à supprimer : <span class="ops-mono"><?= (int) $pruneStats['old_matches'] ?></span></li>
            <li>Matchs mois précédents gardés (erreurs) : <span class="ops-mono"><?= (int) $pruneStats['kept_errors'] ?></span></li>
            <li>Matchs terminés sans prono (&gt; 7 j) : <span class="ops-mono"><?= (int) ($pruneStats['junk_finished'] ?? 0) ?></span></li>
            <li>Options score exact inutiles : <span class="ops-mono"><?= (int) $pruneStats['score_options'] ?></span></li>
            <li>Options buteur périmées : <span class="ops-mono"><?= (int) $pruneStats['buteur_options'] ?></span></li>
            <li>Marchés vides restants : <span class="ops-mono"><?= (int) $pruneStats['empty_markets'] ?></span></li>
        </ul>
        <form method="post" onsubmit="return confirm('Purger <?= (int) $pruneStats['old_matches'] ?> match(s) des mois précédents (erreurs gardées) ?');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="prune">
            <button class="ops-btn ops-btn-primary" type="submit" <?= $purgeTotal === 0 ? 'disabled' : '' ?>>
                Purger maintenant
            </button>
        </form>
    </div>
</div>
<?php adminLayoutEnd(); ?>
