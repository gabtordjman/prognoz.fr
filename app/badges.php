<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/**
 * Badges affichés dans le classement (calculés à la volée, sans table dédiée).
 *
 * @return list<array{id:string, label:string, title:string, variant:string, style?:string}>
 */
function leaderboardBadges(int $rank, int $points, int $serie): array
{
    $badges = [];

    // Podium : pastille discrète à côté du pseudo (plus de ruban diagonal).
    if ($points > 0 && $rank >= 1 && $rank <= 3) {
        $labels = [1 => t('points.1st'), 2 => t('points.2nd'), 3 => t('points.3rd')];
        $variants = [1 => 'gold', 2 => 'silver', 3 => 'bronze'];
        $badges[] = [
            'id'      => 'podium_' . $rank,
            'label'   => $labels[$rank],
            'title'   => $rank === 1 ? t('lb.leading') : t('lb.on_podium'),
            'variant' => $variants[$rank],
            'style'   => 'pill',
        ];
    }

    if ($serie >= 3) {
        $badges[] = [
            'id'      => 'streak',
            'label'   => '×' . $serie,
            'title'   => t('streak.title', ['n' => $serie]),
            'variant' => 'fire',
            'style'   => 'pill',
        ];
    }

    return $badges;
}

function renderLeaderboardBadge(array $badge): void
{
    ?>
    <span class="lb-pill lb-pill-<?= e($badge['variant']) ?>" title="<?= e($badge['title']) ?>"><?= e($badge['label']) ?></span>
    <?php
}

/** Variante CSS podium saison : or / argent / bronze. */
function seasonRewardVariant(int $classement = 0, string $recompense = ''): string
{
    if ($classement === 1) {
        return 'or';
    }
    if ($classement === 2) {
        return 'argent';
    }
    if ($classement === 3) {
        return 'bronze';
    }
    $r = mb_strtolower($recompense);
    if (str_contains($r, 'argent')) {
        return 'argent';
    }
    if (str_contains($r, 'bronze')) {
        return 'bronze';
    }
    if (preg_match('/\bor\b/u', $r)) {
        return 'or';
    }
    return 'argent';
}

function renderSeasonRewardBadge(array $reward): void
{
    $classement = (int) ($reward['classement'] ?? 0);
    $stored = (string) ($reward['recompense'] ?? 'Badge');
    $variant = seasonRewardVariant($classement, $stored);
    $streak = max(1, (int) ($reward['streak'] ?? 1));
    $label = match ($classement) {
        1 => t('season.badge_gold'),
        2 => t('season.badge_silver'),
        3 => t('season.badge_bronze'),
        default => $stored,
    };
    if ($streak >= 2) {
        $label = match ($classement) {
            1 => t('season.badge_gold_streak', ['n' => $streak]),
            2 => t('season.badge_silver_streak', ['n' => $streak]),
            3 => t('season.badge_bronze_streak', ['n' => $streak]),
            default => $label,
        };
    }
    $short = match ($variant) {
        'or' => currentLang() === 'en' ? 'Gold' : 'Or',
        'argent' => currentLang() === 'en' ? 'Silver' : 'Argent',
        'bronze' => currentLang() === 'en' ? 'Bronze' : 'Bronze',
        default => $label,
    };
    ?>
    <span class="season-reward-badge season-reward-badge--<?= e($variant) ?>" title="<?= e($label) ?>">
        <span class="season-reward-badge__shine" aria-hidden="true"></span>
        <span class="season-reward-badge__label"><?= e($short) ?></span>
        <?php if ($streak >= 2 && $classement >= 1 && $classement <= 3): ?>
        <span class="season-reward-badge__streak" aria-hidden="true">×<?= $streak ?></span>
        <?php endif; ?>
    </span>
    <?php
}

