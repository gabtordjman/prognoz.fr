<?php
/**
 * Internationalisation FR / EN.
 * Langue : ?lang=en|fr → cookie + session → Accept-Language → fr.
 */
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

const APP_LANGS = ['fr', 'en'];
const APP_LANG_COOKIE = 'prognoz_lang';
const APP_LANG_COOKIE_DAYS = 365;

/** @var array<string, string>|null */
$GLOBALS['_APP_I18N'] = null;
/** @var string|null */
$GLOBALS['_APP_LANG'] = null;

function initI18n(): void
{
    if ($GLOBALS['_APP_LANG'] !== null) {
        return;
    }

    $chosen = null;
    if (isset($_GET['lang'])) {
        $q = strtolower(trim((string) $_GET['lang']));
        if (in_array($q, APP_LANGS, true)) {
            $chosen = $q;
            $_SESSION['lang'] = $chosen;
            setLangCookie($chosen);
            persistUserPreferredLang($chosen);
        }
    }

    if ($chosen === null && !empty($_SESSION['lang']) && in_array($_SESSION['lang'], APP_LANGS, true)) {
        $chosen = $_SESSION['lang'];
    }

    if ($chosen === null && !empty($_COOKIE[APP_LANG_COOKIE])) {
        $c = strtolower(trim((string) $_COOKIE[APP_LANG_COOKIE]));
        if (in_array($c, APP_LANGS, true)) {
            $chosen = $c;
            $_SESSION['lang'] = $chosen;
        }
    }

    if ($chosen === null) {
        $chosen = detectBrowserLang();
        $_SESSION['lang'] = $chosen;
    }

    $GLOBALS['_APP_LANG'] = $chosen;
    loadLangCatalog($chosen);
}

