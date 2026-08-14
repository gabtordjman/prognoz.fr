<?php
/**
 * Analyse du dump prognoz.sql (hors users) pour ménage + diagnostic "en attente".
 */
$path = 'C:/Users/Gabriel/Downloads/prognoz.sql';
$sql = file_get_contents($path);
if ($sql === false) {
    fwrite(STDERR, "Cannot read dump\n");
    exit(1);
}

preg_match_all('/CREATE TABLE `([^`]+)`/i', $sql, $tm);
echo "=== Tables ===\n" . implode(', ', $tm[1]) . "\n\n";

function extractInsertRows(string $sql, string $table): array
{
    $rows = [];
    if (!preg_match_all(
        '/INSERT INTO `' . preg_quote($table, '/') . '`\s*(?:\(([^)]*)\))?\s*VALUES\s*(.+?);/is',
        $sql,
        $blocks,
        PREG_SET_ORDER
    )) {
        return $rows;
    }
    foreach ($blocks as $block) {
        $cols = [];
        if (!empty($block[1])) {
            $cols = array_map(static function ($c) {
                return trim(str_replace('`', '', $c));
            }, explode(',', $block[1]));
        }
        $valuesPart = $block[2];
        // Parse tuples roughly: (...),(...);
        $len = strlen($valuesPart);
        $i = 0;
        while ($i < $len) {
            while ($i < $len && ($valuesPart[$i] === ' ' || $valuesPart[$i] === "\n" || $valuesPart[$i] === "\r" || $valuesPart[$i] === ',')) {
                $i++;
            }
            if ($i >= $len || $valuesPart[$i] !== '(') {
                break;
            }
            $i++;
            $fields = [];
            $cur = '';
            $inStr = false;
            $esc = false;
            while ($i < $len) {
                $ch = $valuesPart[$i];
                if ($inStr) {
                    if ($esc) {
                        $cur .= $ch;
                        $esc = false;
                    } elseif ($ch === '\\') {
                        $cur .= $ch;
                        $esc = true;
                    } elseif ($ch === "'") {
                        // look ahead for doubled quote
                        if ($i + 1 < $len && $valuesPart[$i + 1] === "'") {
                            $cur .= "''";
                            $i++;
                        } else {
                            $inStr = false;
                        }
                    } else {
                        $cur .= $ch;
                    }
                    $i++;
                    continue;
                }
                if ($ch === "'") {
                    $inStr = true;
                    $i++;
                    continue;
                }
                if ($ch === ',' ) {
                    $fields[] = trimValue($cur);
                    $cur = '';
                    $i++;
                    continue;
                }
                if ($ch === ')') {
                    $fields[] = trimValue($cur);
                    $i++;
                    break;
                }
                $cur .= $ch;
                $i++;
            }
            if ($cols) {
                $row = [];
                foreach ($cols as $idx => $name) {
                    $row[$name] = $fields[$idx] ?? null;
                }
                $rows[] = $row;
            } else {
                $rows[] = $fields;
            }
        }
    }

    return $rows;
}

function trimValue(string $cur): mixed
{
    $cur = trim($cur);
    if ($cur === 'NULL' || $cur === '') {
        return null;
    }

    return $cur;
}

$matches = extractInsertRows($sql, 'matches');
$markets = extractInsertRows($sql, 'prediction_markets');
$preds = extractInsertRows($sql, 'predictions');
$opts = extractInsertRows($sql, 'market_options');
$chat = extractInsertRows($sql, 'chat_messages');
$seasons = extractInsertRows($sql, 'seasons');
$ss = extractInsertRows($sql, 'season_scores');

echo 'matches: ' . count($matches) . "\n";
echo 'prediction_markets: ' . count($markets) . "\n";
echo 'predictions: ' . count($preds) . "\n";
echo 'market_options: ' . count($opts) . "\n";
echo 'chat_messages: ' . count($chat) . "\n";
echo 'seasons: ' . count($seasons) . "\n";
echo 'season_scores: ' . count($ss) . "\n\n";

$byStatut = [];
foreach ($matches as $m) {
    $s = (string) ($m['statut'] ?? '?');
    $byStatut[$s] = ($byStatut[$s] ?? 0) + 1;
}
echo "=== Matches par statut ===\n";
print_r($byStatut);

$now = new DateTimeImmutable('2026-08-11 19:20:00'); // dump time approx UTC+0
$horizonDays = 7; // MATCHS_HORIZON_JOURS
$horizonEnd = $now->modify('+' . $horizonDays . ' days');

$matchById = [];
foreach ($matches as $m) {
    $matchById[(int) $m['id']] = $m;
}
$marketById = [];
foreach ($markets as $pm) {
    $marketById[(int) $pm['id']] = $pm;
}

$predStat = [];
foreach ($preds as $p) {
    $s = (string) ($p['statut'] ?? '?');
    $predStat[$s] = ($predStat[$s] ?? 0) + 1;
}
echo "\n=== Predictions par statut ===\n";
print_r($predStat);

