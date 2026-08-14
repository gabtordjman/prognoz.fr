<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/** Taille max acceptée à l’upload (avant compression serveur). */
define('AVATAR_MAX_BYTES', 5 * 1024 * 1024);
/** Côté max de l’image source (px). */
define('AVATAR_MAX_SIDE', 8000);
/** Taille stockée / affichée (carré). */
define('AVATAR_OUTPUT_SIDE', 256);
/** Objectif disque après compression (≈ 40–50 Ko / photo). */
define('AVATAR_TARGET_BYTES', 48 * 1024);
define('AVATAR_DIR_REL', 'uploads/avatars');

/** Crée le dossier public d’avatars si besoin. */
function avatarStorageDir(): string
{
    return dirname(__DIR__) . '/public/' . AVATAR_DIR_REL;
}

function ensureAvatarSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar_url'")->fetch();
        if (!$col) {
            $pdo->exec('ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) NULL AFTER password_hash');
        }
    } catch (Throwable $e) {
        // migration manuelle possible
    }
    $dir = avatarStorageDir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $index = $dir . '/index.php';
    if (!is_file($index)) {
        @file_put_contents($index, "<?php http_response_code(404); echo 'Not found';\n");
    }
    $ht = dirname($dir) . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Options -Indexes\n");
    }
}

/** Chemin relatif public (uploads/avatars/…) ou null. */
function normalizeAvatarPath(?string $avatarUrl): ?string
{
    if ($avatarUrl === null) {
        return null;
    }
    $path = str_replace('\\', '/', trim($avatarUrl));
    if ($path === '' || str_contains($path, '..')) {
        return null;
    }
    if (!str_starts_with($path, AVATAR_DIR_REL . '/')) {
        return null;
    }
    if (!preg_match('#^uploads/avatars/[a-zA-Z0-9._-]+$#', $path)) {
        return null;
    }

    return $path;
}

/**
 * URL navigateur pour une photo, ou null.
 * Sur vieux navigateurs (pas de WebP) : préfère un .jpg jumeau, sinon génère
 * un JPEG une fois, sinon null (initiales) pour éviter l’icône cassée.
 */
function avatarPublicUrl(?string $avatarUrl): ?string
{
    $path = normalizeAvatarPath($avatarUrl);
    if ($path === null) {
        return null;
    }
    $baseDir = dirname(__DIR__) . '/public/';
    $full = $baseDir . $path;
    if (!is_file($full)) {
        return null;
    }

    $legacy = function_exists('isLegacyBrowser') && isLegacyBrowser();
    if ($legacy && preg_match('/\.webp$/i', $path)) {
        $jpgRel = preg_replace('/\.webp$/i', '.jpg', $path);
        $jpgFull = $baseDir . $jpgRel;
        if (is_file($jpgFull)) {
            return assetUrl($jpgRel);
        }
        if (function_exists('imagecreatefromwebp') && function_exists('imagejpeg')) {
            $img = @imagecreatefromwebp($full);
            if ($img !== false) {
                $ok = @imagejpeg($img, $jpgFull, 82);
                imagedestroy($img);
                if ($ok && is_file($jpgFull)) {
                    return assetUrl($jpgRel);
                }
            }
        }

        // Pas de JPEG utilisable → initiales plutôt qu’image cassée
        return null;
    }

    return assetUrl($path);
}

function avatarAbsolutePath(?string $avatarUrl): ?string
{
    $path = normalizeAvatarPath($avatarUrl);
    if ($path === null) {
        return null;
    }
    $full = dirname(__DIR__) . '/public/' . $path;

    return is_file($full) ? $full : null;
}

function deleteAvatarFile(?string $avatarUrl): void
{
    $full = avatarAbsolutePath($avatarUrl);
    if ($full !== null) {
        @unlink($full);
    }
}

/**
 * Détecte le MIME réel (finfo + getimagesize) — certains WebP arrivent mal typés.
 *
 * @return array{0:?string,1:?array} [mime, getimagesize info]
 */
function avatarDetectImage(string $tmp): array
{
    $info = @getimagesize($tmp);
    $mime = null;

    if (is_array($info) && isset($info[2])) {
        $mime = match ((int) $info[2]) {
            IMAGETYPE_JPEG => 'image/jpeg',
            IMAGETYPE_PNG  => 'image/png',
            IMAGETYPE_WEBP => 'image/webp',
            default        => null,
        };
    }

    if ($mime === null && class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = strtolower((string) $finfo->file($tmp));
        $aliases = [
            'image/jpeg' => 'image/jpeg',
            'image/jpg'  => 'image/jpeg',
            'image/pjpeg'=> 'image/jpeg',
            'image/png'  => 'image/png',
            'image/x-png'=> 'image/png',
            'image/webp' => 'image/webp',
            'image/x-webp' => 'image/webp',
        ];
        $mime = $aliases[$detected] ?? null;
    }

    return [$mime, is_array($info) ? $info : null];
}

