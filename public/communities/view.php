<?php
require __DIR__ . '/../../app/bootstrap.php';
requireLogin();

$pdo = getPDO();
$user = currentUser($pdo);

$communityId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM communities WHERE id = ?');
$stmt->execute([$communityId]);
$community = decryptCommunityRow($stmt->fetch() ?: []);

if (!$community || empty($community['id'])) {
    header('Location: ' . url('communities/index.php'));
    exit;
}

$stmt = $pdo->prepare('SELECT role FROM community_members WHERE community_id = ? AND user_id = ?');
$stmt->execute([$communityId, $user['id']]);
$membership = $stmt->fetch();

if (!$membership) {
    ?>
    <!DOCTYPE html>
    <html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
    <head><?php layoutHead($community['nom'], true, seoPage('community', [
        'title'       => $community['nom'] . ' — Prognoz',
        'description' => t('com.not_member_seo'),
        'path'        => 'communities/view.php?id=' . $communityId,
    ])); ?></head>
    <body>
    <?php layoutTopbar($user, 'communities'); ?>
    <div class="app-main">
        <div class="alert alert-error"><?= e(t('com.not_member')) ?></div>
        <a href="<?= e(url('index.php')) ?>" class="btn btn-primary"><?= e(t('common.back_matches')) ?></a>
    </div>
    </body></html>
    <?php
    exit;
}

$isAdmin = userCanManageCommunity($pdo, $communityId, (int) $user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    if (!csrfCheck()) {
        flash('error', t('common.session_expired'));
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_description') {
            $description = trim($_POST['description'] ?? '');
            if (mb_strlen($description) > 255) {
                flash('error', t('com.desc_too_long'));
            } else {
                $stmt = $pdo->prepare('UPDATE communities SET description = ? WHERE id = ?');
                $stmt->execute([
                    $description !== '' ? encryptSensitive($description) : '',
                    $communityId,
                ]);
                flash('success', t('com.desc_updated'));
            }
            header('Location: ' . url('communities/view.php?id=' . $communityId));
            exit;
        }

        if ($action === 'kick_member') {
            $targetId = (int) ($_POST['user_id'] ?? 0);
            if ($targetId <= 0) {
                flash('error', t('com.member_invalid'));
            } elseif ($targetId === (int) $user['id']) {
                flash('error', t('com.kick_self'));
            } else {
                $stmt = $pdo->prepare(
                    'SELECT role FROM community_members WHERE community_id = ? AND user_id = ?'
                );
                $stmt->execute([$communityId, $targetId]);
                $target = $stmt->fetch();

                if (!$target) {
                    flash('error', t('com.kick_gone'));
                } elseif ($target['role'] === 'moderateur') {
                    flash('error', t('com.kick_admin'));
                } else {
                    $stmt = $pdo->prepare(
                        'DELETE FROM community_members WHERE community_id = ? AND user_id = ? AND role = "membre"'
                    );
                    $stmt->execute([$communityId, $targetId]);
                    flash('success', t('com.kicked'));
                }
            }
            header('Location: ' . url('communities/view.php?id=' . $communityId));
            exit;
        }
    }
}

$classement = fetchCommunitySeasonLeaderboard($pdo, $communityId);
$activeSeason = getActiveSeason($pdo);
$closedSeasons = fetchClosedSeasons($pdo);
$viewSeasonId = (int) ($_GET['season'] ?? 0);
$viewSeason = null;

if ($viewSeasonId > 0) {
    $viewSeason = getSeasonById($pdo, $viewSeasonId);
    if (!$viewSeason) {
        $viewSeasonId = 0;
    } else {
        $classement = fetchCommunitySeasonLeaderboard($pdo, $communityId, $viewSeasonId);
    }
}

$isArchiveView = $viewSeason && !empty($viewSeason['cloturee']);
$lastRewards = fetchUserSeasonRewards($pdo, (int) $user['id'], 3);

$stmt = $pdo->prepare('SELECT code_invite FROM community_invites WHERE community_id = ? ORDER BY created_at DESC LIMIT 1');
$stmt->execute([$communityId]);
$invite = $stmt->fetch();

