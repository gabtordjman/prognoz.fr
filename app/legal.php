<?php
/**
 * Pages légales (CGU, confidentialité, mentions) + consentement pub.
 */
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

const LEGAL_CONSENT_COOKIE = 'prognoz_consent';
const LEGAL_CONSENT_DAYS = 180;

/**
 * Identité éditeur (LCEN). Champs vides = on n’invente rien : contact seulement.
 *
 * @return array{
 *   kind:string,name:string,address:string,phone:string,siret:string,
 *   rcs:string,vat:string,capital:string,director:string,email:string,url:string
 * }
 */
function legalPublisher(): array
{
    $name = trim(env('LEGAL_PUBLISHER_NAME', ''));
    $director = trim(env('LEGAL_PUBLICATION_DIRECTOR', ''));
    if ($director === '') {
        $director = $name;
    }

    return [
        'kind'     => legalPublisherKind(),
        'name'     => $name,
        'address'  => trim(env('LEGAL_PUBLISHER_ADDRESS', '')),
        'phone'    => trim(env('LEGAL_PUBLISHER_PHONE', '')),
        'siret'    => trim(env('LEGAL_SIRET', '')),
        'rcs'      => trim(env('LEGAL_RCS', '')),
        'vat'      => trim(env('LEGAL_VAT', '')),
        'capital'  => trim(env('LEGAL_CAPITAL', '')),
        'director' => $director,
        'email'    => APP_CONTACT_EMAIL,
        'url'      => rtrim(env('APP_URL', 'https://www.prognoz.fr'), '/'),
    ];
}

function legalPublisherKind(): string
{
    $kind = strtolower(trim(env('LEGAL_PUBLISHER_KIND', 'individual')));

    return $kind === 'company' ? 'company' : 'individual';
}

/**
 * Hébergeur d’origine (LCEN). Cloudflare n’est pas l’hébergeur.
 *
 * @return array{name:string,address:string,phone:string,website:string}
 */
function legalHost(): array
{
    return [
        'name'    => trim(env('LEGAL_HOST_NAME', '')),
        'address' => trim(env('LEGAL_HOST_ADDRESS', '')),
        'phone'   => trim(env('LEGAL_HOST_PHONE', '')),
        'website' => trim(env('LEGAL_HOST_WEBSITE', '')),
    ];
}

function legalUpdatedLabel(): string
{
    return t('legal.updated', ['date' => t('legal.date')]);
}

/** Placeholders communs des documents légaux. */
function legalPlaceholders(): array
{
    $email = APP_CONTACT_EMAIL;

    return [
        'app'              => e(APP_NAME),
        'email'            => e($email),
        'min_age'          => (string) APP_MIN_AGE,
        'cooldown'         => (string) (int) PROFILE_CHANGE_COOLDOWN_DAYS,
        'session_days'     => (string) max(1, (int) floor(SESSION_LIFETIME / 86400)),
        'privacy_url'      => e(url('legal/confidentialite.php')),
        'cgu_url'          => e(url('legal/cgu.php')),
        'mentions_url'     => e(url('legal/mentions-legales.php')),
        'settings_url'     => e(url('account/settings.php')),
        'howto_url'        => e(url('legal/comment-ca-marche.php')),
        'contact_mailto'   => '<a href="mailto:' . e($email) . '">' . e($email) . '</a>',
        'cnil_url'         => 'https://www.cnil.fr',
        'ads_settings_url' => 'https://adssettings.google.com',
        'google_privacy'   => 'https://policies.google.com/privacy',
        'google_ads'       => 'https://policies.google.com/technologies/ads',
        'odds_url'         => 'https://the-odds-api.com',
        'cloudflare_privacy' => 'https://www.cloudflare.com/privacypolicy/',
    ];
}

function legalLoadDocument(string $doc): string
{
    $doc = preg_replace('/[^a-z0-9\-]/', '', strtolower($doc)) ?? '';
    $lang = currentLang() === 'en' ? 'en' : 'fr';
    $path = dirname(__DIR__) . '/lang/legal/' . $doc . '.' . $lang . '.php';
    if (!is_file($path)) {
        $path = dirname(__DIR__) . '/lang/legal/' . $doc . '.fr.php';
    }
    if (!is_file($path)) {
        return '';
    }
    $html = require $path;

    return is_string($html) ? $html : '';
}

function legalDocumentHtml(string $doc): string
{
    $html = legalLoadDocument($doc);
    foreach (legalPlaceholders() as $key => $value) {
        $html = str_replace('{' . $key . '}', $value, $html);
    }

    return strip_tags($html, '<p><h2><h3><ul><ol><li><strong><em><a><br><span><code>');
}

