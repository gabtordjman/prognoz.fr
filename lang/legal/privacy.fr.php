<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

return <<<'HTML'
<p>
    La présente politique décrit comment <strong>{app}</strong> traite les données personnelles,
    conformément au Règlement général sur la protection des données (RGPD) et à la loi Informatique
    et Libertés. Elle complète les
    <a href="{cgu_url}">conditions d’utilisation</a> et les
    <a href="{mentions_url}">mentions légales</a>.
</p>

<h2>1. Responsable du traitement</h2>
<p>
    Le responsable du traitement est l’éditeur du site {app}, identifié dans les
    <a href="{mentions_url}">mentions légales</a>.
    Pour toute question relative à vos données&nbsp;: {contact_mailto}.
</p>
<p>
    Aucun délégué à la protection des données (DPO) n’est désigné&nbsp;: ce n’est pas obligatoire
    pour un traitement de cette nature et de cette échelle. L’e-mail ci-dessus est le point de contact.
</p>

<h2>2. Données collectées</h2>
<p>
    Nous collectons les données nécessaires au fonctionnement du service, que vous fournissez ou
    qui sont produites par votre usage&nbsp;:
</p>
<ul>
    <li><strong>Compte</strong> — pseudo, surnom éventuel, adresse e-mail, mot de passe (stocké haché,
        jamais en clair), dates de changement de pseudo ou de mot de passe, langue d’interface,
        date d’acceptation de la confidentialité, option de désinscription aux e-mails non essentiels,
        statut du compte (actif / désactivé).</li>
    <li><strong>Profil</strong> — photo (fichier que vous envoyez, compressé et redimensionné),
        bio, sport favori, équipes préférées, tenue de joueur (kit), fonds et couleurs de pseudo
        obtenus dans la boutique.</li>
    <li><strong>Jeu</strong> — pronostics, points (total et saison), série, badges, solde boutique
        et historique d’échanges de points, participations aux communautés et classements.</li>
    <li><strong>Social</strong> — messages de chat (stockés chiffrés en base), noms et descriptions
        de communautés (chiffrés), relations d’amis (demandes et acceptations).</li>
    <li><strong>Notifications</strong> — si vous les activez&nbsp;: abonnement Web Push (clés techniques
        VAPID), préférences de types d’alerte.</li>
    <li><strong>Technique</strong> — cookie de session, dernière visite approximative (pour l’exploitation
        du service), journaux d’erreurs limités, fichiers temporaires de limitation de débit
        (adresse IP hachée, hors fiche compte), préférences d’affichage.</li>
</ul>
<p>
    Votre <strong>pseudo</strong> (et surnom), votre <strong>photo</strong> s’il y en a une, vos
    <strong>points / classements</strong> et certains éléments de profil sont visibles des autres
    membres selon les écrans du site (profils, chat, amis, classements).
</p>
<p>
    Sans compte, vos choix de ticket peuvent rester dans le navigateur (localStorage) jusqu’à
    connexion ou suppression manuelle. Nous ne les recevons pas tant que vous ne validez pas
    une fois connecté.
</p>
<p>
    Nous ne vous demandons pas de pièce d’identité, de date de naissance complète, de carte bancaire
    ni de données de santé. Ne publiez pas ce type d’information dans le chat ou votre bio.
</p>

<h2>3. Finalités et bases légales</h2>
<ul>
    <li><strong>Exécution du contrat</strong> (utilisation du service)&nbsp;: création du compte,
        pronostics, points, saisons, communautés, chat, amis, boutique cosmétique, e-mails
        strictement nécessaires (réinitialisation de mot de passe).</li>
    <li><strong>Intérêt légitime</strong>&nbsp;: sécurité, limitation des abus, modération,
        diagnostic technique, conservation d’un historique de jeu cohérent, suivi interne minimal
        (dernière visite, sans publicité comportementale de notre part).</li>
    <li><strong>Consentement</strong>&nbsp;: notifications push, photo de profil, cookies et scripts
        <strong>publicitaires</strong> (Google AdSense). Vous pouvez retirer ce consentement
        (paramètres du navigateur ou du site, bandeau cookies, suppression de la photo).</li>
    <li><strong>Obligation légale</strong> le cas échéant (répondre à une autorité, conserver
        ce qui est strictement requis).</li>
