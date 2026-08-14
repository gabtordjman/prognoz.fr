<?php
require __DIR__ . '/../../app/bootstrap.php';

$pdo  = getPDO();
$user = currentUser($pdo);
$contact = APP_CONTACT_EMAIL;
?>
<!DOCTYPE html>
<html lang="<?= e(htmlLang()) ?>"<?= function_exists('htmlUiClassAttr') ? htmlUiClassAttr() : '' ?>>
<head>
    <?php layoutHead(t('legal.cgu.h1'), false, seoPage('terms')); ?>
</head>
<body>
<?php layoutTopbar($user); ?>

<main class="app-main app-main-wide legal-page">
    <h1 class="page-title"><?= e(t('legal.cgu.h1')) ?></h1>
    <p class="page-sub"><?= e(t('legal.updated', ['date' => date('d/m/Y')])) ?></p>
    <?php if (currentLang() === 'en'): ?>
    <p class="page-sub"><?= e(t('legal.note')) ?></p>
    <article class="panel legal-panel"><div class="panel-body legal-body"><p><?= e(t('legal.cgu.en_body', ['email' => APP_CONTACT_EMAIL])) ?></p></div></article>
    <?php else: ?>

    <article class="panel legal-panel">
        <div class="panel-body legal-body">
            <p>
                En utilisant <strong><?= e(APP_NAME) ?></strong>, vous acceptez les présentes conditions.
                Le service est un jeu gratuit de pronostics sportifs entre amis — <strong>aucun argent réel</strong>,
                aucune mise, aucun gain financier.
            </p>

            <h2>1. Objet</h2>
            <p>
                <?= e(APP_NAME) ?> permet de pronostiquer sur des matchs sportifs, de cumuler des points,
                de participer à des saisons / classements, et de comparer ses résultats au sein de
                communautés (dont une communauté générale) ou via le système d’amis.
            </p>

            <h2>2. Compte utilisateur</h2>
            <ul>
                <li>Vous devez fournir des informations exactes (pseudo, e-mail).</li>
                <li>Vous êtes responsable de la confidentialité de votre mot de passe.</li>
                <li>Le pseudo et le mot de passe peuvent être modifiés depuis
                    <a href="<?= e(url('account/settings.php')) ?>">Paramètres</a>,
                    avec un délai minimum de <?= (int) PROFILE_CHANGE_COOLDOWN_DAYS ?> jours entre deux changements
                    (sauf réinitialisation par e-mail si vous êtes déconnecté).</li>
                <li>Vous pouvez ajouter une <strong>photo de profil</strong> (image stockée sur nos serveurs,
                    redimensionnée). Vous restez responsable du caractère licite et respectueux de cette image.</li>
                <li>Un compte par personne ; comportement respectueux exigé dans les chats et communautés.</li>
                <li>La fonction «&nbsp;Amis&nbsp;» permet d’ajouter d’autres joueurs par pseudo, avec acceptation mutuelle.</li>
            </ul>

            <h2>3. Pronostics et points</h2>
            <ul>
                <li>Les points n’ont aucune valeur monétaire.</li>
                <li>Les pronostics sont verrouillés au coup d’envoi du match.</li>
                <li>L’historique de vos pronostics et résultats est conservé dans «&nbsp;Mon espace&nbsp;».</li>
                <li>Des classements de saison peuvent être remis à zéro périodiquement ; des badges / récompenses
                    symboliques (sans valeur monétaire) peuvent être attribués.</li>
                <li>Des notifications (à l’écran et, si vous l’activez, notifications push navigateur) peuvent
                    vous informer de résultats, messages ou événements de saison.</li>
                <li>Les résultats proviennent de sources tierces ; des retards ou erreurs peuvent survenir.
                    L’éditeur peut corriger un score, annuler un match (pronos à 0&nbsp;pt) ou attribuer / retirer
                    des points pour rétablir l’équité du jeu.</li>
            </ul>

            <h2>4. Communautés et contenus</h2>
            <p>
                Vous restez responsable des messages, photos et autres contenus que vous publiez.
                Sont notamment interdits&nbsp;: contenus illicites, haineux, harcelants, pornographiques,
                diffamatoires, ou portant atteinte à la vie privée d’autrui ; spam ; usurpation d’identité ;
                tentative de détournement du service.
            </p>
            <p>
                Les communautés privées sont destinées à un usage entre personnes qui se connaissent ou
                s’invitent volontairement. Le respect d’autrui y est exigé comme sur le reste du site.
            </p>

            <h2>5. Modération et pouvoirs de l’éditeur</h2>
            <p>
                Pour faire fonctionner le service, assurer la sécurité des utilisateurs et faire respecter
                les présentes CGU, l’éditeur (via un accès d’administration réservé) peut notamment&nbsp;:
            </p>
            <ul>
                <li><strong>Consulter les messages</strong> des chats de communauté — y compris ceux stockés
                    sous forme chiffrée — <strong>uniquement en cas de nécessité</strong> (signalement,
                    soupçon d’abus, obligation légale, sécurité, débogage technique lié à un incident).</li>
                <li><strong>Masquer ou supprimer</strong> un message, une photo de profil ou tout contenu contraire aux CGU.</li>
                <li><strong>Suspendre (désactiver) ou rétablir</strong> un compte, ou réinitialiser un mot de passe
                    à la demande de l’utilisateur ou en cas d’incident de sécurité.</li>
                <li><strong>Corriger des scores</strong>, annuler un match, attribuer ou retirer des points
                    (bonus / malus de jeu, rattrapage technique).</li>
                <li><strong>Clôturer ou planifier</strong> une saison de classement.</li>
                <li>Prendre toute mesure raisonnable pour protéger le service (limitation d’accès, purge de données techniques, etc.).</li>
            </ul>
            <p>
                Ces actions ne constituent pas une surveillance généralisée ni une lecture systématique
                des conversations. Elles sont exercées de façon ciblée et proportionnée.
            </p>

            <h2>6. Propriété intellectuelle</h2>
            <p>
                Le site, son identité visuelle et son code restent la propriété de l’éditeur.
                Les marques et compétitions sportives citées appartiennent à leurs titulaires respectifs.
                Les contenus que vous publiez restent les vôtres ; vous accordez à l’éditeur une licence
                non exclusive pour les héberger et les afficher dans le cadre du service.
            </p>

            <h2>7. Données personnelles</h2>
            <p>
                Voir la <a href="<?= e(url('legal/confidentialite.php')) ?>">politique de confidentialité</a>.
                Vous pouvez supprimer votre compte à tout moment depuis
                <a href="<?= e(url('account/settings.php')) ?>">Paramètres</a> (action définitive).
            </p>

            <h2>8. Limitation de responsabilité</h2>
            <p>
                Le service est fourni «&nbsp;en l’état&nbsp;», notamment en phase bêta. L’éditeur ne garantit pas
                une disponibilité continue ni l’absence d’erreurs (résultats, sync, notifications).
                Aucun préjudice financier ne peut découler de l’usage du jeu, celui-ci étant gratuit et sans enjeu monétaire.
            </p>

            <h2>9. Contact</h2>
            <p>Questions : <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a></p>
        </div>
    </article>
    <?php endif; ?>
</main>

<?php layoutFooter(); ?>
</body>
</html>
