<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

function ibmiRenderScreen(PDO $pdo, string $scr): void
{
    $ctx = $_GET;
    $flash = adminTakeFlash();
    if ($flash) {
        $ctx['msg'] = (string) $flash['message'];
        $ctx['msgType'] = (string) $flash['type'];
    }
    try {
        $t = ibmiBuildScreen($pdo, $scr, $ctx);
    } catch (Throwable $e) {
        error_log('ibmi ' . $scr . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        $t = ibmiT();
        ibmiTHeader($t, 'Exception', strtoupper($scr));
        ibmiTPut($t, 3, 2, 'CPF0001', 'r');
        ibmiTPut($t, 5, 2, ibmiTClip($e->getMessage(), 76), 'y');
        ibmiTPut($t, 7, 2, 'F3=Exit   F12=Cancel', 'd');
        ibmiTCmd($t);
        ibmiTFkeys($t, ['F3=Exit', 'F5=Refresh', 'F12=Cancel']);
    }
    ibmiEmitHtml($t, false);
}

function ibmiRenderSignon(string $error = ''): void
{
    ibmiEmitHtml(ibmiPaintSignon($error), true);
}

function ibmiPaintSignon(string $error = ''): array
{
    $t = ibmiT();
    ibmiTHeader($t, 'Sign On', 'SIGNON');
    ibmiTPut($t, 2, 8, 'System  . . . . . . . . . . :', 'g');
    ibmiTPut($t, 2, 40, 'PROGNOZ', 'w');
    ibmiTPut($t, 3, 8, 'Subsystem . . . . . . . . . :', 'g');
    ibmiTPut($t, 3, 40, 'QINTER', 'w');
    ibmiTPut($t, 4, 8, 'Display . . . . . . . . . . :', 'g');
    ibmiTPut($t, 4, 40, 'DSP01', 'w');
    ibmiTPut($t, 7, 8, 'User  . . . . . . . . . . . .', 'g');
    ibmiTField($t, 7, 40, 'username', 10, (string) ($_POST['username'] ?? ''), 'text', ['id' => 'username', 'required' => true]);
    ibmiTPut($t, 8, 8, 'Password  . . . . . . . . . .', 'g');
    ibmiTField($t, 8, 40, 'password', 10, '', 'password', ['required' => true]);
    ibmiTPut($t, 10, 8, 'Program/procedure . . . . . .', 'g');
    ibmiTPut($t, 10, 40, str_repeat('_', 10), 'w');
    ibmiTPut($t, 11, 8, 'Menu  . . . . . . . . . . . .', 'g');
    ibmiTPut($t, 11, 40, str_repeat('_', 10), 'w');
    ibmiTPut($t, 12, 8, 'Current library . . . . . . .', 'g');
    ibmiTPut($t, 12, 40, str_repeat('_', 10), 'w');
    if ($error !== '') {
        ibmiTPut($t, 16, 8, ibmiTClip($error, 64), 'r');
    }
    ibmiTPut($t, 20, 8, '(C) COPYRIGHT IBM CORP. 1980, 2005.', 'd');
    ibmiTPut($t, 21, 8, '(C) PROGNOZ 2024, 2026.', 'd');
    ibmiTFkeys($t, ['Enter=Sign on', 'F3=Exit']);

    return $t;
}

function ibmiPaintSignoff(): array
{
    $t = ibmiT();
    ibmiTHeader($t, 'Sign Off', 'SIGNOFF');
    ibmiTPut($t, 8, 18, 'Job ended normally.', 'g');
    ibmiTPut($t, 10, 18, 'Press Enter to sign on again.', 'd');

    return $t;
}

/**
 * @param array<string,mixed> $ctx
 * @return array<string,mixed>
 */
function ibmiBuildScreen(PDO $pdo, string $scr, array $ctx): array
{
    $scr = strtoupper($scr);
    $t = match ($scr) {
        'MAIN' => ibmiPaintMain($ctx),
        'DSPSTS' => ibmiPaintDspsts($pdo, $ctx),
        'WRKRPT' => ibmiPaintWrkrpt($ctx),
        'WRKANN' => ibmiPaintWrkann($pdo, $ctx),
        'WRKSCR' => ibmiPaintWrkscr($pdo, $ctx),
        'DSPMCH' => ibmiPaintDspmch($pdo, $ctx),
        'WRKOPS' => ibmiPaintWrkops($pdo, $ctx),
        'WRKMSG' => ibmiPaintWrkmsg($pdo, $ctx),
        'WRKUSR' => ibmiPaintWrkusr($pdo, $ctx),
        'DSPUSR' => ibmiPaintDspusr($pdo, $ctx),
        'WRKSEA' => ibmiPaintWrksea($pdo, $ctx),
        'WRKEVT' => ibmiPaintWrkevt($pdo, $ctx),
        'CONFIRM' => ibmiPaintConfirm($ctx),
        default => ibmiPaintMain($ctx),
    };
    if (!empty($ctx['msg'])) {
        ibmiTMsg($t, (string) $ctx['msg'], (string) ($ctx['msgType'] ?? ''));
    }
    ibmiTCmd($t);

    return $t;
}

/** @param array<string,mixed> $ctx */
function ibmiPaintMain(array $ctx): array
{
    $t = ibmiT();
    ibmiTHeader($t, 'MAIN', 'MAIN');
    ibmiTPut($t, 2, 2, 'Select one of the following:', 'g');
    $items = [
        [1, 'DSPSTS', 'Display system status'],
        [2, 'WRKRPT', 'Work with e-mail reports'],
        [3, 'WRKANN', 'Work with announcements'],
        [4, 'WRKSCR', 'Work with scores / results'],
        [5, 'WRKOPS', 'Work with API / sync'],
        [6, 'WRKMSG', 'Work with chat messages'],
        [7, 'WRKUSR', 'Work with user profiles'],
        [8, 'WRKSEA', 'Work with seasons'],
        [9, 'WRKEVT', 'Work with events'],
        [90, 'SIGNOFF', 'Sign off'],
    ];
    $row = 4;
    foreach ($items as $it) {
        ibmiTPut($t, $row, 6, sprintf('%2d. %s', $it[0], $it[2]), $it[0] === 90 ? 'd' : 'g');
        $row++;
    }
    ibmiTPut($t, 22, 2, 'Selection or command', 'g');
    ibmiTFkeys($t, ['F3=Exit', 'F5=Refresh', 'F12=Cancel']);

    return $t;
}

/** @param array<string,mixed> $ctx */
function ibmiPaintDspsts(PDO $pdo, array $ctx): array
{
    $d = adminQueryDashboard($pdo);
    $t = ibmiT();
    ibmiTHeader($t, 'Display System Status', 'DSPSTS');
    $lines = [
        ['Active users', $d['users_active'] . ' / ' . $d['users_all']],
        ['Missing API scores', (string) $d['stuck_count']],
        ['Unavailable data', (string) $d['voided_count']],
        ['Points pending', (string) $d['local_count']],
        ['Postponed matches', (string) $d['postponed_count']],
        ['Upcoming matches', (string) $d['matches_upcoming']],
        ['Pending predictions', (string) ((int) $d['pending']['pending'])],
        ['Stuck predictions', (string) ((int) $d['pending']['stuck'])],
        ['Communities / msgs', $d['communities'] . ' / ' . $d['messages']],
        ['API credits left', $d['quota']['remaining'] !== null ? (string) (int) $d['quota']['remaining'] : '?'],
        ['Notify e-mail', (string) $d['notify_email']],
    ];
    $r = 2;
    foreach ($lines as $ln) {
        ibmiTPut($t, $r, 2, str_pad($ln[0] . ' ', 24, '.') . ' :', 'g');
        ibmiTPut($t, $r, 28, (string) $ln[1], 'w');
        $r++;
    }
    if (!empty($d['season'])) {
        ibmiTPut($t, $r, 2, 'Active season  . . . . :', 'g');
        ibmiTPut($t, $r, 28, '#' . (int) $d['season']['id'] . ' → ' . formatSeasonFin($d['season']['fin'] ?? ''), 'h');
        $r += 2;
    }
    ibmiTPut($t, $r, 2, 'Matches without score:', 't');
    $r++;
    foreach (array_slice($d['need_score'], 0, 5) as $m) {
        ibmiTPut($t, $r, 4, ibmiTClip('#' . $m['id'] . '  ' . $m['equipe_home'] . ' - ' . $m['equipe_away'], 74), 'g');
        $r++;
    }
    ibmiTFkeys($t, ['F3=Exit', 'F5=Refresh', 'F12=Cancel']);

    return $t;
}

/** @param array<string,mixed> $ctx */
function ibmiPaintWrkusr(PDO $pdo, array $ctx): array
{
    $q = trim((string) ($ctx['q'] ?? ''));
    $position = trim((string) ($ctx['position'] ?? ''));
    $page = max(1, (int) ($ctx['page'] ?? 1));
    $queryErr = '';
    try {
        $data = adminQueryUsers($pdo, $q, $position, $page, 12);
    } catch (Throwable $e) {
        error_log('WRKUSR: ' . $e->getMessage());
        $data = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 12];
        $queryErr = $e->getMessage();
    }
    $t = ibmiT();
    ibmiTHeader($t, 'Work with User Profiles', 'WRKUSR');
    if ($queryErr !== '') {
        ibmiTMsg($t, 'CPF0001 ' . $queryErr, 'error');
    }
    ibmiTPut($t, 2, 1, 'Type options, press Enter.  (Opt column  OR  ===> 5 123)', 'g');
    ibmiTPut($t, 3, 1, '2=Points  4=Active  5=Display  7=Mail  8=Password  9=Photo', 'd');
    ibmiTPut($t, 4, 1, 'Position to  . . .', 'g');
    ibmiTField($t, 4, 22, 'position', 12, $position);
    ibmiTPut($t, 4, 36, 'Search . .', 'g');
    ibmiTField($t, 4, 48, 'q', 20, $q);
    ibmiTPut($t, 5, 1, 'Delta  . . . . . .', 'g');
    ibmiTField($t, 5, 22, 'delta', 6, '10');
    ibmiTPut($t, 5, 30, 'Season (Y/N)', 'g');
    ibmiTField($t, 5, 44, 'to_season', 1, 'Y');
    ibmiTPut($t, 5, 47, 'New pwd', 'g');
    ibmiTField($t, 5, 56, 'new_password', 12, '', 'password');
    ibmiTPut($t, 6, 1, sprintf('Page %d/%d  (%d records)   PageUp/PageDown', $data['page'], $data['pages'], $data['total']), 'd');
    ibmiTPut($t, 7, 1, 'Opt  Id    User            Seen       Pts   Sts Bio', 't');
    $r = 8;
    foreach ($data['rows'] as $u) {
        if ($r > 22) {
            break;
        }
        $id = (int) $u['id'];
        ibmiTField($t, $r, 1, 'opt[' . $id . ']', 2, '', 'text', ['opt' => true]);
        $sts = !empty($u['actif']) ? 'ACT' : 'OFF';
        $line = sprintf(
            '  %-5d %-15s %-10s %5d %s %s',
            $id,
            ibmiTClip((string) $u['pseudo'], 15),
            adminFmtShortWhen($u['last_seen_at'] ?? null),
            (int) $u['points_totaux'],
            $sts,
            ibmiTClip((string) ($u['bio'] ?? ''), 18)
        );
        ibmiTPut($t, $r, 4, $line, !empty($u['actif']) ? 'g' : 'r');
        $r++;
    }
    if ($data['rows'] === []) {
        ibmiTPut($t, 8, 4, '(no users)', 'd');
    }
    ibmiTFkeys($t, ['Enter=Confirm', 'F3=Exit', 'F5=Refresh', 'F12=Cancel']);

    return $t;
}

