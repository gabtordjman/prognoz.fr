<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/** Échappement anti-XSS pour l'affichage */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Génère (ou réutilise) un jeton CSRF pour la session courante */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Vérifie le jeton CSRF envoyé par un formulaire POST */
function csrfCheck(): bool
{
    $envoye = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $envoye);
}

/** Vérifie le jeton CSRF dans un corps JSON (API). */
function csrfCheckJson(array $body): bool
{
    $envoye = (string) ($body['csrf_token'] ?? '');
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $envoye);
}

/** Petit champ caché prêt à insérer dans un <form> */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/** Stocke un message flash (affiché puis supprimé à la page suivante) */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Récupère et vide les messages flash en attente */
function getFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/** Libère le verrou session (requis pour les API appelées en parallèle, ex. chat). */
function releaseSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

/** Chemin URL de base du dossier public/ (stable, quel que soit le sous-dossier de la page). */
function publicBasePath(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $publicDir = str_replace('\\', '/', dirname(__DIR__) . '/public');
    $docRoot   = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');

    if ($docRoot !== '' && str_starts_with($publicDir, $docRoot)) {
        $rel = trim(substr($publicDir, strlen($docRoot)), '/');
        $base = $rel === '' ? '/' : '/' . $rel . '/';
        return $base;
    }

    $base = '/';
    return $base;
}

/**
 * Normalise un chemin public pour les URLs v1.0 (sans .php).
 * - retire le suffixe .php (query conservée)
 * - index → accueil (chemin vide)
 */
function prettyPublicPath(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    $path = ltrim($path, '/');

    $query = '';
    $qPos = strpos($path, '?');
    if ($qPos !== false) {
        $query = substr($path, $qPos);
        $path  = substr($path, 0, $qPos);
    }

    if ($path !== '' && preg_match('/\.php$/i', $path)) {
        $path = substr($path, 0, -4);
    }

    if ($path === 'index' || $path === '') {
        $path = '';
    }

    return $path . $query;
}

/**
 * Plages Cloudflare (https://www.cloudflare.com/ips-v4 / ips-v6).
 *
 * @return list<string>
 */
function cloudflareIpRanges(): array
{
    return [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];
}

function ipMatchesCidr(string $ip, string $cidr): bool
{
    $ip = trim($ip);
    $cidr = trim($cidr);
    if ($ip === '' || $cidr === '') {
        return false;
    }
    if (!str_contains($cidr, '/')) {
        return strcasecmp($ip, $cidr) === 0;
    }
    [$subnet, $bitsRaw] = explode('/', $cidr, 2);
    $bits = (int) $bitsRaw;
    $ipBin = @inet_pton($ip);
    $subBin = @inet_pton($subnet);
    if ($ipBin === false || $subBin === false || strlen($ipBin) !== strlen($subBin)) {
        return false;
    }
    $maxBits = strlen($ipBin) * 8;
    if ($bits < 0 || $bits > $maxBits) {
        return false;
    }
    $fullBytes = intdiv($bits, 8);
    $remain = $bits % 8;
    if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subBin, 0, $fullBytes)) {
        return false;
    }
    if ($remain === 0) {
        return true;
    }
    $mask = (0xFF << (8 - $remain)) & 0xFF;

    return (ord($ipBin[$fullBytes]) & $mask) === (ord($subBin[$fullBytes]) & $mask);
}

function ipInAllowlist(string $ip, array $allow): bool
{
    foreach ($allow as $entry) {
        if (ipMatchesCidr($ip, (string) $entry)) {
            return true;
        }
    }

    return false;
}

function requestFromCloudflare(): bool
{
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    return $remote !== '' && ipInAllowlist($remote, cloudflareIpRanges());
}

/** IP client réelle (CF-Connecting-IP derrière Cloudflare, sinon socket). */
function clientIp(): string
{
    $cf = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP) && requestFromCloudflare()) {
        return $cf;
    }

    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($xff !== '' && requestFromCloudflare()) {
        $parts = array_map('trim', explode(',', $xff));
        if ($parts[0] !== '' && filter_var($parts[0], FILTER_VALIDATE_IP)) {
            return $parts[0];
        }
    }

    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * Rate limit simple par clé (fichier cache).
 * @return bool true si la requête est autorisée
 */
