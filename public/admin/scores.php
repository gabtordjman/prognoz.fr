<?php
require __DIR__ . '/../../app/bootstrap.php';
requireAdminLogin();

$pdo = getPDO();
$searchResults = [];
$searchHome = '';
$searchAway = '';
$searchSport = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        adminFlash('error', 'Session expirée.');
        header('Location: ' . url('admin/scores.php'));
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    $anchor = '#saisie';
    try {
        if ($action === 'manual_score') {
            $pens = !empty($_POST['pens']);
            $pensWinner = null;
            if ($pens) {
                $pensWinner = (string) ($_POST['pens_winner'] ?? '');
                if ($pensWinner !== '1' && $pensWinner !== '2') {
                    throw new InvalidArgumentException('Tirs au but : choisis le vainqueur.');
                }
            }
            $res = applyManualMatchScore(
                $pdo,
                (int) ($_POST['match_id'] ?? 0),
                (int) ($_POST['score_home'] ?? -1),
                (int) ($_POST['score_away'] ?? -1),
                $pensWinner
            );
            $msg = 'Score enregistré — points attribués.';
            if ((int) ($res['rescored'] ?? 0) > 0) {
                $msg = 'Score enregistré — '
                    . (int) $res['rescored']
                    . ' prono(s) recalculé(s).';
            }
            adminFlash('success', $msg);
            $anchor = !empty($_POST['from_postponed']) ? '#reportes' : '#file';
        } elseif ($action === 'clear_match_score') {
            $cleared = clearManualMatchScore($pdo, (int) ($_POST['match_id'] ?? 0));
            adminFlash(
                'success',
                'Score effacé — '
                . (int) $cleared['reopened'] . ' prono(s) rouvert(s), '
                . (int) $cleared['points_reversed'] . ' pt retirés. '
                . 'Tu peux resaisir le bon score (cherche le match).'
            );
            $anchor = '#saisie';
        } elseif ($action === 'cancel_match') {
            $reason = normalizeMatchCancelReason((string) ($_POST['cancel_reason'] ?? ''));
            if ($reason === null) {
                throw new InvalidArgumentException('Choisis une raison d’annulation.');
            }
            $n = cancelMatch($pdo, (int) ($_POST['match_id'] ?? 0), $reason);
            $label = matchCancelReasonOptions()[$reason] ?? $reason;
            adminFlash(
                'success',
                'Match annulé (' . $label . ') — ' . $n . ' prono(s) à 0 pt.'
            );
            $anchor = !empty($_POST['from_search']) ? '#saisie' : '#file';
        } elseif ($action === 'postpone_match') {
            $dateRaw = trim((string) ($_POST['new_date'] ?? ''));
            $newDateUtc = $dateRaw !== '' ? parseAdminMatchDatetime($dateRaw) : null;
            $n = postponeMatch($pdo, (int) ($_POST['match_id'] ?? 0), $newDateUtc);
            $msg = 'Match reporté — ' . $n . ' prono(s) à 0 pt (indiqué au joueur).';
            if ($newDateUtc) {
                $msg .= ' Nouvelle date enregistrée.';
            }
            adminFlash('success', $msg);
            $anchor = '#reportes';
        } elseif ($action === 'postpone_set_date') {
            $dateUtc = parseAdminMatchDatetime((string) ($_POST['new_date'] ?? ''));
            updatePostponedMatchDate($pdo, (int) ($_POST['match_id'] ?? 0), $dateUtc);
            adminFlash('success', 'Date du match reporté mise à jour.');
            $anchor = '#reportes';
        } elseif ($action === 'postpone_reactivate') {
            $dateRaw = trim((string) ($_POST['new_date'] ?? ''));
            $newDateUtc = $dateRaw !== '' ? parseAdminMatchDatetime($dateRaw) : null;
            $n = reactivatePostponedMatch($pdo, (int) ($_POST['match_id'] ?? 0), $newDateUtc);
            adminFlash(
                'success',
                'Match réactivé (à venir) — ' . $n . ' prono(s) rouvert(s).'
            );
            $anchor = '#reportes';
        } elseif ($action === 'score_local') {
            $scored = scorePendingFinishedMatches($pdo);
            adminFlash('success', 'Points locaux : ' . $scored . ' match(s) traités.');
            $anchor = '#points-locaux';
        } elseif ($action === 'catchup_scores') {
            @set_time_limit(240);
            $rec = catchUpMissingScoresFromApi($pdo);
            if (!empty($rec['quota_blocked'])) {
                adminFlash('error', 'Rattrapage bloqué : quota API trop bas.');
            } else {
                adminFlash(
                    (int) $rec['resolved'] > 0 ? 'success' : 'info',
                    'Rattrapage API : '
                    . (int) $rec['sports_queried'] . ' ligue(s) (~'
                    . (int) $rec['credits_est'] . ' crédits) · '
                    . (int) $rec['resolved'] . ' score(s) · '
                    . 'reste ' . (int) $rec['still_stuck']
                    . ' (récupérables API=' . (int) $rec['still_api']
                    . ', trop vieux=' . (int) $rec['too_old'] . ')'
                );
            }
            $anchor = '#file';
        } elseif ($action === 'recover_postponed_scores') {
            $rec = recoverPostponedScoresFromApi($pdo, 3);
            $msg = 'Récupération API reportés : '
                . (int) $rec['recovered'] . ' score(s) appliqué(s)'
                . ' · ' . (int) $rec['checked'] . ' dans la fenêtre 3 j'
                . ' · ' . (int) $rec['skipped_old'] . ' trop vieux pour l’API'
                . ( !empty($rec['quota_blocked']) ? ' · quota bloqué' : '' );
            adminFlash((int) $rec['recovered'] > 0 ? 'success' : 'info', $msg);
            $anchor = '#reportes';
        } elseif ($action === 'dismiss_empty_postponed') {
            $n = dismissPostponedMatchesWithoutPredictions($pdo);
            adminFlash(
                $n > 0 ? 'success' : 'info',
                $n > 0
                    ? $n . ' reporté(s) sans aucun prono retiré(s) de la liste (annulés).'
                    : 'Aucun reporté sans prono à nettoyer.'
            );
            $anchor = '#reportes';
        } elseif ($action === 'reactivate_future_postponed') {
            $n = reactivateFuturePostponedMatches($pdo);
            adminFlash(
                $n > 0 ? 'success' : 'info',
                $n > 0
                    ? $n . ' reporté(s) à date future réactivé(s).'
                    : 'Aucun reporté futur à réactiver.'
            );
            $anchor = '#reportes';
        } elseif ($action === 'search_teams') {
            $searchHome = trim((string) ($_POST['team_home'] ?? ''));
            $searchAway = trim((string) ($_POST['team_away'] ?? ''));
            $searchSport = (string) ($_POST['sport'] ?? '');
            if (!in_array($searchSport, ['', 'soccer', 'basketball', 'tennis'], true)) {
                $searchSport = '';
            }
            if ($searchHome === '' && $searchAway === '' && $searchSport === '') {
                adminFlash('error', 'Indique un sport et/ou au moins un nom d’équipe.');
                header('Location: ' . url('admin/scores.php') . '#saisie');
                exit;
            }
            $searchResults = searchMatchesForManualScore($pdo, $searchHome, $searchAway, 25, $searchSport);
            if ($searchResults === []) {
                adminFlash('error', 'Aucun match trouvé.');
            }
            $anchor = '#saisie';
        } else {
            header('Location: ' . url('admin/scores.php'));
            exit;
        }
        if ($action !== 'search_teams') {
            header('Location: ' . url('admin/scores.php') . $anchor);
            exit;
        }
    } catch (InvalidArgumentException $e) {
        adminFlash('error', $e->getMessage());
        header('Location: ' . url('admin/scores.php') . '#saisie');
        exit;
    } catch (Throwable $e) {
        adminFlash('error', 'Erreur technique.');
        header('Location: ' . url('admin/scores.php'));
        exit;
    }
}