/** @param array<string,mixed> $ctx */
function ibmiPaintDspusr(PDO $pdo, array $ctx): array
{
    $id = (int) ($ctx['id'] ?? 0);
    try {
        $d = adminQueryUserDossier($pdo, $id);
    } catch (Throwable $e) {
        error_log('DSPUSR: ' . $e->getMessage());
        $d = null;
        $t = ibmiT();
        ibmiTHeader($t, 'Display User Profile', 'DSPUSR');
        ibmiTPut($t, 4, 2, 'CPF0001', 'r');
        ibmiTPut($t, 6, 2, ibmiTClip($e->getMessage(), 76), 'y');
        ibmiTFkeys($t, ['F3=Exit', 'F12=Cancel']);

        return $t;
    }
    $t = ibmiT();
    ibmiTHeader($t, 'Display User Profile', 'DSPUSR');
    if (!$d) {
        ibmiTPut($t, 4, 2, 'User not found.', 'r');
        ibmiTFkeys($t, ['F3=Exit', 'F12=Cancel']);

        return $t;
    }
    $u = $d['user'];
    $actif = !empty($u['actif']);
    ibmiTPut($t, 2, 1, 'User  . . . . . . . . :', 'g');
    ibmiTPut($t, 2, 26, '#' . (int) $u['id'] . '  ' . (string) $u['pseudo'] . ($actif ? '' : '  *INACTIVE'), $actif ? 'w' : 'r');
    ibmiTPut($t, 3, 1, 'E-mail  . . . . . . . :', 'g');
    ibmiTPut($t, 3, 26, ibmiTClip((string) $u['email'], 50), 'w');
    ibmiTPut($t, 4, 1, 'Bio . . . . . . . . . :', 'g');
    ibmiTPut($t, 4, 26, ibmiTClip((string) ($u['bio'] ?? '') ?: '*NONE', 52), 'g');
    ibmiTPut($t, 5, 1, 'Favorite sport  . . . :', 'g');
    ibmiTPut($t, 5, 26, userFavoriteSportLabel($u['sport_favori'] ?? null) ?: '*NONE', 'g');
    ibmiTPut($t, 6, 1, 'Created / last seen . :', 'g');
    ibmiTPut($t, 6, 26, adminFmtWhen($u['created_at'] ?? null) . '  /  ' . adminFmtWhen($u['last_seen_at'] ?? null), 'h');
    ibmiTPut($t, 7, 1, 'Points / streak / szn :', 'g');
    ibmiTPut($t, 7, 26, (int) $u['points_totaux'] . '  /  ' . (int) ($u['serie_en_cours'] ?? 0) . '  /  ' . (int) $d['season_pts'], 'w');
    $mailOff = !empty($u['mail_opt_out']);
    ibmiTPut($t, 8, 1, 'Mail / photo / friends:', 'g');
    ibmiTPut($t, 8, 26, ($mailOff ? 'OPT-OUT' : 'YES') . '  /  ' . ($d['has_avatar'] ? 'YES' : 'NO') . '  /  ' . (int) $d['friends'], $mailOff ? 'r' : 'g');
    $ps = $d['pred_stats'];
    ibmiTPut($t, 9, 1, 'Predictions . . . . . :', 'g');
    ibmiTPut($t, 9, 26, $ps['total'] . '  W=' . $ps['wins'] . ' L=' . $ps['losses'] . '  ' . $ps['rate'] . '%', 'g');
    ibmiTPut($t, 11, 1, 'Communities', 't');
    $r = 12;
    foreach (array_slice($d['communities'], 0, 3) as $c) {
        ibmiTPut($t, $r, 3, ibmiTClip('#' . $c['id'] . '  ' . $c['nom'] . ' [' . $c['role'] . ']', 74), 'g');
        $r++;
    }
    ibmiTPut($t, $r, 1, 'Recent predictions', 't');
    $r++;
    foreach (array_slice($d['history'], 0, 4) as $h) {
        ibmiTPut($t, $r, 3, ibmiTClip(($h['equipe_home'] ?? '') . '-' . ($h['equipe_away'] ?? '') . '  ' . $h['reponse'] . '  ' . $h['statut'], 74), 'g');
        $r++;
    }
    ibmiTPut($t, 21, 1, 'Delta', 'g');
    ibmiTField($t, 21, 8, 'delta', 6, '10');
    ibmiTPut($t, 21, 16, 'Szn', 'g');
    ibmiTField($t, 21, 20, 'to_season', 1, 'Y');
    ibmiTPut($t, 21, 23, 'Pwd', 'g');
    ibmiTField($t, 21, 28, 'new_password', 12, '', 'password');
    ibmiTPut($t, 22, 1, 'Action', 'g');
    ibmiTField($t, 22, 9, 'dsp_action', 14, '');
    ibmiTPut($t, 22, 25, '(points/toggle_active/toggle_mail/reset_password/remove_avatar)', 'd');
    ibmiTPut($t, 23, 1, 'user_id', 'd');
    ibmiTField($t, 23, 10, 'user_id', 8, (string) (int) $u['id']);
    ibmiTFkeys($t, ['F3=Exit', 'F5=Refresh', 'F6=Points', 'F12=Cancel']);

    return $t;
}

