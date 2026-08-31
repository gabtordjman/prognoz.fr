<?php
require __DIR__ . '/../../app/bootstrap.php';
requireLogin();

$pdo = getPDO();
ensurePredictionHistorySchema($pdo);
$user = currentUser($pdo);
$userId = (int) $user['id'];

$pseudoCooldown = profileChangeCooldownMessage($user['pseudo_changed_at'] ?? null, t('auth.action.change_pseudo'));
$passwordCooldown = profileChangeCooldownMessage($user['password_changed_at'] ?? null, t('auth.action.change_password'));
$canForceSync = userCanForceSync($pdo, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        flash('error', t('common.session_expired'));
        header('Location: ' . url('account/settings.php'));
        exit;
    }
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'change_pseudo') {
            changeUserPseudo(
                $pdo,
                $userId,
                trim($_POST['new_pseudo'] ?? ''),
                $_POST['password'] ?? ''
            );
            flash('success', t('settings.pseudo_ok'));
        } elseif ($action === 'change_surnom') {
            updateUserSurnom($pdo, $userId, (string) ($_POST['surnom'] ?? ''));
            clearCurrentUserCache();
            flash('success', t('settings.surnom_ok'));
        } elseif ($action === 'change_password') {
            changeUserPassword(
                $pdo,
                $userId,
                $_POST['current_password'] ?? '',
                $_POST['new_password'] ?? '',
                $_POST['new_password_confirm'] ?? ''
            );
            flash('success', t('settings.password_ok'));
        } elseif ($action === 'delete_account') {
            deleteUserAccount(
                $pdo,
                $userId,
                trim($_POST['confirm_pseudo'] ?? ''),
                $_POST['password'] ?? ''
            );
            header('Location: ' . url('index.php'));
            exit;
        } elseif ($action === 'sync_odds') {
            if (!userCanForceSync($pdo, $userId)) {
                flash('error', 'Action réservée à l’administrateur du site.');
            } elseif (!oddsQuotaAllows('odds')) {
                flash('error', 'Quota insuffisant pour les cotes (réserve gardée pour les scores).');
            } else {
                @set_time_limit(90);
                $before = countDisplayedOddsCoverage($pdo);
                $odds   = maybeSyncOdds($pdo, true);
                $after  = countDisplayedOddsCoverage($pdo);
                if (empty($odds['ran']) && !empty($odds['skipped_quota'])) {
                    flash('error', 'Sync cotes impossible (clé API, colonnes BDD, ou quota trop bas).');
                } elseif (!empty($odds['nothing_to_do']) || $after['with'] >= $after['total']) {
                    flash(
                        'success',
                        'Cotes déjà en place : ' . $after['with'] . '/' . $after['total']
                        . ' matchs affichés. Rechargez la page Matchs (Ctrl+F5).'
                    );
                } elseif ($after['with'] <= $before['with']) {
                    flash(
                        'error',
                        'Cotes : ' . $after['with'] . '/' . $after['total'] . ' matchs affichés ont des % — '
                        . 'certaines ligues ne sont pas couvertes par l’API (EU). Rechargez la page Matchs (Ctrl+F5).'
                    );
                } else {
                    flash(
                        'success',
                        'Cotes mises à jour : ' . $after['with'] . '/' . $after['total'] . ' matchs affichés'
                        . ' (+' . (int) ($odds['updated'] ?? 0) . '). Rechargez la page Matchs (Ctrl+F5).'
                    );
                }
            }
        } elseif ($action === 'resolve_results') {
            if (!userCanForceSync($pdo, $userId)) {
                flash('error', 'Action réservée à l’administrateur du site.');
            } else {
                @set_time_limit(120);
                $res = resolveMatchResults($pdo, true);
                if (!empty($res['quota_blocked'])) {
                    flash(
                        'error',
                        'Quota API épuisé — aucun appel /scores n’a été lancé. '
                        . 'Saisissez les scores à la main ci-dessous (0 crédit), '
                        . 'ou attendez le renouvellement du quota.'
                    );
                } else {
                    $message = 'Résultats : ' . (int) $res['resolved'] . ' match(s) clôturé(s), '
                        . (int) $res['scored'] . ' match(s) repassé(s) au scoring';
                    if ((int) $res['voided'] > 0) {
                        $message .= ', ' . (int) $res['voided'] . ' prono(s) annulé(s) (aucun résultat API après '
                            . RESULT_MAX_WAIT_DAYS . ' jours)';
                    }
                    flash(
                        ($res['resolved'] > 0 || $res['scored'] > 0 || $res['voided'] > 0) ? 'success' : 'error',
                        $message . '.'
                    );
                }
            }
        } elseif ($action === 'manual_score') {
            if (!userCanForceSync($pdo, $userId)) {
                flash('error', 'Action réservée à l’administrateur du site.');
            } else {
                applyManualMatchScore(
                    $pdo,
                    (int) ($_POST['match_id'] ?? 0),
                    (int) ($_POST['score_home'] ?? -1),
                    (int) ($_POST['score_away'] ?? -1)
                );
                flash('success', 'Score enregistré — points attribués (0 crédit API).');
            }
        } elseif ($action === 'cancel_match') {
            if (!userCanForceSync($pdo, $userId)) {
                flash('error', 'Action réservée à l’administrateur du site.');
            } else {
                $reason = normalizeMatchCancelReason((string) ($_POST['cancel_reason'] ?? 'autre'));
                if ($reason === null) {
                    $reason = 'autre';
                }
                $voided = cancelMatch($pdo, (int) ($_POST['match_id'] ?? 0), $reason);
                flash(
                    'success',
                    'Match annulé — ' . $voided . ' prono(s) à 0 pt (visible « Match annulé »).'
                );
            }
        } elseif ($action === 'grant_points') {
            if (!userCanForceSync($pdo, $userId)) {
                flash('error', 'Action réservée à l’administrateur du site.');
            } else {
                $target = findUserByPseudo($pdo, trim((string) ($_POST['target_pseudo'] ?? '')));
                if (!$target) {
                    throw new InvalidArgumentException('Pseudo introuvable.');
                }
                $result = grantUserPoints(
                    $pdo,
                    (int) $target['id'],
                    (int) ($_POST['points_delta'] ?? 0),
                    !empty($_POST['to_season'])
                );
                $sign = $result['delta'] > 0 ? '+' . $result['delta'] : (string) $result['delta'];
                flash(
                    'success',
                    $result['pseudo'] . ' : ' . $sign . ' pt(s)'
                    . ($result['season'] ? ' (saison + total)' : ' (total)')
                    . ' → ' . $result['points_totaux'] . ' pts totaux.'
                );
            }
        } elseif ($action === 'schedule_season_end') {
            if (!userCanForceSync($pdo, $userId)) {
                flash('error', 'Action réservée à l’administrateur du site.');
            } else {
                $fin = trim((string) ($_POST['season_fin'] ?? ''));
                if ($fin === '' || $fin === 'next_month') {
                    $fin = nextMonthStartDatetime();
                } else {
                    // datetime-local → Y-m-d H:i:s
                    $fin = str_replace('T', ' ', $fin);
                    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $fin)) {
                        $fin .= ':00';
                    }
                }
                $result = scheduleActiveSeasonEnd($pdo, $fin);
                flash(
                    'success',
                    'Saison #' . (int) $result['season']['id'] . ' calée jusqu’au '
                    . formatSeasonFin($result['season']['fin'])
                    . '. Ensuite : nouvelle saison jusqu’au '
                    . formatSeasonFin($result['next_end'])
                    . ' (crédits API remis le 1er du mois).'
                );
            }
        } elseif ($action === 'force_sync') {
            if (!userCanForceSync($pdo, $userId)) {
                flash('error', 'Action réservée à l’administrateur du site.');
            } elseif (isSyncLockHeld()) {
                flash('error', 'Une sync est déjà en cours. Attendez 1–2 minutes puis rechargez les matchs.');
            } elseif (triggerBackgroundMatchSync()) {
                flash('success', 'Sync lancée en arrière-plan. Rechargez la page des matchs dans 1–2 minutes.');
            } else {
                @set_time_limit(SYNC_FORCE_MAX_SECONDS + 15);
                $result = runMatchImportSync($pdo, true, false);
                maintainMatchLifecycle($pdo, false);
                $byCat = getUpcomingMatchesByCategory($pdo);
                $soccer = count($byCat['soccer'] ?? []);
                if ($result['ran']) {
                    flash(
                        'success',
                        'Sync terminée — ' . (int) $result['events_imported'] . ' match(s) importé(s), '
                        . (int) $result['active_soccer'] . ' ligue(s) foot, '
                        . $soccer . ' match(s) foot affiché(s).'
                    );
                } else {
                    $reason = $result['skip_reason'] ?? 'inconnue';
                    flash('error', 'Sync non exécutée (' . $reason . '). Réessayez dans quelques minutes.');
                }
            }
        }
    } catch (InvalidArgumentException $e) {
        flash('error', $e->getMessage());
    }
    header('Location: ' . url('account/settings.php'));
    exit;
}