/** Charge une image uploadée (WebP parfois capricieux → fallback binary). */
function avatarLoadGdImage(string $tmp, string $mime): \GdImage|false
{
    try {
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png'  => @imagecreatefrompng($tmp),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
            default      => false,
        };
        if ($src instanceof \GdImage) {
            return $src;
        }

        $blob = @file_get_contents($tmp);
        if ($blob !== false && $blob !== '') {
            $src = @imagecreatefromstring($blob);
            if ($src instanceof \GdImage) {
                return $src;
            }
        }
    } catch (Throwable $e) {
        error_log('Prognoz avatar load: ' . $e->getMessage());
    }

    return false;
}

/** Corrige l’orientation EXIF des JPEG téléphone. */
function avatarApplyExifOrientation(\GdImage $src, string $tmp, string $mime): \GdImage
{
    if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
        return $src;
    }
    $exif = @exif_read_data($tmp);
    if (!is_array($exif) || empty($exif['Orientation'])) {
        return $src;
    }
    $orientation = (int) $exif['Orientation'];
    $rotated = match ($orientation) {
        3 => @imagerotate($src, 180, 0),
        6 => @imagerotate($src, -90, 0),
        8 => @imagerotate($src, 90, 0),
        default => false,
    };
    if (!($rotated instanceof \GdImage)) {
        return $src;
    }
    imagedestroy($src);

    return $rotated;
}

/**
 * Écrit l’avatar compressé (WebP préféré, sinon JPEG).
 *
 * @return array{0:string,1:string} [chemin absolu, extension]
 */
function avatarWriteCompressed(\GdImage $img, string $destBase): array
{
    $qualities = [78, 70, 62, 55, 48];
    $candidates = [];

    // Image opaque (meilleure compat / compression)
    if (function_exists('imagepalettetotruecolor')) {
        @imagepalettetotruecolor($img);
    }
    imagealphablending($img, true);
    imagesavealpha($img, false);

    if (function_exists('imagewebp')) {
        foreach ($qualities as $q) {
            $path = $destBase . '.webp';
            $ok = false;
            try {
                $ok = @imagewebp($img, $path, $q);
            } catch (Throwable $e) {
                $ok = false;
            }
            if (!$ok || !is_file($path) || filesize($path) <= 0) {
                @unlink($path);
                continue;
            }
            $size = (int) filesize($path);
            $candidates[] = [$path, 'webp', $size];
            if ($size <= AVATAR_TARGET_BYTES) {
                // JPEG jumeau pour IE / vieux Safari (pas de WebP)
                $jpgPath = $destBase . '.jpg';
                @imagejpeg($img, $jpgPath, 82);

                return [$path, 'webp'];
            }
        }
    }

    foreach ($qualities as $q) {
        $path = $destBase . '.jpg';
        $ok = false;
        try {
            $ok = @imagejpeg($img, $path, $q);
        } catch (Throwable $e) {
            $ok = false;
        }
        if (!$ok || !is_file($path) || filesize($path) <= 0) {
            @unlink($path);
            continue;
        }
        $size = (int) filesize($path);
        $candidates[] = [$path, 'jpg', $size];
        if ($size <= AVATAR_TARGET_BYTES) {
            $webpPath = $destBase . '.webp';
            if (is_file($webpPath)) {
                @unlink($webpPath);
            }

            return [$path, 'jpg'];
        }
    }

    if ($candidates === []) {
        throw new RuntimeException(t('avatar.err.storage'));
    }

    usort($candidates, static fn (array $a, array $b): int => $a[2] <=> $b[2]);
    [$bestPath, $bestExt] = $candidates[0];
    foreach ($candidates as [$path]) {
        if ($path !== $bestPath && is_file($path)) {
            // Garder un .jpg jumeau si le gagnant est .webp
            if ($bestExt === 'webp' && preg_match('/\.jpg$/i', (string) $path)) {
                continue;
            }
            @unlink($path);
        }
    }
    if ($bestExt === 'webp') {
        $jpgPath = $destBase . '.jpg';
        if (!is_file($jpgPath)) {
            @imagejpeg($img, $jpgPath, 82);
        }
    }

    return [$bestPath, $bestExt];
}

/**
 * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int} $file
 */