function rateLimitAllow(string $key, int $maxHits, int $windowSec): bool
{
    $dir = dirname(__DIR__) . '/var/cache/rate_limit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $file = $dir . '/' . md5($key) . '.json';
    $now  = time();
    $data = ['hits' => []];

    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded['hits'] ?? null)) {
            $data = $decoded;
        }
    }

    $cutoff = $now - $windowSec;
    $data['hits'] = array_values(array_filter(
        $data['hits'],
        static fn ($t) => (int) $t > $cutoff
    ));

    if (count($data['hits']) >= $maxHits) {
        return false;
    }

    $data['hits'][] = $now;
    @file_put_contents($file, json_encode($data), LOCK_EX);

    return true;
}

/** Lien mailto pour demander une réinitialisation de mot de passe (admin manuel). */
function forgotPasswordMailtoUrl(): string
{
    $subject = APP_NAME . ' — Mot de passe oublié';
    $body = "Bonjour,\n\n"
        . "Je souhaite réinitialiser mon mot de passe sur " . APP_NAME . ".\n\n"
        . "Mon pseudo :\n"
        . "Mon e-mail :\n\n"
        . "Merci.";

    return 'mailto:' . APP_CONTACT_EMAIL
        . '?subject=' . rawurlencode($subject)
        . '&body=' . rawurlencode($body);
}

/** URL absolue vers une ressource du site (Open Graph, partage). */
function absoluteUrl(string $path = ''): string
{
    $pretty = prettyPublicPath($path);
    $configured = env('APP_URL', '');
    if ($configured !== '') {
        if ($pretty === '') {
            return rtrim($configured, '/') . '/';
        }

        return rtrim($configured, '/') . '/' . $pretty;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . url($path);
}

/** URL absolue d'invitation communauté. */
function absoluteInviteUrl(string $code): string
{
    return absoluteUrl('communities/invite.php?code=' . rawurlencode($code));
}

function appTimezone(): DateTimeZone
{
    static $tz = null;
    if ($tz === null) {
        $tz = new DateTimeZone(APP_TIMEZONE);
    }

    return $tz;
}

function utcStorageTimezone(): DateTimeZone
{
    static $tz = null;
    if ($tz === null) {
        $tz = new DateTimeZone('UTC');
    }

    return $tz;
}

/** Parse une datetime stockée en BDD (UTC, format Y-m-d H:i:s). */
function parseUtcDatetime(string $datetime): ?DateTimeImmutable
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datetime, utcStorageTimezone());
    if ($dt instanceof DateTimeImmutable) {
        return $dt;
    }

    try {
        return (new DateTimeImmutable($datetime, utcStorageTimezone()));
    } catch (Exception $e) {
        return null;
    }
}

function utcDatetimeTimestamp(string $datetime): int|false
{
    $dt = parseUtcDatetime($datetime);

    return $dt ? $dt->getTimestamp() : false;
}

/** Jour + date/heure d'un match (ex. Mer 23/07 · 21:00 / Wed 23/07 · 21:00). */
function formatMatchWhen(string $datetime): string
{
    $dt = parseUtcDatetime($datetime);
    if (!$dt) {
        return $datetime;
    }
    $local = $dt->setTimezone(appTimezone());
    $dow = $local->format('D');
    $day = t('date.dow.' . $dow);

    return $day . ' ' . $local->format('d/m · H:i');
}

/** Initiales affichées dans l'avatar (1–2 caractères). */
function userInitials(string $pseudo): string
{
    $pseudo = trim($pseudo);
    if ($pseudo === '') {
        return '?';
    }
    $parts = preg_split('/[\s_\-]+/u', $pseudo, -1, PREG_SPLIT_NO_EMPTY);
    if (count($parts) >= 2) {
        return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    }

    return mb_strtoupper(mb_substr($pseudo, 0, min(2, mb_strlen($pseudo))));
}

