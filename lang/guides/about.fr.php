<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

return <<<'HTML'
<p>
    {app} est un <strong>jeu social gratuit de pronostics sportifs</strong>. L’idée est simple depuis
    la première version&nbsp;: défier ses potes sur le foot, le basket et le tennis, gagner des
    <strong>points de jeu</strong>, comparer les classements et discuter en communauté — sans jamais
    miser d’argent, sans bookmaker, sans gain monétaire.
</p>
<p>
    Ce guide explique pourquoi le site existe, ce qu’il est (et ce qu’il n’est pas), et comment
    l’esprit communautaire reste au centre du produit. Si vous cherchez uniquement le mode d’emploi
    rapide, commencez par
    <a href="{howto_url}">Comment ça marche</a>.
</p>

<h2>Une alternative aux paris d’argent</h2>
<p>
    Beaucoup de sites mélangent divertissement et mise financière. {app} fait volontairement
    l’inverse. Les «&nbsp;paris&nbsp;» ici sont des <strong>pronostics gratuits</strong>&nbsp;: vous
    choisissez un vainqueur (1 / N / 2), parfois un score exact ou un buteur, vous validez un ticket,
    puis vous attendez le résultat du match. En cas de succès vous gagnez des points&nbsp;; en cas
    d’échec, les marchés bonus ne vous retirent rien, et un vainqueur raté remet seulement la série
    à zéro. Aucune carte bancaire n’est demandée pour jouer.
</p>
<p>
    Les pourcentages ou cotes éventuellement affichés viennent d’une source tierce et n’ont qu’une
    valeur indicative. Ils ne constituent pas une offre de pari d’argent et ne transforment pas
    {app} en opérateur de jeux d’argent. Le service n’est pas soumis à l’agrément d’un régulateur
    de paris sportifs pour cette raison précise&nbsp;: <strong>pas de mise, pas de gain d’argent</strong>.
</p>

<h2>Trois piliers inchangés depuis la 1.0</h2>
<h3>1. Des paris gratuits</h3>
<p>
    Consulter les matchs à venir (horizon d’environ {match_horizon}&nbsp;jours) est possible sans
    compte. Créer un compte sert à enregistrer ses pronostics, suivre son historique, rejoindre
    des amis et des communautés, et dépenser des points dans la boutique cosmétique. L’inscription
    est réservée aux personnes d’au moins {min_age}&nbsp;ans.
</p>
<h3>2. L’esprit communautaire</h3>
<p>
    Le classement «&nbsp;global&nbsp;» n’est qu’une partie de l’expérience. Le cœur du jeu, ce sont
    les <strong>communautés privées</strong>, les invitations entre potes, le chat et la comparaison
    des tickets dans un cercle choisi. Vous n’êtes pas obligé de jouer contre tout Internet&nbsp;:
    vous pouvez créer un groupe fermé pour votre équipe, votre coloc ou votre famille.
    Détails dans le guide
    <a href="{communities_url}">Communautés et potes</a>.
</p>
<h3>3. Gagner des points (pas de l’argent)</h3>
<p>
    Les points mesurent la réussite sportive dans le jeu. Ils alimentent le classement de saison
    (environ {season_days}&nbsp;jours), les badges, la série de bons 1x2, et la boutique visuelle
    (fonds de profil, pseudos animés). Ils n’ont <strong>aucune valeur monétaire</strong>, ne se
    convertissent pas en argent et ne s’achètent pas. Voir
    <a href="{points_url}">Points, saisons et séries</a>.
</p>

<h2>Ce que {app} n’est pas</h2>
<ul>
    <li>Ce n’est pas un bookmaker ni un site de paris en ligne monétisés.</li>
    <li>Ce n’est pas un tipster payant ni un service de «&nbsp;pronos sûrs&nbsp;».</li>
    <li>Ce n’est pas un réseau social généraliste&nbsp;: le fil conducteur reste le sport et le ticket.</li>
    <li>Ce n’est pas un produit «&nbsp;freemium&nbsp;» qui bride le jeu derrière un abonnement.</li>
</ul>
<p>
    La boutique utilise uniquement des points gagnés en jouant. L’objectif reste le fun entre amis,
    pas la monétisation du pronostic lui-même.
</p>

<h2>Sports et matchs proposés</h2>
<p>
    Selon la période, {app} propose des rencontres de football, de basketball et de tennis. Les
    calendriers sont synchronisés à partir de données sportives tierces. Un match peut être reporté
    ou annulé côté jeu si le résultat n’est pas disponible à temps ou si l’identité de la rencontre
    est ambiguë&nbsp;: dans ces cas, les pronostics concernés sont tranchés de façon transparente
    (souvent annulés / reportés) plutôt que d’attribuer un score inventé. L’équité entre joueurs
    prime sur la vitesse d’affichage d’un résultat douteux.
</p>

<h2>Pour qui est fait le site&nbsp;?</h2>
<p>
    {app} s’adresse aux amateurs de sport qui veulent un prétexte léger pour se retrouver&nbsp;:
    collègues qui suivent la Ligue&nbsp;1, amis fans de NBA, famille pendant un Grand Chelem.
    Le ton est volontairement accessible&nbsp;: pas besoin d’être un expert des cotes pour
    composer un ticket et rivaliser dans sa communauté.
</p>
<p>
    Si vous découvrez le site, le parcours recommandé est&nbsp;:
</p>
<ol>
    <li>Lire <a href="{howto_url}">Comment ça marche</a> (vue d’ensemble en étapes).</li>
    <li>Parcourir les matchs sur <a href="{home_url}">l’accueil</a> et composer un ticket.</li>
    <li>Créer un compte gratuit via <a href="{register_url}">l’inscription</a> pour valider.</li>
    <li>Inviter des potes dans une communauté privée.</li>
</ol>

<h2>Transparence et contact</h2>
<p>
    Les règles d’usage, la confidentialité et l’identité de l’éditeur sont décrites dans les
    <a href="{cgu_url}">CGU</a>, la
    <a href="{privacy_url}">politique de confidentialité</a> et les
    <a href="{mentions_url}">mentions légales</a>.
    Pour une question pratique (compte, résultat, communauté), consultez d’abord la
    <a href="{faq_url}">FAQ</a>, puis écrivez à {contact_mailto}.
</p>
<p>
    En résumé&nbsp;: {app} est un terrain de jeu sportif entre amis. Gratuit, communautaire,
    centré sur les points — comme depuis la version&nbsp;1.0.
</p>
HTML;
