<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/**
 * Cabine d'essayage — sticker joueur (felt / laiton), maillot + short + prop emoji.
 * Palettes clubs sans écusson ni logo sponsor.
 */

/** Couleur peau (bras / jambes). */
const KIT_SKIN_COLOR = '#c4a07a';

/** Maillot neutre (pas de torse nu). */
const KIT_PLAIN_JERSEY_FILL = '#1e3d2f';

/** Short par défaut — toujours un vrai short, jamais de sous-vêtement. */
const KIT_DEFAULT_SHORTS = 'shorts_black';

/**
 * Catalogue des maillots.
 *
 * @return array<string, array{
 *   id:string, pattern:string, c1:string, c2?:string, trim:bool, trimColor?:string,
 *   texture?:string, shop_item?:string
 * }>
 */
function kitJerseyCatalog(): array
{
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }

    $catalog = [
        'psg' => [
            'id' => 'psg', 'pattern' => 'solid', 'c1' => '#0a1a3c', 'trim' => true,
        ],
        'marseille' => [
            'id' => 'marseille', 'pattern' => 'solid', 'c1' => '#3aa6d9', 'trim' => true,
        ],
        'monaco' => [
            'id' => 'monaco', 'pattern' => 'split_h', 'c1' => '#c8102e', 'c2' => '#f5f2ea', 'trim' => true,
        ],
        'real_madrid' => [
            'id' => 'real_madrid', 'pattern' => 'solid', 'c1' => '#f2f2f2', 'trim' => true, 'trimColor' => '#c9a24b',
        ],
        'barcelone' => [
            'id' => 'barcelone', 'pattern' => 'stripes', 'c1' => '#a50044', 'c2' => '#004d98', 'trim' => true,
        ],
        'juventus' => [
            'id' => 'juventus', 'pattern' => 'stripes', 'c1' => '#1a1a1a', 'c2' => '#ffffff', 'trim' => true,
        ],
        'bayern' => [
            'id' => 'bayern', 'pattern' => 'solid', 'c1' => '#dc052d', 'trim' => true,
        ],
        'man_utd' => [
            'id' => 'man_utd', 'pattern' => 'solid', 'c1' => '#da020e', 'trim' => true,
        ],
        'dortmund' => [
            'id' => 'dortmund', 'pattern' => 'solid', 'c1' => '#fde100', 'trim' => true, 'trimColor' => '#1a1a1a',
        ],
        'inter' => [
            'id' => 'inter', 'pattern' => 'stripes', 'c1' => '#0d3a7a', 'c2' => '#1a1a1a', 'trim' => true,
        ],
        'chelsea' => [
            'id' => 'chelsea', 'pattern' => 'solid', 'c1' => '#034694', 'trim' => true,
        ],
        'man_city' => [
            'id' => 'man_city', 'pattern' => 'solid', 'c1' => '#6cabdd', 'trim' => true,
        ],
        'liverpool' => [
            'id' => 'liverpool', 'pattern' => 'solid', 'c1' => '#c8102e', 'trim' => true,
        ],
        'arsenal' => [
            'id' => 'arsenal', 'pattern' => 'solid', 'c1' => '#ef0107', 'trim' => true,
        ],
        'atletico' => [
            'id' => 'atletico', 'pattern' => 'stripes', 'c1' => '#cb3524', 'c2' => '#ffffff', 'trim' => true,
        ],
        'lille' => [
            'id' => 'lille', 'pattern' => 'split_v', 'c1' => '#c8102e', 'c2' => '#ffffff', 'trim' => true,
        ],
        'nantes' => [
            'id' => 'nantes', 'pattern' => 'split_v', 'c1' => '#fcd116', 'c2' => '#1a7a4c', 'trim' => true,
        ],
        'saint_etienne' => [
            'id' => 'saint_etienne', 'pattern' => 'solid', 'c1' => '#1a7a4c', 'trim' => true,
        ],
        'lens' => [
            'id' => 'lens', 'pattern' => 'stripes', 'c1' => '#8c1a1a', 'c2' => '#f2c14e', 'trim' => true,
        ],
        'rennes' => [
            'id' => 'rennes', 'pattern' => 'stripes', 'c1' => '#e2001a', 'c2' => '#1a1a1a', 'trim' => true,
        ],

        // Rétro boutique — textures tissu aplaties (pas de logos / marques).
        'france_98' => [
            'id' => 'france_98', 'pattern' => 'texture', 'c1' => '#002395', 'trim' => true,
            'trimColor' => '#f4f2ea',
            'texture' => 'assets/img/kit/kit-france-98.jpg',
            'shop_item' => 'kit_france_98',
        ],
        'france_06' => [
            'id' => 'france_06', 'pattern' => 'texture', 'c1' => '#0A2F6B', 'trim' => true,
            'trimColor' => '#E30613',
            'texture' => 'assets/img/kit/kit-france-06.jpg',
            'shop_item' => 'kit_france_06',
        ],
        'barca_09' => [
            'id' => 'barca_09', 'pattern' => 'texture', 'c1' => '#A50044', 'c2' => '#004D98', 'trim' => true,
            'trimColor' => '#F7B500',
            'texture' => 'assets/img/kit/kit-barca-09.jpg',
            'shop_item' => 'kit_barca_09',
        ],
        'ol_07' => [
            'id' => 'ol_07', 'pattern' => 'texture', 'c1' => '#f4f2ea', 'trim' => true,
            'trimColor' => '#C8102E',
            'texture' => 'assets/img/kit/kit-ol-07.jpg',
            'shop_item' => 'kit_ol_07',
        ],
        'italy_06' => [
            'id' => 'italy_06', 'pattern' => 'texture', 'c1' => '#f4f2ea', 'trim' => true,
            'trimColor' => '#0055A4',
            'texture' => 'assets/img/kit/kit-italy-06.jpg',
            'shop_item' => 'kit_italy_06',
        ],
        'brazil_02' => [
            'id' => 'brazil_02', 'pattern' => 'texture', 'c1' => '#FDD116', 'trim' => true,
            'trimColor' => '#009C3B',
            'texture' => 'assets/img/kit/kit-brazil-02.jpg',
            'shop_item' => 'kit_brazil_02',
        ],
    ];

    return $catalog;
}

