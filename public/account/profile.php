<?php
require __DIR__ . '/../../app/bootstrap.php';
requireLogin();

$pdo = getPDO();
$viewer = currentUser($pdo);
$viewerId = (int) $viewer['id'];

$targetId = (int) ($_GET['id'] ?? 0);
if ($targetId <= 0 && !empty($_GET['pseudo'])) {
    $found = findUserByPseudo($pdo, trim((string) $_GET['pseudo']));
    $targetId = $found ? (int) $found['id'] : 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        flash('error', 'Session expirée, merci de réessayer.');
        header('Location: ' . userProfileUrl($targetId > 0 ? $targetId : $viewerId));
        exit;
    }
    $action = $_POST['action'] ?? '';
    $postId = (int) ($_POST['user_id'] ?? $targetId);
    try {
        if ($action === 'add_friend') {
            $u = fetchPublicUserProfile($pdo, $postId);
            if (!$u) {
                throw new InvalidArgumentException('Joueur introuvable.');
            }
            sendFriendRequest($pdo, $viewerId, $u['pseudo']);
            flash('success', 'Demande d\'ami envoyée à ' . $u['pseudo'] . '.');
        } elseif ($action === 'accept_friend') {
            respondFriendRequest($pdo, $viewerId, (int) ($_POST['friendship_id'] ?? 0), true);
            flash('success', 'Ami ajouté.');
        }
    } catch (InvalidArgumentException $e) {
        flash('error', $e->getMessage());
    }
    header('Location: ' . userProfileUrl($postId > 0 ? $postId : $viewerId));
    exit;
}

$profile = fetchPublicUserProfile($pdo, $targetId);
if (!$profile) {
    flash('error', 'Profil introuvable.');
    header('Location: ' . url('account/friends.php'));
    exit;
}

$profileId = (int) $profile['id'];
$isSelf = $profileId === $viewerId;
$friendStatus = friendshipStatusWith($pdo, $viewerId, $profileId);
$friendship = (!$isSelf && in_array($friendStatus, ['pending_in', 'friends', 'pending_out'], true))
    ? friendshipBetween($pdo, $viewerId, $profileId)
    : null;

$activeSeason = getActiveSeason($pdo);
$seasonPoints = $activeSeason ? getUserGeneralSeasonPoints($pdo, $profileId, (int) $activeSeason['id']) : 0;
$stats = getUserPredictionStats($pdo, $profileId);
$history = getUserPredictionHistory($pdo, $profileId, 12);
$friendCount = countAcceptedFriends($pdo, $profileId);
$seasonRewards = fetchUserSeasonRewards($pdo, $profileId, 5, $viewerId);
$milestoneBadges = userMilestoneBadges((int) $profile['points_totaux']);
$profile = applyShopPreviewToUser($profile, $isSelf);
$previewItems = $profile['_shop_preview_items'] ?? [];
$equippedName = shopEquippedName($profile);
shopOverridePageBackground(shopEquippedBg($profile));
?>
<!DOCTYPE html>
<html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
<head>
    <?php layoutHead('Profil · ' . userDisplayName($profile), true, seoPage('profile', [
        'title' => 'Profil · ' . userDisplayName($profile) . ' — Prognoz',
    ])); ?>
</head>
<body>

<?php layoutTopbar($viewer, $isSelf ? 'dashboard' : 'friends'); ?>

