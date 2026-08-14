<?php
require __DIR__ . '/../../app/bootstrap.php';

$pdo  = getPDO();
$user = currentUser($pdo);
$contact = APP_CONTACT_EMAIL;
?>
<!DOCTYPE html>
<html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
<head>
    <?php layoutHead(t('legal.privacy.h1'), false, seoPage('privacy')); ?>
</head>
<body>
<?php layoutTopbar($user); ?>

<main class="app-main app-main-wide legal-page">
    <h1 class="page-title"><?= e(t('legal.privacy.h1')) ?></h1>
    <p class="page-sub"><?= e(t('legal.updated', ['date' => date('d/m/Y')])) ?></p>
    <?php if (currentLang() === 'en'): ?>
    <p class="page-sub"><?= e(t('legal.note')) ?></p>
    <article class="panel legal-panel"><div class="panel-body legal-body"><p><?= e(t('legal.privacy.en_body', ['email' => APP_CONTACT_EMAIL])) ?></p></div></article>
    <?php else: ?>

    <article class="panel legal-panel">
        <div class="panel-body legal-body">
            <p>
                La présente politique décrit comment <strong><?= e(APP_NAME) ?></strong> (ci-après «&nbsp;le site&nbsp;»)
                traite les données personnelles des utilisateurs, conformément au Règlement général sur la protection
                des données (RGPD).
            </p>

            <h2>1. Responsable du traitement</h2>
            <p>
                Le responsable du traitement est l’éditeur du site <?= e(APP_NAME) ?>.
                Pour toute question relative à vos données&nbsp;:
                <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>.
            </p>

            <h2>2. Données collectées</h2>
            <p>Nous collectons uniquement les données nécessaires au fonctionnement du service&nbsp;:</p>
            <ul>
                <li><strong>Compte</strong> — pseudo, adresse e-mail, mot de passe (stocké de façon hachée),
                    dates de changement de pseudo ou de mot de passe le cas échéant, statut du compte (actif / désactivé).</li>
                <li><strong>Photo de profil</strong> — fichier image que vous uploadez volontairement
                    (stocké sur nos serveurs après compression / redimensionnement).</li>
                <li><strong>Activité</strong> — pronostics, points (total et saison), série en cours,
                    badges / récompenses de saison, participations aux communautés.</li>
                <li><strong>Messages</strong> — contenus publiés dans les chats de communauté (stockés chiffrés).</li>
                <li><strong>Amis</strong> — pseudos des joueurs avec lesquels vous êtes en relation (demandes et acceptations).</li>
                <li><strong>Notifications push</strong> — si vous les activez&nbsp;: abonnement navigateur
                    (clés techniques Web Push / VAPID), utilisé pour vous alerter hors onglet ouvert.</li>
                <li><strong>Technique</strong> — identifiant de session (cookie PHP), journaux techniques
                    limités (erreurs, sync, quota API) sans contenu de chat en clair.</li>
            </ul>
            <p>
                Votre <strong>pseudo</strong>, votre <strong>photo de profil</strong> (si présente) et vos
                <strong>points / classements</strong> sont visibles des autres membres selon les écrans du site
                (profils, chat, amis, classements).
            </p>
            <p>
                Lors de la composition d’un ticket sans compte, vos choix peuvent être enregistrés localement
                dans le navigateur (localStorage) jusqu’à votre connexion ou suppression manuelle.
            </p>

            <h2>3. Finalités</h2>
            <ul>
                <li>Création et gestion de votre compte.</li>
                <li>Enregistrement de vos pronostics, calcul des points et gestion des saisons.</li>
                <li>Fonctionnement des communautés, du chat, des amis et des photos de profil.</li>
                <li>Envoi d’e-mails transactionnels (réinitialisation de mot de passe).</li>
                <li>Notifications (écran et, avec votre accord, push navigateur).</li>
                <li>Sécurité, prévention des abus et modération (voir §&nbsp;5).</li>
                <li>Amélioration et maintenance du service (version bêta).</li>
            </ul>

            <h2>4. Chiffrement des contenus sensibles</h2>
            <p>
                Les <strong>messages de chat</strong> et les <strong>noms / descriptions de communautés</strong>
                sont stockés en base sous forme <strong>chiffrée</strong> (AES-256-GCM).
                Ils ne sont pas consultables «&nbsp;en clair&nbsp;» dans un export brut de la base sans la clé serveur.
            </p>
            <p>
                L’application déchiffre ces contenus pour les afficher aux <strong>membres autorisés</strong>
                de la communauté. La clé de chiffrement est conservée sur le serveur (configuration sécurisée,
                hors dépôt public).
            </p>

            <h2>5. Accès administrateur et modération</h2>
            <p>
                Un accès d’administration technique, distinct des comptes joueurs, permet à l’éditeur
                d’opérer le service. Dans ce cadre, l’éditeur peut&nbsp;:
            </p>
            <ul>
                <li><strong>Accéder au contenu des messages</strong> de chat (après déchiffrement par l’application)
                    <strong>uniquement en cas de nécessité absolue ou proportionnée</strong>&nbsp;:
                    signalement, soupçon de contenu illicite ou harcelant, obligation légale,
                    incident de sécurité, ou diagnostic technique lié à un dysfonctionnement.</li>
                <li>Masquer ou supprimer des messages ; retirer une photo de profil inappropriée.</li>
                <li>Désactiver / réactiver un compte ; réinitialiser un mot de passe (aide utilisateur ou sécurité).</li>
                <li>Consulter des données de compte nécessaires à l’exploitation (pseudo, e-mail, points, statut),
                    sans «&nbsp;vendre&nbsp;» ni diffuser ces données à des tiers à des fins commerciales.</li>
                <li>Corriger des résultats de match, annuler une rencontre, ajuster des points ou gérer les saisons.</li>
            </ul>
            <p>
                Il n’y a <strong>pas de lecture systématique</strong> de l’ensemble des conversations.
                L’accès admin est protégé (identifiants, URL / accès restreints) et destiné à un usage opérationnel.
            </p>

            <h2>6. Base légale</h2>
            <p>
                Le traitement repose sur l’exécution du contrat (utilisation du service),
                sur l’intérêt légitime (sécurité, lutte contre les abus, exploitation technique du jeu),
                et, le cas échéant, sur votre consentement pour les fonctionnalités optionnelles
                (notifications push, photo de profil).
            </p>

            <h2>7. Destinataires et sous-traitants</h2>
            <p>Vos données ne sont pas vendues. Elles peuvent être traitées par&nbsp;:</p>
            <ul>
                <li><strong>Hébergeur</strong> — stockage de la base de données et des fichiers du site (dont photos).</li>
                <li><strong>Fournisseur d’e-mail (SMTP)</strong> — envoi des messages transactionnels
                    (réinitialisation de mot de passe) à votre adresse.</li>
                <li><strong>The Odds API</strong> — le serveur du site interroge cette API pour récupérer
                    matchs et cotes. Aucune donnée personnelle de votre compte n’est transmise à ce tiers.</li>
                <li><strong>Services de push navigateur</strong> (ex. infrastructure Web Push du navigateur / OS)
                    — uniquement si vous activez les notifications ; données techniques d’abonnement.</li>
            </ul>

            <h2>8. Durée de conservation</h2>
            <ul>
                <li>Compte, pronostics, points, saisons — conservés tant que le compte est actif.</li>
                <li>Messages de chat — conservés pour l’historique des communautés (sauf masquage / suppression).</li>
                <li>Photos de profil — tant que vous les conservez ou jusqu’à suppression (par vous ou modération).</li>
                <li>Abonnements push — jusqu’au retrait de l’autorisation ou suppression du compte.</li>
                <li>Jetons de réinitialisation de mot de passe — valides 24&nbsp;h, puis invalidés ou supprimés.</li>
                <li>Session — expire après une période d’inactivité ou à la déconnexion.</li>
            </ul>
            <p>
                Vous pouvez supprimer votre compte à tout moment depuis
                <a href="<?= e(url('account/settings.php')) ?>">Paramètres</a>, ou nous écrire à
                <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>.
            </p>

            <h2>9. Vos droits</h2>
            <p>Conformément au RGPD, vous disposez des droits suivants&nbsp;:</p>
            <ul>
                <li>Accès, rectification et effacement de vos données.</li>
                <li>Rectification du pseudo, du mot de passe ou de la photo depuis Paramètres
                    (sous réserve, pour pseudo / MDP, du délai de <?= (int) PROFILE_CHANGE_COOLDOWN_DAYS ?> jours entre deux changements).</li>
                <li>Limitation ou opposition au traitement.</li>
                <li>Portabilité des données que vous avez fournies.</li>
                <li>Retrait du consentement lorsque le traitement en dépend (ex. push, photo).</li>
            </ul>
            <p>
                Pour exercer ces droits&nbsp;:
                <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>.
                Vous pouvez également introduire une réclamation auprès de la CNIL (www.cnil.fr).
            </p>

            <h2>10. Cookies et stockage local</h2>
            <p>
                Le site utilise un <strong>cookie de session</strong> strictement nécessaire à l’authentification.
                Sans ce cookie, la connexion et l’enregistrement des paris ne fonctionnent pas.
            </p>
            <p>
                En navigation invitée, le ticket de pronostics peut être stocké dans le
                <strong>localStorage</strong> de votre navigateur jusqu’à validation ou suppression.
            </p>
            <p>
                Des préférences locales peuvent aussi y être enregistrées&nbsp;: bandeau bêta déjà vu,
                brouillon de ticket, préférences d’affichage, curseurs de notification chat, etc.
                (uniquement sur votre appareil, non destinés à de la publicité ciblée).
            </p>

            <h2>11. Sécurité</h2>
            <p>
                Nous mettons en œuvre des mesures raisonnables (mots de passe hashés, chiffrement des messages,
                accès admin restreint, clés API côté serveur). Aucune transmission sur Internet n’est toutefois
                totalement exempte de risque.
            </p>

            <h2>12. Version bêta</h2>
            <p>
                <?= e(APP_NAME) ?> est actuellement proposé en version bêta. Des évolutions fonctionnelles ou
                techniques peuvent modifier le traitement des données ; cette page sera mise à jour en conséquence.
            </p>
        </div>
    </article>
    <?php endif; ?>
</main>

<?php layoutFooter(); ?>
</body>
</html>
