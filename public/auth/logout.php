<?php
require __DIR__ . '/../../app/bootstrap.php';

$dest = url('index.php');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
    session_destroy();
}

$params = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires'  => time() - 42000,
    'path'     => '/',
    'domain'   => $params['domain'] ?? '',
    'secure'   => $params['secure'] ?? false,
    'httponly' => $params['httponly'] ?? true,
    'samesite' => $params['samesite'] ?? 'Lax',
]);

header('Cache-Control: no-store, no-cache, must-revalidate');
?>
<!DOCTYPE html>
<html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
<head>
    <meta charset="UTF-8">
    <title>Déconnexion</title>
    <meta http-equiv="refresh" content="0;url=<?= e($dest) ?>">
</head>
<body>
<script>
try {
    localStorage.removeItem('prognoz_draft_ticket');
    localStorage.removeItem('prognoz_guest_ticket');
    sessionStorage.removeItem('prognoz_sync_reload');
    sessionStorage.removeItem('prognoz_ticket_collapsed');
} catch (e) {}
location.replace(<?= json_encode($dest) ?>);
</script>
<p><a href="<?= e($dest) ?>">Continuer</a></p>
</body>
</html>
