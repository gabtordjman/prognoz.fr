<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

function ibmiMenuMap(): array
{
    return [
        1  => 'DSPSTS',
        2  => 'WRKRPT',
        3  => 'WRKANN',
        4  => 'WRKSCR',
        5  => 'WRKOPS',
        6  => 'WRKMSG',
        7  => 'WRKUSR',
        8  => 'WRKSEA',
        9  => 'WRKEVT',
        90 => 'SIGNOFF',
    ];
}

function ibmiKnownScreens(): array
{
    return [
        'MAIN', 'DSPSTS', 'WRKRPT', 'WRKANN', 'WRKSCR', 'WRKOPS',
        'WRKMSG', 'WRKUSR', 'DSPUSR', 'WRKSEA', 'WRKEVT', 'CONFIRM', 'DSPMCH',
        'SIGNON', 'SIGNOFF',
    ];
}

function ibmiParentScr(string $scr): string
{
    return match ($scr) {
        'MAIN', 'SIGNON' => '',
        'DSPUSR' => 'WRKUSR',
        'DSPMCH' => 'WRKSCR',
        'CONFIRM' => (string) ($_SESSION['ibmi_confirm_back'] ?? 'MAIN'),
        default => 'MAIN',
    };
}

function ibmiRedirect(string $scr, array $query = []): never
{
    header('Location: ' . ibmiUrl($scr, $query));
    exit;
}

function ibmiApplyResult(array $result, string $scr, array $query = []): never
{
    adminFlash($result['ok'] ? $result['type'] : 'error', $result['message']);
    ibmiRedirect($scr, $query);
}

function ibmiAskConfirm(string $prompt, string $action, array $params, string $back, array $backQ = []): never
{
    $_SESSION['ibmi_confirm'] = [
        'prompt' => $prompt,
        'action' => $action,
        'params' => $params,
        'back'   => $back,
        'back_q' => $backQ,
    ];
    $_SESSION['ibmi_confirm_back'] = $back;
    ibmiRedirect('CONFIRM');
}

/**
 * @return array{scr:string,query:array}|null
 */
function ibmiParseCommand(string $raw): ?array
{
    $raw = strtoupper(trim($raw));
    $raw = preg_replace('/\s+/', ' ', $raw) ?? $raw;
    if ($raw === '') {
        return null;
    }

    if (preg_match('/^\d+$/', $raw)) {
        $n = (int) $raw;
        $map = ibmiMenuMap();
        if (!isset($map[$n])) {
            return null;
        }

        return ['scr' => $map[$n], 'query' => []];
    }

    if (in_array($raw, ['GO MAIN', 'MAIN', 'GO', 'MENU'], true)) {
        return ['scr' => 'MAIN', 'query' => []];
    }
    if (in_array($raw, ['SIGNOFF', 'SIGN OFF', 'END', '90'], true)) {
        return ['scr' => 'SIGNOFF', 'query' => []];
    }
    if (in_array($raw, ['GO OPS', 'WRKOPS'], true)) {
        return ['scr' => 'WRKOPS', 'query' => []];
    }
    if (preg_match('/^DSPUSR\s+(\d+)$/', $raw, $m)) {
        return ['scr' => 'DSPUSR', 'query' => ['id' => $m[1]]];
    }
    if (preg_match('/^DSPUSR\s+(\S+)$/', $raw, $m)) {
        return ['scr' => 'WRKUSR', 'query' => ['q' => $m[1]]];
    }
    if (preg_match('/^WRKUSR(?:\s+(\S+))?$/', $raw, $m)) {
        $q = [];
        if (!empty($m[1])) {
            $q['position'] = $m[1];
        }

        return ['scr' => 'WRKUSR', 'query' => $q];
    }
    if (preg_match('/^DSPMCH\s+(\d+)$/', $raw, $m)) {
        return ['scr' => 'DSPMCH', 'query' => ['id' => $m[1]]];
    }

    $simple = [
        'DSPSTS' => 'DSPSTS',
        'WRKRPT' => 'WRKRPT',
        'WRKANN' => 'WRKANN',
        'WRKSCR' => 'WRKSCR',
        'WRKMSG' => 'WRKMSG',
        'WRKSEA' => 'WRKSEA',
        'WRKEVT' => 'WRKEVT',
        'GO SEA' => 'WRKSEA',
        'GO EVT' => 'WRKEVT',
        'GO MSG' => 'WRKMSG',
        'GO SCR' => 'WRKSCR',
        'GO USR' => 'WRKUSR',
        'GO ANN' => 'WRKANN',
        'GO RPT' => 'WRKRPT',
        'GO STS' => 'DSPSTS',
    ];
    if (isset($simple[$raw])) {
        return ['scr' => $simple[$raw], 'query' => []];
    }

    if (in_array($raw, ibmiKnownScreens(), true)) {
        return ['scr' => $raw, 'query' => []];
    }

    return null;
}

