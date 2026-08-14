<?php
require __DIR__ . '/../../app/bootstrap.php';
requireLogin();

$pdo = getPDO();
$user = currentUser($pdo);
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        flash('error', t('common.session_expired'));
        header('Location: ' . url('account/friends.php'));
        exit;
    }
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            sendFriendRequest($pdo, $userId, trim($_POST['pseudo'] ?? ''));
            flash('success', t('friends.flash_sent'));
        } elseif ($action === 'accept') {
            respondFriendRequest($pdo, $userId, (int) ($_POST['friendship_id'] ?? 0), true);
            flash('success', t('friends.flash_added'));
        } elseif ($action === 'refuse') {
            respondFriendRequest($pdo, $userId, (int) ($_POST['friendship_id'] ?? 0), false);
            flash('success', t('friends.flash_refused'));
        } elseif ($action === 'remove') {
            removeFriend($pdo, $userId, (int) ($_POST['friendship_id'] ?? 0));
            flash('success', t('friends.flash_removed'));
        }
    } catch (InvalidArgumentException $e) {
        flash('error', $e->getMessage());
    }
    header('Location: ' . url('account/friends.php'));
    exit;
}

$friends = listAcceptedFriends($pdo, $userId);
$pending = listPendingFriendRequests($pdo, $userId);
$sent = listSentFriendRequests($pdo, $userId);
?>
<!DOCTYPE html>
<html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
<head>
    <?php layoutHead(t('friends.title'), true, seoPage('friends')); ?>
</head>
<body>

<?php layoutTopbar($user, 'friends'); ?>

<div class="app-main">
    <?php layoutFlashes(); ?>

    <div class="friends-page">
    <h2 class="page-title"><?= e(t('friends.title')) ?></h2>
    <p class="page-sub">
        <?= e(count($friends) > 1 ? t('friends.subtitle_plural', ['n' => count($friends)]) : t('friends.subtitle', ['n' => count($friends)])) ?>
    </p>

    <div class="panel panel-spaced friends-add-panel" id="add-friend">
        <div class="panel-head"><?= e(t('friends.add')) ?></div>
        <div class="panel-body">
            <p class="settings-hint"><?= e(t('friends.hint')) ?></p>
            <form method="post" class="friend-add-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add">
                <input type="text" name="pseudo" class="field-input" placeholder="<?= e(t('friends.placeholder')) ?>" required maxlength="30" autocomplete="off">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i><?= e(t('friends.invite_btn')) ?>
                </button>
            </form>
        </div>
    </div>

    <?php if (!empty($pending)): ?>
    <div class="panel panel-spaced friends-pending-panel">
        <div class="panel-head"><?= e(t('friends.requests')) ?> <span class="friends-count-pill"><?= count($pending) ?></span></div>
        <div class="panel-body friends-list">
            <?php foreach ($pending as $p): ?>
            <div class="friend-row friend-row--card">
                <div class="friend-row-main">
                    <?php renderUserProfileLink((int) $p['id'], (string) $p['pseudo'], 'md', false, $p['avatar_url'] ?? null); ?>
                    <div class="friend-row-text">
                        <a href="<?= e(userProfileUrl((int) $p['id'])) ?>" class="friend-name-link"><strong><?= e($p['pseudo']) ?></strong></a>
                        <span class="friend-meta"><span class="friend-pts"><?= (int) ($p['points_saison'] ?? 0) ?> <?= e(t('common.pts')) ?></span> <?= e(t('friends.season')) ?></span>
                    </div>
                </div>
                <div class="friend-actions">
                    <form method="post" class="inline-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="accept">
                        <input type="hidden" name="friendship_id" value="<?= (int) $p['friendship_id'] ?>">
                        <button type="submit" class="btn btn-primary btn-sm"><?= e(t('friends.accept')) ?></button>
                    </form>
                    <form method="post" class="inline-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="refuse">
                        <input type="hidden" name="friendship_id" value="<?= (int) $p['friendship_id'] ?>">
                        <button type="submit" class="btn btn-ghost btn-sm"><?= e(t('friends.refuse')) ?></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="panel friends-list-panel">
        <div class="panel-head"><?= e(t('friends.list_title')) ?></div>
        <div class="panel-body friends-list">
            <?php if (empty($friends) && empty($sent)): ?>
                <p class="empty-msg"><?= e(t('friends.empty_invite')) ?></p>
            <?php endif; ?>

            <?php foreach ($friends as $f): ?>
            <div class="friend-row friend-row--card">
                <div class="friend-row-main">
                    <?php renderUserProfileLink((int) $f['id'], (string) $f['pseudo'], 'md', false, $f['avatar_url'] ?? null); ?>
                    <div class="friend-row-text">
                        <a href="<?= e(userProfileUrl((int) $f['id'])) ?>" class="friend-name-link"><strong><?= e($f['pseudo']) ?></strong></a>
                        <span class="friend-meta">
                            <span class="friend-pts"><?= (int) ($f['points_saison'] ?? 0) ?> <?= e(t('common.pts')) ?></span> <?= e(t('friends.season')) ?>
                            <?php if ((int) $f['serie_en_cours'] >= 2): ?>
                                · <span class="friend-streak"><?= e(t('friends.streak', ['n' => (int) $f['serie_en_cours']])) ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <div class="friend-actions">
                    <a href="<?= e(userProfileUrl((int) $f['id'])) ?>" class="btn btn-ghost btn-sm"><?= e(t('friends.profile')) ?></a>
                    <form method="post" class="inline-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="friendship_id" value="<?= (int) $f['friendship_id'] ?>">
                        <button type="submit" class="btn btn-ghost btn-sm" title="<?= e(t('friends.remove')) ?>"><?= e(t('friends.remove')) ?></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (!empty($sent)): ?>
                <p class="friend-pending-title"><?= e(t('friends.pending_title')) ?></p>
                <?php foreach ($sent as $s): ?>
                <div class="friend-row friend-row--card friend-row-muted">
                    <div class="friend-row-main">
                        <?php renderUserAvatar($s['pseudo'], 'md', $s['avatar_url'] ?? null); ?>
                        <div class="friend-row-text">
                            <strong><?= e($s['pseudo']) ?></strong>
                            <span class="friend-meta"><?= e(t('friends.invite_sent')) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    </div>
</div>

<?php layoutFooter(); ?>
</body>
</html>
