<?php
/**
 * Fil d'activité léger d'une communauté (pronos + gains).
 */
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/**
 * @return list<array{
 *   kind:string,pseudo:string,user_id:int,created_at:string,
 *   home:string,away:string,pick:string,market_type:string,
 *   points:int,statut:string,match_statut:string
 * }>
 */
function fetchCommunityActivityFeed(PDO $pdo, int $communityId, int $limit = 18): array
{
    $limit = max(5, min(40, $limit));

    $stmt = $pdo->prepare(
        "SELECT p.id, p.user_id, p.reponse, p.statut, p.points_gagnes, p.created_at, p.resolved_at,
                u.pseudo, pm.type AS market_type,
                m.equipe_home, m.equipe_away, m.resultat_1x2, m.score_home, m.score_away, m.statut AS match_statut
         FROM predictions p
         INNER JOIN community_members cm ON cm.user_id = p.user_id AND cm.community_id = ?
         INNER JOIN users u ON u.id = p.user_id AND u.actif = 1
         INNER JOIN prediction_markets pm ON pm.id = p.market_id
         INNER JOIN matches m ON m.id = pm.match_id
         WHERE p.created_at >= (UTC_TIMESTAMP() - INTERVAL 10 DAY)
         ORDER BY COALESCE(p.resolved_at, p.created_at) DESC, p.id DESC
         LIMIT {$limit}"
    );
    $stmt->execute([$communityId]);
    $rows = $stmt->fetchAll();

    $feed = [];
    foreach ($rows as $row) {
        $pick = formatPickLabel($row, (string) $row['reponse']);
        $statut = (string) ($row['statut'] ?? '');
        $points = (int) ($row['points_gagnes'] ?? 0);
        $kind = 'pick';
        if ($statut === 'correct' && $points > 0) {
            $kind = 'win';
        } elseif ($statut === 'incorrect') {
            $kind = 'miss';
        } elseif ($statut === 'annule') {
            $matchStatut = (string) ($row['match_statut'] ?? '');
            $kind = $matchStatut === 'reporte' ? 'postponed' : 'void';
        }

        $feed[] = [
            'kind'         => $kind,
            'pseudo'       => (string) $row['pseudo'],
            'user_id'      => (int) $row['user_id'],
            'created_at'   => (string) ($row['resolved_at'] ?: $row['created_at']),
            'home'         => (string) $row['equipe_home'],
            'away'         => (string) $row['equipe_away'],
            'pick'         => $pick,
            'market_type'  => (string) $row['market_type'],
            'points'       => $points,
            'statut'       => $statut,
            'match_statut' => (string) ($row['match_statut'] ?? ''),
        ];
    }

    return $feed;
}

/** Texte lisible d'une ligne d'activité. */
function formatActivityLine(array $item): string
{
    $name = (string) ($item['pseudo'] ?? '');
    $pick = (string) ($item['pick'] ?? '');
    $match = trim(($item['home'] ?? '') . ' – ' . ($item['away'] ?? ''));
    $kind = (string) ($item['kind'] ?? 'pick');
    $pts = (int) ($item['points'] ?? 0);

    return match ($kind) {
        'win'       => t('feed.win', ['name' => $name, 'n' => $pts, 'match' => $match]),
        'miss'      => t('feed.miss', ['name' => $name, 'pick' => $pick, 'match' => $match]),
        'void'      => t('feed.void', ['name' => $name, 'match' => $match]),
        'postponed' => t('feed.postponed', ['name' => $name, 'match' => $match]),
        default     => t('feed.pick', ['name' => $name, 'pick' => $pick, 'match' => $match]),
    };
}

function renderCommunityActivityFeed(array $items): void
{
    ?>
    <div class="panel panel-spaced">
        <div class="panel-head"><?= e(t('feed.title')) ?></div>
        <div class="panel-body">
            <?php if (empty($items)): ?>
                <p class="empty-msg"><?= e(t('feed.empty')) ?></p>
            <?php else: ?>
                <ul class="activity-feed">
                    <?php foreach ($items as $item):
                        $kind = (string) ($item['kind'] ?? 'pick');
                        $icon = match ($kind) {
                            'win'       => 'fa-trophy',
                            'miss'      => 'fa-xmark',
                            'void'      => 'fa-ban',
                            'postponed' => 'fa-clock',
                            default     => 'fa-ticket',
                        };
                        ?>
                        <li class="activity-item activity-item--<?= e($kind) ?>">
                            <span class="activity-icon" aria-hidden="true"><i class="fa-solid <?= e($icon) ?>"></i></span>
                            <div class="activity-body">
                                <p class="activity-text"><?= e(formatActivityLine($item)) ?></p>
                                <?php if (!empty($item['created_at'])): ?>
                                <time class="activity-time"><?= e(formatMatchWhen((string) $item['created_at'])) ?></time>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