function renderLeaderboardRow(array $member, int $rank, int $currentUserId): void
{
    $points = (int) ($member['points'] ?? $member['points_totaux'] ?? 0);
    $serie  = (int) ($member['serie_en_cours'] ?? 0);
    $badges = leaderboardBadges($rank, $points, $serie);
    $isSelf = (int) $member['id'] === $currentUserId;
    ?>
    <div class="leaderboard-row<?= $rank <= 3 && $points > 0 ? ' leaderboard-row--podium' : '' ?><?= $isSelf ? ' leaderboard-row--self' : '' ?>">
        <div class="leaderboard-rank<?= $rank <= 3 ? ' rank-' . $rank : '' ?>"><?= $rank ?></div>
        <div class="leaderboard-name">
            <?php renderUserProfileLink((int) $member['id'], (string) $member['pseudo'], 'sm', false, $member['avatar_url'] ?? null); ?>
            <span class="leaderboard-name-text">
                <a href="<?= e(userProfileUrl((int) $member['id'])) ?>" class="leaderboard-pseudo<?= $isSelf ? '' : ' leaderboard-pseudo--link' ?>">
                    <?php if (function_exists('renderCosmeticPseudo')): ?>
                        <?php renderCosmeticPseudo(userDisplayName($member), $member['equipped_name'] ?? null); ?>
                    <?php else: ?>
                        <?= e($member['pseudo']) ?>
                    <?php endif; ?>
                    <?php if ($isSelf): ?> <span class="leaderboard-you"><?= e(t('common.you')) ?></span><?php endif; ?>
                </a>
            <?php if ($badges): ?>
            <span class="leaderboard-badges">
                <?php foreach ($badges as $badge) {
                    renderLeaderboardBadge($badge);
                } ?>
            </span>
            <?php endif; ?>
            </span>
        </div>
        <div class="leaderboard-points"><?= $points ?> <?= e(t('common.pts')) ?></div>
    </div>
    <?php
}

function streakBannerLabel(int $serie): string
{
    return $serie === 1 ? t('streak.one') : t('streak.other');
}

function renderStreakBanner(int $serie): void
{
    if ($serie <= 0) {
        return;
    }
    $unlocked = $serie >= 3;
    $mult = function_exists('streakPointsMultiplier') ? streakPointsMultiplier($serie) : 1.0;
    ?>
    <div class="streak-banner<?= $unlocked ? ' streak-banner--unlocked' : '' ?>"
         id="streakBanner"
         data-serie="<?= $serie ?>"
         role="status"
         aria-live="polite">
        <span class="streak-banner-icon" aria-hidden="true"><i class="fa-solid fa-fire"></i></span>
        <div class="streak-banner-body">
            <p class="streak-banner-line">
                <strong class="streak-banner-num"><?= $serie ?></strong>
                <?= e(streakBannerLabel($serie)) ?>
                <?php if ($mult > 1.0): ?>
                    <span class="streak-banner-mult">×<?= rtrim(rtrim(number_format($mult, 1, '.', ''), '0'), '.') ?></span>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <script>
    (function () {
        var banner = document.getElementById('streakBanner');
        if (!banner) return;
        var serie = parseInt(banner.dataset.serie, 10) || 0;
        var lastKey = 'prognoz_streak_last';
        var popKey = 'prognoz_streak_pop_' + serie;
        var last = 0;
        try { last = parseInt(localStorage.getItem(lastKey), 10) || 0; } catch (e) { /* ignore */ }
        var alreadyPopped = false;
        try { alreadyPopped = !!sessionStorage.getItem(popKey); } catch (e) { /* ignore */ }

        if (!alreadyPopped) {
            banner.classList.add('is-popping');
            if (serie > last) {
                banner.classList.add('is-boost');
            }
            try { sessionStorage.setItem(popKey, '1'); } catch (e) { /* ignore */ }
        }

        try { localStorage.setItem(lastKey, String(serie)); } catch (e) { /* ignore */ }
    })();
    </script>
    <?php
}
