<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

require_once __DIR__ . '/odds_api.php';
require_once __DIR__ . '/scoring.php';

function matchHasDraw(string $sportKey): bool
{
    return isSoccerSport($sportKey);
}

function isSoccerSport(string $sportKey): bool
{
    return strncmp($sportKey, 'soccer_', 7) === 0;
}

/** Ligues basket hommes couvertes par The Odds API (priorité sync / affichage). */
function mensBasketballSportKeys(): array
{
    return [
        'basketball_nba',
        'basketball_euroleague',
        'basketball_ncaab',
        'basketball_nbl',
        'basketball_nba_preseason',
        'basketball_nba_summer_league',
    ];
}

function isMensBasketballSport(string $sportKey): bool
{
    return in_array($sportKey, mensBasketballSportKeys(), true);
}

function soccerSportHasScorerOdds(string $sportKey): bool
{
    return in_array($sportKey, ODDS_SCORER_SPORTS, true);
}

function sportCategory(string $sportKey): string
{
    if (strncmp($sportKey, 'tennis_', 7) === 0) {
        return 'tennis';
    }
    if (strncmp($sportKey, 'basketball_', 11) === 0) {
        return 'basketball';
    }
    if (strncmp($sportKey, 'soccer_', 7) === 0) {
        return 'soccer';
    }
    return 'other';
}

function sportCategoryLabel(string $sportKey): string
{
    switch (sportCategory($sportKey)) {
        case 'tennis':     return 'Tennis';
        case 'basketball': return 'Basket';
        case 'soccer':     return 'Foot';
        default:           return 'Sport';
    }
}

/** Catégories affichées sur la page matchs (ordre d'affichage). */
function sportCategories(): array
{
    return ['soccer', 'basketball', 'tennis'];
}

function sportCategoryUi(string $category): array
{
    switch ($category) {
        case 'soccer':
            return ['label' => t('sport.soccer'), 'icon' => 'fa-futbol'];
        case 'basketball':
            return ['label' => t('sport.basketball'), 'icon' => 'fa-basketball'];
        case 'tennis':
            return ['label' => t('sport.tennis'), 'icon' => 'fa-table-tennis-paddle-ball'];
        default:
            return ['label' => t('sport.generic'), 'icon' => 'fa-trophy'];
    }
}

/** Import équilibré : N sports max par groupe (Tennis, Basket, Foot). */
function prioritizeSportsByGroup(array $sports, int $maxPerGroup = 6): array
{
    $byGroup = [];
    foreach (ODDS_SPORT_GROUPS as $g) {
        $byGroup[$g] = [];
    }
    foreach ($sports as $s) {
        $g = $s['group'] ?? '';
        if (isset($byGroup[$g])) {
            $byGroup[$g][] = $s;
        }
    }
    $out = [];
    foreach (ODDS_SPORT_GROUPS as $g) {
        $out = array_merge($out, array_slice($byGroup[$g], 0, $maxPerGroup));
    }
    return $out;
}

function marketTypeLabel(string $type): string
{
    switch ($type) {
        case '1x2':         return t('market.result');
        case 'buteur':      return t('market.scorer');
        case 'score_exact': return t('market.exact_score');
        default:            return $type;
    }
}

function marketPoints(string $type): int
{
    switch ($type) {
        case '1x2':         return POINTS_1X2;
        case 'buteur':      return POINTS_BUTEUR;
        case 'score_exact': return POINTS_SCORE_EXACT;
        default:            return 0;
    }
}

function formatPickLabel(array $row, string $reponse): string
{
    $type = $row['market_type'] ?? '1x2';
    if ($type === 'score_exact') {
        return t('market.score_pick', ['score' => $reponse]);
    }
    if ($type === 'buteur') {
        return $reponse;
    }
    if ($reponse === '1') {
        return $row['equipe_home'];
    }
    if ($reponse === '2') {
        return $row['equipe_away'];
    }
    return t('market.draw');
}

/**
 * Regroupe les scores exacts : domicile / nul / extérieur, triés pour la UI.
 *
 * @param list<string|array{libelle?:string}> $scores
 * @return array{home:list<string>,draw:list<string>,away:list<string>}
 */
function groupExactScores(array $scores): array
{
    $home = [];
    $draw = [];
    $away = [];
    foreach ($scores as $item) {
        $score = is_array($item) ? marketOptionLabel($item) : (string) $item;
        if (!preg_match('/^(\d+)-(\d+)$/', $score, $m)) {
            continue;
        }
        $h = (int) $m[1];
        $a = (int) $m[2];
        if ($h > $a) {
            $home[] = $score;
        } elseif ($h < $a) {
            $away[] = $score;
        } else {
            $draw[] = $score;
        }
    }

    $sortPair = static function (array $list, bool $homeFirst): array {
        usort($list, static function (string $x, string $y) use ($homeFirst): int {
            [$xh, $xa] = array_map('intval', explode('-', $x, 2));
            [$yh, $ya] = array_map('intval', explode('-', $y, 2));
            if ($homeFirst) {
                return $xh <=> $yh ?: $xa <=> $ya;
            }

            return $xa <=> $ya ?: $xh <=> $yh;
        });

        return $list;
    };

    return [
        'home' => $sortPair($home, true),
        'draw' => $sortPair($draw, true),
        'away' => $sortPair($away, false),
    ];
}

/**
 * Score exact valide : format H-A avec 0…EXACT_SCORE_CUSTOM_MAX buts par équipe.
 */
function isValidExactScorePick(string $reponse): bool
{
    $reponse = trim($reponse);
    if ($reponse === '' || !preg_match('/^(\d{1,2})-(\d{1,2})$/', $reponse, $m)) {
        return false;
    }
    $h = (int) $m[1];
    $a = (int) $m[2];
    $max = defined('EXACT_SCORE_CUSTOM_MAX') ? (int) EXACT_SCORE_CUSTOM_MAX : 20;

    return $h >= 0 && $a >= 0 && $h <= $max && $a <= $max;
}

function matchWindowBounds(): array
{
    return [
        time() - 1800,
        time() + (MATCHS_HORIZON_JOURS * 86400),
    ];
}

/** Expression SQL « maintenant » — alignée sur date_match stockées en UTC. */
function matchSqlNow(): string
{
    return 'UTC_TIMESTAMP()';
}

/** Fenêtre d'import API → BDD (plus large que l'affichage). */
function importWindowBounds(): array
{
    return [
        time() - 1800,
        time() + (MATCHS_IMPORT_HORIZON_JOURS * 86400),
    ];
}

function isWithinMatchWindow(string $dateMatch): bool
{
    [$min, $max] = matchWindowBounds();
    $ts = utcDatetimeTimestamp($dateMatch);
    return $ts !== false && $ts >= $min && $ts <= $max;
}

function isWithinImportWindow(int $startTs): bool
{
    [$min, $max] = importWindowBounds();
    return $startTs >= $min && $startTs <= $max;
}

function importSkipStatsReset(): void
{
    $GLOBALS['_import_skip_stats'] = [
        'outrights' => 0,
        'past'      => 0,
        'future'    => 0,
        'no_teams'  => 0,
        'bad_sport' => 0,
        'inserted'  => 0,
        'updated'   => 0,
        'reopened'  => 0,
    ];
}

/** @return array<string,int> */
function importSkipStats(): array
{
    return $GLOBALS['_import_skip_stats'] ?? [];
}

function importSkip(string $reason): void
{
    if (!isset($GLOBALS['_import_skip_stats'])) {
        importSkipStatsReset();
    }
    if (!isset($GLOBALS['_import_skip_stats'][$reason])) {
        $GLOBALS['_import_skip_stats'][$reason] = 0;
    }
    $GLOBALS['_import_skip_stats'][$reason]++;
}

function syncMatchMarketCloseTimes(PDO $pdo, int $matchId, string $fermeLeUtc): void
{
    $pdo->prepare('UPDATE prediction_markets SET ferme_le = ? WHERE match_id = ?')
        ->execute([$fermeLeUtc, $matchId]);
}

function ensureMatchMarkets(PDO $pdo, int $matchId, string $sportKey, string $fermeLe): void
{
    $stmt = $pdo->prepare('SELECT type FROM prediction_markets WHERE match_id = ?');
    $stmt->execute([$matchId]);
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('1x2', $existing, true)) {
        $pdo->prepare(
            'INSERT INTO prediction_markets (match_id, type, points_si_correct, ferme_le) VALUES (?, "1x2", ?, ?)'
        )->execute([$matchId, POINTS_1X2, $fermeLe]);
    }

    if (!isSoccerSport($sportKey)) {
        return;
    }

    if (!in_array('score_exact', $existing, true)) {
        $pdo->prepare(
            'INSERT INTO prediction_markets (match_id, type, points_si_correct, ferme_le) VALUES (?, "score_exact", ?, ?)'
        )->execute([$matchId, POINTS_SCORE_EXACT, $fermeLe]);
        // Pas de seed BDD : score exact en saisie libre côté UI.
    }

    if (!in_array('buteur', $existing, true)) {
        $pdo->prepare(
            'INSERT INTO prediction_markets (match_id, type, points_si_correct, ferme_le) VALUES (?, "buteur", ?, ?)'
        )->execute([$matchId, POINTS_BUTEUR, $fermeLe]);
    }
}

/** @deprecated Les scores exacts ne sont plus stockés — voir COMMON_SCORES + getMarketsForMatches. */
function seedScoreOptions(PDO $pdo, int $marketId): void
{
    // no-op (rétrocompat si un ancien appel reste)
}

/** Probabilités implicites depuis cotes décimales h2h. */
function isDrawOutcomeName(string $name): bool
{
    static $labels = ['draw', 'nul', 'match nul', 'tie', 'x', 'empate', 'remis', 'égalité', 'egalite'];
    return in_array(strtolower(trim($name)), $labels, true);
}

function impliedProbabilities(string $home, string $away, array $outcomes, bool $hasDraw): array
{
    $raw = [];
    foreach ($outcomes as $o) {
        $price = (float) ($o['price'] ?? 0);
        if ($price <= 1.0) {
            continue;
        }
        $name = trim($o['name'] ?? '');
        if (strcasecmp($name, $home) === 0) {
            $raw['1'] = 1 / $price;
        } elseif (strcasecmp($name, $away) === 0) {
            $raw['2'] = 1 / $price;
        } elseif ($hasDraw && isDrawOutcomeName($name)) {
            $raw['N'] = 1 / $price;
        }
    }

    // 3 issues : la cote restante = nul même si le libellé varie
    if ($hasDraw && !isset($raw['N'])) {
        foreach ($outcomes as $o) {
            $price = (float) ($o['price'] ?? 0);
            if ($price <= 1.0) {
                continue;
            }
            $name = trim($o['name'] ?? '');
            if (strcasecmp($name, $home) !== 0 && strcasecmp($name, $away) !== 0) {
                $raw['N'] = 1 / $price;
                break;
            }
        }
    }

    $sum = array_sum($raw);
    if ($sum <= 0) {
        return [];
    }

    $probs = [];
    foreach ($raw as $k => $v) {
        $probs[$k] = round(($v / $sum) * 100, 1);
    }
    return $probs;
}

/** Choisit le bookmaker qui donne le set de probas le plus complet. */
function extractH2hProbabilities(string $home, string $away, array $bookmakers, bool $needsDraw): array
{
    $best      = [];
    $bestScore = -1;

    foreach ($bookmakers as $book) {
        foreach ($book['markets'] ?? [] as $market) {
            if (($market['key'] ?? '') !== 'h2h') {
                continue;
            }
            $probs = impliedProbabilities($home, $away, $market['outcomes'] ?? [], $needsDraw);
            if ($needsDraw && isset($probs['1'], $probs['N'], $probs['2'])) {
                return $probs;
            }
            if (!$needsDraw && isset($probs['1'], $probs['2'])) {
                return $probs;
            }
            $score = count($probs);
            if ($score > $bestScore) {
                $best      = $probs;
                $bestScore = $score;
            }
        }
    }

    return $best;
}

function matchNeedsOdds(array $m): bool
{
    if ($m['prob_1'] === null || $m['prob_2'] === null || $m['prob_1'] === '' || $m['prob_2'] === '') {
        return true;
    }
    if (isSoccerSport($m['sport']) && ($m['prob_n'] === null || $m['prob_n'] === '')) {
        return true;
    }
    return false;
}

function syncSportOdds(PDO $pdo, string $sportKey, bool $bypassCache = false): int
{
    if (!oddsApiConfigured()) {
        return 0;
    }

    ensureMatchProbColumns($pdo);

    $hasDraw = matchHasDraw($sportKey);
    $updated = 0;

    $events = oddsFetchSportOddsResilient($sportKey, 'h2h', $bypassCache);
    if (empty($events)) {
        markSportOddsAvailability($sportKey, false);
        return 0;
    }
    markSportOddsAvailability($sportKey, true);

    $byId = $pdo->prepare('SELECT id FROM matches WHERE external_id = ? AND statut = "a_venir"');
    $byTeams = $pdo->prepare(
        'SELECT id FROM matches WHERE sport = ? AND equipe_home = ? AND equipe_away = ? AND statut = "a_venir" LIMIT 1'
    );
    $update = $pdo->prepare('UPDATE matches SET prob_1 = ?, prob_n = ?, prob_2 = ? WHERE id = ?');

    foreach ($events as $event) {
        $externalId = $event['id'] ?? null;
        $home       = trim((string) ($event['home_team'] ?? ''));
        $away       = trim((string) ($event['away_team'] ?? ''));
        $probs      = extractH2hProbabilities($home, $away, $event['bookmakers'] ?? [], $hasDraw);

        if (empty($probs)) {
            continue;
        }

        $matchId = 0;
        if ($externalId) {
            $byId->execute([$externalId]);
            $matchRow = $byId->fetch();
            if ($matchRow) {
                $matchId = (int) $matchRow['id'];
            }
        }
        if ($matchId <= 0 && $home !== '' && $away !== '') {
            $byTeams->execute([$sportKey, $home, $away]);
            $matchRow = $byTeams->fetch();
            if ($matchRow) {
                $matchId = (int) $matchRow['id'];
            }
        }
        if ($matchId <= 0) {
            continue;
        }

        $update->execute([
            $probs['1'] ?? null,
            $probs['N'] ?? null,
            $probs['2'] ?? null,
            $matchId,
        ]);
        $updated++;
    }

    return $updated;
}

/** Extrait les noms de buteurs depuis player_goal_scorer_anytime (format US). */
function parseScorerOutcomes(array $outcomes): array
{
    $scorers = [];
    foreach ($outcomes as $o) {
        $price = (float) ($o['price'] ?? 0);
        if ($price <= 1.0) {
            continue;
        }
        $label = trim($o['description'] ?? '');
        if ($label === '') {
            $label = trim($o['name'] ?? '');
        }
        if ($label === '' || strcasecmp($label, 'Yes') === 0 || strcasecmp($label, 'No') === 0) {
            continue;
        }
        if (strcasecmp($o['name'] ?? '', 'No') === 0) {
            continue;
        }
        $scorers[$label] = $price;
    }
    return $scorers;
}

