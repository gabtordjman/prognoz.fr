<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/** 5250 *DS4 — 27 lignes × 80 colonnes (même image SSH et navigateur). */
const IBMI_ROWS = 27;
const IBMI_COLS = 80;

function ibmiLen(string $s): int
{
    return function_exists('mb_strlen') ? (int) mb_strlen($s, 'UTF-8') : strlen($s);
}

function ibmiSub(string $s, int $start, int $len): string
{
    return function_exists('mb_substr')
        ? (string) mb_substr($s, $start, $len, 'UTF-8')
        : (string) substr($s, $start, $len);
}

/**
 * @return array{rows:int,cols:int,ch:list<list<string>>,at:list<list<string>>,fields:list<array<string,mixed>>,scr:string,title:string,fkeys:list<string>}
 */
function ibmiT(): array
{
    $blank = array_fill(0, IBMI_COLS, ' ');
    $attr = array_fill(0, IBMI_COLS, 'g');

    return [
        'rows'   => IBMI_ROWS,
        'cols'   => IBMI_COLS,
        'ch'     => array_fill(0, IBMI_ROWS, $blank),
        'at'     => array_fill(0, IBMI_ROWS, $attr),
        'fields' => [],
        'scr'    => '',
        'title'  => '',
        'fkeys'  => [],
    ];
}

function ibmiTPut(array &$t, int $r, int $c, string $text, string $attr = 'g'): void
{
    if ($r < 0 || $r >= $t['rows']) {
        return;
    }
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $col = $c;
    foreach ($chars as $ch) {
        if ($col < 0) {
            $col++;
            continue;
        }
        if ($col >= $t['cols']) {
            break;
        }
        if ($ch === "\n" || $ch === "\r") {
            continue;
        }
        $t['ch'][$r][$col] = $ch;
        $t['at'][$r][$col] = $attr;
        $col++;
    }
}

function ibmiTField(
    array &$t,
    int $r,
    int $c,
    string $name,
    int $len,
    string $value,
    string $type = 'text',
    array $extra = []
): void {
    $len = max(1, min($t['cols'] - max(0, $c), $len));
    if ($type === 'password') {
        ibmiTPut($t, $r, $c, str_repeat('_', $len), 'w');
    } else {
        ibmiTPut($t, $r, $c, ibmiPad(ibmiTClip($value, $len), $len, '_'), 'w');
    }
    $t['fields'][] = [
        'r'     => $r,
        'c'     => $c,
        'len'   => $len,
        'name'  => $name,
        'value' => $value,
        'type'  => $type,
        'extra' => $extra,
    ];
}

function ibmiPad(string $s, int $len, string $pad = ' '): string
{
    $n = ibmiLen($s);
    if ($n >= $len) {
        return ibmiSub($s, 0, $len);
    }

    return $s . str_repeat($pad, $len - $n);
}

function ibmiTClip(string $s, int $len): string
{
    $s = preg_replace('/\s+/', ' ', trim($s)) ?? '';
    if (ibmiLen($s) <= $len) {
        return $s;
    }

    return ibmiSub($s, 0, max(0, $len - 1)) . '>';
}

function ibmiTHeader(array &$t, string $title, string $scr): void
{
    $t['title'] = $title;
    $t['scr'] = $scr;
    $sys = 'PROGNOZ';
    $scr = strtoupper($scr);
    $title = strtoupper($title);
    ibmiTPut($t, 0, 0, $sys, 't');
    $right = str_replace(' ', '', $title) === $scr ? '' : $scr;
    if ($right !== '') {
        ibmiTPut($t, 0, $t['cols'] - ibmiLen($right), $right, 't');
    }
    $mid = ibmiTClip($title, $t['cols'] - ibmiLen($sys) - ibmiLen($right) - 4);
    $start = (int) floor(($t['cols'] - ibmiLen($mid)) / 2);
    ibmiTPut($t, 0, max(ibmiLen($sys) + 1, $start), $mid, 'w');
}

function ibmiTMsg(array &$t, string $msg, string $type = ''): void
{
    $attr = $type === 'error' ? 'r' : ($type === 'info' ? 'b' : 'y');
    if (trim($msg) === '') {
        return;
    }
    ibmiTPut($t, 24, 1, ibmiTClip($msg, $t['cols'] - 2), $attr);
}

