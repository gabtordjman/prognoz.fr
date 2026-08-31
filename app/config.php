<?php
/**
 * Configuration générale.
 */
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

define('APP_NAME', 'Prognoz');
define('APP_VERSION', '1.3.0');
define('APP_BETA', envBool('APP_BETA', false));
define('APP_MAINTENANCE', envBool('APP_MAINTENANCE', false));
define('APP_CONTACT_EMAIL', env('APP_CONTACT_EMAIL', 'contact@example.com'));
/** Boîte admin pour alertes résultats indisponibles / rapports. */
define('ADMIN_NOTIFY_EMAIL', env('ADMIN_NOTIFY_EMAIL', 'admin@prognoz.fr'));
define('APP_OG_DESCRIPTION', env(
    'APP_OG_DESCRIPTION',
    'Pariez entre amis sur le foot, le basket et le tennis. Cumulez des points, créez des communautés privées et défiez vos potes — sans bookmaker, 100 % gratuit.'
));

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'pronosocial'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');

define('POINTS_1X2', 1);
define('POINTS_BUTEUR', 2);
define('POINTS_SCORE_EXACT', 3);
/** Points de base marché équipe préférée (avant ×2 si gagné). */
define('POINTS_FAV_TEAM', 2);
/** Bonus ×2 uniquement sur le marché fav_team gagné. */
define('FAV_TEAM_WIN_MULTIPLIER', 2);
/** Nombre max de sélections nationales préférées (en plus du club). */
define('FAV_TEAMS_MAX', 3);

/** Pénalités sur prono raté (sans multiplicateur streak/event). 1x2 = 0 (reset série seulement). */
define('PENALTY_1X2', 0);
define('PENALTY_BUTEUR', 1);
define('PENALTY_FAV_TEAM', 1);
define('PENALTY_SCORE_EXACT', 2);

/** Seuil points (24 h) pour le bandeau « perf » sur l’accueil. */
define('HIGHLIGHT_POINTS_24H', 50);
/** Seuil série en cours pour le même bandeau. */
define('HIGHLIGHT_STREAK_MIN', 5);

/**
 * Multiplicateur de points selon la série 1x2 après le gain en cours.
 * Clés = série minimale atteinte (ordre décroissant à tester).
 */
define('STREAK_POINT_MULTIPLIERS', [
    5 => 2.5,
    3 => 2.0,
    2 => 1.5,
]);

define('SAISON_DUREE_JOURS', 14);

/** Bonus podium fin de saison (top 1 / 2 / 3) — ajoutés au score final de la saison. */
define('SEASON_PODIUM_BONUS', [1 => 5, 2 => 3, 3 => 1]);
define('SEASON_REWARD_LABELS', [1 => 'Badge Or', 2 => 'Badge Argent', 3 => 'Badge Bronze']);

define('MATCHS_HORIZON_JOURS', 7);
define('MATCHS_IMPORT_HORIZON_JOURS', 14); // import BDD : plus large que l'affichage
/** Max matchs affichés par catégorie (foot / basket / tennis). */
define('MATCHS_PAR_CATEGORIE', 25);
define('MATCHS_AFFICHES', MATCHS_PAR_CATEGORIE * 3);

define('COMMON_SCORES', [
    // Victoire domicile
    '1-0', '2-0', '2-1', '3-0', '3-1', '3-2',
    '4-0', '4-1', '4-2', '4-3',
    '5-0', '5-1', '5-2', '5-3',
    '6-0', '6-1', '6-2', '6-3',
    // Nuls
    '0-0', '1-1', '2-2', '3-3', '4-4',
    // Victoire extérieur
    '0-1', '0-2', '1-2', '0-3', '1-3', '2-3',
    '0-4', '1-4', '2-4', '3-4',
    '0-5', '1-5', '2-5', '3-5',
    '0-6', '1-6', '2-6', '3-6',
]);
/** Plafond buts pour un score exact saisi librement (hors grille). */
define('EXACT_SCORE_CUSTOM_MAX', 20);
/** Matchs des mois précédents : purge possible (sauf erreurs à corriger). */
define('MATCH_ORPHAN_RETENTION_DAYS', 21);
/** Options buteurs : inutiles une fois le match fini — purge après N jours. */
define('BUTEUR_OPTIONS_RETENTION_DAYS', 7);