</ul>
<p>
    Des e-mails liés au jeu (par exemple correction d’un score qui vous concerne, ou rappel de match
    si cette option est activée côté serveur) peuvent être envoyés. Les e-mails non essentiels
    respectent l’option de désinscription de votre compte lorsqu’elle existe.
    La réinitialisation de mot de passe reste possible même en cas de désinscription, car elle
    est nécessaire au compte.
</p>

<h2>4. Chiffrement (ce que c’est, et ce que ce n’est pas)</h2>
<p>
    Les <strong>messages de chat</strong> et les <strong>noms / descriptions de communautés</strong>
    sont stockés en base chiffrés (AES-256-GCM) avec une clé conservée sur le serveur
    (hors dépôt public). Un export brut de la base ne les montre pas en clair sans cette clé.
</p>
<p>
    <strong>Ce n’est pas un chiffrement de bout en bout.</strong> L’application déchiffre ces
    contenus pour les afficher aux membres autorisés. L’éditeur peut aussi y accéder en cas de
    nécessité (voir §&nbsp;5). Ne considérez pas le chat comme un canal confidentiel vis-à-vis
    de l’éditeur.
</p>

<h2>5. Accès administrateur</h2>
<p>
    Un accès d’administration technique, distinct des comptes joueurs, permet d’opérer le service.
    Dans ce cadre, l’éditeur peut&nbsp;:
</p>
<ul>
    <li>accéder au contenu des messages après déchiffrement par l’application,
        <strong>uniquement si c’est nécessaire et proportionné</strong> (signalement, contenu
        illicite ou harcelant suspecté, obligation légale, incident de sécurité, diagnostic)&nbsp;;</li>
    <li>masquer ou supprimer des contenus&nbsp;; retirer une photo inappropriée&nbsp;;</li>
    <li>désactiver / réactiver un compte&nbsp;; aider à réinitialiser un mot de passe&nbsp;;</li>
    <li>consulter des données de compte nécessaires à l’exploitation (pseudo, e-mail, points, statut),
        sans les vendre&nbsp;;</li>
    <li>corriger des résultats, ajuster des points de jeu ou gérer les saisons.</li>
</ul>
<p>
    Il n’y a <strong>pas de lecture systématique</strong> de l’ensemble des conversations.
</p>

<h2>6. Destinataires</h2>
<p>Vos données ne sont pas vendues par {app}. Elles peuvent être traitées par&nbsp;:</p>
<ul>
    <li><strong>Hébergeur</strong> du serveur et de la base (identifié dans les
        <a href="{mentions_url}">mentions légales</a>) — stockage du site, de la base et des fichiers
        (dont photos).</li>
    <li><strong>Cloudflare, Inc.</strong> (États-Unis) — si le site est servi via ce CDN / proxy
        (cas de la production actuelle)&nbsp;: données de connexion (IP, journaux techniques).
        Politique&nbsp;: <a href="{cloudflare_privacy}" rel="noopener noreferrer">cloudflare.com/privacypolicy</a>.</li>
    <li><strong>Prestataire d’e-mail (SMTP)</strong> — envoi des messages transactionnels à votre adresse.
        Lorsque le SMTP est celui de LWS, ce traitement a lieu chez cet hébergeur établi en France.</li>
    <li><strong>The Odds API</strong> — le serveur interroge cette API pour les matchs et cotes.
        <strong>Aucune donnée de votre compte n’est transmise</strong> à ce tiers.
        Site&nbsp;: <a href="{odds_url}" rel="noopener noreferrer">the-odds-api.com</a>.</li>
    <li><strong>Services de push du navigateur / système</strong> (infrastructure Web Push de
        Google, Mozilla ou Apple, selon votre navigateur) — uniquement si vous activez les
        notifications&nbsp;; données techniques d’abonnement.</li>
    <li><strong>Google</strong> (polices, et publicité si vous acceptez)&nbsp;: voir §§&nbsp;7 et&nbsp;8.</li>
    <li><strong>cdnjs (Cloudflare)</strong> — chargement de la bibliothèque d’icônes (Font Awesome)
        sur les pages qui l’utilisent&nbsp;: votre navigateur contacte ce CDN (adresse IP).</li>