// Per-user en_attente breakdown
$userPending = [];
$openTicket = [];
$awaiting = [];
$detailAwait = [];
foreach ($preds as $p) {
    if (($p['statut'] ?? '') !== 'en_attente') {
        continue;
    }
    $uid = (int) ($p['user_id'] ?? 0);
    $mid = (int) ($p['market_id'] ?? 0);
    $pm = $marketById[$mid] ?? null;
    $matchId = $pm ? (int) $pm['match_id'] : 0;
    $m = $matchById[$matchId] ?? null;
    $userPending[$uid] = ($userPending[$uid] ?? 0) + 1;
    if (!$m) {
        $awaiting[$uid] = ($awaiting[$uid] ?? 0) + 1;
        $detailAwait[] = ['user' => $uid, 'reason' => 'orphan_market', 'market' => $mid];
        continue;
    }
    $statut = (string) $m['statut'];
    $date = (string) $m['date_match'];
    $inHorizon = $date <= $horizonEnd->format('Y-m-d H:i:s');
    $isOpen = in_array($statut, ['a_venir', 'en_cours'], true) && $inHorizon;
    if ($isOpen) {
        $openTicket[$uid] = ($openTicket[$uid] ?? 0) + 1;
    } else {
        $awaiting[$uid] = ($awaiting[$uid] ?? 0) + 1;
        $detailAwait[] = [
            'user' => $uid,
            'match' => $matchId,
            'teams' => ($m['equipe_home'] ?? '') . ' - ' . ($m['equipe_away'] ?? ''),
            'statut' => $statut,
            'date' => $date,
            'score' => ($m['score_home'] ?? 'null') . '-' . ($m['score_away'] ?? 'null'),
            'market_type' => $pm['type'] ?? '?',
        ];
    }
}

echo "\n=== en_attente par user (total / ouverts / hors ticket) ===\n";
$uids = array_unique(array_merge(array_keys($userPending), array_keys($openTicket), array_keys($awaiting)));
sort($uids);
foreach ($uids as $uid) {
    echo "user $uid: total=" . ($userPending[$uid] ?? 0)
        . ' open=' . ($openTicket[$uid] ?? 0)
        . ' awaiting/hors=' . ($awaiting[$uid] ?? 0) . "\n";
}

echo "\n=== Détail hors-ticket (max 40) ===\n";
foreach (array_slice($detailAwait, 0, 40) as $d) {
    echo json_encode($d, JSON_UNESCAPED_UNICODE) . "\n";
}

// Matches termine without score
$needScore = 0;
$termineOk = 0;
$termineNoScorePendingPreds = 0;
foreach ($matches as $m) {
    if (($m['statut'] ?? '') !== 'termine') {
        continue;
    }
    if ($m['score_home'] === null || $m['score_away'] === null || $m['score_home'] === '' || $m['score_away'] === '') {
        $needScore++;
    } else {
        $termineOk++;
    }
}
echo "\n=== Matchs terminés ===\n";
echo "avec score: $termineOk\n";
echo "sans score: $needScore\n";

// Pending preds on matches with score already (should have been scored)
$stuckWithScore = 0;
$stuckNoScore = 0;
$stuckFutureOutsideHorizon = 0;
$stuckAVenirPast = 0;
foreach ($detailAwait as $d) {
    if (($d['reason'] ?? '') === 'orphan_market') {
        continue;
    }
    if (($d['statut'] ?? '') === 'termine') {
        if (str_contains((string) $d['score'], 'null')) {
            $stuckNoScore++;
        } else {
            $stuckWithScore++;
        }
    } elseif (($d['statut'] ?? '') === 'a_venir') {
        $stuckFutureOutsideHorizon++;
    } else {
        $stuckAVenirPast++;
    }
}
echo "\n=== Causes hors-ticket ===\n";
echo "termine AVEC score mais pred encore en_attente: $stuckWithScore  (BUG scoring)\n";
echo "termine SANS score: $stuckNoScore\n";
echo "a_venir hors horizon: $stuckFutureOutsideHorizon\n";
echo "autres: $stuckAVenirPast\n";

// Old matches a_venir far past?
$oldAvenir = 0;
$oldTermine = 0;
$cutoff = $now->modify('-14 days')->format('Y-m-d H:i:s');
foreach ($matches as $m) {
    if (($m['date_match'] ?? '') >= $cutoff) {
        continue;
    }
    if (($m['statut'] ?? '') === 'a_venir') {
        $oldAvenir++;
    }
    if (($m['statut'] ?? '') === 'termine') {
        $oldTermine++;
    }
}
echo "\n=== Matchs > 14j ===\n";
echo "encore a_venir: $oldAvenir\n";
echo "termine: $oldTermine\n";

// Markets without match / options bloat
$orphanMarkets = 0;
foreach ($markets as $pm) {
    if (!isset($matchById[(int) $pm['match_id']])) {
        $orphanMarkets++;
    }
}
echo "\n=== Orphelins ===\n";
echo "markets sans match: $orphanMarkets\n";

$marketsWithScoreOpts = 0;
$scoreOptCount = 0;
foreach ($markets as $pm) {
    if (($pm['type'] ?? '') === 'score_exact') {
        $marketsWithScoreOpts++;
    }
}
foreach ($opts as $o) {
    // can't easily know type without join; count all
    $scoreOptCount++;
}
echo "market_options total: $scoreOptCount\n";
echo "markets score_exact: $marketsWithScoreOpts\n";

// Predictions on cancelled
$onAnnule = 0;
foreach ($preds as $p) {
    if (($p['statut'] ?? '') !== 'en_attente') {
        continue;
    }
    $pm = $marketById[(int) $p['market_id']] ?? null;
    if (!$pm) {
        continue;
    }
    $m = $matchById[(int) $pm['match_id']] ?? null;
    if ($m && ($m['statut'] ?? '') === 'annule') {
        $onAnnule++;
    }
}
echo "preds en_attente sur match annule: $onAnnule\n";

echo "\nDONE\n";