function saveButeurOptions(PDO $pdo, int $matchId, array $scorers): void
{
    if (empty($scorers)) {
        return;
    }
    asort($scorers, SORT_NUMERIC);
    $stmt = $pdo->prepare(
        'SELECT id FROM prediction_markets WHERE match_id = ? AND type = "buteur" LIMIT 1'
    );
    $stmt->execute([$matchId]);
    $buteurMarket = $stmt->fetch();
    if (!$buteurMarket) {
        return;
    }
    $marketId = (int) $buteurMarket['id'];
    $pdo->prepare('DELETE FROM market_options WHERE market_id = ?')->execute([$marketId]);
    $ins = $pdo->prepare('INSERT INTO market_options (market_id, libelle) VALUES (?, ?)');
    $n = 0;
    foreach (array_keys($scorers) as $player) {
        if (++$n > 14) {
            break;
        }
        $ins->execute([$marketId, $player]);
    }
}

/** Buteurs via /events/{id}/odds (1 crédit/match, région US). */
function syncMatchScorers(PDO $pdo, int $matchId, string $sportKey, string $externalId): bool
{
    if (!oddsApiConfigured() || !soccerSportHasScorerOdds($sportKey)) {
        return false;
    }

    $event = oddsFetchEventOdds($sportKey, $externalId, 'player_goal_scorer_anytime', ODDS_SCORER_REGIONS);
    $scorers = [];

    foreach ($event['bookmakers'] ?? [] as $book) {
        foreach ($book['markets'] ?? [] as $market) {
            if (($market['key'] ?? '') !== 'player_goal_scorer_anytime') {
                continue;
            }
            $scorers = parseScorerOutcomes($market['outcomes'] ?? []);
            if (!empty($scorers)) {
                break 2;
            }
        }
    }

    if (empty($scorers)) {
        return false;
    }

    saveButeurOptions($pdo, $matchId, $scorers);
    return true;
}

function matchNeedsScorers(array $m, array $marketsForMatch): bool
{
    if (!isSoccerSport($m['sport']) || !soccerSportHasScorerOdds($m['sport'])) {
        return false;
    }
    foreach ($marketsForMatch as $mk) {
        if ($mk['type'] === 'buteur' && empty($mk['options'])) {
            return true;
        }
    }
    return false;
}

/** Buteurs pour les matchs foot affichés sans options (max 12 appels API / sync). */
function syncDisplayedMatchScorers(PDO $pdo, int $maxMatches = 12): int
{
    if (!oddsApiConfigured()) {
        return 0;
    }

    $display = getUpcomingMatches($pdo);
    $markets = getMarketsForMatches($pdo, array_column($display, 'id'));
    $n = 0;

    foreach ($display as $m) {
        if ($n >= $maxMatches) {
            break;
        }
        $mid = (int) $m['id'];
        if (!matchNeedsScorers($m, $markets[$mid] ?? [])) {
            continue;
        }
        if (empty($m['external_id'])) {
            continue;
        }
        if (syncMatchScorers($pdo, $mid, $m['sport'], $m['external_id'])) {
            $n++;
        }
    }

    return $n;
}

function matchesHaveProbColumns(PDO $pdo, bool $forceRefresh = false): bool
{
    static $has = null;
    if ($forceRefresh) {
        $has = null;
    }
    if ($has !== null) {
        return $has;
    }
    try {
        $pdo->query('SELECT prob_1 FROM matches LIMIT 1');
        $has = true;
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

function matchProbColumnsReadyPath(): string
{
    return APP_CACHE_DIR . '/match_prob_columns.ok';
}

/** Ajoute les colonnes prob_* si absentes (migration auto). */
function ensureMatchProbColumns(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    if (is_file(matchProbColumnsReadyPath()) || matchesHaveProbColumns($pdo)) {
        if (!is_file(matchProbColumnsReadyPath()) && matchesHaveProbColumns($pdo)) {
            @file_put_contents(matchProbColumnsReadyPath(), '1');
        }
        $done = true;
        ensureMatchSportColumn($pdo);
        return;
    }
    $cols = [
        'prob_1 DECIMAL(5,1) NULL',
        'prob_n DECIMAL(5,1) NULL',
        'prob_2 DECIMAL(5,1) NULL',
    ];
    foreach ($cols as $col) {
        try {
            $pdo->exec('ALTER TABLE matches ADD COLUMN ' . $col);
        } catch (Throwable $e) {
            // Colonne déjà là
        }
    }
    matchesHaveProbColumns($pdo, true);
    if (matchesHaveProbColumns($pdo)) {
        @file_put_contents(matchProbColumnsReadyPath(), '1');
    }
    $done = true;
    ensureMatchSportColumn($pdo);
}

/** Élargit matches.sport si trop court (clés API type soccer_uefa_...). */
function ensureMatchSportColumn(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $col = $pdo->query('SHOW COLUMNS FROM matches LIKE "sport"')->fetch();
        if (!$col) {
            return;
        }
        if (preg_match('/varchar\((\d+)\)/i', (string) ($col['Type'] ?? ''), $m) && (int) $m[1] < 100) {
            $pdo->exec('ALTER TABLE matches MODIFY sport VARCHAR(100) NOT NULL');
        }
    } catch (Throwable $e) {
        // Migration manuelle : db/migrations/003_sport_column.sql
    }
}

/** Probas h2h pour les sports des matchs affichés SANS cote en BDD (1 crédit h2h / sport). */
/**
 * @return array{updated:int,sports:list<string>,skipped_quota:bool,nothing_to_do:bool}
 */
function syncDisplayedMatchOdds(PDO $pdo, bool $force = false): array
{
    $out = [
        'updated'       => 0,
        'sports'        => [],
        'skipped_quota' => false,
        'nothing_to_do' => false,
    ];

    ensureMatchProbColumns($pdo);
    if (!oddsApiConfigured() || !matchesHaveProbColumns($pdo) || !oddsQuotaAllows('odds')) {
        $out['skipped_quota'] = !oddsQuotaAllows('odds');
        return $out;
    }

    // force = ignore le throttle horaire, PAS le filtre « déjà en BDD ».
    // Recharger des probas déjà présentes = crédits jetés.
    $needCounts = [];
    foreach (getUpcomingMatches($pdo) as $m) {
        if (matchNeedsOdds($m)) {
            $sport = (string) $m['sport'];
            $needCounts[$sport] = ($needCounts[$sport] ?? 0) + 1;
        }
    }

    if ($needCounts === []) {
        $out['nothing_to_do'] = true;
        return $out;
    }

    // Priorité aux ligues avec le plus de matchs affichés sans cote.
    arsort($needCounts, SORT_NUMERIC);
    $sportKeys = array_keys($needCounts);
    $maxSports = $force ? (int) ODDS_FORCE_MAX_SPORTS : 2;
    if (count($sportKeys) > $maxSports) {
        $sportKeys = array_slice($sportKeys, 0, $maxSports);
    }

    foreach ($sportKeys as $sportKey) {
        if (!oddsQuotaAllows('odds')) {
            $out['skipped_quota'] = true;
            break;
        }
        // Jamais bypassCache : le fichier cache 6 h + la BDD font foi.
        $n = syncSportOdds($pdo, $sportKey, false);
        $out['updated'] += $n;
        $out['sports'][] = $sportKey . ':' . $n;
    }

    return $out;
}

/** Nombre de matchs affichés avec cotes 1 / 2 (et N si foot). */
function countDisplayedOddsCoverage(PDO $pdo): array
{
    $displayed = getUpcomingMatches($pdo);
    $with      = 0;
    foreach ($displayed as $m) {
        if (!matchNeedsOdds($m)) {
            $with++;
        }
    }

    return ['with' => $with, 'total' => count($displayed)];
}

function scorersSyncLastRunPath(): string
{
    return APP_CACHE_DIR . '/last_scorers_sync.txt';
}

/** Buteurs : sync ciblée sur les matchs affichés (1 crédit / match MLS, EPL…). */
function maybeSyncScorers(PDO $pdo): bool
{
    if (!oddsQuotaAllows('scorers')) {
        return false;
    }

    $display = getUpcomingMatches($pdo);
    $markets = getMarketsForMatches($pdo, array_column($display, 'id'));
    $needs   = false;
    foreach ($display as $m) {
        if (matchNeedsScorers($m, $markets[(int) $m['id']] ?? [])) {
            $needs = true;
            break;
        }
    }
    if (!$needs) {
        return false;
    }

    $file = scorersSyncLastRunPath();
    if (is_file($file) && (time() - (int) file_get_contents($file)) < SCORERS_SYNC_INTERVAL_SECONDS) {
        return false;
    }

    syncDisplayedMatchScorers($pdo);

    if (!is_dir(APP_CACHE_DIR)) {
        mkdir(APP_CACHE_DIR, 0755, true);
    }
    file_put_contents($file, (string) time());
    return true;
}

function oddsSyncLastRunPath(): string
{
    return APP_CACHE_DIR . '/last_odds_sync.txt';
}

/**
 * Sync probas des matchs affichés (1 crédit h2h / sport, cache 6 h).
 *
 * @return array{ran:bool,updated:int,sports:list<string>,nothing_to_do:bool,skipped_quota:bool,throttled:bool}
 */
function maybeSyncOdds(PDO $pdo, bool $force = false): array
{
    $empty = [
        'ran'           => false,
        'updated'       => 0,
        'sports'        => [],
        'nothing_to_do' => false,
        'skipped_quota' => false,
        'throttled'     => false,
    ];

    ensureMatchProbColumns($pdo);
    if (!matchesHaveProbColumns($pdo) || !oddsQuotaAllows('odds')) {
        $empty['skipped_quota'] = !oddsQuotaAllows('odds');
        return $empty;
    }

    if (!$force) {
        $needs = false;
        foreach (getUpcomingMatches($pdo) as $m) {
            if (matchNeedsOdds($m)) {
                $needs = true;
                break;
            }
        }
        if (!$needs) {
            $empty['nothing_to_do'] = true;
            return $empty;
        }

        $file = oddsSyncLastRunPath();
        if (
            is_file($file)
            && (time() - (int) file_get_contents($file)) < ODDS_SYNC_INTERVAL_SECONDS
        ) {
            $empty['throttled'] = true;
            return $empty;
        }
    }

    $detail = syncDisplayedMatchOdds($pdo, $force);
    if (!is_dir(APP_CACHE_DIR)) {
        mkdir(APP_CACHE_DIR, 0755, true);
    }
    file_put_contents(oddsSyncLastRunPath(), (string) time());

    return [
        'ran'           => true,
        'updated'       => (int) $detail['updated'],
        'sports'        => $detail['sports'],
        'nothing_to_do' => (bool) $detail['nothing_to_do'],
        'skipped_quota' => (bool) $detail['skipped_quota'],
        'throttled'     => false,
    ];
}

/** Rafraîchit les cotes si des matchs affichés n'en ont pas (throttle, jamais en force sur le web). */
function maybeSyncOddsIfNeeded(PDO $pdo): bool
{
    return !empty(maybeSyncOdds($pdo, false)['ran']);
}

function syncLastRunPath(): string
{
    return APP_CACHE_DIR . '/last_sync.txt';
}

function syncLockPath(): string
{
    return APP_CACHE_DIR . '/sync.lock';
}

/** Supprime un verrou périmé (âge fichier) — filet de sécu si flock non fiable. */
function clearStaleSyncLock(): bool
{
    $path = syncLockPath();
    if (!is_file($path)) {
        return false;
    }
    $age = time() - (int) @filemtime($path);
    if ($age < SYNC_LOCK_MAX_AGE) {
        return false;
    }
    return @unlink($path);
}

/**
 * Verrou exclusif non bloquant. Ne jamais attendre indéfiniment (évite curl pendu).
 * @return resource|false
 */
function acquireSyncLock(bool $force = false)
{
    if (!ensureAppCacheDir()) {
        return false;
    }

    clearStaleSyncLock();

    $attempts = $force ? 2 : 1;
    for ($i = 0; $i < $attempts; $i++) {
        $fp = @fopen(syncLockPath(), 'c+');
        if ($fp === false) {
            return false;
        }

        if (flock($fp, LOCK_EX | LOCK_NB)) {
            ftruncate($fp, 0);
            fwrite($fp, (string) getmypid() . ' ' . (string) time());
            fflush($fp);
            return $fp;
        }

        fclose($fp);

        // 2ᵉ essai (force) : fichier orphelin sans détenteur flock (bug historique).
        if ($force && $i === 0 && !isSyncLockHeld()) {
            @unlink(syncLockPath());
        }
    }

    return false;
}

/** @param resource|false|null $lockFp */
function releaseSyncLock($lockFp): void
{
    if (!is_resource($lockFp)) {
        return;
    }
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    // Sans unlink, isSyncLockHeld() historique prenait le fichier pour un lock vivant 10 min.
    @unlink(syncLockPath());
}

/**
 * Vrai seulement si un process détient encore le flock.
 * Un simple fichier sync.lock résiduel (sync terminée) ne compte PAS.
 */
function isSyncLockHeld(): bool
{
    clearStaleSyncLock();
    $path = syncLockPath();
    if (!is_file($path)) {
        return false;
    }

    $fp = @fopen($path, 'c+');
    if ($fp === false) {
        return false;
    }

    if (flock($fp, LOCK_EX | LOCK_NB)) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }

    fclose($fp);
    return true;
}

/**
 * Admin / console : efface un verrou orphelin. Refuse si une sync détient encore le flock.
 *
 * @return array{cleared:bool,busy:bool,path:string}
 */
function clearIdleSyncLock(): array
{
    $path = syncLockPath();
    if (isSyncLockHeld()) {
        return ['cleared' => false, 'busy' => true, 'path' => $path];
    }
    $existed = is_file($path);
    if ($existed) {
        @unlink($path);
    }
    clearStaleSyncLock();
    return ['cleared' => $existed || !is_file($path), 'busy' => false, 'path' => $path];
}

/** Lance la sync matchs en arrière-plan (CLI) — ne bloque pas le site. */
function triggerBackgroundMatchSync(bool $refreshEvents = false): bool
{
    $script = dirname(__DIR__) . '/tools/sync_matches.php';
    if (!is_file($script)) {
        return false;
    }

    $php = (defined('PHP_BINARY') && PHP_BINARY !== '') ? PHP_BINARY : 'php';
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script);
    if ($refreshEvents) {
        $cmd .= ' --refresh';
    }

    if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
        $full = 'start /B "" ' . $cmd . ' > NUL 2>&1';
        @pclose(@popen($full, 'r'));
        return true;
    }

    if (!function_exists('exec')) {
        return false;
    }

    exec($cmd . ' > /dev/null 2>&1 &');

    return true;
}

