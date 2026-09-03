<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

return <<<'HTML'
<p>
    Les présentes conditions d’utilisation (ci-après «&nbsp;CGU&nbsp;») régissent l’accès au site
    <strong>{app}</strong> et son usage. En créant un compte ou en utilisant le service, vous les acceptez.
    Si vous n’êtes pas d’accord, n’utilisez pas le site.
</p>

<h2>1. Objet et nature du service</h2>
<p>
    {app} est un <strong>jeu social gratuit de pronostics sportifs</strong> (football, basket, tennis, selon
    les matchs proposés). Il permet de pronostiquer des rencontres, de cumuler des <strong>points de jeu</strong>,
    de participer à des saisons et classements, d’acheter des éléments visuels avec ces points, et d’échanger
    avec d’autres joueurs (communautés, amis, chat).
</p>
<p>
    <strong>Ce n’est pas un site de paris d’argent.</strong> Aucune mise financière n’est demandée, aucun gain
    d’argent n’est versé, les points n’ont aucune valeur monétaire et ne peuvent pas être convertis en argent
    ni cédés contre de l’argent. Les cotes ou pourcentages affichés viennent d’une source tierce et n’ont
    qu’une valeur indicative&nbsp;: ils ne constituent pas une offre de pari d’argent.
</p>
<p>
    {app} n’est pas un opérateur de jeux d’argent, n’est pas agréé par l’Autorité nationale des jeux (ANJ)
    et n’a pas à l’être pour ce service gratuit sans mise. Le site n’est pas un bookmaker.
</p>

<h2>2. Éditeur et mentions</h2>
<p>
    L’identité de l’éditeur, les coordonnées de contact et celles de l’hébergeur figurent dans les
    <a href="{mentions_url}">mentions légales</a>.
    Le traitement des données personnelles est décrit dans la
    <a href="{privacy_url}">politique de confidentialité</a>.
</p>

<h2>3. Accès, gratuité et âge</h2>
<p>
    L’accès au service est <strong>gratuit</strong>. Consulter les matchs ne nécessite pas de compte&nbsp;;
    enregistrer des pronostics, rejoindre des communautés, utiliser le chat, les amis, la boutique ou les
    notifications nécessite un compte.
</p>
<p>
    Le service est réservé aux personnes âgées d’au moins <strong>{min_age}&nbsp;ans</strong>.
    En créant un compte, vous déclarez avoir atteint cet âge. Nous ne collectons pas sciemment de données
    concernant des enfants de moins de {min_age}&nbsp;ans. Si un compte a été ouvert en infraction avec cette
    règle, écrivez à {contact_mailto} pour le faire supprimer.
</p>
<p>
    Une connexion Internet et un navigateur à jour sont nécessaires. Certaines fonctions (notifications push,
    photo de profil) dépendent de votre appareil et de vos autorisations.
</p>

<h2>4. Compte utilisateur</h2>
<ul>
    <li>Vous devez fournir un pseudo et une adresse e-mail exacts et dont vous avez l’usage.</li>
    <li>Vous êtes responsable de la confidentialité de votre mot de passe et de l’activité de votre compte.</li>
    <li>Le pseudo et le mot de passe peuvent être changés depuis
        <a href="{settings_url}">Paramètres</a>, avec un délai minimum de {cooldown} jours entre deux changements
        (sauf réinitialisation du mot de passe par e-mail si vous n’êtes pas connecté).</li>
    <li>Vous pouvez ajouter une photo de profil, un surnom, une bio, un sport favori et des équipes préférées.
        Vous restez responsable du caractère licite et respectueux de ces éléments.</li>
    <li>Un compte par personne est demandé. Les comptes créés pour fausser un classement, spammer ou contourner
        une sanction peuvent être suspendus.</li>
    <li>La fonction «&nbsp;Amis&nbsp;» permet d’ajouter d’autres joueurs par pseudo, avec acceptation mutuelle.</li>
</ul>

