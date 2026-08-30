<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/** Créateur ou modérateur d’une communauté privée. */
function userCanManageCommunity(PDO $pdo, int $communityId, int $userId): bool
{
    $stmt = $pdo->prepare(
        'SELECT c.est_generale, c.createur_id, cm.role
         FROM communities c
         INNER JOIN community_members cm ON cm.community_id = c.id AND cm.user_id = ?
         WHERE c.id = ?'
    );
    $stmt->execute([$userId, $communityId]);
    $row = $stmt->fetch();

    if (!$row || (int) $row['est_generale'] === 1) {
        return false;
    }

    if ($row['role'] === 'moderateur') {
        return true;
    }

    return (int) ($row['createur_id'] ?? 0) === $userId;
}

/** Membres d’une communauté (pseudo en clair). */
function fetchCommunityMembers(PDO $pdo, int $communityId): array
{
    $stmt = $pdo->prepare(
        'SELECT u.id, u.pseudo, u.avatar_url, u.equipped_name, cm.role, cm.joined_at
         FROM community_members cm
         INNER JOIN users u ON u.id = cm.user_id
         WHERE cm.community_id = ?
         ORDER BY FIELD(cm.role, "moderateur", "membre"), u.pseudo ASC'
    );
    $stmt->execute([$communityId]);

    return $stmt->fetchAll();
}

/** Texte prêt à coller / WhatsApp pour une invitation. */
function communityInviteShareText(string $communityName, string $inviteUrl): string
{
    $name = trim($communityName);
    if ($name === '') {
        $name = APP_NAME;
    }

    return t('com.share_text', ['name' => $name, 'url' => $inviteUrl]);
}

/**
 * Boîte invitation : copier le message + WhatsApp.
 */
function renderCommunityInviteShare(string $inviteUrl, string $communityName): void
{
    $shareText = communityInviteShareText($communityName, $inviteUrl);
    $waUrl = 'https://wa.me/?text=' . rawurlencode($shareText);
    $uid = 'invite_' . substr(md5($inviteUrl), 0, 8);
    ?>
    <div class="panel panel-spaced">
        <div class="panel-head"><?= e(t('com.invite')) ?></div>
        <div class="panel-body">
            <p class="invite-intro"><?= e(t('com.share_lead')) ?></p>
            <div class="invite-box">
                <div class="invite-url-wrap">
                    <span class="invite-label"><?= e(t('com.invite_label')) ?></span>
                    <code class="invite-url" id="<?= e($uid) ?>_link"><?= e($inviteUrl) ?></code>
                </div>
            </div>
            <div class="invite-actions">
                <button type="button" class="btn btn-primary btn-sm invite-share-btn" id="<?= e($uid) ?>_copy"
                        data-share-text="<?= e($shareText) ?>">
                    <i class="fa-regular fa-copy" aria-hidden="true"></i> <?= e(t('com.share_copy')) ?>
                </button>
                <a class="btn btn-accent btn-sm" href="<?= e($waUrl) ?>" target="_blank" rel="noopener noreferrer">
                    <i class="fa-brands fa-whatsapp" aria-hidden="true"></i> <?= e(t('com.share_whatsapp')) ?>
                </a>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var btn = document.getElementById(<?= json_encode($uid . '_copy') ?>);
        if (!btn) return;
        var copyLabel = <?= json_encode(t('com.share_copy'), JSON_UNESCAPED_UNICODE) ?>;
        var copiedLabel = <?= json_encode(t('common.copied'), JSON_UNESCAPED_UNICODE) ?>;
        var copyError = <?= json_encode(t('com.copy_error'), JSON_UNESCAPED_UNICODE) ?>;
        function copyText(text) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(text);
            }
            return new Promise(function (resolve, reject) {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                try {
                    document.execCommand('copy') ? resolve() : reject();
                } catch (e) {
                    reject(e);
                }
                document.body.removeChild(ta);
            });
        }
        btn.addEventListener('click', function () {
            var text = btn.getAttribute('data-share-text') || '';
            copyText(text).then(function () {
                btn.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i> ' + copiedLabel;
                btn.classList.add('is-copied');
                setTimeout(function () {
                    btn.innerHTML = '<i class="fa-regular fa-copy" aria-hidden="true"></i> ' + copyLabel;
                    btn.classList.remove('is-copied');
                }, 2000);
            }).catch(function () {
                btn.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i> ' + copyError;
                setTimeout(function () {
                    btn.innerHTML = '<i class="fa-regular fa-copy" aria-hidden="true"></i> ' + copyLabel;
                }, 2000);
            });
        });
    })();
    </script>
    <?php
}
