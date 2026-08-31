<?php
require __DIR__ . '/../app/bootstrap.php';

$pdo  = getPDO();
$user = currentUser($pdo);
ensureMatchProbColumns($pdo);
maintainMatchLifecycle($pdo, true);

$matchsByCategory = getUpcomingMatchesByCategory($pdo);
$matchsRaw = getUpcomingMatches($pdo);
$matchIds = array_column($matchsRaw, 'id');
$marketsByMatch = getMarketsForMatches($pdo, $matchIds);

$allMarketIds = [];
$matchs = [];
foreach ($matchsRaw as $m) {
    $m['markets'] = $marketsByMatch[(int) $m['id']] ?? [];
    foreach ($m['markets'] as $mk) {
        $allMarketIds[] = (int) $mk['id'];
    }
    $matchs[] = $m;
}

$matchsByCategoryDisplay = [];
foreach (sportCategories() as $cat) {
    $matchsByCategoryDisplay[$cat] = [];
    foreach ($matchsByCategory[$cat] ?? [] as $m) {
        $m['markets'] = $marketsByMatch[(int) $m['id']] ?? [];
        $matchsByCategoryDisplay[$cat][] = $m;
    }
}

$predictions = getUserPredictions($pdo, $user ? (int) $user['id'] : null, $allMarketIds);
$flashes = getFlashes();
$userFavTeams = $user ? userFavoriteTeams($user) : [];
$userFavTeam = $userFavTeams[0] ?? null;
$homeHighlights = fetchHomeHighlights($pdo);

if ($user) {
    foreach (sportCategories() as $cat) {
        $matchsByCategoryDisplay[$cat] = prioritizeUnfinishedMatches(
            $matchsByCategoryDisplay[$cat],
            $predictions,
            $userFavTeams
        );
    }
}

$totalAffichés = 0;
foreach ($matchsByCategoryDisplay as $catMatchs) {
    $totalAffichés += count($catMatchs);
}

$marketsMeta = [];
foreach ($matchs as $m) {
    foreach ($m['markets'] as $mk) {
        $mType = (string) ($mk['type'] ?? '');
        $marketsMeta[(int) $mk['id']] = [
            'competition' => $m['competition'],
            'home'        => $m['equipe_home'],
            'away'        => $m['equipe_away'],
            'type'        => $mType,
            'label'       => marketTypeLabel($mType),
            'points'      => $mType === 'fav_team'
                ? marketPoints('fav_team')
                : (int) $mk['points_si_correct'],
            'probs'       => [
                '1' => $m['prob_1'] !== null ? (float) $m['prob_1'] : null,
                'N' => $m['prob_n'] !== null ? (float) $m['prob_n'] : null,
                '2' => $m['prob_2'] !== null ? (float) $m['prob_2'] : null,
            ],
        ];
    }
}

$validatedPicksJs = [];
if ($user) {
    foreach ($predictions as $mid => $pred) {
        if (($pred['statut'] ?? '') !== 'en_attente') {
            continue;
        }
        $validatedPicksJs[(int) $mid] = $pred['reponse'];
    }
}

// CSRF + flashes lus : on libère le verrou session pour ne pas bloquer validate_ticket / sync.
csrfToken();
releaseSession();

?>
<!DOCTYPE html>
<html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
<head>
    <?php layoutHead(t('home.title'), true, seoPage('home')); ?>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-7134608349713810"
     crossorigin="anonymous">
    </script>
</head>
<body>

<?php layoutTopbar($user, 'matchs'); ?>

<?php if (!$user): ?>
<header class="hero hero-guest">
    <div class="hero-guest-grid">
        <div class="hero-guest-visual" aria-hidden="true">
            <div class="hero-scene">
                <div class="hero-scene-bar"></div>
                <div class="hero-scene-felt"></div>
                <div class="hero-scene-ball hero-scene-ball--foot"><i class="fa-solid fa-futbol"></i></div>
                <div class="hero-scene-ball hero-scene-ball--basket"><i class="fa-solid fa-basketball"></i></div>
                <div class="hero-scene-ball hero-scene-ball--tennis"><?php renderSportIcon('tennis-racket'); ?></div>
                <div class="hero-scene-chalk">1 · N · 2</div>
            </div>
        </div>
        <div class="hero-guest-copy">
            <h1><?= e(t('home.hero_title')) ?></h1>
            <p class="hero-lead"><?= e(t('home.hero_lead')) ?></p>
            <div class="hero-guest-cta">
                <a href="<?= e(url('auth/register.php?redirect=index.php')) ?>" class="btn btn-primary btn-lg hero-cta-main">
                    <i class="fa-solid fa-ticket"></i> <?= e(t('home.cta_play')) ?>
                </a>
                <a href="<?= e(url('legal/comment-ca-marche.php')) ?>" class="btn btn-ghost btn-lg"><?= e(t('nav.howto')) ?></a>
            </div>
        </div>
    </div>