$needScore = listStuckMatchesForManualScore($pdo, 100);
$needPoints = listMatchesAwaitingLocalScore($pdo, 40);
$voidedMatches = listVoidedMatchesForManualScore($pdo, 40);
$postponedMatches = listPostponedMatchesForAdmin($pdo, 80);
$stuckSummary = summarizeStuckScoresQueue($pdo);
$needScoreApi = [];
$needScoreOld = [];
foreach ($needScore as $m) {
    if (matchIsInScoresApiWindow($m)) {
        $needScoreApi[] = $m;
    } else {
        $needScoreOld[] = $m;
    }
}

$predMatchIds = array_values(array_unique(array_merge(
    array_map(static fn ($m) => (int) $m['id'], $needScore),
    array_map(static fn ($m) => (int) $m['id'], $voidedMatches),
    array_map(static fn ($m) => (int) $m['id'], $postponedMatches),
    array_map(static fn ($m) => (int) $m['id'], $needPoints),
    array_map(static fn ($m) => (int) $m['id'], $searchResults)
)));
$predsByMatch = fetchAdminMatchPredictions($pdo, $predMatchIds);

/**
 * @param array<string,mixed> $m
 */
function adminRenderScoreForm(array $m, bool $fromPostponed = false): void
{
    $id = (int) $m['id'];
    $home = (string) ($m['equipe_home'] ?? 'Domicile');
    $away = (string) ($m['equipe_away'] ?? 'Extérieur');
    $isSoccer = sportCategory((string) ($m['sport'] ?? '')) === 'soccer';
    ?>
    <form method="post" class="ops-score-form">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="manual_score">
        <input type="hidden" name="match_id" value="<?= $id ?>">
        <?php if ($fromPostponed): ?>
        <input type="hidden" name="from_postponed" value="1">
        <?php endif; ?>
        <div class="ops-score-line">
            <div class="ops-score-inputs">
                <label class="ops-score-team">
                    <span class="ops-score-team-name" title="<?= e($home) ?>"><?= e($home) ?></span>
                    <input class="ops-input ops-input-score" type="number" name="score_home" min="0" max="300" required placeholder="0">
                </label>
                <span class="ops-score-sep" aria-hidden="true">–</span>
                <label class="ops-score-team">
                    <span class="ops-score-team-name" title="<?= e($away) ?>"><?= e($away) ?></span>
                    <input class="ops-input ops-input-score" type="number" name="score_away" min="0" max="300" required placeholder="0">
                </label>
            </div>
            <button type="submit" class="ops-btn ops-btn-primary ops-btn-sm ops-score-submit">OK</button>
        </div>
        <?php if ($isSoccer): ?>
        <div class="ops-pens-row">
            <label class="ops-check ops-pens-check">
                <input type="checkbox" name="pens" value="1" class="ops-pens-toggle" data-pens-for="<?= $id ?>">
                <span>TAB</span>
            </label>
            <select class="ops-select ops-pens-winner" name="pens_winner" data-pens-select="<?= $id ?>" disabled>
                <option value="">Vainqueur…</option>
                <option value="1"><?= e($home) ?></option>
                <option value="2"><?= e($away) ?></option>
            </select>
        </div>
        <?php endif; ?>
    </form>
    <?php
}

