<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/** Adresse destinataire des alertes / rapports admin. */
function adminNotifyEmail(): string
{
    $email = trim((string) ADMIN_NOTIFY_EMAIL);
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }

    return 'admin@prognoz.fr';
}

/**
 * Regroupe les lignes void → par utilisateur puis matchs.
 *
 * @param list<array<string,mixed>> $rows
 * @return array<int, array{pseudo:string,email:string,matches:list<array<string,mixed>>}>
 */
function groupUnavailableRowsByUser(array $rows): array
{
    $byUser = [];
    foreach ($rows as $row) {
        $uid = (int) ($row['user_id'] ?? 0);
        if ($uid <= 0) {
            continue;
        }
        if (!isset($byUser[$uid])) {
            $byUser[$uid] = [
                'pseudo'  => (string) ($row['pseudo'] ?? ''),
                'email'   => (string) ($row['email'] ?? ''),
                'matches' => [],
            ];
        }
        $mid = (int) ($row['match_id'] ?? 0);
        $key = $mid . '|' . (string) ($row['market_type'] ?? '');
        if (isset($byUser[$uid]['matches'][$key])) {
            continue;
        }
        $byUser[$uid]['matches'][$key] = [
            'match_id'    => $mid,
            'label'       => trim(($row['equipe_home'] ?? '') . ' – ' . ($row['equipe_away'] ?? '')),
            'competition' => (string) ($row['competition'] ?? ''),
            'sport'       => (string) ($row['sport'] ?? ''),
            'date_match'  => (string) ($row['date_match'] ?? ''),
            'market_type' => (string) ($row['market_type'] ?? ''),
        ];
    }
    foreach ($byUser as &$u) {
        $u['matches'] = array_values($u['matches']);
    }
    unset($u);

    return $byUser;
}

/**
 * E-mail automatique quand des pronos passent « données indisponibles ».
 *
 * @param list<array<string,mixed>> $rows
 */
function notifyAdminUnavailableResults(array $rows): bool
{
    if ($rows === []) {
        return false;
    }

    $byUser = groupUnavailableRowsByUser($rows);
    $predCount = count($rows);
    $userCount = count($byUser);
    $matchIds = [];
    foreach ($rows as $r) {
        $matchIds[(int) ($r['match_id'] ?? 0)] = true;
    }
    $matchCount = count(array_filter(array_keys($matchIds)));

    $textLines = [
        'Alerte Prognoz — matchs reportés automatiquement',
        '',
        sprintf(
            '%d prono(s) concernés (%d joueur(s), %d match(s)) : délai dépassé sans résultat API.',
            $predCount,
            $userCount,
            $matchCount
        ),
        'Les matchs sont passés en « reporté » (0 pt, visible joueur). RESULT_MAX_WAIT_DAYS.',
        '',
        'Info : panel admin → Résultats (section Reportés) si tu veux resaisir un score plus tard.',
        '',
    ];

    $htmlParts = [
        '<p style="margin:0 0 12px;">'
        . htmlspecialchars(
            sprintf(
                '%d prono(s) viennent d’être liés à un match reporté automatiquement '
                . '(%d joueur(s), %d match(s) — délai sans résultat API).',
                $predCount,
                $userCount,
                $matchCount
            ),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        )
        . '</p>',
        '<p style="margin:0 0 16px;color:#5c5345;font-size:14px;">'
        . 'Les joueurs voient « Match reporté » (0 pt). Tu peux encore saisir un score '
        . 'dans la section Reportés si le résultat arrive plus tard.</p>',
    ];

    foreach ($byUser as $u) {
        $textLines[] = '— ' . $u['pseudo'] . ' <' . $u['email'] . '>';
        $htmlParts[] = '<div style="margin:0 0 14px;padding:12px 14px;background:rgba(255,255,255,0.45);'
            . 'border:1px solid rgba(90,75,55,0.22);border-radius:8px;">'
            . '<p style="margin:0 0 8px;font-weight:700;">'
            . htmlspecialchars($u['pseudo'], ENT_QUOTES | ENT_HTML5, 'UTF-8')
            . ' <span style="font-weight:500;color:#5c5345;font-size:13px;">'
            . htmlspecialchars($u['email'], ENT_QUOTES | ENT_HTML5, 'UTF-8')
            . '</span></p><ul style="margin:0;padding-left:18px;">';

        foreach ($u['matches'] as $m) {
            $when = $m['date_match'] !== '' ? formatMatchWhen($m['date_match']) : '—';
            $market = marketTypeLabel($m['market_type']);
            $line = sprintf(
                '%s · %s · %s (#%d)',
                $m['label'],
                $market,
                $when,
                $m['match_id']
            );
            $textLines[] = '   · ' . $line;
            $htmlParts[] = '<li style="margin:0 0 4px;">'
                . htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8')
                . '</li>';
        }
        $htmlParts[] = '</ul></div>';
        $textLines[] = '';
    }

    $subject = sprintf(
        '[Prognoz] %d match(s) reporté(s) auto — %d joueur(s)',
        $matchCount,
        $userCount
    );
    $bodyText = implode("\n", $textLines);
    $html = renderAppMailHtml([
        'title'       => 'Matchs reportés automatiquement',
        'preheader'   => sprintf('%d match(s) sans résultat API après délai.', $matchCount),
        'greeting'    => 'Alerte administration',
        'body_html'   => implode('', $htmlParts),
        'cta_url'     => absoluteUrl('admin/scores.php') . '#reportes',
        'cta_label'   => 'Voir les reportés',
        'footer_note' => 'E-mail automatique Prognoz · ' . adminNotifyEmail(),
    ]);

    return sendAppMail(adminNotifyEmail(), $subject, $bodyText, $html);
}

