<?php
require __DIR__ . '/../app/bootstrap.php';

layoutStatusPage(
    '404',
    t('status.404_title'),
    t('status.404_lead'),
    url('index.php'),
    t('status.404_cta'),
    404
);
