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
 * @return array<string, array{id:string, pattern:string, c1:string, c2?:string, trim:bool, trimColor?:string}>
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
    ];

    return $catalog;
}

/** Valeur SVG (fill=) à appliquer au maillot. */
function kitJerseyFill(array $jersey): string
{
    switch ($jersey['pattern']) {
        case 'stripes':
            return 'url(#kitStripes_' . $jersey['id'] . ')';
        case 'split_h':
            return 'url(#kitSplitH_' . $jersey['id'] . ')';
        case 'split_v':
            return 'url(#kitSplitV_' . $jersey['id'] . ')';
        default:
            return $jersey['c1'];
    }
}

/**
 * Équivalent CSS (background:) pour la pastille de sélection.
 */
function kitJerseyChip(array $jersey): string
{
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
        return 'translate(118, 238) scale(1.55)';
    }

    return 'translate(128, 148) scale(1.45)';
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

function userKitJersey(array $user): ?string
{
    $id = (string) ($user['kit_jersey'] ?? '');

    return kitJersey($id) ? $id : null;
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

    $jerseyId = ($jerseyId !== null && $jerseyId !== '') ? $jerseyId : null;
    $shortsId = resolveKitShortsId($shortsId);
    $propId = ($propId !== null && $propId !== '') ? $propId : null;

    if ($jerseyId !== null && !kitJersey($jerseyId)) {
        throw new InvalidArgumentException(t('kit.err.unknown'));
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
 * Joueur SVG — proportions plus réalistes (silhouette footballeur),
 * scène felt côté CSS. Remplit #kitTorsoGroup / #kitShortsGroup pour le JS.
 */
function renderKitDollSvg(?string $jerseyId, ?string $shortsId, ?string $avatarUrl = null, string $pseudo = '', ?string $propId = null): void
{
    $jersey = kitJersey($jerseyId);
    $shorts = kitShorts(resolveKitShortsId($shortsId));
    $torsoFill = $jersey !== null ? kitJerseyFill($jersey) : KIT_PLAIN_JERSEY_FILL;
    $shortsFill = $shorts['fill'] ?? kitShortsCatalog()[KIT_DEFAULT_SHORTS]['fill'];
    $collarVisible = $jersey !== null;
    $collarColor = $jersey !== null ? kitJerseyTrimColor($jersey) : '';
    $avatarSrc = avatarPublicUrl($avatarUrl);
    ?>
    <svg viewBox="0 0 180 280" class="kit-doll" role="img" aria-label="<?= e(t('kit.doll_alt')) ?>">
        <defs>
            <?php foreach (kitJerseyCatalog() as $j): ?>
                <?php if ($j['pattern'] === 'stripes'): ?>
            <pattern id="kitStripes_<?= e($j['id']) ?>" width="12" height="24" patternUnits="userSpaceOnUse">
                <rect width="12" height="24" fill="<?= e($j['c1']) ?>"></rect>
                <rect width="6" height="24" fill="<?= e($j['c2']) ?>"></rect>
            </pattern>
                <?php elseif ($j['pattern'] === 'split_h'): ?>
            <linearGradient id="kitSplitH_<?= e($j['id']) ?>" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="<?= e($j['c1']) ?>"></stop>
                <stop offset="50%" stop-color="<?= e($j['c1']) ?>"></stop>
                <stop offset="50%" stop-color="<?= e($j['c2']) ?>"></stop>
                <stop offset="100%" stop-color="<?= e($j['c2']) ?>"></stop>
            </linearGradient>
                <?php elseif ($j['pattern'] === 'split_v'): ?>
            <linearGradient id="kitSplitV_<?= e($j['id']) ?>" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stop-color="<?= e($j['c1']) ?>"></stop>
                <stop offset="50%" stop-color="<?= e($j['c1']) ?>"></stop>
                <stop offset="50%" stop-color="<?= e($j['c2']) ?>"></stop>
                <stop offset="100%" stop-color="<?= e($j['c2']) ?>"></stop>
            </linearGradient>
                <?php endif; ?>
            <?php endforeach; ?>
            <linearGradient id="kitSkinGrad" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#e0b892"></stop>
                <stop offset="55%" stop-color="#c4a07a"></stop>
                <stop offset="100%" stop-color="#a88460"></stop>
            </linearGradient>
            <linearGradient id="kitSockGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#f4ede0"></stop>
                <stop offset="100%" stop-color="#d8d0c0"></stop>
            </linearGradient>
            <clipPath id="kitHeadClip">
                <ellipse cx="90" cy="42" rx="26" ry="30"></ellipse>
            </clipPath>
        </defs>

        <!-- Ombre -->
        <ellipse class="kit-shadow" cx="90" cy="266" rx="48" ry="7"></ellipse>

        <!-- Jambe G -->
        <path class="kit-skin" fill="url(#kitSkinGrad)" d="M68 168
            C66 190 64 210 62 228
            L76 230 C78 210 80 190 82 168 Z"></path>
        <!-- Jambe D -->
        <path class="kit-skin" fill="url(#kitSkinGrad)" d="M98 168
            C100 190 102 210 104 228
            L118 230 C116 210 114 190 112 168 Z"></path>

        <!-- Chaussettes -->
        <path class="kit-sock" fill="url(#kitSockGrad)" d="M62 218 h16 v18 h-17 z"></path>
        <path class="kit-sock" fill="url(#kitSockGrad)" d="M102 218 h16 v18 h-15 z"></path>

        <!-- Chaussures -->
        <path class="kit-boot" d="M56 234 q2 -6 12 -6 h14 q8 0 12 8 v6 h-40 z"></path>
        <path class="kit-boot" d="M98 234 q2 -6 12 -6 h14 q8 0 12 8 v6 h-40 z"></path>
        <path class="kit-boot-sole" d="M54 246 h42 v4 q0 3 -3 3 h-36 q-3 0 -3 -3 z"></path>
        <path class="kit-boot-sole" d="M96 246 h42 v4 q0 3 -3 3 h-36 q-3 0 -3 -3 z"></path>

        <!-- Short (toujours) -->
        <g id="kitShortsGroup" style="fill: <?= e($shortsFill) ?>;">
            <path d="M62 148
                C58 148 56 152 56 158
                L54 188 C54 194 58 198 64 198
                L78 198 L84 178 L90 178 L96 198 L116 198
                C122 198 126 194 126 188
                L124 158 C124 152 122 148 118 148
                Z"></path>
        </g>
        <path class="kit-shorts-shade" d="M84 150 v28 L90 178 L96 150 Z"></path>

        <!-- Bras G (légèrement écarté) -->
        <path class="kit-skin" fill="url(#kitSkinGrad)" d="M52 78
            C40 95 32 120 30 148
            C30 154 34 158 40 156
            C48 130 52 105 58 88 Z"></path>
        <ellipse class="kit-skin" fill="url(#kitSkinGrad)" cx="36" cy="158" rx="9" ry="7"></ellipse>

        <!-- Bras D -->
        <path class="kit-skin" fill="url(#kitSkinGrad)" d="M128 78
            C140 95 148 120 150 148
            C150 154 146 158 140 156
            C132 130 128 105 122 88 Z"></path>
        <ellipse class="kit-skin" fill="url(#kitSkinGrad)" cx="144" cy="158" rx="9" ry="7"></ellipse>

        <!-- Maillot : torse + manches -->
        <g id="kitTorsoGroup" style="fill: <?= e($torsoFill) ?>;">
            <path d="M58 72
                C52 76 48 86 50 98
                L48 146 C48 154 54 160 62 160
                L118 160 C126 160 132 154 132 146
                L130 98 C132 86 128 76 122 72
                C112 66 100 64 90 64
                C80 64 68 66 58 72 Z"></path>
            <!-- Manche G -->
            <path d="M58 72 C48 78 40 92 38 108 C44 104 52 96 58 88 Z"></path>
            <!-- Manche D -->
            <path d="M122 72 C132 78 140 92 142 108 C136 104 128 96 122 88 Z"></path>
        </g>
        <path class="kit-jersey-shade" d="M90 68 L90 158 L118 158 C124 158 128 152 128 146 L126 100 C124 82 112 70 90 68 Z"></path>

        <!-- Cou -->
        <path class="kit-skin" fill="url(#kitSkinGrad)" d="M80 58 C80 52 84 48 90 48 C96 48 100 52 100 58 L98 68 L82 68 Z"></path>

        <!-- Col V -->
        <path id="kitCollarShape" class="kit-collar-shape"
              style="<?= $collarVisible ? 'fill: ' . e($collarColor) . ';' : 'display: none;' ?>"
              d="M78 68 L90 82 L102 68 L98 66 L90 76 L82 66 Z"></path>

        <!-- Tête -->
        <?php if ($avatarSrc !== null): ?>
        <image href="<?= e($avatarSrc) ?>" xlink:href="<?= e($avatarSrc) ?>" x="64" y="12" width="52" height="60"
               preserveAspectRatio="xMidYMid slice" clip-path="url(#kitHeadClip)"></image>
        <ellipse cx="90" cy="42" rx="26" ry="30" fill="none" class="kit-head-ring"></ellipse>
        <?php else: ?>
        <ellipse cx="90" cy="42" rx="26" ry="30" style="fill: <?= e(userAvatarColor($pseudo)) ?>;"></ellipse>
        <text x="90" y="48" text-anchor="middle" class="kit-head-initials"><?= e(userInitials($pseudo)) ?></text>
        <ellipse cx="90" cy="42" rx="26" ry="30" fill="none" class="kit-head-ring"></ellipse>
        <?php endif; ?>

        <!-- Prop -->
        <g id="kitPropStage">
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
    $jerseyId = userKitJersey($user);
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
    $jerseyId = userKitJersey($user);
    $shortsId = userKitShorts($user);
    $propId = userKitProp($user);
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
                    <?php renderKitDollSvg($jerseyId, $shortsId, $user['avatar_url'] ?? null, (string) ($user['pseudo'] ?? ''), $propId); ?>
                </div>

                <div class="kit-picker">
                    <div class="kit-picker-group">
                        <h3 class="kit-picker-title"><?= e(t('kit.section_jersey')) ?></h3>
                        <div class="kit-swatches" id="kitJerseySwatches" role="group" aria-label="<?= e(t('kit.section_jersey')) ?>">
                            <button type="button" class="kit-swatch kit-swatch-none<?= $jerseyId === null ? ' is-active' : '' ?>"
                                    data-kit-id="" data-kit-fill="<?= e(KIT_PLAIN_JERSEY_FILL) ?>" data-kit-trim="0"
                                    aria-pressed="<?= $jerseyId === null ? 'true' : 'false' ?>" title="<?= e(t('kit.item.none_jersey')) ?>">
                                <span class="kit-swatch-chip" style="background: <?= e(KIT_PLAIN_JERSEY_FILL) ?>;"></span>
                                <span class="sr-only"><?= e(t('kit.item.none_jersey')) ?></span>
                            </button>
                            <?php foreach (kitJerseyCatalog() as $j): ?>
                            <button type="button" class="kit-swatch<?= $jerseyId === $j['id'] ? ' is-active' : '' ?>"
                                    data-kit-id="<?= e($j['id']) ?>" data-kit-fill="<?= e(kitJerseyFill($j)) ?>" data-kit-trim="1"
                                    data-kit-trim-color="<?= e(kitJerseyTrimColor($j)) ?>"
                                    aria-pressed="<?= $jerseyId === $j['id'] ? 'true' : 'false' ?>" title="<?= e(kitItemName($j['id'])) ?>">
                                <span class="kit-swatch-chip" style="background: <?= e(kitJerseyChip($j)) ?>;"></span>
                                <span class="sr-only"><?= e(kitItemName($j['id'])) ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
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
               data-msg-error="<?= e(t('kit.err.generic')) ?>"></p>
        </div>
    </div>
    <?php
}
