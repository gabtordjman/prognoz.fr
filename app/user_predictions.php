<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/** Colonne last_seen_at (connexions / fréquence admin). */
function ensureUserLastSeenSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $cols = $pdo->query('SHOW COLUMNS FROM users LIKE "last_seen_at"')->fetch();
        if (!$cols) {
            $pdo->exec('ALTER TABLE users ADD COLUMN last_seen_at DATETIME NULL AFTER password_changed_at');
        }
    } catch (PDOException $e) {
        // Migration manuelle si droits limités
    }
}

/** Met à jour last_seen_at (throttle ~5 min). */
function touchUserLastSeen(PDO $pdo, int $userId): void
{
    if ($userId <= 0) {
        return;
    }
    $now = time();
    $prev = (int) ($_SESSION['_last_seen_touch'] ?? 0);
    if ($prev > 0 && ($now - $prev) < 300) {
        return;
    }
    ensureUserLastSeenSchema($pdo);
    try {
        $pdo->prepare('UPDATE users SET last_seen_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$userId]);
        $_SESSION['_last_seen_touch'] = $now;
    } catch (Throwable $e) {
        // ignore
    }
}

/** Colonnes / enums optionnels (migrations auto). */
function ensurePredictionHistorySchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $cols = $pdo->query('SHOW COLUMNS FROM predictions LIKE "resolved_at"')->fetch();
        if (!$cols) {
            $pdo->exec('ALTER TABLE predictions ADD COLUMN resolved_at DATETIME NULL AFTER points_gagnes');
        }
        $cols = $pdo->query('SHOW COLUMNS FROM predictions LIKE "result_notified"')->fetch();
        if (!$cols) {
            $pdo->exec('ALTER TABLE predictions ADD COLUMN result_notified TINYINT(1) NOT NULL DEFAULT 0 AFTER resolved_at');
        }
        $statutCol = $pdo->query('SHOW COLUMNS FROM predictions LIKE "statut"')->fetch();
        if ($statutCol && stripos((string) ($statutCol['Type'] ?? ''), 'annule') === false) {
            $pdo->exec(
                "ALTER TABLE predictions
                 MODIFY statut ENUM('en_attente', 'correct', 'incorrect', 'annule')
                 NOT NULL DEFAULT 'en_attente'"
            );
        }
        $cols = $pdo->query('SHOW COLUMNS FROM users LIKE "privacy_accepted_at"')->fetch();
        if (!$cols) {
            $pdo->exec('ALTER TABLE users ADD COLUMN privacy_accepted_at DATETIME NULL AFTER created_at');
        }
        $cols = $pdo->query('SHOW COLUMNS FROM users LIKE "pseudo_changed_at"')->fetch();
        if (!$cols) {
            $pdo->exec('ALTER TABLE users ADD COLUMN pseudo_changed_at DATETIME NULL AFTER privacy_accepted_at');
        }
        $cols = $pdo->query('SHOW COLUMNS FROM users LIKE "password_changed_at"')->fetch();
        if (!$cols) {
            $pdo->exec('ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL AFTER pseudo_changed_at');
        }
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_reset_token (token_hash),
                INDEX idx_reset_user (user_id)
            ) ENGINE=InnoDB'
        );
    } catch (PDOException $e) {
        // Migration manuelle si droits limités
    }
}

function formatPredictionPick(array $row): string
{
    return formatPickLabel($row, $row['reponse']);
}

function formatMatchResultLine(array $row): string
{
    $matchStatut = (string) ($row['match_statut'] ?? '');
    if ($matchStatut === 'reporte') {
        return t('dash.match_postponed');
    }
    if ($matchStatut === 'annule') {
        return formatCancelledMatchResultLine($row['annulation_raison'] ?? null);
    }
    if ($row['score_home'] !== null && $row['score_away'] !== null) {
        return (int) $row['score_home'] . ' – ' . (int) $row['score_away'];
    }
    if (!empty($row['resultat_1x2'])) {
        if ($row['resultat_1x2'] === '1') {
            return $row['equipe_home'];
        }
        if ($row['resultat_1x2'] === '2') {
            return $row['equipe_away'];
        }
        return t('market.draw');
    }

    return '—';
}

/**
 * Présentation historique d’un prono (badge + libellé résultat).
 *
 * @return array{item_class:string,badge_class:string,badge_label:string,result:string}
 */
function predictionHistoryPresentation(array $h): array
{
    $statut = (string) ($h['statut'] ?? '');
    $won = $statut === 'correct';
    $voided = $statut === 'annule';
    $matchStatut = (string) ($h['match_statut'] ?? '');
    $matchCancelled = $matchStatut === 'annule';
    $matchPostponed = $matchStatut === 'reporte';

    if ($matchPostponed) {
        return [
            'item_class'   => 'void',
            'badge_class'  => 'postponed',
            'badge_label'  => t('dash.postponed_short'),
            'result'       => t('dash.match_postponed'),
        ];
    }
    if ($matchCancelled) {
        $resultLine = formatCancelledMatchResultLine($h['annulation_raison'] ?? null);

        return [
            'item_class'   => 'void',
            'badge_class'  => 'void',
            'badge_label'  => t('dash.match_cancelled'),
            'result'       => $resultLine,
        ];
    }
    if ($voided) {
        return [
            'item_class'   => 'void',
            'badge_class'  => 'void',
            'badge_label'  => t('dash.void_short'),
            'result'       => t('dash.voided'),
        ];
    }
    if ($won) {
        $pts = (int) ($h['points_gagnes'] ?? 0);

        return [
            'item_class'   => 'win',
            'badge_class'  => 'win',
            'badge_label'  => '+' . $pts . ' ' . ($pts > 1 ? t('common.pts') : t('common.pt')),
            'result'       => formatMatchResultLine($h),
        ];
    }

    $lostPts = (int) ($h['points_gagnes'] ?? 0);
    if ($lostPts < 0) {
        $n = abs($lostPts);

        return [
            'item_class'   => 'loss',
            'badge_class'  => 'loss',
            'badge_label'  => '−' . $n . ' ' . ($n > 1 ? t('common.pts') : t('common.pt')),
            'result'       => formatMatchResultLine($h),
        ];
    }

    return [
        'item_class'   => 'loss',
        'badge_class'  => 'loss',
        'badge_label'  => t('dash.missed'),
        'result'       => formatMatchResultLine($h),
    ];
}

