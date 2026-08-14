<?php
/**
 * Bascule de saison (horloge Europe/Paris, pas MySQL NOW()).
 *
 * Usage :
 *   php tools/prepare_season_reset.php
 *   php tools/prepare_season_reset.php --apply
 *   php tools/prepare_season_reset.php --close-now   # clôture immédiate + ouvre la suivante
 */
define('APP_BOOT', true);

$root = dirname(__DIR__);
require_once $root . '/app/env.php';
loadEnvFile($root . '/.env');
require_once $root . '/app/config.php';
require_once $root . '/app/helpers.php';
require_once $root . '/app/db.php';
require_once $root . '/app/encryption.php';
require_once $root . '/app/push.php';
require_once $root . '/app/user_predictions.php';
require_once $root . '/app/seasons.php';
require_once $root . '/app/odds_api.php';
require_once $root . '/app/scoring.php';
require_once $root . '/app/matches.php';

$apply    = in_array('--apply', $argv, true);
$closeNow = in_array('--close-now', $argv, true);
$at       = nextMonthStartDatetime();
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--at=')) {
        $at = substr($arg, 5);
    }
}

function line(string $label, $value): void
{
    printf("  %-28s %s\n", $label, $value);
}

try {
    $pdo = getPDO();
    ensureSeasonSchema($pdo);

    echo "=== Fuseau / horloge ===\n";
    line('PHP timezone', appTimezone()->getName());
    line('Horloge saisons', seasonClockNow());
    line('MySQL NOW()', (string) $pdo->query('SELECT NOW()')->fetchColumn());
    line('MySQL UTC_TIMESTAMP()', (string) $pdo->query('SELECT UTC_TIMESTAMP()')->fetchColumn());
    line('Durée saison suivante', SAISON_DUREE_JOURS . ' jours');

    echo "\n=== Saisons ===\n";
    foreach ($pdo->query('SELECT id, debut, fin, cloturee FROM seasons ORDER BY id DESC LIMIT 8') as $r) {
        printf(
            "  #%d  %s → %s  [%s]\n",
            (int) $r['id'],
            $r['debut'],
            $r['fin'],
            !empty($r['cloturee']) ? 'clôturée' : 'ACTIVE'
        );
    }

    if ($closeNow) {
        echo "\n=== Clôture forcée (--close-now) ===\n";
        $beforeId = (int) ($pdo->query(
            'SELECT id FROM seasons WHERE cloturee = 0 ORDER BY debut DESC LIMIT 1'
        )->fetchColumn() ?: 0);

        $season = maintainSeasons($pdo);

        if ($beforeId > 0) {
            $st = $pdo->prepare('SELECT cloturee FROM seasons WHERE id = ?');
            $st->execute([$beforeId]);
            line('Saison #' . $beforeId, ((int) $st->fetchColumn() === 1) ? 'CLÔTURÉE' : 'encore ouverte');
        }
        line(
            'Saison active',
            $season ? '#' . $season['id'] . ' → ' . $season['fin'] : 'aucune'
        );
        echo "\nRecharge le site (Ctrl+F5).\n";
        exit(0);
    }

    $active = $pdo->query(
        'SELECT id, debut, fin FROM seasons WHERE cloturee = 0 ORDER BY debut DESC LIMIT 1'
    )->fetch();

    echo "\n=== Plan (schedule) ===\n";
    line('Fin ciblée', $at);
    if ($active) {
        line('Saison active', '#' . $active['id'] . ' (fin actuelle ' . $active['fin'] . ')');
    } else {
        line('Saison active', 'aucune — sera créée');
    }

    if ($active && $active['fin'] === $at) {
        echo "\nDéjà calée — rien à faire. Pour clôturer maintenant : --close-now\n";
        exit(0);
    }

    if (!$apply) {
        echo "\nDry-run. --apply pour planifier, --close-now pour clôturer tout de suite.\n";
        exit(0);
    }

    $result = scheduleActiveSeasonEnd($pdo, $at);
    echo "\nOK — saison #{$result['season']['id']} : fin = {$result['season']['fin']}\n";
    echo "Prochaine (auto) jusqu'au {$result['next_end']}.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