/** @return array{ran:bool,skip_reason:?string,sports_checked:int,events_fetched:int,events_imported:int,upcoming_fetched:int,active_tennis:int,active_basketball:int,active_soccer:int} */
function runMatchImportSync(PDO $pdo, bool $force = false, bool $refreshEvents = false): array
{
    $result = [
        'ran'              => false,
        'skip_reason'      => null,
        'sports_checked'   => 0,
        'events_fetched'   => 0,
        'events_imported'  => 0,
        'upcoming_fetched' => 0,
        'active_tennis'    => 0,
        'active_basketball'=> 0,
        'active_soccer'    => 0,
        'cross_sport_fetched' => 0,
        'fetched_by_sport' => [],
    ];

    if (!oddsApiConfigured()) {
        $result['skip_reason'] = 'no_api_key';
        return $result;
    }

    $lastRunFile = syncLastRunPath();
    if ($force) {
        @unlink($lastRunFile);
    } elseif (is_file($lastRunFile) && (time() - (int) file_get_contents($lastRunFile)) < SYNC_INTERVAL_SECONDS) {
        $result['skip_reason'] = 'throttled';
        return $result;
    }

    if (!ensureAppCacheDir()) {
        $result['skip_reason'] = 'cache_not_writable';
        return $result;
    }

    $lockFp = acquireSyncLock($force);
    if ($lockFp === false) {
        $result['skip_reason'] = is_file(syncLockPath())
            ? 'lock_busy'
            : 'lock_open_failed';
        return $result;
    }

    $deadline = $force ? (time() + SYNC_FORCE_MAX_SECONDS) : null;

    try {
        @set_time_limit($force ? SYNC_FORCE_MAX_SECONDS + 30 : 120);
        ensureMatchProbColumns($pdo);
        importSkipStatsReset();

        $sports = oddsSportsForSync($force, !$force);
        if ($force && count($sports) > SYNC_FORCE_MAX_SPORTS) {
            // Pas de array_slice naïf : tennis est trié en premier et mangait les 28 slots.
            $sports = oddsLimitSportsBalanced($sports, (int) SYNC_FORCE_MAX_SPORTS);
        }
        $result['sports_checked'] = count($sports);
        $result['active_sport_keys'] = array_column($sports, 'key');
        foreach ($sports as $sportMeta) {
            switch ($sportMeta['group'] ?? '') {
                case 'Tennis':     $result['active_tennis']++; break;
                case 'Basketball': $result['active_basketball']++; break;
                case 'Soccer':     $result['active_soccer']++; break;
            }
        }

        $fetchFresh = $refreshEvents || !$force;
        $importStats = syncUpcomingMatchesForSports($pdo, $sports, false, $fetchFresh, $deadline);
        $result['events_fetched'] += $importStats['fetched'];
        $result['events_imported'] += $importStats['imported'];
        $result['fetched_by_sport'] = $importStats['by_sport'];
        $result['import_skips'] = importSkipStats();

        // Import = /events gratuit + BDD. Les scores sont gérés uniquement par
        // resolveMatchResults / cron (évite un 2ᵉ /scores payant dans la même sync).
        scorePendingFinishedMatches($pdo);
        file_put_contents($lastRunFile, (string) time());
        $result['ran'] = true;
    } finally {
        releaseSyncLock($lockFp);
    }

    return $result;
}

/** Sync API complète, avec throttle et verrou (ne bloque pas l'affichage si déjà à jour). */
function maybeSyncMatches(PDO $pdo, bool $forceRefresh = false): bool
{
    return runMatchImportSync($pdo, $forceRefresh)['ran'];
}

