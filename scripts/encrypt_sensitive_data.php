<?php
/**
 * Migration : chiffre les noms/descriptions de communautés et les messages chat.
 * Usage : php scripts/encrypt_sensitive_data.php
 *
 * Prérequis : APP_ENCRYPTION_KEY défini dans .env (32 octets base64).
 * Générer une clé : php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
 */
define('APP_BOOT', true);

require __DIR__ . '/../app/env.php';
loadEnvFile(dirname(__DIR__) . '/.env');
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/db.php';
require __DIR__ . '/../app/encryption.php';

if (!encryptionConfigured()) {
    fwrite(STDERR, "Erreur : définissez APP_ENCRYPTION_KEY dans .env avant de lancer ce script.\n");
    fwrite(STDERR, "Générer une clé : php -r \"echo base64_encode(random_bytes(32)) . PHP_EOL;\"\n");
    exit(1);
}

$pdo = getPDO();
ensureEncryptionSchema($pdo);

$communities = 0;
$stmt = $pdo->query('SELECT id, nom, description FROM communities');
while ($row = $stmt->fetch()) {
    $nom = encryptSensitive($row['nom']);
    $desc = $row['description'] !== null && $row['description'] !== ''
        ? encryptSensitive($row['description'])
        : $row['description'];

    if ($nom === $row['nom'] && $desc === $row['description']) {
        continue;
    }

    $upd = $pdo->prepare('UPDATE communities SET nom = ?, description = ? WHERE id = ?');
    $upd->execute([$nom, $desc, $row['id']]);
    $communities++;
}

$messages = 0;
$stmt = $pdo->query('SELECT id, contenu FROM community_messages');
while ($row = $stmt->fetch()) {
    $enc = encryptSensitive($row['contenu']);
    if ($enc === $row['contenu']) {
        continue;
    }
    $pdo->prepare('UPDATE community_messages SET contenu = ? WHERE id = ?')->execute([$enc, $row['id']]);
    $messages++;
}

echo "Migration terminée.\n";
echo "- Communautés chiffrées : {$communities}\n";
echo "- Messages chiffrés : {$messages}\n";
echo "Conservez APP_ENCRYPTION_KEY en lieu sûr : sans elle, les données sont irrécupérables.\n";