/**
 * Snapshot actuel des données indisponibles (void + bloqués sans score).
 *
 * @return array{
 *   voided_matches:list<array<string,mixed>>,
 *   stuck_matches:list<array<string,mixed>>,
 *   by_user:array<int,array{pseudo:string,email:string,matches:list<array<string,mixed>>}>,
 *   voided_predictions:int
 * }
 */
function collectUnavailableDataSnapshot(PDO $pdo): array
{
    ensurePredictionHistorySchema($pdo);

    $voidedMatches = listVoidedMatchesForManualScore($pdo, 100);
    $stuckMatches  = listStuckMatchesForManualScore($pdo, 100);

    $stmt = $pdo->query(
        "SELECT p.id, p.user_id, u.pseudo, u.email,
                m.id AS match_id, m.equipe_home, m.equipe_away, m.competition,
                m.sport, m.date_match, pm.type AS market_type
         FROM predictions p
         INNER JOIN prediction_markets pm ON pm.id = p.market_id
         INNER JOIN matches m ON m.id = pm.match_id
         INNER JOIN users u ON u.id = p.user_id
         WHERE p.statut = 'annule'
           AND (m.resultat_1x2 IS NULL OR m.resultat_1x2 = '')
         ORDER BY u.pseudo ASC, m.date_match DESC
         LIMIT 500"
    );
    $rows = $stmt->fetchAll();

    return [
        'voided_matches'      => $voidedMatches,
        'stuck_matches'       => $stuckMatches,
        'by_user'             => groupUnavailableRowsByUser($rows),
        'voided_predictions'  => count($rows),
    ];
}