function ibmiTCmd(array &$t): void
{
    ibmiTPut($t, 25, 0, '===>', 't');
    ibmiTField($t, 25, 5, 'cmdline', $t['cols'] - 6, '', 'text', ['id' => 'ibmiCmd']);
}

function ibmiTFkeys(array &$t, array $keys): void
{
    $t['fkeys'] = $keys;
    ibmiTPut($t, 26, 1, ibmiTClip(implode('  ', $keys), $t['cols'] - 2), 'w');
}

/** @return array<string,int> row+col keyed by "r:c" for field starts */
function ibmiTFieldMap(array $t): array
{
    $map = [];
    foreach ($t['fields'] as $i => $f) {
        $map[$f['r'] . ':' . $f['c']] = $i;
    }

    return $map;
}

function ibmiTAnsi(array $t): string
{
    $codes = [
        'g' => "\033[0;32m",
        'h' => "\033[1;32m",
        'w' => "\033[1;37m",
        't' => "\033[36m",
        'r' => "\033[31m",
        'y' => "\033[33m",
        'd' => "\033[2;32m",
        'b' => "\033[34m",
    ];
    $out = "\033[?25l\033[2J\033[H";
    for ($r = 0; $r < $t['rows']; $r++) {
        $out .= "\033[" . ($r + 1) . ";1H";
        $prev = '';
        for ($c = 0; $c < $t['cols']; $c++) {
            $a = $t['at'][$r][$c];
            if ($a !== $prev) {
                $out .= $codes[$a] ?? $codes['g'];
                $prev = $a;
            }
            $out .= $t['ch'][$r][$c];
        }
        $out .= "\033[0m";
    }

    return $out;
}

function ibmiTHtmlGlass(array $t): string
{
    $map = ibmiTFieldMap($t);
    $html = '<div class="ibmi-glass" data-rows="' . (int) $t['rows'] . '" data-cols="' . (int) $t['cols'] . '">';
    for ($r = 0; $r < $t['rows']; $r++) {
        $c = 0;
        while ($c < $t['cols']) {
            $key = $r . ':' . $c;
            $gc = $c + 1;
            $gr = $r + 1;
            if (isset($map[$key])) {
                $f = $t['fields'][$map[$key]];
                $html .= ibmiTHtmlField($f);
                $c += (int) $f['len'];
                continue;
            }
            $a = $t['at'][$r][$c];
            $run = $t['ch'][$r][$c];
            $c2 = $c + 1;
            while ($c2 < $t['cols'] && !isset($map[$r . ':' . $c2]) && $t['at'][$r][$c2] === $a) {
                $run .= $t['ch'][$r][$c2];
                $c2++;
            }
            $span = $c2 - $c;
            $html .= '<span class="ibmi-a-' . e($a) . '" style="grid-column:' . $gc . ' / span ' . $span
                . ';grid-row:' . $gr . '">' . e($run) . '</span>';
            $c = $c2;
        }
    }
    $html .= '</div>';

    return $html;
}

/** @param array<string,mixed> $f */
function ibmiTHtmlField(array $f): string
{
    $type = (string) ($f['type'] ?? 'text');
    $id = (string) ($f['extra']['id'] ?? '');
    $max = (int) $f['len'];
    $r = (int) $f['r'] + 1;
    $c = (int) $f['c'] + 1;
    $cls = 'ibmi-fld';
    if (!empty($f['extra']['opt'])) {
        $cls .= ' ibmi-fld-opt';
    }
    $extra = ' spellcheck="false" autocomplete="off" autocapitalize="off"';
    if ($id !== '') {
        $extra .= ' id="' . e($id) . '"';
    }
    if (!empty($f['extra']['required'])) {
        $extra .= ' required';
    }
    if ($type === 'password') {
        $extra .= ' autocomplete="off"';
    }
    if (!empty($f['extra']['opt'])) {
        $extra .= ' inputmode="numeric" maxlength="2"';
    }

    return '<input class="' . $cls . '" type="' . e($type) . '" name="' . e((string) $f['name'])
        . '" value="' . e((string) $f['value']) . '" maxlength="' . $max
        . '" size="' . $max . '" style="grid-column:' . $c . ' / span ' . $max
        . ';grid-row:' . $r . '"' . $extra . '>';
}

