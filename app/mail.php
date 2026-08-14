<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/** Dernière erreur d'envoi (diagnostic). */
function lastMailError(): string
{
    return $GLOBALS['_mail_last_error'] ?? '';
}

function setMailError(string $message): void
{
    $GLOBALS['_mail_last_error'] = $message;
}

function mailFromAddress(): string
{
    $smtpUser = trim(env('MAIL_SMTP_USER', ''));
    if (smtpConfigured() && $smtpUser !== '' && filter_var($smtpUser, FILTER_VALIDATE_EMAIL)) {
        return $smtpUser;
    }

    return env('MAIL_FROM', env('APP_CONTACT_EMAIL', 'noreply@prognoz.fr'));
}

function mailFromName(): string
{
    return env('MAIL_FROM_NAME', APP_NAME);
}

function smtpConfigured(): bool
{
    return trim(env('MAIL_SMTP_HOST', '')) !== '';
}

/** Colonne users.mail_opt_out (désinscription e-mails applicatifs). */
function ensureMailPrefsSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM users LIKE "mail_opt_out"')->fetch();
        if (!$cols) {
            $pdo->exec(
                'ALTER TABLE users ADD COLUMN mail_opt_out TINYINT(1) NOT NULL DEFAULT 0
                 COMMENT "1 = pas d’e-mails applicatifs (rappels, bilans)" AFTER email'
            );
        }
        $cols = $pdo->query('SHOW COLUMNS FROM users LIKE "preferred_lang"')->fetch();
        if (!$cols) {
            $pdo->exec(
                'ALTER TABLE users ADD COLUMN preferred_lang VARCHAR(2) NULL
                 COMMENT "fr|en pour e-mails" AFTER mail_opt_out'
            );
        }
    } catch (PDOException $e) {
        // Migration manuelle si droits limités
    }
}

/** True si le joueur accepte les e-mails non critiques (rappels, bilans, etc.). */
function userAllowsAppMail(array $user): bool
{
    return empty($user['mail_opt_out']);
}

function setUserMailOptOut(PDO $pdo, int $userId, bool $optOut): void
{
    ensureMailPrefsSchema($pdo);
    $pdo->prepare('UPDATE users SET mail_opt_out = ? WHERE id = ?')
        ->execute([$optOut ? 1 : 0, $userId]);
}

/**
 * Langue des e-mails : preferred_lang, sinon heuristique e-mail (.de → en, .fr → fr…).
 *
 * @param array{email?:string,preferred_lang?:?string} $user
 */
function resolveMailLang(array $user): string
{
    $pref = strtolower(trim((string) ($user['preferred_lang'] ?? '')));
    if (in_array($pref, APP_LANGS, true)) {
        return $pref;
    }

    return guessMailLangFromEmail((string) ($user['email'] ?? ''));
}

function guessMailLangFromEmail(string $email): string
{
    $email = strtolower(trim($email));
    if ($email === '' || !str_contains($email, '@')) {
        return 'fr';
    }
    $domain = substr($email, strrpos($email, '@') + 1);

    $frDomains = [
        'orange.fr', 'free.fr', 'laposte.net', 'sfr.fr', 'wanadoo.fr', 'bbox.fr',
        'club-internet.fr', 'numericable.fr', 'aliceadsl.fr', 'gmail.fr',
    ];
    if (in_array($domain, $frDomains, true) || str_ends_with($domain, '.fr')) {
        return 'fr';
    }

    $enHintDomains = [
        'live.de', 'outlook.de', 'hotmail.de', 'gmx.de', 'gmx.net', 'web.de',
        't-online.de', 'mail.de', 'live.at', 'outlook.at', 'gmx.at',
        'live.co.uk',
    ];
    if (in_array($domain, $enHintDomains, true)) {
        return 'en';
    }

    foreach (['.de', '.at', '.uk', '.us', '.nl', '.pl', '.it', '.es', '.se', '.no', '.dk', '.fi'] as $tld) {
        if (str_ends_with($domain, $tld)) {
            return 'en';
        }
    }

    return 'fr';
}

/**
 * Envoie un e-mail (SMTP si configuré, sinon mail() PHP).
 * Si $bodyHtml est fourni → multipart/alternative (texte + HTML).
 */