if (!$community['est_generale'] && !$invite && $isAdmin) {
    do {
        $code = strtoupper(bin2hex(random_bytes(8)));
        $check = $pdo->prepare('SELECT COUNT(*) FROM community_invites WHERE code_invite = ?');
        $check->execute([$code]);
    } while ((int) $check->fetchColumn() > 0);
    $stmt = $pdo->prepare(
        'INSERT INTO community_invites (community_id, code_invite, cree_par, usages_max) VALUES (?, ?, ?, 0)'
    );
    $stmt->execute([$communityId, $code, $user['id']]);
    $invite = ['code_invite' => $code];
}

$members = $isAdmin ? fetchCommunityMembers($pdo, $communityId) : [];
$activityFeed = fetchCommunityActivityFeed($pdo, $communityId);

// Recharger la communauté après éventuelle modification
$stmt = $pdo->prepare('SELECT * FROM communities WHERE id = ?');
$stmt->execute([$communityId]);
$community = decryptCommunityRow($stmt->fetch() ?: []);
?>
<!DOCTYPE html>
<html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
<head>
    <?php
    $communityDisplayName = !empty($community['est_generale']) ? t('com.general_name') : (string) $community['nom'];
    layoutHead($communityDisplayName, true, seoPage('community', [
        'title'       => $communityDisplayName . ' — Prognoz',
        'description' => t('seo.community.desc'),
        'path'        => 'communities/view.php?id=' . $communityId,
    ]));
    ?>
</head>
<body>

<?php layoutTopbar($user, 'communities'); ?>

