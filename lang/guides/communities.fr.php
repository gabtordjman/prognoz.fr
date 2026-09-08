<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

return <<<'HTML'
<p>
    {app} n’est pas un classement anonyme géant. L’idée, depuis le début&nbsp;: retrouver l’ambiance
    d’un défi entre potes — qui a vu le derby, qui ose le score exact, qui tient la série. Les
    <strong>amis</strong> et les <strong>communautés privées</strong> portent ça.
</p>

<h2>Amis</h2>
<p>
    La liste d’amis sert à retrouver vite les profils qui comptent. Tu vois leurs points, leur
    série, leur saison — de quoi lancer une vanne ou une petite rivalité. Ajouter quelqu’un reste
    volontaire&nbsp;: personne n’est forcé d’accepter.
</p>
<p>
    Chacun compose et valide ses propres pronos. Comparer les historiques a juste plus de sens
    quand on sait pour qui on joue.
</p>

<h2>Communautés privées</h2>
<p>
    Une communauté, c’est un espace fermé avec&nbsp;:
</p>
<ul>
    <li>un nom choisi par le créateur&nbsp;;</li>
    <li>des membres invités (lien d’invitation)&nbsp;;</li>
    <li>un classement saison du groupe&nbsp;;</li>
    <li>un chat pour commenter les matchs, se moquer d’un ticket raté, ou fêter une série.</li>
</ul>
<p>
    Les communautés sont <strong>privées</strong>&nbsp;: pas une vitrine publique. On y entre sur
    invitation. Les discussions restent dans le cercle.
</p>
<p>
    Le créateur (ou l’admin du groupe) gère les membres. Dans le chat comme ailleurs&nbsp;: pas de
    harcèlement, pas de contenus illicites, pas de spam. Détail dans les
    <a href="{cgu_url}">CGU</a>.
</p>

<h2>Chat et données</h2>
<p>
    Les messages sont traités comme décrit dans la
    <a href="{privacy_url}">politique de confidentialité</a>. En cas de signalement ou de contenu
    hors règles, l’éditeur peut intervenir de façon proportionnée. Le chat sert à parler sport
    entre membres, pas à n’importe quoi.
</p>
<p>
    Les notifications push (si tu les actives) peuvent prévenir d’un message ou d’un prono gagné.
    Tu peux les refuser dans les paramètres du compte et du navigateur.
</p>

<h2>Classement global vs communauté</h2>
<ul>
    <li><strong>Saison / profil global</strong> — ta progression sur tout le site, mêmes barèmes
        pour tous (voir <a href="{points_url}">Points, saisons et séries</a>).</li>
    <li><strong>Classement communauté</strong> — la compétition locale du groupe (bureau, supporters,
        famille…).</li>
</ul>
<p>
    Tu peux appartenir à plusieurs communautés. Un seul compte, toujours gratuit.
</p>

<h2>Inviter sans pression</h2>
<p>
    Un lien d’invitation fait rejoindre après connexion ou inscription. Gratuit pour les invités
    comme pour toi. Pas de parrainage payant ni de quota monétisé. Si quelqu’un veut juste regarder
    les matchs sans rejoindre de groupe, c’est possible aussi.
</p>

<h2>Quelques règles de bon sens</h2>
<ul>
    <li>Se mettre d’accord sur le ton du groupe (décontracté, compétitif, familial).</li>
    <li>Éviter de spoiler un résultat si des membres n’ont pas vu le match.</li>
    <li>Ne pas harceler quelqu’un qui perd une série.</li>
    <li>Signaler / écrire à {contact_mailto} si ça dépasse les limites.</li>
</ul>

<h2>Lien avec le reste du site</h2>
<p>
    Les communautés ne changent pas le produit&nbsp;: toujours des
    <strong>pronostics gratuits</strong>, toujours des <strong>points de jeu</strong>, toujours
    l’esprit potes. Vue d’ensemble&nbsp;:
    <a href="{about_url}">À propos de {app}</a>. Démarrage en cinq étapes&nbsp;:
    <a href="{howto_url}">Comment ça marche</a>. Questions&nbsp;:
    <a href="{faq_url}">FAQ</a>.
</p>
HTML;
