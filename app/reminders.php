<?php
/**
 * Rappels matchs du jour (push uniquement par défaut).
 * Branché sur le cron léger /api/sync.php?cron=1.
 * E-mail : uniquement si REMIND_MAIL_ENABLED=1 (désactivé par défaut).
 */
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/** @return array{sent_push:int,sent_mail:int,skipped:int,candidates:int} */
function maybeSendDailyMatchReminders(PDO $pdo): array
{
    $stats = ['sent_push' => 0, 'sent_mail' => 0, 'skipped' => 0, 'candidates' => 0];

    $nowLocal = new DateTimeImmutable('now', appTimezone());
    $hour = (int) $nowLocal->format('G');
    // Fenêtre après-midi / soirée (évite de spammer le matin).
    if ($hour < 12 || $hour > 21) {
        return $stats;
    }

    $dayKey = $nowLocal->format('Y-m-d');
    $sentMap = loadReminderSentMap($dayKey);

    $openMatches = countOpenMatchesToday($pdo);
    if ($openMatches <= 0) {
        return $stats;
    }

    $users = fetchReminderCandidates($pdo);
    $stats['candidates'] = count($users);

    foreach ($users as $user) {
        $userId = (int) $user['id'];
        if (!empty($sentMap[$userId])) {
            $stats['skipped']++;
            continue;
        }

        $remaining = countUnpredictedOpenMatchesToday($pdo, $userId);
        if ($remaining <= 0) {
            $sentMap[$userId] = 'done';
            $stats['skipped']++;
            continue;
        }

        $lang = resolveMailLang($user);
        [$title, $body] = withLang($lang, static function () use ($remaining): array {
            $title = t('remind.push_title');
            $body = $remaining === 1
                ? t('remind.push_body_one', ['n' => $remaining])
                : t('remind.push_body_other', ['n' => $remaining]);

            return [$title, $body];
        });
        $url = url('index.php');

        $pushed = 0;
        if (pushIsConfigured()) {
            $pushed = sendPushToUser($pdo, $userId, [
                'title' => $title,
                'body'  => $body,
                'url'   => $url,
                'tag'   => 'prognoz-remind-' . $dayKey,
            ]);
        }

        $mailed = false;
        // E-mail de rappel : opt-in explicite via .env (pas d’envoi auto par défaut).
        if (
            $pushed === 0
            && REMIND_MAIL_ENABLED
            && userAllowsAppMail($user)
            && smtpConfigured()
        ) {
            $email = trim((string) ($user['email'] ?? ''));
            if ($email !== '') {
                $subject = APP_NAME . ' — ' . $title;
                $mailBody = withLang($lang, static function () use ($user, $body): string {
                    return t('remind.mail_hello', ['name' => $user['pseudo']]) . "\n\n"
                        . $body . "\n\n"
                        . absoluteUrl('index.php') . "\n\n"
                        . '— ' . APP_NAME;
                });
                $mailed = sendAppMail($email, $subject, $mailBody);
            }
        }

        if ($pushed > 0) {
            $stats['sent_push']++;
            $sentMap[$userId] = 'push';
        } elseif ($mailed) {
            $stats['sent_mail']++;
            $sentMap[$userId] = 'mail';
        } else {
            $stats['skipped']++;
            // Ne pas marquer comme envoyé si rien n'est parti (retry push au prochain cron).
            continue;
        }
    }

    saveReminderSentMap($dayKey, $sentMap);

    return $stats;
}

/** Matchs encore ouverts aujourd'hui (fuseau app). */
function countOpenMatchesToday(PDO $pdo): int
{
    [$startUtc, $endUtc] = todayBoundsUtc();
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM matches
         WHERE statut = 'a_venir'
           AND date_match >= ?
           AND date_match < ?"
    );
    $stmt->execute([$startUtc, $endUtc]);

    return (int) $stmt->fetchColumn();
}

/** Matchs ouverts aujourd'hui sans prono 1x2 (ou aucun prono sur un marché du match). */
function countUnpredictedOpenMatchesToday(PDO $pdo, int $userId): int
{
    [$startUtc, $endUtc] = todayBoundsUtc();
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM matches m
         WHERE m.statut = 'a_venir'
           AND m.date_match >= ?
           AND m.date_match < ?
           AND NOT EXISTS (
               SELECT 1 FROM predictions p
               INNER JOIN prediction_markets pm ON pm.id = p.market_id
               WHERE pm.match_id = m.id AND p.user_id = ?
           )"
    );
    $stmt->execute([$startUtc, $endUtc, $userId]);

    return (int) $stmt->fetchColumn();
}

/** @return list<array{id:int,pseudo:string,email:string,preferred_lang:?string,mail_opt_out:int}> */
function fetchReminderCandidates(PDO $pdo): array
{
    ensureMailPrefsSchema($pdo);
    // Candidats push (ou e-mail si REMIND_MAIL_ENABLED).
    $stmt = $pdo->query(
        "SELECT DISTINCT u.id, u.pseudo, u.email, u.mail_opt_out, u.preferred_lang
         FROM users u
         WHERE u.actif = 1
           AND (
               EXISTS (SELECT 1 FROM push_subscriptions ps WHERE ps.user_id = u.id)
               OR (
                   " . (REMIND_MAIL_ENABLED ? '1' : '0') . "
                   AND u.mail_opt_out = 0
                   AND u.email IS NOT NULL AND u.email <> ''
               )
           )
         ORDER BY u.id ASC
         LIMIT 500"
    );

    $rows = $stmt->fetchAll() ?: [];
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id'             => (int) $row['id'],
            'pseudo'         => (string) $row['pseudo'],
            'email'          => (string) ($row['email'] ?? ''),
            'mail_opt_out'   => (int) ($row['mail_opt_out'] ?? 0),
            'preferred_lang' => $row['preferred_lang'] ?? null,
        ];
    }

    return $out;
}

/** @return array{0:string,1:string} startUtc, endUtc (Y-m-d H:i:s) */
function todayBoundsUtc(): array
{
    $tz = appTimezone();
    $startLocal = new DateTimeImmutable('today', $tz);
    $endLocal = $startLocal->modify('+1 day');
    $startUtc = $startLocal->setTimezone(utcStorageTimezone())->format('Y-m-d H:i:s');
    $endUtc = $endLocal->setTimezone(utcStorageTimezone())->format('Y-m-d H:i:s');

    return [$startUtc, $endUtc];
}

/** @return array<int,string> */
function loadReminderSentMap(string $dayKey): array
{
    $path = reminderSentPath($dayKey);
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        return [];
    }
    $out = [];
    foreach ($data as $k => $v) {
        $out[(int) $k] = (string) $v;
    }

    return $out;
}

/** @param array<int,string> $map */
function saveReminderSentMap(string $dayKey, array $map): void
{
    ensureAppCacheDir();
    $path = reminderSentPath($dayKey);
    @file_put_contents($path, json_encode($map, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function reminderSentPath(string $dayKey): string
{
    return dirname(__DIR__) . '/var/cache/reminders_' . preg_replace('/[^0-9\-]/', '', $dayKey) . '.json';
}
