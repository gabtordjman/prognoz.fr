<?php
/**
 * Poste IBM i 5250 — administration Prognoz.
 * URL : /admin/ibmi/?s=SLUG  (même gate que le panel felt)
 */
require __DIR__ . '/../../../app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/ibmi_term.php';
require_once dirname(__DIR__, 3) . '/app/ibmi_layout.php';
require_once dirname(__DIR__, 3) . '/app/ibmi_router.php';
require_once dirname(__DIR__, 3) . '/app/ibmi_screens.php';

requireAdminGate();

if (!adminLoggedIn()) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ibmi_signon'])) {
        if (!csrfCheck()) {
            $error = 'Session expirée, réessayez.';
        } else {
            $fkey = strtoupper(trim((string) ($_POST['fkey'] ?? '')));
            if ($fkey === 'F3') {
                header('Location: ' . url('admin/login.php'));
                exit;
            }
            $result = adminAttemptLogin(
                trim((string) ($_POST['username'] ?? '')),
                (string) ($_POST['password'] ?? '')
            );
            if ($result === true) {
                header('Location: ' . ibmiUrl('MAIN'));
                exit;
            }
            $error = is_string($result) ? $result : 'Échec de connexion.';
        }
    }
    ibmiRenderSignon($error);
    exit;
}

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['ibmi_signon'])) {
    ibmiHandlePost($pdo);
}

$scr = ibmiCurrentScr();
if ($scr === 'SIGNOFF') {
    adminLogout();
    header('Location: ' . url('admin/ibmi/index.php'));
    exit;
}
if (!in_array($scr, ibmiKnownScreens(), true) || $scr === 'SIGNON') {
    $scr = 'MAIN';
}

ibmiRenderScreen($pdo, $scr);
