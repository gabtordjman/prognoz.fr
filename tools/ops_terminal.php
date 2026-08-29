<?php
/**
 * Administration Prognoz — poste 5250 en SSH (même noyau que /admin/ibmi/).
 *
 *   php tools/ops_terminal.php
 *
 * Commandes : GO MAIN, WRKUSR, DSPUSR n, WRKSCR, GO OPS, F3, F5, F12, 90
 */
define('APP_CLI_ADMIN', true);

$root = dirname(__DIR__);
require $root . '/app/bootstrap.php';
require_once $root . '/app/ibmi_term.php';
require_once $root . '/app/ibmi_layout.php';
require_once $root . '/app/ibmi_router.php';
require_once $root . '/app/ibmi_screens.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

const T_W = 80;
const T_GREEN = "\033[32m";
const T_BOLD = "\033[1;32m";
const T_CYAN = "\033[36m";
const T_YEL = "\033[33m";
const T_RED = "\033[31m";
const T_DIM = "\033[2;32m";
const T_RST = "\033[0m";

/** @var array{ok:bool,type?:string,message:string}|null */
$tFlash = null;
$tAlt = false;

function t_enter_alt(): void
{
    global $tAlt;
    if ($tAlt) {
        return;
    }
    echo "\033[?1049h\033[?25h";
    $tAlt = true;
}

function t_leave(): void
{
    global $tAlt;
    if (!$tAlt) {
        return;
    }
    echo "\033[?25h\033[?1049l";
    $tAlt = false;
}

function t_field_cup(array $t, string $name): string
{
    foreach ($t['fields'] as $f) {
        if ((string) $f['name'] === $name) {
            return "\033[" . ((int) $f['r'] + 1) . ';' . ((int) $f['c'] + 1) . 'H';
        }
    }

    return "\033[26;6H";
}

function t_read(string $prompt): string
{
    echo "\033[25;2H\033[K\033[33m" . $prompt . T_RST . "\033[1;37m";
    $line = fgets(STDIN);
    echo T_RST;

    return $line === false ? '' : trim($line);
}

function t_msg(string $msg, string $type = 'ok'): void
{
    $c = $type === 'error' ? T_RED : ($type === 'info' ? T_CYAN : T_YEL);
    echo "\033[25;2H\033[K" . $c . $msg . T_RST;
}

function t_auth(): void
{
    t_enter_alt();
    if (!adminPanelConfigured()) {
        $t = ibmiT();
        ibmiTHeader($t, 'Sign On', 'SIGNON');
        ibmiTPut($t, 8, 8, 'ADMIN_* missing from .env', 'r');
        ibmiTPut($t, 10, 8, 'php tools/generate_admin_credentials.php', 'y');
        echo ibmiTAnsi($t);
        t_leave();
        exit(1);
    }
    $t = ibmiPaintSignon('');
    echo ibmiTAnsi($t);
    echo t_field_cup($t, 'username') . "\033[1;37m";
    $user = fgets(STDIN);
    $user = $user === false ? '' : trim($user);
    echo t_field_cup($t, 'password');
    $hidden = false;
    $stty = '';
    if (strncasecmp(PHP_OS, 'WIN', 3) !== 0 && function_exists('shell_exec')) {
        $stty = (string) @shell_exec('stty -g 2>/dev/null');
        if ($stty !== '') {
            @shell_exec('stty -echo');
            $hidden = true;
        }
    }
    $passLine = fgets(STDIN);
    $pass = $passLine === false ? '' : trim($passLine);
    if ($hidden) {
        @shell_exec('stty ' . escapeshellarg(trim($stty)));
    }
    echo "\033[0m";
    if (!hash_equals(ADMIN_USERNAME, $user) || !password_verify($pass, ADMIN_PASSWORD_HASH)) {
        echo ibmiTAnsi(ibmiPaintSignon('CPF1116 - Sign-on failed.'));
        usleep(1200000);
        t_leave();
        exit(1);
    }
}

function t_confirm(string $prompt): bool
{
    $ok = strtolower(t_read($prompt . ' (Y/N) '));

    return $ok === 'o' || $ok === 'oui' || $ok === 'y';
}

function t_run(PDO $pdo, string $action, array $p = []): void
{
    global $tFlash;
    $tFlash = adminRunAction($pdo, $action, $p);
}