<div class="app-main app-main--espace">
    <?php layoutFlashes(); ?>

    <?php if ($previewItems !== []): ?>
    <p class="shop-preview-banner" role="status">
        <?= e(t('shop.preview_banner', ['name' => implode(' · ', array_map('shopItemName', $previewItems))])) ?>
        <a href="<?= e(url('account/shop.php')) ?>"><?= e(t('shop.preview_back')) ?></a>
    </p>
    <?php endif; ?>

    <header class="dash-head">
        <div class="dash-id">
            <div class="dash-id-photo">
                <?php renderUserAvatar($profile['pseudo'], 'lg', $profile['avatar_url'] ?? null); ?>
            </div>
            <div class="dash-id-copy">
                <h2 class="page-title dash-id-title">
                    <?php renderCosmeticPseudo(userDisplayName($profile), $equippedName); ?>
                    <?php if (isSiteAdminUser($profileId)): ?>
                        <?= adminBadgeHtml() ?>
                    <?php endif; ?>
                </h2>
                <p class="page-sub dash-id-sub">
                    <?php if ($isSelf): ?>
                        <?= e(t('profile.self_sub')) ?>
                    <?php elseif ($friendStatus === 'friends'): ?>
                        <i class="fa-solid fa-user-check" aria-hidden="true"></i> <?= e(t('profile.already_friends')) ?>
                    <?php elseif ($friendStatus === 'pending_out'): ?>
                        <?= e(t('profile.request_sent')) ?>
                    <?php else: ?>
                        <?= e(t('profile.title')) ?>
                    <?php endif; ?>
                </p>
                <?php
                $favLbl = userFavoriteSportLabel($profile['sport_favori'] ?? null);
                $bioTxt = trim((string) ($profile['bio'] ?? ''));
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
                <?php if (!$isSelf && ($friendStatus === 'friends' || $friendStatus === 'pending_in' || $friendStatus !== 'pending_out')): ?>
                <div class="profile-head-actions">
                    <?php if ($friendStatus === 'friends'): ?>
                        <a href="<?= e(url('account/friends.php')) ?>" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-users" aria-hidden="true"></i> <?= e(t('profile.see_friends')) ?>
                        </a>
                    <?php elseif ($friendStatus === 'pending_in' && $friendship): ?>
                        <form method="post" class="inline-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="accept_friend">
                            <input type="hidden" name="user_id" value="<?= $profileId ?>">
                            <input type="hidden" name="friendship_id" value="<?= (int) $friendship['id'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa-solid fa-user-plus" aria-hidden="true"></i> <?= e(t('profile.accept_request')) ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="post" class="inline-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="add_friend">
                            <input type="hidden" name="user_id" value="<?= $profileId ?>">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa-solid fa-user-plus" aria-hidden="true"></i> <?= e(t('profile.add_friend')) ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <section class="panel dash-stats-panel" aria-label="<?= e(t('profile.title')) ?>">
        <div class="dash-stats">
            <div class="dash-stat">
                <span class="dash-stat-val"><?= (int) $seasonPoints ?></span>
                <span class="dash-stat-lbl"><?= e(t('dash.season')) ?></span>
            </div>
            <div class="dash-stat">
                <span class="dash-stat-val"><?= (int) $profile['points_totaux'] ?></span>
                <span class="dash-stat-lbl"><?= e(t('profile.points_total')) ?></span>
            </div>
            <?php if ($isSelf): ?>
            <a class="dash-stat" href="<?= e(url('account/friends.php')) ?>">
                <span class="dash-stat-val"><?= (int) $friendCount ?></span>
                <span class="dash-stat-lbl"><?= e(t('dash.my_friends')) ?></span>
            </a>
            <?php else: ?>
            <div class="dash-stat">
                <span class="dash-stat-val"><?= (int) $friendCount ?></span>
                <span class="dash-stat-lbl"><?= e(t('dash.my_friends')) ?></span>
            </div>
            <?php endif; ?>
            <div class="dash-stat">
                <span class="dash-stat-val"><?= (int) $profile['serie_en_cours'] ?></span>
                <span class="dash-stat-lbl"><?= e(t('profile.streak')) ?></span>
            </div>
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
            <div class="dash-stat">
                <span class="dash-stat-val"><?= (int) $stats['points'] ?></span>
                <span class="dash-stat-lbl"><?= e(t('dash.pts_hist')) ?></span>
            </div>
        </div>
        <?php else: ?>
        <p class="empty-msg profile-empty"><?= e(t('profile.no_resolved')) ?></p>
        <?php endif; ?>
    </section>

    <?php renderKitDollCard($profile, $isSelf); ?>

    <?php if (!empty($milestoneBadges)): ?>
    <section class="panel panel-spaced">
        <div class="panel-head"><?= e(t('shop.milestones')) ?></div>
        <div class="panel-body">
            <ul class="milestone-list">
                <?php foreach ($milestoneBadges as $badge): ?>
                <li class="milestone-item">
                    <?php renderMilestoneBadge($badge); ?>
                    <span class="milestone-meta"><?= e(milestoneBadgeDesc($badge)) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
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
                        <strong><?= e($reward['community_name'] ?? '') ?></strong>
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

    <?php if (!empty($history)): ?>
    <section class="panel history-panel" id="historyPanel">
        <div class="panel-head"><?= e(t('dash.history_title')) ?></div>
        <div class="panel-body">
            <ul class="history-list">
                <?php foreach ($history as $h):
                    $pres = predictionHistoryPresentation($h);
                    $pick = formatPickLabel($h, (string) ($h['reponse'] ?? ''));
                ?>
                <li class="history-item history-item--<?= $pres['item_class'] ?>">
                    <div class="history-top">
                        <span class="history-match"><?= e($h['equipe_home']) ?> – <?= e($h['equipe_away']) ?></span>
                        <span class="history-badge history-badge--<?= $pres['badge_class'] ?>">
                            <?= e($pres['badge_label']) ?>
                        </span>
                    </div>
                    <div class="history-detail">
                        <span><?= e(marketTypeLabel($h['market_type'] ?? '')) ?> : <strong><?= e($pick) ?></strong></span>
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
</body>
</html>