/** Envoie le rapport « vérifier les données indisponibles ». */
function sendUnavailableDataReportMail(PDO $pdo): bool
{
    $snap = collectUnavailableDataSnapshot($pdo);
    $voidN = count($snap['voided_matches']);
    $stuckN = count($snap['stuck_matches']);
    $predN = $snap['voided_predictions'];
    $userN = count($snap['by_user']);

    $text = [
        'Rapport Prognoz — données indisponibles',
        'Généré le ' . date('d/m/Y H:i') . ' (UTC serveur : ' . gmdate('Y-m-d H:i') . ')',
        '',
        sprintf('Pronos annulés sans score : %d', $predN),
        sprintf('Matchs concernés (à saisir) : %d', $voidN),
        sprintf('Matchs bloqués (pronos encore en attente) : %d', $stuckN),
        sprintf('Joueurs impactés : %d', $userN),
        '',
    ];

    $html = [
        '<p style="margin:0 0 12px;">Rapport généré le <strong>'
        . htmlspecialchars(date('d/m/Y H:i'), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        . '</strong>.</p>',
        '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 18px;font-size:14px;">',
        rowReportStat('Pronos « données indisponibles »', (string) $predN),
        rowReportStat('Matchs à saisir (annulés sans score)', (string) $voidN),
        rowReportStat('Matchs bloqués (en attente)', (string) $stuckN),
        rowReportStat('Joueurs impactés', (string) $userN),
        '</table>',
    ];

    if ($snap['by_user'] === [] && $stuckN === 0) {
        $text[] = 'Aucune donnée indisponible pour le moment.';
        $html[] = '<p style="margin:0;color:#1e5035;font-weight:700;">Tout est clair — rien à corriger.</p>';
    } else {
        if ($snap['by_user'] !== []) {
            $text[] = 'Détail par joueur (pronos annulés sans score) :';
            $text[] = '';
            $html[] = '<h3 style="margin:0 0 10px;font-size:15px;">Par joueur</h3>';
            foreach ($snap['by_user'] as $u) {
                $text[] = '— ' . $u['pseudo'] . ' <' . $u['email'] . '>';
                $html[] = '<div style="margin:0 0 12px;padding:10px 12px;background:rgba(255,255,255,0.45);'
                    . 'border-radius:8px;border:1px solid rgba(90,75,55,0.2);">'
                    . '<strong>' . htmlspecialchars($u['pseudo'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</strong>'
                    . ' <span style="color:#5c5345;font-size:13px;">'
                    . htmlspecialchars($u['email'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</span><ul style="margin:6px 0 0;padding-left:18px;">';
                foreach ($u['matches'] as $m) {
                    $line = sprintf(
                        '%s · %s · %s (#%d)',
                        $m['label'],
                        marketTypeLabel($m['market_type']),
                        $m['date_match'] !== '' ? formatMatchWhen($m['date_match']) : '—',
                        $m['match_id']
                    );
                    $text[] = '   · ' . $line;
                    $html[] = '<li>' . htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</li>';
                }
                $html[] = '</ul></div>';
                $text[] = '';
            }
        }
        if ($stuckN > 0) {
            $text[] = 'Matchs encore bloqués (pronos en attente, pas de score API) :';
            $html[] = '<h3 style="margin:16px 0 10px;font-size:15px;">Encore bloqués (en attente)</h3><ul style="margin:0;padding-left:18px;">';
            foreach ($snap['stuck_matches'] as $m) {
                $line = sprintf(
                    '#%d %s – %s · %s · %d prono(s)',
                    (int) $m['id'],
                    $m['equipe_home'],
                    $m['equipe_away'],
                    formatMatchWhen((string) $m['date_match']),
                    (int) $m['pending_count']
                );
                $text[] = '   · ' . $line;
                $html[] = '<li>' . htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</li>';
            }
            $html[] = '</ul>';
        }
    }

    $subject = sprintf(
        '[Prognoz] Rapport données indisponibles — %d match(s) / %d joueur(s)',
        $voidN + $stuckN,
        $userN
    );
    $mailHtml = renderAppMailHtml([
        'title'       => 'Données indisponibles',
        'preheader'   => sprintf('%d match(s) à examiner', $voidN + $stuckN),
        'greeting'    => 'Rapport demandé',
        'body_html'   => implode('', $html),
        'cta_url'     => absoluteUrl('admin/scores.php') . '#donnees-indisponibles',
        'cta_label'   => 'Ouvrir les scores manuels',
        'footer_note' => 'Rapport manuel · ' . adminNotifyEmail(),
    ]);

    return sendAppMail(adminNotifyEmail(), $subject, implode("\n", $text), $mailHtml);
}

function rowReportStat(string $label, string $value): string
{
    return '<tr>'
        . '<td style="padding:6px 0;color:#5c5345;border-bottom:1px solid rgba(90,75,55,0.15);">'
        . htmlspecialchars($label, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</td>'
        . '<td style="padding:6px 0;text-align:right;font-weight:800;font-family:IBM Plex Mono,monospace;'
        . 'border-bottom:1px solid rgba(90,75,55,0.15);">'
        . htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</td>'
        . '</tr>';
}

/**
 * Statistiques du mois civil en cours (fuseau serveur).
 *
 * @return array<string,mixed>
 */
function collectMonthlySiteReport(PDO $pdo): array
{
    ensurePredictionHistorySchema($pdo);
    ensureUserLastSeenSchema($pdo);

    $start = date('Y-m-01 00:00:00');
    $end   = date('Y-m-t 23:59:59');
    $monthsFr = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
        5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
        9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
    ];
    $labelFr = ($monthsFr[(int) date('n')] ?? date('F')) . ' ' . date('Y');

    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM matches WHERE statut = \'termine\' AND date_match BETWEEN ? AND ?'
    );
    $st->execute([$start, $end]);
    $matchesPlayed = (int) $st->fetchColumn();

    $st = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN statut = 'correct' THEN points_gagnes ELSE 0 END), 0) AS points_won,
            COALESCE(SUM(CASE WHEN statut = 'incorrect' THEN 1 ELSE 0 END), 0) AS losses,
            COALESCE(SUM(CASE WHEN statut = 'correct' THEN 1 ELSE 0 END), 0) AS wins,
            COALESCE(SUM(CASE WHEN statut = 'annule' THEN 1 ELSE 0 END), 0) AS voided,
            COUNT(*) AS resolved
         FROM predictions
         WHERE resolved_at BETWEEN ? AND ?
           AND statut IN ('correct', 'incorrect', 'annule')"
    );
    $st->execute([$start, $end]);
    $predStats = $st->fetch() ?: [];

    // Points « perdus » = somme des points_si_correct des pronos incorrects du mois
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(pm.points_si_correct), 0)
         FROM predictions p
         INNER JOIN prediction_markets pm ON pm.id = p.market_id
         WHERE p.statut = 'incorrect'
           AND p.resolved_at BETWEEN ? AND ?"
    );
    $st->execute([$start, $end]);
    $pointsLost = (int) $st->fetchColumn();

    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM community_messages
         WHERE created_at BETWEEN ? AND ? AND supprime = 0'
    );
    $st->execute([$start, $end]);
    $messages = (int) $st->fetchColumn();

    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM users WHERE created_at BETWEEN ? AND ?'
    );
    $st->execute([$start, $end]);
    $newUsers = (int) $st->fetchColumn();

    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM users WHERE actif = 1'
    );
    $st->execute();
    $activeUsers = (int) $st->fetchColumn();

    // Joueurs les plus actifs : pronos créés + messages ce mois
    $st = $pdo->prepare(
        "SELECT u.id, u.pseudo, u.email, u.last_seen_at, u.points_totaux,
                COALESCE(p.preds, 0) AS preds,
                COALESCE(m.msgs, 0) AS msgs
         FROM users u
         LEFT JOIN (
             SELECT user_id, COUNT(*) AS preds
             FROM predictions
             WHERE created_at BETWEEN ? AND ?
             GROUP BY user_id
         ) p ON p.user_id = u.id
         LEFT JOIN (
             SELECT user_id, COUNT(*) AS msgs
             FROM community_messages
             WHERE created_at BETWEEN ? AND ? AND supprime = 0
             GROUP BY user_id
         ) m ON m.user_id = u.id
         WHERE u.actif = 1
           AND (COALESCE(p.preds, 0) > 0 OR COALESCE(m.msgs, 0) > 0)
         ORDER BY (COALESCE(p.preds, 0) + COALESCE(m.msgs, 0)) DESC, u.last_seen_at DESC
         LIMIT 15"
    );
    $st->execute([$start, $end, $start, $end]);
    $topUsers = $st->fetchAll();

    // Connexions les plus fréquentes = last_seen le plus récent / actifs ce mois
    $st = $pdo->prepare(
        'SELECT id, pseudo, email, last_seen_at, points_totaux
         FROM users
         WHERE actif = 1 AND last_seen_at IS NOT NULL AND last_seen_at BETWEEN ? AND ?
         ORDER BY last_seen_at DESC
         LIMIT 15'
    );
    $st->execute([$start, $end]);
    $recentLogins = $st->fetchAll();

    $quota = oddsQuotaState();

    $st = $pdo->prepare(
        "SELECT m.equipe_home, m.equipe_away, m.competition, m.date_match, m.score_home, m.score_away,
                COUNT(DISTINCT p.user_id) AS players
         FROM matches m
         INNER JOIN prediction_markets pm ON pm.match_id = m.id
         INNER JOIN predictions p ON p.market_id = pm.id
         WHERE m.statut = 'termine' AND m.date_match BETWEEN ? AND ?
         GROUP BY m.id
         ORDER BY players DESC, m.date_match DESC
         LIMIT 20"
    );
    $st->execute([$start, $end]);
    $topMatches = $st->fetchAll();

    return [
        'label'           => $labelFr,
        'label_fr'        => $labelFr,
        'start'           => $start,
        'end'             => $end,
        'matches_played'  => $matchesPlayed,
        'points_won'      => (int) ($predStats['points_won'] ?? 0),
        'points_lost'     => $pointsLost,
        'wins'            => (int) ($predStats['wins'] ?? 0),
        'losses'          => (int) ($predStats['losses'] ?? 0),
        'voided'          => (int) ($predStats['voided'] ?? 0),
        'resolved'        => (int) ($predStats['resolved'] ?? 0),
        'messages'        => $messages,
        'new_users'       => $newUsers,
        'active_users'    => $activeUsers,
        'top_users'       => $topUsers,
        'recent_logins'   => $recentLogins,
        'top_matches'     => $topMatches,
        'api_used'        => $quota['used'] ?? null,
        'api_remaining'   => $quota['remaining'] ?? null,
        'api_updated_at'  => $quota['updated_at'] ?? null,
    ];
}