/** @param array<string,mixed> $ctx */
function ibmiPaintWrkscr(PDO $pdo, array $ctx): array
{
    $panel = (string) ($ctx['panel'] ?? 'file');
    if (!in_array($panel, ['file', 'saisie', 'reportes', 'points'], true)) {
        $panel = 'file';
    }
    $home = trim((string) ($ctx['team_home'] ?? ''));
    $away = trim((string) ($ctx['team_away'] ?? ''));
    $sport = (string) ($ctx['sport'] ?? '');
    $data = adminQueryScores($pdo, $home, $away, $sport);
    $sum = $data['stuck_summary'];
    $t = ibmiT();
    ibmiTHeader($t, 'Work with Scores', 'WRKSCR');
    ibmiTPut($t, 2, 1, 'Panel ' . strtoupper($panel) . '   stuck=' . (int) $sum['total']
        . ' API=' . (int) $sum['api_window'] . ' void=' . count($data['voided'])
        . ' postp=' . count($data['postponed']), 'g');
    ibmiTPut($t, 3, 1, 'Type options, press Enter.  4=Cancel  5=Preds  6=Postpone  7=Reactivate  8=Date  9=Clear', 'd');
    ibmiTPut($t, 4, 1, 'Cancel reason', 'g');
    ibmiTField($t, 4, 16, 'cancel_reason', 14, '');
    ibmiTPut($t, 4, 32, 'New date', 'g');
    ibmiTField($t, 4, 42, 'new_date', 16, '');
    if ($panel === 'saisie') {
        ibmiTPut($t, 5, 1, 'Sport', 'g');
        ibmiTField($t, 5, 8, 'sport', 10, $sport);
        ibmiTPut($t, 5, 20, 'Team1', 'g');
        ibmiTField($t, 5, 27, 'team_home', 14, $home);
        ibmiTPut($t, 5, 43, 'Team2', 'g');
        ibmiTField($t, 5, 50, 'team_away', 14, $away);
    } elseif ($panel === 'reportes') {
        ibmiTPut($t, 5, 1, 'scr_do', 'g');
        ibmiTField($t, 5, 9, 'scr_do', 22, '');
        ibmiTPut($t, 5, 33, '(recover_postponed / dismiss_empty / reactivate_future)', 'd');
    } else {
        ibmiTPut($t, 5, 1, 'Type scores in H/A columns, then Enter.  F6=File F7=Search F8=Postp F9=Pts', 'd');
    }
    $rows = match ($panel) {
        'saisie' => $data['search'],
        'reportes' => $data['postponed'],
        'points' => $data['need_points'],
        default => array_merge($data['voided'], $data['need_score']),
    };
    ibmiTPut($t, 6, 1, 'Opt  Id    Match                              When      H  A  N', 't');
    $r = 7;
    foreach (array_slice($rows, 0, 15) as $m) {
        $id = (int) $m['id'];
        ibmiTField($t, $r, 1, 'opt[' . $id . ']', 2, '', 'text', ['opt' => true]);
        $label = ibmiTClip($m['equipe_home'] . '-' . $m['equipe_away'], 32);
        ibmiTPut($t, $r, 4, sprintf('  %-5d %-32s %-9s', $id, $label, adminFmtShortWhen($m['date_match'] ?? '')), 'g');
        ibmiTField($t, $r, 56, 'score_home[' . $id . ']', 3, '');
        ibmiTField($t, $r, 60, 'score_away[' . $id . ']', 3, '');
        $n = (int) ($m['pending_count'] ?? $m['voided_count'] ?? 0);
        ibmiTPut($t, $r, 65, (string) $n, 'd');
        $r++;
    }
    ibmiTFkeys($t, ['Enter=Confirm', 'F3=Exit', 'F5=Refresh', 'F6=File', 'F7=Search', 'F8=Postp', 'F9=Pts', 'F10=Catchup', 'F11=Local']);

    return $t;
}