$pushStatus = pushConfigStatus();
$oddsCoverage = countDisplayedOddsCoverage($pdo);
$pendingStats = $canForceSync ? countPendingPredictions($pdo) : ['pending' => 0, 'stuck' => 0, 'users' => 0];
$apiQuota = $canForceSync ? oddsQuotaState() : [];
$stuckMatches = ($canForceSync && $pendingStats['stuck'] > 0)
    ? listStuckMatchesForManualScore($pdo, 40)
    : [];
$quotaRemaining = isset($apiQuota['remaining']) && $apiQuota['remaining'] !== null
    ? (int) $apiQuota['remaining']
    : null;
$quotaMonthReset = $canForceSync && oddsQuotaLikelyMonthlyReset();
$quotaDead = $quotaRemaining !== null && $quotaRemaining <= 0 && !$quotaMonthReset;
$activeSeason = $canForceSync ? (getActiveSeason($pdo) ?: null) : null;
$plannedSeasonEnd = nextMonthStartDatetime(); // 2026-08-01… tant qu’on est en juillet
?>
<!DOCTYPE html>
<html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
<head>
    <?php layoutHead(t('settings.title'), true, seoPage('settings')); ?>
</head>
<body>

<?php layoutTopbar($user, 'dashboard'); ?>

<div class="app-main app-main-wide">
    <?php layoutFlashes(); ?>

    <h2 class="page-title"><?= e(t('settings.h1')) ?></h2>
    <p class="page-sub"><?php
        $shown = userDisplayName($user);
        echo e($shown);
        if ($shown !== (string) $user['pseudo']) {
            echo ' · @' . e((string) $user['pseudo']);
        }
    ?> · <?= e($user['email']) ?></p>

    <div class="panel panel-spaced">
        <div class="panel-head"><?= e(t('settings.notify_head')) ?></div>
        <div class="panel-body">
            <p class="settings-hint"><?= e(t('settings.notify_hint')) ?></p>
            <ol class="settings-push-howto">
                <li><strong>Navigateur</strong> — autoriser Prognoz (bouton ci-dessous).</li>
                <li><strong>Abonnement</strong> — le site enregistre cet appareil auprès du serveur.</li>
                <li><strong>Serveur</strong> — envoie les alertes (gains, chat, fin de saison) même si l’onglet est fermé.</li>
            </ol>
            <p class="settings-push-server <?= $pushStatus['ok'] ? 'is-ok' : 'is-ko' ?>">
                <?php if ($pushStatus['ok']): ?>
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    Serveur push : <strong>OK</strong> — les alertes peuvent partir.
                <?php else: ?>
                    <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
                    Serveur push : <strong>indisponible</strong>
                    <?php if (!$pushStatus['has_vapid']): ?> — clés VAPID manquantes dans le <code>.env</code> du serveur.<?php endif; ?>
                    <?php if (!$pushStatus['has_vendor']): ?> — dossier <code>vendor/</code> absent (<code>composer install</code>).<?php endif; ?>
                    Sans ça, « Autoriser » ne suffit pas.
                <?php endif; ?>
            </p>
            <p class="settings-notify-status" id="notifyStatus">—</p>
            <p class="settings-notify-diag" id="notifyDiagnostics">—</p>
            <div class="settings-notify-options">
                <label class="settings-check">
                    <input type="checkbox" id="notifyWins" checked>
                    <?= e(t('settings.notify_wins_lbl')) ?>
                </label>
                <label class="settings-check">
                    <input type="checkbox" id="notifyChat" checked>
                    <?= e(t('settings.notify_chat_lbl')) ?>
                </label>
            </div>
            <div class="settings-notify-actions">
                <button type="button" class="btn btn-primary btn-sm" id="btnEnableNotify"<?= $pushStatus['ok'] ? '' : ' disabled title="Réparez d’abord le serveur push (VAPID / vendor)"' ?>><?= e(t('settings.notify_enable_btn')) ?></button>
                <button type="button" class="btn btn-ghost btn-sm" id="btnTestNotify"<?= $pushStatus['ok'] ? '' : ' disabled' ?>><?= e(t('settings.notify_test_btn')) ?></button>
                <button type="button" class="btn btn-ghost btn-sm" id="btnDisableNotify" hidden><?= e(t('settings.notify_disable_btn')) ?></button>
            </div>
            <p class="settings-hint settings-hint-sm" id="notifyTestResult" hidden></p>
        </div>
    </div>

    <?php if ($canForceSync): ?>
    <div class="panel panel-spaced">
        <div class="panel-head">Push serveur (admin)</div>
        <div class="panel-body">
            <?php if ($pushStatus['ok']): ?>
                <p class="settings-hint">Clés VAPID + Composer OK sur <strong>cette</strong> machine. Vérifiez que le même <code>.env</code> est bien celui du VPS de prod.</p>
            <?php else: ?>
                <p class="settings-hint settings-warning">
                    Push inactif ici.
                    <?php if (!$pushStatus['has_vapid']): ?> Clés VAPID manquantes dans <code>.env</code>.<?php endif; ?>
                    <?php if (!$pushStatus['has_vendor']): ?> Dossier <code>vendor/</code> absent.<?php endif; ?>
                </p>
                <ul class="settings-push-checklist">
                    <li>Clés VAPID : <?= $pushStatus['has_vapid'] ? 'OK' : '<strong>manquantes</strong>' ?></li>
                    <li>Bibliothèque push (<code>vendor/</code>) : <?= $pushStatus['has_vendor'] ? 'OK' : '<strong>manquante</strong>' ?></li>
                    <?php if (!$pushStatus['has_vendor']): ?>
                    <li>SSH : <code>composer install --no-interaction</code></li>
                    <?php endif; ?>
                    <?php if (!$pushStatus['has_vapid']): ?>
                    <li>SSH : <code>php tools/generate_vapid.php</code> → coller les 3 lignes dans le <code>.env</code> du <strong>serveur</strong>, puis recharger PHP-FPM / Apache.</li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel panel-spaced">
        <div class="panel-head">Saison</div>
        <div class="panel-body">
            <?php if ($activeSeason): ?>
                <p class="settings-hint">
                    Saison #<?= (int) $activeSeason['id'] ?> :
                    <?= e(formatSeasonFin($activeSeason['debut'])) ?>
                    → <strong><?= e(formatSeasonFin($activeSeason['fin'])) ?></strong>
                    (<?= e(seasonCountdownLabel($activeSeason)) ?>).
                    Durée de la suivante : <?= (int) SAISON_DUREE_JOURS ?> jours.
                </p>
            <?php else: ?>
                <p class="settings-hint settings-warning">Aucune saison active — elle sera créée au prochain chargement.</p>
            <?php endif; ?>
            <p class="settings-hint">
                Les crédits The Odds API se remettent à <strong>0 le 1er de chaque mois</strong> (FAQ officielle).
                Caler la fin de saison sur cette date : podium + push, puis classement à zéro pour la vraie saison.
            </p>
            <form method="post" class="settings-sync-form" onsubmit="return confirm('Planifier la fin de saison au <?= e(formatSeasonFin($plannedSeasonEnd)) ?> ?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="schedule_season_end">
                <input type="hidden" name="season_fin" value="next_month">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-flag-checkered" aria-hidden="true"></i>
                    Clôturer le <?= e(formatSeasonFin($plannedSeasonEnd)) ?> (reset API)
                </button>
            </form>
        </div>
    </div>

    <div class="panel panel-spaced">
        <div class="panel-head">Administration</div>
        <div class="panel-body">
            <p class="settings-hint">
                <strong>Matchs</strong> = liste des rencontres (gratuit côté API).<br>
                <strong>Cotes</strong> = pourcentages sous les boutons 1 / N / 2 (1 crédit / ligue, admin seulement, max <?= (int) ODDS_FORCE_MAX_SPORTS ?> ligues sans %).<br>
                <strong>État actuel :</strong> <?= (int) $oddsCoverage['with'] ?>/<?= (int) $oddsCoverage['total'] ?> matchs affichés ont des cotes (identique pour tous les joueurs).
            </p>
            <p class="settings-hint<?= $pendingStats['stuck'] > 0 ? ' settings-warning' : '' ?>">
                <strong>Pronos en attente :</strong> <?= (int) $pendingStats['pending'] ?>
                (<?= (int) $pendingStats['users'] ?> joueur<?= $pendingStats['users'] > 1 ? 's' : '' ?>),
                dont <strong><?= (int) $pendingStats['stuck'] ?></strong> sur des matchs déjà joués.
                <?php if ($pendingStats['stuck'] > 0 && $quotaDead): ?>
                    Quota mort → saisie manuelle ci-dessous.
                <?php elseif ($pendingStats['stuck'] > 0): ?>
                    Résoudre via l’API ou saisir le score à la main.
                <?php endif; ?>
            </p>
            <p class="settings-hint<?= (!empty($apiQuota['last_error']) || ($quotaRemaining !== null && $quotaRemaining <= 50)) ? ' settings-warning' : '' ?>">
                <strong>Quota API :</strong>
                <?php if ($quotaRemaining === null): ?>
                    inconnu — aucun appel enregistré depuis la mise à jour.
                <?php else: ?>
                    <?= $quotaRemaining ?> crédit<?= $quotaRemaining > 1 ? 's' : '' ?> restant<?= $quotaRemaining > 1 ? 's' : '' ?>
                    (<?= (int) $apiQuota['used'] ?> utiliséé<?= (int) $apiQuota['used'] > 1 ? 's' : '' ?>),
                    relevé le <?= e(date('d/m/Y H:i', (int) $apiQuota['updated_at'])) ?>.
                    <?php if ($quotaDead): ?>
                        <strong>Quota épuisé : les résultats API sont coupés. Utilisez la saisie manuelle ci-dessous.</strong>
                    <?php elseif ($quotaMonthReset): ?>
                        <strong>Nouveau mois : le cache quota est périmé — le prochain appel API relira le solde réel (reset le 1er).</strong>
                    <?php elseif ($quotaRemaining <= (int) ODDS_QUOTA_RESERVE_ODDS): ?>
                        <strong>Mode économie : seuls les scores sont autorisés (cotes/buteurs coupés).</strong>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($apiQuota['last_error'])): ?>
                    <br><strong>Dernière erreur API :</strong> <?= e($apiQuota['last_error']) ?>
                    (<?= e(date('d/m/Y H:i', (int) $apiQuota['last_error_at'])) ?>).
                <?php endif; ?>
            </p>
            <p class="settings-hint">
                Budget auto : max <?= (int) SCORES_MAX_SPORTS_BACKLOG ?> ligues / <?= (int) (SCORES_SYNC_INTERVAL_SECONDS / 60) ?> min
                si backlog (sinon <?= (int) SCORES_MAX_SPORTS_PER_RUN ?>),
                uniquement si des pronos sont bloqués.
                Cache scores <?= (int) (ODDS_CACHE_TTL_SCORES / 3600) ?> h · cotes coupées sous <?= (int) ODDS_QUOTA_RESERVE_ODDS ?> crédits ·
                jamais de cotes dans le cron.
            </p>
            <form method="post" class="settings-sync-form settings-sync-form-inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="sync_odds">
                <button type="submit" class="btn btn-primary btn-sm"<?= $quotaDead ? ' disabled title="Quota épuisé"' : '' ?>>
                    <i class="fa-solid fa-percent" aria-hidden="true"></i> Rafraîchir les cotes
                </button>
            </form>
            <form method="post" class="settings-sync-form settings-sync-form-inline" onsubmit="return confirm('Lancer une sync des matchs en arrière-plan ?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="force_sync">
                <button type="submit" class="btn btn-ghost btn-sm">
                    <i class="fa-solid fa-rotate" aria-hidden="true"></i> Importer les matchs
                </button>
            </form>
            <form method="post" class="settings-sync-form settings-sync-form-inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="resolve_results">
                <button type="submit" class="btn btn-ghost btn-sm"<?= $quotaDead ? ' disabled title="Quota épuisé — saisie manuelle ci-dessous"' : '' ?>>
                    <i class="fa-solid fa-trophy" aria-hidden="true"></i> Résoudre via l’API
                </button>
            </form>
        </div>
    </div>

    <?php if ($canForceSync): ?>
    <div class="panel panel-spaced">
        <div class="panel-head">Attribuer des points</div>
        <div class="panel-body">
            <p class="settings-hint">Bonus / malus manuel (ex. concours). Total carrière, et saison en cours si coché.</p>
            <form method="post" class="friend-add-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="grant_points">
                <input type="text" name="target_pseudo" class="field-input" placeholder="Pseudo du joueur" required maxlength="30" autocomplete="off">
                <input type="number" name="points_delta" class="field-input field-input-sm" value="10" required title="Positif ou négatif">
                <label class="field-check" style="margin:0;align-items:center;">
                    <input type="checkbox" name="to_season" value="1" checked>
                    <span class="field-check-text">Saison</span>
                </label>
                <button type="submit" class="btn btn-primary btn-sm">OK</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($stuckMatches)): ?>
    <div class="panel panel-spaced">
        <div class="panel-head">Saisie manuelle des scores <span class="pts-tag">0 crédit</span></div>
        <div class="panel-body">
            <p class="settings-hint<?= $quotaDead ? ' settings-warning' : '' ?>">
                <?= $quotaDead
                    ? 'Quota API à zéro : saisissez le score réel, ou annulez le match s’il n’a pas eu lieu.'
                    : 'Filet de secours si l’API rate un match. Score domicile – extérieur, ou « Annuler » si report / forfait.' ?>
            </p>
            <div class="manual-score-list">
                <?php foreach ($stuckMatches as $sm): ?>
                <div class="manual-score-row">
                    <div class="manual-score-meta">
                        <strong><?= e($sm['equipe_home']) ?></strong>
                        <span class="vs">–</span>
                        <strong><?= e($sm['equipe_away']) ?></strong>
                        <span class="manual-score-info">
                            <?= e($sm['competition'] ?: $sm['sport']) ?>
                            · <?= e(formatMatchWhen($sm['date_match'])) ?>
                            · <?= (int) $sm['pending_count'] ?> prono<?= (int) $sm['pending_count'] > 1 ? 's' : '' ?>
                        </span>
                    </div>
                    <div class="manual-score-inputs">
                        <form method="post" class="manual-score-score-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="manual_score">
                            <input type="hidden" name="match_id" value="<?= (int) $sm['id'] ?>">
                            <input type="number" name="score_home" class="field-input field-input-sm" min="0" max="99" required
                                   aria-label="Score <?= e($sm['equipe_home']) ?>" placeholder="0">
                            <span class="vs">–</span>
                            <input type="number" name="score_away" class="field-input field-input-sm" min="0" max="99" required
                                   aria-label="Score <?= e($sm['equipe_away']) ?>" placeholder="0">
                            <button type="submit" class="btn btn-primary btn-sm">Valider</button>
                        </form>
                        <form method="post" class="inline-form" onsubmit="return confirm('Annuler ce match ? Les pronos passent à 0 pt.');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="cancel_match">
                            <input type="hidden" name="match_id" value="<?= (int) $sm['id'] ?>">
                            <select name="cancel_reason" class="field-input field-input-sm" required>
                                <option value="">Raison…</option>
                                <?php foreach (matchCancelReasonOptions() as $code => $label): ?>
                                <option value="<?= e($code) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-ghost btn-sm">Annuler</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <div class="panel panel-spaced">
        <div class="panel-head"><?= e(t('settings.pseudo_head')) ?></div>
        <div class="panel-body">
            <p class="settings-hint"><?= e(t('settings.pseudo_hint', ['n' => PROFILE_CHANGE_COOLDOWN_DAYS])) ?></p>
            <?php if ($pseudoCooldown): ?>
                <p class="settings-cooldown"><?= e($pseudoCooldown) ?></p>
            <?php else: ?>
                <form method="post" class="settings-pseudo-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="change_pseudo">
                    <div class="field-group">
                        <label class="field-label"><?= e(t('settings.new_pseudo')) ?></label>
                        <input type="text" name="new_pseudo" class="field-input" required minlength="3" maxlength="30" pattern="[a-zA-Z0-9_\-]+" autocomplete="username" value="<?= e($user['pseudo']) ?>">
                    </div>
                    <div class="field-group">
                        <label class="field-label"><?= e(t('settings.password_confirm')) ?></label>
                        <input type="password" name="password" class="field-input" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><?= e(t('settings.save_pseudo')) ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel panel-spaced">
        <div class="panel-head"><?= e(t('settings.surnom_head')) ?></div>
        <div class="panel-body">
            <p class="settings-hint"><?= e(t('settings.surnom_hint')) ?></p>
            <form method="post" class="settings-pseudo-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="change_surnom">
                <div class="field-group">
                    <label class="field-label"><?= e(t('dash.surnom')) ?></label>
                    <input type="text" name="surnom" class="field-input" maxlength="40"
                           value="<?= e((string) ($user['surnom'] ?? '')) ?>"
                           placeholder="<?= e(t('dash.surnom_ph')) ?>" autocomplete="nickname">
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><?= e(t('settings.save_surnom')) ?></button>
            </form>
        </div>
    </div>

    <div class="panel panel-spaced">
        <div class="panel-head"><?= e(t('settings.password_head')) ?></div>
        <div class="panel-body">
            <p class="settings-hint"><?= e(t('settings.password_hint', ['n' => PROFILE_CHANGE_COOLDOWN_DAYS])) ?></p>
            <?php if ($passwordCooldown): ?>
                <p class="settings-cooldown"><?= e($passwordCooldown) ?></p>
            <?php else: ?>
                <form method="post" class="settings-password-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="change_password">
                    <div class="field-group">
                        <label class="field-label"><?= e(t('settings.current_password')) ?></label>
                        <input type="password" name="current_password" class="field-input" required autocomplete="current-password">
                    </div>
                    <div class="field-group">
                        <label class="field-label"><?= e(t('settings.new_password')) ?></label>
                        <input type="password" name="new_password" class="field-input" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="field-group">
                        <label class="field-label"><?= e(t('settings.confirm_new_password')) ?></label>
                        <input type="password" name="new_password_confirm" class="field-input" required minlength="8" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><?= e(t('settings.save_password')) ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel panel-danger">
        <div class="panel-head"><?= e(t('settings.delete_head')) ?></div>
        <div class="panel-body">
            <p class="settings-warning"><?= e(t('settings.delete_warn')) ?></p>
            <form method="post" class="settings-delete-form" onsubmit="return confirm(<?= json_encode(t('settings.delete_confirm'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) ?>);">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete_account">
                <div class="field-group">
                    <label class="field-label"><?= e(t('settings.confirm_pseudo')) ?></label>
                    <input type="text" name="confirm_pseudo" class="field-input" required autocomplete="off" placeholder="<?= e($user['pseudo']) ?>">
                </div>
                <div class="field-group">
                    <label class="field-label"><?= e(t('settings.password_head')) ?></label>
                    <input type="password" name="password" class="field-input" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-danger btn-sm"><?= e(t('settings.delete_btn')) ?></button>
            </form>
        </div>
    </div>

    <nav class="settings-legal" aria-label="<?= e(t('common.legal_nav')) ?>">
        <a href="<?= e(url('legal/cgu.php')) ?>"><?= e(t('legal.cgu.h1')) ?></a>
        <a href="<?= e(url('legal/confidentialite.php')) ?>"><?= e(t('common.privacy')) ?></a>
    </nav>
