<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/** Délai au-delà duquel un signal « typing » expire (secondes). */
const CHAT_TYPING_TTL = 8;

function chatTypingPath(int $communityId): string
{
    if (!is_dir(APP_CACHE_DIR)) {
        @mkdir(APP_CACHE_DIR, 0755, true);
    }
    return APP_CACHE_DIR . '/chat_typing_' . $communityId . '.json';
}

/**
 * @return array<string, array{pseudo: string, ts: int}>
 */
function chatTypingLoad(int $communityId): array
{
    $path = chatTypingPath($communityId);
    if (!is_file($path)) {
        return [];
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * @param array<string, array{pseudo: string, ts: int}> $data
 */
function chatTypingSave(int $communityId, array $data): void
{
    $path = chatTypingPath($communityId);
    $fp = @fopen($path, 'c+');
    if ($fp === false) {
        return;
    }
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return;
    }
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

/**
 * @param array<string, array{pseudo: string, ts: int}> $data
 * @return array<string, array{pseudo: string, ts: int}>
 */
function chatTypingPrune(array $data): array
{
    $now = time();
    $out = [];
    foreach ($data as $uid => $row) {
        if (!is_array($row)) {
            continue;
        }
        $ts = (int) ($row['ts'] ?? 0);
        if ($ts > 0 && ($now - $ts) <= CHAT_TYPING_TTL) {
            $out[(string) $uid] = [
                'pseudo' => (string) ($row['pseudo'] ?? ''),
                'ts'     => $ts,
            ];
        }
    }
    return $out;
}

function chatTypingSet(int $communityId, int $userId, string $pseudo = ''): void
{
    if ($communityId < 1 || $userId < 1) {
        return;
    }
    $pseudo = trim($pseudo);
    // Toujours préférer le pseudo BDD (évite session vide / valeur pourrie)
    if ($pseudo === '' || str_starts_with($pseudo, 'com.chat_typing') || $pseudo === 'com_typing') {
        try {
            $stmt = getPDO()->prepare('SELECT pseudo FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $dbPseudo = trim((string) $stmt->fetchColumn());
            if ($dbPseudo !== '') {
                $pseudo = $dbPseudo;
            }
        } catch (Throwable $e) {
            // ignore
        }
    }
    if ($pseudo === '') {
        $pseudo = '#' . $userId;
    }
    $data = chatTypingPrune(chatTypingLoad($communityId));
    $data[(string) $userId] = [
        'pseudo' => $pseudo,
        'ts'     => time(),
    ];
    chatTypingSave($communityId, $data);
}

function chatTypingClear(int $communityId, int $userId): void
{
    if ($communityId < 1 || $userId < 1) {
        return;
    }
    $data = chatTypingPrune(chatTypingLoad($communityId));
    unset($data[(string) $userId]);
    chatTypingSave($communityId, $data);
}

/**
 * Liste des membres en train d’écrire (hors soi).
 *
 * @return list<array{user_id: int, pseudo: string, equipped_name: string}>
 */
function chatTypingList(int $communityId, int $excludeUserId): array
{
    $data = chatTypingPrune(chatTypingLoad($communityId));
    // Persiste le prune pour éviter des fichiers qui grossissent
    chatTypingSave($communityId, $data);

    $ids = [];
    foreach ($data as $uid => $row) {
        $id = (int) $uid;
        if ($id > 0 && $id !== $excludeUserId) {
            $ids[] = $id;
        }
    }
    if ($ids === []) {
        return [];
    }

    $pseudos = [];
    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = getPDO()->prepare("SELECT id, pseudo, surnom, equipped_name FROM users WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $pseudos[(int) $u['id']] = [
                'pseudo' => userDisplayName($u),
                'equipped_name' => (string) ($u['equipped_name'] ?? ''),
            ];
        }
    } catch (Throwable $e) {
        // fallback cache ci-dessous
    }

    $list = [];
    foreach ($ids as $id) {
        $row = $pseudos[$id] ?? null;
        $pseudo = is_array($row)
            ? (string) ($row['pseudo'] ?? '')
            : trim((string) ($data[(string) $id]['pseudo'] ?? ''));
        $nameStyle = is_array($row) ? (string) ($row['equipped_name'] ?? '') : '';
        if ($pseudo === '' || str_starts_with($pseudo, 'com.chat_typing') || $pseudo === 'com_typing') {
            continue;
        }
        $list[] = [
            'user_id' => $id,
            'pseudo'  => $pseudo,
            'equipped_name' => $nameStyle,
        ];
    }
    return $list;
}