/** @param array<string,mixed> $ctx */
function ibmiPaintDspmch(PDO $pdo, array $ctx): array
{
    $id = (int) ($ctx['id'] ?? 0);
    $preds = fetchAdminMatchPredictions($pdo, [$id], ['en_attente', 'annule', 'correct', 'incorrect'])[$id] ?? [];
    $t = ibmiT();
    ibmiTHeader($t, 'Display Match Predictions', 'DSPMCH');
    ibmiTPut($t, 2, 1, 'Match  . . . . . . . :', 'g');
    ibmiTPut($t, 2, 26, '#' . $id, 'w');
    ibmiTPut($t, 4, 1, 'User            Market       Pick        Status', 't');
    $r = 5;
    foreach (array_slice($preds, 0, 16) as $p) {
        ibmiTPut($t, $r, 1, sprintf(
            '%-15s %-12s %-11s %s',
            ibmiTClip((string) $p['pseudo'], 15),
            ibmiTClip((string) $p['market_type'], 12),
            ibmiTClip((string) $p['reponse'], 11),
            (string) $p['statut']
        ), 'g');
        $r++;
    }
    if ($preds === []) {
        ibmiTPut($t, 5, 1, '(no predictions)', 'd');
    }
    ibmiTFkeys($t, ['F3=Exit', 'F12=Cancel']);

    return $t;
}