<h2>5. Pronostics, points, saisons et boutique</h2>
<ul>
    <li>Les points, badges, séries, multiplicateurs et récompenses de saison n’ont <strong>aucune valeur
        monétaire</strong>.</li>
    <li>Les pronostics sont verrouillés au coup d’envoi du match (selon l’heure indiquée sur le site).</li>
    <li>L’historique de vos pronostics est consultable dans «&nbsp;Mon espace&nbsp;».</li>
    <li>Des classements de saison peuvent être remis à zéro périodiquement. Des badges ou éléments visuels
        (sans valeur d’argent) peuvent être attribués.</li>
    <li>La boutique permet d’échanger des points de jeu déjà verrouillés contre des cosmétiques
        (fonds de profil, couleurs de pseudo, etc.). Ces objets ne s’achètent pas avec de l’argent réel
        et ne se revendent pas.</li>
    <li>Des événements temporaires (multiplicateur de points, habillage) peuvent être organisés par l’éditeur.
        Ils ne changent rien au caractère gratuit et non monétaire du jeu.</li>
    <li>Les résultats, cotes et compositions viennent de sources tierces. Des retards, absences ou erreurs
        peuvent arriver. L’éditeur peut corriger un score, annuler un match (pronostics concernés à 0&nbsp;point)
        ou ajuster des points pour rétablir l’équité du jeu, sans que cela ouvre droit à une compensation
        financière (il n’y a pas d’enjeu d’argent).</li>
</ul>
<p>
    Le détail du barème est expliqué dans l’aide du site et la page
    <a href="{howto_url}">Comment ça marche</a>. L’éditeur peut faire évoluer les règles de jeu
    (barème, saisons, marchés) pour le bon fonctionnement du service. Ces évolutions n’ont pas d’effet
    rétroactif sur de l’argent, puisqu’il n’y en a pas.
</p>

<h2>6. Communautés et contenus</h2>
<p>
    Vous restez responsable des messages, photos, bios, noms de communautés et autres contenus que vous publiez.
    Sont notamment interdits&nbsp;: contenus illicites, haineux, harcelants, pornographiques, diffamatoires,
    ou portant atteinte à la vie privée d’autrui&nbsp;; apologie de crimes&nbsp;; spam&nbsp;; usurpation
    d’identité&nbsp;; tentative de piratage, de contournement des mesures de sécurité ou de détournement du
    service&nbsp;; publicité non autorisée.
</p>
<p>
    Les communautés privées sont destinées à un usage entre personnes qui se connaissent ou s’invitent
    volontairement. Le respect d’autrui y est exigé comme sur le reste du site.
    Vous ne devez pas y publier de données personnelles d’autrui sans base légale.
</p>
<p>
    Les contenus que vous publiez restent les vôtres. Vous accordez à l’éditeur une licence non exclusive,
    gratuite, mondiale, pour les héberger, les afficher et les modérer dans le cadre du service, pendant
    la durée nécessaire à celui-ci.
</p>

<h2>7. Modération</h2>
<p>
    Pour faire fonctionner le service, assurer la sécurité des utilisateurs et faire respecter les présentes CGU,
    l’éditeur (via un accès d’administration distinct des comptes joueurs) peut notamment&nbsp;:
</p>
<ul>
    <li><strong>Consulter des messages</strong> de chat de communauté — y compris ceux stockés chiffrés en base —
        <strong>uniquement en cas de nécessité</strong> (signalement, soupçon d’abus, obligation légale, sécurité,
        diagnostic technique lié à un incident). Ce n’est pas une lecture systématique de toutes les conversations.</li>
    <li><strong>Masquer ou supprimer</strong> un message, une photo, une bio ou tout contenu contraire aux CGU.</li>
    <li><strong>Suspendre (désactiver) ou rétablir</strong> un compte, ou réinitialiser un mot de passe
        à la demande de l’utilisateur ou en cas d’incident de sécurité.</li>
    <li><strong>Corriger des scores</strong>, annuler un match, attribuer ou retirer des points de jeu,
        clôturer ou planifier une saison.</li>
    <li>Prendre toute mesure raisonnable pour protéger le service (limitation d’accès, purge technique, etc.).</li>