</div>

<script>
    window.PRONO_SW_URL = <?= json_encode(url('sw-notifications.js'), JSON_UNESCAPED_UNICODE) ?>;
    window.PRONO_SW_SCOPE = <?= json_encode(publicBasePath(), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= e(assetUrl('assets/js/notifications.js')) ?>"></script>
<script>
(function () {
    if (!window.PrognozNotify) return;
    PrognozNotify.syncUi();

    var winsCb = document.getElementById('notifyWins');
    var chatCb = document.getElementById('notifyChat');
    var enableBtn = document.getElementById('btnEnableNotify');
    var disableBtn = document.getElementById('btnDisableNotify');
    var testBtn = document.getElementById('btnTestNotify');
    var testResult = document.getElementById('notifyTestResult');

    if (winsCb) {
        winsCb.addEventListener('change', function () {
            PrognozNotify.setWinsEnabled(winsCb.checked);
        });
    }
    if (chatCb) {
        chatCb.addEventListener('change', function () {
            PrognozNotify.setChatEnabled(chatCb.checked);
        });
    }
    if (enableBtn) {
        enableBtn.addEventListener('click', function () {
            PrognozNotify.requestPermission().then(function () {
                PrognozNotify.syncUi();
            });
        });
    }
    if (disableBtn) {
        disableBtn.addEventListener('click', function () {
            PrognozNotify.unsubscribePush().finally(function () {
                PrognozNotify.setEnabled(false);
                PrognozNotify.syncUi();
            });
        });
    }
    if (testBtn) {
        testBtn.addEventListener('click', function () {
            testBtn.disabled = true;
            if (testResult) {
                testResult.hidden = false;
                testResult.textContent = 'Envoi en cours…';
            }
            PrognozNotify.testNotification().then(function (res) {
                if (testResult) {
                    testResult.textContent = (res && res.message) ? res.message : (res && res.ok ? 'OK' : 'Échec — vérifiez les paramètres du site.');
                }
                PrognozNotify.syncUi();
            }).finally(function () {
                testBtn.disabled = false;
            });
        });
    }
})();
</script>

<?php layoutFooter(); ?>
</body>
</html>