/** @param array<string,mixed> $ctx */
function ibmiPaintWrkops(PDO $pdo, array $ctx): array
{
    $d = adminQueryOps($pdo);
    $q = $d['quota'];
    $s = $d['stuck'];
    $p = $d['prune'];
    $t = ibmiT();
    ibmiTHeader($t, 'Work with Ops / API', 'WRKOPS');
    ibmiTPut($t, 2, 1, 'Credits remaining . . :', 'g');
    ibmiTPut($t, 2, 26, $q['remaining'] !== null ? (string) (int) $q['remaining'] : '?', 'w');
    ibmiTPut($t, 3, 1, 'Used / last probe . . :', 'g');
    ibmiTPut($t, 3, 26, (isset($q['used']) ? (string) (int) $q['used'] : '?')
        . (!empty($q['updated_at']) ? '   ' . date('d/m H:i', (int) $q['updated_at']) : ''), 'g');
    ibmiTPut($t, 5, 1, 'Score queue  . . . . :', 'g');
    ibmiTPut($t, 5, 26, 'total=' . (int) $s['total'] . '  API=' . (int) $s['api_window'] . '  old=' . (int) $s['too_old'], 'g');
    ibmiTPut($t, 6, 1, 'Purge pending  . . . :', 'g');
    ibmiTPut($t, 6, 26, (string) (int) $d['purge_total'], 'g');
    ibmiTPut($t, 8, 1, 'Alternate API key . . :', 'g');
    ibmiTField($t, 8, 26, 'alt_key', 28, '');
    ibmiTPut($t, 10, 1, 'Action (ops_do) . . . :', 'g');
    ibmiTField($t, 10, 26, 'ops_do', 16, '');
    ibmiTPut($t, 12, 1, 'probe_quota  score_local  matches  odds  cron', 'd');
    ibmiTPut($t, 13, 1, 'catchup_scores  clear_lock  prune', 'd');
    ibmiTPut($t, 15, 1, 'F4=Quota  F6=Local pts  F7=Import  F8=Odds  F9=Cron', 'd');
    ibmiTPut($t, 16, 1, 'F10=Catch-up  F11=Unlock', 'd');
    ibmiTFkeys($t, ['F3=Exit', 'F4=Quota', 'F5=Refresh', 'F6=Local', 'F7=Import', 'F8=Odds', 'F9=Cron', 'F10=Catchup', 'F11=Lock']);

    return $t;
}