/** URL publique de la texture maillot, ou null. */
function kitJerseyTextureUrl(array $jersey): ?string
{
    $rel = (string) ($jersey['texture'] ?? '');
    if ($rel === '') {
        return null;
    }
    $abs = dirname(__DIR__) . '/public/' . $rel;
    if (!is_file($abs)) {
        return null;
    }

    return assetUrl($rel);
}

/** Id boutique lié à un maillot verrouillé, ou null si gratuit. */
function kitJerseyShopItemId(array $jersey): ?string
{
    $id = (string) ($jersey['shop_item'] ?? '');

    return $id !== '' ? $id : null;
}

function kitJerseyRequiresUnlock(array $jersey): bool
{
    return kitJerseyShopItemId($jersey) !== null;
}

/**
 * @param list<string> $ownedCosmeticIds
 */
function kitUserOwnsJersey(array $jersey, array $ownedCosmeticIds): bool
{
    $shopId = kitJerseyShopItemId($jersey);
    if ($shopId === null) {
        return true;
    }

    return in_array($shopId, $ownedCosmeticIds, true);
}

/** Valeur SVG (fill=) à appliquer au maillot. Textures = couleur de base (image clipée à part). */
function kitJerseyFill(array $jersey, string $uid = ''): string
{
    $sfx = $uid !== '' ? ('_' . $uid) : '';
    switch ($jersey['pattern']) {
        case 'texture':
            return $jersey['c1'];
        case 'stripes':
            return 'url(#kitStripes_' . $jersey['id'] . $sfx . ')';
        case 'split_h':
            return 'url(#kitSplitH_' . $jersey['id'] . $sfx . ')';
        case 'split_v':
            return 'url(#kitSplitV_' . $jersey['id'] . $sfx . ')';
        default:
            return $jersey['c1'];
    }
}

/**
 * Équivalent CSS (background:) pour la pastille de sélection.
 */
