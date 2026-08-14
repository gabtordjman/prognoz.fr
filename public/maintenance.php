<?php
define('APP_MAINTENANCE_PAGE', true);
require __DIR__ . '/../app/bootstrap.php';

if (!appInMaintenanceMode()) {
    header('Location: ' . url('index.php'));
    exit;
}

header('Retry-After: 3600');

layoutStatusPage(
    'Maintenance',
    'On revient très vite',
    'Mise à jour en cours — pronostics et communautés indisponibles quelques minutes.',
    url('index.php'),
    'Réessayer',
    503
);
