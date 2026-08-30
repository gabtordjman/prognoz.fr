<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

const SHOP_BG_DEFAULT = 'bg_default';
const SHOP_NAME_DEFAULT = 'name_default';

/**
 * Catalogue boutique (contenu site, pas en BDD).
 *
 * @return array<string, array{
 *   id:string, type:'bg'|'name', rarity:'common'|'rare'|'epic'|'legend',
 *   price:int, image:?string, css:string, animated?:bool
 * }>
 */
function shopCatalog(): array
{
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }

    $catalog = [
        SHOP_BG_DEFAULT => [
            'id' => SHOP_BG_DEFAULT, 'type' => 'bg', 'rarity' => 'common', 'price' => 0,
            'image' => null, 'css' => 'felt',
        ],
        'bg_brass' => [
            'id' => 'bg_brass', 'type' => 'bg', 'rarity' => 'common', 'price' => 35,
            'image' => 'assets/img/shop/shop-bg-brass.jpg', 'css' => 'brass',
        ],
        'bg_midnight' => [
            'id' => 'bg_midnight', 'type' => 'bg', 'rarity' => 'common', 'price' => 45,
            'image' => 'assets/img/shop/shop-bg-midnight.jpg', 'css' => 'midnight',
        ],
        'bg_forest' => [
            'id' => 'bg_forest', 'type' => 'bg', 'rarity' => 'common', 'price' => 55,
            'image' => 'assets/img/shop/shop-bg-forest.jpg', 'css' => 'forest',
        ],
        'bg_ember' => [
            'id' => 'bg_ember', 'type' => 'bg', 'rarity' => 'rare', 'price' => 65,
            'image' => 'assets/img/shop/shop-bg-ember.jpg', 'css' => 'ember',
        ],
        'bg_ocean' => [
            'id' => 'bg_ocean', 'type' => 'bg', 'rarity' => 'rare', 'price' => 80,
            'image' => 'assets/img/shop/shop-bg-ocean.jpg', 'css' => 'ocean',
        ],
        'bg_sunset' => [
            'id' => 'bg_sunset', 'type' => 'bg', 'rarity' => 'rare', 'price' => 95,
            'image' => 'assets/img/shop/shop-bg-sunset.jpg', 'css' => 'sunset',
        ],
        'bg_marble' => [
            'id' => 'bg_marble', 'type' => 'bg', 'rarity' => 'rare', 'price' => 120,
            'image' => 'assets/img/shop/shop-bg-marble.jpg', 'css' => 'marble',
        ],
        'bg_storm' => [
            'id' => 'bg_storm', 'type' => 'bg', 'rarity' => 'epic', 'price' => 150,
            'image' => 'assets/img/shop/shop-bg-storm.jpg', 'css' => 'storm',
        ],
        'bg_velvet' => [
            'id' => 'bg_velvet', 'type' => 'bg', 'rarity' => 'epic', 'price' => 180,
            'image' => 'assets/img/shop/shop-bg-velvet.jpg', 'css' => 'velvet',
        ],
        'bg_goldleaf' => [
            'id' => 'bg_goldleaf', 'type' => 'bg', 'rarity' => 'epic', 'price' => 320,
            'image' => 'assets/img/shop/shop-bg-goldleaf.jpg', 'css' => 'goldleaf',
        ],
        'bg_legend' => [
            'id' => 'bg_legend', 'type' => 'bg', 'rarity' => 'legend', 'price' => 1800,
            'image' => 'assets/img/shop/shop-bg-legend.jpg', 'css' => 'legend',
        ],

        SHOP_NAME_DEFAULT => [
            'id' => SHOP_NAME_DEFAULT, 'type' => 'name', 'rarity' => 'common', 'price' => 0,
            'image' => null, 'css' => 'ink', 'animated' => false,
        ],
        'name_brass' => [
            'id' => 'name_brass', 'type' => 'name', 'rarity' => 'common', 'price' => 30,
            'image' => null, 'css' => 'brass', 'animated' => false,
        ],
        'name_emerald' => [
            'id' => 'name_emerald', 'type' => 'name', 'rarity' => 'common', 'price' => 45,
            'image' => null, 'css' => 'emerald', 'animated' => false,
        ],
        'name_ice' => [
            'id' => 'name_ice', 'type' => 'name', 'rarity' => 'rare', 'price' => 55,
            'image' => null, 'css' => 'ice', 'animated' => false,
        ],
        'name_flame' => [
            'id' => 'name_flame', 'type' => 'name', 'rarity' => 'rare', 'price' => 85,
            'image' => null, 'css' => 'flame', 'animated' => true,
        ],
        'name_aurora' => [
            'id' => 'name_aurora', 'type' => 'name', 'rarity' => 'rare', 'price' => 130,
            'image' => null, 'css' => 'aurora', 'animated' => true,
        ],
        'name_neon' => [
            'id' => 'name_neon', 'type' => 'name', 'rarity' => 'epic', 'price' => 160,
            'image' => null, 'css' => 'neon', 'animated' => true,
        ],
        'name_prism' => [
            'id' => 'name_prism', 'type' => 'name', 'rarity' => 'epic', 'price' => 220,
            'image' => null, 'css' => 'prism', 'animated' => true,
        ],
        'name_legend' => [
            'id' => 'name_legend', 'type' => 'name', 'rarity' => 'legend', 'price' => 2200,
            'image' => null, 'css' => 'legend', 'animated' => true,
        ],
    ];

    return $catalog;
}