/**
 * @param array<string,mixed> $m
 */
function adminRenderCancelForm(array $m, bool $fromSearch = false): void
{
    $id = (int) $m['id'];
    $reasons = matchCancelReasonOptions();
    ?>
    <form method="post" class="ops-cancel-form"
          onsubmit="return confirm('Annuler ce match ? Pronos → 0 pt, avec la raison choisie.');">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="cancel_match">
        <input type="hidden" name="match_id" value="<?= $id ?>">
        <?php if ($fromSearch): ?>
        <input type="hidden" name="from_search" value="1">
        <?php endif; ?>
        <label class="ops-cancel-reason">
            <span>Raison</span>
            <select class="ops-select ops-select-sm" name="cancel_reason" required>
                <option value="">Choisir…</option>
                <?php foreach ($reasons as $code => $label): ?>
                <option value="<?= e($code) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="ops-btn ops-btn-danger ops-btn-sm">Annuler le match</button>
    </form>
    <?php
}

/**
 * @param array<string,mixed> $m
 */
function adminRenderPostponeControls(array $m): void
{
    $id = (int) $m['id'];
    $dateVal = matchDatetimeLocalValue((string) ($m['date_match'] ?? ''));
    ?>
    <div class="ops-postpone-box">
        <form method="post" class="ops-postpone-form" onsubmit="return confirm('Marquer ce match comme reporté ? Les joueurs verront « Match reporté » (0 pt).');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="postpone_match">
            <input type="hidden" name="match_id" value="<?= $id ?>">
            <label class="ops-postpone-date">
                <span>Nouvelle date (opt.)</span>
                <input class="ops-input ops-input-datetime" type="datetime-local" name="new_date" value="<?= e($dateVal) ?>">
            </label>
            <div class="ops-postpone-actions">
                <button type="submit" class="ops-btn ops-btn-sm">Reporté</button>
            </div>
        </form>
        <?php adminRenderCancelForm($m); ?>
    </div>
    <?php
}