function sendAppMail(string $to, string $subject, string $bodyText, ?string $bodyHtml = null): bool
{
    setMailError('');
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        setMailError('Adresse destinataire invalide.');
        return false;
    }

    $from = mailFromAddress();
    $name = mailFromName();

    if (smtpConfigured()) {
        return sendSmtpMail($to, $subject, $bodyText, $from, $name, $bodyHtml);
    }

    setMailError('SMTP non configuré (MAIL_SMTP_HOST vide) — mail() PHP utilisé, souvent inactif sur un VPS.');

    $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'MIME-Version: 1.0',
        'From: ' . formatMailAddress($name, $from),
        'Reply-To: ' . APP_CONTACT_EMAIL,
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    if ($bodyHtml !== null && trim($bodyHtml) !== '') {
        $boundary = 'prognoz_' . bin2hex(random_bytes(8));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $body = buildMultipartAlternativeBody($boundary, $bodyText, $bodyHtml);
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $body = $bodyText;
    }

    $ok = @mail($to, $subjectEnc, $body, implode("\r\n", $headers));
    if (!$ok) {
        setMailError('mail() PHP a échoué.');
    }

    return $ok;
}

function buildMultipartAlternativeBody(string $boundary, string $bodyText, string $bodyHtml): string
{
    return implode("\r\n", [
        '--' . $boundary,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        $bodyText,
        '--' . $boundary,
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        $bodyHtml,
        '--' . $boundary . '--',
        '',
    ]);
}

/**
 * Gabarit HTML Prognoz (look site : felt / papier / laiton).
 *
 * @param array{
 *   title?:string,
 *   preheader?:string,
 *   greeting?:string,
 *   body_html?:string,
 *   cta_url?:string,
 *   cta_label?:string,
 *   footer_note?:string,
 *   lang?:string
 * } $opts
 */
