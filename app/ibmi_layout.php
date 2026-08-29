<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

function ibmiUrl(string $scr = 'MAIN', array $query = []): string
{
    $query = array_merge(['scr' => strtoupper($scr)], $query);
    foreach ($query as $k => $v) {
        if ($v === null || $v === '') {
            unset($query[$k]);
        }
    }

    return url('admin/ibmi/index.php') . '?' . http_build_query($query);
}

function ibmiCurrentScr(): string
{
    $scr = strtoupper(trim((string) ($_GET['scr'] ?? $_POST['scr'] ?? 'MAIN')));

    return $scr !== '' ? $scr : 'MAIN';
}

function ibmiPreserveQuery(string $scr): array
{
    $keep = [];
    foreach (['q', 'position', 'page', 'panel', 'id', 'edit', 'community_id', 'include_deleted', 'team_home', 'team_away', 'sport'] as $k) {
        if (isset($_GET[$k]) && (string) $_GET[$k] !== '') {
            $keep[$k] = $_GET[$k];
        }
    }

    return $keep;
}

function ibmiIsYes(mixed $v, bool $default = false): bool
{
    if ($v === null || $v === '') {
        return $default;
    }
    if (is_bool($v)) {
        return $v;
    }
    $s = strtoupper(trim((string) $v));

    return in_array($s, ['1', 'Y', 'O', 'OUI', 'YES', 'ON'], true);
}