function kitJerseyChip(array $jersey): string
{
    $tex = kitJerseyTextureUrl($jersey);
    if ($tex !== null) {
        return "center / cover no-repeat url('" . $tex . "')";
    }
    $c1 = $jersey['c1'];
    $c2 = $jersey['c2'] ?? $c1;
    switch ($jersey['pattern']) {
        case 'stripes':
            return "repeating-linear-gradient(90deg, {$c1} 0 7px, {$c2} 7px 14px)";
        case 'split_h':
            return "linear-gradient(180deg, {$c1} 0 50%, {$c2} 50% 100%)";
        case 'split_v':
            return "linear-gradient(90deg, {$c1} 0 50%, {$c2} 50% 100%)";
        default:
            return $c1;
    }
}

function kitJerseyTrimColor(array $jersey): string
{
    return $jersey['trimColor'] ?? '#e8d078';
}

/**
 * @return array<string, array{id:string, fill:string}>
 */
function kitShortsCatalog(): array
{
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }

    $catalog = [
        'shorts_white' => ['id' => 'shorts_white', 'fill' => '#f4ede0'],
        'shorts_black' => ['id' => 'shorts_black', 'fill' => '#211d19'],
        'shorts_navy' => ['id' => 'shorts_navy', 'fill' => '#1c2a4a'],
        'shorts_crimson' => ['id' => 'shorts_crimson', 'fill' => '#8c2a26'],
    ];

    return $catalog;
}

/**
 * @return array<string, array{id:string}>
 */
function kitPropCatalog(): array
{
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }

    $catalog = [
        'prop_phone' => ['id' => 'prop_phone'],
        'prop_cigarette' => ['id' => 'prop_cigarette'],
        'prop_can' => ['id' => 'prop_can'],
        'prop_ball' => ['id' => 'prop_ball'],
        'prop_coffee' => ['id' => 'prop_coffee'],
        'prop_beer' => ['id' => 'prop_beer'],
        'prop_wine' => ['id' => 'prop_wine'],
        'prop_trophy' => ['id' => 'prop_trophy'],
        'prop_dice' => ['id' => 'prop_dice'],
        'prop_money' => ['id' => 'prop_money'],
        'prop_pizza' => ['id' => 'prop_pizza'],
        'prop_burger' => ['id' => 'prop_burger'],
        'prop_headphones' => ['id' => 'prop_headphones'],
    ];

    return $catalog;
}

function kitPropTransform(string $propId): string
{
    if ($propId === 'prop_ball') {
        return 'translate(118, 246) scale(1.5)';
    }

    return 'translate(130, 152) scale(1.4)';
}

function kitPropEmoji(string $propId): string
{
    $map = [
        'prop_phone' => '📱',
        'prop_cigarette' => '🚬',
        'prop_can' => '🥤',
        'prop_ball' => '⚽',
        'prop_coffee' => '☕',
        'prop_beer' => '🍺',
        'prop_wine' => '🍷',
        'prop_trophy' => '🏆',
        'prop_dice' => '🎲',
        'prop_money' => '💰',
        'prop_pizza' => '🍕',
        'prop_burger' => '🍔',
        'prop_headphones' => '🎧',
    ];

    return $map[$propId] ?? '';
}

function renderKitPropShapes(string $propId): void
{
    $emoji = kitPropEmoji($propId);
    if ($emoji === '') {
        return;
    }
    ?>
    <text x="12" y="12" font-size="21" text-anchor="middle" dominant-baseline="central"><?= $emoji ?></text>
    <?php
}

function kitJersey(?string $id): ?array
{
    if ($id === null || $id === '') {
        return null;
    }

    return kitJerseyCatalog()[$id] ?? null;
}

function kitShorts(?string $id): ?array
{
    if ($id === null || $id === '') {
        return null;
    }

    return kitShortsCatalog()[$id] ?? null;
}

function kitProp(?string $id): ?array
{
    if ($id === null || $id === '') {
        return null;
    }

    return kitPropCatalog()[$id] ?? null;
}

/** Short valide, sinon défaut (jamais vide / sous-vêtement). */
function resolveKitShortsId(?string $id): string
{
    return kitShorts($id) ? (string) $id : KIT_DEFAULT_SHORTS;
}

function kitItemName(string $id): string
{
    return t('kit.item.' . $id);
}

function ensureKitSchema(PDO $pdo): void
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
            // migration manuelle possible
        }
    };

    $addUserCol($pdo, 'kit_jersey', 'kit_jersey VARCHAR(32) NULL DEFAULT NULL AFTER equipped_name');
    $addUserCol($pdo, 'kit_shorts', 'kit_shorts VARCHAR(32) NULL DEFAULT NULL AFTER kit_jersey');
    $addUserCol($pdo, 'kit_prop', 'kit_prop VARCHAR(32) NULL DEFAULT NULL AFTER kit_shorts');
}