/**
 * E-mail joueur : correction des pronos « données indisponibles ».
 *
 * @param array{pseudo:string,email:string} $user
 * @param list<array{
 *   match_label:string,
 *   market_type:string,
 *   pick_label:string,
 *   result_line:string,
 *   statut:string,
 *   points:int,
 *   kind:string
 * }> $lines
 */
function sendPlayerScoreCorrectionMail(array $user, array $lines): bool
{
    if (!userAllowsAppMail($user)) {
        setMailError('Joueur désinscrit des e-mails.');
        return false;
    }
    $pseudo = (string) ($user['pseudo'] ?? '');
    $email  = trim((string) ($user['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $lines === []) {
        return false;
    }

    $lang = resolveMailLang($user);

    return withLang($lang, static function () use ($lines, $pseudo, $email, $lang): bool {
        $won = 0;
        $lost = 0;
        $void = 0;
        $points = 0;
        foreach ($lines as $line) {
            $st = (string) ($line['statut'] ?? '');
            if ($st === 'correct') {
                $won++;
                $points += (int) ($line['points'] ?? 0);
            } elseif ($st === 'incorrect') {
                $lost++;
            } else {
                $void++;
            }
        }

        $en = $lang === 'en';
        $text = [
            t('mail.correction.hello', ['name' => $pseudo]),
            '',
            $en
                ? 'Some of your predictions were voided as “unavailable” because the API did not return the result in time.'
                : 'Certains de tes pronostics avaient été passés en « Annulé (données indisponibles) »',
            $en
                ? 'We entered the real scores manually — your points were recalculated. Details for you only:'
                : 'parce que l’API n’avait pas renvoyé le résultat à temps.',
            '',
        ];
        if (!$en) {
            $text[] = 'Bonne nouvelle : on a saisi les vrais scores à la main. Tes points ont été';
            $text[] = 'recalculés correctement. Voici le détail pour TOI uniquement :';
            $text[] = '';
        }

        $htmlItems = '';
        foreach ($lines as $line) {
            $st = (string) ($line['statut'] ?? '');
            if ($st === 'correct') {
                $badge = '+' . (int) $line['points'] . ' pt';
                $tone  = '#1e5035';
            } elseif ($st === 'incorrect') {
                $badge = $en ? 'Lost' : 'Raté';
                $tone  = '#6a3028';
            } elseif (($line['kind'] ?? '') === 'postponed') {
                $badge = $en ? 'Match postponed · 0 pt' : 'Match reporté · 0 pt';
                $tone  = '#7a5420';
            } else {
                $badge = $en ? 'Match cancelled · 0 pt' : 'Match annulé · 0 pt';
                $tone  = '#5c5345';
            }

            $detail = sprintf(
                '%s · %s',
                $line['match_label'],
                marketTypeLabel((string) $line['market_type'])
            );
            $pick = ($en ? 'Your pick : ' : 'Ton prono : ') . $line['pick_label'];
            $res  = ($en ? 'Result : ' : 'Résultat : ') . $line['result_line'];
            if (($line['kind'] ?? '') === 'postponed') {
                $res = $en
                    ? 'Result : match postponed (void)'
                    : 'Résultat : match reporté';
            } elseif (($line['kind'] ?? '') === 'cancelled') {
                $res = $en
                    ? 'Result : match cancelled / not played (void)'
                    : 'Résultat : match annulé / non joué';
            }

            $text[] = '· ' . $detail;
            $text[] = '  ' . $pick;
            $text[] = '  ' . $res;
            $text[] = '  → ' . $badge;
            $text[] = '';

            $htmlItems .= '<div style="margin:0 0 12px;padding:12px 14px;background:rgba(255,255,255,0.45);'
                . 'border:1px solid rgba(90,75,55,0.2);border-radius:8px;">'
                . '<p style="margin:0 0 4px;font-weight:700;">'
                . htmlspecialchars($detail, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>'
                . '<p style="margin:0 0 2px;font-size:14px;color:#5c5345;">'
                . htmlspecialchars($pick, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>'
                . '<p style="margin:0 0 6px;font-size:14px;color:#5c5345;">'
                . htmlspecialchars($res, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>'
                . '<p style="margin:0;font-weight:800;color:' . $tone . ';">'
                . htmlspecialchars($badge, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>'
                . '</div>';
        }

        $bilan = $en
            ? sprintf('Summary: %d won, %d lost, %d void — +%d point(s) credited.', $won, $lost, $void, $points)
            : sprintf('Bilan : %d gagné(s), %d raté(s), %d annulé(s) — +%d point(s) crédité(s).', $won, $lost, $void, $points);
        $text[] = $bilan;
        $text[] = '';
        $text[] = $en
            ? 'You can find everything in your Prognoz history.'
            : 'Tu peux retrouver tout ça dans ton historique sur Prognoz.';
        $text[] = '';
        $text[] = '— ' . APP_NAME;

        $summaryHtml = $en
            ? ('<p style="margin:0 0 14px;">Some of your predictions were marked '
                . '<strong>“unavailable”</strong> because the API result arrived too late.</p>'
                . '<p style="margin:0 0 16px;">We entered the <strong>real scores</strong> manually — '
                . 'your points were recalculated. Here are <strong>your</strong> updated matches:</p>')
            : ('<p style="margin:0 0 14px;">Certains de tes pronostics avaient été passés en '
                . '<strong>« Annulé (données indisponibles) »</strong> faute de résultat API à temps.</p>'
                . '<p style="margin:0 0 16px;">On a saisi les <strong>vrais scores</strong> à la main : '
                . 'tes points ont été recalculés. Voici <strong>tes</strong> matchs corrigés :</p>');
        $summaryHtml .= $htmlItems
            . '<p style="margin:8px 0 0;font-weight:700;">' . htmlspecialchars($bilan, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>';

        $subject = APP_NAME . ' — ' . t('mail.correction.subject') . ' (+' . $points . ' pt)';
        $html = renderAppMailHtml([
            'lang'        => $lang,
            'title'       => t('mail.correction.subject'),
            'preheader'   => sprintf('%d · +%d pt', count($lines), $points),
            'greeting'    => t('mail.correction.hello', ['name' => $pseudo]),
            'body_html'   => $summaryHtml,
            'cta_url'     => absoluteUrl('account/dashboard.php'),
            'cta_label'   => $en ? 'Open my history' : 'Voir mon historique',
            'footer_note' => ($en ? 'Manual result correction · ' : 'Correction manuelle des résultats · ') . APP_NAME,
        ]);

        return sendAppMail($email, $subject, implode("\n", $text), $html);
    });
}

/**
 * Charge les pronos d’un joueur sur une liste de matchs (après correction).
 *
 * @param list<int> $matchIds
 * @param list<int> $predictionIds
 * @return list<array<string,mixed>>
 */
function fetchUserCorrectionLines(PDO $pdo, int $userId, array $matchIds, array $predictionIds = []): array
{
    if ($matchIds === [] && $predictionIds === []) {
        return [];
    }

    ensurePredictionHistorySchema($pdo);
    ensureMatchCancelReasonSchema($pdo);
    $params = [$userId];
    $where  = ['p.user_id = ?'];

    if ($predictionIds !== []) {
        $ids = array_values(array_unique(array_map('intval', $predictionIds)));
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $where[] = "p.id IN ($ph)";
        $params = array_merge($params, $ids);
    } else {
        $ids = array_values(array_unique(array_map('intval', $matchIds)));
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $where[] = "m.id IN ($ph)";
        $params = array_merge($params, $ids);
    }

    $sql = 'SELECT p.id, p.reponse, p.statut, p.points_gagnes,
                   pm.type AS market_type,
                   m.id AS match_id, m.equipe_home, m.equipe_away, m.statut AS match_statut,
                   m.score_home, m.score_away, m.resultat_1x2, m.date_match, m.annulation_raison
            FROM predictions p
            INNER JOIN prediction_markets pm ON pm.id = p.market_id
            INNER JOIN matches m ON m.id = pm.match_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY m.date_match ASC, pm.type ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $row) {
        $matchStatut = (string) ($row['match_statut'] ?? '');
        if ($matchStatut === 'reporte') {
            $kind = 'postponed';
        } elseif ($matchStatut === 'annule' || ($row['statut'] ?? '') === 'annule') {
            $kind = 'cancelled';
        } else {
            $kind = 'scored';
        }
        $out[] = [
            'match_label'  => trim(($row['equipe_home'] ?? '') . ' – ' . ($row['equipe_away'] ?? '')),
            'market_type'  => (string) ($row['market_type'] ?? ''),
            'pick_label'   => formatPickLabel($row, (string) $row['reponse']),
            'result_line'  => formatMatchResultLine($row),
            'statut'       => (string) ($row['statut'] ?? ''),
            'points'       => (int) ($row['points_gagnes'] ?? 0),
            'kind'         => $kind,
            'match_id'     => (int) ($row['match_id'] ?? 0),
            'prediction_id'=> (int) ($row['id'] ?? 0),
        ];
    }

    return $out;
}

/** Envoie le rapport mensuel complet à l’admin. */
function sendMonthlySiteReportMail(PDO $pdo): bool
{
    $r = collectMonthlySiteReport($pdo);

    $text = [
        'Rapport mensuel Prognoz — ' . $r['label_fr'],
        'Période : ' . $r['start'] . ' → ' . $r['end'],
        '',
        '=== Synthèse ===',
        'Matchs terminés : ' . $r['matches_played'],
        'Pronos résolus : ' . $r['resolved'] . ' (gagnés ' . $r['wins'] . ' / ratés ' . $r['losses'] . ' / annulés ' . $r['voided'] . ')',
        'Points distribués : ' . $r['points_won'],
        'Points manqués (ratés) : ' . $r['points_lost'],
        'Messages communautés : ' . $r['messages'],
        'Nouveaux joueurs : ' . $r['new_users'],
        'Joueurs actifs (total) : ' . $r['active_users'],
        'Crédits API utilisés (relevé) : ' . ($r['api_used'] !== null ? (string) $r['api_used'] : 'n/d'),
        'Crédits API restants : ' . ($r['api_remaining'] !== null ? (string) $r['api_remaining'] : 'n/d'),
        '',
    ];

    $html = [
        '<p style="margin:0 0 14px;">Période <strong>'
        . htmlspecialchars($r['label_fr'], ENT_QUOTES | ENT_HTML5, 'UTF-8')
        . '</strong> ('
        . htmlspecialchars(substr($r['start'], 0, 10) . ' → ' . substr($r['end'], 0, 10), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        . ').</p>',
        '<h3 style="margin:0 0 8px;font-size:15px;">Synthèse</h3>',
        '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 18px;font-size:14px;">',
        rowReportStat('Matchs terminés', (string) $r['matches_played']),
        rowReportStat('Pronos résolus', (string) $r['resolved']),
        rowReportStat('Pronos gagnés', (string) $r['wins']),
        rowReportStat('Pronos ratés', (string) $r['losses']),
        rowReportStat('Pronos annulés', (string) $r['voided']),
        rowReportStat('Points distribués', (string) $r['points_won']),
        rowReportStat('Points manqués (ratés)', (string) $r['points_lost']),
        rowReportStat('Messages communautés', (string) $r['messages']),
        rowReportStat('Nouveaux joueurs', (string) $r['new_users']),
        rowReportStat('Joueurs actifs (total)', (string) $r['active_users']),
        rowReportStat(
            'Crédits API utilisés',
            $r['api_used'] !== null ? (string) $r['api_used'] : 'n/d'
        ),
        rowReportStat(
            'Crédits API restants',
            $r['api_remaining'] !== null ? (string) $r['api_remaining'] : 'n/d'
        ),
        '</table>',
    ];

    $text[] = '=== Joueurs les plus actifs (pronos + messages) ===';
    $html[] = '<h3 style="margin:0 0 8px;font-size:15px;">Joueurs les plus actifs</h3>';
    if ($r['top_users'] === []) {
        $text[] = '(aucun)';
        $html[] = '<p style="margin:0 0 14px;color:#5c5345;">Aucune activité ce mois.</p>';
    } else {
        $html[] = '<ol style="margin:0 0 16px;padding-left:20px;font-size:14px;">';
        foreach ($r['top_users'] as $u) {
            $seen = !empty($u['last_seen_at']) ? formatMatchWhen((string) $u['last_seen_at']) : 'jamais';
            $line = sprintf(
                '%s — %d prono(s), %d msg, dernière vue %s, %d pts',
                $u['pseudo'],
                (int) $u['preds'],
                (int) $u['msgs'],
                $seen,
                (int) $u['points_totaux']
            );
            $text[] = ' · ' . $line;
            $html[] = '<li style="margin:0 0 4px;">'
                . htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</li>';
        }
        $html[] = '</ol>';
        $text[] = '';
    }

    $text[] = '=== Dernières connexions / vues (ce mois) ===';
    $html[] = '<h3 style="margin:0 0 8px;font-size:15px;">Connexions les plus récentes</h3>';
    if ($r['recent_logins'] === []) {
        $text[] = '(aucune — le suivi last_seen démarre après déploiement)';
        $html[] = '<p style="margin:0 0 14px;color:#5c5345;">Pas encore de données de connexion ce mois.</p>';
    } else {
        $html[] = '<ol style="margin:0 0 16px;padding-left:20px;font-size:14px;">';
        foreach ($r['recent_logins'] as $u) {
            $line = sprintf(
                '%s — %s',
                $u['pseudo'],
                formatMatchWhen((string) $u['last_seen_at'])
            );
            $text[] = ' · ' . $line;
            $html[] = '<li style="margin:0 0 4px;">'
                . htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</li>';
        }
        $html[] = '</ol>';
        $text[] = '';
    }

    $text[] = '=== Matchs les plus pronostiqués ===';
    $html[] = '<h3 style="margin:0 0 8px;font-size:15px;">Matchs les plus pronostiqués</h3>';
    if ($r['top_matches'] === []) {
        $text[] = '(aucun)';
        $html[] = '<p style="margin:0;color:#5c5345;">Aucun match terminé ce mois.</p>';
    } else {
        $html[] = '<ul style="margin:0;padding-left:18px;font-size:14px;">';
        foreach ($r['top_matches'] as $m) {
            $score = ($m['score_home'] !== null && $m['score_away'] !== null)
                ? ((int) $m['score_home'] . '–' . (int) $m['score_away'])
                : '—';
            $line = sprintf(
                '%s – %s (%s) · %s · %d joueur(s)',
                $m['equipe_home'],
                $m['equipe_away'],
                $score,
                formatMatchWhen((string) $m['date_match']),
                (int) $m['players']
            );
            $text[] = ' · ' . $line;
            $html[] = '<li style="margin:0 0 4px;">'
                . htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</li>';
        }
        $html[] = '</ul>';
    }

    $subject = '[Prognoz] Rapport du mois — ' . $r['label_fr'];
    $mailHtml = renderAppMailHtml([
        'title'       => 'Rapport du mois',
        'preheader'   => 'Synthèse ' . $r['label_fr'] . ' · ' . $r['matches_played'] . ' matchs',
        'greeting'    => 'Rapport mensuel',
        'body_html'   => implode('', $html),
        'cta_url'     => absoluteUrl('admin/dashboard.php'),
        'cta_label'   => 'Ouvrir le panel admin',
        'footer_note' => 'Rapport mensuel · ' . adminNotifyEmail(),
    ]);

    return sendAppMail(adminNotifyEmail(), $subject, implode("\n", $text), $mailHtml);
}

/**
 * Snapshot rétention / boucle de feedback (admin).
 * S’appuie sur last_seen_at + predictions (pas de table d’analytics dédiée).
 *
 * @return array{
 *   seen_24h:int,seen_7d:int,pickers_7d:int,picks_today:int,picks_7d:int,
 *   regulars_count:int,returned_after_match:int,had_finished_pick:int,
 *   return_rate_pct:?float,regulars:list<array<string,mixed>>,
 *   recent_active:list<array<string,mixed>>
 * }
 */
function collectRetentionSnapshot(PDO $pdo): array
{
    if (function_exists('ensureUserLastSeenSchema')) {
        ensureUserLastSeenSchema($pdo);
    }

    $seen24 = (int) $pdo->query(
        "SELECT COUNT(*) FROM users
         WHERE actif = 1
           AND last_seen_at IS NOT NULL
           AND last_seen_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)"
    )->fetchColumn();

    $seen7 = (int) $pdo->query(
        "SELECT COUNT(*) FROM users
         WHERE actif = 1
           AND last_seen_at IS NOT NULL
           AND last_seen_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)"
    )->fetchColumn();

    $pickers7 = (int) $pdo->query(
        "SELECT COUNT(DISTINCT user_id) FROM predictions
         WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)"
    )->fetchColumn();

    $picksToday = (int) $pdo->query(
        "SELECT COUNT(*) FROM predictions
         WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)"
    )->fetchColumn();

    $picks7 = (int) $pdo->query(
        "SELECT COUNT(*) FROM predictions
         WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)"
    )->fetchColumn();

    $regulars = $pdo->query(
        "SELECT u.id, u.pseudo, u.last_seen_at, u.points_totaux, u.serie_en_cours,
                COUNT(p.id) AS picks_14d,
                COUNT(DISTINCT DATE(p.created_at)) AS days_with_picks
         FROM users u
         INNER JOIN predictions p
           ON p.user_id = u.id
          AND p.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 14 DAY)
         WHERE u.actif = 1
           AND u.last_seen_at IS NOT NULL
           AND u.last_seen_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY)
         GROUP BY u.id, u.pseudo, u.last_seen_at, u.points_totaux, u.serie_en_cours
         HAVING COUNT(DISTINCT DATE(p.created_at)) >= 2
         ORDER BY days_with_picks DESC, picks_14d DESC, u.last_seen_at DESC
         LIMIT 25"
    )->fetchAll() ?: [];

    $hadFinished = (int) $pdo->query(
        "SELECT COUNT(DISTINCT p.user_id)
         FROM predictions p
         INNER JOIN prediction_markets pm ON pm.id = p.market_id
         INNER JOIN matches m ON m.id = pm.match_id
         INNER JOIN users u ON u.id = p.user_id AND u.actif = 1
         WHERE m.statut = 'termine'
           AND m.date_match >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 14 DAY)
           AND m.date_match < UTC_TIMESTAMP()"
    )->fetchColumn();

    $returned = (int) $pdo->query(
        "SELECT COUNT(DISTINCT p.user_id)
         FROM predictions p
         INNER JOIN prediction_markets pm ON pm.id = p.market_id
         INNER JOIN matches m ON m.id = pm.match_id
         INNER JOIN users u ON u.id = p.user_id AND u.actif = 1
         WHERE m.statut = 'termine'
           AND m.date_match >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 14 DAY)
           AND m.date_match < UTC_TIMESTAMP()
           AND u.last_seen_at IS NOT NULL
           AND u.last_seen_at >= DATE_ADD(m.date_match, INTERVAL 2 HOUR)"
    )->fetchColumn();

    $returnPct = $hadFinished > 0
        ? round(100.0 * $returned / $hadFinished, 1)
        : null;

    $recentActive = $pdo->query(
        "SELECT u.id, u.pseudo, u.last_seen_at, u.points_totaux, u.serie_en_cours,
                (
                    SELECT COUNT(*) FROM predictions p
                    WHERE p.user_id = u.id
                      AND p.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
                ) AS picks_7d
         FROM users u
         WHERE u.actif = 1
           AND u.last_seen_at IS NOT NULL
           AND u.last_seen_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
         ORDER BY u.last_seen_at DESC
         LIMIT 30"
    )->fetchAll() ?: [];

    return [
        'seen_24h'             => $seen24,
        'seen_7d'              => $seen7,
        'pickers_7d'           => $pickers7,
        'picks_today'          => $picksToday,
        'picks_7d'             => $picks7,
        'regulars_count'       => count($regulars),
        'returned_after_match' => $returned,
        'had_finished_pick'    => $hadFinished,
        'return_rate_pct'      => $returnPct,
        'regulars'             => $regulars,
        'recent_active'        => $recentActive,
    ];
}
