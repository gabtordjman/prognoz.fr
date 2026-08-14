<?php
/**
 * Entrée du panel : valide le slug secret (?s=...) puis redirige.
 * URL type : https://www.prognoz.fr/admin/?s=VOTRE_SLUG_LONG
 */
require __DIR__ . '/../../app/bootstrap.php';

requireAdminGate();

if (adminLoggedIn()) {
    header('Location: ' . url('admin/dashboard.php'));
    exit;
}

header('Location: ' . url('admin/login.php'));
exit;
