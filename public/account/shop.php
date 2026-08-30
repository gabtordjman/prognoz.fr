<?php
require __DIR__ . '/../../app/bootstrap.php';
requireLogin();

$pdo = getPDO();
$user = currentUser($pdo);
$userId = (int) $user['id'];

$shopTabRedirect = static function (): string {
    $tab = (string) ($_POST['tab'] ?? $_GET['tab'] ?? 'all');
    if (!in_array($tab, ['all', 'bg', 'name'], true)) {
        $tab = 'all';
    }

    return url('account/shop.php' . ($tab !== 'all' ? '?tab=' . rawurlencode($tab) : ''));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        flash('error', t('common.session_expired'));
        header('Location: ' . $shopTabRedirect());
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    $itemId = (string) ($_POST['item'] ?? '');
    try {
        if ($action === 'buy') {
            flash('success', purchaseShopItem($pdo, $userId, $itemId));
        } elseif ($action === 'equip') {
            flash('success', equipShopItem($pdo, $userId, $itemId));
        } else {
            throw new InvalidArgumentException(t('shop.err.unknown'));
        }
        clearCurrentUserCache();
    } catch (InvalidArgumentException $e) {
        flash('error', $e->getMessage());
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    header('Location: ' . $shopTabRedirect());
    exit;
}

$user = currentUser($pdo);
$balance = shopBalance($user);
$ownedIds = shopOwnedIds($pdo, $userId);
$equippedBg = shopEquippedBg($user);
$equippedName = shopEquippedName($user);
$activeSeason = getActiveSeason($pdo);
$seasonPoints = $activeSeason ? getUserGeneralSeasonPoints($pdo, $userId, (int) $activeSeason['id']) : 0;
$seasonLabel = $activeSeason ? seasonCountdownLabel($activeSeason) : '';
$filter = (string) ($_GET['tab'] ?? 'all');
if (!in_array($filter, ['all', 'bg', 'name'], true)) {
    $filter = 'all';
}

$items = array_values(shopCatalog());
if ($filter !== 'all') {
    $items = array_values(array_filter($items, static fn (array $it): bool => $it['type'] === $filter));
}

$previewPseudo = (string) $user['pseudo'];
?>
<!DOCTYPE html>
<html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
<head>
    <?php layoutHead(t('shop.title'), true, seoPage('shop')); ?>
</head>
<body>

<?php layoutTopbar($user, 'shop'); ?>

<div class="app-main app-main--espace">
    <?php layoutFlashes(); ?>

    <header class="<?= e(profileHeaderClass($user, true)) ?>">
        <div class="profile-showcase-shade" aria-hidden="true"></div>
        <div class="dash-id">
            <div class="dash-id-photo">
                <?php renderUserAvatar($user['pseudo'], 'lg', $user['avatar_url'] ?? null); ?>
            </div>
            <div class="dash-id-copy">
                <h1 class="page-title dash-id-title"><?= e(t('shop.title')) ?></h1>
                <p class="page-sub dash-id-sub"><?= e(t('shop.lead')) ?></p>
            </div>
        </div>
    </header>

    <section class="shop-wallet" aria-label="<?= e(t('shop.wallet')) ?>">
        <div class="shop-wallet-main">
            <span class="shop-wallet-lbl"><?= e(t('shop.wallet')) ?></span>
            <span class="shop-wallet-val"><?= (int) $balance ?> <small><?= e(t('common.pts')) ?></small></span>
        </div>
        <div class="shop-wallet-meta">
            <p>
                <?= e(t('shop.lock_hint')) ?>
                <?php if ($seasonPoints > 0 && $seasonLabel !== ''): ?>
                    <strong><?= e(t('shop.pending', ['n' => $seasonPoints, 'when' => $seasonLabel])) ?></strong>
                <?php elseif ($seasonLabel !== ''): ?>
                    <?= e(t('shop.pending_zero', ['when' => $seasonLabel])) ?>
                <?php endif; ?>
            </p>
        </div>
    </section>

    <nav class="shop-tabs" aria-label="<?= e(t('shop.title')) ?>">
        <a class="shop-tab<?= $filter === 'all' ? ' is-active' : '' ?>" href="<?= e(url('account/shop.php')) ?>"><?= e(t('shop.tab_all')) ?></a>
        <a class="shop-tab<?= $filter === 'bg' ? ' is-active' : '' ?>" href="<?= e(url('account/shop.php?tab=bg')) ?>"><?= e(t('shop.tab_bg')) ?></a>
        <a class="shop-tab<?= $filter === 'name' ? ' is-active' : '' ?>" href="<?= e(url('account/shop.php?tab=name')) ?>"><?= e(t('shop.tab_name')) ?></a>
    </nav>

    <div class="shop-grid">
        <?php foreach ($items as $item):
            $owned = shopUserOwns($item, $ownedIds);
            $equipped = ($item['type'] === 'bg' && $item['id'] === $equippedBg)
                || ($item['type'] === 'name' && $item['id'] === $equippedName);
            $imgUrl = shopItemImageUrl($item);
            $canBuy = !$owned && $balance >= (int) $item['price'];
            ?>
        <article class="shop-card shop-card--<?= e($item['rarity']) ?><?= $equipped ? ' is-equipped' : '' ?><?= $owned ? ' is-owned' : '' ?>">
            <div class="shop-card-preview<?php if ($item['type'] === 'bg'): ?> <?= e(profileBgClass($item['id'])) ?><?php endif; ?>">
                <?php if ($item['type'] === 'bg' && $imgUrl): ?>
                    <img src="<?= e($imgUrl) ?>" alt="" class="shop-card-photo">
                <?php endif; ?>
                <?php if ($item['type'] === 'name'): ?>
                    <span class="<?= e(cosmeticNameClass($item['id'])) ?> shop-card-sample"><?= e($previewPseudo) ?></span>
                <?php endif; ?>
                <span class="shop-card-rarity shop-rarity--<?= e($item['rarity']) ?>"><?= e(shopRarityLabel($item['rarity'])) ?></span>
            </div>
            <div class="shop-card-body">
                <h2 class="shop-card-title"><?= e(shopItemName($item)) ?></h2>
                <p class="shop-card-desc"><?= e(shopItemDesc($item)) ?></p>
                <div class="shop-card-foot">
                    <?php if ((int) $item['price'] <= 0): ?>
                        <span class="shop-card-price shop-card-price--free"><?= e(t('shop.free')) ?></span>
                    <?php else: ?>
                        <span class="shop-card-price"><?= (int) $item['price'] ?> <?= e(t('common.pts')) ?></span>
                    <?php endif; ?>
                    <?php if ($equipped): ?>
                        <span class="shop-card-state"><?= e(t('shop.equipped')) ?></span>
                    <?php elseif ($owned): ?>
                        <form method="post" class="shop-card-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="equip">
                            <input type="hidden" name="item" value="<?= e($item['id']) ?>">
                            <input type="hidden" name="tab" value="<?= e($filter) ?>">
                            <button type="submit" class="btn btn-ghost btn-sm"><?= e(t('shop.equip')) ?></button>
                        </form>
                    <?php else: ?>
                        <form method="post" class="shop-card-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="buy">
                            <input type="hidden" name="item" value="<?= e($item['id']) ?>">
                            <input type="hidden" name="tab" value="<?= e($filter) ?>">
                            <button type="submit" class="btn btn-primary btn-sm" <?= $canBuy ? '' : 'disabled' ?>>
                                <?= e(t('shop.buy')) ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</div>

<?php layoutFooter(); ?>
</body>
</html>
