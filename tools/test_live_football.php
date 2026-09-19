<?php
/**
 * Tests unitaires — live football (sans BDD / sans API).
 * Usage : php tools/test_live_football.php
 * Exit 0 = OK, 1 = échecs.
 */
declare(strict_types=1);

define('APP_BOOT', true);

require dirname(__DIR__) . '/app/env.php';
loadEnvFile(dirname(__DIR__) . '/.env');
require dirname(__DIR__) . '/app/config.php';
require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/scoring.php';
require dirname(__DIR__) . '/app/live_football.php';

$failed = 0;
$passed = 0;

function assert_true(bool $cond, string $label): void
{
    global $failed, $passed;
    if ($cond) {
        $passed++;
        echo "  OK  {$label}\n";
    } else {
        $failed++;
        echo "FAIL  {$label}\n";
    }
}

function assert_eq(mixed $expected, mixed $actual, string $label): void
{
    assert_true($expected === $actual, $label . ' (expected ' . json_encode($expected) . ', got ' . json_encode($actual) . ')');
}

echo "=== live football unit tests ===\n";

// --- normalize / similarité ---
assert_true(liveFootballNormalizeTeam('Paris Saint-Germain') !== '', 'normalize PSG non vide');
assert_true(
    liveFootballTeamSimilarity('Paris Saint Germain', 'Paris Saint-Germain') >= 90,
    'PSG vs Paris Saint-Germain ≈ match'
);
assert_true(
    liveFootballTeamSimilarity('Olympique de Marseille', 'Marseille') >= 70,
    'OM vs Marseille similarité suffisante'
);
assert_true(
    liveFootballTeamSimilarity('Real Madrid', 'Barcelona') < 50,
    'Real vs Barça ne matchent pas'
);

// --- appariement scores ---
$linked = liveFootballMatchFixtureTeams(
    'Paris Saint Germain',
    'Marseille',
    'Paris Saint-Germain',
    'Olympique Marseille',
    2,
    1
);
assert_true($linked !== null, 'appariement PSG-OM trouvé');
assert_eq(2, $linked['home'] ?? null, 'score domicile orienté');
assert_eq(1, $linked['away'] ?? null, 'score extérieur orienté');

$swapped = liveFootballMatchFixtureTeams(
    'Marseille',
    'Paris Saint Germain',
    'Paris Saint-Germain',
    'Olympique Marseille',
    2,
    1
);
assert_true($swapped !== null, 'appariement inversé trouvé');
assert_eq(1, $swapped['home'] ?? null, 'swap : domicile = buts away API');
assert_eq(2, $swapped['away'] ?? null, 'swap : extérieur = buts home API');

$nope = liveFootballMatchFixtureTeams(
    'Lyon',
    'Lille',
    'Paris Saint-Germain',
    'Marseille',
    1,
    0
);
assert_true($nope === null, 'mauvais match → null');

// --- horloge ---
assert_eq("67'", liveFootballFormatClock(['short' => '2H', 'elapsed' => 67, 'extra' => null]), '67e minute');
assert_eq("45+2'", liveFootballFormatClock(['short' => '1H', 'elapsed' => 45, 'extra' => 2]), 'arrêts de jeu');
assert_eq('MT', liveFootballFormatClock(['short' => 'HT', 'elapsed' => 45]), 'mi-temps');
assert_eq('Fin', liveFootballFormatClock(['short' => 'FT', 'elapsed' => 90]), 'fin de match');

// --- parse fixture ---
$parsed = liveFootballParseFixture([
    'fixture' => ['status' => ['short' => '2H', 'elapsed' => 71, 'extra' => null]],
    'teams'   => [
        'home' => ['name' => 'Paris Saint Germain'],
        'away' => ['name' => 'Marseille'],
    ],
    'goals'   => ['home' => 1, 'away' => 0],
]);
assert_true($parsed !== null, 'parse fixture OK');
assert_eq(true, $parsed['in_play'] ?? false, 'in_play');
assert_eq("71'", $parsed['clock'] ?? '', 'clock parse');

// --- build snapshots ---
$fixtures = [[
    'fixture' => ['status' => ['short' => '2H', 'elapsed' => 55]],
    'teams'   => [
        'home' => ['name' => 'FC Bayern München'],
        'away' => ['name' => 'Borussia Dortmund'],
    ],
    'goals'   => ['home' => 3, 'away' => 1],
]];
$tracked = [[
    'id'          => 42,
    'equipe_home' => 'Bayern Munich',
    'equipe_away' => 'Dortmund',
]];
$snaps = liveFootballBuildSnapshots($fixtures, $tracked);
assert_true(isset($snaps['42']), 'snapshot match 42');
assert_eq(3, $snaps['42']['home'] ?? null, 'snapshot score home');
assert_eq(1, $snaps['42']['away'] ?? null, 'snapshot score away');
assert_eq("55'", $snaps['42']['clock'] ?? '', 'snapshot clock');

// --- merge display ---
$upcoming = [
    ['id' => 1, 'equipe_home' => 'A', 'equipe_away' => 'B'],
    ['id' => 2, 'equipe_home' => 'C', 'equipe_away' => 'D'],
];
$live = [
    ['id' => 9, 'equipe_home' => 'Live', 'equipe_away' => 'Now'],
    ['id' => 2, 'equipe_home' => 'C', 'equipe_away' => 'D'],
];
$merged = mergeLiveSoccerMatchesForDisplay($upcoming, $live);
assert_eq(3, count($merged), 'merge = live + upcoming sans doublon');
assert_eq(9, (int) $merged[0]['id'], 'live en tête');
assert_eq(true, !empty($merged[0]['live_track']), 'flag live_track');
$ids = array_map(static fn ($m) => (int) $m['id'], $merged);
assert_eq([9, 2, 1], $ids, 'ordre merge attendu');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