</header>
<?php endif; ?>

<div class="app-main app-main-wide">
    <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>

    <?php renderHomeHighlightBanner($homeHighlights ?? []); ?>

    <?php if ($user): ?>
        <?php renderOnboardingChecklist($pdo, $user); ?>
    <?php endif; ?>

    <div class="pronos-page" id="matches">
    <?php if ($user): ?>
        <header class="page-head">
            <h1 class="page-title"><?= e(t('home.matches_today')) ?></h1>
            <?php if ($totalAffichés > 0): ?>
            <p class="page-sub"><?= e($totalAffichés > 1 ? t('home.meetings_other', ['n' => $totalAffichés]) : t('home.meetings_one', ['n' => $totalAffichés])) ?></p>
            <?php endif; ?>
        </header>
    <?php endif; ?>
    <?php if ($totalAffichés > 0): ?>
        <div class="sport-cat-sticky-sentinel" id="sportCatSentinel" aria-hidden="true"></div>
        <div class="sport-cat-panel" id="sportCatPanel">
                    <div class="sport-cat-panel-head"><?= e(t('home.choose_cat')) ?></div>
                    <nav class="sport-cat-nav" id="sportCatNav" aria-label="<?= e(t('home.cat_aria')) ?>">
                    <button type="button" class="sport-cat-btn is-active" data-cat="all">
                        <i class="fa-solid fa-layer-group" aria-hidden="true"></i> <?= e(t('home.all')) ?>
                        <span class="sport-cat-count"><?= $totalAffichés ?></span>
                    </button>
                    <?php foreach (sportCategories() as $cat):
                        $ui = sportCategoryUi($cat);
                        $count = count($matchsByCategoryDisplay[$cat]);
                    ?>
                    <button type="button" class="sport-cat-btn" data-cat="<?= e($cat) ?>">
                        <?php renderSportIcon($ui['icon']); ?> <?= e($ui['label']) ?>
                        <span class="sport-cat-count"><?= $count ?></span>
                    </button>
                    <?php endforeach; ?>
            </nav>
        </div>
    <?php endif; ?>

        <div class="pronos-matches">
            <?php if ($totalAffichés === 0): ?>
                <div class="panel"><div class="panel-body empty-msg"><?= e(t('home.no_matches')) ?></div></div>
            <?php else: ?>

                <?php foreach (sportCategories() as $cat):
                    $ui = sportCategoryUi($cat);
                    $catMatchs = $matchsByCategoryDisplay[$cat];
                ?>
                <section class="sport-section" data-cat-section="<?= e($cat) ?>">
                    <header class="sport-section-head">
                        <h2 class="sport-section-title match-cat-<?= e($cat) ?>">
                            <?php renderSportIcon($ui['icon']); ?>
                            <?= e($ui['label']) ?>
                        </h2>
                        <span class="sport-section-meta"><?= e(t('home.matches_count', ['n' => count($catMatchs), 'max' => (int) MATCHS_PAR_CATEGORIE])) ?></span>
                    </header>

                    <?php if (empty($catMatchs)): ?>
                        <div class="panel sport-section-empty">
                            <div class="panel-body empty-msg"><?= e(t('home.no_matches_sport', ['sport' => strtolower($ui['label'])])) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($catMatchs as $m):
                        $hasDraw = matchHasDraw($m['sport']);
                        $isSoccer = isSoccerSport($m['sport']);
                        $ferme = utcDatetimeTimestamp($m['date_match']) <= time();
                        $market1x2 = null;
                        $marketScore = null;
                        $marketButeur = null;
                        $marketFav = null;
                        foreach ($m['markets'] as $mk) {
                            if ($mk['type'] === '1x2') $market1x2 = $mk;
                            if ($mk['type'] === 'score_exact') $marketScore = $mk;
                            if ($mk['type'] === 'buteur') $marketButeur = $mk;
                            if ($mk['type'] === 'fav_team') $marketFav = $mk;
                        }
                        if (!$market1x2) continue;
                        $mid1x2 = (int) $market1x2['id'];
                        $choix1x2 = $predictions[$mid1x2]['reponse'] ?? null;
                        $pickChoices = $hasDraw
                            ? ['1' => '1', 'N' => 'N', '2' => '2']
                            : ['1' => $m['equipe_home'], '2' => $m['equipe_away']];
                        $hasScoreMarket = $isSoccer && $marketScore;
                        $hasButeurMarket = $isSoccer
                            && $marketButeur
                            && soccerSportHasScorerOdds($m['sport'])
                            && !empty($marketButeur['options']);
                        $buteurOptions = $hasButeurMarket ? ($marketButeur['options'] ?? []) : [];
                        $favInMatch = ($user && $marketFav)
                            ? matchFavoriteTeamsInMatch($m, $userFavTeams)
                            : [];
                        $hasFavMarket = $favInMatch !== [];
                        $hasExtraMarkets = $hasScoreMarket || $hasButeurMarket || $hasFavMarket;
                        $extraHints = array_values(array_filter([
                            $hasFavMarket ? t('home.fav_team') : null,
                            $hasScoreMarket ? t('home.exact_score') : null,
                            $hasButeurMarket ? t('home.scorer') : null,
                        ]));
                        $extraPts = [];
                        if ($hasFavMarket) {
                            $extraPts[] = '+' . marketPoints('fav_team');
                        }
                        if ($hasButeurMarket) {
                            $extraPts[] = '+' . POINTS_BUTEUR;
                        }
                        if ($hasScoreMarket) {
                            $extraPts[] = '+' . POINTS_SCORE_EXACT;
                        }
                        $extraOpen = false;
                        if ($hasExtraMarkets) {
                            foreach ([$marketFav, $marketScore, $marketButeur] as $extraMarket) {
                                if (!$extraMarket) {
                                    continue;
                                }
                                $extraMid = (int) $extraMarket['id'];
                                if (!empty($predictions[$extraMid])) {
                                    $extraOpen = true;
                                    break;
                                }
                            }
                        }
                    ?>
                    <article class="match-card match-slip<?= $extraOpen ? ' is-markets-open' : '' ?><?= $ferme ? ' is-picks-closed' : '' ?>" data-match-cat="<?= e($cat) ?>" data-match-id="<?= (int) $m['id'] ?>">
                        <div class="match-slip-edge match-slip-edge-top" aria-hidden="true"></div>
                        <div class="match-card-body">
                        <div class="match-meta">
                            <span class="match-comp match-cat-<?= e(sportCategory($m['sport'])) ?>">
                                <?= e($m['competition']) ?>
                            </span>
                            <time><?= e(formatMatchWhen($m['date_match'])) ?></time>
                        </div>
                        <div class="match-teams">
                            <span class="team home"><?= e($m['equipe_home']) ?></span>
                            <span class="vs">–</span>
                            <span class="team away"><?= e($m['equipe_away']) ?></span>
                        </div>

                        <?php if ($ferme): ?>
                            <p class="match-locked"><?= e(t('home.picks_closed')) ?></p>
                        <?php else: ?>

                        <?php if ($market1x2): ?>
                        <div class="market-block">
                            <div class="market-label"><?= e(t('home.winner')) ?> <span class="pts-tag">+1</span></div>
                            <div class="pick-row<?= $hasDraw ? '' : ' pick-row-2' ?>"
                                 data-market="<?= $mid1x2 ?>">
                                <?php
                                $probCols = ['1' => 'prob_1', 'N' => 'prob_n', '2' => 'prob_2'];
                                foreach ($pickChoices as $val => $lbl):
                                    $probVal = $m[$probCols[$val]] ?? null;
                                ?>
                                <button type="button" class="pick-btn<?= $choix1x2 === $val ? ' selected' : '' ?><?= $probVal === null ? ' pick-btn--no-prob' : '' ?>" data-pick="<?= e($val) ?>">
                                    <span class="pick-val"><?= e($lbl) ?></span>
                                    <?php if ($probVal !== null): ?>
                                    <span class="pick-prob"><?= (float) $probVal ?>%</span>
                                    <?php else: ?>
                                    <span class="pick-prob pick-prob--empty" aria-hidden="true">—</span>
                                    <?php endif; ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($hasExtraMarkets): ?>
                        <button type="button"
                                class="match-markets-toggle"
                                aria-expanded="<?= $extraOpen ? 'true' : 'false' ?>"
                                aria-controls="matchMarkets-<?= (int) $m['id'] ?>">
                            <span class="match-markets-toggle-text">
                                <span class="match-markets-toggle-label"><?= e(t('home.more_picks')) ?></span>
                                <span class="match-markets-toggle-hint"><?= e(implode(' · ', $extraHints)) ?></span>
                            </span>
                            <span class="match-markets-toggle-pts"><?= e(implode(' / ', $extraPts)) ?> pts</span>
                            <i class="fa-solid fa-chevron-down match-markets-chevron" aria-hidden="true"></i>
                        </button>
                        <div class="match-markets-extra"
                             id="matchMarkets-<?= (int) $m['id'] ?>"
                             <?= $extraOpen ? '' : 'hidden' ?>>

                        <?php if ($hasFavMarket):
                            $midFav = (int) $marketFav['id'];
                            $choixFav = $predictions[$midFav]['reponse'] ?? null;
                            $favPts = marketPoints('fav_team');
                            $parsedFavPick = is_string($choixFav) ? parseFavTeamPick($choixFav) : null;
                            $favClash = count($favInMatch) >= 2;
                            $supportedTeam = $parsedFavPick['team']
                                ?? (!$favClash ? ($favInMatch[0] ?? null) : null);
                            // Compat anciens picks W/L sans équipe
                            if ($supportedTeam === null && $parsedFavPick && ($parsedFavPick['team'] ?? null) === null && !$favClash) {
                                $supportedTeam = $favInMatch[0] ?? null;
                            }
                            $pickW = $supportedTeam ? encodeFavTeamPick('W', $supportedTeam) : '';
                            $pickL = $supportedTeam ? encodeFavTeamPick('L', $supportedTeam) : '';
                            $selW = false;
                            $selL = false;
                            if ($parsedFavPick) {
                                $sameTeam = ($parsedFavPick['team'] ?? null) === null
                                    || ($supportedTeam !== null
                                        && normalizeTeamName((string) $parsedFavPick['team']) === normalizeTeamName($supportedTeam));
                                if ($sameTeam && $parsedFavPick['outcome'] === 'W') {
                                    $selW = true;
                                }
                                if ($sameTeam && $parsedFavPick['outcome'] === 'L') {
                                    $selL = true;
                                }
                            }
                        ?>
                        <div class="market-block market-block-extra market-block-fav"
                             data-fav-clash="<?= $favClash ? '1' : '0' ?>"
                             data-fav-home="<?= e((string) ($m['equipe_home'] ?? '')) ?>"
                             data-fav-away="<?= e((string) ($m['equipe_away'] ?? '')) ?>">
                            <div class="market-label">
                                <?= e(t('home.fav_team')) ?>
                                <span class="pts-tag pts-2">+<?= (int) $favPts ?></span>
                            </div>
                            <?php if ($favClash): ?>
                            <div class="fav-support-row" data-fav-support-for="<?= $midFav ?>">
                                <span class="fav-support-label"><?= e(t('home.fav_support')) ?></span>
                                <?php foreach ($favInMatch as $ft):
                                    $isSup = $supportedTeam !== null
                                        && normalizeTeamName($supportedTeam) === normalizeTeamName($ft);
                                ?>
                                <button type="button"
                                        class="fav-support-btn<?= $isSup ? ' selected' : '' ?>"
                                        data-support-team="<?= e($ft) ?>"
                                        aria-pressed="<?= $isSup ? 'true' : 'false' ?>">
                                    <?= e($ft) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <p class="fav-team-hint"><?= e(t('home.fav_team_of', ['team' => (string) ($favInMatch[0] ?? '')])) ?></p>
                            <?php endif; ?>
                            <div class="pick-row pick-row-2 fav-team-picks"
                                 data-market="<?= $midFav ?>"
                                 data-fav-supported="<?= e((string) ($supportedTeam ?? '')) ?>">
                                <button type="button"
                                        class="pick-btn pick-btn--no-prob<?= $selW ? ' selected' : '' ?>"
                                        data-pick="<?= e($pickW) ?>"
                                        data-fav-outcome="W"
                                        <?= $pickW === '' ? 'disabled' : '' ?>>
                                    <span class="pick-val"><?= e(t('home.fav_win')) ?></span>
                                </button>
                                <button type="button"
                                        class="pick-btn pick-btn--no-prob<?= $selL ? ' selected' : '' ?>"
                                        data-pick="<?= e($pickL) ?>"
                                        data-fav-outcome="L"
                                        <?= $pickL === '' ? 'disabled' : '' ?>>
                                    <span class="pick-val"><?= e(t('home.fav_lose')) ?></span>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($hasScoreMarket):
                            $midScore = (int) $marketScore['id'];
                            $choixScore = $predictions[$midScore]['reponse'] ?? null;
                            $scoreCustomMax = defined('EXACT_SCORE_CUSTOM_MAX') ? (int) EXACT_SCORE_CUSTOM_MAX : 20;
                            $scoreHomeVal = '';
                            $scoreAwayVal = '';
                            $hasScorePick = false;
                            if (is_string($choixScore) && preg_match('/^(\d+)-(\d+)$/', $choixScore, $cm)) {
                                $scoreHomeVal = $cm[1];
                                $scoreAwayVal = $cm[2];
                                $hasScorePick = true;
                            }
                        ?>
                        <div class="market-block market-block-extra">
                            <div class="market-label"><?= e(t('home.exact_score')) ?> <span class="pts-tag pts-3">+3</span></div>
                            <div class="score-entry<?= $hasScorePick ? ' selected' : '' ?>"
                                 data-market="<?= $midScore ?>"
                                 data-max="<?= $scoreCustomMax ?>">
                                <div class="score-entry-side">
                                    <span class="score-entry-team" title="<?= e($m['equipe_home']) ?>"><?= e($m['equipe_home']) ?></span>
                                    <div class="score-entry-control">
                                        <button type="button"
                                                class="score-step"
                                                data-side="home"
                                                data-delta="-1"
                                                aria-label="<?= e(t('home.exact_score_minus')) ?>">−</button>
                                        <input type="number"
                                               class="score-entry-input score-entry-home"
                                               inputmode="numeric"
                                               min="0"
                                               max="<?= $scoreCustomMax ?>"
                                               step="1"
                                               placeholder="0"
                                               value="<?= e($scoreHomeVal) ?>"
                                               aria-label="<?= e(t('home.exact_score_home')) ?>">
                                        <button type="button"
                                                class="score-step"
                                                data-side="home"
                                                data-delta="1"
                                                aria-label="<?= e(t('home.exact_score_plus')) ?>">+</button>
                                    </div>
                                </div>
                                <span class="score-entry-sep" aria-hidden="true">–</span>
                                <div class="score-entry-side">
                                    <span class="score-entry-team" title="<?= e($m['equipe_away']) ?>"><?= e($m['equipe_away']) ?></span>
                                    <div class="score-entry-control">
                                        <button type="button"
                                                class="score-step"
                                                data-side="away"
                                                data-delta="-1"
                                                aria-label="<?= e(t('home.exact_score_minus')) ?>">−</button>
                                        <input type="number"
                                               class="score-entry-input score-entry-away"
                                               inputmode="numeric"
                                               min="0"
                                               max="<?= $scoreCustomMax ?>"
                                               step="1"
                                               placeholder="0"
                                               value="<?= e($scoreAwayVal) ?>"
                                               aria-label="<?= e(t('home.exact_score_away')) ?>">
                                        <button type="button"
                                                class="score-step"
                                                data-side="away"
                                                data-delta="1"
                                                aria-label="<?= e(t('home.exact_score_plus')) ?>">+</button>
                                    </div>
                                </div>
                                <button type="button"
                                        class="score-entry-clear"
                                        <?= $hasScorePick ? '' : 'hidden' ?>><?= e(t('home.exact_score_clear')) ?></button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($hasButeurMarket):
                            $midButeur = (int) $marketButeur['id'];
                            $choixButeur = $predictions[$midButeur]['reponse'] ?? null;
                        ?>
                        <div class="market-block market-block-extra market-block-buteur">
                            <div class="market-label"><?= e(t('home.scorer')) ?> <span class="pts-tag pts-2">+2</span></div>
                            <div class="scorer-grid" data-market="<?= $midButeur ?>">
                                <?php foreach ($buteurOptions as $playerOpt):
                                    $player = marketOptionLabel($playerOpt);
                                ?>
                                <button type="button"
                                        class="scorer-btn<?= $choixButeur === $player ? ' selected' : '' ?>"
                                        data-pick="<?= e($player) ?>"
                                        title="<?= e($player) ?>"><?= e($player) ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        </div>
                        <?php endif; ?>

                        <?php endif; ?>
                        </div>
                        <div class="match-slip-edge match-slip-edge-bottom" aria-hidden="true"></div>
                    </article>
                    <?php endforeach; ?>
                </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php renderTicketPanel($user, []); ?>
    </div>