define('ODDS_API_KEY', env('ODDS_API_KEY', ''));
define('ODDS_API_BASE', 'https://api.the-odds-api.com');
define('ODDS_SPORT_GROUPS', ['Tennis', 'Basketball', 'Soccer']);

/** Ligues prioritaires par groupe — ordre d'affichage (sync inclut tout le catalogue API actif). */
define('ODDS_SPORT_PRIORITY', [
    'Tennis' => [
        // Grands Chelems
        'tennis_atp_french_open', 'tennis_wta_french_open',
        'tennis_atp_wimbledon', 'tennis_wta_wimbledon',
        'tennis_atp_us_open', 'tennis_wta_us_open',
        'tennis_atp_aus_open_singles', 'tennis_wta_aus_open_singles',
        // Masters 1000 / 500 / ATP 250 & WTA (catalogue The Odds API)
        'tennis_atp_indian_wells', 'tennis_wta_indian_wells',
        'tennis_atp_miami_open', 'tennis_wta_miami_open',
        'tennis_atp_monte_carlo_masters', 'tennis_wta_charleston_open',
        'tennis_atp_madrid_open', 'tennis_wta_madrid_open',
        'tennis_atp_italian_open', 'tennis_wta_italian_open',
        'tennis_atp_hamburg_open', 'tennis_wta_german_open',
        'tennis_atp_halle_open', 'tennis_wta_bad_homburg_open',
        'tennis_atp_queens_club_champ', 'tennis_wta_queens_club_champ',
        'tennis_atp_canadian_open', 'tennis_wta_canadian_open',
        'tennis_atp_cincinnati_open', 'tennis_wta_cincinnati_open',
        'tennis_atp_shanghai_masters', 'tennis_wta_wuhan_open',
        'tennis_atp_paris_masters', 'tennis_wta_china_open',
        'tennis_atp_dubai', 'tennis_wta_dubai',
        'tennis_atp_qatar_open', 'tennis_wta_qatar_open',
        'tennis_atp_barcelona_open', 'tennis_wta_stuttgart_open',
        'tennis_atp_munich', 'tennis_wta_strasbourg',
        'tennis_atp_china_open',
    ],
    'Basketball' => [
        // Hommes d’abord (saison / volume Odds API)
        'basketball_nba',
        'basketball_euroleague',
        'basketball_ncaab',
        'basketball_nbl',
        'basketball_nba_preseason',
        'basketball_nba_summer_league',
        // Femmes ensuite
        'basketball_wnba',
        'basketball_wncaab',
    ],
    'Soccer' => [
        // Grands championnats + coupes européennes
        'soccer_france_ligue_one',
        'soccer_epl',
        'soccer_spain_la_liga',
        'soccer_germany_bundesliga',
        'soccer_italy_serie_a',
        'soccer_uefa_champs_league',
        'soccer_uefa_europa_league',
        'soccer_uefa_europa_conference_league',
        // Sélections nationales
        'soccer_fifa_world_cup',
        'soccer_uefa_european_championship',
        'soccer_uefa_nations_league',
        'soccer_belgium_first_div',
        'soccer_netherlands_eredivisie',
        'soccer_portugal_primeira_liga',
        // Championnats estivaux / nordiques (été, trêve des grands championnats)
        'soccer_poland_ekstraklasa',
        'soccer_denmark_superliga',
        'soccer_norway_eliteserien',
        'soccer_sweden_allsvenskan',
        'soccer_sweden_superettan',
        'soccer_finland_veikkausliiga',
        'soccer_greece_super_league',
        'soccer_turkey_super_league',
        'soccer_austria_bundesliga',
        'soccer_switzerland_superleague',
        'soccer_spl',
        'soccer_russia_premier_league',
        'soccer_league_of_ireland',
        // Secondes divisions & anglais
        'soccer_spain_segunda_division',
        'soccer_efl_champ',
        'soccer_france_ligue_two',
        'soccer_germany_bundesliga2',
        'soccer_italy_serie_b',
        // Amériques & autres
        'soccer_usa_mls',
        'soccer_brazil_campeonato',
        'soccer_mexico_ligamx',
        'soccer_argentina_primera_division',
    ],
]);

