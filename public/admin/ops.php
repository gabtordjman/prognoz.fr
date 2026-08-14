<?php
require __DIR__ . '/../../app/bootstrap.php';
requireAdminLogin();

$pdo = getPDO();
$appUrl = rtrim((string) env('APP_URL', ''), '/');
$cron = CRON_SECRET;

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
        } elseif (in_array($action, ['cron', 'matches', 'odds', 'score_local'], true)) {
            if ($appUrl === '' || $cron === '') {
                throw new InvalidArgumentException('APP_URL ou CRON_SECRET manquant dans .env');
            }
            $params = match ($action) {
                'cron' => ['cron' => '1', 'key' => $cron],
                'matches' => ['key' => $cron, 'force' => '1', 'refresh' => '1', 'wait' => '1'],
                'odds' => ['mode' => 'odds', 'force' => '1', 'key' => $cron],
                default => ['mode' => 'score_local', 'key' => $cron],
            };
            $url = $appUrl . '/api/sync.php?' . http_build_query($params);
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => $action === 'matches' ? 150 : 90,
                    'header'  => "Accept: application/json\r\nUser-Agent: PrognozOps/1.0\r\n",
                ],
            ]);
            $raw = @file_get_contents($url, false, $ctx);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($data)) {
                throw new InvalidArgumentException('Réponse sync invalide.');
            }
            adminFlash(
                !empty($data['ok']) ? 'success' : 'error',
                'Sync ' . $action . ' : ' . substr(json_encode($data, JSON_UNESCAPED_UNICODE), 0, 400)
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
    <div class="ops-panel-head">Actions serveur</div>
    <div class="ops-panel-body">
        <p class="ops-muted">Appelle le site via APP_URL + CRON_SECRET (équivalent console Python).</p>
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
            <form method="post" onsubmit="return confirm('Cron scores — jusqu’à 2 crédits ?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="cron">
                <button class="ops-btn ops-btn-ghost" type="submit">Cron scores</button>
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