function userKitJersey(array $user, ?array $ownedCosmeticIds = null): ?string
{
    $id = (string) ($user['kit_jersey'] ?? '');
    $jersey = kitJersey($id);
    if (!$jersey) {
        return null;
    }
    if ($ownedCosmeticIds !== null && !kitUserOwnsJersey($jersey, $ownedCosmeticIds)) {
        foreach (($user['_shop_preview_items'] ?? []) as $it) {
            if (($it['type'] ?? '') === 'kit' && (string) ($it['kit_jersey'] ?? '') === $id) {
                return $id;
            }
        }

        return null;
    }

    return $id;
}

/** Toujours un id de short catalogue. */
function userKitShorts(array $user): string
{
    return resolveKitShortsId((string) ($user['kit_shorts'] ?? ''));
}

function userKitProp(array $user): ?string
{
    $id = (string) ($user['kit_prop'] ?? '');

    return kitProp($id) ? $id : null;
}

/** Enregistre la tenue (short vide → défaut ; prop/maillot optionnels). */
function saveUserKit(PDO $pdo, int $userId, ?string $jerseyId, ?string $shortsId, ?string $propId): void
{
    ensureKitSchema($pdo);
    ensureShopSchema($pdo);

    $jerseyId = ($jerseyId !== null && $jerseyId !== '') ? $jerseyId : null;
    $shortsId = resolveKitShortsId($shortsId);
    $propId = ($propId !== null && $propId !== '') ? $propId : null;

    if ($jerseyId !== null) {
        $jersey = kitJersey($jerseyId);
        if (!$jersey) {
            throw new InvalidArgumentException(t('kit.err.unknown'));
        }
        $owned = shopOwnedIds($pdo, $userId);
        if (!kitUserOwnsJersey($jersey, $owned)) {
            throw new InvalidArgumentException(t('kit.err.locked'));
        }
    }
    if (!kitShorts($shortsId)) {
        throw new InvalidArgumentException(t('kit.err.unknown'));
    }
    if ($propId !== null && !kitProp($propId)) {
        throw new InvalidArgumentException(t('kit.err.unknown'));
    }

    $pdo->prepare('UPDATE users SET kit_jersey = ?, kit_shorts = ?, kit_prop = ? WHERE id = ?')
        ->execute([$jerseyId, $shortsId, $propId, $userId]);
}

/**
 * Joueur SVG — silhouette plus humaine.
 * Maillots texture : <image> clipée sur le torse (patterns SVG image instables).
 * $forEditor = true : IDs fixes pour le JS de la cabine ; sinon IDs uniques.
 */
