<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

return <<<'HTML'
<p>
    {app} est un <strong>jeu social gratuit de pronostics sportifs</strong>. Depuis la première
    version, le principe n’a pas bougé&nbsp;: défier ses potes sur le foot, le basket et le tennis,
    gagner des <strong>points de jeu</strong>, comparer les classements et discuter en communauté —
    sans jamais miser d’argent, sans bookmaker, sans gain monétaire.
</p>
<p>
    Cette page explique ce qu’est le site (et ce qu’il n’est pas). Pour le mode d’emploi rapide,
    voir <a href="{howto_url}">Comment ça marche</a>.
</p>

<h2>Pas de paris d’argent</h2>
<p>
    Ici, un «&nbsp;pari&nbsp;» veut dire un <strong>prono gratuit</strong>&nbsp;: tu choisis un
    vainqueur (1 / N / 2), parfois un score exact ou un buteur, tu valides un ticket, tu attends
    le résultat. Si tu gagnes, tu marques des points. Si tu rates un marché bonus, tu ne perds
    rien sur le solde&nbsp;; un vainqueur raté remet seulement la série à zéro. Pas de carte
    bancaire pour jouer.
</p>
<p>
    Les pourcentages ou cotes affichés viennent d’une source tierce et ne servent qu’à donner une
    idée. Ce n’est pas une offre de pari d’argent. {app} n’est pas un opérateur de jeux d’argent
    et n’a pas besoin d’agrément bookmaker&nbsp;: <strong>pas de mise, pas de gain d’argent</strong>.
</p>

<h2>Trois piliers depuis la 1.0</h2>
<h3>1. Des paris gratuits</h3>
<p>
    Tu peux regarder les matchs à venir (environ {match_horizon}&nbsp;jours) sans compte. Le compte
    sert à enregistrer tes pronos, suivre l’historique, rejoindre amis et communautés, et dépenser
    des points dans la boutique cosmétique. Inscription à partir de {min_age}&nbsp;ans.
</p>
<h3>2. Les potes et les communautés</h3>
<p>
    Le classement global, c’est bien. Le vrai fun, ce sont les
    <strong>communautés privées</strong>, les invitations, le chat et la comparaison des tickets
    dans un cercle choisi — équipe, coloc, famille. Détails dans
    <a href="{communities_url}">Communautés et potes</a>.
</p>
<h3>3. Gagner des points (pas de l’argent)</h3>
<p>
    Les points mesurent tes bons pronos. Ils nourrissent le classement de saison (environ
    {season_days}&nbsp;jours), les badges, la série de 1x2, et la boutique (fonds de profil,
    pseudos animés). Ils n’ont <strong>aucune valeur monétaire</strong>, ne se convertissent pas
    en argent et ne s’achètent pas. Voir
    <a href="{points_url}">Points, saisons et séries</a>.
</p>

<h2>Ce que {app} n’est pas</h2>
<ul>
    <li>Pas un bookmaker ni un site de paris monétisés.</li>
    <li>Pas un tipster payant ni un service de «&nbsp;pronos sûrs&nbsp;».</li>
    <li>Pas un réseau social généraliste&nbsp;: on est là pour le sport et le ticket.</li>
    <li>Pas un freemium qui bloque le jeu derrière un abonnement.</li>
</ul>
<p>
    La boutique tourne uniquement avec des points gagnés en jouant. Le but, c’est le fun entre
    amis, pas de monétiser le prono lui-même.
</p>

<h2>Sports et matchs</h2>
<p>
    Selon la période&nbsp;: football, basketball, tennis. Les calendriers viennent de données
    sportives tierces. Si le résultat n’est pas fiable à temps ou si le match est ambigu, on
    préfère reporter ou annuler plutôt que d’inventer un score. Mieux vaut un match reporté
    qu’un mauvais résultat pour tout le monde.
</p>

<h2>Pour qui&nbsp;?</h2>
<p>
    Pour les fans qui veulent un prétexte léger à se retrouver&nbsp;: collègues sur la Ligue&nbsp;1,
    potes NBA, famille pendant un Grand Chelem. Pas besoin d’être un pro des cotes pour composer
    un ticket et rivaliser dans sa communauté.
</p>
<p>
    Pour démarrer&nbsp;:
</p>
<ol>
    <li><a href="{howto_url}">Comment ça marche</a> (vue d’ensemble).</li>
    <li>Les matchs sur <a href="{home_url}">l’accueil</a>, tu composes un ticket.</li>
    <li>Compte gratuit via <a href="{register_url}">l’inscription</a> pour valider.</li>
    <li>Tu invites des potes dans une communauté privée.</li>
</ol>

<h2>Transparence et contact</h2>
<p>
    Règles, données et éditeur&nbsp;:
    <a href="{cgu_url}">CGU</a>,
    <a href="{privacy_url}">confidentialité</a>,
    <a href="{mentions_url}">mentions légales</a>.
    Question pratique&nbsp;: d’abord la <a href="{faq_url}">FAQ</a>, sinon {contact_mailto}.
</p>
<p>
    Bref&nbsp;: {app}, c’est un jeu de pronos entre amis. Gratuit, communautaire, centré sur les
    points — comme depuis la version&nbsp;1.0.
</p>
HTML;
