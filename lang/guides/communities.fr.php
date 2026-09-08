<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

return <<<'HTML'
<p>
    {app} n’est pas conçu comme un classement anonyme géant. Depuis le début, l’ambition est de
    retrouver l’ambiance d’un défi entre potes&nbsp;: qui a le mieux vu le derby, qui ose le score
    exact, qui tient la série. Les <strong>amis</strong> et les <strong>communautés privées</strong>
    portent cet esprit.
</p>

<h2>Amis</h2>
<p>
    La liste d’amis sert à retrouver rapidement les profils qui comptent pour vous. Vous voyez
    leurs points, leur série, leur saison — de quoi lancer une conversation ou une petite rivalité
    saine. Ajouter quelqu’un reste volontaire&nbsp;: personne n’est forcé d’accepter une demande.
</p>
<p>
    Les amis ne remplacent pas le ticket&nbsp;: chacun compose et valide ses propres pronostics.
    En revanche, comparer les historiques et les classements devient beaucoup plus parlant quand
    on sait pour qui on joue.
</p>

<h2>Communautés privées</h2>
<p>
    Une communauté est un espace fermé avec&nbsp;:
</p>
<ul>
    <li>un nom choisi par le créateur&nbsp;;</li>
    <li>des membres invités (lien d’invitation)&nbsp;;</li>
    <li>un classement saison propre au groupe&nbsp;;</li>
    <li>un chat pour commenter les matchs, se moquer gentiment d’un ticket raté, ou fêter une série.</li>
</ul>
<p>
    Les communautés sont <strong>privées par conception</strong>&nbsp;: elles ne sont pas une vitrine
    publique pour le référencement. L’accès se fait sur invitation. C’est volontaire&nbsp;: le contenu
    des discussions appartient au cercle, pas à un fil ouvert.
</p>
<p>
    Le créateur (ou l’admin de la communauté, selon les droits) peut gérer les membres. Les règles
    de respect du site s’appliquent aussi dans le chat&nbsp;: pas de harcèlement, pas de contenus
    illicites, pas de spam. Le détail figure dans les <a href="{cgu_url}">CGU</a>.
</p>

<h2>Chat et confidentialité</h2>
<p>
    Les messages de communauté sont traités avec des mesures de protection décrites dans la
    <a href="{privacy_url}">politique de confidentialité</a>. L’éditeur peut intervenir en cas de
    signalement ou de contenu contraire aux règles, de façon proportionnée. Le chat n’est pas un
    espace «&nbsp;tout est permis&nbsp;»&nbsp;: c’est un outil pour parler sport entre membres.
</p>
<p>
    Les notifications push (si vous les activez) peuvent vous prévenir d’un message ou d’un prono
    gagné. Vous restez libre de les refuser dans les paramètres du compte et du navigateur.
</p>

<h2>Classement global vs classement de communauté</h2>
<p>
    Deux lectures utiles&nbsp;:
</p>
<ul>
    <li><strong>Saison / profil global</strong> — votre progression sur tout le site, avec les
        mêmes barèmes de points pour tous (voir
        <a href="{points_url}">Points, saisons et séries</a>).</li>
    <li><strong>Classement communauté</strong> — la compétition locale entre membres du groupe.
        Idéal pour un challenge de bureau, une bande de supporters ou une famille.</li>
</ul>
<p>
    Vous pouvez appartenir à plusieurs communautés. Chaque groupe a sa dynamique&nbsp;; le compte
    reste unique et gratuit.
</p>

<h2>Inviter sans pression</h2>
<p>
    Un lien d’invitation permet à une personne de rejoindre la communauté après connexion ou
    inscription. {app} reste gratuit pour les invités comme pour vous. Il n’y a pas de «&nbsp;parrainage
    payant&nbsp;» ni de quota d’invitations monétisé. Si quelqu’un préfère seulement parcourir les
    matchs sans rejoindre de groupe, c’est possible aussi.
</p>

<h2>Bonnes pratiques communautaires</h2>
<ul>
    <li>Clarifier dès le départ le ton du groupe (décontracté, compétitif, familial).</li>
    <li>Éviter de spoiler un résultat si des membres n’ont pas encore vu le match — simple courtoisie.</li>
    <li>Ne pas harceler quelqu’un qui perd une série&nbsp;: le jeu doit rester un plaisir.</li>
    <li>Utiliser le signalement / contact ({contact_mailto}) si un comportement dépasse les limites.</li>
</ul>

<h2>Lien avec le reste du site</h2>
<p>
    Les communautés ne changent pas la nature du produit&nbsp;: toujours des
    <strong>pronostics gratuits</strong>, toujours des <strong>points de jeu</strong>, toujours
    l’esprit potes. Pour comprendre le projet global, lisez
    <a href="{about_url}">À propos de {app}</a>. Pour démarrer en cinq étapes,
    <a href="{howto_url}">Comment ça marche</a>. Questions fréquentes&nbsp;:
    <a href="{faq_url}">FAQ</a>.
</p>
HTML;
