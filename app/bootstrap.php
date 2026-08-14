<?php
/**
 * Bootstrap — chargé en tout premier par chaque page de public/.
 * Définit APP_BOOT (les fichiers de app/ refusent de s'exécuter sans
 * cette constante, même si quelqu'un devine leur URL directe).
 */
define('APP_BOOT', true);

require __DIR__ . '/env.php';
loadEnvFile(dirname(__DIR__) . '/.env');

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/browser.php';
require __DIR__ . '/i18n.php';
initI18n();
require __DIR__ . '/routes.php';
require __DIR__ . '/maintenance.php';

enforceRetroUiGate();

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$isAdminArea = str_contains($scriptName, '/admin/');

if (
    !$isAdminArea
    && !defined('APP_MAINTENANCE_PAGE')
    && appInMaintenanceMode()
    && !appMaintenanceBypass()
) {
    appSendMaintenanceResponse();
    exit;
}

require __DIR__ . '/db.php';
require __DIR__ . '/encryption.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/user_predictions.php';
require __DIR__ . '/friends.php';
require __DIR__ . '/seo.php';
require __DIR__ . '/badges.php';
require __DIR__ . '/push.php';
require __DIR__ . '/seasons.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/matches.php';
require __DIR__ . '/notifications.php';
require __DIR__ . '/communities.php';
require __DIR__ . '/chat_typing.php';
require __DIR__ . '/onboarding.php';
require __DIR__ . '/activity.php';
require __DIR__ . '/reminders.php';
require __DIR__ . '/profile.php';
require __DIR__ . '/avatars.php';
require __DIR__ . '/admin_auth.php';
require __DIR__ . '/admin_layout.php';
require __DIR__ . '/admin_reports.php';
require __DIR__ . '/voided_score_batch.php';

// Migrations légères au boot (idempotentes, no-op si déjà à jour).
try {
    $pdoBoot = getPDO();
    ensureMatchProbColumns($pdoBoot);
    ensurePredictionHistorySchema($pdoBoot);
    ensureUserLastSeenSchema($pdoBoot);
    ensureMailPrefsSchema($pdoBoot);
    ensureEncryptionSchema($pdoBoot);
    maintainSeasons($pdoBoot);
    ensurePushSubscriptionSchema($pdoBoot);
    ensureAvatarSchema($pdoBoot);
} catch (Throwable $e) {
    // Connexion / ALTER : géré ailleurs ou migration manuelle
}