/** @param list<string> $keys */
function ibmiFkeyButtons(array $keys): string
{
    $html = '<div class="ibmi-fkbar">';
    foreach ($keys as $k) {
        if (preg_match('/^(F\d+|PAGEUP|PAGEDOWN)=(.*)$/i', $k, $m)) {
            $code = strtoupper($m[1]);
            $html .= '<button type="button" class="ibmi-fk" form="ibmiForm" data-fkey="' . e($code) . '">'
                . '<b>' . e($code) . '</b>' . e(trim($m[2])) . '</button>';
        } elseif (preg_match('/^Enter=(.*)$/i', $k, $m)) {
            $html .= '<button type="submit" class="ibmi-fk ibmi-fk-enter" form="ibmiForm">'
                . '<b>Enter</b>' . e(trim($m[1])) . '</button>';
        }
    }
    $html .= '</div>';

    return $html;
}

function ibmiEmitHtml(array $t, bool $signon = false): void
{
    $scr = (string) $t['scr'];
    $title = (string) $t['title'];
    header('X-Robots-Tag: noindex, nofollow');
    $action = $signon ? '' : ibmiUrl($scr, ibmiPreserveQuery($scr));
    $boot = empty($_SESSION['ibmi_crt_on']);
    $_SESSION['ibmi_crt_on'] = true;
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#1a1b1d">
    <title><?= e($scr) ?> · <?= e($title) ?> · PROGNOZ 5250</title>
    <link rel="stylesheet" href="<?= e(assetUrl('assets/css/ibmi.css')) ?>">
</head>
<body class="ibmi-body <?= $boot ? 'ibmi-cold' : 'ibmi-warm' ?>">
<div class="ibmi-room">
    <div class="ibmi-terminal" id="ibmiTerminal">
        <div class="ibmi-hood">
            <img class="ibmi-logo" src="<?= e(assetUrl('assets/img/ibm-8bar.svg')) ?>" alt="IBM" width="96" height="44">
        </div>
        <div class="ibmi-bezel">
            <div class="ibmi-crt" id="ibmiCrt">
                <div class="ibmi-phosphor">
                    <form method="post" action="<?= e($action) ?>" class="ibmi-form" id="ibmiForm" autocomplete="off">
                        <?= csrfField() ?>
                        <?php if ($signon): ?>
                            <input type="hidden" name="ibmi_signon" value="1">
                        <?php else: ?>
                            <input type="hidden" name="scr" value="<?= e($scr) ?>">
                        <?php endif; ?>
                        <input type="hidden" name="fkey" id="ibmiFkey" value="">
                        <input type="hidden" name="action" id="ibmiAction" value="">
                        <button type="submit" class="ibmi-enter-hidden" tabindex="-1" name="ibmi_enter" value="1">Enter</button>
                        <?= ibmiTHtmlGlass($t) ?>
                    </form>
                </div>
                <div class="ibmi-scanlines" aria-hidden="true"></div>
                <div class="ibmi-refresh" aria-hidden="true"></div>
                <div class="ibmi-vignette" aria-hidden="true"></div>
                <div class="ibmi-glare" aria-hidden="true"></div>
                <div class="ibmi-noise" aria-hidden="true"></div>
            </div>
        </div>
        <div class="ibmi-ledge">
            <span class="ibmi-led" title="Power"></span>
            <span class="ibmi-model">InfoWindow 3487 · 5250 · 27×80</span>
            <span class="ibmi-sess"><?= e($scr !== '' ? $scr : 'SIGNON') ?> · DSP01</span>
            <button type="button" class="ibmi-fs" id="ibmiFs" title="Plein écran (Alt+Enter)">Plein écran</button>
        </div>
        <?= ibmiFkeyButtons($t['fkeys'] ?? []) ?>
    </div>
</div>
<script src="<?= e(assetUrl('assets/js/ibmi.js')) ?>"></script>
</body>
</html>
    <?php
}
