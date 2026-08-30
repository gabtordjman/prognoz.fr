<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

require_once __DIR__ . '/seasons.php';

/** Comparaison tolérante de noms d'équipes (casse, accents, ponctuation, espaces). */
function normalizeTeamName(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '';
    }

    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    if (is_string($ascii) && $ascii !== '') {
        $name = $ascii;
    }

    return (string) preg_replace('/[^a-z0-9]+/', '', strtolower($name));
}

/**
 * Scores officiels de l'API, orientés selon NOTRE domicile / extérieur.
 *
 * L'appariement se fait par nom d'équipe : impossible d'inverser un score, même si
 * l'API renvoie les équipes dans l'autre sens. Retourne null si une des deux équipes
 * ne peut pas être appariée avec certitude — mieux vaut ne rien écrire qu'un faux score.
 *
 * @return array{home:int,away:int}|null
 */
function extractMatchScores(?array $scores, string $dbHomeTeam, string $dbAwayTeam): ?array
{
    if (empty($scores)) {
        return null;
    }

    $byTeam = [];
    foreach ($scores as $s) {
        if (!is_array($s) || !isset($s['score']) || !is_numeric($s['score'])) {
            continue;
        }
        $key = normalizeTeamName((string) ($s['name'] ?? ''));
        if ($key !== '') {
            $byTeam[$key] = (int) $s['score'];
        }
    }

    $home = normalizeTeamName($dbHomeTeam);
    $away = normalizeTeamName($dbAwayTeam);
    if ($home === '' || $away === '' || $home === $away) {
        return null;
    }
    if (!array_key_exists($home, $byTeam) || !array_key_exists($away, $byTeam)) {
        return null;
    }

    return ['home' => $byTeam[$home], 'away' => $byTeam[$away]];
}

/** Issue 1/N/2 déduite du score final — jamais calculée séparément de celui-ci. */
function result1x2FromScores(int $homeScore, int $awayScore, bool $hasDraw = true): ?string
{
    if ($homeScore > $awayScore) {
        return '1';
    }
    if ($homeScore < $awayScore) {
        return '2';
    }

    return $hasDraw ? 'N' : null;
}

function computeResult1x2(string $homeTeam, string $awayTeam, ?array $scores, bool $hasDraw = true): ?string
{
    $extracted = extractMatchScores($scores, $homeTeam, $awayTeam);
    if ($extracted === null) {
        return null;
    }

    return result1x2FromScores($extracted['home'], $extracted['away'], $hasDraw);
}

/** Multiplicateur de points pour une série 1x2 (après le gain courant). */
function streakPointsMultiplier(int $serieAfter): float
{
    if ($serieAfter < 2) {
        return 1.0;
    }
    $tiers = STREAK_POINT_MULTIPLIERS;
    krsort($tiers, SORT_NUMERIC);
    foreach ($tiers as $minSerie => $mult) {
        if ($serieAfter >= (int) $minSerie) {
            return (float) $mult;
        }
    }

    return 1.0;
}

function userCurrentSerie(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT serie_en_cours FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    return $row ? max(0, (int) $row['serie_en_cours']) : 0;
}

function applyPredictionResult(PDO $pdo, array $pred, bool $correct, int $points, bool $affectsSerie): void
{
    $statut = $correct ? 'correct' : 'incorrect';
    ensurePredictionHistorySchema($pdo);
    $pdo->prepare(
        'UPDATE predictions SET statut = ?, points_gagnes = ?, resolved_at = UTC_TIMESTAMP() WHERE id = ?'
    )->execute([$statut, $correct ? $points : 0, $pred['id']]);

    if ($correct) {
        $pdo->prepare(
            'UPDATE users SET points_totaux = points_totaux + ? WHERE id = ?'
        )->execute([$points, $pred['user_id']]);
        addSeasonPoints($pdo, (int) $pred['user_id'], $points);
        if ($affectsSerie) {
            $pdo->prepare(
                'UPDATE users SET serie_en_cours = serie_en_cours + 1 WHERE id = ?'
            )->execute([$pred['user_id']]);
        }
    } elseif ($affectsSerie) {
        $shield = false;
        if (function_exists('eventHasStreakShield')) {
            try {
                $shield = eventHasStreakShield($pdo);
            } catch (Throwable $e) {
                $shield = false;
            }
        }
        if (!$shield) {
            $pdo->prepare('UPDATE users SET serie_en_cours = 0 WHERE id = ?')->execute([$pred['user_id']]);
        }
    }
}

/** Annule les pronos en attente d'un marché (0 pt, hors stats win/loss). */
function voidPendingPredictions(PDO $pdo, int $marketId): void
{
    ensurePredictionHistorySchema($pdo);
    $pdo->prepare(
        'UPDATE predictions
         SET statut = "annule", points_gagnes = 0, resolved_at = UTC_TIMESTAMP()
         WHERE market_id = ? AND statut = "en_attente"'
    )->execute([$marketId]);
}