/** Couleur d'avatar stable selon le pseudo. */
function userAvatarColor(string $pseudo): string
{
    $hash = crc32(mb_strtolower(trim($pseudo)));
    $hues = [145, 168, 195, 32, 12, 280, 220, 45, 350, 200];
    $hue = $hues[abs($hash) % count($hues)];

    return "hsl({$hue}, 44%, 36%)";
}

/** Avatar rond : photo de profil ou initiales. */
function renderUserAvatar(string $pseudo, string $size = 'md', ?string $avatarUrl = null): void
{
    $sizeClass = in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
    $src = avatarPublicUrl($avatarUrl);
    $hasPhoto = $src !== null;
    $px = $sizeClass === 'lg' ? 56 : ($sizeClass === 'sm' ? 28 : 36);
    ?>
    <span class="user-avatar user-avatar-<?= e($sizeClass) ?><?= $hasPhoto ? ' has-photo' : '' ?>" style="background-color: <?= e(userAvatarColor($pseudo)) ?>;" title="<?= e($pseudo) ?>"<?= $hasPhoto ? '' : ' aria-hidden="true"' ?>>
        <?php if ($hasPhoto): ?>
            <img src="<?= e($src) ?>" alt="" width="<?= $px ?>" height="<?= $px ?>"<?= function_exists('isLegacyBrowser') && isLegacyBrowser() ? '' : ' loading="lazy" decoding="async"' ?>>
        <?php else: ?>
            <span class="user-avatar-initials"><?= e(userInitials($pseudo)) ?></span>
        <?php endif; ?>
    </span>
    <?php
}

/** Résultat récent (flash dashboard). */
function isFreshPredictionResult(?string $resolvedAt, int $withinHours = 6): bool
{
    if ($resolvedAt === null || $resolvedAt === '') {
        return false;
    }
    $ts = strtotime($resolvedAt);

    return $ts !== false && (time() - $ts) < $withinHours * 3600;
}

/** Crée var/cache si besoin ; retourne false si le serveur web ne peut pas y écrire. */
function ensureAppCacheDir(): bool
{
    $dir = APP_CACHE_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (!is_dir($dir)) {
        return false;
    }
    if (!is_writable($dir)) {
        return false;
    }
    return true;
}

/** @return array{dir:string,exists:bool,writable:bool} */
function appCacheStatus(): array
{
    return [
        'dir'       => APP_CACHE_DIR,
        'exists'    => is_dir(APP_CACHE_DIR),
        'writable'  => is_dir(APP_CACHE_DIR) && is_writable(APP_CACHE_DIR),
    ];
}

/** Sync forcée depuis le site (Paramètres) — pas via curl. */
function userCanForceSync(PDO $pdo, int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }
    if (isSiteAdminUser($userId) || in_array($userId, SYNC_ADMIN_USER_IDS, true)) {
        return true;
    }
    $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ? AND actif = 1');
    $stmt->execute([$userId]);
    $email = $stmt->fetchColumn();
    if ($email === false) {
        return false;
    }
    $contact = strtolower(trim(APP_CONTACT_EMAIL));

    return $contact !== '' && strtolower(trim((string) $email)) === $contact;
}

/** Propriétaire / staff visible (badge ADMIN). */
function isSiteAdminUser(int $userId): bool
{
    return $userId > 0 && in_array($userId, SITE_ADMIN_USER_IDS, true);
}

/** HTML du badge ADMIN (libellé fixe, pas i18n). */
function adminBadgeHtml(): string
{
    return '<span class="badge-admin" title="Admin">ADMIN</span>';
}

function syncForbiddenResponse(string $reason): never
{
    http_response_code(403);
    $payload = ['ok' => false, 'error' => 'Forbidden', 'reason' => $reason];
    if (CRON_SECRET === '') {
        $payload['hint'] = 'CRON_SECRET absent du .env serveur — définissez-le puis relancez.';
    } elseif ($reason === 'cron_key') {
        $payload['hint'] = 'Clé invalide : utilisez exactement CRON_SECRET du .env (paramètre ?key=...).';
    } else {
        $payload['hint'] = 'Sync complète réservée au cron (?key=CRON_SECRET) ou au compte admin (Paramètres).';
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
