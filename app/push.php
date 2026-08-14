<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

function pushIsConfigured(): bool
{
    return pushConfigStatus()['ok'];
}

/** @return array{ok:bool, has_vapid:bool, has_vendor:bool, missing:list<string>} */
function pushConfigStatus(): array
{
    $hasVapid  = VAPID_PUBLIC_KEY !== '' && VAPID_PRIVATE_KEY !== '';
    $hasVendor = is_file(dirname(__DIR__) . '/vendor/autoload.php');
    $missing   = [];

    if (!$hasVapid) {
        $missing[] = 'vapid';
    }
    if (!$hasVendor) {
        $missing[] = 'vendor';
    }

    return [
        'ok'         => $hasVapid && $hasVendor,
        'has_vapid'  => $hasVapid,
        'has_vendor' => $hasVendor,
        'missing'    => $missing,
    ];
}

function ensurePushSubscriptionSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            endpoint TEXT NOT NULL,
            p256dh VARCHAR(255) NOT NULL,
            auth VARCHAR(255) NOT NULL,
            content_encoding VARCHAR(20) NOT NULL DEFAULT "aesgcm",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uq_push_endpoint (endpoint(500))
        ) ENGINE=InnoDB'
    );
}

/** @param array{endpoint:string,keys:array{p256dh:string,auth:string},contentEncoding?:string} $subscription */
function savePushSubscription(PDO $pdo, int $userId, array $subscription): bool
{
    ensurePushSubscriptionSchema($pdo);

    $endpoint = trim($subscription['endpoint'] ?? '');
    $p256dh   = trim($subscription['keys']['p256dh'] ?? '');
    $auth     = trim($subscription['keys']['auth'] ?? '');
    $encoding = trim($subscription['contentEncoding'] ?? 'aesgcm');

    if ($endpoint === '' || $p256dh === '' || $auth === '') {
        return false;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, content_encoding)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            p256dh = VALUES(p256dh),
            auth = VALUES(auth),
            content_encoding = VALUES(content_encoding),
            updated_at = CURRENT_TIMESTAMP'
    );

    return $stmt->execute([$userId, $endpoint, $p256dh, $auth, $encoding !== '' ? $encoding : 'aesgcm']);
}

function deletePushSubscription(PDO $pdo, int $userId, string $endpoint): void
{
    ensurePushSubscriptionSchema($pdo);
    $stmt = $pdo->prepare('DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?');
    $stmt->execute([$userId, $endpoint]);
}

/** @param array{title?:string,body?:string,url?:string,tag?:string,icon?:string} $payload */
function sendPushToUser(PDO $pdo, int $userId, array $payload): int
{
    if (!pushIsConfigured()) {
        return 0;
    }

    ensurePushSubscriptionSchema($pdo);
    require_once dirname(__DIR__) . '/vendor/autoload.php';

    $stmt = $pdo->prepare(
        'SELECT endpoint, p256dh, auth, content_encoding FROM push_subscriptions WHERE user_id = ?'
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        return 0;
    }

    $auth = [
        'VAPID' => [
            'subject'    => VAPID_SUBJECT,
            'publicKey'  => VAPID_PUBLIC_KEY,
            'privateKey' => VAPID_PRIVATE_KEY,
        ],
    ];

    $webPush = new Minishlink\WebPush\WebPush($auth);
    $sent    = 0;

    $json = json_encode([
        'title' => (string) ($payload['title'] ?? 'Prognoz'),
        'body'  => (string) ($payload['body'] ?? ''),
        'url'   => (string) ($payload['url'] ?? url('index.php')),
        'tag'   => (string) ($payload['tag'] ?? ('prognoz-' . time())),
        'icon'  => (string) ($payload['icon'] ?? absoluteUrl('assets/img/apple-touch-icon.svg')),
    ], JSON_UNESCAPED_UNICODE);

    foreach ($rows as $row) {
        $subscription = Minishlink\WebPush\Subscription::create([
            'endpoint'        => $row['endpoint'],
            'publicKey'       => $row['p256dh'],
            'authToken'       => $row['auth'],
            'contentEncoding' => $row['content_encoding'] ?: 'aesgcm',
        ]);

        try {
            $report = $webPush->sendOneNotification($subscription, $json);
            if ($report->isSuccess()) {
                $sent++;
                continue;
            }
            if ($report->isSubscriptionExpired()) {
                deletePushSubscription($pdo, $userId, $row['endpoint']);
            } else {
                error_log('Prognoz push fail user=' . $userId . ' reason=' . $report->getReason());
            }
        } catch (Throwable $e) {
            error_log('Prognoz push exception user=' . $userId . ' ' . $e->getMessage());
        }
    }

    return $sent;
}