function renderAppMailHtml(array $opts): string
{
    $lang = (string) ($opts['lang'] ?? 'fr');
    if (!in_array($lang, APP_LANGS, true)) {
        $lang = 'fr';
    }
    $app = htmlspecialchars(APP_NAME, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $title = htmlspecialchars((string) ($opts['title'] ?? APP_NAME), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $preheader = htmlspecialchars((string) ($opts['preheader'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $greeting = htmlspecialchars((string) ($opts['greeting'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $bodyHtml = (string) ($opts['body_html'] ?? '');
    $ctaUrl = htmlspecialchars((string) ($opts['cta_url'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $defaultCta = $lang === 'en' ? 'Open' : 'Ouvrir';
    $ctaLabel = htmlspecialchars((string) ($opts['cta_label'] ?? $defaultCta), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $defaultFooter = $lang === 'en'
        ? ('© ' . date('Y') . ' ' . APP_NAME . ' — free game, no real money.')
        : ('© ' . date('Y') . ' ' . APP_NAME . ' — jeu gratuit, sans argent réel.');
    $footer = htmlspecialchars(
        (string) ($opts['footer_note'] ?? $defaultFooter),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );
    $logoUrl = htmlspecialchars(absoluteUrl('assets/img/apple-touch-icon.svg'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $siteUrl = htmlspecialchars(absoluteUrl('index.php'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $htmlLang = htmlspecialchars($lang, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $ctaBlock = '';
    if ($ctaUrl !== '') {
        $ctaBlock = '
          <tr>
            <td style="padding:8px 28px 28px;text-align:center;">
              <a href="' . $ctaUrl . '" style="display:inline-block;background:#2d6b48;color:#ffffff;font-family:Figtree,Segoe UI,Arial,sans-serif;font-size:15px;font-weight:700;text-decoration:none;padding:12px 22px;border-radius:8px;border:1px solid #1e5035;">'
              . $ctaLabel .
              '</a>
            </td>
          </tr>';
    }

    return '<!DOCTYPE html>
<html lang="' . $htmlLang . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . $title . '</title>
</head>
<body style="margin:0;padding:0;background:#0f1a14;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . $preheader . '</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0f1a14;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#e4d9c4;border-radius:10px;overflow:hidden;border:1px solid rgba(90,75,55,0.28);">
          <tr>
            <td style="background:#4a3424;background-image:linear-gradient(180deg,#5a3e2c 0%,#4a3424 45%,#3a2618 100%);padding:18px 24px;border-bottom:2px solid #9a7420;text-align:center;">
              <a href="' . $siteUrl . '" style="text-decoration:none;display:inline-block;">
                <img src="' . $logoUrl . '" width="48" height="48" alt="' . $app . '" style="display:block;margin:0 auto 8px;border:0;border-radius:10px;">
                <span style="font-family:Figtree,Segoe UI,Arial,sans-serif;font-size:18px;font-weight:800;letter-spacing:0.14em;color:#e4d9c4;text-transform:uppercase;">'
                . $app .
                '</span>
              </a>
            </td>
          </tr>
          <tr>
            <td style="padding:28px 28px 8px;font-family:Figtree,Segoe UI,Arial,sans-serif;color:#1a1612;">
              ' . ($greeting !== '' ? '<p style="margin:0 0 12px;font-size:16px;font-weight:700;">' . $greeting . '</p>' : '') . '
              <div style="margin:0;font-size:15px;line-height:1.55;color:#1a1612;">' . $bodyHtml . '</div>
            </td>
          </tr>
          ' . $ctaBlock . '
          <tr>
            <td style="padding:0 28px 22px;font-family:Figtree,Segoe UI,Arial,sans-serif;font-size:12px;line-height:1.45;color:#5c5345;text-align:center;">
              ' . $footer . '
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
}

function formatMailAddress(string $name, string $email): string
{
    return sprintf('"%s" <%s>', str_replace(['"', "\r", "\n"], '', $name), $email);
}

/**
 * @param array{pseudo?:string,email?:string,preferred_lang?:?string}|string $userOrPseudo
 */
function sendPasswordResetMail(string $to, string|array $userOrPseudo, string $resetUrl): bool
{
    if (is_array($userOrPseudo)) {
        $pseudo = (string) ($userOrPseudo['pseudo'] ?? '');
        $lang = resolveMailLang(array_merge($userOrPseudo, ['email' => $to]));
    } else {
        $pseudo = $userOrPseudo;
        $lang = resolveMailLang(['email' => $to]);
    }

    return withLang($lang, static function () use ($to, $pseudo, $resetUrl, $lang): bool {
        $subject = APP_NAME . ' — ' . t('mail.reset.subject');
        $body = t('mail.reset.hello', ['name' => $pseudo]) . "\n\n"
            . t('mail.reset.body', ['app' => APP_NAME]) . "\n\n"
            . t('mail.reset.link_line') . "\n"
            . $resetUrl . "\n\n"
            . t('mail.reset.ignore') . "\n\n"
            . '— ' . APP_NAME;

        $html = renderAppMailHtml([
            'lang' => $lang,
            'title' => t('mail.reset.subject'),
            'preheader' => t('mail.reset.preheader'),
            'greeting' => t('mail.reset.hello', ['name' => $pseudo]),
            'body_html' => '<p style="margin:0 0 12px;">' . htmlspecialchars(t('mail.reset.body', ['app' => APP_NAME]), ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>'
                . '<p style="margin:0;color:#5c5345;font-size:14px;">' . htmlspecialchars(t('mail.reset.ignore'), ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>',
            'cta_url' => $resetUrl,
            'cta_label' => t('mail.reset.cta'),
        ]);

        return sendAppMail($to, $subject, $body, $html);
    });
}

/** Port 465 = SSL direct, 587 = STARTTLS. Corrige les combinaisons inversées. */
function smtpNormalizeConfig(int $port, string $secure): array
{
    $secure = strtolower(trim($secure));
    if ($secure === '' || $secure === 'none') {
        return [$port, 'none'];
    }
    if ($port === 587 && $secure === 'ssl') {
        return [$port, 'tls'];
    }
    if ($port === 465 && $secure === 'tls') {
        return [$port, 'ssl'];
    }

    return [$port, $secure];
}

/** Envoi SMTP (AUTH LOGIN, TLS ou SSL). */
function sendSmtpMail(
    string $to,
    string $subject,
    string $body,
    string $from,
    string $fromName,
    ?string $bodyHtml = null
): bool
{
    $host = trim(env('MAIL_SMTP_HOST', ''));
    $port = (int) env('MAIL_SMTP_PORT', '587');
    $user = env('MAIL_SMTP_USER', '');
    $pass = env('MAIL_SMTP_PASS', '');
    $secure = strtolower(trim(env('MAIL_SMTP_SECURE', 'tls')));

    if ($host === '') {
        setMailError('MAIL_SMTP_HOST vide.');
        return false;
    }

    [$port, $secure] = smtpNormalizeConfig($port, $secure);

    try {
        $socket = smtpConnect($host, $port, $secure);
        if ($socket === false) {
            return false;
        }

        smtpExpect($socket, [220]);
        smtpCmd($socket, 'EHLO ' . smtpEhloHost());
        smtpExpect($socket, [250]);

        if ($secure === 'tls') {
            smtpCmd($socket, 'STARTTLS');
            smtpExpect($socket, [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                setMailError('STARTTLS impossible.');
                return false;
            }
            smtpCmd($socket, 'EHLO ' . smtpEhloHost());
            smtpExpect($socket, [250]);
        }

        if ($user !== '') {
            smtpCmd($socket, 'AUTH LOGIN');
            smtpExpect($socket, [334]);
            smtpCmd($socket, base64_encode($user));
            smtpExpect($socket, [334]);
            smtpCmd($socket, base64_encode($pass));
            smtpExpect($socket, [235]);
        }

        smtpCmd($socket, 'MAIL FROM:<' . $from . '>');
        smtpExpect($socket, [250]);
        smtpCmd($socket, 'RCPT TO:<' . $to . '>');
        smtpExpect($socket, [250, 251]);
        smtpCmd($socket, 'DATA');
        smtpExpect($socket, [354]);

        $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $messageId = '<' . bin2hex(random_bytes(8)) . '@' . smtpMailDomain() . '>';
        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'Message-ID: ' . $messageId,
            'From: ' . formatMailAddress($fromName, $from),
            'To: <' . $to . '>',
            'Reply-To: ' . APP_CONTACT_EMAIL,
            'Subject: ' . $subjectEnc,
            'MIME-Version: 1.0',
        ];

        if ($bodyHtml !== null && trim($bodyHtml) !== '') {
            $boundary = 'prognoz_' . bin2hex(random_bytes(8));
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $payload = buildMultipartAlternativeBody($boundary, $body, $bodyHtml);
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';
            $payload = $body;
        }

        $message = implode("\r\n", $headers) . "\r\n\r\n" . smtpDotStuff($payload) . "\r\n.\r\n";

        fwrite($socket, $message);
        smtpExpect($socket, [250]);
        smtpCmd($socket, 'QUIT');
        fclose($socket);

        return true;
    } catch (Throwable $e) {
        if (isset($socket)) {
            @fclose($socket);
        }
        setMailError($e->getMessage());
        error_log('Prognoz SMTP: ' . $e->getMessage());
        return false;
    }
}

function smtpEhloHost(): string
{
    $host = parse_url(env('APP_URL', ''), PHP_URL_HOST);
    if (is_string($host) && $host !== '') {
        return $host;
    }
    return 'localhost';
}

function smtpMailDomain(): string
{
    $from = mailFromAddress();
    $at = strrpos($from, '@');
    if ($at !== false) {
        return substr($from, $at + 1);
    }
    $host = parse_url(env('APP_URL', ''), PHP_URL_HOST);
    return is_string($host) && $host !== '' ? $host : 'localhost';
}

/** @return resource|false */
function smtpConnect(string $host, int $port, string $secure)
{
    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $verify = !envBool('MAIL_SMTP_INSECURE', false);
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer'       => $verify,
            'verify_peer_name'  => $verify,
            'allow_self_signed' => !$verify,
            'peer_name'         => $host,
        ],
    ]);

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client(
        $remote,
        $errno,
        $errstr,
        15,
        STREAM_CLIENT_CONNECT,
        $ctx
    );

    if ($socket === false) {
        $hint = '';
        if ($secure === 'ssl' && $port === 587) {
            $hint = ' Port 587 = MAIL_SMTP_SECURE=tls. Port 465 = MAIL_SMTP_SECURE=ssl.';
        }
        setMailError("Connexion {$remote} échouée ({$errno}: {$errstr}).{$hint}");
        return false;
    }

    stream_set_timeout($socket, 15);

    return $socket;
}

/** @param resource $socket */
function smtpCmd($socket, string $cmd): void
{
    fwrite($socket, $cmd . "\r\n");
}

/** @param resource $socket @param list<int> $codes */
function smtpExpect($socket, array $codes): void
{
    $line = '';
    while (($chunk = fgets($socket, 515)) !== false) {
        $line .= $chunk;
        if (isset($chunk[3]) && $chunk[3] === ' ') {
            break;
        }
    }

    if ($line === '') {
        throw new RuntimeException('SMTP: réponse vide (timeout ?)');
    }

    $code = (int) substr($line, 0, 3);
    if (!in_array($code, $codes, true)) {
        throw new RuntimeException('SMTP ' . $code . ': ' . trim($line));
    }
}

function smtpDotStuff(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $body);
    foreach ($lines as &$line) {
        if ($line !== '' && $line[0] === '.') {
            $line = '.' . $line;
        }
    }
    unset($line);

    return implode("\r\n", $lines);
}
