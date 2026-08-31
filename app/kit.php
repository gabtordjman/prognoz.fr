<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/**
 * Cabine d'essayage — habille un mannequin de joueur avec un maillot et
 * un short (personnalisation visuelle, indépendante de la boutique de
 * points). Rendu 100% SVG, style "voxel" flat.
 *
 * Les maillots reprennent les couleurs/motifs de vrais clubs, mais SANS
 * écusson ni logo sponsor/équipementier (marques déposées) — uniquement
 * la palette, dans le même style flat que le reste du mannequin.
 */

/** Teinte de peau / sous-vêtement neutre du mannequin par défaut (torse nu). */
const KIT_SKIN_COLOR = '#d9a877';
const KIT_UNDERWEAR_COLOR = '#8a7f6d';

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

/** Valeur SVG (fill=) à appliquer au maillot du mannequin. */
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
 * Équivalent CSS (background:) pour la pastille de sélection — un
 * <pattern>/<linearGradient> SVG n'est pas référençable depuis un
 * background CSS classique, d'où cette version dupliquée en pur CSS.
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

/** Couleur du col du maillot (visible seulement avec un maillot équipé). */
function kitJerseyTrimColor(array $jersey): string
{
    return $jersey['trimColor'] ?? '#f4ede0';
}

/**
 * Catalogue des shorts.
 *
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
 * Catalogue des objets tenus en main (accessoire cosmétique).
 *
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

/**
 * Position/échelle (transform SVG) d'un objet sur le mannequin — tous en
 * main sauf le ballon, posé au sol à côté des pieds.
 */
function kitPropTransform(string $propId): string
{
    if ($propId === 'prop_ball') {
        return 'translate(132, 248) scale(1.9)';
    }

    return 'translate(127, 178) scale(1.8)';
}

/** Emoji associé à chaque objet — simple et lisible à toutes les tailles. */
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

/**
 * Formes SVG (sans <svg> englobant) d'un objet en main, dans un repère
 * 24×24 — réutilisé tel quel pour la pastille de sélection (petit) et
 * pour l'objet posé sur le mannequin (agrandi, voir kitPropTransform).
 */
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

/** Nom affiché d'un maillot ou d'un short (clé i18n `kit.item.<id>`). */
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

/** Maillot actuellement porté par l'utilisateur, ou null (torse nu). */
function userKitJersey(array $user): ?string
{
    $id = (string) ($user['kit_jersey'] ?? '');
    return kitJersey($id) ? $id : null;
}

/** Short actuellement porté par l'utilisateur, ou null (par défaut). */
function userKitShorts(array $user): ?string
{
    $id = (string) ($user['kit_shorts'] ?? '');
    return kitShorts($id) ? $id : null;
}

/** Objet en main actuellement équipé, ou null (mains vides). */
function userKitProp(array $user): ?string
{
    $id = (string) ($user['kit_prop'] ?? '');
    return kitProp($id) ? $id : null;
}

/** Enregistre la tenue du mannequin (accepte null/"" pour déshabiller). */
function saveUserKit(PDO $pdo, int $userId, ?string $jerseyId, ?string $shortsId, ?string $propId): void
{
    ensureKitSchema($pdo);

    $jerseyId = ($jerseyId !== null && $jerseyId !== '') ? $jerseyId : null;
    $shortsId = ($shortsId !== null && $shortsId !== '') ? $shortsId : null;
    $propId   = ($propId !== null && $propId !== '') ? $propId : null;

    if ($jerseyId !== null && !kitJersey($jerseyId)) {
        throw new InvalidArgumentException(t('kit.err.unknown'));
    }
    if ($shortsId !== null && !kitShorts($shortsId)) {
        throw new InvalidArgumentException(t('kit.err.unknown'));
    }
    if ($propId !== null && !kitProp($propId)) {
        throw new InvalidArgumentException(t('kit.err.unknown'));
    }

    $pdo->prepare('UPDATE users SET kit_jersey = ?, kit_shorts = ?, kit_prop = ? WHERE id = ?')
        ->execute([$jerseyId, $shortsId, $propId, $userId]);
}

/**
 * Rendu SVG du mannequin seul (défs + pièces) — partagé par la cabine
 * d'essayage (dialog éditable) et par l'affichage lecture-seule sur le
 * profil public (voir renderKitDollCard).
 */