function t_fkeys(string $line): ?string
{
    $u = strtoupper(trim($line));
    $map = [
        'F3' => 'F3', 'F5' => 'F5', 'F12' => 'F12', 'F4' => 'F4',
        'F6' => 'F6', 'F7' => 'F7', 'F8' => 'F8', 'F9' => 'F9',
        'F10' => 'F10', 'F11' => 'F11', 'P+' => 'PAGEDOWN', 'P-' => 'PAGEUP',
        'PAGEDOWN' => 'PAGEDOWN', 'PAGEUP' => 'PAGEUP',
    ];

    return $map[$u] ?? null;
}

function t_parent(string $scr): string
{
    return match ($scr) {
        'DSPUSR' => 'WRKUSR',
        'DSPMCH' => 'WRKSCR',
        'MAIN' => 'MAIN',
        default => 'MAIN',
    };
}

register_shutdown_function('t_leave');
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function () {
        t_leave();
        exit(130);
    });
}

t_auth();
$pdo = getPDO();
$scr = 'MAIN';
$ctx = [
    'page' => 1,
    'q' => '',
    'position' => '',
    'panel' => 'file',
    'id' => 0,
    'community_id' => 0,
    'include_deleted' => false,
    'team_home' => '',
    'team_away' => '',
    'sport' => '',
];

