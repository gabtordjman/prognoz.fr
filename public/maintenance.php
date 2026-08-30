<?php
define('APP_MAINTENANCE_PAGE', true);
require __DIR__ . '/../app/bootstrap.php';

if (!appInMaintenanceMode()) {
    header('Location: ' . url('index.php'));
    exit;
}

header('Retry-After: 3600');

layoutStatusPage(
    t('maintenance.tag'),
    t('maintenance.title', ['name' => APP_NAME]),
    t('maintenance.lead'),
    url('index.php'),
    t('maintenance.retry'),
    503
);