function ibmiHandlePost(PDO $pdo): void
{
    if (!csrfCheck()) {
        adminFlash('error', 'Session expirée.');
        ibmiRedirect(ibmiCurrentScr(), ibmiPreserveQuery(ibmiCurrentScr()));
    }

    $scr = strtoupper(trim((string) ($_POST['scr'] ?? 'MAIN')));
    $fkey = strtoupper(trim((string) ($_POST['fkey'] ?? '')));
    $cmd = trim((string) ($_POST['cmdline'] ?? ''));
    $keep = ibmiPreserveQuery($scr);

    if ($fkey === 'F3') {
        if ($scr === 'CONFIRM') {
            $cfg = $_SESSION['ibmi_confirm'] ?? [];
            unset($_SESSION['ibmi_confirm']);
            ibmiRedirect((string) ($cfg['back'] ?? 'MAIN'), (array) ($cfg['back_q'] ?? []));
        }
        if ($scr === 'MAIN' || $scr === 'SIGNON') {
            header('Location: ' . url('admin/dashboard.php'));
            exit;
        }
        $parent = ibmiParentScr($scr);
        if ($parent === '') {
            header('Location: ' . url('admin/dashboard.php'));
            exit;
        }
        $q = $parent === 'WRKUSR' ? array_filter(['q' => $_GET['q'] ?? '', 'position' => $_GET['position'] ?? '']) : [];
        ibmiRedirect($parent, $q);
    }

    if ($fkey === 'F12') {
        if ($scr === 'CONFIRM') {
            $cfg = $_SESSION['ibmi_confirm'] ?? [];
            unset($_SESSION['ibmi_confirm']);
            ibmiRedirect((string) ($cfg['back'] ?? 'MAIN'), (array) ($cfg['back_q'] ?? []));
        }
        $parent = ibmiParentScr($scr);
        if ($parent === '') {
            ibmiRedirect('MAIN');
        }
        ibmiRedirect($parent, $scr === 'DSPUSR' ? array_filter(['q' => $_GET['q'] ?? '']) : []);
    }

    if ($fkey === 'F5') {
        ibmiRedirect($scr, $keep);
    }

    if ($fkey === 'PAGEUP' || $fkey === 'PAGEDOWN') {
        $page = (int) ($keep['page'] ?? $_GET['page'] ?? 1);
        $page = $fkey === 'PAGEDOWN' ? $page + 1 : max(1, $page - 1);
        $keep['page'] = $page;
        ibmiRedirect($scr, $keep);
    }

    if ($cmd !== '' && ibmiApplyCmdlineAsOpt($cmd)) {
        $cmd = '';
    }

    if ($cmd !== '') {
        $isMenuDigit = (bool) preg_match('/^\d+$/', $cmd);
        if ($scr !== 'MAIN' && $isMenuDigit) {
            if ($scr === 'WRKUSR' && (int) $cmd > 90) {
                ibmiRedirect('DSPUSR', ['id' => (int) $cmd, 'q' => $keep['q'] ?? '']);
            }
            adminFlash('info', ibmiOptHelp($scr));
            ibmiRedirect($scr, $keep);
        }
        $parsed = ibmiParseCommand($cmd);
        if ($parsed === null) {
            adminFlash('error', 'Commande non reconnue : ' . $cmd);
            ibmiRedirect($scr, $keep);
        }
        if ($parsed['scr'] === 'SIGNOFF') {
            adminLogout();
            header('Location: ' . url('admin/ibmi/index.php'));
            exit;
        }
        ibmiRedirect($parsed['scr'], $parsed['query'] ?? []);
    }

    if ($scr === 'CONFIRM') {
        $cfg = $_SESSION['ibmi_confirm'] ?? null;
        unset($_SESSION['ibmi_confirm']);
        if (!is_array($cfg)) {
            ibmiRedirect('MAIN');
        }
        $result = adminRunAction($pdo, (string) $cfg['action'], (array) $cfg['params']);
        ibmiApplyResult($result, (string) ($cfg['back'] ?? 'MAIN'), (array) ($cfg['back_q'] ?? []));
    }

    ibmiHandleScreenPost($pdo, $scr, $fkey, $keep);
}