/** @param array<string,mixed> $ctx */
function ibmiPaintWrkmsg(PDO $pdo, array $ctx): array
{
    $cid = (int) ($ctx['community_id'] ?? 0);
    $q = trim((string) ($ctx['q'] ?? ''));
    $inc = !empty($ctx['include_deleted']);
    $data = adminQueryMessages($pdo, $cid, $q, $inc, 80);
    $page = max(1, (int) ($ctx['page'] ?? 1));
    $per = 12;
    $all = $data['messages'];
    $pages = max(1, (int) ceil(count($all) / $per));
    $slice = array_slice($all, ($page - 1) * $per, $per);
    $t = ibmiT();
    ibmiTHeader($t, 'Work with Messages', 'WRKMSG');
    ibmiTPut($t, 2, 1, 'Type options, press Enter.  2=Restore  4=Hide  9=Delete', 'd');
    ibmiTPut($t, 3, 1, 'Community', 'g');
    ibmiTField($t, 3, 12, 'community_id', 6, (string) $data['community_id']);
    ibmiTPut($t, 3, 20, 'Filter', 'g');
    ibmiTField($t, 3, 28, 'q', 18, $q);
    ibmiTPut($t, 3, 48, 'Incl. hidden (Y/N)', 'g');
    ibmiTField($t, 3, 68, 'include_deleted', 1, $inc ? 'Y' : 'N');
    $names = [];
    foreach (array_slice($data['communities'], 0, 3) as $c) {
        $names[] = $c['id'] . '=' . ibmiTClip($c['nom'], 12);
    }
    ibmiTPut($t, 4, 1, ibmiTClip(implode('  ', $names), 78), 'd');
    ibmiTPut($t, 5, 1, sprintf('Page %d/%d  (%d)', $page, $pages, count($all)), 'd');
    ibmiTPut($t, 6, 1, 'Opt  Id     When       User         St  Text', 't');
    $r = 7;
    foreach ($slice as $m) {
        $id = (int) $m['id'];
        ibmiTField($t, $r, 1, 'opt[' . $id . ']', 2, '', 'text', ['opt' => true]);
        $hid = !empty($m['supprime']);
        ibmiTPut(
            $t,
            $r,
            4,
            sprintf(
                '  %-6d %-10s %-12s %s %s',
                $id,
                adminFmtShortWhen($m['created_at'] ?? ''),
                ibmiTClip((string) $m['pseudo'], 12),
                $hid ? 'HID' : 'OK ',
                ibmiTClip((string) ($m['contenu'] ?? ''), 32)
            ),
            $hid ? 'r' : 'g'
        );
        $r++;
    }
    ibmiTFkeys($t, ['Enter=Confirm', 'F3=Exit', 'F5=Refresh', 'F12=Cancel']);

    return $t;
}