/** @return list<array<string,mixed>> */
function shopCatalogByType(string $type): array
{
    return array_values(array_filter(shopCatalog(), static fn (array $item): bool => $item['type'] === $type));
}

function shopItem(string $id): ?array
{
    return shopCatalog()[$id] ?? null;
}

function shopItemName(array $item): string
{
    return t('shop.item.' . $item['id'] . '.name');
}

function shopItemDesc(array $item): string
{
    return t('shop.item.' . $item['id'] . '.desc');
}

function shopRarityLabel(string $rarity): string
{
    return t('shop.rarity.' . $rarity);
}

function shopItemImageUrl(array $item): ?string
{
    $rel = (string) ($item['image'] ?? '');
    if ($rel === '') {
        return null;
    }
    $abs = dirname(__DIR__) . '/public/' . $rel;
    if (!is_file($abs)) {
        return null;
    }

    return assetUrl($rel);
}

function ensureShopSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $addUserCol = static function (PDO $pdo, string $name, string $ddl): void {
        try {
            $col = $pdo->query('SHOW COLUMNS FROM users LIKE ' . $pdo->quote($name))->fetch();
            if (!$col) {
                $pdo->exec('ALTER TABLE users ADD COLUMN ' . $ddl);
            }
        } catch (Throwable $e) {
            // ignore
        }
    };

    $addUserCol($pdo, 'shop_balance', 'shop_balance INT NOT NULL DEFAULT 0 AFTER points_totaux');
    $addUserCol($pdo, 'equipped_bg', "equipped_bg VARCHAR(64) NOT NULL DEFAULT 'bg_default' AFTER shop_balance");
    $addUserCol($pdo, 'equipped_name', "equipped_name VARCHAR(64) NOT NULL DEFAULT 'name_default' AFTER equipped_bg");

    try {
        $col = $pdo->query('SHOW COLUMNS FROM seasons LIKE "shop_locked"')->fetch();
        if (!$col) {
            $pdo->exec('ALTER TABLE seasons ADD COLUMN shop_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER cloturee');
        }
    } catch (Throwable $e) {
        // ignore
    }

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS user_cosmetics (
                user_id INT NOT NULL,
                cosmetic_id VARCHAR(64) NOT NULL,
                purchased_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, cosmetic_id),
                KEY idx_user_cosmetics_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (Throwable $e) {
        // ignore
    }

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS shop_ledger (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                amount INT NOT NULL,
                reason VARCHAR(32) NOT NULL,
                ref VARCHAR(64) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_shop_ledger_user (user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (Throwable $e) {
        // ignore
    }
}

/** Verrouille les saisons déjà closes qui n'ont pas encore versé le solde boutique. */
function maintainShopLocks(PDO $pdo): void
{
    ensureShopSchema($pdo);
    try {
        $stmt = $pdo->query('SELECT id FROM seasons WHERE cloturee = 1 AND shop_locked = 0 ORDER BY id ASC');
        if (!$stmt) {
            return;
        }
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $seasonId) {
            lockSeasonShopPoints($pdo, (int) $seasonId);
        }
    } catch (Throwable $e) {
        error_log('Prognoz shop locks: ' . $e->getMessage());
    }
}

/**
 * Verse les points Générale d'une saison close dans le solde boutique (idempotent).
 */