function ibmiOptValid(string $scr): array
{
    return match (strtoupper($scr)) {
        'WRKUSR' => ['2', '4', '5', '7', '8', '9'],
        'WRKSCR' => ['4', '5', '6', '7', '8', '9'],
        'WRKMSG' => ['2', '4', '9'],
        'WRKEVT' => ['2', '4', '6', '7', '8'],
        'WRKANN' => ['2', '4', '7'],
        default  => [],
    };
}

function ibmiOptHelp(string $scr): string
{
    $ok = ibmiOptValid($scr);
    if ($ok === []) {
        return 'Commandes : WRKUSR, DSPUSR n, GO MAIN, 90. F3=Exit  F12=Cancel';
    }

    return 'Tapez l\'option dans Opt (gauche), Entrée. Ou ===> ' . $ok[0] . ' 123  (' . implode(',', $ok) . ')';
}

function ibmiEnsureOpt(string $scr, int $id, string $opt, array $keep): void
{
    if ($id < 1 || $opt === '') {
        return;
    }
    $ok = ibmiOptValid($scr);
    if ($ok !== [] && !in_array($opt, $ok, true)) {
        adminFlash(
            'error',
            'CPF0006 - Option ' . $opt . ' not valid. Use ' . implode(', ', $ok)
            . '.  Opt + Enter, or ===> ' . $ok[0] . ' ' . $id
        );
        ibmiRedirect($scr, $keep);
    }
}

function ibmiApplyCmdlineAsOpt(string $cmd): bool
{
    $cmd = trim($cmd);
    if (!preg_match('/^(\d{1,2})\s+(\d+)$/', $cmd, $m)) {
        return false;
    }
    $id = (int) $m[2];
    if ($id < 1) {
        return false;
    }
    if (!isset($_POST['opt']) || !is_array($_POST['opt'])) {
        $_POST['opt'] = [];
    }
    $_POST['opt'][$id] = $m[1];

    return true;
}

function ibmiFirstOpt(array $opts): array
{
    foreach ($opts as $id => $val) {
        $val = strtoupper(trim((string) $val, " \t_"));
        if ($val !== '') {
            return [(int) $id, $val];
        }
    }

    return [0, ''];
}