</ul>
<p>
    Une suspension n’ouvre pas droit à indemnité. En cas de désaccord, vous pouvez écrire à {contact_mailto}.
</p>

<h2>8. Propriété intellectuelle</h2>
<p>
    Le site, son identité visuelle, ses textes originaux et son code restent la propriété de l’éditeur
    ou de ses concédants. Toute reproduction non autorisée est interdite, hors exceptions légales
    (copie privée, courte citation, etc.).
</p>
<p>
    Les noms d’équipes, compétitions, joueurs et marques sportives appartiennent à leurs titulaires.
    Leur mention sur le site est uniquement informative, pour identifier les matchs. Elle n’implique
    aucune affiliation, sponsoring ou partenariat.
</p>

<h2>9. Données personnelles</h2>
<p>
    Voir la <a href="{privacy_url}">politique de confidentialité</a>.
    Vous pouvez supprimer votre compte à tout moment depuis
    <a href="{settings_url}">Paramètres</a> (action définitive sur la base de production).
</p>

<h2>10. Publicité</h2>
<p>
    Des publicités de tiers (notamment Google AdSense) peuvent s’afficher. Les scripts publicitaires
    ne sont chargés <strong>qu’après votre accord</strong> via le bandeau cookies. Vous pouvez refuser
    aussi facilement qu’accepter, et changer d’avis ensuite. Refuser les cookies publicitaires n’empêche
    pas d’utiliser le jeu.
</p>

<h2>11. Disponibilité et limitation de responsabilité</h2>
<p>
    Le service est fourni gratuitement, «&nbsp;en l’état&nbsp;», y compris lorsqu’une mention «&nbsp;bêta&nbsp;»
    est affichée. L’éditeur s’efforce d’en assurer le bon fonctionnement mais ne garantit pas une disponibilité
    continue, ni l’absence d’erreurs (scores, synchronisation, notifications, affichage).
</p>
<p>
    L’éditeur n’est pas responsable des contenus publiés par les utilisateurs, des pannes de réseaux ou
    prestataires tiers, ni des dommages indirects. Comme le jeu est gratuit et sans enjeu d’argent,
    aucun préjudice financier lié à des points, classements ou objets de boutique ne peut être réclamé.
</p>
<p>
    Ces limitations <strong>ne s’appliquent pas</strong> en cas de faute lourde ou dolosive, d’atteinte à
    la personne, ni aux droits auxquels la loi interdit de renoncer (notamment les droits des consommateurs).
</p>

<h2>12. Modification des CGU</h2>
<p>
    L’éditeur peut modifier les présentes CGU pour refléter une évolution du service ou de la loi.
    La date indiquée en haut de cette page fait foi. En cas de changement important, un moyen raisonnable
    d’information pourra être utilisé (mention sur le site, par exemple). L’usage du service après
    l’entrée en vigueur des nouvelles CGU vaut acceptation. Si vous refusez une modification, vous pouvez
    supprimer votre compte.
</p>

<h2>13. Durée, résiliation</h2>
<p>
    Les CGU s’appliquent pendant toute la durée d’utilisation du service. Vous pouvez cesser d’utiliser
    le site et supprimer votre compte à tout moment. L’éditeur peut suspendre ou clôturer un compte
    en cas de manquement grave ou répété, ou cesser le service (en s’efforçant d’en informer les
    utilisateurs si c’est raisonnablement possible).
</p>

<h2>14. Droit applicable</h2>
<p>
    Les présentes CGU sont régies par le <strong>droit français</strong>.
    En cas de litige, et à défaut d’accord amiable, les tribunaux français compétents seront saisis,
    sous réserve des règles impératives de protection des consommateurs (vous pouvez notamment agir
    devant le tribunal du lieu où vous résidez si vous êtes consommateur).
</p>
<p>
    La version française de ces CGU prévaut en cas de divergence avec une traduction.
</p>

<h2>15. Contact</h2>
<p>Questions relatives aux CGU&nbsp;: {contact_mailto}</p>
HTML;