<div class="app-main">
    <?php layoutFlashes(); ?>
    <h2 class="page-title">
        <?= e(!empty($community['est_generale']) ? t('com.general_name') : $community['nom']) ?>
        <?php if ($community['est_generale']): ?><span class="community-badge-generale"><?= e(t('com.general')) ?></span><?php endif; ?>
    </h2>
    <?php if ($isArchiveView): ?>
        <p class="page-sub season-archive-label"><?= e(t('com.archive_label', ['date' => formatSeasonFin($viewSeason['fin'] ?? '')])) ?></p>
    <?php elseif ($community['description']): ?>
        <p class="page-sub"><?= e($community['description']) ?></p>
    <?php endif; ?>
    <?php renderSeasonBanner($isArchiveView ? null : $activeSeason, 'community'); ?>

    <div class="grid-2">
        <div>
            <div class="panel panel-spaced">
                <div class="panel-head panel-head-split">
                    <span><?php if ($activeSeason && !$isArchiveView): ?><?= e(t('com.ranking_season')) ?><?php else: ?><?= e(t('com.ranking')) ?><?php endif; ?></span>
                    <?php if ($activeSeason || !empty($closedSeasons)): ?>
                    <form method="get" class="season-select-form">
                        <input type="hidden" name="id" value="<?= (int) $communityId ?>">
                        <label class="sr-only" for="seasonSelect"><?= e(t('nav.season_pts')) ?></label>
                        <select name="season" id="seasonSelect" class="field-input field-input-sm" onchange="this.form.submit()">
                            <?php if ($activeSeason): ?>
                            <option value=""<?= $viewSeasonId === 0 ? ' selected' : '' ?>><?= e(t('season.current')) ?></option>
                            <?php endif; ?>
                            <?php foreach ($closedSeasons as $closed): ?>
                            <option value="<?= (int) $closed['id'] ?>"<?= $viewSeasonId === (int) $closed['id'] ? ' selected' : '' ?>>
                                <?= e(t('com.season_ended', ['date' => formatSeasonFin($closed['fin'] ?? '')])) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="panel-body panel-body-leaderboard">
                    <?php foreach ($classement as $i => $m): ?>
                        <?php renderLeaderboardRow($m, $i + 1, (int) $user['id']); ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php renderCommunityActivityFeed($activityFeed); ?>

            <?php if (!empty($lastRewards)): ?>
            <div class="panel panel-spaced">
                <div class="panel-head"><?= e(t('com.recent_badges')) ?></div>
                <div class="panel-body">
                    <ul class="season-rewards-list">
                        <?php foreach ($lastRewards as $reward): ?>
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
            </div>
            <?php endif; ?>

            <?php if (!$community['est_generale'] && $invite):
                renderCommunityInviteShare(
                    absoluteInviteUrl($invite['code_invite']),
                    $communityDisplayName
                );
            endif; ?>

            <?php if ($isAdmin): ?>
            <div class="panel panel-spaced">
                <div class="panel-head"><?= e(t('com.admin')) ?><?php if ((int) ($community['createur_id'] ?? 0) === (int) $user['id']): ?> · <?= e(t('com.creator')) ?><?php endif; ?></div>
                <div class="panel-body">
                    <form method="post" class="community-admin-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_description">
                        <div class="field-group">
                            <label class="field-label" for="communityDescription"><?= e(t('com.description')) ?></label>
                            <textarea name="description" id="communityDescription" class="field-textarea" rows="2" maxlength="255" placeholder="<?= e(t('com.desc_placeholder')) ?>"><?= e($community['description'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><?= e(t('com.save_desc')) ?></button>
                    </form>

                    <?php if (!empty($members)): ?>
                    <div class="community-members-admin">
                        <h3 class="community-members-title"><?= e(t('com.members_title', ['n' => count($members)])) ?></h3>
                        <ul class="community-members-list">
                            <?php foreach ($members as $m): ?>
                            <li class="community-member-row">
                                <div class="community-member-info">
                                    <?php renderUserProfileLink((int) $m['id'], (string) $m['pseudo'], 'sm', false, $m['avatar_url'] ?? null); ?>
                                    <a href="<?= e(userProfileUrl((int) $m['id'])) ?>" class="community-member-name"><?php renderCosmeticPseudo((string) $m['pseudo'], $m['equipped_name'] ?? null); ?></a>
                                    <?php if (isSiteAdminUser((int) $m['id'])): ?>
                                        <?= adminBadgeHtml() ?>
                                    <?php endif; ?>
                                    <?php if ($m['role'] === 'moderateur'): ?>
                                        <span class="community-member-badge"><?= e(t('com.role_admin')) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($m['role'] === 'membre' && (int) $m['id'] !== (int) $user['id']): ?>
                                <form method="post" class="community-kick-form" onsubmit="return confirm(<?= json_encode(t('com.kick_confirm', ['name' => $m['pseudo']]), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) ?>);">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="kick_member">
                                    <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm btn-danger-text" title="<?= e(t('com.kick')) ?>">
                                        <i class="fa-solid fa-user-minus" aria-hidden="true"></i> <?= e(t('com.kick')) ?>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="panel chat-panel">
            <div class="panel-head"><?= e(t('com.chat_title')) ?></div>
            <div class="chat-messages" id="chatMessages"></div>
            <div class="chat-typing" id="chatTyping" hidden aria-live="polite"></div>
            <form class="chat-form" id="chatForm">
                <input type="text" id="chatInput" placeholder="<?= e(t('com.chat_input')) ?>" maxlength="500" autocomplete="off">
                <button type="submit" class="chat-send-btn" title="<?= e(t('com.chat_send')) ?>">
                    <?php if (function_exists('wantsRetroUi') && wantsRetroUi()): ?>
                        <?= e(t('com.chat_send')) ?>
                    <?php else: ?>
                        <i class="fa-solid fa-paper-plane"></i><span class="sr-only"><?= e(t('com.chat_send')) ?></span>
                    <?php endif; ?>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    var COMMUNITY_ID = <?= (int) $communityId ?>;
    var CURRENT_USER_ID = <?= (int) $user['id'] ?>;
    window.CURRENT_USER_PSEUDO = <?= json_encode($user['pseudo'], JSON_UNESCAPED_UNICODE) ?>;
    window.CURRENT_USER_AVATAR = <?= json_encode(avatarPublicUrl($user['avatar_url'] ?? null), JSON_UNESCAPED_UNICODE) ?>;
    window.PRONO_CSRF = <?= json_encode(csrfToken()) ?>;
    window.PRONO_API = <?= json_encode(url('api/')) ?>;
    window.PRONO_DASHBOARD_URL = <?= json_encode(url('account/dashboard.php'), JSON_UNESCAPED_UNICODE) ?>;
    window.PRONO_CHAT_I18N = {
        typing_one: <?= json_encode(t('com.chat_typing_one'), JSON_UNESCAPED_UNICODE) ?>,
        typing_two: <?= json_encode(t('com.chat_typing_two'), JSON_UNESCAPED_UNICODE) ?>,
        typing_many: <?= json_encode(t('com.chat_typing_many'), JSON_UNESCAPED_UNICODE) ?>
    };
</script>
<script src="<?= e(url('assets/js/chat.js')) ?>"></script>
<?php layoutFooter(); ?>
</body>
</html>
