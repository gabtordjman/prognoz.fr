<?php
require __DIR__ . '/../../app/bootstrap.php';
requireLogin();

$pdo = getPDO();
$user = currentUser($pdo);
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        flash('error', t('common.session_expired'));
        header('Location: ' . url('account/dashboard.php'));
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'upload_avatar') {
            if (empty($_FILES['avatar']) || !is_array($_FILES['avatar'])) {
                throw new InvalidArgumentException(t('avatar.err.upload'));
            }
            uploadUserAvatar($pdo, $userId, $_FILES['avatar']);
            flash('success', t('avatar.ok_upload'));
        } elseif ($action === 'remove_avatar') {
            removeUserAvatar($pdo, $userId);
            flash('success', t('avatar.ok_remove'));
        } elseif ($action === 'save_profile_extras') {
            updateUserProfileExtras(
                $pdo,
                $userId,
                (string) ($_POST['bio'] ?? ''),
                (string) ($_POST['sport_favori'] ?? ''),
                (string) ($_POST['equipe_favorie'] ?? ''),
                [
                    (string) ($_POST['equipe_nationale_1'] ?? ''),
                    (string) ($_POST['equipe_nationale_2'] ?? ''),
                    (string) ($_POST['equipe_nationale_3'] ?? ''),
                ]
            );
            clearCurrentUserCache();
            flash('success', t('dash.profile_saved'));
        }
    } catch (InvalidArgumentException $e) {
        flash('error', $e->getMessage());
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    } catch (Throwable $e) {
        error_log('Prognoz avatar upload: ' . $e->getMessage());
        flash('error', t('avatar.err.generic'));
    }
    header('Location: ' . url('account/dashboard.php'));
    exit;
}

$user = currentUser($pdo);
$userId = (int) $user['id'];
$hasAvatar = avatarPublicUrl($user['avatar_url'] ?? null) !== null;

$stmt = $pdo->prepare(
    "SELECT c.id, c.nom, c.est_generale,
            (SELECT COUNT(*) FROM community_members cm2 WHERE cm2.community_id = c.id) AS nb_membres
     FROM community_members cm
     INNER JOIN communities c ON c.id = cm.community_id
     WHERE cm.user_id = ?
     ORDER BY c.est_generale DESC, c.id ASC"
);
$stmt->execute([$userId]);
$communautes = array_map(function (array $row): array {
    return decryptCommunityRow($row, false);
}, $stmt->fetchAll());

$pronosEnCours = countUserOpenTicketPredictions($pdo, $userId);
$pronosAwaitingResult = countUserAwaitingResultPredictions($pdo, $userId);

$savedTicketRows = getUserTicket($pdo, $userId);
$savedTicketItems = array_map('ticketItemToArray', $savedTicketRows);
$savedTicketGain = 0;
foreach ($savedTicketItems as $item) {
    $savedTicketGain += (int) $item['points'];
}

$stats = getUserPredictionStats($pdo, $userId);
$history = getUserPredictionHistory($pdo, $userId, 20);
$friendCount = countAcceptedFriends($pdo, $userId);
$activeSeason = getActiveSeason($pdo);
$seasonPoints = $activeSeason ? getUserGeneralSeasonPoints($pdo, $userId, (int) $activeSeason['id']) : 0;
$seasonRewards = fetchUserSeasonRewards($pdo, $userId, 5);
$favClubChoices = listFavoriteClubChoices($pdo);
$favNationalChoices = listFavoriteNationalChoices();
$currentFavClub = userFavoriteClub($user);
$currentFavNationals = userFavoriteNationals($user);
if ($currentFavClub !== null && !in_array($currentFavClub, $favClubChoices, true)) {
    array_unshift($favClubChoices, $currentFavClub);
}
foreach ($currentFavNationals as $nat) {
    if (!in_array($nat, $favNationalChoices, true)) {
        array_unshift($favNationalChoices, $nat);
    }
}
while (count($currentFavNationals) < (int) FAV_TEAMS_MAX) {
    $currentFavNationals[] = '';
}
?>
<!DOCTYPE html>
<html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
<head>
    <?php layoutHead(t('dash.title'), true, seoPage('dashboard')); ?>
</head>
<body>

<?php layoutTopbar($user, 'dashboard'); ?>