function legalCrossNav(string $current): void
{
    $items = [
        'cgu'      => ['url' => url('legal/cgu.php'), 'label' => t('common.cgu')],
        'privacy'  => ['url' => url('legal/confidentialite.php'), 'label' => t('common.privacy')],
        'mentions' => ['url' => url('legal/mentions-legales.php'), 'label' => t('common.mentions')],
    ];
    ?>
    <nav class="legal-cross" aria-label="<?= e(t('common.legal_nav')) ?>">
        <?php foreach ($items as $key => $item): ?>
            <?php if ($key === $current): ?>
                <span class="legal-cross-current" aria-current="page"><?= e($item['label']) ?></span>
            <?php else: ?>
                <a href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <?php
}

function adsenseClient(): string
{
    return defined('ADSENSE_CLIENT') ? trim((string) ADSENSE_CLIENT) : '';
}

function adsenseConfigured(): bool
{
    if (adsenseClient() === '') {
        return false;
    }
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    return !str_contains($script, '/admin/');
}

/** null = pas encore choisi, true = pubs acceptées, false = refus. */
function consentAdsChoice(): ?bool
{
    $raw = strtolower(trim((string) ($_COOKIE[LEGAL_CONSENT_COOKIE] ?? '')));
    if ($raw === 'ads' || $raw === '1') {
        return true;
    }
    if ($raw === 'essential' || $raw === '0') {
        return false;
    }

    return null;
}

function consentAdsAllowed(): bool
{
    return adsenseConfigured() && consentAdsChoice() === true;
}

function layoutAdsenseLoader(): void
{
    if (!adsenseConfigured()) {
        return;
    }
    $client = adsenseClient();
    $src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . rawurlencode($client);
    ?>
    <script>
    window.prognozLoadAds = function () {
        if (window.__prognozAdsLoaded) return;
        window.__prognozAdsLoaded = true;
        var s = document.createElement('script');
        s.async = true;
        s.src = <?= json_encode($src, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        s.setAttribute('crossorigin', 'anonymous');
        document.head.appendChild(s);
    };
    </script>
    <?php if (consentAdsAllowed()): ?>
    <script>window.prognozLoadAds && window.prognozLoadAds();</script>
    <?php endif;
}

function layoutConsentBanner(): void
{
    if (!adsenseConfigured()) {
        return;
    }
    $choice = consentAdsChoice();
    $hidden = $choice !== null;
    $privacy = url('legal/confidentialite.php') . '#cookies';
    ?>
    <div class="cookie-banner" id="cookieBanner" role="region" aria-label="<?= e(t('cookies.aria')) ?>"<?= $hidden ? ' hidden' : '' ?>>
        <div class="cookie-banner-inner">
            <p class="cookie-banner-text">
                <?= t('cookies.text', [
                    'privacy' => '<a href="' . e($privacy) . '">' . e(t('cookies.privacy_link')) . '</a>',
                ]) ?>
            </p>
            <div class="cookie-banner-actions">
                <button type="button" class="btn btn-primary btn-sm" id="cookieAccept"><?= e(t('cookies.accept')) ?></button>
                <button type="button" class="btn btn-ghost btn-sm" id="cookieRefuse"><?= e(t('cookies.refuse')) ?></button>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var COOKIE = <?= json_encode(LEGAL_CONSENT_COOKIE) ?>;
        var DAYS = <?= (int) LEGAL_CONSENT_DAYS ?>;
        var banner = document.getElementById('cookieBanner');
        function setConsent(ads) {
            var v = ads ? 'ads' : 'essential';
            var max = DAYS * 24 * 3600;
            var secure = location.protocol === 'https:' ? ';Secure' : '';
            document.cookie = COOKIE + '=' + v + ';Max-Age=' + max + ';Path=/;SameSite=Lax' + secure;
            if (banner) banner.hidden = true;
            if (ads && window.prognozLoadAds) window.prognozLoadAds();
        }
        var acceptBtn = document.getElementById('cookieAccept');
        var refuseBtn = document.getElementById('cookieRefuse');
        if (acceptBtn) acceptBtn.addEventListener('click', function () { setConsent(true); });
        if (refuseBtn) refuseBtn.addEventListener('click', function () { setConsent(false); });
        document.querySelectorAll('[data-cookie-settings]').forEach(function (el) {
            el.addEventListener('click', function (ev) {
                ev.preventDefault();
                if (banner) {
                    banner.hidden = false;
                    banner.scrollIntoView({ block: 'nearest' });
                }
            });
        });
        window.prognozSetConsent = setConsent;
    })();
    </script>
    <?php
}