/**
 * @param list<array<string,mixed>> $preds
 */
function adminRenderMatchPredictors(array $preds): void
{
    if ($preds === []) {
        echo '<span class="ops-muted">—</span>';
        return;
    }
    $byUser = [];
    foreach ($preds as $p) {
        $uid = (int) ($p['user_id'] ?? 0);
        $pseudo = (string) ($p['pseudo'] ?? '?');
        if (!isset($byUser[$uid])) {
            $byUser[$uid] = [
                'pseudo' => $pseudo,
                'picks'  => [],
            ];
        }
        $byUser[$uid]['picks'][] = marketTypeLabel((string) ($p['market_type'] ?? ''))
            . ' : '
            . formatPickLabel($p, (string) ($p['reponse'] ?? ''));
    }
    ?>
    <ul class="ops-pred-list">
        <?php foreach ($byUser as $u): ?>
        <li>
            <strong class="ops-pred-pseudo"><?= e($u['pseudo']) ?></strong>
            <span class="ops-pred-picks"><?= e(implode(' · ', $u['picks'])) ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php
}

/**
 * @param array<string,mixed> $m
 */
function adminMatchStatusLabel(array $m): string
{
    $statut = (string) ($m['statut'] ?? '');
    if ($statut === 'reporte') {
        return 'Reporté';
    }
    if ($statut === 'annule') {
        return 'Annulé';
    }
    if (!empty($m['resultat_1x2'])) {
        return (int) $m['score_home'] . '–' . (int) $m['score_away'];
    }
    if ((int) ($m['voided_count'] ?? 0) > 0) {
        return 'Données indispo.';
    }
    if ((int) ($m['pending_count'] ?? 0) > 0) {
        return 'Sans score';
    }
    return $statut !== '' ? $statut : '—';
}

