<?php
/**
 * Administration Prognoz — terminal type Minitel / service vidéotex.
 *
 *   ssh ...
 *   cd /var/www/prognoz
 *   php tools/ops_terminal.php
 */
define('APP_BOOT', true);

$root = dirname(__DIR__);
require_once $root . '/app/env.php';
loadEnvFile($root . '/.env');
require_once $root . '/app/config.php';
require_once $root . '/app/helpers.php';
require_once $root . '/app/db.php';
require_once $root . '/app/encryption.php';
require_once $root . '/app/odds_api.php';
require_once $root . '/app/scoring.php';
require_once $root . '/app/seasons.php';
require_once $root . '/app/matches.php';
require_once $root . '/app/friends.php';
require_once $root . '/app/user_predictions.php';
require_once $root . '/app/admin_auth.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

const T_W = 52;

function t_cls(): void
{
    // ANSI clear + home (ignoré si terminal basique)
    echo "\033[2J\033[H";
}

function t_out(string $msg = ''): void
{
    echo $msg . PHP_EOL;
}

function t_rule(string $ch = '='): void
{
    t_out(str_repeat($ch, T_W));
}

function t_center(string $text): void
{
    $text = substr($text, 0, T_W);
    $pad = max(0, (int) floor((T_W - strlen($text)) / 2));
    t_out(str_repeat(' ', $pad) . $text);
}

function t_header(string $screen): void
{
    t_cls();
    t_rule('=');
    t_center('PROGNOZ');
    t_center('SERVICE ADMINISTRATION');
    t_rule('=');
    t_out(' ' . $screen);
    t_rule('-');
}

function t_read(string $prompt): string
{
    echo $prompt;
    $line = fgets(STDIN);
    return $line === false ? '' : trim($line);
}

function t_pause(): void
{
    t_out('');
    t_read('  [ENTREE] pour revenir au menu... ');
}

function t_auth(): void
{
    if (!adminPanelConfigured()) {
        t_header('ERREUR CONFIG');
        t_out(' ADMIN_* absent du .env');
        t_out(' => php tools/generate_admin_credentials.php');
        t_out('');
        exit(1);
    }

    t_header('CONNEXION');
    t_out('');
    t_out(' Identifiez-vous pour accéder au service.');
    t_out('');
    $user = t_read(' Utilisateur : ');
    echo ' Mot de passe : ';
    // Masquage basique (Linux/mac). Sous Windows PowerShell l'écho reste visible.
    $hidden = false;
    if (strncasecmp(PHP_OS, 'WIN', 3) !== 0 && function_exists('shell_exec')) {
        $stty = @shell_exec('stty -g 2>/dev/null');
        if (is_string($stty) && $stty !== '') {
            @shell_exec('stty -echo');
            $hidden = true;
        }
    }
    $passLine = fgets(STDIN);
    $pass = $passLine === false ? '' : trim($passLine);
    if ($hidden) {
        @shell_exec('stty ' . escapeshellarg(trim($stty)));
        t_out('');
    }
    t_out('');

    if (!hash_equals(ADMIN_USERNAME, $user) || !password_verify($pass, ADMIN_PASSWORD_HASH)) {
        t_out(' *** ACCES REFUSE ***');
        t_out('');
        exit(1);
    }
}

function t_menu_home(): string
{
    t_header('MENU PRINCIPAL');
    t_out('');
    t_out('  1  Etat du systeme');
    t_out('  2  Matchs bloques (liste)');
    t_out('  3  Saisir un score');
    t_out('  4  Annuler un match');
    t_out('  5  Points joueur (+/-)');
    t_out('  6  Attribuer points locaux');
    t_out('  7  Purger la base');
    t_out('  8  Liberer verrou sync');
    t_out('');
    t_out('  0  Fin de connexion');
    t_out('');
    t_rule('-');
    return t_read(' Votre choix : ');
}

function t_status(PDO $pdo): void
{
    t_header('1 · ETAT DU SYSTEME');
    $pending = countPendingPredictions($pdo);
    $quota = oddsQuotaState();
    $users = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE actif = 1')->fetchColumn();
    $upcoming = (int) $pdo->query(
        "SELECT COUNT(*) FROM matches WHERE statut = 'a_venir' AND date_match > UTC_TIMESTAMP()"
    )->fetchColumn();

    t_out('');
    t_out(sprintf('  Joueurs actifs      %d', $users));
    t_out(sprintf('  Matchs a venir      %d', $upcoming));
    t_out(sprintf('  Pronos en attente   %d', (int) $pending['pending']));
    t_out(sprintf('  Matchs bloques      %d', (int) $pending['stuck']));
    t_out(sprintf(
        '  Quota API restant   %s',
        $quota['remaining'] !== null ? (string) (int) $quota['remaining'] : '?'
    ));
    t_out('');
    t_pause();
}

