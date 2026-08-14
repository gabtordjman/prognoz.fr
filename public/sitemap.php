<?php
require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');

echo seoRenderSitemapXml();