</ul>

<h2>7. Transferts hors Union européenne</h2>
<p>
    Le serveur d’application et la base sont liés à l’hébergeur indiqué dans les mentions légales.
    Certains prestataires peuvent toutefois traiter des données hors UE, notamment&nbsp;:
</p>
<ul>
    <li>Cloudflare (CDN) — adresse IP et données de connexion&nbsp;;</li>
    <li>Google (polices&nbsp;; AdSense si vous avez consenti&nbsp;; éventuellement le push Chrome)&nbsp;;</li>
    <li>d’autres éditeurs de navigateurs pour le Web Push.</li>
</ul>
<p>
    Ces transferts reposent, selon le prestataire, sur une décision d’adéquation, des clauses
    contractuelles types ou votre consentement (publicité). The Odds API est interrogée par notre
    serveur sans lui envoyer vos données de compte.
</p>
<p>
    Nous n’affirmons pas que «&nbsp;toutes les données restent dans l’UE&nbsp;», car ce ne serait
    pas exact dès lors que des CDN, polices ou publicités de sociétés établies hors UE sont utilisés.
</p>

<h2 id="cookies">8. Cookies et stockage local</h2>
<p>
    Un <strong>bandeau</strong> permet d’accepter ou de refuser les cookies et scripts
    <strong>publicitaires</strong>. Les cookies strictement nécessaires au service sont déposés
    sans consentement préalable, conformément aux lignes directrices de la CNIL.
</p>
<p><strong>Nécessaires au service</strong>&nbsp;:</p>
<ul>
    <li>Cookie de session PHP — authentification, durée d’environ {session_days} jours, renouvelée
        à l’usage. Sans lui, la connexion et l’enregistrement des pronostics ne fonctionnent pas.</li>
    <li>Cookie de langue (<code>prognoz_lang</code>) — mémorise FR ou EN, jusqu’à 13 mois.</li>
    <li>Cookie d’affichage rétro (<code>prognoz_ui</code>) — uniquement si un navigateur ancien
        déclenche le mode simplifié.</li>
    <li>Cookie d’accueil guidé (<code>prognoz_onboard_hide</code>) — masque le tutoriel si vous
        le fermez.</li>
    <li>Cookie de choix (<code>prognoz_consent</code>) — mémorise votre acceptation ou votre refus
        des cookies publicitaires, 180 jours.</li>
</ul>
<p>
    Le navigateur peut aussi stocker localement (localStorage / sessionStorage), sur <em>votre</em>
    appareil uniquement&nbsp;: brouillon de ticket, bandeau bêta déjà vu, curseurs de chat,
    préférences d’affichage. Ce n’est pas de la publicité ciblée de notre part.
</p>
<p id="publicite">
    <strong>Publicité (consentement requis)</strong> — si vous acceptez, le site charge Google AdSense
    (identifiant éditeur Google). Google peut alors déposer ses propres cookies et traiter des données
    de navigation (pages vues, identifiants publicitaires, adresse IP approximative) pour afficher
    des publicités, y compris personnalisées selon les réglages Google. Nous ne contrôlons pas les
    cookies de Google. Politiques&nbsp;:
    <a href="{google_privacy}" rel="noopener noreferrer">Confidentialité Google</a>,
    <a href="{google_ads}" rel="noopener noreferrer">Publicités Google</a>.
    Réglages&nbsp;: <a href="{ads_settings_url}" rel="noopener noreferrer">adssettings.google.com</a>.
</p>
<p>
    Refuser les cookies publicitaires n’empêche pas d’utiliser le jeu. Vous pouvez changer d’avis
    via le lien «&nbsp;Cookies&nbsp;» du pied de page. Tant que vous n’avez pas choisi, les scripts
    AdSense ne sont pas chargés.
</p>
<p>
    <strong>Polices Google Fonts</strong> — sur l’affichage moderne, le navigateur charge des polices
    depuis les serveurs de Google. Google peut recevoir votre adresse IP. Ce chargement sert à
    l’affichage, pas à vous profiler de notre part.