function lockSeasonShopPoints(PDO $pdo, int $seasonId): bool
{
    if ($seasonId <= 0) {
        return false;
    }
    ensureShopSchema($pdo);
    $generalId = getGeneralCommunityId($pdo);
    if (!$generalId) {
        return false;
    }

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }
    try {
        $lock = $pdo->prepare('SELECT shop_locked FROM seasons WHERE id = ? FOR UPDATE');
        $lock->execute([$seasonId]);
        $row = $lock->fetch();
        if (!$row) {
            if ($ownTx) {
                $pdo->rollBack();
            }
            return false;
        }
        if ((int) ($row['shop_locked'] ?? 0) === 1) {
            if ($ownTx) {
                $pdo->commit();
            }
            return true;
        }

        $scores = $pdo->prepare(
            'SELECT user_id, points FROM season_scores
             WHERE season_id = ? AND community_id = ? AND points > 0'
        );
        $scores->execute([$seasonId, $generalId]);

        $credit = $pdo->prepare('UPDATE users SET shop_balance = shop_balance + ? WHERE id = ?');
        $ledger = $pdo->prepare(
            'INSERT INTO shop_ledger (user_id, amount, reason, ref) VALUES (?, ?, ?, ?)'
        );
        $ref = 'season:' . $seasonId;
        foreach ($scores->fetchAll() as $score) {
            $pts = (int) $score['points'];
            $uid = (int) $score['user_id'];
            if ($pts <= 0 || $uid <= 0) {
                continue;
            }
            $credit->execute([$pts, $uid]);
            $ledger->execute([$uid, $pts, 'season_lock', $ref]);
        }

        $pdo->prepare('UPDATE seasons SET shop_locked = 1 WHERE id = ?')->execute([$seasonId]);
        if ($ownTx) {
            $pdo->commit();
        }

        return true;
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Prognoz lockSeasonShopPoints: ' . $e->getMessage());
        return false;
    }
}

function shopBalance(array $user): int
{
    return max(0, (int) ($user['shop_balance'] ?? 0));
}

function shopEquippedBg(array $user): string
{
    $id = (string) ($user['equipped_bg'] ?? SHOP_BG_DEFAULT);
    $item = shopItem($id);

    return ($item && $item['type'] === 'bg') ? $id : SHOP_BG_DEFAULT;
}

function shopEquippedName(array $user): string
{
    $id = (string) ($user['equipped_name'] ?? SHOP_NAME_DEFAULT);
    $item = shopItem($id);

    return ($item && $item['type'] === 'name') ? $id : SHOP_NAME_DEFAULT;
}