function importCalendarEvent(PDO $pdo, array $event, array $sportMeta): bool
{
    $sportKey = $sportMeta['key'] ?? ($event['sport_key'] ?? '');

    if (!empty($event['has_outrights'])) {
        importSkip('outrights');
        return false;
    }

    $externalId = $event['id'] ?? null;
    $start      = $event['commence_time'] ?? null;
    $startTs    = $start ? strtotime($start) : false;
    if (!$externalId || !$start || $startTs === false) {
        importSkip('past');
        return false;
    }

    [$windowMin] = importWindowBounds();
    if ($startTs < $windowMin) {
        importSkip('past');
        return false;
    }

    $home = $event['home_team'] ?? '';
    $away = $event['away_team'] ?? '';
    if ($home === '' || $away === '') {
        importSkip('no_teams');
        return false;
    }

    $dateMatch   = gmdate('Y-m-d H:i:s', $startTs);
    $competition = $event['sport_title'] ?? ($sportMeta['title'] ?? $sportKey);
    $eventSport  = $event['sport_key'] ?? $sportKey;

    if (sportGroupFromKey($eventSport) === '') {
        importSkip('bad_sport');
        return false;
    }

    $stmt = $pdo->prepare('SELECT id, statut FROM matches WHERE external_id = ?');
    $stmt->execute([$externalId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $reopen = $existing['statut'] !== 'a_venir'
            && $startTs >= time() - 1800
            && isWithinImportWindow($startTs);

        if ($existing['statut'] !== 'a_venir' && !$reopen) {
            importSkip('past');
            return false;
        }

        $pdo->prepare(
            'UPDATE matches SET date_match = ?, competition = ?, sport = ?, equipe_home = ?, equipe_away = ?, statut = "a_venir"
             WHERE id = ?'
        )->execute([$dateMatch, $competition, $eventSport, $home, $away, $existing['id']]);
        ensureMatchMarkets($pdo, (int) $existing['id'], $eventSport, $dateMatch);
        syncMatchMarketCloseTimes($pdo, (int) $existing['id'], $dateMatch);
        importSkip($reopen ? 'reopened' : 'updated');
        return true;
    }

    if (!isWithinImportWindow($startTs)) {
        importSkip('future');
        return false;
    }

    $pdo->prepare(
        'INSERT INTO matches (external_id, sport, competition, equipe_home, equipe_away, date_match, statut)
         VALUES (?, ?, ?, ?, ?, ?, "a_venir")'
    )->execute([$externalId, $eventSport, $competition, $home, $away, $dateMatch]);

    ensureMatchMarkets($pdo, (int) $pdo->lastInsertId(), $eventSport, $dateMatch);
    importSkip('inserted');
    return true;
}

/** @return array{fetched:int,imported:int} */
function syncUpcomingEventsBatch(PDO $pdo, array $events, ?int $deadline = null): array
{
    $stats = ['fetched' => count($events), 'imported' => 0];
    foreach ($events as $event) {
        if ($deadline !== null && time() >= $deadline) {
            break;
        }
        $sportKey = $event['sport_key'] ?? '';
        $group    = sportGroupFromKey($sportKey);
        if ($group === '') {
            continue;
        }
        if (importCalendarEvent($pdo, $event, [
            'key'   => $sportKey,
            'group' => $group,
            'title' => $event['sport_title'] ?? $sportKey,
        ])) {
            $stats['imported']++;
        }
    }

    return $stats;
}

/** @return array{fetched:int,imported:int,by_sport:array<string,int>} */
function syncUpcomingMatchesForSports(PDO $pdo, array $sports, bool $cacheOnly = false, bool $forceRefresh = false, ?int $deadline = null): array
{
    $stats = ['fetched' => 0, 'imported' => 0, 'by_sport' => []];
    foreach ($sports as $sportMeta) {
        if ($deadline !== null && time() >= $deadline) {
            break;
        }
        $sportKey = $sportMeta['key'];
        $events = $cacheOnly
            ? oddsReadEventsCache($sportKey, true)
            : oddsFetchEvents($sportKey, $forceRefresh);

        $stats['fetched'] += count($events);
        $stats['by_sport'][$sportKey] = count($events);
        foreach ($events as $event) {
            if ($deadline !== null && time() >= $deadline) {
                break 2;
            }
            if (importCalendarEvent($pdo, $event, $sportMeta)) {
                $stats['imported']++;
            }
        }
    }

    return $stats;
}

function syncUpcomingMatches(PDO $pdo): void
{
    syncUpcomingMatchesForSports($pdo, array_slice(oddsFetchActiveSportsByGroup(), 0, 10), false);
}

/** Retire de la liste les matchs passés (sans appel API). */
function closeExpiredMatches(PDO $pdo): int
{
    $mins = (int) MATCH_CLOSE_AFTER_MINUTES;
    $now  = matchSqlNow();
    $stmt = $pdo->prepare(
        "UPDATE matches SET statut = \"termine\"
         WHERE statut IN (\"a_venir\", \"en_cours\")
           AND date_match < DATE_SUB({$now}, INTERVAL ? MINUTE)"
    );
    $stmt->execute([$mins]);
    return $stmt->rowCount();
}

function sportGroupFromKey(string $sportKey): string
{
    if (strncmp($sportKey, 'tennis_', 7) === 0) {
        return 'Tennis';
    }
    if (strncmp($sportKey, 'basketball_', 11) === 0) {
        return 'Basketball';
    }
    if (strncmp($sportKey, 'soccer_', 7) === 0) {
        return 'Soccer';
    }
    return '';
}

/** Sports ayant un fichier cache events_*.json (même périmé). */
function getSportsWithCachedEvents(): array
{
    if (!is_dir(APP_CACHE_DIR)) {
        return [];
    }

    $allowed = array_flip(ODDS_SPORT_GROUPS);
    $sports  = [];

    foreach (glob(APP_CACHE_DIR . '/events_*.json') ?: [] as $path) {
        $sportKey = substr(basename($path, '.json'), strlen('events_'));
        if ($sportKey === '') {
            continue;
        }
        $group = sportGroupFromKey($sportKey);
        if ($group === '' || !isset($allowed[$group])) {
            continue;
        }
        if (empty(oddsReadEventsCache($sportKey, true))) {
            continue;
        }
        $sports[] = [
            'key'   => $sportKey,
            'title' => $sportKey,
            'group' => $group,
        ];
    }

    return prioritizeSportsByGroup($sports, 12);
}

function cacheRefreshLastRunPath(): string
{
    return APP_CACHE_DIR . '/last_cache_refresh.txt';
}

/**
 * Fait tourner les matchs affichés depuis le cache local (0 crédit API).
 * Ferme les matchs passés + importe les nouveaux événements en cache.
 */
function refreshMatchesFromCache(PDO $pdo): array
{
    $closed = closeExpiredMatches($pdo);

    $sports = getSportsWithCachedEvents();
    if (!empty($sports)) {
        syncUpcomingMatchesForSports($pdo, $sports, true);
    }

    return ['closed' => $closed, 'sports' => count($sports)];
}

function maybeRefreshMatchesFromCache(PDO $pdo): bool
{
    if (!is_dir(APP_CACHE_DIR)) {
        mkdir(APP_CACHE_DIR, 0755, true);
    }

    $file = cacheRefreshLastRunPath();
    if (is_file($file) && (time() - (int) file_get_contents($file)) < CACHE_REFRESH_INTERVAL_SECONDS) {
        return false;
    }

    refreshMatchesFromCache($pdo);
    file_put_contents($file, (string) time());
    return true;
}

/**
 * Ligues à interroger : uniquement celles qui ont des pronos encore en attente
 * sur des matchs réellement terminés. Priorité au plus gros volume de pronos.
 * (Interroger une ligue sans prono = 2 crédits jetés.)
 *
 * @return list<string>
 */
function sportsAwaitingResults(PDO $pdo, int $maxSports): array
{
    $rows = listSportsAwaitingResults($pdo, $maxSports);

    return array_values(array_map(
        static fn (array $r): string => (string) ($r['sport'] ?? ''),
        $rows
    ));
}

/**
 * Détail des ligues en attente de score API (pour admin / budget backlog).
 *
 * @return list<array{sport:string,pending_count:int,match_count:int,oldest:?string}>
 */
function listSportsAwaitingResults(PDO $pdo, int $maxSports = 50): array
{
    $now      = matchSqlNow();
    $maxWait  = (int) RESULT_MAX_WAIT_DAYS;
    $readyMin = (int) MATCH_RESULT_READY_MINUTES;
    $maxSports = max(1, min(80, $maxSports));

    $stmt = $pdo->query(
        "SELECT m.sport,
                COUNT(p.id) AS pending_count,
                COUNT(DISTINCT m.id) AS match_count,
                MIN(m.date_match) AS oldest
         FROM matches m
         INNER JOIN prediction_markets pm ON pm.match_id = m.id
         INNER JOIN predictions p ON p.market_id = pm.id AND p.statut = 'en_attente'
         WHERE m.date_match < DATE_SUB({$now}, INTERVAL {$readyMin} MINUTE)
           AND m.date_match >= DATE_SUB({$now}, INTERVAL {$maxWait} DAY)
           AND m.statut NOT IN ('annule', 'reporte')
           AND (m.resultat_1x2 IS NULL OR m.resultat_1x2 = '')
           AND m.sport <> ''
         GROUP BY m.sport
         ORDER BY pending_count DESC, oldest ASC
         LIMIT {$maxSports}"
    );

    $out = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $sport = (string) ($row['sport'] ?? '');
        if ($sport === '') {
            continue;
        }
        $out[] = [
            'sport'         => $sport,
            'pending_count' => (int) ($row['pending_count'] ?? 0),
            'match_count'   => (int) ($row['match_count'] ?? 0),
            'oldest'        => $row['oldest'] !== null ? (string) $row['oldest'] : null,
        ];
    }

    return $out;
}

/** True si le coup d’envoi est encore dans la fenêtre /scores de l’API (~3 j). */
function matchIsInScoresApiWindow(array $match): bool
{
    $ts = utcDatetimeTimestamp((string) ($match['date_match'] ?? ''));
    if ($ts === false) {
        return false;
    }

    return $ts >= (time() - ((int) SCORES_CATCHUP_DAYS * 86400));
}

/**
 * Synthèse file d’attente admin : récupérables API vs trop vieux.
 *
 * @return array{
 *   total:int,api_window:int,too_old:int,sports_api:int,
 *   credits_est:int,sports:list<array{sport:string,pending_count:int,match_count:int,oldest:?string}>
 * }
 */
function summarizeStuckScoresQueue(PDO $pdo): array
{
    $stuck = listStuckMatchesForManualScore($pdo, 200);
    $api = 0;
    $old = 0;
    foreach ($stuck as $m) {
        if (matchIsInScoresApiWindow($m)) {
            $api++;
        } else {
            $old++;
        }
    }
    $sports = listSportsAwaitingResults($pdo, 50);

    return [
        'total'       => count($stuck),
        'api_window'  => $api,
        'too_old'     => $old,
        'sports_api'  => count($sports),
        'credits_est' => count($sports) * 2,
        'sports'      => $sports,
    ];
}

/**
 * Budget sports /scores pour cette passe : 0 si quota mort.
 * Calme = 1 ligue ; backlog (plusieurs ligues bloquées) = jusqu’à SCORES_MAX_SPORTS_BACKLOG.
 */
function scoresSportsBudget(): int
{
    $remaining = oddsQuotaRemaining();
    if ($remaining !== null && $remaining <= (int) ODDS_QUOTA_RESERVE_SCORES) {
        return 0;
    }
    // Chaque ligue coûte 2 crédits : ne jamais programmer plus que le stock permet.
    $maxByCredits = $remaining === null
        ? (int) SCORES_MAX_SPORTS_BACKLOG
        : (int) floor(max(0, $remaining - (int) ODDS_QUOTA_RESERVE_SCORES) / 2);

    $cap = ($remaining !== null && $remaining <= (int) ODDS_QUOTA_RESERVE_ODDS)
        ? (int) SCORES_MAX_SPORTS_LOW_QUOTA
        : (int) SCORES_MAX_SPORTS_PER_RUN;

    // Backlog : accélère le rattrapage sans exploser le quota mensuel.
    try {
        $backlog = count(listSportsAwaitingResults(getPDO(), 20));
        if ($backlog > 1) {
            $cap = max($cap, min((int) SCORES_MAX_SPORTS_BACKLOG, $backlog));
        }
    } catch (Throwable $e) {
        // ignore
    }

    return max(0, min($cap, $maxByCredits));
}

/** Intervalle mini entre deux passes /scores (allongé si quota bas). */
function scoresSyncIntervalSeconds(): int
{
    $remaining = oddsQuotaRemaining();
    if ($remaining !== null && $remaining < (int) ODDS_QUOTA_LOW) {
        return (int) SCORES_SYNC_INTERVAL_LOW_QUOTA;
    }

    return (int) SCORES_SYNC_INTERVAL_SECONDS;
}

/**
 * Résultats API → BDD + scoring immédiat.
 * daysFrom = 3 par défaut pour rattraper les matchs restés sans résultat.
 */
function syncMatchScores(PDO $pdo, ?int $daysFrom = null, ?int $maxSports = null, bool $bypassCache = false): int
{
    if (!oddsApiConfigured()) {
        return 0;
    }

    $budget = $maxSports ?? scoresSportsBudget();
    if ($budget <= 0) {
        return 0;
    }

    $daysFrom  = $daysFrom ?? (int) SCORES_CATCHUP_DAYS;
    $sportKeys = sportsAwaitingResults($pdo, $budget);
    if (empty($sportKeys)) {
        return 0;
    }

    $byExternalId = $pdo->prepare('SELECT * FROM matches WHERE external_id = ?');
    // Repli si le match a été réimporté sous un autre external_id : on exige alors
    // un coup d'envoi proche, pour ne jamais confondre deux rencontres identiques
    // (matchs aller/retour, phases de tournoi).
    $byTeams = $pdo->prepare(
        'SELECT * FROM matches
         WHERE sport = ? AND equipe_home = ? AND equipe_away = ?
           AND (resultat_1x2 IS NULL OR resultat_1x2 = "")
           AND date_match BETWEEN DATE_SUB(?, INTERVAL 12 HOUR) AND DATE_ADD(?, INTERVAL 12 HOUR)
         ORDER BY ABS(TIMESTAMPDIFF(MINUTE, date_match, ?)) ASC
         LIMIT 2'
    );
    $setEnCours = $pdo->prepare('UPDATE matches SET statut = "en_cours" WHERE id = ? AND statut = "a_venir"');
    $setResult  = $pdo->prepare(
        'UPDATE matches SET statut = "termine", resultat_1x2 = ?, score_home = ?, score_away = ?
         WHERE id = ?'
    );

    $resolved = 0;

    foreach ($sportKeys as $sportKey) {
        // Stop net si le quota a basculé sous la réserve pendant la boucle.
        if (!oddsQuotaAllows('scores')) {
            break;
        }

        foreach (oddsFetchScores($sportKey, $daysFrom, $bypassCache) as $game) {
            if (!is_array($game)) {
                continue;
            }

            $home     = trim((string) ($game['home_team'] ?? ''));
            $away     = trim((string) ($game['away_team'] ?? ''));
            $commence = isset($game['commence_time']) ? strtotime((string) $game['commence_time']) : false;

            $match = null;
            if (!empty($game['id'])) {
                $byExternalId->execute([$game['id']]);
                $match = $byExternalId->fetch() ?: null;
            }
            if (!$match && $home !== '' && $away !== '' && $commence !== false) {
                $kickoff = gmdate('Y-m-d H:i:s', $commence);
                $byTeams->execute([$sportKey, $home, $away, $kickoff, $kickoff, $kickoff]);
                $candidates = $byTeams->fetchAll();
                // Deux candidats dans la même fenêtre : appariement ambigu, on s'abstient.
                $match = count($candidates) === 1 ? $candidates[0] : null;
            }
            if (!$match) {
                continue;
            }

            if ($match['resultat_1x2'] !== null && $match['resultat_1x2'] !== '') {
                continue;
            }

            if (empty($game['completed'])) {
                if (utcDatetimeTimestamp($match['date_match']) <= time()) {
                    $setEnCours->execute([$match['id']]);
                }
                continue;
            }

            // Scores appariés sur NOS noms d'équipes : le sens domicile/extérieur
            // stocké ne peut pas être inversé par rapport à ce que voit le joueur.
            $scores = extractMatchScores(
                $game['scores'] ?? null,
                (string) $match['equipe_home'],
                (string) $match['equipe_away']
            );
            $result1x2 = $scores === null
                ? null
                : result1x2FromScores($scores['home'], $scores['away'], matchHasDraw($match['sport']));

            // Sans score exploitable, on attend la passe suivante plutôt que de clôturer à vide.
            if ($scores === null || $result1x2 === null) {
                $setEnCours->execute([$match['id']]);
                continue;
            }

            $setResult->execute([$result1x2, $scores['home'], $scores['away'], $match['id']]);
            scoreMatch($pdo, (int) $match['id']);
            $resolved++;
        }
    }

    return $resolved;
}

/**
 * Récupère via The Odds API les scores des matchs « reporte » encore dans la fenêtre
 * daysFrom (max API = 3 jours). Réouvre les pronos annulés et attribue les points.
 *
 * @return array{checked:int,recovered:int,sports:list<string>,skipped_old:int,quota_blocked:bool}
 */
function recoverPostponedScoresFromApi(PDO $pdo, int $daysFrom = 3): array
{
    $out = [
        'checked'       => 0,
        'recovered'     => 0,
        'sports'        => [],
        'skipped_old'   => 0,
        'quota_blocked' => false,
    ];

    if (!oddsApiConfigured()) {
        return $out;
    }

    ensureMatchStatutSchema($pdo);
    $daysFrom = max(1, min(3, $daysFrom));

    $allPostponed = $pdo->query(
        "SELECT id, sport, date_match, external_id, equipe_home, equipe_away
         FROM matches
         WHERE statut = 'reporte'
           AND (resultat_1x2 IS NULL OR resultat_1x2 = '')"
    )->fetchAll() ?: [];

    if ($allPostponed === []) {
        return $out;
    }

    $inWindow = [];
    foreach ($allPostponed as $row) {
        $ts = utcDatetimeTimestamp((string) ($row['date_match'] ?? ''));
        if ($ts === null) {
            $out['skipped_old']++;
            continue;
        }
        // Fenêtre API : kick-off déjà passé et pas plus vieux que $daysFrom jours.
        if ($ts > time() || $ts < time() - ($daysFrom * 86400)) {
            $out['skipped_old']++;
            continue;
        }
        $inWindow[] = $row;
    }

    $out['checked'] = count($inWindow);
    if ($inWindow === []) {
        return $out;
    }

    $bySport = [];
    foreach ($inWindow as $row) {
        $sport = (string) ($row['sport'] ?? '');
        if ($sport === '') {
            continue;
        }
        $bySport[$sport][] = $row;
    }

    foreach ($bySport as $sportKey => $rows) {
        if (!oddsQuotaAllows('scores')) {
            $out['quota_blocked'] = true;
            break;
        }

        $games = oddsFetchScores($sportKey, $daysFrom, true);
        $out['sports'][] = $sportKey . ':' . count($games);

        $indexByExt = [];
        $indexByTeams = [];
        foreach ($games as $game) {
            if (!is_array($game) || empty($game['completed'])) {
                continue;
            }
            $gid = (string) ($game['id'] ?? '');
            if ($gid !== '') {
                $indexByExt[$gid] = $game;
            }
            $h = trim((string) ($game['home_team'] ?? ''));
            $a = trim((string) ($game['away_team'] ?? ''));
            if ($h !== '' && $a !== '') {
                $indexByTeams[mb_strtolower($h . '|' . $a)] = $game;
            }
        }

        foreach ($rows as $match) {
            $game = null;
            $ext = (string) ($match['external_id'] ?? '');
            if ($ext !== '' && isset($indexByExt[$ext])) {
                $game = $indexByExt[$ext];
            }
            if ($game === null) {
                $key = mb_strtolower(
                    trim((string) $match['equipe_home']) . '|' . trim((string) $match['equipe_away'])
                );
                $game = $indexByTeams[$key] ?? null;
            }
            if ($game === null) {
                continue;
            }

            $scores = extractMatchScores(
                $game['scores'] ?? null,
                (string) $match['equipe_home'],
                (string) $match['equipe_away']
            );
            if ($scores === null) {
                continue;
            }

            try {
                applyManualMatchScore(
                    $pdo,
                    (int) $match['id'],
                    (int) $scores['home'],
                    (int) $scores['away'],
                    null
                );
                $out['recovered']++;
            } catch (Throwable $e) {
                // Score impossible (sport sans nul, etc.) : on laisse en reporté.
            }
        }
    }

    return $out;
}

/**
 * Filet de sécurité après RESULT_MAX_WAIT_DAYS sans score API :
 * - match AVEC pronos en attente → « reporte » (visible joueur + admin)
 * - match SANS aucun prono → « annule » silencieux (pas de pollution admin)
 * Dernière chance /scores avant bascule (uniquement matchs avec pronos).
 */
function voidStalePredictions(PDO $pdo): int
{
    ensurePredictionHistorySchema($pdo);
    ensureMatchStatutSchema($pdo);

    $now     = matchSqlNow();
    $maxWait = (int) RESULT_MAX_WAIT_DAYS;
    $catchup = (int) SCORES_CATCHUP_DAYS;

    $detailStmt = $pdo->query(
        "SELECT p.id, p.user_id, u.pseudo, u.email,
                m.id AS match_id, m.equipe_home, m.equipe_away, m.competition,
                m.sport, m.date_match, pm.type AS market_type
         FROM predictions p
         INNER JOIN prediction_markets pm ON pm.id = p.market_id
         INNER JOIN matches m ON m.id = pm.match_id
         INNER JOIN users u ON u.id = p.user_id
         WHERE p.statut = 'en_attente'
           AND m.date_match < DATE_SUB({$now}, INTERVAL {$maxWait} DAY)
           AND (m.resultat_1x2 IS NULL OR m.resultat_1x2 = '')
           AND m.statut NOT IN ('annule', 'reporte')
         ORDER BY u.pseudo ASC, m.date_match ASC"
    );
    $rows = $detailStmt->fetchAll() ?: [];

    $withPendingStmt = $pdo->query(
        "SELECT m.id
         FROM matches m
         INNER JOIN prediction_markets pm ON pm.match_id = m.id
         INNER JOIN predictions p ON p.market_id = pm.id AND p.statut = 'en_attente'
         WHERE m.date_match < DATE_SUB({$now}, INTERVAL {$maxWait} DAY)
           AND (m.resultat_1x2 IS NULL OR m.resultat_1x2 = '')
           AND m.statut NOT IN ('annule', 'reporte')
         GROUP BY m.id
         ORDER BY MIN(m.date_match) ASC
         LIMIT 80"
    );
    $withPendingIds = array_map('intval', $withPendingStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

    $orphanStmt = $pdo->query(
        "SELECT m.id
         FROM matches m
         WHERE m.date_match < DATE_SUB({$now}, INTERVAL {$maxWait} DAY)
           AND (m.resultat_1x2 IS NULL OR m.resultat_1x2 = '')
           AND m.statut NOT IN ('annule', 'reporte', 'termine')
           AND NOT EXISTS (
                SELECT 1 FROM prediction_markets pm
                INNER JOIN predictions p ON p.market_id = pm.id
                WHERE pm.match_id = m.id
           )
         ORDER BY m.date_match ASC
         LIMIT 120"
    );
    $orphanIds = array_map('intval', $orphanStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

    if ($withPendingIds === [] && $orphanIds === [] && $rows === []) {
        return 0;
    }

    $candidateIds = $withPendingIds;
    if ($candidateIds !== [] && oddsApiConfigured() && oddsQuotaAllows('scores')) {
        $ph = implode(',', array_fill(0, count($candidateIds), '?'));
        $sportQ = $pdo->prepare(
            "SELECT DISTINCT sport FROM matches WHERE id IN ($ph) AND sport <> ''"
        );
        $sportQ->execute($candidateIds);
        $sports = $sportQ->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $passes = 0;
        foreach ($sports as $sportKey) {
            if ($passes >= 3 || !oddsQuotaAllows('scores')) {
                break;
            }
            $sportKey = (string) $sportKey;
            $games = oddsFetchScores($sportKey, $catchup, true);
            $passes++;
            foreach ($games as $game) {
                if (!is_array($game) || empty($game['completed']) || empty($game['id'])) {
                    continue;
                }
                $byExt = $pdo->prepare(
                    'SELECT * FROM matches WHERE external_id = ? AND (resultat_1x2 IS NULL OR resultat_1x2 = "")'
                );
                $byExt->execute([$game['id']]);
                $match = $byExt->fetch() ?: null;
                if (!$match || !in_array((int) $match['id'], $candidateIds, true)) {
                    continue;
                }
                $scores = extractMatchScores(
                    $game['scores'] ?? null,
                    (string) $match['equipe_home'],
                    (string) $match['equipe_away']
                );
                $result1x2 = $scores === null
                    ? null
                    : result1x2FromScores($scores['home'], $scores['away'], matchHasDraw($match['sport']));
                if ($scores === null || $result1x2 === null) {
                    continue;
                }
                $pdo->prepare(
                    'UPDATE matches SET statut = "termine", resultat_1x2 = ?, score_home = ?, score_away = ?
                     WHERE id = ?'
                )->execute([$result1x2, $scores['home'], $scores['away'], $match['id']]);
                scoreMatch($pdo, (int) $match['id']);
            }
        }

        $withPendingStmt = $pdo->query(
            "SELECT m.id
             FROM matches m
             INNER JOIN prediction_markets pm ON pm.match_id = m.id
             INNER JOIN predictions p ON p.market_id = pm.id AND p.statut = 'en_attente'
             WHERE m.date_match < DATE_SUB({$now}, INTERVAL {$maxWait} DAY)
               AND (m.resultat_1x2 IS NULL OR m.resultat_1x2 = '')
               AND m.statut NOT IN ('annule', 'reporte')
             GROUP BY m.id
             ORDER BY MIN(m.date_match) ASC
             LIMIT 80"
        );
        $withPendingIds = array_map('intval', $withPendingStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    $voided = 0;
    foreach ($withPendingIds as $matchId) {
        if ($matchId <= 0) {
            continue;
        }
        try {
            $voided += postponeMatch($pdo, $matchId, null);
        } catch (Throwable $e) {
            // Match déjà clôturé / score arrivé entre-temps.
        }
    }

    foreach ($orphanIds as $matchId) {
        if ($matchId <= 0) {
            continue;
        }
        try {
            cancelMatch($pdo, $matchId);
        } catch (Throwable $e) {
            // ignore
        }
    }

    if ($rows !== []) {
        $ids = [];
        foreach ($rows as $r) {
            $ids[(int) ($r['id'] ?? 0)] = true;
        }
        $ids = array_values(array_filter(array_keys($ids)));
        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare(
                "UPDATE predictions
                 SET statut = 'annule', points_gagnes = 0, resolved_at = UTC_TIMESTAMP()
                 WHERE id IN ($placeholders) AND statut = 'en_attente'"
            );
            $stmt->execute($ids);
            $voided += $stmt->rowCount();
        }
        if (function_exists('notifyAdminUnavailableResults')) {
            try {
                notifyAdminUnavailableResults($rows);
            } catch (Throwable $e) {
                // ignore
            }
        }
    }

    return $voided;
}

/**
 * Reportés sans aucune ligne predictions → hors file (statut annule).
 */
function dismissPostponedMatchesWithoutPredictions(PDO $pdo): int
{
    ensureMatchStatutSchema($pdo);
    $stmt = $pdo->query(
        "SELECT m.id
         FROM matches m
         WHERE m.statut = 'reporte'
           AND (m.resultat_1x2 IS NULL OR m.resultat_1x2 = '')
           AND NOT EXISTS (
                SELECT 1 FROM prediction_markets pm
                INNER JOIN predictions p ON p.market_id = pm.id
                WHERE pm.match_id = m.id
           )
         LIMIT 300"
    );
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    if ($ids === []) {
        return 0;
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $upd = $pdo->prepare(
        "UPDATE matches
         SET statut = 'annule', score_home = NULL, score_away = NULL
         WHERE id IN ($ph) AND statut = 'reporte'"
    );
    $upd->execute($ids);
    return $upd->rowCount();
}

/**
 * Réactive les matchs « reporté » dont la date est encore dans le futur.
 */
function reactivateFuturePostponedMatches(PDO $pdo): int
{
    ensureMatchStatutSchema($pdo);
    $now = matchSqlNow();
    $stmt = $pdo->query(
        "SELECT id FROM matches
         WHERE statut = 'reporte'
           AND date_match > {$now}
         LIMIT 100"
    );
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    $n = 0;
    foreach ($ids as $id) {
        try {
            reactivatePostponedMatch($pdo, $id, null);
            $n++;
        } catch (Throwable $e) {
            // ignore
        }
    }
    return $n;
}

/**
 * Diagnostic : pronos en attente, dont ceux dont le match est déjà passé.
 * @return array{pending:int,stuck:int,users:int}
 */
function countPendingPredictions(PDO $pdo): array
{
    $now  = matchSqlNow();
    $stmt = $pdo->query(
        "SELECT
             COUNT(*) AS pending,
             SUM(CASE WHEN m.date_match < {$now} THEN 1 ELSE 0 END) AS stuck,
             COUNT(DISTINCT p.user_id) AS users
         FROM predictions p
         INNER JOIN prediction_markets pm ON pm.id = p.market_id
         INNER JOIN matches m ON m.id = pm.match_id
         WHERE p.statut = 'en_attente'"
    );
    $row = $stmt->fetch() ?: [];

    return [
        'pending' => (int) ($row['pending'] ?? 0),
        'stuck'   => (int) ($row['stuck'] ?? 0),
        'users'   => (int) ($row['users'] ?? 0),
    ];
}

/**
 * Passe complète de résolution : résultats API → scoring → annulation des blocages.
 * @return array{resolved:int,scored:int,voided:int,skipped:bool,quota_blocked:bool}
 */
function resolveMatchResults(PDO $pdo, bool $force = false): array
{
    $out = ['resolved' => 0, 'scored' => 0, 'voided' => 0, 'skipped' => false, 'quota_blocked' => false];

    if (!is_dir(APP_CACHE_DIR)) {
        @mkdir(APP_CACHE_DIR, 0755, true);
    }

    $file = scoresSyncLastRunPath();
    if (!$force && is_file($file) && (time() - (int) @file_get_contents($file)) < scoresSyncIntervalSeconds()) {
        $out['skipped'] = true;
        $out['scored']  = scorePendingFinishedMatches($pdo);
        return $out;
    }

    // Quota mort : on ne brûle rien. Scoring local + annulation éventuelle seulement.
    if (scoresSportsBudget() <= 0) {
        $out['quota_blocked'] = true;
        $out['scored']        = scorePendingFinishedMatches($pdo);
        $out['voided']        = voidStalePredictions($pdo);
        return $out;
    }

    @file_put_contents($file, (string) time());

    // force = ignore le throttle ; cache fichier conservé sauf catch-up admin.
    $out['resolved'] = syncMatchScores($pdo, null, null, false);
    $out['scored']   = scorePendingFinishedMatches($pdo);
    $out['voided']   = voidStalePredictions($pdo);

    return $out;
}

/**
 * Rattrapage admin : interroge d’un coup toutes les ligues bloquées (fenêtre API ~3 j).
 * Bypass cache + ignore le throttle horaire. Coût ≈ 2 crédits × nb ligues.
 *
 * @return array{
 *   sports:list<string>,sports_queried:int,resolved:int,scored:int,voided:int,
 *   still_stuck:int,still_api:int,too_old:int,credits_est:int,
 *   quota_blocked:bool,quota_remaining:?int
 * }
 */
function catchUpMissingScoresFromApi(PDO $pdo, ?int $maxSports = null, bool $bypassCache = true): array
{
    $maxSports = $maxSports ?? (int) SCORES_ADMIN_CATCHUP_MAX_SPORTS;
    $maxSports = max(1, min(30, $maxSports));

    $out = [
        'sports'          => [],
        'sports_queried'  => 0,
        'resolved'        => 0,
        'scored'          => 0,
        'voided'          => 0,
        'still_stuck'     => 0,
        'still_api'       => 0,
        'too_old'         => 0,
        'credits_est'     => 0,
        'quota_blocked'   => false,
        'quota_remaining' => oddsQuotaRemaining(),
    ];

    if (!oddsApiConfigured()) {
        return $out;
    }

    $remaining = oddsQuotaRemaining();
    if ($remaining !== null && $remaining <= (int) ODDS_QUOTA_RESERVE_SCORES) {
        $out['quota_blocked'] = true;
        $out['scored'] = scorePendingFinishedMatches($pdo);
        $out['voided'] = voidStalePredictions($pdo);
        $summary = summarizeStuckScoresQueue($pdo);
        $out['still_stuck'] = $summary['total'];
        $out['still_api'] = $summary['api_window'];
        $out['too_old'] = $summary['too_old'];
        return $out;
    }

    if ($remaining !== null) {
        $affordable = (int) floor(max(0, $remaining - (int) ODDS_QUOTA_RESERVE_SCORES) / 2);
        $maxSports = min($maxSports, max(0, $affordable));
    }
    if ($maxSports <= 0) {
        $out['quota_blocked'] = true;
        return $out;
    }

    $sportKeys = sportsAwaitingResults($pdo, $maxSports);
    $out['sports'] = $sportKeys;
    $out['sports_queried'] = count($sportKeys);
    $out['credits_est'] = count($sportKeys) * 2;

    if ($sportKeys !== []) {
        if (ensureAppCacheDir()) {
            @file_put_contents(scoresSyncLastRunPath(), (string) time());
        }
        $out['resolved'] = syncMatchScores(
            $pdo,
            (int) SCORES_CATCHUP_DAYS,
            count($sportKeys),
            $bypassCache
        );
    }

    $out['scored'] = scorePendingFinishedMatches($pdo);
    $out['voided'] = voidStalePredictions($pdo);
    $out['quota_remaining'] = oddsQuotaRemaining();

    $summary = summarizeStuckScoresQueue($pdo);
    $out['still_stuck'] = $summary['total'];
    $out['still_api'] = $summary['api_window'];
    $out['too_old'] = $summary['too_old'];

    return $out;
}

function scoresSyncLastRunPath(): string
{
    return APP_CACHE_DIR . '/last_scores_sync.txt';
}

/** Rattrapage des points pour matchs terminés (0 crédit si scores déjà en base). */
function scorePendingFinishedMatches(PDO $pdo): int
{
    $stmt = $pdo->query(
        'SELECT DISTINCT m.id
         FROM matches m
         INNER JOIN prediction_markets pm ON pm.match_id = m.id
         INNER JOIN predictions p ON p.market_id = pm.id
         WHERE m.statut = "termine"
           AND p.statut = "en_attente"
           AND (
                (m.score_home IS NOT NULL AND m.score_away IS NOT NULL)
                OR (m.resultat_1x2 IS NOT NULL AND m.resultat_1x2 <> "")
           )'
    );
    $n = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $matchId) {
        scoreMatch($pdo, (int) $matchId);
        $n++;
    }
    return $n;
}

/**
 * Pronos par match pour l’admin (file d’attente / reportés).
 *
 * @param list<int> $matchIds
 * @param list<string>|null $statuts
 * @return array<int, list<array<string,mixed>>>
 */
function fetchAdminMatchPredictions(PDO $pdo, array $matchIds, ?array $statuts = null): array
{
    $matchIds = array_values(array_unique(array_filter(array_map('intval', $matchIds))));
    if ($matchIds === []) {
        return [];
    }
    $statuts = $statuts ?? ['en_attente', 'annule'];
    $statuts = array_values(array_filter($statuts, static fn ($s) => is_string($s) && $s !== ''));
    if ($statuts === []) {
        return [];
    }

    $phM = implode(',', array_fill(0, count($matchIds), '?'));
    $phS = implode(',', array_fill(0, count($statuts), '?'));
    $stmt = $pdo->prepare(
        "SELECT p.id, p.reponse, p.statut, p.user_id, u.pseudo,
                pm.match_id, pm.type AS market_type,
                m.equipe_home, m.equipe_away
         FROM predictions p
         INNER JOIN users u ON u.id = p.user_id
         INNER JOIN prediction_markets pm ON pm.id = p.market_id
         INNER JOIN matches m ON m.id = pm.match_id
         WHERE pm.match_id IN ($phM)
           AND p.statut IN ($phS)
         ORDER BY u.pseudo ASC, FIELD(pm.type, '1x2', 'score_exact', 'buteur'), p.id ASC"
    );
    $stmt->execute([...$matchIds, ...$statuts]);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $mid = (int) $row['match_id'];
        $out[$mid][] = $row;
    }

    return $out;
}

/**
 * Matchs joués sans résultat API, avec au moins un prono en attente.
 * Pour la saisie manuelle admin (0 crédit).
 *
 * @return list<array<string,mixed>>
 */
function listStuckMatchesForManualScore(PDO $pdo, int $limit = 40): array
{
    $readyMin = (int) MATCH_RESULT_READY_MINUTES;
    $now      = matchSqlNow();
    // Afficher dès qu'un match est marqué terminé sans résultat, OU dès que le
    // délai « fin réelle » est passé (sinon l'admin voit 0 alors que des pronos attendent).
    $stmt     = $pdo->query(
        "SELECT m.id, m.sport, m.competition, m.equipe_home, m.equipe_away, m.date_match,
                m.statut, m.resultat_1x2, m.score_home, m.score_away,
                COUNT(p.id) AS pending_count
         FROM matches m
         INNER JOIN prediction_markets pm ON pm.match_id = m.id
         INNER JOIN predictions p ON p.market_id = pm.id AND p.statut = 'en_attente'
         WHERE m.statut NOT IN ('annule', 'reporte')
           AND (m.resultat_1x2 IS NULL OR m.resultat_1x2 = '')
           AND (
                m.statut = 'termine'
                OR m.date_match < DATE_SUB({$now}, INTERVAL {$readyMin} MINUTE)
           )
         GROUP BY m.id
         ORDER BY m.date_match ASC
         LIMIT " . max(1, $limit)
    );

    return $stmt->fetchAll();
}

/**
 * Matchs dont les pronos ont été passés « annulé (données indisponibles) »
 * et qui n’ont toujours pas de score — à corriger à la main.
 *
 * @return list<array<string,mixed>>
 */
function listVoidedMatchesForManualScore(PDO $pdo, int $limit = 40): array
{
    ensurePredictionHistorySchema($pdo);
    $stmt = $pdo->query(
        "SELECT m.id, m.sport, m.competition, m.equipe_home, m.equipe_away, m.date_match,
                m.statut, m.resultat_1x2, m.score_home, m.score_away,
                COUNT(p.id) AS voided_count,
                COUNT(DISTINCT p.user_id) AS user_count
         FROM matches m
         INNER JOIN prediction_markets pm ON pm.match_id = m.id
         INNER JOIN predictions p ON p.market_id = pm.id AND p.statut = 'annule'
         WHERE (m.resultat_1x2 IS NULL OR m.resultat_1x2 = '')
           AND m.statut NOT IN ('annule', 'reporte')
         GROUP BY m.id
         ORDER BY m.date_match DESC
         LIMIT " . max(1, $limit)
    );

    return $stmt->fetchAll();
}

/**
 * Recherche de matchs pour saisie manuelle (équipes + sport).
 *
 * @param ''|'soccer'|'basketball'|'tennis' $sportCategory
 * @return list<array<string,mixed>>
 */
function searchMatchesForManualScore(
    PDO $pdo,
    string $homeQuery,
    string $awayQuery,
    int $limit = 15,
    string $sportCategory = ''
): array {
    $homeQuery = trim($homeQuery);
    $awayQuery = trim($awayQuery);
    $sportCategory = trim($sportCategory);
    if ($homeQuery === '' && $awayQuery === '' && $sportCategory === '') {
        return [];
    }

    $clauses = [];
    $params  = [];

    if ($sportCategory === 'soccer') {
        $clauses[] = "m.sport LIKE 'soccer_%'";
    } elseif ($sportCategory === 'basketball') {
        $clauses[] = "m.sport LIKE 'basketball_%'";
    } elseif ($sportCategory === 'tennis') {
        $clauses[] = "m.sport LIKE 'tennis_%'";
    }

    if ($homeQuery !== '' && $awayQuery !== '') {
        $clauses[] = 'm.equipe_home LIKE ? AND m.equipe_away LIKE ?';
        $params[]  = '%' . $homeQuery . '%';
        $params[]  = '%' . $awayQuery . '%';
    } elseif ($homeQuery !== '') {
        $clauses[] = '(m.equipe_home LIKE ? OR m.equipe_away LIKE ?)';
        $params[]  = '%' . $homeQuery . '%';
        $params[]  = '%' . $homeQuery . '%';
    } elseif ($awayQuery !== '') {
        $clauses[] = '(m.equipe_home LIKE ? OR m.equipe_away LIKE ?)';
        $params[]  = '%' . $awayQuery . '%';
        $params[]  = '%' . $awayQuery . '%';
    }

    if ($clauses === []) {
        return [];
    }

    $sql = 'SELECT m.id, m.sport, m.competition, m.equipe_home, m.equipe_away, m.date_match,
                   m.statut, m.resultat_1x2, m.score_home, m.score_away,
                   SUM(CASE WHEN p.statut = \'en_attente\' THEN 1 ELSE 0 END) AS pending_count,
                   SUM(CASE WHEN p.statut = \'annule\' THEN 1 ELSE 0 END) AS voided_count
            FROM matches m
            LEFT JOIN prediction_markets pm ON pm.match_id = m.id
            LEFT JOIN predictions p ON p.market_id = pm.id
            WHERE ' . implode(' AND ', $clauses) . '
            GROUP BY m.id
            ORDER BY m.date_match DESC
            LIMIT ' . max(1, $limit);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Matchs déjà avec score en BDD mais pronos encore « en_attente »
 * (rattrapage points locaux, 0 crédit API).
 *
 * @return list<array<string,mixed>>
 */
function listMatchesAwaitingLocalScore(PDO $pdo, int $limit = 40): array
{
    $stmt = $pdo->query(
        'SELECT m.id, m.sport, m.competition, m.equipe_home, m.equipe_away, m.date_match,
                m.statut, m.resultat_1x2, m.score_home, m.score_away,
                COUNT(p.id) AS pending_count
         FROM matches m
         INNER JOIN prediction_markets pm ON pm.match_id = m.id
         INNER JOIN predictions p ON p.market_id = pm.id AND p.statut = \'en_attente\'
         WHERE m.resultat_1x2 IS NOT NULL AND m.resultat_1x2 <> \'\'
           AND m.statut NOT IN (\'annule\', \'reporte\')
         GROUP BY m.id
         ORDER BY m.date_match ASC
         LIMIT ' . max(1, $limit)
    );

    return $stmt->fetchAll();
}

/**
 * Enregistre un score saisi à la main (admin) et attribue les points.
 * Même pipeline que l'API : resultat_1x2 déduit du score, puis scoreMatch().
 *
 * @param '1'|'2'|null $pensWinner Vainqueur aux tirs au but (écrase le 1x2 si score nul)
 * @return array{rescored:int,scored:int}
 */
function applyManualMatchScore(
    PDO $pdo,
    int $matchId,
    int $scoreHome,
    int $scoreAway,
    ?string $pensWinner = null
): array {
    if ($scoreHome < 0 || $scoreAway < 0 || $scoreHome > 300 || $scoreAway > 300) {
        throw new InvalidArgumentException('Score invalide (0–300).');
    }
    if ($pensWinner !== null && $pensWinner !== '1' && $pensWinner !== '2') {
        throw new InvalidArgumentException('Vainqueur aux tirs au but invalide (domicile ou extérieur).');
    }

    ensurePredictionHistorySchema($pdo);

    $stmt = $pdo->prepare('SELECT * FROM matches WHERE id = ?');
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    if (!$match) {
        throw new InvalidArgumentException('Match introuvable.');
    }

    $hadResult = $match['resultat_1x2'] !== null && $match['resultat_1x2'] !== '';
    $hasPending = false;
    $hasVoided  = false;
    $chk = $pdo->prepare(
        'SELECT
            SUM(CASE WHEN p.statut = \'en_attente\' THEN 1 ELSE 0 END) AS pending_n,
            SUM(CASE WHEN p.statut = \'annule\' THEN 1 ELSE 0 END) AS voided_n
         FROM predictions p
         INNER JOIN prediction_markets pm ON pm.id = p.market_id
         WHERE pm.match_id = ?'
    );
    $chk->execute([$matchId]);
    $counts = $chk->fetch() ?: [];
    $hasPending = (int) ($counts['pending_n'] ?? 0) > 0;
    $hasVoided  = (int) ($counts['voided_n'] ?? 0) > 0;

    // Déjà scoré sans pronos à rattraper → refus (évite double attribution).
    if ($hadResult && !$hasPending && !$hasVoided) {
        throw new InvalidArgumentException('Ce match a déjà un résultat et aucun prono à corriger.');
    }
    // Score déjà connu mais seulement des pronos en attente → rattrapage points locaux.
    if ($hadResult && $hasPending && !$hasVoided) {
        scoreMatch($pdo, $matchId);
        return ['rescored' => 0, 'scored' => (int) ($counts['pending_n'] ?? 0)];
    }

    $result1x2 = result1x2FromScores($scoreHome, $scoreAway, matchHasDraw($match['sport']));
    if ($pensWinner !== null) {
        // TAB : le score 90/120 min peut être nul, le 1x2 suit le vainqueur des tirs.
        $result1x2 = $pensWinner;
    }
    if ($result1x2 === null) {
        throw new InvalidArgumentException(
            'Score nul impossible pour ce sport (tennis / basket : un vainqueur est obligatoire).'
            . ' Cochez « Tirs au but » avec un vainqueur, ou utilisez « Match vraiment annulé ».'
        );
    }

    // Réouvre les pronos « données indisponibles » pour les re-noter.
    $rescored = 0;
    if ($hasVoided) {
        $reopen = $pdo->prepare(
            'UPDATE predictions p
             INNER JOIN prediction_markets pm ON pm.id = p.market_id
             SET p.statut = \'en_attente\', p.points_gagnes = 0, p.resolved_at = NULL, p.result_notified = 0
             WHERE pm.match_id = ? AND p.statut = \'annule\''
        );
        $reopen->execute([$matchId]);
        $rescored = $reopen->rowCount();
    }

    $upd = $pdo->prepare(
        'UPDATE matches SET statut = "termine", resultat_1x2 = ?, score_home = ?, score_away = ?
         WHERE id = ?'
    );
    $upd->execute([$result1x2, $scoreHome, $scoreAway, $matchId]);

    scoreMatch($pdo, $matchId);

    return ['rescored' => $rescored, 'scored' => $rescored + (int) ($counts['pending_n'] ?? 0)];
}

/**
 * Annule un score manuel (ou API) : retire les points déjà donnés, remet les pronos
 * en attente, efface resultat/score. Sert à corriger un mauvais match scoré.
 *
 * @return array{reopened:int,points_reversed:int}
 */
function clearManualMatchScore(PDO $pdo, int $matchId): array
{
    ensurePredictionHistorySchema($pdo);
    ensureMatchStatutSchema($pdo);
    ensureMatchCancelReasonSchema($pdo);

    $stmt = $pdo->prepare('SELECT * FROM matches WHERE id = ?');
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    if (!$match) {
        throw new InvalidArgumentException('Match introuvable.');
    }
    if ($match['resultat_1x2'] === null || $match['resultat_1x2'] === '') {
        throw new InvalidArgumentException('Ce match n’a pas de score à effacer.');
    }

    $predStmt = $pdo->prepare(
        'SELECT p.id, p.user_id, p.statut, p.points_gagnes, pm.type AS market_type
         FROM predictions p
         INNER JOIN prediction_markets pm ON pm.id = p.market_id
         WHERE pm.match_id = ?
           AND p.statut IN (\'correct\', \'incorrect\')'
    );
    $predStmt->execute([$matchId]);
    $preds = $predStmt->fetchAll() ?: [];

    $pointsReversed = 0;
    foreach ($preds as $pred) {
        $pts = (int) ($pred['points_gagnes'] ?? 0);
        $uid = (int) ($pred['user_id'] ?? 0);
        if ($uid <= 0) {
            continue;
        }
        if ($pts > 0) {
            $pdo->prepare(
                'UPDATE users SET points_totaux = GREATEST(0, points_totaux - ?) WHERE id = ?'
            )->execute([$pts, $uid]);
            addSeasonPoints($pdo, $uid, -$pts);
            $pointsReversed += $pts;
        }
        // Série 1x2 : on décrémente si c’était un bon prono ; l’inverse (mauvais prono)
        // ne peut pas restaurer l’ancienne série — approximation acceptable.
        if (($pred['market_type'] ?? '') === '1x2' && ($pred['statut'] ?? '') === 'correct') {
            $pdo->prepare(
                'UPDATE users SET serie_en_cours = GREATEST(0, serie_en_cours - 1) WHERE id = ?'
            )->execute([$uid]);
        }
    }

    $reopen = $pdo->prepare(
        'UPDATE predictions p
         INNER JOIN prediction_markets pm ON pm.id = p.market_id
         SET p.statut = \'en_attente\', p.points_gagnes = 0, p.resolved_at = NULL, p.result_notified = 0
         WHERE pm.match_id = ? AND p.statut IN (\'correct\', \'incorrect\')'
    );
    $reopen->execute([$matchId]);
    $reopened = $reopen->rowCount();

    $pdo->prepare(
        'UPDATE matches
         SET statut = \'a_venir\', resultat_1x2 = NULL, score_home = NULL, score_away = NULL,
             annulation_raison = NULL
         WHERE id = ?'
    )->execute([$matchId]);

    return ['reopened' => $reopened, 'points_reversed' => $pointsReversed];
}

/**
 * Raisons d’annulation proposées en admin (code => libellé FR).
 *
 * @return array<string,string>
 */
function matchCancelReasonOptions(): array
{
    return [
        'api_doublon'   => 'Doublon / mauvais adversaire (API)',
        'non_joue'      => 'Match non joué / abandonné',
        'forfait'       => 'Forfait',
        'erreur_saisie' => 'Erreur de saisie admin',
        'autre'         => 'Autre',
    ];
}

function normalizeMatchCancelReason(?string $code): ?string
{
    $code = trim((string) $code);
    if ($code === '') {
        return null;
    }
    $opts = matchCancelReasonOptions();
    if (!isset($opts[$code])) {
        throw new InvalidArgumentException('Choisis une raison d’annulation dans la liste.');
    }

    return $code;
}

/** Libellé joueur (i18n) pour une raison d’annulation. */
function matchCancelReasonLabel(?string $code): string
{
    $code = trim((string) $code);
    if ($code === '') {
        return '';
    }
    $key = 'cancel.reason.' . $code;
    $label = t($key);
    if ($label === $key) {
        $opts = matchCancelReasonOptions();
        return $opts[$code] ?? t('cancel.reason.autre');
    }

    return $label;
}

/** Ligne résultat joueur pour un match annulé (+ raison si présente). */
function formatCancelledMatchResultLine(?string $reasonCode = null): string
{
    $reason = matchCancelReasonLabel($reasonCode);
    if ($reason === '') {
        return t('dash.match_cancelled');
    }

    return t('dash.match_cancelled_reason', ['reason' => $reason]);
}

function ensureMatchCancelReasonSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $col = $pdo->query('SHOW COLUMNS FROM matches LIKE "annulation_raison"')->fetch();
        if (!$col) {
            $pdo->exec(
                'ALTER TABLE matches ADD COLUMN annulation_raison VARCHAR(64) NULL DEFAULT NULL
                 AFTER statut'
            );
        }
    } catch (PDOException $e) {
        // Migration manuelle si droits limités
    }
}

/**
 * Match annulé sans vainqueur : 0 pt pour tous les pronos en attente.
 * Visible côté joueur comme « Match annulé » (+ raison éventuelle).
 * Si un score avait déjà été saisi, il est d’abord effacé (points retirés).
 *
 * @return int Nombre de pronos annulés / rouverts puis annulés
 */
function cancelMatch(PDO $pdo, int $matchId, ?string $reasonCode = null): int
{
    $reason = null;
    if ($reasonCode !== null && trim($reasonCode) !== '') {
        $reason = normalizeMatchCancelReason($reasonCode);
    }

    ensureMatchCancelReasonSchema($pdo);
    $stmt = $pdo->prepare('SELECT id, resultat_1x2 FROM matches WHERE id = ?');
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    if (!$match) {
        throw new InvalidArgumentException('Match introuvable.');
    }
    if ($match['resultat_1x2'] !== null && $match['resultat_1x2'] !== '') {
        clearManualMatchScore($pdo, $matchId);
    }

    return finalizeMatchWithoutScore($pdo, $matchId, 'annule', $reason);
}

/**
 * Match reporté : 0 pt, visible côté joueur comme « Match reporté ».
 * Reste listé en admin jusqu’à score ou réactivation.
 *
 * @param string|null $newDateUtc Nouvelle date kick-off (UTC Y-m-d H:i:s), optionnelle
 * @return int Nombre de pronos annulés
 */
function postponeMatch(PDO $pdo, int $matchId, ?string $newDateUtc = null): int
{
    $n = finalizeMatchWithoutScore($pdo, $matchId, 'reporte');
    if ($newDateUtc !== null && $newDateUtc !== '') {
        rescheduleMatchDate($pdo, $matchId, $newDateUtc);
    }

    return $n;
}

/**
 * Clôture un match sans score (annulé ou reporté) et void les pronos en attente.
 *
 * @param 'annule'|'reporte' $matchStatut
 * @param string|null $cancelReason Code raison (uniquement si annule)
 */
function finalizeMatchWithoutScore(
    PDO $pdo,
    int $matchId,
    string $matchStatut,
    ?string $cancelReason = null
): int {
    ensurePredictionHistorySchema($pdo);
    ensureMatchStatutSchema($pdo);
    ensureMatchCancelReasonSchema($pdo);

    if (!in_array($matchStatut, ['annule', 'reporte'], true)) {
        throw new InvalidArgumentException('Statut de clôture invalide.');
    }

    $stmt = $pdo->prepare('SELECT id, statut, resultat_1x2 FROM matches WHERE id = ?');
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    if (!$match) {
        throw new InvalidArgumentException('Match introuvable.');
    }
    if ($match['resultat_1x2'] !== null && $match['resultat_1x2'] !== '') {
        throw new InvalidArgumentException(
            $matchStatut === 'reporte'
                ? 'Ce match a déjà un score — impossible de le marquer reporté.'
                : 'Ce match a déjà un score — impossible d’annuler.'
        );
    }

    $reason = $matchStatut === 'annule' ? $cancelReason : null;
    $pdo->prepare(
        'UPDATE matches
         SET statut = ?, score_home = NULL, score_away = NULL, annulation_raison = ?
         WHERE id = ? AND (resultat_1x2 IS NULL OR resultat_1x2 = "")'
    )->execute([$matchStatut, $reason, $matchId]);

    $stmt = $pdo->prepare(
        'UPDATE predictions p
         INNER JOIN prediction_markets pm ON pm.id = p.market_id
         SET p.statut = "annule", p.points_gagnes = 0, p.resolved_at = UTC_TIMESTAMP()
         WHERE pm.match_id = ? AND p.statut = "en_attente"'
    );
    $stmt->execute([$matchId]);

    return $stmt->rowCount();
}

/** Datetime saisie admin (fuseau app) → UTC stocké en BDD. */
function parseAdminMatchDatetime(string $raw): string
{
    $raw = trim(str_replace('T', ' ', $raw));
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw)) {
        $raw .= ':00';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $raw)) {
        throw new InvalidArgumentException('Date invalide (format JJ/… via le sélecteur).');
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, appTimezone());
    $errors = DateTimeImmutable::getLastErrors();
    if (
        !$dt
        || (($errors['warning_count'] ?? 0) > 0)
        || (($errors['error_count'] ?? 0) > 0)
    ) {
        throw new InvalidArgumentException('Date invalide.');
    }

    return $dt->setTimezone(utcStorageTimezone())->format('Y-m-d H:i:s');
}

/** Valeur pour <input type="datetime-local"> depuis une date UTC BDD. */
function matchDatetimeLocalValue(string $utcDatetime): string
{
    $dt = parseUtcDatetime($utcDatetime);
    if (!$dt) {
        return '';
    }

    return $dt->setTimezone(appTimezone())->format('Y-m-d\TH:i');
}

function rescheduleMatchDate(PDO $pdo, int $matchId, string $dateUtc): void
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dateUtc)) {
        throw new InvalidArgumentException('Date invalide.');
    }
    $pdo->prepare('UPDATE matches SET date_match = ? WHERE id = ?')
        ->execute([$dateUtc, $matchId]);
    syncMatchMarketCloseTimes($pdo, $matchId, $dateUtc);
}

/** Change la date d’un match encore marqué reporté. */
function updatePostponedMatchDate(PDO $pdo, int $matchId, string $dateUtc): void
{
    ensureMatchStatutSchema($pdo);
    $stmt = $pdo->prepare('SELECT id, statut FROM matches WHERE id = ?');
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    if (!$match) {
        throw new InvalidArgumentException('Match introuvable.');
    }
    if (($match['statut'] ?? '') !== 'reporte') {
        throw new InvalidArgumentException('Ce match n’est pas reporté.');
    }
    rescheduleMatchDate($pdo, $matchId, $dateUtc);
}

/**
 * Remet un match reporté en « à venir » (pronos rouverts) pour le flux normal / API.
 *
 * @return int Pronos réouverts
 */
function reactivatePostponedMatch(PDO $pdo, int $matchId, ?string $newDateUtc = null): int
{
    ensureMatchStatutSchema($pdo);
    ensurePredictionHistorySchema($pdo);

    $stmt = $pdo->prepare('SELECT id, statut, date_match FROM matches WHERE id = ?');
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    if (!$match) {
        throw new InvalidArgumentException('Match introuvable.');
    }
    if (($match['statut'] ?? '') !== 'reporte') {
        throw new InvalidArgumentException('Ce match n’est pas reporté.');
    }

    $dateUtc = $newDateUtc ?: (string) $match['date_match'];
    $pdo->prepare(
        'UPDATE matches
         SET statut = "a_venir", date_match = ?, score_home = NULL, score_away = NULL,
             resultat_1x2 = NULL, annulation_raison = NULL
         WHERE id = ?'
    )->execute([$dateUtc, $matchId]);
    syncMatchMarketCloseTimes($pdo, $matchId, $dateUtc);

    $reopen = $pdo->prepare(
        'UPDATE predictions p
         INNER JOIN prediction_markets pm ON pm.id = p.market_id
         SET p.statut = "en_attente", p.points_gagnes = 0, p.resolved_at = NULL, p.result_notified = 0
         WHERE pm.match_id = ? AND p.statut = "annule"'
    );
    $reopen->execute([$matchId]);

    return $reopen->rowCount();
}

/**
 * Matchs reportés (toujours visibles en admin jusqu’à score / réactivation).
 *
 * @return list<array<string,mixed>>
 */
function listPostponedMatchesForAdmin(PDO $pdo, int $limit = 80): array
{
    ensureMatchStatutSchema($pdo);
    ensurePredictionHistorySchema($pdo);
    $stmt = $pdo->query(
        "SELECT m.id, m.sport, m.competition, m.equipe_home, m.equipe_away, m.date_match,
                m.statut, m.resultat_1x2, m.score_home, m.score_away,
                COUNT(p.id) AS voided_count,
                COUNT(DISTINCT p.user_id) AS user_count
         FROM matches m
         LEFT JOIN prediction_markets pm ON pm.match_id = m.id
         LEFT JOIN predictions p ON p.market_id = pm.id AND p.statut = 'annule'
         WHERE m.statut = 'reporte'
         GROUP BY m.id
         ORDER BY m.date_match ASC
         LIMIT " . max(1, $limit)
    );

    return $stmt->fetchAll();
}

/** Ajoute le statut « reporte » à l’enum matches.statut si besoin. */
function ensureMatchStatutSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $col = $pdo->query('SHOW COLUMNS FROM matches LIKE "statut"')->fetch();
        $type = (string) ($col['Type'] ?? '');
        if ($type !== '' && stripos($type, 'reporte') === false) {
            $pdo->exec(
                "ALTER TABLE matches
                 MODIFY statut ENUM('a_venir', 'en_cours', 'termine', 'annule', 'reporte')
                 NOT NULL DEFAULT 'a_venir'"
            );
        }
    } catch (PDOException $e) {
        // Migration manuelle si droits limités
    }
}

function maybeSyncMatchScores(PDO $pdo): bool
{
    $result = resolveMatchResults($pdo, false);

    return !$result['skipped'];
}

function pendingScoreLastRunPath(): string
{
    return APP_CACHE_DIR . '/last_pending_score.txt';
}

/** Rattrapage points sur page web : throttlé, 0 appel API (scores déjà en base). */
function maybeScorePendingFinishedMatches(PDO $pdo): int
{
    if (!is_dir(APP_CACHE_DIR)) {
        @mkdir(APP_CACHE_DIR, 0755, true);
    }

    $file = pendingScoreLastRunPath();
    if (is_file($file) && (time() - (int) @file_get_contents($file)) < PENDING_SCORE_INTERVAL_SECONDS) {
        return 0;
    }
    @file_put_contents($file, (string) time());

    return scorePendingFinishedMatches($pdo);
}

/** Ordre : rotation cache locale → fermeture affichage (pas d'API lourde sur les pages web). */
function maintainMatchLifecycle(PDO $pdo, bool $webRequest = false): array
{
    $cache = false;
    $scores = false;
    $pruned = null;

    if ($webRequest) {
        maybeScorePendingFinishedMatches($pdo);
        $closed = closeExpiredMatches($pdo);
    } else {
        $scores = maybeSyncMatchScores($pdo);
        $cache = maybeRefreshMatchesFromCache($pdo);
        $closed = closeExpiredMatches($pdo);
        scorePendingFinishedMatches($pdo);
        $pruned = maybePruneStaleMatchData($pdo);
    }

    return ['scores' => $scores, 'cache' => $cache, 'closed' => $closed, 'pruned' => $pruned];
}

function pruneLastRunPath(): string
{
    return APP_CACHE_DIR . '/last_db_prune.txt';
}

/** Purge BDD au plus 1× / 24 h (cron). */
function maybePruneStaleMatchData(PDO $pdo): ?array
{
    if (!ensureAppCacheDir()) {
        return null;
    }
    $file = pruneLastRunPath();
    if (is_file($file) && (time() - (int) @file_get_contents($file)) < 86400) {
        return null;
    }
    $result = pruneStaleMatchData($pdo);
    @file_put_contents($file, (string) time());
    return $result;
}

function matchDisplayPriority(array $m): int
{
    $score = 0;
    $sport = (string) ($m['sport'] ?? '');
    if (!empty($m['prob_1']) && !empty($m['prob_2'])) {
        $score += 20;
    }
    if (isSoccerSport($sport) && !empty($m['prob_n'])) {
        $score += 5;
    }
    if (soccerSportHasScorerOdds($sport)) {
        $score += 4;
    }
    if (sportOddsAvailable($sport) === false) {
        $score -= 15;
    }
    // Basket masculin prioritaire à l’affichage (NBA / Euroleague / NCAAB…).
    if (isMensBasketballSport($sport)) {
        $score += 12;
    } elseif (strncmp($sport, 'basketball_', 11) === 0) {
        $score += 2;
    }

    return $score;
}

function sortMatchesForDisplay(array &$matches): void
{
    usort($matches, static function ($a, $b) {
        $dateCmp = strcmp($a['date_match'] ?? '', $b['date_match'] ?? '');
        if ($dateCmp !== 0) {
            return $dateCmp;
        }
        return matchDisplayPriority($b) - matchDisplayPriority($a);
    });
}

function getUpcomingMatchesByCategory(PDO $pdo, ?int $perCategory = null): array
{
    $perCategory = $perCategory ?? (int) MATCHS_PAR_CATEGORIE;
    $horizon     = (int) MATCHS_HORIZON_JOURS;
    $closeMins   = (int) MATCH_CLOSE_AFTER_MINUTES;
    $now         = matchSqlNow();
    // Marge : tri priorité après coup, sans se faire manger par le foot dans un LIMIT global.
    $fetchLimit  = max($perCategory * 4, 40);

    $sportLike = [
        'tennis'     => "m.sport LIKE 'tennis_%'",
        'basketball' => "m.sport LIKE 'basketball_%'",
        'soccer'     => "m.sport LIKE 'soccer_%'",
    ];

    $byCategory = [];
    foreach (sportCategories() as $cat) {
        $like = $sportLike[$cat] ?? null;
        if ($like === null) {
            $byCategory[$cat] = [];
            continue;
        }
        $stmt = $pdo->query(
            "SELECT m.*
             FROM matches m
             WHERE m.statut = 'a_venir'
               AND m.external_id IS NOT NULL
               AND {$like}
               AND m.date_match > DATE_SUB({$now}, INTERVAL {$closeMins} MINUTE)
               AND m.date_match <= DATE_ADD({$now}, INTERVAL {$horizon} DAY)
             ORDER BY m.date_match ASC
             LIMIT {$fetchLimit}"
        );
        $bucket = $stmt->fetchAll();
        sortMatchesForDisplay($bucket);
        if ($cat === 'basketball') {
            $men = [];
            $rest = [];
            foreach ($bucket as $m) {
                if (isMensBasketballSport((string) ($m['sport'] ?? ''))) {
                    $men[] = $m;
                } else {
                    $rest[] = $m;
                }
            }
            $bucket = array_merge($men, $rest);
        }
        $byCategory[$cat] = array_slice($bucket, 0, $perCategory);
    }

    return $byCategory;
}

/** Compte les matchs à venir en BDD par catégorie (diagnostic sync). */
function countDbUpcomingByCategory(PDO $pdo): array
{
    $horizon   = (int) MATCHS_HORIZON_JOURS;
    $closeMins = (int) MATCH_CLOSE_AFTER_MINUTES;
    $now = matchSqlNow();
    $stmt = $pdo->query(
        "SELECT sport FROM matches
         WHERE statut = 'a_venir'
           AND external_id IS NOT NULL
           AND date_match > DATE_SUB({$now}, INTERVAL {$closeMins} MINUTE)
           AND date_match <= DATE_ADD({$now}, INTERVAL {$horizon} DAY)"
    );
    $counts = ['soccer' => 0, 'basketball' => 0, 'tennis' => 0, 'other' => 0];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $sportKey) {
        $cat = sportCategory((string) $sportKey);
        if (isset($counts[$cat])) {
            $counts[$cat]++;
        }
    }

    return $counts;
}