function renderKitDollSvg(
    ?string $jerseyId,
    ?string $shortsId,
    ?string $avatarUrl = null,
    string $pseudo = '',
    ?string $propId = null,
    bool $forEditor = false
): void {
    $jersey = kitJersey($jerseyId);
    $shorts = kitShorts(resolveKitShortsId($shortsId));
    $uid = $forEditor ? '' : bin2hex(random_bytes(3));
    $sfx = $uid !== '' ? ('_' . $uid) : '';
    $torsoFill = $jersey !== null ? kitJerseyFill($jersey, $uid) : KIT_PLAIN_JERSEY_FILL;
    $shortsFill = $shorts['fill'] ?? kitShortsCatalog()[KIT_DEFAULT_SHORTS]['fill'];
    $collarVisible = $jersey !== null;
    $collarColor = $jersey !== null ? kitJerseyTrimColor($jersey) : '';
    $avatarSrc = avatarPublicUrl($avatarUrl);
    $texUrl = ($jersey !== null && ($jersey['pattern'] ?? '') === 'texture')
        ? kitJerseyTextureUrl($jersey)
        : null;

    $bodyPath = 'M54 78 C46 82 40 92 42 104 L40 152 C40 160 46 166 54 166'
        . ' L126 166 C134 166 140 160 140 152 L138 104 C140 92 134 82 126 78'
        . ' C116 70 104 66 90 66 C76 66 64 70 54 78 Z';
    $sleeveL = 'M54 78 C44 86 36 100 34 116 C42 110 50 100 56 90 Z';
    $sleeveR = 'M126 78 C136 86 144 100 146 116 C138 110 130 100 124 90 Z';
    $clipId = 'kitJerseyClip' . $sfx;
    $headClipId = 'kitHeadClip' . $sfx;
    $skinId = 'kitSkinGrad' . $sfx;
    $sockId = 'kitSockGrad' . $sfx;
    ?>
    <svg viewBox="0 0 180 280" class="kit-doll" role="img" aria-label="<?= e(t('kit.doll_alt')) ?>">
        <defs>
            <?php foreach (kitJerseyCatalog() as $j): ?>
                <?php if ($j['pattern'] === 'stripes'): ?>
            <pattern id="kitStripes_<?= e($j['id'] . $sfx) ?>" width="14" height="28" patternUnits="userSpaceOnUse" patternTransform="translate(40,66)">
                <rect width="14" height="28" fill="<?= e($j['c1']) ?>"></rect>
                <rect width="7" height="28" fill="<?= e($j['c2']) ?>"></rect>
            </pattern>
                <?php elseif ($j['pattern'] === 'split_h'): ?>
            <linearGradient id="kitSplitH_<?= e($j['id'] . $sfx) ?>" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="<?= e($j['c1']) ?>"></stop>
                <stop offset="48%" stop-color="<?= e($j['c1']) ?>"></stop>
                <stop offset="48%" stop-color="<?= e($j['c2']) ?>"></stop>
                <stop offset="100%" stop-color="<?= e($j['c2']) ?>"></stop>
            </linearGradient>
                <?php elseif ($j['pattern'] === 'split_v'): ?>
            <linearGradient id="kitSplitV_<?= e($j['id'] . $sfx) ?>" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stop-color="<?= e($j['c1']) ?>"></stop>
                <stop offset="50%" stop-color="<?= e($j['c1']) ?>"></stop>
                <stop offset="50%" stop-color="<?= e($j['c2']) ?>"></stop>
                <stop offset="100%" stop-color="<?= e($j['c2']) ?>"></stop>
            </linearGradient>
                <?php endif; ?>
            <?php endforeach; ?>
            <linearGradient id="<?= e($skinId) ?>" x1="0" y1="0" x2="0.35" y2="1">
                <stop offset="0%" stop-color="#e8c4a0"></stop>
                <stop offset="45%" stop-color="#d0a882"></stop>
                <stop offset="100%" stop-color="#b08968"></stop>
            </linearGradient>
            <linearGradient id="<?= e($sockId) ?>" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#f7f1e6"></stop>
                <stop offset="100%" stop-color="#d4ccbc"></stop>
            </linearGradient>
            <clipPath id="<?= e($clipId) ?>" clipPathUnits="userSpaceOnUse">
                <path d="<?= $bodyPath ?>"></path>
                <path d="<?= $sleeveL ?>"></path>
                <path d="<?= $sleeveR ?>"></path>
            </clipPath>
            <clipPath id="<?= e($headClipId) ?>">
                <ellipse cx="90" cy="40" rx="24" ry="28"></ellipse>
            </clipPath>
        </defs>

        <ellipse class="kit-shadow" cx="90" cy="268" rx="46" ry="7"></ellipse>

        <path class="kit-skin" fill="url(#<?= e($skinId) ?>)" d="M70 172 C68 196 66 218 64 236 L78 238 C80 218 82 196 84 172 Z"></path>
        <path class="kit-skin" fill="url(#<?= e($skinId) ?>)" d="M96 172 C98 196 100 218 102 236 L116 238 C114 218 112 196 110 172 Z"></path>

        <path class="kit-sock" fill="url(#<?= e($sockId) ?>)" d="M64 226 h16 v20 h-17 z"></path>
        <path class="kit-sock" fill="url(#<?= e($sockId) ?>)" d="M100 226 h16 v20 h-15 z"></path>

        <path class="kit-boot" d="M58 244 q3 -7 13 -7 h13 q9 0 13 9 v5 h-41 z"></path>
        <path class="kit-boot" d="M96 244 q3 -7 13 -7 h13 q9 0 13 9 v5 h-41 z"></path>
        <path class="kit-boot-sole" d="M56 255 h40 v4 q0 3 -3 3 h-34 q-3 0 -3 -3 z"></path>
        <path class="kit-boot-sole" d="M94 255 h40 v4 q0 3 -3 3 h-34 q-3 0 -3 -3 z"></path>

        <g data-kit-role="shorts"<?= $forEditor ? ' id="kitShortsGroup"' : '' ?> style="fill: <?= e($shortsFill) ?>;">
            <path d="M60 154 C56 154 54 158 54 163 L52 192 C52 198 56 202 62 202 L78 202 L84 180 L90 180 L96 202 L118 202 C124 202 128 198 128 192 L126 163 C126 158 124 154 120 154 Z"></path>
        </g>
        <path class="kit-shorts-shade" d="M84 156 v26 L90 180 L96 156 Z"></path>

        <path class="kit-skin" fill="url(#<?= e($skinId) ?>)" d="M48 86 C36 104 28 128 28 152 C28 158 33 162 39 160 C48 132 52 108 58 92 Z"></path>
        <ellipse class="kit-skin" fill="url(#<?= e($skinId) ?>)" cx="34" cy="162" rx="9" ry="7"></ellipse>
        <path class="kit-skin" fill="url(#<?= e($skinId) ?>)" d="M132 86 C144 104 152 128 152 152 C152 158 147 162 141 160 C132 132 128 108 122 92 Z"></path>
        <ellipse class="kit-skin" fill="url(#<?= e($skinId) ?>)" cx="146" cy="162" rx="9" ry="7"></ellipse>

        <g data-kit-role="torso"<?= $forEditor ? ' id="kitTorsoGroup"' : '' ?> style="fill: <?= e($torsoFill) ?>;">
            <path d="<?= $bodyPath ?>"></path>
            <path d="<?= $sleeveL ?>"></path>
            <path d="<?= $sleeveR ?>"></path>
        </g>

        <g clip-path="url(#<?= e($clipId) ?>)">
            <image data-kit-role="tex"<?= $forEditor ? ' id="kitJerseyTexImg"' : '' ?>
                   class="kit-jersey-tex"
                   href="<?= $texUrl !== null ? e($texUrl) : '' ?>"
                   xlink:href="<?= $texUrl !== null ? e($texUrl) : '' ?>"
                   x="38" y="64" width="104" height="108"
                   preserveAspectRatio="xMidYMid slice"
                   style="<?= $texUrl !== null ? '' : 'display: none;' ?>"></image>
        </g>

        <g pointer-events="none" clip-path="url(#<?= e($clipId) ?>)">
            <path class="kit-jersey-shade" d="M90 68 L90 166 L126 166 C132 166 136 160 136 154 L134 104 C132 84 116 70 90 68 Z"></path>
            <path class="kit-jersey-fold" d="M72 92 C78 110 80 130 78 150" fill="none"></path>
            <path class="kit-jersey-fold" d="M108 92 C102 110 100 130 102 150" fill="none"></path>
        </g>

        <path class="kit-skin" fill="url(#<?= e($skinId) ?>)" d="M81 56 C81 50 85 46 90 46 C95 46 99 50 99 56 L97 70 L83 70 Z"></path>

        <path data-kit-role="collar"<?= $forEditor ? ' id="kitCollarShape"' : '' ?> class="kit-collar-shape"
              style="<?= $collarVisible ? 'fill: ' . e($collarColor) . ';' : 'display: none;' ?>"
              d="M80 70 L90 86 L100 70 L96 68 L90 80 L84 68 Z"></path>

        <?php if ($avatarSrc !== null): ?>
        <image href="<?= e($avatarSrc) ?>" xlink:href="<?= e($avatarSrc) ?>" x="66" y="12" width="48" height="56"
               preserveAspectRatio="xMidYMid slice" clip-path="url(#<?= e($headClipId) ?>)"></image>
        <ellipse cx="90" cy="40" rx="24" ry="28" fill="none" class="kit-head-ring"></ellipse>
        <?php else: ?>
        <ellipse cx="90" cy="40" rx="24" ry="28" style="fill: <?= e(userAvatarColor($pseudo)) ?>;"></ellipse>
        <text x="90" y="46" text-anchor="middle" class="kit-head-initials"><?= e(userInitials($pseudo)) ?></text>
        <ellipse cx="90" cy="40" rx="24" ry="28" fill="none" class="kit-head-ring"></ellipse>
        <?php endif; ?>

        <g data-kit-role="props"<?= $forEditor ? ' id="kitPropStage"' : '' ?>>
            <?php foreach (kitPropCatalog() as $p): ?>
            <g class="kit-prop-look" data-kit-prop-id="<?= e($p['id']) ?>"
               transform="<?= e(kitPropTransform($p['id'])) ?>"
               style="<?= $propId === $p['id'] ? '' : 'display: none;' ?>">
                <?php renderKitPropShapes($p['id']); ?>
            </g>
            <?php endforeach; ?>
        </g>
    </svg>
    <?php
}