function setLangCookie(string $lang): void
{
    if (headers_sent()) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    setcookie(APP_LANG_COOKIE, $lang, [
        'expires'  => time() + (APP_LANG_COOKIE_DAYS * 86400),
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

/** Mémorise la langue UI pour les e-mails (si connecté). */
function persistUserPreferredLang(string $lang): void
{
    if (!in_array($lang, APP_LANGS, true)) {
        return;
    }
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    if ($uid <= 0 || !function_exists('getPDO') || !function_exists('ensureMailPrefsSchema')) {
        return;
    }
    try {
        $pdo = getPDO();
        ensureMailPrefsSchema($pdo);
        $pdo->prepare('UPDATE users SET preferred_lang = ? WHERE id = ?')->execute([$lang, $uid]);
    } catch (Throwable $e) {
        // ignore
    }
}

function detectBrowserLang(): string
{
    $header = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    if ($header === '') {
        return 'fr';
    }
    foreach (explode(',', $header) as $part) {
        $code = strtolower(trim(explode(';', $part)[0]));
        $code = substr($code, 0, 2);
        if (in_array($code, APP_LANGS, true)) {
            return $code;
        }
    }

    return 'fr';
}

/**
 * Clés récentes : si le fichier lang déployé est en retard, on évite d’afficher la clé brute.
 *
 * @return array<string, string>
 */
function i18nCriticalFallbacks(string $lang): array
{
    $rows = [
        'dash.personalize'     => ['fr' => 'Mon profil', 'en' => 'My profile'],
        'dash.bio'             => ['fr' => 'Bio', 'en' => 'Bio'],
        'dash.bio_ph'          => ['fr' => 'Quelques mots…', 'en' => 'A few words…'],
        'dash.bio_too_long'    => ['fr' => 'Bio trop longue (200 car. max).', 'en' => 'Bio too long (200 chars max).'],
        'dash.fav_sport'       => ['fr' => 'Sport favori', 'en' => 'Favorite sport'],
        'dash.fav_sport_none'  => ['fr' => 'Aucun', 'en' => 'None'],
        'dash.sport_invalid'   => ['fr' => 'Sport invalide.', 'en' => 'Invalid sport.'],
        'dash.save_profile'    => ['fr' => 'Enregistrer', 'en' => 'Save'],
        'dash.profile_saved'   => ['fr' => 'Profil enregistré.', 'en' => 'Profile saved.'],
        'event.active'         => ['fr' => 'Événement', 'en' => 'Event'],
        'event.until'          => ['fr' => 'Jusqu’à {when}', 'en' => 'Until {when}'],
        'profile.self_sub'     => ['fr' => 'C’est toi', 'en' => 'That’s you'],
        'season.badge_bronze_streak' => ['fr' => 'Bronze · série {n}', 'en' => 'Bronze · streak {n}'],
        'season.badge_gold_streak'   => ['fr' => 'Or · série {n}', 'en' => 'Gold · streak {n}'],
        'season.badge_silver_streak' => ['fr' => 'Argent · série {n}', 'en' => 'Silver · streak {n}'],
        'announce.aria'              => ['fr' => 'Annonces', 'en' => 'Announcements'],
        'announce.empty'             => ['fr' => 'Aucune annonce pour le moment.', 'en' => 'No announcements yet.'],
        'announce.got_it'            => ['fr' => 'Compris', 'en' => 'Got it'],
        'announce.kicker'            => ['fr' => 'Annonce', 'en' => 'News'],
    ];
    $code = $lang === 'en' ? 'en' : 'fr';
    $out = [];
    foreach ($rows as $key => $pair) {
        $out[$key] = $pair[$code] ?? $pair['fr'];
    }

    return $out;
}

function loadLangCatalog(string $lang): void
{
    $path = dirname(__DIR__) . '/lang/' . $lang . '.php';
    if (!is_file($path)) {
        $path = dirname(__DIR__) . '/lang/fr.php';
    }
    /** @var array<string, string> $catalog */
    $catalog = require $path;
    $fallbacks = i18nCriticalFallbacks($lang === 'en' ? 'en' : 'fr');
    $GLOBALS['_APP_I18N'] = array_merge($fallbacks, is_array($catalog) ? $catalog : []);
}

function currentLang(): string
{
    if ($GLOBALS['_APP_LANG'] === null) {
        initI18n();
    }

    return $GLOBALS['_APP_LANG'] ?? 'fr';
}

function htmlLang(): string
{
    return currentLang();
}

function ogLocale(): string
{
    return currentLang() === 'en' ? 'en_US' : 'fr_FR';
}

/**
 * Traduction. Placeholders : {name} remplacés par $replace['name'].
 */
function t(string $key, array $replace = []): string
{
    if ($GLOBALS['_APP_I18N'] === null) {
        initI18n();
    }
    $catalog = $GLOBALS['_APP_I18N'] ?? [];
    $text = $catalog[$key] ?? null;
    if ($text === null) {
        // Fallback FR si clé absente dans EN
        if (currentLang() !== 'fr') {
            static $fr = null;
            if ($fr === null) {
                $frPath = dirname(__DIR__) . '/lang/fr.php';
                $fr = is_file($frPath) ? require $frPath : [];
            }
            $text = $fr[$key] ?? $key;
        } else {
            $text = $key;
        }
    }
    if ($replace) {
        foreach ($replace as $k => $v) {
            $text = str_replace('{' . $k . '}', (string) $v, $text);
        }
    }

    return $text;
}

/** Pluriel simple : clé _one / _other selon n. */
function tn(string $keyBase, int $n, array $replace = []): string
{
    $suffix = $n === 1 ? '_one' : '_other';
    $replace = array_merge(['n' => $n], $replace);

    return t($keyBase . $suffix, $replace);
}

/**
 * Exécute $fn avec le catalogue de $lang, puis restaure la langue courante.
 *
 * @template T
 * @param callable():T $fn
 * @return T
 */
function withLang(string $lang, callable $fn): mixed
{
    if (!in_array($lang, APP_LANGS, true)) {
        $lang = 'fr';
    }
    $prevLang = $GLOBALS['_APP_LANG'] ?? null;
    $prevCat  = $GLOBALS['_APP_I18N'] ?? null;
    $GLOBALS['_APP_LANG'] = $lang;
    loadLangCatalog($lang);
    try {
        return $fn();
    } finally {
        $GLOBALS['_APP_LANG'] = $prevLang;
        $GLOBALS['_APP_I18N'] = $prevCat;
    }
}

/** URL de la page courante avec ?lang=xx (préserve les autres query params). */
function langSwitchUrl(string $lang): string
{
    if (!in_array($lang, APP_LANGS, true)) {
        $lang = 'fr';
    }
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $parts = parse_url($uri);
    $path = $parts['path'] ?? '/';
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $query['lang'] = $lang;
    $qs = http_build_query($query);

    return $path . ($qs !== '' ? '?' . $qs : '');
}

/** Catalogue JS exposé à window.PRONO_I18N */
function i18nJsCatalog(): array
{
    $keys = [
        'js.draw',
        'js.score_prefix',
        'js.score_custom_invalid',
        'js.remove',
        'js.remove_aria',
        'js.already_validated',
        'js.validating',
        'js.validate',
        'js.no_picks',
        'js.invalid_response',
        'js.network_error',
        'js.validate_impossible',
        'js.saved_one',
        'js.saved_other',
        'js.pt',
        'js.pts',
        'js.to_validate',
        'js.good_pick',
        'js.bravo',
        'js.see_results',
        'js.close',
        'js.result_label',
        'js.batch_wins_one',
        'js.batch_wins_other',
        'js.and_others_one',
        'js.and_others_other',
    ];
    $out = [];
    foreach ($keys as $k) {
        $out[$k] = t($k);
    }
    $out['lang'] = currentLang();

    return $out;
}

function layoutLangSwitcher(): void
{
    $lang = currentLang();
    ?>
    <div class="lang-switch" role="group" aria-label="<?= e(t('lang.label')) ?>">
        <a href="<?= e(langSwitchUrl('fr')) ?>" class="lang-switch-btn<?= $lang === 'fr' ? ' is-active' : '' ?>" hreflang="fr" lang="fr"<?= $lang === 'fr' ? ' aria-current="true"' : '' ?>>FR</a>
        <a href="<?= e(langSwitchUrl('en')) ?>" class="lang-switch-btn<?= $lang === 'en' ? ' is-active' : '' ?>" hreflang="en" lang="en"<?= $lang === 'en' ? ' aria-current="true"' : '' ?>>EN</a>
    </div>
    <?php
}

function layoutI18nScript(): void
{
    ?>
    <script>
        window.PRONO_LANG = <?= json_encode(currentLang(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        window.PRONO_I18N = <?= json_encode(i18nJsCatalog(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        window.t = function (key, replace) {
            var dict = window.PRONO_I18N || {};
            var text = dict[key] || key;
            if (replace) {
                Object.keys(replace).forEach(function (k) {
                    text = text.split('{' + k + '}').join(String(replace[k]));
                });
            }
            return text;
        };
    </script>
    <?php
}