function getUpcomingMatches(PDO $pdo, ?int $limit = null): array
{
    unset($limit);
    $byCategory = getUpcomingMatchesByCategory($pdo);
    $picked     = [];
    foreach (sportCategories() as $cat) {
        foreach ($byCategory[$cat] as $m) {
            $picked[] = $m;
        }
    }

    usort($picked, function ($a, $b) {
        return strcmp($a['date_match'], $b['date_match']);
    });

    return $picked;
}

function getMarketsForMatches(PDO $pdo, array $matchIds): array
{
    if (empty($matchIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($matchIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, match_id, type, points_si_correct, ferme_le
         FROM prediction_markets
         WHERE match_id IN ($placeholders)
         ORDER BY match_id, FIELD(type, '1x2', 'score_exact', 'buteur')"
    );
    $stmt->execute($matchIds);
    $rows = $stmt->fetchAll();

    $marketIds = array_column($rows, 'id');
    $optionsByMarket = [];
    if (!empty($marketIds)) {
        $ph = implode(',', array_fill(0, count($marketIds), '?'));
        $opt = $pdo->prepare(
            "SELECT market_id, libelle FROM market_options WHERE market_id IN ($ph) ORDER BY libelle ASC"
        );
        $opt->execute($marketIds);
        foreach ($opt->fetchAll() as $o) {
            $optionsByMarket[(int) $o['market_id']][] = [
                'libelle' => $o['libelle'],
            ];
        }
    }

    $byMatch = [];
    foreach ($rows as $m) {
        $mid = (int) $m['match_id'];
        if (($m['type'] ?? '') === 'score_exact') {
            // Saisie libre côté UI — plus de liste d’options à hydrater.
            $m['options'] = [];
        } else {
            $m['options'] = $optionsByMarket[(int) $m['id']] ?? [];
        }
        $byMatch[$mid][] = $m;
    }
    return $byMatch;
}

/**
 * Début du mois calendaire courant (fuseau app), en UTC pour comparer à date_match.
 */
function matchPurgeMonthCutoffUtc(): string
{
    $startLocal = (new DateTimeImmutable('now', appTimezone()))
        ->modify('first day of this month')
        ->setTime(0, 0, 0);

    return $startLocal->setTimezone(utcStorageTimezone())->format('Y-m-d H:i:s');
}

/**
 * SQL : match encore « en erreur » à garder (données indispo, score manquant, prono en attente).
 * Les matchs vraiment annulés (statut=annule) ne sont PAS gardés — ils sont purgables.
 * Les matchs reportés restent visibles jusqu’à score / réactivation.
 * Alias internes distincts (évite erreur MySQL 1093 sur DELETE).
 */
function matchPurgeKeepErrorSql(string $alias = 'm'): string
{
    return "(
        {$alias}.statut IN ('a_venir', 'en_cours', 'reporte')
        OR EXISTS (
            SELECT 1 FROM prediction_markets pm_keep_pend
            INNER JOIN predictions p_keep_pend ON p_keep_pend.market_id = pm_keep_pend.id
            WHERE pm_keep_pend.match_id = {$alias}.id AND p_keep_pend.statut = 'en_attente'
        )
        OR (
            {$alias}.statut NOT IN ('annule', 'reporte')
            AND (
                {$alias}.resultat_1x2 IS NULL OR {$alias}.resultat_1x2 = ''
                OR EXISTS (
                    SELECT 1 FROM prediction_markets pm_keep_void
                    INNER JOIN predictions p_keep_void ON p_keep_void.market_id = pm_keep_void.id
                    WHERE pm_keep_void.match_id = {$alias}.id AND p_keep_void.statut = 'annule'
                )
            )
        )
    )";
}

/**
 * Aperçu de ce que pruneStaleMatchData() peut supprimer (sans écrire).
 *
 * @return array{
 *   score_options:int, buteur_options:int, empty_markets:int,
 *   old_matches:int, kept_errors:int, cutoff:string, cutoff_label:string
 * }
 */
function staleMatchDataStats(PDO $pdo): array
{
    $cutoff = matchPurgeMonthCutoffUtc();
    $keepSql = matchPurgeKeepErrorSql('m');
    $out = [
        'score_options'  => 0,
        'buteur_options' => 0,
        'empty_markets'  => 0,
        'old_matches'    => 0,
        'junk_finished'  => 0,
        'kept_errors'    => 0,
        'cutoff'         => $cutoff,
        'cutoff_label'   => '',
    ];

    try {
        $label = (new DateTimeImmutable($cutoff, utcStorageTimezone()))
            ->setTimezone(appTimezone())
            ->format('d/m/Y');
        $out['cutoff_label'] = $label;
    } catch (Throwable $e) {
        $out['cutoff_label'] = $cutoff;
    }

    $out['score_options'] = (int) $pdo->query(
        'SELECT COUNT(*) FROM market_options mo
         INNER JOIN prediction_markets pm ON pm.id = mo.market_id
         WHERE pm.type = "score_exact"'
    )->fetchColumn();

    $buteurDays = max(1, (int) BUTEUR_OPTIONS_RETENTION_DAYS);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM market_options mo
         INNER JOIN prediction_markets pm ON pm.id = mo.market_id
         INNER JOIN matches m ON m.id = pm.match_id
         WHERE pm.type = "buteur"
           AND m.statut IN ("termine", "annule")
           AND m.date_match < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)'
    );
    $stmt->execute([$buteurDays]);
    $out['buteur_options'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM matches m
         WHERE m.date_match < ?
           AND NOT {$keepSql}"
    );
    $stmt->execute([$cutoff]);
    $out['old_matches'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM matches m
         WHERE m.date_match < ?
           AND {$keepSql}"
    );
    $stmt->execute([$cutoff]);
    $out['kept_errors'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM prediction_markets pm
         INNER JOIN matches m ON m.id = pm.match_id
         WHERE m.date_match < ?
           AND NOT {$keepSql}
           AND NOT EXISTS (SELECT 1 FROM predictions p WHERE p.market_id = pm.id)"
    );
    $stmt->execute([$cutoff]);
    $out['empty_markets'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM matches m
         WHERE m.statut IN ("termine", "annule")
           AND m.date_match < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
           AND NOT EXISTS (
               SELECT 1 FROM prediction_markets pm
               INNER JOIN predictions p ON p.market_id = pm.id
               WHERE pm.match_id = m.id
           )'
    );
    $stmt->execute();
    $out['junk_finished'] = (int) $stmt->fetchColumn();

    return $out;
}