adminLayoutStart('Résultats & scores manuels', 'scores');
?>
<div class="ops-panel" id="saisie">
    <div class="ops-panel-head">Saisir un score</div>
    <div class="ops-panel-body">
        <p class="ops-muted">
            Choisis le sport, tape les équipes (ou un joueur en tennis), puis entre le score exact.
        </p>
        <form method="post" class="ops-score-search">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="search_teams">
            <label class="ops-field">
                <span>Sport</span>
                <select class="ops-select" name="sport">
                    <option value="" <?= $searchSport === '' ? 'selected' : '' ?>>Tous</option>
                    <option value="soccer" <?= $searchSport === 'soccer' ? 'selected' : '' ?>>Football</option>
                    <option value="basketball" <?= $searchSport === 'basketball' ? 'selected' : '' ?>>Basket</option>
                    <option value="tennis" <?= $searchSport === 'tennis' ? 'selected' : '' ?>>Tennis</option>
                </select>
            </label>
            <label class="ops-field">
                <span>Équipe / joueur 1</span>
                <input class="ops-input" name="team_home" value="<?= e($searchHome) ?>" placeholder="ex. Dinamo, Fritz…">
            </label>
            <label class="ops-field">
                <span>Équipe / joueur 2</span>
                <input class="ops-input" name="team_away" value="<?= e($searchAway) ?>" placeholder="ex. Thun, Bergs…">
            </label>
            <div class="ops-field ops-field--action">
                <span>&nbsp;</span>
                <button type="submit" class="ops-btn ops-btn-primary">Chercher</button>
            </div>
        </form>

        <?php if ($searchResults !== []): ?>
        <div class="ops-table-wrap ops-table-wrap--scores">
            <table class="ops-table ops-table--scores">
                <thead>
                    <tr>
                        <th>Sport</th>
                        <th>Match</th>
                        <th>Quand</th>
                        <th>État</th>
                        <th>Pronos</th>
                        <th>Score</th>
                        <th>Annuler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($searchResults as $m): ?>
                    <?php
                    $mid = (int) $m['id'];
                    $statut = (string) ($m['statut'] ?? '');
                    $alreadyCancelled = $statut === 'annule';
                    $canScore = !$alreadyCancelled && (
                        empty($m['resultat_1x2'])
                        || (int) ($m['voided_count'] ?? 0) > 0
                        || (int) ($m['pending_count'] ?? 0) > 0
                    );
                    ?>
                    <tr>
                        <td>
                            <div><?= e(sportCategoryLabel((string) ($m['sport'] ?? ''))) ?></div>
                            <div class="ops-sub"><?= e((string) ($m['competition'] ?: $m['sport'])) ?></div>
                        </td>
                        <td>
                            <strong><?= e($m['equipe_home'] . ' – ' . $m['equipe_away']) ?></strong>
                            <div class="ops-mono ops-sub">#<?= $mid ?></div>
                        </td>
                        <td class="ops-mono ops-nowrap"><?= e(formatMatchWhen($m['date_match'])) ?></td>
                        <td class="ops-mono"><?= e(adminMatchStatusLabel($m)) ?></td>
                        <td class="ops-td-preds"><?php adminRenderMatchPredictors($predsByMatch[$mid] ?? []); ?></td>
                        <td class="ops-td-score">
                            <?php if ($alreadyCancelled): ?>
                                <span class="ops-muted">Déjà annulé</span>
                            <?php elseif ($canScore): ?>
                                <?php adminRenderScoreForm($m); ?>
                            <?php else: ?>
                                <div class="ops-muted" style="margin-bottom:0.35rem">
                                    Déjà scoré
                                    <?php if ($m['score_home'] !== null && $m['score_away'] !== null): ?>
                                        · <?= (int) $m['score_home'] ?>–<?= (int) $m['score_away'] ?>
                                    <?php endif; ?>
                                </div>
                                <form method="post"
                                      onsubmit="return confirm('Effacer ce score, retirer les points et rouvrir les pronos ?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="clear_match_score">
                                    <input type="hidden" name="match_id" value="<?= $mid ?>">
                                    <button type="submit" class="ops-btn ops-btn-sm">Effacer le score</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td class="ops-td-actions">
                            <?php if ($alreadyCancelled): ?>
                                <span class="ops-muted">—</span>
                            <?php else: ?>
                                <?php adminRenderCancelForm($m, true); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="ops-panel" id="file">
    <div class="ops-panel-head">File d’attente</div>
    <div class="ops-panel-body">
        <p class="ops-muted">
            Matchs qui attendent encore un score ou un recalcul.
            Sans score : <span class="ops-mono"><?= (int) $stuckSummary['total'] ?></span>
            · Récupérables API (≤ <?= (int) SCORES_CATCHUP_DAYS ?> j) :
            <span class="ops-mono"><?= (int) $stuckSummary['api_window'] ?></span>
            · Trop vieux (saisie manuelle) :
            <span class="ops-mono"><?= (int) $stuckSummary['too_old'] ?></span>
            · Données indispo. : <span class="ops-mono"><?= count($voidedMatches) ?></span>
            · Reportés : <span class="ops-mono"><?= count($postponedMatches) ?></span>
            <?php if ($postponedMatches !== []): ?>
                · <a href="#reportes">Voir les reportés ↓</a>
            <?php endif; ?>
        </p>
        <div class="ops-form-row" style="margin-bottom:0.85rem; flex-wrap:wrap; gap:0.5rem;">
            <form method="post"
                  onsubmit="return confirm('Rattraper via API toutes les ligues bloquées (≤ <?= (int) SCORES_CATCHUP_DAYS ?> j) ?\nEnviron <?= (int) $stuckSummary['sports_api'] ?> ligue(s) ≈ <?= (int) $stuckSummary['credits_est'] ?> crédits.');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="catchup_scores">
                <button type="submit" class="ops-btn ops-btn-primary"
                    <?= (int) $stuckSummary['sports_api'] === 0 ? 'disabled' : '' ?>>
                    Rattrapage API multi-ligues
                    (<?= (int) $stuckSummary['sports_api'] ?> ligue<?= (int) $stuckSummary['sports_api'] > 1 ? 's' : '' ?>
                    · ~<?= (int) $stuckSummary['credits_est'] ?> crédits)
                </button>
            </form>
            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="score_local">
                <button type="submit" class="ops-btn">Points locaux (0 crédit)</button>
            </form>
        </div>
        <p class="ops-muted" style="margin-top:0">
            Les matchs <strong>trop vieux</strong> (&gt; <?= (int) SCORES_CATCHUP_DAYS ?> j) ne sont plus dans l’API :
            saisie manuelle ou annulation. Le cron horaire traite jusqu’à
            <?= (int) SCORES_MAX_SPORTS_BACKLOG ?> ligues s’il y a du retard.
        </p>

        <?php if ($needScore === [] && $voidedMatches === []): ?>
            <p class="ops-muted" style="margin-bottom:0">Rien en attente (hors reportés).</p>
        <?php else: ?>
        <div class="ops-table-wrap ops-table-wrap--scores">
            <table class="ops-table ops-table--scores">
                <thead>
                    <tr>
                        <th>Sport</th>
                        <th>Match</th>
                        <th>Quand</th>
                        <th>Qui a prono</th>
                        <th>Score</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($voidedMatches as $m): ?>
                    <?php $mid = (int) $m['id']; ?>
                    <tr>
                        <td>
                            <?= e(sportCategoryLabel((string) ($m['sport'] ?? ''))) ?>
                            <div class="ops-sub"><?= e((string) ($m['competition'] ?: 'Données indisponibles')) ?></div>
                            <div><span class="ops-badge ops-badge--warn">indispo.</span></div>
                        </td>
                        <td>
                            <strong><?= e($m['equipe_home'] . ' – ' . $m['equipe_away']) ?></strong>
                            <div class="ops-mono ops-sub">#<?= $mid ?></div>
                        </td>
                        <td class="ops-mono ops-nowrap"><?= e(formatMatchWhen($m['date_match'])) ?></td>
                        <td class="ops-td-preds"><?php adminRenderMatchPredictors($predsByMatch[$mid] ?? []); ?></td>
                        <td class="ops-td-score"><?php adminRenderScoreForm($m); ?></td>
                        <td></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php foreach ($needScoreApi as $m): ?>
                    <?php $mid = (int) $m['id']; ?>
                    <tr>
                        <td>
                            <?= e(sportCategoryLabel((string) ($m['sport'] ?? ''))) ?>
                            <div class="ops-sub"><?= e((string) ($m['competition'] ?: 'Sans score API')) ?></div>
                            <div><span class="ops-badge">API ≤ <?= (int) SCORES_CATCHUP_DAYS ?> j</span></div>
                        </td>
                        <td>
                            <strong><?= e($m['equipe_home'] . ' – ' . $m['equipe_away']) ?></strong>
                            <div class="ops-mono ops-sub">#<?= $mid ?></div>
                        </td>
                        <td class="ops-mono ops-nowrap"><?= e(formatMatchWhen($m['date_match'])) ?></td>
                        <td class="ops-td-preds"><?php adminRenderMatchPredictors($predsByMatch[$mid] ?? []); ?></td>
                        <td class="ops-td-score"><?php adminRenderScoreForm($m); ?></td>
                        <td class="ops-td-actions"><?php adminRenderPostponeControls($m); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php foreach ($needScoreOld as $m): ?>
                    <?php $mid = (int) $m['id']; ?>
                    <tr>
                        <td>
                            <?= e(sportCategoryLabel((string) ($m['sport'] ?? ''))) ?>
                            <div class="ops-sub"><?= e((string) ($m['competition'] ?: 'Hors fenêtre API')) ?></div>
                            <div><span class="ops-badge ops-badge--warn">trop vieux</span></div>
                        </td>
                        <td>
                            <strong><?= e($m['equipe_home'] . ' – ' . $m['equipe_away']) ?></strong>
                            <div class="ops-mono ops-sub">#<?= $mid ?></div>
                        </td>
                        <td class="ops-mono ops-nowrap"><?= e(formatMatchWhen($m['date_match'])) ?></td>
                        <td class="ops-td-preds"><?php adminRenderMatchPredictors($predsByMatch[$mid] ?? []); ?></td>
                        <td class="ops-td-score"><?php adminRenderScoreForm($m); ?></td>
                        <td class="ops-td-actions"><?php adminRenderPostponeControls($m); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="ops-panel" id="reportes">
    <div class="ops-panel-head">Matchs reportés</div>
    <div class="ops-panel-body">
        <p class="ops-muted">
            Ils restent ici tant que tu n’as pas saisi le score ou réactivé le match.
            Tu peux changer la date, puis entrer le score quand le match a enfin été joué.
            <strong>The Odds API</strong> ne remonte les scores que sur ~3 jours :
            au-delà, saisie manuelle (Flashscore, etc.).
        </p>
        <div class="ops-form-row" style="margin-bottom:0.85rem; flex-wrap:wrap; gap:0.5rem;">
            <form method="post"
                  onsubmit="return confirm('Interroger l’API pour les reportés des 3 derniers jours ? (crédits /scores)');">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="recover_postponed_scores">
                <button type="submit" class="ops-btn ops-btn-primary">Récupérer scores API (reportés ≤ 3 j)</button>
            </form>
            <form method="post"
                  onsubmit="return confirm('Retirer de la liste tous les reportés sans aucun prono ?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="dismiss_empty_postponed">
                <button type="submit" class="ops-btn">Nettoyer reportés sans prono</button>
            </form>
            <form method="post"
                  onsubmit="return confirm('Réactiver les reportés dont la date est encore dans le futur ?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="reactivate_future_postponed">
                <button type="submit" class="ops-btn">Réactiver reportés futurs</button>
            </form>
        </div>
        <?php if ($postponedMatches === []): ?>
            <p class="ops-muted" style="margin-bottom:0">Aucun match reporté pour le moment.</p>
        <?php else: ?>
        <div class="ops-table-wrap ops-table-wrap--scores">
            <table class="ops-table ops-table--scores">
                <thead>
                    <tr>
                        <th>Sport</th>
                        <th>Match</th>
                        <th>Date prévue</th>
                        <th>Qui a prono</th>
                        <th>Score</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($postponedMatches as $m): ?>
                    <?php
                    $mid = (int) $m['id'];
                    $dateVal = matchDatetimeLocalValue((string) ($m['date_match'] ?? ''));
                    ?>
                    <tr>
                        <td>
                            <?= e(sportCategoryLabel((string) ($m['sport'] ?? ''))) ?>
                            <div><span class="ops-badge ops-badge--warn">reporté</span></div>
                        </td>
                        <td>
                            <strong><?= e($m['equipe_home'] . ' – ' . $m['equipe_away']) ?></strong>
                            <div class="ops-sub"><?= e((string) ($m['competition'] ?: '—')) ?></div>
                            <div class="ops-mono ops-sub">#<?= $mid ?></div>
                        </td>
                        <td class="ops-td-date">
                            <div class="ops-mono ops-nowrap"><?= e(formatMatchWhen($m['date_match'])) ?></div>
                            <form method="post" class="ops-postpone-date-form">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="postpone_set_date">
                                <input type="hidden" name="match_id" value="<?= $mid ?>">
                                <input class="ops-input ops-input-datetime" type="datetime-local" name="new_date" value="<?= e($dateVal) ?>" required>
                                <button type="submit" class="ops-btn ops-btn-sm">Date</button>
                            </form>
                        </td>
                        <td class="ops-td-preds"><?php adminRenderMatchPredictors($predsByMatch[$mid] ?? []); ?></td>
                        <td class="ops-td-score"><?php adminRenderScoreForm($m, true); ?></td>
                        <td class="ops-td-actions">
                            <form method="post" onsubmit="return confirm('Réactiver ce match (à venir) et rouvrir les pronos ?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="postpone_reactivate">
                                <input type="hidden" name="match_id" value="<?= $mid ?>">
                                <input type="hidden" name="new_date" value="<?= e($dateVal) ?>">
                                <button type="submit" class="ops-btn ops-btn-sm">Réactiver</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="ops-panel" id="points-locaux">
    <div class="ops-panel-head">Score déjà en base → donner les points</div>
    <div class="ops-panel-body">
        <p class="ops-muted">
            Score connu, pronos encore « en attente ». Un clic, 0 crédit API.
        </p>
        <?php if ($needPoints === []): ?>
            <p class="ops-muted" style="margin-bottom:0.75rem">Rien à faire.</p>
        <?php else: ?>
        <div class="ops-table-wrap ops-table-wrap--scores" style="margin-bottom:0.75rem">
            <table class="ops-table ops-table--scores">
                <thead>
                    <tr>
                        <th>Match</th>
                        <th>Score</th>
                        <th>Quand</th>
                        <th>Qui a prono</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($needPoints as $m): ?>
                    <?php $mid = (int) $m['id']; ?>
                    <tr>
                        <td>
                            <strong><?= e($m['equipe_home'] . ' – ' . $m['equipe_away']) ?></strong>
                            <div class="ops-mono ops-sub">#<?= $mid ?></div>
                        </td>
                        <td class="ops-mono ops-nowrap"><?= (int) $m['score_home'] ?>–<?= (int) $m['score_away'] ?></td>
                        <td class="ops-mono ops-nowrap"><?= e(formatMatchWhen($m['date_match'])) ?></td>
                        <td class="ops-td-preds"><?php adminRenderMatchPredictors($predsByMatch[$mid] ?? []); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="score_local">
            <button type="submit" class="ops-btn ops-btn-primary" <?= $needPoints === [] ? 'disabled' : '' ?>>
                Donner les points (0 crédit)
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('.ops-pens-toggle').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var id = cb.getAttribute('data-pens-for');
            var sel = document.querySelector('.ops-pens-winner[data-pens-select="' + id + '"]');
            if (!sel) return;
            sel.disabled = !cb.checked;
            if (!cb.checked) sel.value = '';
        });
    });
})();
</script>
<?php adminLayoutEnd(); ?>