define('ODDS_CACHE_TTL_SPORTS', 21600);
define('ODDS_CACHE_TTL_EVENTS', 14400);
define('ODDS_CACHE_TTL_SCORES', 7200); // 2 h : un /scores réussi nourrit plusieurs passes (0 crédit)
define('ODDS_CACHE_TTL_ODDS', 21600); // 6 h : les % affichés changent peu ; BDD fait foi

define('APP_CACHE_DIR', dirname(__DIR__) . '/var/cache');
define('CRON_SECRET', env('CRON_SECRET', ''));
/** IDs utilisateurs autorisés à lancer une sync complète depuis Paramètres (CSV, ex. "1,3"). */
define('SYNC_ADMIN_USER_IDS', array_values(array_filter(array_map(
    'intval',
    preg_split('/\s*,\s*/', (string) env('SYNC_ADMIN_USER_IDS', ''), -1, PREG_SPLIT_NO_EMPTY) ?: []
))));
/** Badge ADMIN sur le site (CSV). Défaut : compte propriétaire id 2. */
define('SITE_ADMIN_USER_IDS', array_values(array_unique(array_filter(array_map(
    'intval',
    preg_split('/\s*,\s*/', (string) env('SITE_ADMIN_USER_IDS', '2'), -1, PREG_SPLIT_NO_EMPTY) ?: []
)))));
/** Rappels match du jour par e-mail (0 = push seulement, recommandé). */
define('REMIND_MAIL_ENABLED', (int) env('REMIND_MAIL_ENABLED', '0') === 1);

/** Panel admin web — URL secrète /admin/?s=SLUG + compte dédié (hors table users). */
define('ADMIN_PANEL_SLUG', trim((string) env('ADMIN_PANEL_SLUG', '')));
define('ADMIN_USERNAME', trim((string) env('ADMIN_USERNAME', '')));
define('ADMIN_PASSWORD_HASH', trim((string) env('ADMIN_PASSWORD_HASH', '')));

// Région bookmakers (1 région = 1× le coût du marché).
// Fallback UK DÉSACTIVÉ : un 2ᵉ appel vide double le coût pour souvent 0 cote.
define('ODDS_REGIONS', 'eu');
define('ODDS_REGIONS_FALLBACK', '');
define('ODDS_SCORER_REGIONS', 'us'); // props joueurs foot : bookmakers US uniquement

// Ligues où l'API expose les buteurs (event-odds, région us)
define('ODDS_SCORER_SPORTS', [
    'soccer_epl',
    'soccer_france_ligue_one',
    'soccer_germany_bundesliga',
    'soccer_italy_serie_a',
    'soccer_spain_la_liga',
    'soccer_usa_mls',
    'soccer_uefa_champs_league',
    'soccer_belgium_first_div',
    'soccer_netherlands_eredivisie',
    'soccer_portugal_primeira_liga',
]);