/** @param array<string,mixed> $ctx */
function ibmiPaintWrksea(PDO $pdo, array $ctx): array
{
    $d = adminQuerySeasons($pdo);
    $a = $d['active'];
    $t = ibmiT();
    ibmiTHeader($t, 'Work with Seasons', 'WRKSEA');
    if ($a) {
        ibmiTPut($t, 2, 1, 'Active season  . . . :', 'g');
        ibmiTPut($t, 2, 26, '#' . (int) $a['id'] . '  ' . formatSeasonFin($a['debut'] ?? '') . ' -> ' . formatSeasonFin($a['fin'] ?? ''), 'w');
        ibmiTPut($t, 3, 1, 'Countdown  . . . . . :', 'g');
        ibmiTPut($t, 3, 26, seasonCountdownLabel($a), 'h');
    } else {
        ibmiTPut($t, 2, 1, 'No active season.', 'y');
    }
    ibmiTPut($t, 5, 1, '1st of month target  :', 'g');
    ibmiTPut($t, 5, 26, $d['month_target'], 'g');
    ibmiTPut($t, 6, 1, 'Custom end (FIN) . . :', 'g');
    ibmiTField($t, 6, 26, 'fin', 19, '');
    ibmiTPut($t, 7, 1, 'Action (sea_do)  . . :', 'g');
    ibmiTField($t, 7, 26, 'sea_do', 16, '');
    ibmiTPut($t, 8, 1, '(close_now / schedule_month / schedule_custom)', 'd');
    ibmiTPut($t, 10, 1, 'Id   Start               End                 Status', 't');
    $r = 11;
    foreach ($d['history'] as $s) {
        ibmiTPut($t, $r, 1, sprintf(
            '#%-3d %-19s %-19s %s',
            (int) $s['id'],
            (string) $s['debut'],
            (string) $s['fin'],
            !empty($s['cloturee']) ? 'closed' : 'ACTIVE'
        ), !empty($s['cloturee']) ? 'g' : 'h');
        $r++;
        if ($r > 22) {
            break;
        }
    }
    ibmiTFkeys($t, ['F3=Exit', 'F5=Refresh', 'F6=Close', 'F7=Month', 'F8=Schedule', 'F12=Cancel']);

    return $t;
}

/** @param array<string,mixed> $ctx */
function ibmiPaintWrkevt(PDO $pdo, array $ctx): array
{
    $edit = (int) ($ctx['edit'] ?? 0);
    $d = adminQueryEvents($pdo, $edit);
    $f = $d['form'];
    $t = ibmiT();
    ibmiTHeader($t, 'Work with Events', 'WRKEVT');
    ibmiTPut($t, 2, 1, 'Type options, press Enter.  2=Edit  4=Delete  6=Toggle  7=Publish  8=Notify', 'd');
    ibmiTPut($t, 3, 1, 'Id', 'g');
    ibmiTField($t, 3, 5, 'id', 5, (string) (int) $f['id']);
    ibmiTPut($t, 3, 12, 'Title', 'g');
    ibmiTField($t, 3, 19, 'title', 40, (string) $f['title']);
    ibmiTPut($t, 4, 1, 'Type', 'g');
    ibmiTField($t, 4, 8, 'type', 18, (string) $f['type']);
    ibmiTPut($t, 4, 28, 'Theme', 'g');
    ibmiTField($t, 4, 35, 'theme', 10, (string) $f['theme']);
    ibmiTPut($t, 5, 1, 'Message', 'g');
    ibmiTField($t, 5, 10, 'message', 50, (string) $f['message']);
    ibmiTPut($t, 6, 1, 'x', 'g');
    ibmiTField($t, 6, 4, 'multiplier', 4, (string) $f['multiplier']);
    ibmiTPut($t, 6, 10, 'Sport', 'g');
    ibmiTField($t, 6, 17, 'sport', 10, (string) $f['sport']);
    ibmiTPut($t, 6, 29, 'Enab', 'g');
    ibmiTField($t, 6, 35, 'enabled', 1, !empty($f['enabled']) ? 'Y' : 'N');
    ibmiTPut($t, 6, 38, 'Pub', 'g');
    ibmiTField($t, 6, 43, 'published', 1, !empty($f['published']) ? 'Y' : 'N');
    ibmiTPut($t, 7, 1, 'Start', 'g');
    ibmiTField($t, 7, 8, 'starts_at', 16, (string) $f['starts_at']);
    ibmiTPut($t, 7, 26, 'End', 'g');
    ibmiTField($t, 7, 31, 'ends_at', 16, (string) $f['ends_at']);
    ibmiTPut($t, 9, 1, 'Opt  Id   Title                    Type            Status', 't');
    $r = 10;
    foreach (array_slice($d['events'], 0, 12) as $ev) {
        $eid = (int) $ev['id'];
        ibmiTField($t, $r, 1, 'opt[' . $eid . ']', 2, '', 'text', ['opt' => true]);
        ibmiTPut($t, $r, 4, sprintf(
            '  %-4d %-23s %-15s %s',
            $eid,
            ibmiTClip((string) $ev['title'], 23),
            ibmiTClip(siteEventTypeLabel((string) $ev['type']), 15),
            siteEventStatusLabel($ev)
        ), 'g');
        $r++;
    }
    ibmiTFkeys($t, ['Enter=Confirm', 'F3=Exit', 'F5=Refresh', 'F6=Save', 'F12=Cancel']);

    return $t;
}