function notifyCommunityChatPush(
    PDO $pdo,
    int $communityId,
    int $senderId,
    string $senderPseudo,
    string $contentPreview,
    string $communityName
): int {
    if (!pushIsConfigured()) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT user_id FROM community_members WHERE community_id = ? AND user_id != ?'
    );
    $stmt->execute([$communityId, $senderId]);
    $userIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $preview = mb_strlen($contentPreview) > 120
        ? mb_substr($contentPreview, 0, 119) . '…'
        : $contentPreview;

    $payload = [
        'title' => $communityName !== '' ? $communityName : 'Communauté',
        'body'  => $senderPseudo . ' : ' . $preview,
        'url'   => url('communities/view.php?id=' . $communityId),
        'tag'   => 'prognoz-chat-' . $communityId,
    ];

    $total = 0;
    foreach ($userIds as $uid) {
        $total += sendPushToUser($pdo, $uid, $payload);
    }

    return $total;
}

function notifyWinPush(
    PDO $pdo,
    int $userId,
    int $predictionId,
    int $points,
    string $matchLabel,
    string $marketLabel
): int {
    if ($points <= 0 || !pushIsConfigured()) {
        return 0;
    }

    return sendPushToUser($pdo, $userId, [
        'title' => '+' . $points . ' pt' . ($points > 1 ? 's' : '') . ' — Bon prono !',
        'body'  => $matchLabel . ' · ' . $marketLabel,
        'url'   => url('account/dashboard.php'),
        'tag'   => 'prognoz-win-' . $predictionId,
    ]);
}

function notifySeasonClosedPush(PDO $pdo, int $seasonId): int
{
    if (!pushIsConfigured()) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT fin FROM seasons WHERE id = ?');
    $stmt->execute([$seasonId]);
    $finLabel = formatSeasonFin((string) ($stmt->fetchColumn() ?: ''));

    $stmt = $pdo->prepare(
        'SELECT DISTINCT user_id FROM season_scores WHERE season_id = ? AND points > 0'
    );
    $stmt->execute([$seasonId]);
    $userIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $rewardStmt = $pdo->prepare(
        'SELECT sr.classement, sr.recompense, c.nom AS community_name
         FROM season_rewards sr
         INNER JOIN communities c ON c.id = sr.community_id
         WHERE sr.season_id = ? AND sr.user_id = ?
         ORDER BY sr.classement ASC'
    );

    $sent = 0;
    foreach ($userIds as $userId) {
        $rewardStmt->execute([$seasonId, $userId]);
        $rewards = $rewardStmt->fetchAll();
        $parts   = [];
        foreach ($rewards as $row) {
            $name = decryptSensitive($row['community_name'] ?? '');
            $parts[] = ($row['recompense'] ?? 'Badge') . ' · ' . ($name !== '' ? $name : 'Communauté');
        }

        if (!empty($parts)) {
            $body  = implode(' · ', array_slice($parts, 0, 3));
            if (count($parts) > 3) {
                $body .= '…';
            }
            $title = 'Saison terminée — félicitations !';
        } else {
            $title = 'Saison terminée';
            $body  = 'Consultez le classement dans vos communautés (fin le ' . $finLabel . ').';
        }

        $sent += sendPushToUser($pdo, $userId, [
            'title' => $title,
            'body'  => $body,
            'url'   => url('account/dashboard.php'),
            'tag'   => 'prognoz-season-' . $seasonId,
        ]);
    }

    return $sent;
}