function uploadUserAvatar(PDO $pdo, int $userId, array $file): string
{
    ensureAvatarSchema($pdo);
    if ($userId <= 0) {
        throw new InvalidArgumentException(t('avatar.err.user'));
    }
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        throw new InvalidArgumentException(t('avatar.err.too_big'));
    }
    if ($err !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException(t('avatar.err.upload'));
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > AVATAR_MAX_BYTES) {
        throw new InvalidArgumentException(t('avatar.err.too_big'));
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new InvalidArgumentException(t('avatar.err.upload'));
    }

    [$mime, $info] = avatarDetectImage($tmp);
    $allowed = ['image/jpeg' => true, 'image/png' => true, 'image/webp' => true];
    if ($mime === null || !isset($allowed[$mime])) {
        throw new InvalidArgumentException(t('avatar.err.type'));
    }
    if ($mime === 'image/webp' && !function_exists('imagecreatefromwebp') && !function_exists('imagecreatefromstring')) {
        throw new InvalidArgumentException(t('avatar.err.process'));
    }

    if ($info === null || empty($info[0]) || empty($info[1])) {
        throw new InvalidArgumentException(t('avatar.err.type'));
    }
    $w = (int) $info[0];
    $h = (int) $info[1];
    if ($w < 32 || $h < 32) {
        throw new InvalidArgumentException(t('avatar.err.too_small'));
    }
    if ($w > AVATAR_MAX_SIDE || $h > AVATAR_MAX_SIDE) {
        throw new InvalidArgumentException(t('avatar.err.dims'));
    }

    $prevMem = ini_get('memory_limit');
    if (is_string($prevMem) && $prevMem !== '' && $prevMem !== '-1') {
        @ini_set('memory_limit', '256M');
    }

    $src = avatarLoadGdImage($tmp, $mime);
    if (!($src instanceof \GdImage)) {
        throw new InvalidArgumentException(t('avatar.err.process'));
    }
    $src = avatarApplyExifOrientation($src, $tmp, $mime);
    $w = imagesx($src);
    $h = imagesy($src);

    $side = AVATAR_OUTPUT_SIDE;
    $dst = imagecreatetruecolor($side, $side);
    if ($dst === false) {
        imagedestroy($src);
        throw new InvalidArgumentException(t('avatar.err.process'));
    }
    $bg = imagecolorallocate($dst, 245, 240, 230);
    imagefilledrectangle($dst, 0, 0, $side, $side, $bg);

    $scale = max($side / $w, $side / $h);
    $nw = (int) round($w * $scale);
    $nh = (int) round($h * $scale);
    $dx = (int) round(($side - $nw) / 2);
    $dy = (int) round(($side - $nh) / 2);
    imagecopyresampled($dst, $src, $dx, $dy, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    $dir = avatarStorageDir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        imagedestroy($dst);
        throw new RuntimeException(t('avatar.err.storage'));
    }
    if (!is_writable($dir)) {
        imagedestroy($dst);
        throw new RuntimeException(t('avatar.err.storage'));
    }

    $token = bin2hex(random_bytes(8));
    $baseName = $userId . '_' . $token;
    $destBase = $dir . '/' . $baseName;

    try {
        [$dest, $ext] = avatarWriteCompressed($dst, $destBase);
    } finally {
        imagedestroy($dst);
    }
    @chmod($dest, 0644);

    $rel = AVATAR_DIR_REL . '/' . $baseName . '.' . $ext;

    try {
        $stmt = $pdo->prepare('SELECT avatar_url FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $old = $stmt->fetchColumn();
        $pdo->prepare('UPDATE users SET avatar_url = ? WHERE id = ?')->execute([$rel, $userId]);
    } catch (Throwable $e) {
        @unlink($dest);
        error_log('Prognoz avatar DB: ' . $e->getMessage());
        throw new RuntimeException(t('avatar.err.generic'));
    }

    if (is_string($old) && $old !== '' && $old !== $rel) {
        deleteAvatarFile($old);
    }
    clearCurrentUserCache();

    return $rel;
}

function removeUserAvatar(PDO $pdo, int $userId): void
{
    ensureAvatarSchema($pdo);
    if ($userId <= 0) {
        throw new InvalidArgumentException(t('avatar.err.user'));
    }
    $stmt = $pdo->prepare('SELECT avatar_url FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $old = $stmt->fetchColumn();
    $pdo->prepare('UPDATE users SET avatar_url = NULL WHERE id = ?')->execute([$userId]);
    if (is_string($old) && $old !== '') {
        deleteAvatarFile($old);
    }
    clearCurrentUserCache();
}

/** @return list<array{id:int,pseudo:string,avatar_url:string,created_at:?string}> */
function listUsersWithAvatars(PDO $pdo, int $limit = 60): array
{
    ensureAvatarSchema($pdo);
    $limit = max(1, min(200, $limit));
    $stmt = $pdo->query(
        'SELECT id, pseudo, avatar_url, created_at
         FROM users
         WHERE avatar_url IS NOT NULL AND avatar_url != ""
         ORDER BY id DESC
         LIMIT ' . $limit
    );

    return $stmt ? $stmt->fetchAll() : [];
}
