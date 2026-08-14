<?php
require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');
header('X-Robots-Tag: noindex');

echo seoRenderRobotsTxt();
