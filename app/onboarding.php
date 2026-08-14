<?php
/**
 * Checklist premier jour : ami · communauté · 3 pronos.
 */
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

const ONBOARDING_COOKIE = 'prognoz_onboard_hide';
const ONBOARDING_PREDICTIONS_GOAL = 3;

/** @return array{friend:bool,community:bool,predictions:bool,prediction_count:int,done:int,total:int,complete:bool,dismissed:bool,show:bool} */
function getOnboardingProgress(PDO $pdo, int $userId): array
{
    $friendDone = countAcceptedFriends($pdo, $userId) > 0
        || countSentFriendRequests($pdo, $userId) > 0;

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM community_members cm
         INNER JOIN communities c ON c.id = cm.community_id
         WHERE cm.user_id = ? AND c.est_generale = 0'
    );
    $stmt->execute([$userId]);
    $communityDone = (int) $stmt->fetchColumn() > 0;

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM predictions WHERE user_id = ?');
    $stmt->execute([$userId]);
    $predictionCount = (int) $stmt->fetchColumn();
    $predictionsDone = $predictionCount >= ONBOARDING_PREDICTIONS_GOAL;

    $steps = [
        'friend'      => $friendDone,
        'community'   => $communityDone,
        'predictions' => $predictionsDone,
    ];
    $done = count(array_filter($steps));
    $total = count($steps);
    $complete = $done === $total;
    $dismissed = onboardingIsDismissed();

    return [
        'friend'           => $friendDone,
        'community'        => $communityDone,
        'predictions'      => $predictionsDone,
        'prediction_count' => $predictionCount,
        'done'             => $done,
        'total'            => $total,
        'complete'         => $complete,
        'dismissed'        => $dismissed,
        'show'             => !$complete && !$dismissed,
    ];
}

function countSentFriendRequests(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM friendships WHERE user_id = ? AND statut = "en_attente"'
    );
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

function onboardingIsDismissed(): bool
{
    return !empty($_COOKIE[ONBOARDING_COOKIE]);
}

function onboardingDismissCookie(): void
{
    if (headers_sent()) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    setcookie(ONBOARDING_COOKIE, '1', [
        'expires'  => time() + (60 * 86400),
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

/** Affiche la checklist si pertinente. */
function renderOnboardingChecklist(PDO $pdo, array $user): void
{
    $progress = getOnboardingProgress($pdo, (int) $user['id']);
    if (!$progress['show']) {
        return;
    }

    $steps = [
        [
            'key'  => 'friend',
            'done' => $progress['friend'],
            'href' => url('account/friends.php#add-friend'),
            'icon' => 'fa-user-plus',
            'label' => t('onboard.step_friend'),
            'hint'  => t('onboard.hint_friend'),
        ],
        [
            'key'  => 'community',
            'done' => $progress['community'],
            'href' => url('communities/index.php'),
            'icon' => 'fa-users',
            'label' => t('onboard.step_community'),
            'hint'  => t('onboard.hint_community'),
        ],
        [
            'key'  => 'predictions',
            'done' => $progress['predictions'],
            'href' => url('index.php#matches'),
            'icon' => 'fa-ticket',
            'label' => t('onboard.step_predictions', [
                'n'   => min($progress['prediction_count'], ONBOARDING_PREDICTIONS_GOAL),
                'max' => ONBOARDING_PREDICTIONS_GOAL,
            ]),
            'hint'  => t('onboard.hint_predictions'),
        ],
    ];
    ?>
    <section class="onboard-card" id="onboardCard" aria-labelledby="onboardTitle">
        <div class="onboard-head">
            <div>
                <h2 class="onboard-title" id="onboardTitle"><?= e(t('onboard.title')) ?></h2>
                <p class="onboard-lead"><?= e(t('onboard.lead', ['done' => $progress['done'], 'total' => $progress['total']])) ?></p>
            </div>
            <button type="button" class="onboard-dismiss" id="onboardDismiss" aria-label="<?= e(t('onboard.dismiss')) ?>">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <ol class="onboard-steps">
            <?php foreach ($steps as $step): ?>
            <li class="onboard-step<?= $step['done'] ? ' is-done' : '' ?>">
                <span class="onboard-check" aria-hidden="true">
                    <?php if ($step['done']): ?>
                        <i class="fa-solid fa-check"></i>
                    <?php else: ?>
                        <i class="fa-solid <?= e($step['icon']) ?>"></i>
                    <?php endif; ?>
                </span>
                <div class="onboard-step-body">
                    <span class="onboard-step-label"><?= e($step['label']) ?></span>
                    <?php if (!$step['done']): ?>
                    <span class="onboard-step-hint"><?= e($step['hint']) ?></span>
                    <a href="<?= e($step['href']) ?>" class="btn btn-primary btn-sm onboard-step-cta"><?= e(t('onboard.cta')) ?></a>
                    <?php else: ?>
                    <span class="onboard-step-done"><?= e(t('onboard.done')) ?></span>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ol>
    </section>
    <script>
    (function () {
        var btn = document.getElementById('onboardDismiss');
        var card = document.getElementById('onboardCard');
        if (!btn || !card) return;
        btn.addEventListener('click', function () {
            var maxAge = 60 * 86400;
            document.cookie = <?= json_encode(ONBOARDING_COOKIE) ?> + '=1;path=/;max-age=' + maxAge + ';SameSite=Lax';
            card.classList.add('is-hidden');
            setTimeout(function () { card.remove(); }, 280);
        });
    })();
    </script>
    <?php
}