/** @param array<string,mixed> $ctx */
function ibmiPaintWrkann(PDO $pdo, array $ctx): array
{
    $edit = (int) ($ctx['edit'] ?? 0);
    $d = adminQueryAnnouncements($pdo, $edit);
    $f = $d['form'];
    $t = ibmiT();
    ibmiTHeader($t, 'Work with Announcements', 'WRKANN');
    ibmiTPut($t, 2, 1, 'Type options, press Enter.  2=Edit  4=Delete  7=Publish', 'd');
    ibmiTPut($t, 3, 1, 'Id', 'g');
    ibmiTField($t, 3, 5, 'id', 5, (string) (int) $f['id']);
    ibmiTPut($t, 3, 12, 'Title', 'g');
    ibmiTField($t, 3, 19, 'title', 40, (string) $f['title']);
    ibmiTPut($t, 4, 1, 'Body', 'g');
    ibmiTField($t, 4, 8, 'body', 60, (string) $f['body']);
    ibmiTPut($t, 5, 1, 'Publish now (Y/N)', 'g');
    ibmiTField($t, 5, 20, 'published', 1, !empty($f['published']) ? 'Y' : 'N');
    ibmiTPut($t, 7, 1, 'Opt  Id   Title                              St     When', 't');
    $r = 8;
    foreach (array_slice($d['all'], 0, 14) as $row) {
        $id = (int) $row['id'];
        ibmiTField($t, $r, 1, 'opt[' . $id . ']', 2, '', 'text', ['opt' => true]);
        $when = $row['published_at'] ?? $row['created_at'] ?? '';
        ibmiTPut($t, $r, 4, sprintf(
            '  %-4d %-33s %-6s %s',
            $id,
            ibmiTClip((string) $row['title'], 33),
            !empty($row['published']) ? 'PUB' : 'DRAFT',
            adminFmtShortWhen((string) $when)
        ), 'g');
        $r++;
    }
    ibmiTFkeys($t, ['Enter=Confirm', 'F3=Exit', 'F5=Refresh', 'F6=Save', 'F12=Cancel']);

    return $t;
}

/** @param array<string,mixed> $ctx */
function ibmiPaintWrkrpt(array $ctx): array
{
    $t = ibmiT();
    ibmiTHeader($t, 'Work with Reports', 'WRKRPT');
    ibmiTPut($t, 3, 1, 'Recipient  . . . . . :', 'g');
    ibmiTPut($t, 3, 26, adminNotifyEmail(), 'w');
    ibmiTPut($t, 5, 1, 'F6  Send unavailable-data diagnostic (read only)', 'g');
    ibmiTPut($t, 6, 1, 'F7  Send monthly site report', 'g');
    ibmiTPut($t, 8, 1, 'Action (rpt_do)  . . :', 'g');
    ibmiTField($t, 8, 26, 'rpt_do', 22, '');
    ibmiTPut($t, 9, 1, '(report_unavailable / report_month)', 'd');
    ibmiTFkeys($t, ['F3=Exit', 'F5=Refresh', 'F6=Diagnostic', 'F7=Monthly', 'F12=Cancel']);

    return $t;
}

/** @param array<string,mixed> $ctx */
function ibmiPaintConfirm(array $ctx): array
{
    $cfg = $_SESSION['ibmi_confirm'] ?? null;
    $t = ibmiT();
    ibmiTHeader($t, 'Confirm', 'CONFIRM');
    ibmiTPut($t, 6, 8, 'Confirm this action:', 'y');
    if (is_array($cfg)) {
        ibmiTPut($t, 8, 8, ibmiTClip((string) $cfg['prompt'], 64), 'w');
    } else {
        ibmiTPut($t, 8, 8, 'No pending confirmation.', 'r');
    }
    ibmiTPut($t, 12, 8, 'Press Enter to confirm.', 'g');
    ibmiTPut($t, 13, 8, 'Press F12 to cancel.', 'g');
    ibmiTFkeys($t, ['Enter=Confirm', 'F3=Exit', 'F12=Cancel']);

    return $t;
}