/** @return list<array<string,mixed>> */
function getUserPredictionHistory(PDO $pdo, int $userId, int $limit = 25): array
{
    ensurePredictionHistorySchema($pdo);
    ensureMatchCancelReasonSchema($pdo);
    $limit = max(1, min(50, $limit));
    $stmt = $pdo->prepare(
        "SELECT p.id, p.reponse, p.statut, p.points_gagnes, p.resolved_at, p.created_at,
                pm.type AS market_type, pm.points_si_correct,
                m.equipe_home, m.equipe_away, m.competition, m.sport, m.statut AS match_statut,
                m.score_home, m.score_away, m.resultat_1x2, m.date_match, m.annulation_raison
         FROM predictions p
         INNER JOIN prediction_markets pm ON pm.id = p.market_id
         INNER JOIN matches m ON m.id = pm.match_id
         WHERE p.user_id = ? AND p.statut IN ('correct', 'incorrect', 'annule')
         ORDER BY COALESCE(p.resolved_at, p.created_at) DESC
         LIMIT {$limit}"
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

/** @return array{total:int,wins:int,losses:int,rate:float,points:int} */
function getUserPredictionStats(PDO $pdo, int $userId): array
{
    ensurePredictionHistorySchema($pdo);
    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN statut = 'correct' THEN 1 ELSE 0 END) AS wins,
            SUM(CASE WHEN statut = 'incorrect' THEN 1 ELSE 0 END) AS losses,
            COALESCE(SUM(points_gagnes), 0) AS points
         FROM predictions
         WHERE user_id = ? AND statut IN ('correct', 'incorrect')"
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch() ?: [];

    $total = (int) ($row['total'] ?? 0);
    $wins  = (int) ($row['wins'] ?? 0);

    return [
        'total'  => $total,
        'wins'   => $wins,
        'losses' => (int) ($row['losses'] ?? 0),
        'rate'   => $total > 0 ? round(100 * $wins / $total, 1) : 0.0,
        'points' => (int) ($row['points'] ?? 0),
    ];
}

/** Pronostics gagnés non encore notifiés à l'utilisateur. */
function fetchUnnotifiedWins(PDO $pdo, int $userId): array
{
    ensurePredictionHistorySchema($pdo);
    $stmt = $pdo->prepare(
        "SELECT p.id, p.points_gagnes, p.reponse, pm.type AS market_type,
                m.equipe_home, m.equipe_away, m.score_home, m.score_away, m.resultat_1x2
         FROM predictions p
         INNER JOIN prediction_markets pm ON pm.id = p.market_id
         INNER JOIN matches m ON m.id = pm.match_id
         WHERE p.user_id = ? AND p.statut = 'correct' AND p.result_notified = 0
         ORDER BY p.id ASC
         LIMIT 20"
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

function countUnnotifiedWins(PDO $pdo, int $userId): int
{
    ensurePredictionHistorySchema($pdo);
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM predictions WHERE user_id = ? AND statut = 'correct' AND result_notified = 0"
    );
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

function markUserResultsSeen(PDO $pdo, int $userId): void
{
    ensurePredictionHistorySchema($pdo);
    $pdo->prepare(
        "UPDATE predictions SET result_notified = 1 WHERE user_id = ? AND statut = 'correct' AND result_notified = 0"
    )->execute([$userId]);
}

function markPredictionsNotified(PDO $pdo, int $userId, array $predictionIds): void
{
    if (empty($predictionIds)) {
        return;
    }
    ensurePredictionHistorySchema($pdo);
    $ids = array_map('intval', $predictionIds);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$userId], $ids);
    $pdo->prepare(
        "UPDATE predictions SET result_notified = 1 WHERE user_id = ? AND id IN ({$ph})"
    )->execute($params);
}

function buildPointNotificationPayload(PDO $pdo, int $userId, bool $markNotified = false): array
{
    $wins = fetchUnnotifiedWins($pdo, $userId);
    if (empty($wins)) {
        return ['items' => [], 'total_points' => 0];
    }

    $items = [];
    $total = 0;
    $ids   = [];
    foreach ($wins as $w) {
        $pts = (int) $w['points_gagnes'];
        $total += $pts;
        $ids[] = (int) $w['id'];
        $match = $w['equipe_home'] . ' – ' . $w['equipe_away'];
        $result = formatMatchResultLine($w);
        $pick = formatPickLabel($w, $w['reponse']);
        $items[] = [
            'id'     => (int) $w['id'],
            'points' => $pts,
            'match'  => $match,
            'pick'   => $pick,
            'result' => $result,
            'label'  => marketTypeLabel($w['market_type']),
        ];
    }

    if ($markNotified) {
        markPredictionsNotified($pdo, $userId, $ids);
    }

    return ['items' => $items, 'total_points' => $total];
}