</div>

<script>
    window.PRONO_USER = <?= $user ? 'true' : 'false' ?>;
    window.PRONO_CSRF = <?= json_encode(csrfToken()) ?>;
    window.PRONO_LOGIN_URL = <?= json_encode(url('auth/login.php?redirect=index.php')) ?>;
    window.PRONO_REGISTER_URL = <?= json_encode(url('auth/register.php?redirect=index.php')) ?>;
    window.PRONO_API = <?= json_encode(url('api/')) ?>;
    window.PRONO_MARKETS = <?= json_encode($marketsMeta, JSON_UNESCAPED_UNICODE) ?>;
    window.PRONO_VALIDATED = <?= json_encode($validatedPicksJs, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= e(assetUrl('assets/js/predictions.js')) ?>"></script>
<script src="<?= e(assetUrl('assets/js/match-effects.js')) ?>"></script>
<script>
(function () {
    var nav = document.getElementById('sportCatNav');
    if (!nav) return;
    nav.addEventListener('click', function (e) {
        var btn = e.target.closest('.sport-cat-btn');
        if (!btn) return;
        var cat = btn.getAttribute('data-cat');
        nav.querySelectorAll('.sport-cat-btn').forEach(function (b) {
            b.classList.toggle('is-active', b === btn);
        });
        document.querySelectorAll('[data-cat-section]').forEach(function (section) {
            var show = cat === 'all' || section.getAttribute('data-cat-section') === cat;
            if (show) {
                section.removeAttribute('hidden');
                requestAnimationFrame(function () {
                    section.classList.remove('is-filtered-out');
                });
            } else if (!section.classList.contains('is-filtered-out')) {
                section.classList.add('is-filtered-out');
                window.setTimeout(function () {
                    if (section.classList.contains('is-filtered-out')) {
                        section.setAttribute('hidden', '');
                    }
                }, 280);
            }
        });
    });
})();

(function () {
    var panel = document.getElementById('sportCatPanel');
    var sentinel = document.getElementById('sportCatSentinel');
    var topbar = document.querySelector('.topbar');
    if (!panel || !sentinel) return;

    var observer = null;

    function syncStickyTop() {
        var h = topbar ? topbar.offsetHeight : 0;
        document.documentElement.style.setProperty('--sport-cat-sticky-top', h + 'px');
        return h;
    }

    function attachObserver() {
        if (observer) {
            observer.disconnect();
        }
        if (!('IntersectionObserver' in window)) {
            return;
        }
        var topOffset = syncStickyTop();
        observer = new IntersectionObserver(function (entries) {
            panel.classList.toggle('is-stuck', !entries[0].isIntersecting);
        }, {
            threshold: 0,
            rootMargin: '-' + topOffset + 'px 0px 0px 0px',
        });
        observer.observe(sentinel);
    }

    attachObserver();
    window.addEventListener('resize', attachObserver);
})();
</script>
<?php layoutFooter(); ?>
</body>
</html>