/** @return list<array<string,mixed>> */
function t_list_stuck(PDO $pdo, bool $header = true): array
{
    if ($header) {
        t_header('2 · MATCHS BLOQUES');
    }
    $rows = listStuckMatchesForManualScore($pdo, 30);
    t_out('');
    if ($rows === []) {
        t_out('  (aucun match bloque)');
        t_out('');
        return [];
    }
    foreach ($rows as $i => $m) {
        t_out(sprintf(
            '  %2d  #%d',
            $i + 1,
            (int) $m['id']
        ));
        t_out(sprintf(
            '      %s - %s',
            $m['equipe_home'],
            $m['equipe_away']
        ));
        t_out(sprintf(
            '      %s | pronos=%d',
            $m['competition'] ?: $m['sport'],
            (int) $m['pending_count']
        ));
        t_out('');
    }
    return $rows;
}

function t_score(PDO $pdo): void
{
    t_header('3 · SAISIE SCORE');
    $rows = t_list_stuck($pdo, false);
    if ($rows === []) {
        t_pause();
        return;
    }
    $pick = (int) t_read('  N° de ligne : ');
    if ($pick < 1 || $pick > count($rows)) {
        t_out('  Choix invalide.');
        t_pause();
        return;
    }
    $m = $rows[$pick - 1];
    t_out('');
    t_out('  ' . $m['equipe_home'] . ' - ' . $m['equipe_away']);
    $home = (int) t_read('  Score domicile  : ');
    $away = (int) t_read('  Score exterieur : ');
    try {
        applyManualMatchScore($pdo, (int) $m['id'], $home, $away);
        t_out('');
        t_out('  *** SCORE ENREGISTRE ***');
    } catch (Throwable $e) {
        t_out('');
        t_out('  Erreur : ' . $e->getMessage());
    }
    t_pause();
}

function t_cancel(PDO $pdo): void
{
    t_header('4 · ANNULER MATCH');
    $rows = t_list_stuck($pdo, false);
    if ($rows === []) {
        t_pause();
        return;
    }
    $pick = (int) t_read('  N° de ligne : ');
    if ($pick < 1 || $pick > count($rows)) {
        t_out('  Choix invalide.');
        t_pause();
        return;
    }
    $m = $rows[$pick - 1];
    t_out('');
    t_out('  ' . $m['equipe_home'] . ' - ' . $m['equipe_away']);
    $ok = strtolower(t_read('  Confirmer (O/N) : '));
    if ($ok !== 'o' && $ok !== 'oui') {
        t_out('  Abandon.');
        t_pause();
        return;
    }
    try {
        $n = cancelMatch($pdo, (int) $m['id']);
        t_out('');
        t_out("  *** MATCH ANNULE ({$n} pronos a 0 pt) ***");
    } catch (Throwable $e) {
        t_out('  Erreur : ' . $e->getMessage());
    }
    t_pause();
}

function t_points(PDO $pdo): void
{
    t_header('5 · POINTS JOUEUR');
    t_out('');
    $pseudo = t_read('  Pseudo     : ');
    $delta = (int) t_read('  Points +/- : ');
    $season = strtolower(t_read('  Saison aussi (O/N) : '));
    $toSeason = !($season === 'n' || $season === 'non');
    $u = findUserByPseudo($pdo, $pseudo);
    if (!$u) {
        t_out('  Pseudo introuvable.');
        t_pause();
        return;
    }
    try {
        $r = grantUserPoints($pdo, (int) $u['id'], $delta, $toSeason);
        $sign = $r['delta'] > 0 ? '+' . $r['delta'] : (string) $r['delta'];
        t_out('');
        t_out('  ' . $r['pseudo'] . ' : ' . $sign . ' -> total ' . $r['points_totaux']);
    } catch (Throwable $e) {
        t_out('  Erreur : ' . $e->getMessage());
    }
    t_pause();
}

// --- main ---
t_auth();
$pdo = getPDO();

while (true) {
    $choice = t_menu_home();
    switch ($choice) {
        case '1':
            t_status($pdo);
            break;
        case '2':
            t_list_stuck($pdo, true);
            t_pause();
            break;
        case '3':
            t_score($pdo);
            break;
        case '4':
            t_cancel($pdo);
            break;
        case '5':
            t_points($pdo);
            break;
        case '6':
            t_header('6 · POINTS LOCAUX');
            $n = scorePendingFinishedMatches($pdo);
            t_out('');
            t_out("  Matchs traites : {$n}");
            t_pause();
            break;
        case '7':
            t_header('7 · PURGE BDD');
            $p = pruneStaleMatchData($pdo);
            t_out('');
            t_out('  score_options : ' . $p['score_options']);
            t_out('  buteurs       : ' . $p['buteur_options']);
            t_out('  empty_markets : ' . ($p['empty_markets'] ?? 0));
            t_out('  old_matches   : ' . ($p['old_matches'] ?? $p['orphan_matches'] ?? 0));
            t_out('  kept_errors   : ' . ($p['kept_errors'] ?? 0));
            t_pause();
            break;
        case '8':
            t_header('8 · VERROU SYNC');
            $lock = clearIdleSyncLock();
            t_out('');
            t_out($lock['busy'] ? '  Encore verrouille.' : '  Verrou libre.');
            t_pause();
            break;
        case '0':
            t_header('FIN DE CONNEXION');
            t_out('');
            t_center('Au revoir.');
            t_out('');
            t_rule('=');
            exit(0);
        default:
            t_out('');
            t_out('  Choix invalide.');
            t_pause();
    }
}
