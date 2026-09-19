<?php
/** Génère une preview HTML autonome du kit (sans MySQL). */
define('APP_BOOT', true);

function e(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function t(string $k, array $r = []): string { return $k; }
function url(string $path = ''): string { return '/' . ltrim($path, '/'); }
function assetUrl(string $path): string { return '/' . ltrim($path, '/'); }
function avatarPublicUrl(?string $u): ?string { return null; }
function userAvatarColor(string $p): string { return '#3a6b4f'; }
function userInitials(string $p): string { return 'T'; }

require __DIR__ . '/../app/kit.php';

ob_start();
?>
<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><title>Kit preview</title>
<style>
body{margin:1rem;background:#12241c;color:#eee;font-family:system-ui,sans-serif}
.grid{display:flex;flex-wrap:wrap;gap:1.25rem}
.cell{width:200px;text-align:center}
.kit-stage{width:180px;margin:0 auto;padding:.6rem;background:linear-gradient(180deg,#2f5240,#12241c);border-radius:10px;border:1px solid rgba(196,160,53,.5)}
.kit-doll{width:100%;height:auto;display:block}
.kit-shadow{fill:rgba(0,0,0,.3)} .kit-boot{fill:#1a1612} .kit-boot-sole{fill:#0d0b09}
.kit-jersey-shade{fill:rgba(0,0,0,.14)} .kit-jersey-fold{stroke:rgba(0,0,0,.12);stroke-width:1.4;fill:none}
.kit-head-ring{stroke:rgba(196,160,53,.55);stroke-width:2} .kit-collar-shape{fill:#e8d078}
.kit-jersey-tex{pointer-events:none}
h3{font-size:.85rem;margin:.5rem 0 0}
</style></head><body>
<h1>Preview kit — textures clipées</h1>
<div class="grid">
<?php
foreach (['france_98', 'france_06', 'barca_09', 'ol_07', 'italy_06', 'brazil_02', 'psg', 'monaco', null] as $id) {
    echo '<div class="cell"><div class="kit-stage">';
    renderKitDollSvg($id, 'shorts_crimson', null, 'Test', 'prop_cigarette');
    echo '</div><h3>' . e($id ?? 'plain') . '</h3></div>';
}
?>
</div></body></html>
<?php
$html = ob_get_clean();
$out = __DIR__ . '/../public/kit-preview.html';
// Paths relative for file:// and php -S
$html = str_replace('href="/assets/', 'href="assets/', $html);
$html = str_replace('xlink:href="/assets/', 'xlink:href="assets/', $html);
file_put_contents($out, $html);
echo "Wrote {$out} (" . strlen($html) . " bytes)\n";
echo (str_contains($html, 'data-kit-role="tex"') ? "HAS_TEX_ROLE\n" : "NO_TEX_ROLE\n");
echo (str_contains($html, 'kit-france-98') ? "HAS_FRANCE98\n" : "NO_FRANCE98\n");
echo (str_contains($html, 'kitTex_') ? "HAS_OLD_PATTERN\n" : "NO_OLD_PATTERN_OK\n");
echo (str_contains($html, 'clip-path="url(#kitJerseyClip') ? "HAS_CLIP\n" : "NO_CLIP\n");