/**
 * Nettoyage BDD :
 * - options score_exact / buteurs périmés
 * - matchs des mois précédents (y compris avec pronos résolus)
 * - sauf matchs encore en erreur (données indispo, score manquant, en attente)
 *
 * @return array{
 *   score_options:int, buteur_options:int, empty_markets:int,
 *   old_matches:int, kept_errors:int, cutoff:string
 * }
 */
function pruneStaleMatchData(PDO $pdo): array
{
    $cutoff = matchPurgeMonthCutoffUtc();
    $keepSql = matchPurgeKeepErrorSql('m');
    $stats = staleMatchDataStats($pdo);

    $out = [
        'score_options'  => 0,
        'buteur_options' => 0,
        'empty_markets'  => 0,
        'old_matches'    => 0,
        'junk_finished'  => 0,
        'kept_errors'    => (int) $stats['kept_errors'],
        'cutoff'         => $cutoff,
    ];

    // 1) Options « score exact » (liste fixe en PHP).
    $out['score_options'] = (int) $pdo->exec(
        'DELETE mo FROM market_options mo
         INNER JOIN prediction_markets pm ON pm.id = mo.market_id
         WHERE pm.type = "score_exact"'
    );

    // 2) Options buteur des matchs terminés / annulés depuis un moment.
    $buteurDays = max(1, (int) BUTEUR_OPTIONS_RETENTION_DAYS);
    $stmt = $pdo->prepare(
        'DELETE mo FROM market_options mo
         INNER JOIN prediction_markets pm ON pm.id = mo.market_id
         INNER JOIN matches m ON m.id = pm.match_id
         WHERE pm.type = "buteur"
           AND m.statut IN ("termine", "annule")
           AND m.date_match < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)'
    );
    $stmt->execute([$buteurDays]);
    $out['buteur_options'] = $stmt->rowCount();

    // 3) Matchs des mois précédents, hors erreurs à corriger (CASCADE → markets / pronos).
    $stmt = $pdo->prepare(
        "DELETE m FROM matches m
         WHERE m.date_match < ?
           AND NOT {$keepSql}"
    );
    $stmt->execute([$cutoff]);
    $out['old_matches'] = $stmt->rowCount();

    // 4) Marchés vides restants (filet) — sous-requête pour éviter MySQL 1093.
    $stmt = $pdo->prepare(
        "DELETE FROM prediction_markets
         WHERE id IN (
             SELECT id FROM (
                 SELECT pm.id
                 FROM prediction_markets pm
                 INNER JOIN matches m ON m.id = pm.match_id
                 WHERE m.date_match < ?
                   AND NOT {$keepSql}
                   AND NOT EXISTS (
                       SELECT 1 FROM predictions p_empty
                       WHERE p_empty.market_id = pm.id
                   )
             ) AS doomed_markets
         )"
    );
    $stmt->execute([$cutoff]);
    $out['empty_markets'] = $stmt->rowCount();

    // 5) Matchs terminés / annulés sans aucun prono (déchets API) — dès 7 jours.
    $junkDays = 7;
    $stmt = $pdo->prepare(
        'DELETE m FROM matches m
         WHERE m.statut IN ("termine", "annule")
           AND m.date_match < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)
           AND NOT EXISTS (
               SELECT 1 FROM prediction_markets pm
               INNER JOIN predictions p ON p.market_id = pm.id
               WHERE pm.match_id = m.id
           )'
    );
    $stmt->execute([$junkDays]);
    $out['junk_finished'] = $stmt->rowCount();

    return $out;
}