/** @return list<string> */
function shopOwnedIds(PDO $pdo, int $userId): array
{
    ensureShopSchema($pdo);
    $stmt = $pdo->prepare('SELECT cosmetic_id FROM user_cosmetics WHERE user_id = ?');
    $stmt->execute([$userId]);

    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function shopUserOwns(array $item, array $ownedIds): bool
{
    if ((int) ($item['price'] ?? 0) <= 0) {
        return true;
    }

    return in_array((string) $item['id'], $ownedIds, true);
}

function purchaseShopItem(PDO $pdo, int $userId, string $cosmeticId): string
{
    ensureShopSchema($pdo);
    $item = shopItem($cosmeticId);
    if (!$item) {
        throw new InvalidArgumentException(t('shop.err.unknown'));
    }
    if ((int) $item['price'] <= 0) {
        throw new InvalidArgumentException(t('shop.err.free'));
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT shop_balance FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $balance = $stmt->fetchColumn();
        if ($balance === false) {
            throw new InvalidArgumentException(t('shop.err.user'));
        }
        $owned = $pdo->prepare('SELECT 1 FROM user_cosmetics WHERE user_id = ? AND cosmetic_id = ?');
        $owned->execute([$userId, $cosmeticId]);
        if ($owned->fetch()) {
            throw new InvalidArgumentException(t('shop.err.owned'));
        }
        $price = (int) $item['price'];
        if ((int) $balance < $price) {
            throw new InvalidArgumentException(t('shop.err.funds'));
        }

        $pdo->prepare('UPDATE users SET shop_balance = shop_balance - ? WHERE id = ?')
            ->execute([$price, $userId]);
        $pdo->prepare('INSERT INTO user_cosmetics (user_id, cosmetic_id) VALUES (?, ?)')
            ->execute([$userId, $cosmeticId]);
        $pdo->prepare('INSERT INTO shop_ledger (user_id, amount, reason, ref) VALUES (?, ?, ?, ?)')
            ->execute([$userId, -$price, 'purchase', $cosmeticId]);

        $typeCol = $item['type'] === 'bg' ? 'equipped_bg' : 'equipped_name';
        $pdo->prepare("UPDATE users SET {$typeCol} = ? WHERE id = ?")
            ->execute([$cosmeticId, $userId]);

        $pdo->commit();
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Prognoz shop purchase: ' . $e->getMessage());
        throw new RuntimeException(t('shop.err.generic'));
    }

    return t('shop.flash.bought', ['name' => shopItemName($item)]);
}

function equipShopItem(PDO $pdo, int $userId, string $cosmeticId): string
{
    ensureShopSchema($pdo);
    $item = shopItem($cosmeticId);
    if (!$item) {
        throw new InvalidArgumentException(t('shop.err.unknown'));
    }
    if ((int) $item['price'] > 0) {
        $owned = $pdo->prepare('SELECT 1 FROM user_cosmetics WHERE user_id = ? AND cosmetic_id = ?');
        $owned->execute([$userId, $cosmeticId]);
        if (!$owned->fetch()) {
            throw new InvalidArgumentException(t('shop.err.not_owned'));
        }
    }

    $col = $item['type'] === 'bg' ? 'equipped_bg' : 'equipped_name';
    $pdo->prepare("UPDATE users SET {$col} = ? WHERE id = ?")->execute([$cosmeticId, $userId]);

    return t('shop.flash.equipped', ['name' => shopItemName($item)]);
}

function profileBgClass(?string $bgId): string
{
    $item = shopItem((string) $bgId);
    $css = ($item && $item['type'] === 'bg') ? (string) $item['css'] : 'felt';

    return 'profile-bg profile-bg--' . $css;
}

function cosmeticNameClass(?string $nameId): string
{
    $item = shopItem((string) $nameId);
    if (!$item || ($item['type'] ?? '') !== 'name') {
        return 'cos-name cos-name--ink';
    }
    $css = (string) ($item['css'] ?? 'ink');
    $anim = !empty($item['animated']) ? ' cos-name--animated' : '';

    return 'cos-name cos-name--' . $css . $anim;
}

function renderCosmeticPseudo(string $pseudo, ?string $nameId, string $extraClass = ''): void
{
    $class = trim(cosmeticNameClass($nameId) . ' ' . $extraClass);
    echo '<span class="' . e($class) . '">' . e($pseudo) . '</span>';
}

/** Salut + pseudo cosmétique, sans casser la traduction. */
function renderCosmeticHello(string $pseudo, ?string $nameId): void
{
    $marker = "\u{E000}";
    $parts = explode($marker, t('dash.hello', ['name' => $marker]), 2);
    echo e($parts[0] ?? '');
    renderCosmeticPseudo($pseudo, $nameId);
    echo e($parts[1] ?? '');
}

function shopHasCustomBg(array $user): bool
{
    return shopEquippedBg($user) !== SHOP_BG_DEFAULT;
}

/** Classes du bandeau profil / dashboard / boutique. */
function profileHeaderClass(array $user, bool $forceShowcase = false): string
{
    $bg = shopEquippedBg($user);
    $showcase = $forceShowcase || $bg !== SHOP_BG_DEFAULT;
    $class = 'dash-head';
    if ($showcase) {
        $class .= ' profile-showcase ' . profileBgClass($bg);
    }

    return $class;
}

/**
 * Badges de palier sur les points totaux (100 / 500).
 *
 * @return list<array{id:string, threshold:int, variant:string, image:?string}>
 */
function userMilestoneBadges(int $pointsTotaux): array
{
    $defs = [
        [
            'id' => 'pts_100',
            'threshold' => 100,
            'variant' => 'cent',
            'image' => 'assets/img/shop/shop-badge-100.jpg',
        ],
        [
            'id' => 'pts_500',
            'threshold' => 500,
            'variant' => 'veteran',
            'image' => 'assets/img/shop/shop-badge-500.jpg',
        ],
    ];
    $earned = [];
    foreach ($defs as $def) {
        if ($pointsTotaux >= (int) $def['threshold']) {
            $earned[] = $def;
        }
    }

    return $earned;
}

function milestoneBadgeName(array $badge): string
{
    return t('shop.badge.' . $badge['id'] . '.name');
}

function milestoneBadgeDesc(array $badge): string
{
    return t('shop.badge.' . $badge['id'] . '.desc');
}

function renderMilestoneBadge(array $badge): void
{
    $imgRel = (string) ($badge['image'] ?? '');
    $imgAbs = $imgRel !== '' ? dirname(__DIR__) . '/public/' . $imgRel : '';
    $imgUrl = ($imgAbs !== '' && is_file($imgAbs)) ? assetUrl($imgRel) : null;
    $label = milestoneBadgeName($badge);
    $title = $label . ' — ' . milestoneBadgeDesc($badge);
    ?>
    <span class="milestone-badge milestone-badge--<?= e((string) $badge['variant']) ?>" title="<?= e($title) ?>">
        <?php if ($imgUrl): ?>
            <img class="milestone-badge__img" src="<?= e($imgUrl) ?>" alt="" width="36" height="36">
        <?php endif; ?>
        <span class="milestone-badge__label"><?= e($label) ?></span>
    </span>
    <?php
}