while (true) {
    unset($ctx['msg'], $ctx['msgType']);
    if (is_array($tFlash)) {
        $ctx['msg'] = (string) ($tFlash['message'] ?? '');
        $ctx['msgType'] = !empty($tFlash['ok']) ? (string) ($tFlash['type'] ?? 'info') : 'error';
        $tFlash = null;
    }
    try {
        $term = ibmiBuildScreen($pdo, $scr, $ctx);
    } catch (Throwable $e) {
        $term = ibmiT();
        ibmiTHeader($term, 'Exception', $scr);
        ibmiTPut($term, 4, 2, ibmiTClip($e->getMessage(), 76), 'r');
        ibmiTCmd($term);
        ibmiTFkeys($term, ['F3=Exit', 'F12=Cancel']);
    }
    echo ibmiTAnsi($term);
    echo t_field_cup($term, 'cmdline') . "\033[1;37m";
    $raw = fgets(STDIN);
    echo "\033[0m";
    $line = $raw === false ? '' : trim($raw);
    if ($line === '' && feof(STDIN)) {
        break;
    }

    $fkey = t_fkeys($line);
    if ($fkey === 'F3' || $fkey === 'F12') {
        if ($scr === 'MAIN' && $fkey === 'F3') {
            echo ibmiTAnsi(ibmiPaintSignoff());
            usleep(400000);
            t_leave();
            exit(0);
        }
        $scr = t_parent($scr);
        continue;
    }
    if ($fkey === 'F5') {
        continue;
    }
    if ($fkey === 'PAGEDOWN') {
        $ctx['page'] = (int) ($ctx['page'] ?? 1) + 1;
        continue;
    }
    if ($fkey === 'PAGEUP') {
        $ctx['page'] = max(1, (int) ($ctx['page'] ?? 1) - 1);
        continue;
    }

    $parsed = ibmiParseCommand($line);
    $isMenuDigit = (bool) preg_match('/^\d+$/', strtoupper(trim($line)));
    if ($parsed && ($scr === 'MAIN' || !$isMenuDigit)) {
        if ($parsed['scr'] === 'SIGNOFF') {
            echo ibmiTAnsi(ibmiPaintSignoff());
            usleep(400000);
            t_leave();
            exit(0);
        }
        $scr = $parsed['scr'];
        if (isset($parsed['query']['id'])) {
            $ctx['id'] = (int) $parsed['query']['id'];
        }
        if (isset($parsed['query']['q'])) {
            $ctx['q'] = (string) $parsed['query']['q'];
            $ctx['page'] = 1;
        }
        if (isset($parsed['query']['position'])) {
            $ctx['position'] = (string) $parsed['query']['position'];
            $ctx['page'] = 1;
        }
        continue;
    }

    $u = strtoupper($line);
    $parts = preg_split('/\s+/', $line) ?: [];

    if ($scr === 'WRKUSR') {
        if (isset($parts[0], $parts[1]) && $parts[0] === 'Q') {
            $ctx['q'] = $parts[1];
            $ctx['page'] = 1;
            continue;
        }
        $opt = $parts[0] ?? '';
        $id = (int) ($parts[1] ?? 0);
        if ($opt === '5' && $id > 0) {
            $ctx['id'] = $id;
            $scr = 'DSPUSR';
            continue;
        }
        if ($opt === '2' && $id > 0) {
            $delta = (int) ($parts[2] ?? t_read('  Delta : '));
            t_run($pdo, 'grant_points', ['user_id' => $id, 'delta' => $delta, 'to_season' => 1]);
            continue;
        }
        if ($opt === '4' && $id > 0 && t_confirm('  Basculer actif')) {
            $st = $pdo->prepare('SELECT actif FROM users WHERE id = ?');
            $st->execute([$id]);
            $cur = $st->fetch();
            t_run($pdo, 'set_active', ['user_id' => $id, 'actif' => empty($cur['actif']) ? 1 : 0]);
            continue;
        }
        if ($opt === '7' && $id > 0) {
            $st = $pdo->prepare('SELECT mail_opt_out FROM users WHERE id = ?');
            $st->execute([$id]);
            $cur = $st->fetch() ?: [];
            t_run($pdo, 'set_mail_opt_out', ['user_id' => $id, 'mail_opt_out' => empty($cur['mail_opt_out']) ? 1 : 0]);
            continue;
        }
        if ($opt === '8' && $id > 0) {
            $pwd = $parts[2] ?? t_read('  Nouveau MDP : ');
            t_run($pdo, 'reset_password', ['user_id' => $id, 'new_password' => $pwd]);
            continue;
        }
        if ($opt === '9' && $id > 0 && t_confirm('  Retirer photo')) {
            t_run($pdo, 'remove_avatar', ['user_id' => $id]);
            continue;
        }
    }

    if ($scr === 'DSPUSR') {
        $opt = $parts[0] ?? '';
        $id = (int) $ctx['id'];
        if ($opt === '2') {
            $delta = (int) ($parts[1] ?? t_read('  Delta : '));
            t_run($pdo, 'grant_points', ['user_id' => $id, 'delta' => $delta, 'to_season' => 1]);
            continue;
        }
        if ($opt === '4' && t_confirm('  Basculer actif')) {
            $st = $pdo->prepare('SELECT actif FROM users WHERE id = ?');
            $st->execute([$id]);
            $cur = $st->fetch();
            t_run($pdo, 'set_active', ['user_id' => $id, 'actif' => empty($cur['actif']) ? 1 : 0]);
            continue;
        }
        if ($opt === '7') {
            $st = $pdo->prepare('SELECT mail_opt_out FROM users WHERE id = ?');
            $st->execute([$id]);
            $cur = $st->fetch() ?: [];
            t_run($pdo, 'set_mail_opt_out', ['user_id' => $id, 'mail_opt_out' => empty($cur['mail_opt_out']) ? 1 : 0]);
            continue;
        }
        if ($opt === '8') {
            $pwd = $parts[1] ?? t_read('  Nouveau MDP : ');
            t_run($pdo, 'reset_password', ['user_id' => $id, 'new_password' => $pwd]);
            continue;
        }
        if ($opt === '9' && t_confirm('  Retirer photo')) {
            t_run($pdo, 'remove_avatar', ['user_id' => $id]);
            continue;
        }
    }

    if ($scr === 'WRKSCR') {
        if (preg_match('/^PANEL\s+(FILE|SAISIE|REPORTES|POINTS)$/i', $line, $m)) {
            $ctx['panel'] = strtolower($m[1]);
            continue;
        }
        if ($u === 'CATCHUP' && t_confirm('  Rattrapage API')) {
            t_run($pdo, 'catchup_scores');
            continue;
        }
        if ($u === 'LOCAL') {
            t_run($pdo, 'score_local');
            continue;
        }
        if (isset($parts[0]) && strtoupper($parts[0]) === 'S' && isset($parts[1], $parts[2], $parts[3])) {
            t_run($pdo, 'manual_score', [
                'match_id' => (int) $parts[1],
                'score_home' => (int) $parts[2],
                'score_away' => (int) $parts[3],
            ]);
            continue;
        }
        if (($parts[0] ?? '') === '4' && isset($parts[1])) {
            $reason = $parts[2] ?? 'autre';
            if (t_confirm('  Annuler match #' . $parts[1])) {
                t_run($pdo, 'cancel_match', ['match_id' => (int) $parts[1], 'cancel_reason' => $reason]);
            }
            continue;
        }
        if (($parts[0] ?? '') === '5' && isset($parts[1])) {
            $ctx['id'] = (int) $parts[1];
            $scr = 'DSPMCH';
            continue;
        }
        if (($parts[0] ?? '') === '6' && isset($parts[1])) {
            t_run($pdo, 'postpone_match', ['match_id' => (int) $parts[1]]);
            continue;
        }
        if (($parts[0] ?? '') === '7' && isset($parts[1])) {
            t_run($pdo, 'postpone_reactivate', ['match_id' => (int) $parts[1]]);
            continue;
        }
        if (($parts[0] ?? '') === '9' && isset($parts[1]) && t_confirm('  Effacer score')) {
            t_run($pdo, 'clear_match_score', ['match_id' => (int) $parts[1]]);
            continue;
        }
        if ($u === 'RECOVER' && t_confirm('  Recuperer reportes')) {
            t_run($pdo, 'recover_postponed_scores');
            continue;
        }
    }

    if ($scr === 'WRKOPS') {
        $cmd = strtoupper($parts[0] ?? '');
        $ops = [
            'QUOTA' => 'probe_quota',
            'LOCAL' => 'score_local',
            'MATCHES' => 'matches',
            'ODDS' => 'odds',
            'CRON' => 'cron',
            'CATCHUP' => 'catchup_scores',
            'LOCK' => 'clear_lock',
            'PRUNE' => 'prune',
        ];
        if (isset($ops[$cmd])) {
            $need = in_array($cmd, ['MATCHES', 'ODDS', 'CRON', 'CATCHUP', 'PRUNE'], true);
            if ($need && !t_confirm('  ' . $cmd)) {
                continue;
            }
            $p = $cmd === 'LOCAL' ? ['close_expired' => 1] : [];
            t_run($pdo, $ops[$cmd], $p);
            continue;
        }
    }

    if ($scr === 'WRKMSG') {
        if (strtoupper($parts[0] ?? '') === 'COM' && isset($parts[1])) {
            $ctx['community_id'] = (int) $parts[1];
            continue;
        }
        if (strtoupper($parts[0] ?? '') === 'Q') {
            $ctx['q'] = $parts[1] ?? '';
            continue;
        }
        $opt = $parts[0] ?? '';
        $id = (int) ($parts[1] ?? 0);
        if ($opt === '4' && $id) {
            t_run($pdo, 'soft_delete', ['message_id' => $id]);
            continue;
        }
        if ($opt === '2' && $id) {
            t_run($pdo, 'restore', ['message_id' => $id]);
            continue;
        }
        if ($opt === '9' && $id && t_confirm('  Effacer BDD')) {
            t_run($pdo, 'hard_delete', ['message_id' => $id]);
            continue;
        }
    }

    if ($scr === 'WRKSEA') {
        if ($u === 'CLOSE' && t_confirm('  Cloturer maintenant')) {
            t_run($pdo, 'close_now');
            continue;
        }
        if ($u === 'MONTH' && t_confirm('  Fin 1er du mois')) {
            t_run($pdo, 'schedule_month');
            continue;
        }
        if (strtoupper($parts[0] ?? '') === 'FIN' && isset($parts[1], $parts[2])) {
            t_run($pdo, 'schedule_custom', ['fin' => $parts[1] . ' ' . $parts[2]]);
            continue;
        }
    }

    if ($scr === 'WRKEVT') {
        $opt = $parts[0] ?? '';
        $id = (int) ($parts[1] ?? 0);
        if ($opt === '4' && $id && t_confirm('  Supprimer evenement')) {
            t_run($pdo, 'event_delete', ['id' => $id]);
            continue;
        }
        if ($opt === '6' && $id) {
            t_run($pdo, 'event_toggle', ['id' => $id]);
            continue;
        }
        if ($opt === '7' && $id) {
            t_run($pdo, 'event_publish', ['id' => $id, 'notify_on_publish' => 1]);
            continue;
        }
        if ($opt === '8' && $id) {
            t_run($pdo, 'event_notify', ['id' => $id]);
            continue;
        }
    }

    if ($scr === 'WRKANN') {
        $opt = $parts[0] ?? '';
        $id = (int) ($parts[1] ?? 0);
        if ($opt === '4' && $id && t_confirm('  Supprimer annonce')) {
            t_run($pdo, 'ann_delete', ['id' => $id]);
            continue;
        }
        if ($opt === '7' && $id) {
            t_run($pdo, 'ann_publish', ['id' => $id]);
            continue;
        }
        if (strtoupper($parts[0] ?? '') === 'NEW' && isset($parts[1])) {
            $rest = substr($line, 4);
            $bits = explode('|', $rest, 2);
            t_run($pdo, 'ann_save', [
                'title' => trim($bits[0]),
                'body' => trim($bits[1] ?? $bits[0]),
                'published' => 1,
            ]);
            continue;
        }
    }

    if ($scr === 'WRKRPT') {
        if ($u === 'DIAG') {
            t_run($pdo, 'report_unavailable');
            continue;
        }
        if ($u === 'MONTH' && t_confirm('  Envoyer rapport du mois')) {
            t_run($pdo, 'report_month');
            continue;
        }
    }

    $tFlash = ['ok' => false, 'type' => 'error', 'message' => 'CPF0006 - Commande non reconnue.'];
}
