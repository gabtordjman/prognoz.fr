<?php
require __DIR__ . '/../../app/bootstrap.php';
requireAdminLogin();

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        adminFlash('error', 'Session expirée.');
        header('Location: ' . url('admin/reports.php'));
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'report_unavailable') {
            $ok = sendUnavailableDataReportMail($pdo);
            if ($ok) {
                adminFlash('success', 'Diagnostic envoyé à ' . adminNotifyEmail() . ' (liste des problèmes, rien n’est corrigé).');
            } else {
                adminFlash('error', 'Échec envoi mail : ' . (lastMailError() ?: 'inconnu'));
            }
        } elseif ($action === 'report_month') {
            $ok = sendMonthlySiteReportMail($pdo);
            if ($ok) {
                adminFlash('success', 'Rapport du mois envoyé à ' . adminNotifyEmail() . '.');
            } else {
                adminFlash('error', 'Échec envoi mail : ' . (lastMailError() ?: 'inconnu'));
            }
        }
    } catch (Throwable $e) {
        adminFlash('error', 'Erreur : ' . $e->getMessage());
    }
    header('Location: ' . url('admin/reports.php'));
    exit;
}

adminLayoutStart('Rapports e-mail', 'reports');
?>
<div class="ops-panel">
    <div class="ops-panel-head">Destinataire</div>
    <div class="ops-panel-body">
        <p class="ops-muted" style="margin-bottom:0">
            Mails admin → <span class="ops-mono"><?= e(adminNotifyEmail()) ?></span>
            (<span class="ops-mono">ADMIN_NOTIFY_EMAIL</span> dans le .env).
            Pour saisir un score : <a href="<?= e(url('admin/scores.php')) ?>">Résultats &amp; scores manuels</a>.
        </p>
    </div>
</div>

<div class="ops-panel">
    <div class="ops-panel-head">Diagnostic données indisponibles</div>
    <div class="ops-panel-body">
        <p class="ops-muted">
            Envoie la liste des problèmes par mail. Ne corrige rien et n’écrit pas aux joueurs.
        </p>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="report_unavailable">
            <button type="submit" class="ops-btn ops-btn-ghost">Envoyer le diagnostic</button>
        </form>
    </div>
</div>

<div class="ops-panel">
    <div class="ops-panel-head">Rapport du mois</div>
    <div class="ops-panel-body">
        <p class="ops-muted">
            Synthèse : matchs, points, crédits API, messages, joueurs actifs.
        </p>
        <form method="post" onsubmit="return confirm('Envoyer le rapport du mois à <?= e(adminNotifyEmail()) ?> ?');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="report_month">
            <button type="submit" class="ops-btn ops-btn-ghost">Envoyer le rapport du mois</button>
        </form>
    </div>
</div>
<?php adminLayoutEnd(); ?>