function marketOptionLabel(array|string $option): string
{
    return is_array($option) ? (string) ($option['libelle'] ?? '') : (string) $option;
}

function getMatchMarkets(PDO $pdo, int $matchId): array
{
    $all = getMarketsForMatches($pdo, [$matchId]);
    return $all[$matchId] ?? [];
}

function getUserPredictions(PDO $pdo, ?int $userId, array $marketIds): array
{
    if (!$userId || empty($marketIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($marketIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT market_id, reponse, statut, points_gagnes FROM predictions
         WHERE user_id = ? AND market_id IN ($placeholders)"
    );
    $stmt->execute(array_merge([$userId], $marketIds));
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(int) $row['market_id']] = $row;
    }
    return $map;
}

function getUserTicket(PDO $pdo, int $userId): array
{
    $horizon = (int) MATCHS_HORIZON_JOURS;
    $now     = matchSqlNow();
    $stmt = $pdo->prepare(
        "SELECT p.reponse, p.market_id, pm.type AS market_type, pm.points_si_correct,
                m.equipe_home, m.equipe_away, m.competition, m.date_match, m.sport
         FROM predictions p
         INNER JOIN prediction_markets pm ON pm.id = p.market_id
         INNER JOIN matches m ON m.id = pm.match_id
         WHERE p.user_id = ?
           AND p.statut = 'en_attente'
           AND m.statut IN ('a_venir', 'en_cours')
           AND m.date_match <= DATE_ADD({$now}, INTERVAL {$horizon} DAY)
         ORDER BY m.date_match ASC, pm.type ASC"
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/** Pronos encore ouverts (ceux listés dans « Mes pronostics »). */
function countUserOpenTicketPredictions(PDO $pdo, int $userId): int
{
    $horizon = (int) MATCHS_HORIZON_JOURS;
    $now     = matchSqlNow();
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM predictions p
         INNER JOIN prediction_markets pm ON pm.id = p.market_id
         INNER JOIN matches m ON m.id = pm.match_id
         WHERE p.user_id = ?
           AND p.statut = 'en_attente'
           AND m.statut IN ('a_venir', 'en_cours')
           AND m.date_match <= DATE_ADD({$now}, INTERVAL {$horizon} DAY)"
    );
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

/**
 * Pronos validés mais match déjà joué / hors horizon : en attente de score / résolution.
 * (Souvent la différence entre « 19 en attente » et les 5 visibles sur le ticket.)
 */
function countUserAwaitingResultPredictions(PDO $pdo, int $userId): int
{
    $horizon = (int) MATCHS_HORIZON_JOURS;
    $now     = matchSqlNow();
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM predictions p
         INNER JOIN prediction_markets pm ON pm.id = p.market_id
         INNER JOIN matches m ON m.id = pm.match_id
         WHERE p.user_id = ?
           AND p.statut = 'en_attente'
           AND NOT (
                m.statut IN ('a_venir', 'en_cours')
                AND m.date_match <= DATE_ADD({$now}, INTERVAL {$horizon} DAY)
           )"
    );
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

function ticketItemToArray(array $row): array
{
    $type = $row['market_type'] ?? '1x2';
    return [
        'market_id'    => (int) $row['market_id'],
        'market_type'  => $type,
        'market_label' => marketTypeLabel($type),
        'points'       => (int) ($row['points_si_correct'] ?? marketPoints($type)),
        'reponse'      => $row['reponse'],
        'pick_label'   => formatPickLabel($row, $row['reponse']),
        'competition'  => $row['competition'],
        'home'         => $row['equipe_home'],
        'away'         => $row['equipe_away'],
        'date'         => formatMatchWhen($row['date_match']),
        'sport_icon'   => strncmp($row['sport'], 'tennis_', 7) === 0 ? 'tennis' : 'football',
    ];
}

function ticketResponse(PDO $pdo, int $userId): array
{
    $items = getUserTicket($pdo, $userId);
    $gain  = 0;
    foreach ($items as $r) {
        $gain += (int) ($r['points_si_correct'] ?? 0);
    }
    return [
        'ticket' => array_map('ticketItemToArray', $items),
        'gain'   => $gain,
    ];
}

function validatePredictionsBatch(PDO $pdo, int $userId, array $picks): array
{
    $saved  = 0;
    $skipped = 0;
    $errors = [];

    foreach ($picks as $p) {
        if (!is_array($p)) {
            continue;
        }
        $marketId = (int) ($p['market_id'] ?? 0);
        $reponse  = trim((string) ($p['reponse'] ?? ''));
        if ($marketId <= 0 || $reponse === '') {
            continue;
        }
        try {
            submitPrediction($pdo, $userId, $marketId, $reponse);
            $saved++;
        } catch (InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'déjà validé') || str_contains($e->getMessage(), 'déjà été traité')) {
                $skipped++;
                continue;
            }
            $errors[] = $e->getMessage();
        }
    }

    $result = ticketResponse($pdo, $userId);
    $result['saved']   = $saved;
    $result['skipped'] = $skipped;
    $result['errors']  = $errors;
    return $result;
}