function ibmiHandleScreenPost(PDO $pdo, string $scr, string $fkey, array $keep): void
{
    if ($scr === 'MAIN') {
        $choix = trim((string) ($_POST['choix'] ?? ''));
        if ($choix !== '') {
            $parsed = ibmiParseCommand($choix);
            if ($parsed) {
                if ($parsed['scr'] === 'SIGNOFF') {
                    adminLogout();
                    header('Location: ' . url('admin/ibmi/index.php'));
                    exit;
                }
                ibmiRedirect($parsed['scr'], $parsed['query'] ?? []);
            }
        }
        ibmiRedirect('MAIN');
    }

    if ($scr === 'WRKUSR') {
        ibmiPostWrkusr($pdo, $fkey, $keep);
    }
    if ($scr === 'DSPUSR') {
        ibmiPostDspusr($pdo, $fkey, $keep);
    }
    if ($scr === 'WRKSCR' || $scr === 'DSPMCH') {
        ibmiPostWrkscr($pdo, $scr, $fkey, $keep);
    }
    if ($scr === 'WRKOPS') {
        ibmiPostWrkops($pdo, $fkey, $keep);
    }
    if ($scr === 'WRKMSG') {
        ibmiPostWrkmsg($pdo, $fkey, $keep);
    }
    if ($scr === 'WRKSEA') {
        ibmiPostWrksea($pdo, $fkey, $keep);
    }
    if ($scr === 'WRKEVT') {
        ibmiPostWrkevt($pdo, $fkey, $keep);
    }
    if ($scr === 'WRKANN') {
        ibmiPostWrkann($pdo, $fkey, $keep);
    }
    if ($scr === 'WRKRPT') {
        ibmiPostWrkrpt($pdo, $fkey, $keep);
    }
    if ($scr === 'DSPSTS') {
        ibmiRedirect('DSPSTS');
    }

    ibmiRedirect($scr, $keep);
}