function renderKitDollCard(array $user, bool $isSelf): void
{
    $owned = null;
    try {
        $owned = shopOwnedIds(getPDO(), (int) ($user['id'] ?? 0));
    } catch (Throwable $e) {
        $owned = [];
    }
    $jerseyId = userKitJersey($user, $owned);
    $shortsId = userKitShorts($user);
    $propId = userKitProp($user);
    ?>
    <section class="panel panel-spaced kit-card">
        <div class="panel-head"><?= e(t('kit.card_title')) ?></div>
        <div class="panel-body kit-card-body">
            <div class="kit-card-stage">
                <?php renderKitDollSvg($jerseyId, $shortsId, $user['avatar_url'] ?? null, (string) ($user['pseudo'] ?? ''), $propId); ?>
            </div>
            <?php if ($isSelf): ?>
            <a href="<?= e(url('account/dashboard.php')) ?>" class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-shirt" aria-hidden="true"></i> <?= e(t('kit.open_btn')) ?>
            </a>
            <?php endif; ?>
        </div>
    </section>
    <?php
}

function renderKitButtonAndDialog(array $user): void
{
    $pdo = getPDO();
    $owned = shopOwnedIds($pdo, (int) ($user['id'] ?? 0));
    $jerseyId = userKitJersey($user, $owned);
    $shortsId = userKitShorts($user);
    $propId = userKitProp($user);
    $shopUrl = url('account/shop.php?tab=kit');
    ?>
    <button type="button" class="btn btn-ghost btn-sm" id="kitOpenBtn" aria-haspopup="dialog" aria-controls="kitDialog">
        <i class="fa-solid fa-shirt" aria-hidden="true"></i> <?= e(t('kit.open_btn')) ?>
    </button>

    <div class="kit-dialog" id="kitDialog" role="dialog" aria-modal="true" aria-labelledby="kitDialogTitle" hidden>
        <div class="kit-dialog-backdrop" id="kitDialogBackdrop"></div>
        <div class="kit-dialog-card">
            <button type="button" class="kit-dialog-close" id="kitDialogClose" aria-label="<?= e(t('common.close')) ?>">&times;</button>
            <h2 class="kit-dialog-title" id="kitDialogTitle"><?= e(t('kit.dialog_title')) ?></h2>
            <p class="kit-dialog-sub"><?= e(t('kit.dialog_sub')) ?></p>

            <div class="kit-body">
                <div class="kit-stage">
                    <?php renderKitDollSvg($jerseyId, $shortsId, $user['avatar_url'] ?? null, (string) ($user['pseudo'] ?? ''), $propId, true); ?>
                </div>

                <div class="kit-picker">
                    <div class="kit-picker-group">
                        <h3 class="kit-picker-title"><?= e(t('kit.section_jersey')) ?></h3>
                        <div class="kit-swatches" id="kitJerseySwatches" role="group" aria-label="<?= e(t('kit.section_jersey')) ?>">
                            <button type="button" class="kit-swatch kit-swatch-none<?= $jerseyId === null ? ' is-active' : '' ?>"
                                    data-kit-id="" data-kit-fill="<?= e(KIT_PLAIN_JERSEY_FILL) ?>" data-kit-trim="0" data-kit-texture=""
                                    aria-pressed="<?= $jerseyId === null ? 'true' : 'false' ?>" title="<?= e(t('kit.item.none_jersey')) ?>">
                                <span class="kit-swatch-chip" style="background: <?= e(KIT_PLAIN_JERSEY_FILL) ?>;"></span>
                                <span class="sr-only"><?= e(t('kit.item.none_jersey')) ?></span>
                            </button>
                            <?php foreach (kitJerseyCatalog() as $j):
                                $locked = !kitUserOwnsJersey($j, $owned);
                                $title = kitItemName($j['id']) . ($locked ? ' — ' . t('kit.locked_hint') : '');
                                ?>
                            <button type="button" class="kit-swatch<?= $jerseyId === $j['id'] ? ' is-active' : '' ?><?= $locked ? ' is-locked' : '' ?>"
                                    data-kit-id="<?= e($j['id']) ?>" data-kit-fill="<?= e(kitJerseyFill($j)) ?>" data-kit-trim="1"
                                    data-kit-trim-color="<?= e(kitJerseyTrimColor($j)) ?>"
                                    data-kit-texture="<?= e(kitJerseyTextureUrl($j) ?? '') ?>"
                                    <?php if ($locked): ?>data-kit-locked="1" data-kit-shop="<?= e($shopUrl) ?>"<?php endif; ?>
                                    aria-pressed="<?= $jerseyId === $j['id'] ? 'true' : 'false' ?>" title="<?= e($title) ?>">
                                <span class="kit-swatch-chip" style="background: <?= e(kitJerseyChip($j)) ?>;"></span>
                                <?php if ($locked): ?>
                                <span class="kit-swatch-lock" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
                                <?php endif; ?>
                                <span class="sr-only"><?= e($title) ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <p class="kit-retro-hint"><?= e(t('kit.retro_hint')) ?>
                            <a href="<?= e($shopUrl) ?>"><?= e(t('kit.retro_shop')) ?></a>
                        </p>
                    </div>

                    <div class="kit-picker-group">
                        <h3 class="kit-picker-title"><?= e(t('kit.section_shorts')) ?></h3>
                        <div class="kit-swatches" id="kitShortsSwatches" role="group" aria-label="<?= e(t('kit.section_shorts')) ?>">
                            <?php foreach (kitShortsCatalog() as $s): ?>
                            <button type="button" class="kit-swatch<?= $shortsId === $s['id'] ? ' is-active' : '' ?>"
                                    data-kit-id="<?= e($s['id']) ?>" data-kit-fill="<?= e($s['fill']) ?>"
                                    aria-pressed="<?= $shortsId === $s['id'] ? 'true' : 'false' ?>" title="<?= e(kitItemName($s['id'])) ?>">
                                <span class="kit-swatch-chip" style="background: <?= e($s['fill']) ?>;"></span>
                                <span class="sr-only"><?= e(kitItemName($s['id'])) ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="kit-picker-group">
                        <h3 class="kit-picker-title"><?= e(t('kit.section_prop')) ?></h3>
                        <div class="kit-swatches" id="kitPropSwatches" role="group" aria-label="<?= e(t('kit.section_prop')) ?>">
                            <button type="button" class="kit-swatch kit-swatch-none<?= $propId === null ? ' is-active' : '' ?>"
                                    data-kit-id=""
                                    aria-pressed="<?= $propId === null ? 'true' : 'false' ?>" title="<?= e(t('kit.item.none_prop')) ?>">
                                <i class="fa-solid fa-ban" aria-hidden="true"></i>
                            </button>
                            <?php foreach (kitPropCatalog() as $p): ?>
                            <button type="button" class="kit-swatch<?= $propId === $p['id'] ? ' is-active' : '' ?>"
                                    data-kit-id="<?= e($p['id']) ?>"
                                    aria-pressed="<?= $propId === $p['id'] ? 'true' : 'false' ?>" title="<?= e(kitItemName($p['id'])) ?>">
                                <svg viewBox="0 0 24 24" class="kit-swatch-icon" aria-hidden="true"><?php renderKitPropShapes($p['id']); ?></svg>
                                <span class="sr-only"><?= e(kitItemName($p['id'])) ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <p class="kit-save-note" id="kitSaveNote" role="status" aria-live="polite"
               data-msg-saving="<?= e(t('kit.saving')) ?>"
               data-msg-saved="<?= e(t('kit.saved')) ?>"
               data-msg-error="<?= e(t('kit.err.generic')) ?>"
               data-msg-locked="<?= e(t('kit.err.locked')) ?>"></p>
        </div>
    </div>
    <?php
}
