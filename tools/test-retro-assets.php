<?php
define('APP_BOOT', 1);
require __DIR__ . '/../app/browser.php';

$uaIe11 = 'Mozilla/5.0 (Windows NT 6.3; Trident/7.0; rv:11.0) like Gecko';
$uaIos6 = 'Mozilla/5.0 (iPhone; CPU iPhone OS 6_1_6 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Version/6.0 Mobile/10B500 Safari/8536.25';
$uaChrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

assert(isLegacyBrowser($uaIe11) === true, 'IE11 legacy');
assert(isLegacyBrowser($uaIos6) === true, 'iOS6 legacy');
assert(isLegacyBrowser($uaChrome) === false, 'Chrome modern');

$poly = file_get_contents(__DIR__ . '/../public/assets/js/legacy-polyfill.js');
assert(strpos($poly, 'NodeList.prototype.forEach') !== false, 'polyfill forEach');
assert(strpos($poly, 'window.fetch') !== false, 'polyfill fetch');
assert(strpos($poly, 'classList.toggle') !== false || strpos($poly, 'DOMTokenList.prototype.toggle') !== false, 'polyfill toggle');
assert(strpos($poly, '.finally') !== false, 'polyfill finally');

$css = file_get_contents(__DIR__ . '/../public/assets/css/retro.css');
assert(strpos($css, '#4a3424') !== false, 'wood color');
assert(strpos($css, '#e4d9c4') !== false, 'paper color');
assert(strpos($css, '#9a7420') !== false, 'brass color');
assert(strpos($css, '.pick-btn.selected') !== false, 'pick selected style');
assert(strpos($css, 'legacy-polyfill') === false, 'css only');

$layout = file_get_contents(__DIR__ . '/../app/layout.php');
assert(strpos($layout, 'legacy-polyfill.js') !== false, 'layout loads polyfill');
assert(preg_match('/if \(\$retroUi\):.*?legacy-polyfill.*?retro\.css.*?else:.*?style\.css/s', $layout) === 1, 'retro skips style.css');

echo "OK browser + assets checks\n";