function ibmiPostWrkusr(PDO $pdo, string $fkey, array $keep): never
{
    $q = trim((string) ($_POST['q'] ?? $keep['q'] ?? ''));
    $position = trim((string) ($_POST['position'] ?? $keep['position'] ?? ''));
    $keep['q'] = $q;
    $keep['position'] = $position;
    unset($keep['page']);

    $opts = $_POST['opt'] ?? [];
    [$id, $opt] = ibmiFirstOpt(is_array($opts) ? $opts : []);
    ibmiEnsureOpt('WRKUSR', $id, $opt, $keep);

    if ($id > 0 && $opt === '5') {
        ibmiRedirect('DSPUSR', ['id' => $id, 'q' => $q]);
    }

    if ($id > 0 && $opt === '2') {
        $delta = (int) ($_POST['delta'] ?? 0);
        $result = adminRunAction($pdo, 'grant_points', [
            'user_id'   => $id,
            'delta'     => $delta,
            'to_season' => ibmiIsYes($_POST['to_season'] ?? 'Y', true),
        ]);
        ibmiApplyResult($result, 'WRKUSR', $keep);
    }

    if ($id > 0 && $opt === '4') {
        $stmt = $pdo->prepare('SELECT pseudo, actif FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        $next = empty($u['actif']) ? 1 : 0;
        ibmiAskConfirm(
            ($next ? 'Réactiver' : 'Désactiver') . ' ' . ($u['pseudo'] ?? ('#' . $id)) . ' ?',
            'set_active',
            ['user_id' => $id, 'actif' => $next],
            'WRKUSR',
            $keep
        );
    }

    if ($id > 0 && $opt === '7') {
        $stmt = $pdo->prepare('SELECT pseudo, mail_opt_out FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $u = $stmt->fetch() ?: [];
        $next = empty($u['mail_opt_out']) ? 1 : 0;
        $result = adminRunAction($pdo, 'set_mail_opt_out', [
            'user_id'      => $id,
            'mail_opt_out' => $next,
        ]);
        ibmiApplyResult($result, 'WRKUSR', $keep);
    }

    if ($id > 0 && $opt === '8') {
        $result = adminRunAction($pdo, 'reset_password', [
            'user_id'      => $id,
            'new_password' => (string) ($_POST['new_password'] ?? ''),
        ]);
        ibmiApplyResult($result, 'WRKUSR', $keep);
    }

    if ($id > 0 && $opt === '9') {
        ibmiAskConfirm(
            'Retirer la photo du joueur #' . $id . ' ?',
            'remove_avatar',
            ['user_id' => $id],
            'WRKUSR',
            $keep
        );
    }

    ibmiRedirect('WRKUSR', $keep);
}

function ibmiPostDspusr(PDO $pdo, string $fkey, array $keep): never
{
    $id = (int) ($_POST['user_id'] ?? $keep['id'] ?? $_GET['id'] ?? 0);
    $keep['id'] = $id;

    if ($fkey === 'F6' || (string) ($_POST['dsp_action'] ?? '') === 'points') {
        $result = adminRunAction($pdo, 'grant_points', [
            'user_id'   => $id,
            'delta'     => (int) ($_POST['delta'] ?? 0),
            'to_season' => ibmiIsYes($_POST['to_season'] ?? 'Y', true),
        ]);
        ibmiApplyResult($result, 'DSPUSR', $keep);
    }
    $dsp = (string) ($_POST['dsp_action'] ?? '');
    if ($dsp === 'toggle_active') {
        $stmt = $pdo->prepare('SELECT actif, pseudo FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $u = $stmt->fetch() ?: [];
        $next = empty($u['actif']) ? 1 : 0;
        ibmiAskConfirm(
            ($next ? 'Réactiver' : 'Désactiver') . ' ' . ($u['pseudo'] ?? '') . ' ?',
            'set_active',
            ['user_id' => $id, 'actif' => $next],
            'DSPUSR',
            $keep
        );
    }
    if ($dsp === 'toggle_mail') {
        $stmt = $pdo->prepare('SELECT mail_opt_out FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $u = $stmt->fetch() ?: [];
        $result = adminRunAction($pdo, 'set_mail_opt_out', [
            'user_id'      => $id,
            'mail_opt_out' => empty($u['mail_opt_out']) ? 1 : 0,
        ]);
        ibmiApplyResult($result, 'DSPUSR', $keep);
    }
    if ($dsp === 'reset_password') {
        $result = adminRunAction($pdo, 'reset_password', [
            'user_id'      => $id,
            'new_password' => (string) ($_POST['new_password'] ?? ''),
        ]);
        ibmiApplyResult($result, 'DSPUSR', $keep);
    }
    if ($dsp === 'remove_avatar') {
        ibmiAskConfirm(
            'Retirer la photo ?',
            'remove_avatar',
            ['user_id' => $id],
            'DSPUSR',
            $keep
        );
    }

    ibmiRedirect('DSPUSR', $keep);
}

function ibmiPostWrkscr(PDO $pdo, string $scr, string $fkey, array $keep): never
{
    $panelMap = [
        'F6' => 'file',
        'F7' => 'saisie',
        'F8' => 'reportes',
        'F9' => 'points',
    ];
    if (isset($panelMap[$fkey])) {
        $keep['panel'] = $panelMap[$fkey];
        ibmiRedirect('WRKSCR', $keep);
    }

    if ($fkey === 'F10') {
        ibmiAskConfirm(
            'Rattrapage API multi-ligues (crédits) ?',
            'catchup_scores',
            [],
            'WRKSCR',
            $keep
        );
    }
    if ($fkey === 'F11') {
        $result = adminRunAction($pdo, 'score_local', []);
        $keep['panel'] = 'points';
        ibmiApplyResult($result, 'WRKSCR', $keep);
    }

    $keep['team_home'] = trim((string) ($_POST['team_home'] ?? $keep['team_home'] ?? ''));
    $keep['team_away'] = trim((string) ($_POST['team_away'] ?? $keep['team_away'] ?? ''));
    $keep['sport'] = (string) ($_POST['sport'] ?? $keep['sport'] ?? '');

    if ($fkey === 'F4' || (string) ($_POST['scr_do'] ?? '') === 'search') {
        $keep['panel'] = 'saisie';
        ibmiRedirect('WRKSCR', $keep);
    }

    $bulk = (string) ($_POST['scr_do'] ?? '');
    if ($bulk === 'recover_postponed') {
        ibmiAskConfirm('Récupérer scores API des reportés (≤ 3 j) ?', 'recover_postponed_scores', [], 'WRKSCR', $keep + ['panel' => 'reportes']);
    }
    if ($bulk === 'dismiss_empty') {
        ibmiAskConfirm('Nettoyer les reportés sans prono ?', 'dismiss_empty_postponed', [], 'WRKSCR', $keep + ['panel' => 'reportes']);
    }
    if ($bulk === 'reactivate_future') {
        ibmiAskConfirm('Réactiver les reportés à date future ?', 'reactivate_future_postponed', [], 'WRKSCR', $keep + ['panel' => 'reportes']);
    }

    $homes = $_POST['score_home'] ?? [];
    $aways = $_POST['score_away'] ?? [];
    $pens = $_POST['pens'] ?? [];
    $pensW = $_POST['pens_winner'] ?? [];
    $applied = 0;
    $lastMsg = '';
    if (is_array($homes) && is_array($aways)) {
        foreach ($homes as $mid => $h) {
            $h = trim((string) $h);
            $a = trim((string) ($aways[$mid] ?? ''));
            if ($h === '' || $a === '') {
                continue;
            }
            $params = [
                'match_id'    => (int) $mid,
                'score_home'  => (int) $h,
                'score_away'  => (int) $a,
            ];
            if (!empty($pens[$mid])) {
                $params['pens'] = 1;
                $params['pens_winner'] = (string) ($pensW[$mid] ?? '');
            }
            $result = adminRunAction($pdo, 'manual_score', $params);
            $lastMsg = $result['message'];
            if ($result['ok']) {
                $applied++;
            } else {
                ibmiApplyResult($result, 'WRKSCR', $keep);
            }
        }
    }
    if ($applied > 0) {
        adminFlash('success', $applied > 1 ? $applied . ' score(s) enregistrés.' : $lastMsg);
        ibmiRedirect('WRKSCR', $keep);
    }

    $opts = $_POST['opt'] ?? [];
    [$id, $opt] = ibmiFirstOpt(is_array($opts) ? $opts : []);
    ibmiEnsureOpt('WRKSCR', $id, $opt, $keep);
    if ($id > 0 && $opt === '5') {
        ibmiRedirect('DSPMCH', ['id' => $id, 'panel' => $keep['panel'] ?? 'file']);
    }
    if ($id > 0 && $opt === '4') {
        $reason = (string) ($_POST['cancel_reason'] ?? '');
        ibmiAskConfirm(
            'Annuler le match #' . $id . ' ?',
            'cancel_match',
            ['match_id' => $id, 'cancel_reason' => $reason],
            'WRKSCR',
            $keep
        );
    }
    if ($id > 0 && $opt === '6') {
        $result = adminRunAction($pdo, 'postpone_match', [
            'match_id' => $id,
            'new_date' => (string) ($_POST['new_date'] ?? ''),
        ]);
        $keep['panel'] = 'reportes';
        ibmiApplyResult($result, 'WRKSCR', $keep);
    }
    if ($id > 0 && $opt === '7') {
        $result = adminRunAction($pdo, 'postpone_reactivate', [
            'match_id' => $id,
            'new_date' => (string) ($_POST['new_date'] ?? ''),
        ]);
        $keep['panel'] = 'reportes';
        ibmiApplyResult($result, 'WRKSCR', $keep);
    }
    if ($id > 0 && $opt === '8') {
        $result = adminRunAction($pdo, 'postpone_set_date', [
            'match_id' => $id,
            'new_date' => (string) ($_POST['new_date'] ?? ''),
        ]);
        $keep['panel'] = 'reportes';
        ibmiApplyResult($result, 'WRKSCR', $keep);
    }
    if ($id > 0 && $opt === '9') {
        ibmiAskConfirm(
            'Effacer le score du match #' . $id . ' (reouvre les pronos) ?',
            'clear_match_score',
            ['match_id' => $id],
            'WRKSCR',
            $keep
        );
    }

    ibmiRedirect($scr === 'DSPMCH' ? 'DSPMCH' : 'WRKSCR', $keep);
}

function ibmiPostWrkops(PDO $pdo, string $fkey, array $keep): never
{
    $map = [
        'F6'  => 'score_local',
        'F7'  => 'matches',
        'F8'  => 'odds',
        'F9'  => 'cron',
        'F10' => 'catchup_scores',
        'F11' => 'clear_lock',
    ];
    $needsConfirm = ['matches' => 'Import matchs /events (1–2 min) ?', 'odds' => 'Sync cotes (crédits) ?', 'cron' => 'Lancer le cron scores ?', 'catchup_scores' => 'Rattrapage API multi-ligues ?', 'prune' => 'Purger les données périmées ?'];

    $action = $map[$fkey] ?? (string) ($_POST['ops_do'] ?? '');
    if ($fkey === 'F4' || $action === 'probe_quota') {
        $result = adminRunAction($pdo, 'probe_quota', ['alt_key' => (string) ($_POST['alt_key'] ?? '')]);
        ibmiApplyResult($result, 'WRKOPS', $keep);
    }
    if ($action === 'score_local') {
        $result = adminRunAction($pdo, 'score_local', ['close_expired' => 1]);
        ibmiApplyResult($result, 'WRKOPS', $keep);
    }
    if ($action === 'clear_lock') {
        $result = adminRunAction($pdo, 'clear_lock', []);
        ibmiApplyResult($result, 'WRKOPS', $keep);
    }
    if (isset($needsConfirm[$action])) {
        ibmiAskConfirm($needsConfirm[$action], $action, [], 'WRKOPS', $keep);
    }
    ibmiRedirect('WRKOPS', $keep);
}

function ibmiPostWrkmsg(PDO $pdo, string $fkey, array $keep): never
{
    $keep['community_id'] = (int) ($_POST['community_id'] ?? $keep['community_id'] ?? 0);
    $keep['q'] = trim((string) ($_POST['q'] ?? $keep['q'] ?? ''));
    $keep['include_deleted'] = ibmiIsYes($_POST['include_deleted'] ?? ($keep['include_deleted'] ?? ''), false) ? '1' : '';

    $opts = $_POST['opt'] ?? [];
    [$id, $opt] = ibmiFirstOpt(is_array($opts) ? $opts : []);
    ibmiEnsureOpt('WRKMSG', $id, $opt, $keep);
    $params = ['message_id' => $id];
    if ($id > 0 && $opt === '4') {
        $result = adminRunAction($pdo, 'soft_delete', $params);
        ibmiApplyResult($result, 'WRKMSG', $keep);
    }
    if ($id > 0 && $opt === '2') {
        $result = adminRunAction($pdo, 'restore', $params);
        ibmiApplyResult($result, 'WRKMSG', $keep);
    }
    if ($id > 0 && $opt === '9') {
        ibmiAskConfirm(
            'Effacer définitivement le message #' . $id . ' ?',
            'hard_delete',
            $params,
            'WRKMSG',
            $keep
        );
    }
    ibmiRedirect('WRKMSG', $keep);
}

function ibmiPostWrksea(PDO $pdo, string $fkey, array $keep): never
{
    if ($fkey === 'F6' || (string) ($_POST['sea_do'] ?? '') === 'close_now') {
        ibmiAskConfirm('Clôturer maintenant ? Podium + badges + nouvelle saison.', 'close_now', [], 'WRKSEA');
    }
    if ($fkey === 'F7' || (string) ($_POST['sea_do'] ?? '') === 'schedule_month') {
        ibmiAskConfirm('Planifier la fin au 1er du mois ?', 'schedule_month', [], 'WRKSEA');
    }
    if ((string) ($_POST['sea_do'] ?? '') === 'schedule_custom' || $fkey === 'F8') {
        $result = adminRunAction($pdo, 'schedule_custom', ['fin' => (string) ($_POST['fin'] ?? '')]);
        ibmiApplyResult($result, 'WRKSEA', $keep);
    }
    ibmiRedirect('WRKSEA', $keep);
}

function ibmiPostWrkevt(PDO $pdo, string $fkey, array $keep): never
{
    $opts = $_POST['opt'] ?? [];
    [$id, $opt] = ibmiFirstOpt(is_array($opts) ? $opts : []);
    ibmiEnsureOpt('WRKEVT', $id, $opt, $keep);
    if ($id > 0 && $opt === '2') {
        ibmiRedirect('WRKEVT', ['edit' => $id]);
    }
    if ($id > 0 && $opt === '4') {
        ibmiAskConfirm('Supprimer l’événement #' . $id . ' ?', 'event_delete', ['id' => $id], 'WRKEVT');
    }
    if ($id > 0 && $opt === '6') {
        $result = adminRunAction($pdo, 'event_toggle', ['id' => $id]);
        ibmiApplyResult($result, 'WRKEVT', $keep);
    }
    if ($id > 0 && $opt === '7') {
        $result = adminRunAction($pdo, 'event_publish', [
            'id' => $id,
            'notify_on_publish' => 1,
        ]);
        ibmiApplyResult($result, 'WRKEVT', $keep);
    }
    if ($id > 0 && $opt === '8') {
        $result = adminRunAction($pdo, 'event_notify', ['id' => $id]);
        ibmiApplyResult($result, 'WRKEVT', $keep);
    }
    if ($fkey === 'F6' || (string) ($_POST['evt_do'] ?? '') === 'save') {
        $result = adminRunAction($pdo, 'event_save', [
            'id'          => (int) ($_POST['id'] ?? 0),
            'title'       => $_POST['title'] ?? '',
            'message'     => $_POST['message'] ?? '',
            'type'        => $_POST['type'] ?? '',
            'theme'       => $_POST['theme'] ?? 'default',
            'starts_at'   => $_POST['starts_at'] ?? '',
            'ends_at'     => $_POST['ends_at'] ?? '',
            'enabled'     => ibmiIsYes($_POST['enabled'] ?? 'Y', true),
            'published'   => ibmiIsYes($_POST['published'] ?? '', false),
            'multiplier'  => $_POST['multiplier'] ?? '2',
            'sport'       => $_POST['sport'] ?? '',
            'notify_push' => !empty($_POST['notify_push']),
        ]);
        ibmiApplyResult($result, 'WRKEVT', []);
    }
    ibmiRedirect('WRKEVT', $keep);
}

function ibmiPostWrkann(PDO $pdo, string $fkey, array $keep): never
{
    $opts = $_POST['opt'] ?? [];
    [$id, $opt] = ibmiFirstOpt(is_array($opts) ? $opts : []);
    ibmiEnsureOpt('WRKANN', $id, $opt, $keep);
    if ($id > 0 && $opt === '2') {
        ibmiRedirect('WRKANN', ['edit' => $id]);
    }
    if ($id > 0 && $opt === '4') {
        ibmiAskConfirm('Supprimer l’annonce #' . $id . ' ?', 'ann_delete', ['id' => $id], 'WRKANN');
    }
    if ($id > 0 && $opt === '7') {
        $result = adminRunAction($pdo, 'ann_publish', ['id' => $id]);
        ibmiApplyResult($result, 'WRKANN', $keep);
    }
    if ($fkey === 'F6' || (string) ($_POST['ann_do'] ?? '') === 'save') {
        $result = adminRunAction($pdo, 'ann_save', [
            'id'        => (int) ($_POST['id'] ?? 0),
            'title'     => $_POST['title'] ?? '',
            'body'      => $_POST['body'] ?? '',
            'published' => ibmiIsYes($_POST['published'] ?? 'Y', true),
        ]);
        ibmiApplyResult($result, 'WRKANN', []);
    }
    ibmiRedirect('WRKANN', $keep);
}

function ibmiPostWrkrpt(PDO $pdo, string $fkey, array $keep): never
{
    if ($fkey === 'F6' || (string) ($_POST['rpt_do'] ?? '') === 'report_unavailable') {
        $result = adminRunAction($pdo, 'report_unavailable', []);
        ibmiApplyResult($result, 'WRKRPT', $keep);
    }
    if ($fkey === 'F7' || (string) ($_POST['rpt_do'] ?? '') === 'report_month') {
        ibmiAskConfirm(
            'Envoyer le rapport du mois à ' . adminNotifyEmail() . ' ?',
            'report_month',
            [],
            'WRKRPT'
        );
    }
    ibmiRedirect('WRKRPT', $keep);
}
