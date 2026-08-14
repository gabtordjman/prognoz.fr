<?php
/**
 * Smoke test v1.0 — prettyPublicPath / url (CLI, sans serveur web).
 */
define('APP_BOOT', true);
$_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', dirname(__DIR__) . '/public');
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'off';

require dirname(__DIR__) . '/app/env.php';
loadEnvFile(dirname(__DIR__) . '/.env');
require dirname(__DIR__) . '/app/config.php';
require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/routes.php';

$fail = 0;
function assert_eq($label, $got, $expected) {
    global $fail;
    if ($got !== $expected) {
        echo "FAIL $label\n  got:      [$got]\n  expected: [$expected]\n";
        $fail++;
    } else {
        echo "OK   $label\n";
    }
}

assert_eq('version', APP_VERSION, '1.0.0');
assert_eq('pretty login', prettyPublicPath('auth/login.php'), 'auth/login');
assert_eq('pretty login qs', prettyPublicPath('auth/login.php?redirect=index.php'), 'auth/login?redirect=index.php');
assert_eq('pretty index', prettyPublicPath('index.php'), '');
assert_eq('pretty already clean', prettyPublicPath('account/dashboard'), 'account/dashboard');
assert_eq('pretty api', prettyPublicPath('api/validate_ticket.php'), 'api/validate_ticket');

$base = publicBasePath();
assert_eq('url login ends', substr(url('auth/login.php'), -strlen('auth/login')), 'auth/login');
assert_eq('url home is base', url('index.php'), $base);
assert_eq('asset keeps css', (bool) preg_match('#assets/css/admin\.css\?v=#', assetUrl('assets/css/admin.css')), true);

$abs = absoluteUrl('legal/cgu.php');
assert_eq('absolute strips php', (bool) preg_match('#/legal/cgu$#', parse_url($abs, PHP_URL_PATH) ?: $abs), true);

echo $fail === 0 ? "\nAll checks passed.\n" : "\n$fail failure(s).\n";
exit($fail === 0 ? 0 : 1);
