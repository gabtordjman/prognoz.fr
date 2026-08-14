<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

const SENSITIVE_ENC_PREFIX = 'enc1:';

function appEncryptionKey(): string
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }

    $raw = env('APP_ENCRYPTION_KEY', '');
    if ($raw === '') {
        throw new RuntimeException('APP_ENCRYPTION_KEY manquant dans .env');
    }

    $bin = base64_decode($raw, true);
    if ($bin === false || strlen($bin) !== 32) {
        throw new RuntimeException('APP_ENCRYPTION_KEY invalide (attendu : 32 octets encodés en base64).');
    }

    $key = $bin;
    return $key;
}

function encryptionConfigured(): bool
{
    $raw = env('APP_ENCRYPTION_KEY', '');
    if ($raw === '') {
        return false;
    }
    $bin = base64_decode($raw, true);

    return $bin !== false && strlen($bin) === 32;
}

function isEncryptedValue(?string $value): bool
{
    return $value !== null && $value !== '' && str_starts_with($value, SENSITIVE_ENC_PREFIX);
}

/** Chiffre une chaîne (messages chat, noms de communautés, etc.). */
function encryptSensitive(?string $plaintext): ?string
{
    if ($plaintext === null || $plaintext === '') {
        return $plaintext;
    }
    if (isEncryptedValue($plaintext)) {
        return $plaintext;
    }

    $key = appEncryptionKey();
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt(
        $plaintext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        '',
        16
    );

    if ($cipher === false) {
        throw new RuntimeException('Échec du chiffrement.');
    }

    return SENSITIVE_ENC_PREFIX . base64_encode($iv . $tag . $cipher);
}

/** Déchiffre si préfixe enc1:, sinon renvoie tel quel (données legacy). */
function decryptSensitive(?string $stored): ?string
{
    if ($stored === null || $stored === '') {
        return $stored;
    }
    if (!isEncryptedValue($stored)) {
        return $stored;
    }

    if (!encryptionConfigured()) {
        return '[contenu chiffré]';
    }

    $payload = base64_decode(substr($stored, strlen(SENSITIVE_ENC_PREFIX)), true);
    if ($payload === false || strlen($payload) < 28) {
        return '[contenu illisible]';
    }

    $iv = substr($payload, 0, 12);
    $tag = substr($payload, 12, 16);
    $cipher = substr($payload, 28);

    $plain = openssl_decrypt(
        $cipher,
        'aes-256-gcm',
        appEncryptionKey(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    return $plain === false ? '[contenu illisible]' : $plain;
}

function decryptCommunityRow(array $row, bool $withDescription = true): array
{
    if (array_key_exists('nom', $row)) {
        $row['nom'] = decryptSensitive($row['nom']);
    }
    if ($withDescription && array_key_exists('description', $row)) {
        $row['description'] = decryptSensitive($row['description']);
    }
    if (array_key_exists('community_nom', $row)) {
        $row['community_nom'] = decryptSensitive($row['community_nom']);
    }

    return $row;
}

function decryptMessageRow(array $row): array
{
    if (array_key_exists('contenu', $row)) {
        $row['contenu'] = decryptSensitive($row['contenu']);
    }

    return $row;
}

function encryptionSchemaReadyPath(): string
{
    return APP_CACHE_DIR . '/encryption_schema.ok';
}

function encryptionSchemaUpToDate(PDO $pdo): bool
{
    $stmt = $pdo->query(
        "SELECT TABLE_NAME, COLUMN_NAME, CHARACTER_MAXIMUM_LENGTH, DATA_TYPE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND (
             (TABLE_NAME = 'communities' AND COLUMN_NAME IN ('nom', 'description'))
             OR (TABLE_NAME = 'community_messages' AND COLUMN_NAME = 'contenu')
           )"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) < 3) {
        return false;
    }

    foreach ($rows as $row) {
        if ($row['TABLE_NAME'] === 'communities' && $row['COLUMN_NAME'] === 'nom') {
            if ((int) $row['CHARACTER_MAXIMUM_LENGTH'] < 512) {
                return false;
            }
            continue;
        }
        $type = strtolower((string) $row['DATA_TYPE']);
        if (!in_array($type, ['text', 'mediumtext', 'longtext'], true)) {
            return false;
        }
    }

    return true;
}

function ensureEncryptionSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    if (is_file(encryptionSchemaReadyPath()) || encryptionSchemaUpToDate($pdo)) {
        if (!is_file(encryptionSchemaReadyPath()) && encryptionSchemaUpToDate($pdo)) {
            @file_put_contents(encryptionSchemaReadyPath(), '1');
        }
        $done = true;
        return;
    }

    try {
        $pdo->exec(
            "ALTER TABLE communities
             MODIFY nom VARCHAR(512) NOT NULL COMMENT 'Nom chiffré (enc1:...)'"
        );
        $pdo->exec(
            "ALTER TABLE communities
             MODIFY description TEXT NULL COMMENT 'Description chiffrée (enc1:...)'"
        );
        $pdo->exec(
            "ALTER TABLE community_messages
             MODIFY contenu TEXT NOT NULL COMMENT 'Message chiffré (enc1:...)'"
        );
    } catch (Throwable $e) {
        // Droits ALTER limités — migration manuelle via db/migrations/002_encryption.sql
    }

    if (encryptionSchemaUpToDate($pdo)) {
        @file_put_contents(encryptionSchemaReadyPath(), '1');
    }
    $done = true;
}