</p>

<h2>9. Durées de conservation</h2>
<ul>
    <li>Compte, pronostics, points, saisons, boutique — tant que le compte est actif.</li>
    <li>Messages de chat — pour l’historique des communautés (sauf masquage ou suppression).</li>
    <li>Photos de profil — tant que vous les conservez, ou jusqu’à retrait (par vous ou modération).</li>
    <li>Abonnements push — jusqu’au retrait de l’autorisation ou suppression du compte.</li>
    <li>Jetons de réinitialisation de mot de passe — valables 24&nbsp;heures, puis invalidés ou supprimés.</li>
    <li>Session — expire après inactivité ou à la déconnexion (voir durée du cookie ci-dessus).</li>
    <li>Fichiers de limitation de débit — quelques heures, puis écrasés.</li>
    <li>Journaux techniques — durée limitée, le temps du diagnostic.</li>
</ul>
<p>
    La suppression du compte depuis <a href="{settings_url}">Paramètres</a> efface le compte et les
    données liées en base de production (pronostics, messages, abonnements, etc., via les relations
    de la base). Une communauté dont vous étiez créateur peut subsister sans votre nom comme créateur.
    Des copies résiduelles dans des sauvegardes techniques peuvent exister un temps limité, puis
    être écrasées. Ce n’est pas une restauration du compte pour vous.
</p>
<p>
    Vous pouvez aussi écrire à {contact_mailto}.
</p>

<h2>10. Vos droits</h2>
<p>Conformément au RGPD, vous disposez notamment des droits suivants&nbsp;:</p>
<ul>
    <li>accès, rectification et effacement&nbsp;;</li>
    <li>rectification du pseudo, du mot de passe ou de la photo depuis Paramètres
        (sous réserve, pour pseudo et mot de passe, du délai de {cooldown} jours entre deux changements)&nbsp;;</li>
    <li>limitation ou opposition, dans les cas prévus par le texte&nbsp;;</li>
    <li>portabilité des données que vous avez fournies&nbsp;;</li>
    <li>retrait du consentement lorsque le traitement en dépend (push, photo, publicité)&nbsp;;</li>
    <li>consignes relatives au sort de vos données après votre décès (art.&nbsp;85 de la loi Informatique et Libertés).</li>
</ul>
<p>
    Pour exercer ces droits&nbsp;: {contact_mailto}.
    Merci de préciser votre pseudo afin que nous puissions traiter la demande.
    Vous pouvez introduire une réclamation auprès de la CNIL
    (<a href="{cnil_url}" rel="noopener noreferrer">www.cnil.fr</a>).
</p>
<p>
    Le délai de {cooldown} jours entre deux changements de pseudo ou de mot de passe est une mesure
    de sécurité du jeu. Il ne fait pas obstacle à une rectification ou un effacement demandés
    au titre du RGPD (y compris la suppression du compte, possible à tout moment).
</p>

<h2>11. Mineurs</h2>
<p>
    Le service est réservé aux personnes d’au moins {min_age} ans (voir les CGU).
    {min_age} ans est un seuil choisi pour le chat et les communautés, plus élevé que l’âge de
    15 ans retenu en France pour le consentement numérique dans de nombreux cas.
    Nous n’avons pas de dispositif de consentement parental&nbsp;: en dessous de {min_age} ans,
    le compte n’est pas autorisé.
</p>

<h2>12. Sécurité</h2>
<p>
    Mesures raisonnables&nbsp;: mots de passe hachés, chiffrement serveur des messages, accès admin
    restreint, clés API côté serveur, cookies de session HttpOnly. Aucune transmission sur Internet
    n’est toutefois totalement exempte de risque.
</p>

<h2>13. Évolution de cette politique</h2>
<p>
    Cette page est mise à jour lorsque le traitement des données change (nouvelle fonction, nouveau
    prestataire, etc.). La date en haut de page fait foi. Une mention «&nbsp;bêta&nbsp;» sur le site,
    le cas échéant, n’autorise pas à traiter des données non décrites ici.
</p>
HTML;