function renderKitDollSvg(?string $jerseyId, ?string $shortsId, ?string $avatarUrl = null, string $pseudo = '', ?string $propId = null): void
{
    $jersey = kitJersey($jerseyId);
    $shorts = kitShorts($shortsId);
    $torsoFill = $jersey !== null ? kitJerseyFill($jersey) : KIT_SKIN_COLOR;
    $shortsFill = $shorts['fill'] ?? KIT_UNDERWEAR_COLOR;
    $collarVisible = $jersey !== null;
    $collarColor = $jersey !== null ? kitJerseyTrimColor($jersey) : '';
    $avatarSrc = avatarPublicUrl($avatarUrl);
    ?>
    <svg viewBox="0 0 200 300" class="kit-doll" role="img" aria-label="<?= e(t('kit.doll_alt')) ?>">
        <defs>
            <?php foreach (kitJerseyCatalog() as $j): ?>
                <?php if ($j['pattern'] === 'stripes'): ?>
            <pattern id="kitStripes_<?= e($j['id']) ?>" width="14" height="28" patternUnits="userSpaceOnUse">
                <rect width="14" height="28" fill="<?= e($j['c1']) ?>"></rect>
                <rect width="7" height="28" fill="<?= e($j['c2']) ?>"></rect>
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
            <clipPath id="kitHeadClip">
                <rect x="70" y="8" width="60" height="56" rx="10"></rect>
            </clipPath>
        </defs>

        <!-- Perso "voxel" (façon personnage blocky) : chaque vêtement est un
             rectangle posé exactement sur le rectangle du corps concerné,
             donc toujours parfaitement ajusté, sans courbe à recaler. -->

        <!-- Tête : photo de profil si dispo, sinon initiales colorées (même
             logique que renderUserAvatar) pour rester identifiable partout -->
        <?php if ($avatarSrc !== null): ?>
        <image href="<?= e($avatarSrc) ?>" xlink:href="<?= e($avatarSrc) ?>" x="70" y="8" width="60" height="56"
               preserveAspectRatio="xMidYMid slice" clip-path="url(#kitHeadClip)"></image>
        <?php else: ?>
        <rect x="70" y="8" width="60" height="56" rx="10" style="fill: <?= e(userAvatarColor($pseudo)) ?>;"></rect>
        <text x="100" y="42" text-anchor="middle" class="kit-head-initials"><?= e(userInitials($pseudo)) ?></text>
        <?php endif; ?>

        <!-- Bras (peau, sous les manches du maillot) -->
        <rect class="kit-skin" x="34" y="64" width="30" height="140" rx="8"></rect>
        <rect class="kit-skin" x="136" y="64" width="30" height="140" rx="8"></rect>

        <!-- Jambes (peau, prolongent exactement le bas du short) -->
        <rect class="kit-skin" x="66" y="152" width="32" height="120" rx="8"></rect>
        <rect class="kit-skin" x="102" y="152" width="32" height="120" rx="8"></rect>

        <!-- Chaussures -->
        <rect class="kit-boot" x="64" y="270" width="36" height="18" rx="6"></rect>
        <rect class="kit-boot" x="100" y="270" width="36" height="18" rx="6"></rect>

        <!-- Short (torse nu = sous-vêtement neutre) — même largeur que les jambes -->
        <g id="kitShortsGroup" style="fill: <?= e($shortsFill) ?>;">
            <rect x="66" y="152" width="32" height="48" rx="8"></rect>
            <rect x="102" y="152" width="32" height="48" rx="8"></rect>
        </g>

        <!-- Maillot : torse + manches courtes, même largeur que les bras/torse -->
        <g id="kitTorsoGroup" style="fill: <?= e($torsoFill) ?>;">
            <rect x="64" y="64" width="72" height="88" rx="8"></rect>
            <rect x="34" y="64" width="30" height="46" rx="8"></rect>
            <rect x="136" y="64" width="30" height="46" rx="8"></rect>
        </g>

        <!-- Col (visible seulement avec un maillot, couleur propre à chaque maillot) -->
        <rect id="kitCollarShape" class="kit-collar-shape"
              style="<?= $collarVisible ? 'fill: ' . e($collarColor) . ';' : 'display: none;' ?>"
              x="88" y="64" width="24" height="7" rx="3"></rect>

        <!-- Objet équipé (en main, sauf le ballon posé au sol près des pieds) -->
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

/**
 * Carte lecture-seule (profil public) : montre la tenue du joueur, sans
 * édition. Un lien "Habiller mon joueur" apparaît si c'est son propre profil.
 */
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

/**
 * Bouton "Habiller mon joueur" (à placer près de l'avatar) + la cabine
 * d'essayage (dialog plein écran, masquée par défaut). Le mannequin est
 * rendu déjà dans le bon état (pas de flash JS au chargement) ; le script
 * `kit.js` gère ensuite les clics et l'enregistrement.
 */
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
                                    data-kit-id="" data-kit-fill="<?= e(KIT_SKIN_COLOR) ?>" data-kit-trim="0"
                                    aria-pressed="<?= $jerseyId === null ? 'true' : 'false' ?>" title="<?= e(t('kit.item.none_jersey')) ?>">
                                <i class="fa-solid fa-ban" aria-hidden="true"></i>
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
                            <button type="button" class="kit-swatch kit-swatch-none<?= $shortsId === null ? ' is-active' : '' ?>"
                                    data-kit-id="" data-kit-fill="<?= e(KIT_UNDERWEAR_COLOR) ?>"
                                    aria-pressed="<?= $shortsId === null ? 'true' : 'false' ?>" title="<?= e(t('kit.item.none_shorts')) ?>">
                                <i class="fa-solid fa-ban" aria-hidden="true"></i>
                            </button>
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