function scoreMarket(PDO $pdo, array $match, array $market): void
{
    $marketId = (int) $market['id'];
    $type     = $market['type'];
    $points   = (int) $market['points_si_correct'];

    $stmt = $pdo->prepare(
        'SELECT id, user_id, reponse FROM predictions WHERE market_id = ? AND statut = "en_attente"'
    );
    $stmt->execute([$marketId]);
    $predictions = $stmt->fetchAll();
    if (empty($predictions)) {
        return;
    }

    $eventMult = 1.0;
    if (function_exists('eventPointsMultiplier')) {
        try {
            $eventMult = eventPointsMultiplier($pdo, $match);
        } catch (Throwable $e) {
            $eventMult = 1.0;
        }
    }
    if ($eventMult < 1.0) {
        $eventMult = 1.0;
    }

    // Équipe préférée : résultat relatif à chaque user (pas une réponse unique).
    if ($type === 'fav_team') {
        if (($match['resultat_1x2'] ?? null) === null || $match['resultat_1x2'] === '') {
            return;
        }
        $mult = defined('FAV_TEAM_WIN_MULTIPLIER') ? (float) FAV_TEAM_WIN_MULTIPLIER : 2.0;
        foreach ($predictions as $pred) {
            $favs = fetchUserFavoriteTeams($pdo, (int) $pred['user_id']);
            $fav = $favs[0] ?? null;
            $correct = favTeamPickIsCorrect($match, $fav, (string) $pred['reponse'], $favs);
            $award = 0;
            if ($correct) {
                $award = (int) max(0, (int) round($points * $mult * $eventMult));
            }
            applyPredictionResult($pdo, $pred, $correct, $award, false);
            if ($correct) {
                $matchLabel = ($match['equipe_home'] ?? '') . ' – ' . ($match['equipe_away'] ?? '');
                notifyWinPush(
                    $pdo,
                    (int) $pred['user_id'],
                    (int) $pred['id'],
                    $award,
                    $matchLabel,
                    marketTypeLabel($type)
                );
            }
        }

        return;
    }

    $affectsSerie = ($type === '1x2');
    $result       = null;

    switch ($type) {
        case '1x2':
            $result = $match['resultat_1x2'];
            break;
        case 'score_exact':
            if ($match['score_home'] !== null && $match['score_away'] !== null) {
                $result = $match['score_home'] . '-' . $match['score_away'];
            }
            break;
        case 'buteur':
            $result = $match['buteur_reel'] ?? null;
            if ($result === null || $result === '') {
                // Le match est clos à l'affichage 30 min après le coup d'envoi : tant que le
                // résultat officiel n'est pas tombé, on attend au lieu d'annuler à tort.
                if ($match['resultat_1x2'] === null || $match['resultat_1x2'] === '') {
                    return;
                }
                // Match réellement terminé sans buteur de référence → annulation (0 pt).
                voidPendingPredictions($pdo, $marketId);
                return;
            }
            break;
    }

    if ($result === null || $result === '') {
        return;
    }

    $serieByUser = [];
    if ($affectsSerie) {
        foreach ($predictions as $pred) {
            $uid = (int) $pred['user_id'];
            if (!isset($serieByUser[$uid])) {
                $serieByUser[$uid] = userCurrentSerie($pdo, $uid);
            }
        }
    }

    foreach ($predictions as $pred) {
        $correct = ($pred['reponse'] === $result);
        $award = $points;
        if ($correct) {
            $streakMult = 1.0;
            if ($affectsSerie) {
                $uid = (int) $pred['user_id'];
                $serieAfter = ($serieByUser[$uid] ?? 0) + 1;
                $streakMult = streakPointsMultiplier($serieAfter);
            }
            $combined = $eventMult * $streakMult;
            if ($combined > 1.0) {
                $award = (int) max(0, (int) round($points * $combined));
            }
        }
        applyPredictionResult($pdo, $pred, $correct, $award, $affectsSerie);
        if ($correct) {
            $matchLabel = ($match['equipe_home'] ?? '') . ' – ' . ($match['equipe_away'] ?? '');
            notifyWinPush(
                $pdo,
                (int) $pred['user_id'],
                (int) $pred['id'],
                $award,
                $matchLabel,
                marketTypeLabel($type)
            );
        }
    }
}

function scoreMatch(PDO $pdo, int $matchId): void
{
    $stmt = $pdo->prepare('SELECT * FROM matches WHERE id = ? AND statut = "termine"');
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    if (!$match) {
        return;
    }

    $stmt = $pdo->prepare('SELECT id, type, points_si_correct FROM prediction_markets WHERE match_id = ?');
    $stmt->execute([$matchId]);
    foreach ($stmt->fetchAll() as $market) {
        scoreMarket($pdo, $match, $market);
    }
}