function purgeMatchPredictions(PDO $pdo, int $matchId): void
{
    $pdo->prepare(
        'DELETE p FROM predictions p
         INNER JOIN prediction_markets pm ON pm.id = p.market_id
         WHERE pm.match_id = ?'
    )->execute([$matchId]);
}

function validateMarketOpen(array $market): void
{
    if ($market['statut'] !== 'a_venir') {
        throw new InvalidArgumentException('Les pronostics sont fermés pour ce match.');
    }
    if (utcDatetimeTimestamp($market['ferme_le']) <= time()) {
        throw new InvalidArgumentException('C\'est trop tard — le match a déjà commencé.');
    }
}

/** Annulation : tant que le match n'est pas terminé. */
function validateMarketCancelable(array $market): void
{
    if (!in_array($market['statut'], ['a_venir', 'en_cours'], true)) {
        throw new InvalidArgumentException('Impossible d\'annuler — match terminé.');
    }
}

function cancelPrediction(PDO $pdo, int $userId, int $marketId): array
{
    $stmt = $pdo->prepare(
        'SELECT pm.id, pm.ferme_le, pm.type, m.statut
         FROM prediction_markets pm
         INNER JOIN matches m ON m.id = pm.match_id
         WHERE pm.id = ?'
    );
    $stmt->execute([$marketId]);
    $market = $stmt->fetch();
    if (!$market) {
        throw new InvalidArgumentException('Ce pari n\'existe plus.');
    }

    validateMarketCancelable($market);

    $stmt = $pdo->prepare(
        'DELETE FROM predictions WHERE user_id = ? AND market_id = ? AND statut = "en_attente"'
    );
    $stmt->execute([$userId, $marketId]);

    return ticketResponse($pdo, $userId);
}

function submitPrediction(PDO $pdo, int $userId, int $marketId, string $reponse): array
{
    $stmt = $pdo->prepare(
        'SELECT pm.id, pm.ferme_le, pm.type, pm.points_si_correct, m.statut, m.sport,
                m.equipe_home, m.equipe_away, m.competition, m.date_match
         FROM prediction_markets pm
         INNER JOIN matches m ON m.id = pm.match_id
         WHERE pm.id = ?'
    );
    $stmt->execute([$marketId]);
    $market = $stmt->fetch();

    if (!$market) {
        throw new InvalidArgumentException('Ce match n\'existe plus.');
    }

    validateMarketOpen($market);

    $type = $market['type'];

    if ($type === '1x2') {
        $hasDraw = matchHasDraw($market['sport']);
        $allowed = $hasDraw ? ['1', 'N', '2'] : ['1', '2'];
        if (!in_array($reponse, $allowed, true)) {
            throw new InvalidArgumentException('Choix invalide.');
        }
    } elseif ($type === 'score_exact') {
        if (!isValidExactScorePick($reponse)) {
            throw new InvalidArgumentException('Score invalide.');
        }
    } elseif ($type === 'buteur') {
        $opt = $pdo->prepare('SELECT COUNT(*) FROM market_options WHERE market_id = ? AND libelle = ?');
        $opt->execute([$marketId, $reponse]);
        if ((int) $opt->fetchColumn() === 0) {
            throw new InvalidArgumentException('Buteur invalide pour ce match.');
        }
    }

    $stmt = $pdo->prepare('SELECT id, statut, reponse FROM predictions WHERE user_id = ? AND market_id = ?');
    $stmt->execute([$userId, $marketId]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['statut'] !== 'en_attente') {
            throw new InvalidArgumentException('Ce pronostic a déjà été traité.');
        }
        if ($existing['reponse'] === $reponse) {
            $resp = ticketResponse($pdo, $userId);
            $market['reponse'] = $reponse;
            $market['market_id'] = $marketId;
            $market['market_type'] = $type;
            $resp['item'] = ticketItemToArray($market);
            return $resp;
        }
        throw new InvalidArgumentException('Ce pronostic est déjà validé — modification impossible.');
    }

    $pdo->prepare(
        'INSERT INTO predictions (user_id, market_id, reponse) VALUES (?, ?, ?)'
    )->execute([$userId, $marketId, $reponse]);

    $resp = ticketResponse($pdo, $userId);
    $market['reponse'] = $reponse;
    $market['market_id'] = $marketId;
    $market['market_type'] = $type;
    $resp['item'] = ticketItemToArray($market);
    return $resp;
}
