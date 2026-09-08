<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

return <<<'HTML'
<p>
    Réponses aux questions les plus fréquentes sur {app}. Cette FAQ complète
    <a href="{howto_url}">Comment ça marche</a> et les guides
    <a href="{about_url}">À propos</a>,
    <a href="{points_url}">Points</a> et
    <a href="{communities_url}">Communautés</a>.
    Si votre cas n’y figure pas, écrivez à {contact_mailto}.
</p>

<h2>Compte et gratuité</h2>
<h3>Est-ce vraiment gratuit&nbsp;?</h3>
<p>
    Oui. Jouer, valider des pronostics, rejoindre des amis et des communautés, cumuler des points
    et utiliser la boutique cosmétique se fait <strong>sans payer</strong>. Les points n’ont pas de
    valeur monétaire. {app} n’est pas un bookmaker.
</p>
<h3>Faut-il un compte pour tout faire&nbsp;?</h3>
<p>
    Non pour parcourir les matchs et commencer un ticket. Oui pour valider des pronostics, garder
    un historique, les amis, les communautés, le chat, la boutique et certaines notifications.
</p>
<h3>Quel âge minimum&nbsp;?</h3>
<p>
    {min_age}&nbsp;ans déclarés à l’inscription. Le détail légal est dans les
    <a href="{cgu_url}">CGU</a> et la <a href="{privacy_url}">confidentialité</a>.
</p>

<h2>Pronostics et tickets</h2>
<h3>Puis-je modifier un prono après validation&nbsp;?</h3>
<p>
    Non. Une fois validé, le choix reste verrouillé jusqu’au résultat — comme un défi posé à vos
    potes. Vous pouvez retirer des sélections du ticket <em>avant</em> validation.
</p>
<h3>Les marchés sont-ils liés entre eux&nbsp;?</h3>
<p>
    Non. Sur un même match, un vainqueur correct et un score exact raté (ou l’inverse) sont
    traités séparément. Voir le guide <a href="{points_url}">Points</a>.
</p>
<h3>Que signifient 1, N et 2&nbsp;?</h3>
<p>
    Ce sont les issues classiques&nbsp;: équipe / joueur à domicile (1), match nul quand le sport
    le permet (N), équipe / joueur extérieur (2). Au tennis ou basket selon le format, le «&nbsp;nul&nbsp;»
    peut être absent.
</p>

<h2>Points, séries et saisons</h2>
<h3>Je me suis trompé&nbsp;: vais-je perdre des points&nbsp;?</h3>
<p>
    Non sur le solde. Les marchés bonus (score exact, buteur, équipe préférée) rapportent des points
    ou 0. Un 1x2 raté remet seulement la série à zéro. Aucun total ne passe sous 0.
</p>
<h3>À quoi sert la saison&nbsp;?</h3>
<p>
    Environ {season_days}&nbsp;jours de classement «&nbsp;frais&nbsp;», avec podium et badges possibles
    (+{podium_1} / +{podium_2} / +{podium_3}). Le total profil, lui, conserve l’historique long terme.
</p>
<h3>La boutique est-elle payante&nbsp;?</h3>
<p>
    Non en argent. Vous dépensez des points gagnés en jouant pour des éléments visuels (fonds,
    pseudos animés…). Rien de tout cela n’améliore artificiellement vos résultats sportifs.
</p>

<h2>Résultats et matchs</h2>
<h3>Pourquoi un match est «&nbsp;reporté&nbsp;» alors qu’il s’est joué à la télé&nbsp;?</h3>
<p>
    Parce que le score fiable n’est pas toujours disponible immédiatement côté synchronisation
    (données externes, délai, ambiguïté d’identité). {app} préfère reporter / annuler plutôt que
    d’attribuer un score douteux. Les joueurs concernés sont traités de la même façon.
</p>
<h3>Qui fournit les matchs et les cotes&nbsp;?</h3>
<p>
    Des données sportives tierces. Les cotes ou pourcentages affichés sont indicatifs et ne
    constituent pas une offre de pari d’argent.
</p>
<h3>Combien de matchs vois-je&nbsp;?</h3>
<p>
    Un horizon d’environ {match_horizon}&nbsp;jours est affiché, réparti par sport. La liste évolue
    avec le calendrier réel.
</p>

<h2>Communautés</h2>
<h3>Mes discussions sont-elles publiques&nbsp;?</h3>
<p>
    Non. Les communautés sont privées, sur invitation. Voir
    <a href="{communities_url}">Communautés et potes</a>.
</p>
<h3>Puis-je créer plusieurs groupes&nbsp;?</h3>
<p>
    Oui. Un même compte gratuit peut rejoindre ou créer plusieurs communautés.
</p>

<h2>Données, pubs et contact</h2>
<h3>Où lire ce que vous faites de mes données&nbsp;?</h3>
<p>
    Dans la <a href="{privacy_url}">politique de confidentialité</a>. Les cookies et la publicité
    (si activée après consentement) y sont décrits. Vous pouvez revoir vos choix cookies depuis le
    pied de page.
</p>
<h3>Comment contacter l’équipe&nbsp;?</h3>
<p>
    {contact_mailto}. Pour l’identité de l’éditeur et de l’hébergeur&nbsp;:
    <a href="{mentions_url}">mentions légales</a>.
</p>
<p>
    Vous voulez l’esprit du projet en une page&nbsp;?
    <a href="{about_url}">À propos de {app}</a>. Tous les guides&nbsp;:
    <a href="{guides_url}">sommaire</a>.
</p>
HTML;