<div class="app-main app-main--espace">
    <?php layoutFlashes(); ?>

    <header class="dash-head">
        <div class="dash-id">
            <div class="dash-id-photo">
                <?php renderUserAvatar($user['pseudo'], 'lg', $user['avatar_url'] ?? null); ?>
                <form method="post" enctype="multipart/form-data" class="dash-avatar-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="upload_avatar">
                    <label class="dash-avatar-cam" title="<?= e($hasAvatar ? t('avatar.change') : t('avatar.add')) ?>">
                        <?php if (function_exists('wantsRetroUi') && wantsRetroUi()): ?>
                            <span class="dash-avatar-cam-label"><?= e($hasAvatar ? t('avatar.change') : t('avatar.add')) ?></span>
                        <?php else: ?>
                            <i class="fa-solid fa-camera" aria-hidden="true"></i>
                        <?php endif; ?>
                        <span class="sr-only"><?= e($hasAvatar ? t('avatar.change') : t('avatar.add')) ?></span>
                        <input type="file" id="dashAvatarPick" name="avatar" accept="image/jpeg,image/png,image/webp" required class="dash-avatar-input">
                    </label>
                </form>
                <?php if ($hasAvatar): ?>
                <form method="post" class="dash-avatar-remove-form" onsubmit="return confirm(<?= json_encode(t('avatar.remove_confirm'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) ?>);">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="remove_avatar">
                    <button type="submit" class="dash-avatar-remove" title="<?= e(t('avatar.remove')) ?>">
                        <?php if (function_exists('wantsRetroUi') && wantsRetroUi()): ?>
                            <span><?= e(t('avatar.remove')) ?></span>
                        <?php else: ?>
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        <?php endif; ?>
                        <span class="sr-only"><?= e(t('avatar.remove')) ?></span>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <div class="dash-id-copy">
                <h2 class="page-title dash-id-title"><?= e(t('dash.hello', ['name' => $user['pseudo']])) ?></h2>
                <p class="page-sub dash-id-sub">
                    <?= e($pronosEnCours === 1
                        ? t('dash.sub_pending_one', ['n' => $pronosEnCours])
                        : t('dash.sub_pending_other', ['n' => $pronosEnCours])) ?>
                    <?php if ($pronosAwaitingResult > 0): ?>
                        · <?= e($pronosAwaitingResult === 1
                            ? t('dash.sub_awaiting_one', ['n' => $pronosAwaitingResult])
                            : t('dash.sub_awaiting_other', ['n' => $pronosAwaitingResult])) ?>
                    <?php endif; ?>
                </p>
                <?php
                $favLbl = userFavoriteSportLabel($user['sport_favori'] ?? null);
                $bioTxt = trim((string) ($user['bio'] ?? ''));
                if ($favLbl !== '' || $bioTxt !== ''):
                ?>
                <p class="dash-id-bio">
                    <?php if ($favLbl !== ''): ?>
                        <span class="dash-fav-sport"><?= e($favLbl) ?></span>
                    <?php endif; ?>
                    <?php if ($bioTxt !== ''): ?>
                        <?= $favLbl !== '' ? ' · ' : '' ?><?= e($bioTxt) ?>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <nav class="dash-links" aria-label="<?= e(t('dash.title')) ?>">
            <a href="<?= e(url('account/friends.php')) ?>" class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-user-group" aria-hidden="true"></i> <?= e(t('dash.my_friends')) ?>
            </a>
            <a href="<?= e(userProfileUrl($userId)) ?>" class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-id-card" aria-hidden="true"></i> <?= e(t('dash.profile')) ?>
            </a>
            <a href="<?= e(url('account/settings.php')) ?>" class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-gear" aria-hidden="true"></i> <?= e(t('dash.settings')) ?>
            </a>
        </nav>
    </header>

    <section class="panel dash-stats-panel" aria-label="<?= e(t('dash.title')) ?>">
        <div class="dash-stats">
            <a class="dash-stat" href="<?= e(url('index.php')) ?>" title="<?= e(t('dash.pending_hint')) ?>">
                <span class="dash-stat-val"><?= (int) $pronosEnCours ?></span>
                <span class="dash-stat-lbl"><?= e(t('dash.pending')) ?></span>
            </a>
            <div class="dash-stat" title="<?= e(t('dash.season_hint')) ?>">
                <span class="dash-stat-val"><?= (int) $seasonPoints ?></span>
                <span class="dash-stat-lbl"><?= e(t('dash.season')) ?></span>
            </div>
            <div class="dash-stat" title="<?= e(t('dash.points_hint')) ?>">
                <span class="dash-stat-val"><?= (int) $user['points_totaux'] ?></span>
                <span class="dash-stat-lbl"><?= e(t('dash.points')) ?></span>
            </div>
            <a class="dash-stat" href="<?= e(url('account/friends.php')) ?>">
                <span class="dash-stat-val"><?= (int) $friendCount ?></span>
                <span class="dash-stat-lbl"><?= e(t('dash.my_friends')) ?></span>
            </a>
            <a class="dash-stat" href="<?= e(url('communities/index.php')) ?>">
                <span class="dash-stat-val"><?= count($communautes) ?></span>
                <span class="dash-stat-lbl"><?= e(t('com.mine')) ?></span>
            </a>
        </div>
        <?php if ($stats['total'] > 0): ?>
        <div class="dash-stats dash-stats--perf">
            <div class="dash-stat">
                <span class="dash-stat-val"><?= (int) $stats['rate'] ?>%</span>
                <span class="dash-stat-lbl"><?= e(t('dash.rate')) ?></span>
            </div>
            <div class="dash-stat">
                <span class="dash-stat-val"><?= (int) $stats['wins'] ?>/<?= (int) $stats['total'] ?></span>
                <span class="dash-stat-lbl"><?= e(t('dash.wins')) ?></span>
            </div>
            <div class="dash-stat" title="<?= e(t('dash.pts_hist_hint')) ?>">
                <span class="dash-stat-val"><?= (int) $stats['points'] ?></span>
                <span class="dash-stat-lbl"><?= e(t('dash.pts_hist')) ?></span>
            </div>
            <div class="dash-stat">
                <span class="dash-stat-val"><?= (int) $user['serie_en_cours'] ?></span>
                <span class="dash-stat-lbl"><?= e(t('dash.streak_now')) ?></span>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <?php renderSeasonBanner($activeSeason, 'dashboard'); ?>

    <section class="panel dash-profile-panel" aria-label="<?= e(t('dash.personalize')) ?>">
        <div class="panel-head"><?= e(t('dash.personalize')) ?></div>
        <div class="panel-body dash-profile-body">
            <form method="post" class="dash-profile-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_profile_extras">
                <div class="dash-profile-field dash-profile-field--bio">
                    <label class="field-label" for="dashBio"><?= e(t('dash.bio')) ?></label>
                    <textarea class="field-input" id="dashBio" name="bio" rows="2" maxlength="200"
                              placeholder="<?= e(t('dash.bio_ph')) ?>"><?= e((string) ($user['bio'] ?? '')) ?></textarea>
                </div>
                <div class="dash-profile-field dash-profile-field--sport">
                    <label class="field-label" for="dashFavSport"><?= e(t('dash.fav_sport')) ?></label>
                    <select class="field-input" id="dashFavSport" name="sport_favori">
                        <option value=""><?= e(t('dash.fav_sport_none')) ?></option>
                        <option value="soccer" <?= ($user['sport_favori'] ?? '') === 'soccer' ? 'selected' : '' ?>><?= e(t('sport.soccer')) ?></option>
                        <option value="basketball" <?= ($user['sport_favori'] ?? '') === 'basketball' ? 'selected' : '' ?>><?= e(t('sport.basketball')) ?></option>
                        <option value="tennis" <?= ($user['sport_favori'] ?? '') === 'tennis' ? 'selected' : '' ?>><?= e(t('sport.tennis')) ?></option>
                    </select>
                </div>
                <div class="dash-profile-field dash-profile-field--team">
                    <label class="field-label" for="dashFavClub"><?= e(t('dash.fav_club')) ?></label>
                    <select class="field-input" id="dashFavClub" name="equipe_favorie">
                        <option value=""><?= e(t('dash.fav_team_none')) ?></option>
                        <?php foreach ($favClubChoices as $teamName): ?>
                            <option value="<?= e($teamName) ?>" <?= $currentFavClub === $teamName ? 'selected' : '' ?>><?= e($teamName) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="dash-profile-hint"><?= e(t('dash.fav_club_hint')) ?></p>
                </div>
                <div class="dash-profile-field dash-profile-field--nationals">
                    <span class="field-label"><?= e(t('dash.fav_nationals')) ?></span>
                    <div class="dash-fav-teams">
                        <?php for ($fi = 0; $fi < (int) FAV_TEAMS_MAX; $fi++):
                            $slotVal = (string) ($currentFavNationals[$fi] ?? '');
                            $slotId = 'dashFavNat' . ($fi + 1);
                            $slotName = 'equipe_nationale_' . ($fi + 1);
                        ?>
                        <div class="dash-fav-team-slot">
                            <label class="field-label field-label--sub" for="<?= e($slotId) ?>"><?= e(t('dash.fav_national_n', ['n' => $fi + 1])) ?></label>
                            <select class="field-input" id="<?= e($slotId) ?>" name="<?= e($slotName) ?>">
                                <option value=""><?= e(t('dash.fav_team_none')) ?></option>
                                <?php foreach ($favNationalChoices as $teamName): ?>
                                    <option value="<?= e($teamName) ?>" <?= $slotVal === $teamName ? 'selected' : '' ?>><?= e($teamName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <p class="dash-profile-hint"><?= e(t('dash.fav_nationals_hint')) ?></p>
                </div>
                <div class="dash-profile-actions">
                    <button type="submit" class="btn btn-primary btn-sm"><?= e(t('dash.save_profile')) ?></button>
                </div>
            </form>
        </div>
    </section>

    <?php renderOnboardingChecklist($pdo, $user); ?>

    <?php renderStreakBanner((int) $user['serie_en_cours']); ?>
    <?php if ((int) $user['serie_en_cours'] === 0): ?>
    <script>try { localStorage.setItem('prognoz_streak_last', '0'); } catch (e) { /* ignore */ }</script>
    <?php endif; ?>

    <?php if (!empty($seasonRewards)): ?>
    <section class="panel panel-spaced">
        <div class="panel-head"><?= e(t('dash.season_badges')) ?></div>
        <div class="panel-body">
            <ul class="season-rewards-list">
                <?php foreach ($seasonRewards as $reward): ?>
                <li class="season-reward-item">
                    <?php renderSeasonRewardBadge($reward); ?>
                    <span class="season-reward-meta">
                        <strong><?= e($reward['community_name']) ?></strong>
                        <span><?php
                            $streak = (int) ($reward['streak'] ?? 1);
                            if ($streak >= 2) {
                                echo e(t('season.streak_meta', ['n' => $streak]));
                            } else {
                                echo e(t('com.top_n', ['n' => (int) $reward['classement']]));
                            }
                        ?></span>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?>

    <div class="dashboard-grid">
        <div class="panel">
            <div class="panel-head"><?= e(t('com.mine')) ?></div>
            <div class="panel-body">
                <?php if (empty($communautes)): ?>
                    <p class="empty-msg"><?= e(t('dash.no_com')) ?></p>
                <?php endif; ?>
                <?php foreach ($communautes as $c): ?>
                    <a href="<?= e(url('communities/view.php?id=' . (int) $c['id'])) ?>" class="community-card">
                        <div>
                            <div class="cc-name"><?= e(!empty($c['est_generale']) ? t('com.general_name') : $c['nom']) ?><?php if ($c['est_generale']): ?><span class="community-badge-generale"><?= e(t('com.general')) ?></span><?php endif; ?></div>
                            <div class="cc-meta"><?= e(t('com.members_paren', ['n' => (int) $c['nb_membres']])) ?></div>
                        </div>
                        <i class="fa-solid fa-chevron-right" style="color:var(--muted);"></i>
                    </a>
                <?php endforeach; ?>
                <div class="dashboard-quick-links">
                    <a href="<?= e(url('communities/index.php')) ?>" class="btn btn-primary btn-block">
                        <i class="fa-solid fa-plus"></i> <?= e(t('com.title')) ?>
                    </a>
                    <a href="<?= e(url('account/friends.php#add-friend')) ?>" class="btn btn-ghost btn-block">
                        <i class="fa-solid fa-user-plus"></i> <?= e(t('dash.add_friend')) ?>
                    </a>
                </div>
            </div>
        </div>

        <?php renderSavedTicket($savedTicketItems, $savedTicketGain); ?>
    </div>

    <?php if (!empty($history)): ?>
    <section class="panel history-panel" id="historyPanel">
        <div class="panel-head"><?= e(t('dash.history_title')) ?></div>
        <div class="panel-body">
            <ul class="history-list">
                <?php foreach ($history as $h):
                    $pres = predictionHistoryPresentation($h);
                    $pick = formatPickLabel($h, $h['reponse']);
                    $fresh = isFreshPredictionResult($h['resolved_at'] ?? null);
                ?>
                <li class="history-item history-item--<?= $pres['item_class'] ?><?= $fresh ? ' is-result-flash' : '' ?>">
                    <div class="history-top">
                        <span class="history-match"><?= e($h['equipe_home']) ?> – <?= e($h['equipe_away']) ?></span>
                        <span class="history-badge history-badge--<?= $pres['badge_class'] ?>">
                            <?= e($pres['badge_label']) ?>
                        </span>
                    </div>
                    <div class="history-detail">
                        <span><?= e(marketTypeLabel($h['market_type'])) ?> : <strong><?= e($pick) ?></strong></span>
                        <span class="history-result"><?= e(t('dash.result_label', ['result' => $pres['result']])) ?></span>
                    </div>
                    <?php if (!empty($h['resolved_at'])): ?>
                    <time class="history-time"><?= e(formatMatchWhen($h['resolved_at'])) ?></time>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php layoutFooter(); ?>
<script>
(function () {
    var input = document.querySelector('.dash-avatar-input');
    if (input) {
        input.addEventListener('change', function () {
            if (input.files && input.files.length) {
                input.form.submit();
            }
        });
    }
    var api = window.PRONO_API || '/api/';
    setTimeout(function () {
        fetch(api + 'point_notifications.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                mark_all_seen: true,
                csrf_token: window.PRONO_CSRF || ''
            })
        }).catch(function () {});
    }, 4000);
})();
</script>
<script src="<?= e(url('assets/js/match-effects.js')) ?>"></script>
</body>
</html>