// Synchro API : intervalle minimum entre deux sync (secondes)
define('SYNC_INTERVAL_SECONDS', 3600);
define('CACHE_REFRESH_INTERVAL_SECONDS', 300); // rotation matchs depuis cache : 5 min, 0 crédit API
// /scores avec daysFrom = 2 crédits / ligue. Budget mensuel gratuit Odds API = 500.
// Priorité absolue : BDD + cache fichier. L'API n'est qu'un appoint rare.
define('SCORES_SYNC_INTERVAL_SECONDS', 3600); // max 1 passe scores / heure
define('SCORES_SYNC_INTERVAL_LOW_QUOTA', 7200); // sous 100 crédits : 1 passe / 2 h
define('SCORES_CATCHUP_DAYS', 3); // daysFrom (toujours 2 crédits ; 1 ou 3 = même coût)
define('MATCH_RESULT_READY_MINUTES', 150); // délai avant d'interroger /scores (match réellement fini)
define('SCORES_MAX_SPORTS_PER_RUN', 1); // cron calme : 1 ligue / passe (= 2 crédits)
define('SCORES_MAX_SPORTS_BACKLOG', 4); // si plusieurs ligues bloquées : jusqu’à 4 / passe (quota permitting)
define('SCORES_MAX_SPORTS_LOW_QUOTA', 1);
define('SCORES_ADMIN_CATCHUP_MAX_SPORTS', 15); // bouton admin « rattrapage » : multi-ligues d’un coup
define('RESULT_MAX_WAIT_DAYS', 3); // aligné sur SCORES_CATCHUP_DAYS (fenêtre API /scores)
define('PENDING_SCORE_INTERVAL_SECONDS', 120); // rattrapage points sur page web : max 1× / 2 min
define('MATCH_CLOSE_AFTER_MINUTES', 30); // match retiré de l'affichage après coup d'envoi + délai
define('ODDS_SYNC_INTERVAL_SECONDS', 21600); // cotes : max 1 sync auto / 6 h (jamais en cron)
define('ODDS_FORCE_MAX_SPORTS', 3); // admin « rafraîchir cotes » : max 3 ligues SANS probas
define('SCORERS_SYNC_INTERVAL_SECONDS', 86400); // buteurs : pas en auto (1 crédit / match)
define('ODDS_QUOTA_RESERVE_SCORES', 8); // sous ce seuil : plus aucun appel payant
define('ODDS_QUOTA_RESERVE_ODDS', 60); // sous ce seuil : scores OK, cotes/buteurs interdits
define('ODDS_QUOTA_LOW', 100); // sous ce seuil : intervalle scores doublé
define('ODDS_API_TIMEOUT', 6);
define('SYNC_LOCK_MAX_AGE', 600); // verrou considéré périmé après 10 min
define('SYNC_MAX_SPORTS_PER_GROUP', 8); // tennis / basket : pas de plafond effectif (voir oddsSportsForSync)
define('SYNC_MAX_SOCCER_SPORTS', 40); // foot : inclure toutes les ligues actives API (été = 30+ compétitions)
define('SYNC_FORCE_MAX_SECONDS', 90); // budget temps sync forcée (réponse HTTP / CLI)
define('SYNC_PROBE_MAX_PER_GROUP', 40); // sonde /events max par catégorie (gratuit, cache 4h)
define('SYNC_FORCE_MAX_SPORTS', 40); // import forcé : mix tennis/basket/foot (/events = 0 crédit)

/** Web Push (VAPID) — générer : php tools/generate_vapid.php */
define('VAPID_PUBLIC_KEY', env('VAPID_PUBLIC_KEY', ''));
define('VAPID_PRIVATE_KEY', env('VAPID_PRIVATE_KEY', ''));
define('VAPID_SUBJECT', env('VAPID_SUBJECT', 'mailto:' . APP_CONTACT_EMAIL));

/** Fuseau d'affichage (heures matchs, messages, etc.). Stockage BDD = UTC. */
define('APP_TIMEZONE', env('APP_TIMEZONE', 'Europe/Paris'));
date_default_timezone_set(APP_TIMEZONE);

/**
 * Durée de session (cookie + GC serveur), en secondes.
 * lifetime=0 (avant) = cookie « session » : iOS/Android le jettent souvent en arrière-plan → déconnexions.
 * Défaut 7 jours ; renouvelé à chaque visite (fenêtre glissante).
 * Surcharge : SESSION_LIFETIME=3600 pour 1 h, etc.
 */
define('SESSION_LIFETIME', max(300, (int) env('SESSION_LIFETIME', (string) (7 * 86400))));

if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);
    ini_set('session.cookie_lifetime', (string) SESSION_LIFETIME);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Fenêtre glissante : chaque hit prolonge le cookie (évite déco mobile après 1h fixe).
    if (!empty($_SESSION['user_id']) && !headers_sent()) {
        setcookie(session_name(), session_id(), [
            'expires'  => time() + SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
