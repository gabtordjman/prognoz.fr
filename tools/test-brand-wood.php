<?php
/**
 * Logo centré + bois tuilé (pas de cover zoomé).
 */
$root = dirname(__DIR__);
$fail = 0;

function check(string $label, bool $ok): void
{
    global $fail;
    if ($ok) {
        echo "OK   $label\n";
    } else {
        echo "FAIL $label\n";
        $fail++;
    }
}

$layout = (string) file_get_contents($root . '/app/layout.php');
$css    = (string) file_get_contents($root . '/public/assets/css/style.css');
$admin  = (string) file_get_contents($root . '/public/assets/css/admin.css');
$svg    = (string) file_get_contents($root . '/public/assets/img/logo-wordmark.svg');
$wood   = $root . '/public/assets/img/wood-counter.jpg';

check('renderBrandMark exists', str_contains($layout, 'function renderBrandMark'));
check('logo viewBox 168 (mot seul)', str_contains($layout, 'viewBox="0 0 168 36"'));
check('PROGNOZ text-anchor middle', (bool) preg_match('/text-anchor="middle"[^>]*PROGNOZ|PROGNOZ<\/text>/', $layout) && str_contains($layout, 'text-anchor="middle"'));
check('PROGNOZ x=84 (centre du viewBox)', str_contains($layout, 'x="84"') && str_contains($layout, 'PROGNOZ'));
check('boule 8 hors viewBox (gauche)', str_contains($layout, 'cx="-20"'));
check('svg overflow visible', str_contains($layout, 'overflow="visible"'));
check('topbar utilise le mark', str_contains($layout, 'renderBrandMark()'));
check('sr-only Prognoz', str_contains($layout, 'sr-only'));

check('wood-tile défini', str_contains($css, '--wood-tile: 64rem'));
check('topbar ne zoome pas en cover', !preg_match('/\.topbar\s*\{[^}]*background-size:\s*auto,\s*cover/s', $css));
check('topbar tuile le bois', (bool) preg_match('/\.topbar\s*\{[^}]*wood-tile\) 100%/s', $css));
check('topbar repeat-x', (bool) preg_match('/\.topbar\s*\{[^}]*repeat-x/s', $css));
check('hero bar tuile aussi', (bool) preg_match('/\.hero-scene-bar\s*\{[^}]*wood-tile\) 100%/s', $css));

$topbarCover = preg_match('/\.topbar\s*\{[^}]{0,800}background-size:\s*auto,\s*cover/s', $css);
check('pas de cover sur .topbar', $topbarCover === 0);

check('admin wood-tile', str_contains($admin, '--wood-tile: 64rem'));
check('admin pas cover bois', !preg_match('/\.ops-topbar\s*\{[^}]*background-size:\s*auto,\s*cover/s', $admin));

check('asset svg aligné', str_contains($svg, 'viewBox="0 0 168 36"') && str_contains($svg, 'x="84"'));
check('wood jpeg présent', is_file($wood) && filesize($wood) > 80000);
if (is_file($wood) && function_exists('getimagesize')) {
    $info = getimagesize($wood);
    check('wood au moins 2000px de large', is_array($info) && ($info[0] ?? 0) >= 2000);
}

echo $fail === 0 ? "\nAll brand/wood checks passed.\n" : "\n$fail failure(s).\n";
exit($fail === 0 ? 0 : 1);
