<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

return <<<'HTML'
<p>
    Sur {app}, les <strong>points</strong> sont la monnaie du jeu — et uniquement du jeu. Ils
    récompensent tes bons pronos, alimentent les classements et débloquent des cosmétiques.
    Ils ne s’achètent pas, ne se vendent pas et ne se convertissent jamais en argent. Ici&nbsp;:
    marchés, série, saisons, et ce qui se passe quand un résultat manque.
</p>

<h2>Les marchés et les points gagnés</h2>
<p>
    Chaque prono validé est évalué après le match. Les barèmes de base sont&nbsp;:
</p>
<ul>
    <li><strong>+{pts_1x2}&nbsp;pt</strong> — bon vainqueur (1 / N / 2 selon le sport). Ce marché
        peut être multiplié par la <em>série</em> ou par un <em>événement</em> temporaire du site.</li>
    <li><strong>+{pts_score}&nbsp;pts</strong> — score exact (football uniquement). Marché bonus.</li>
    <li><strong>+{pts_buteur}&nbsp;pts</strong> — bon buteur (football). Marché bonus.</li>
    <li><strong>+{pts_fav_win}&nbsp;pts</strong> — marché «&nbsp;mon équipe gagne / perd&nbsp;»
        (base {pts_fav_base}, multipliée en cas de succès). Jusqu’à un club plus {fav_max}
        sélections nationales dans Mon espace.</li>
</ul>
<p>
    Important&nbsp;: <strong>aucun marché ne retire de points</strong> en cas d’échec. Score exact,
    buteur et équipe préférée sont des bonus purs (vous gagnez ou vous marquez 0). Un vainqueur
    (1x2) raté ne retire rien non plus&nbsp;: seule la série repart à zéro. Le total de points et
    le score de saison ne descendent jamais sous zéro.
</p>

<h2>Série de bons 1x2</h2>
<p>
    La série compte les vainqueurs corrects d’affilée. Elle multiplie uniquement les gains du
    marché 1x2 (pas les bonus score / buteur / fav), selon des paliers&nbsp;:
</p>
<ul>
    <li>dès 2 bons d’affilée&nbsp;: ×1,5</li>
    <li>dès 3&nbsp;: ×2</li>
    <li>dès 5&nbsp;: ×2,5</li>
</ul>
<p>
    Un événement du site peut ajouter un multiplicateur temporaire, cumulable avec la série sur
    le 1x2. Perdre un 1x2 remet la série à zéro sans toucher au solde de points.
</p>

<h2>Saison, total profil et podium</h2>
<p>
    Deux compteurs coexistent&nbsp;:
</p>
<ul>
    <li><strong>Points saison</strong> — classement de la saison en cours (environ
        {season_days}&nbsp;jours). Ils repartent à zéro à chaque nouvelle saison.</li>
    <li><strong>Total profil</strong> — historique cumulé depuis la création du compte. Il ne
        repart pas à zéro.</li>
</ul>
<p>
    En fin de saison, un podium peut attribuer des bonus&nbsp;:
    +{podium_1} / +{podium_2} / +{podium_3}&nbsp;pts pour les 1<sup>re</sup>, 2<sup>e</sup> et
    3<sup>e</sup> places, avec badges associés. Les points de saison se verrouillent ensuite pour
    la boutique cosmétique&nbsp;: fonds de profil, pseudos animés, etc. Les objets rares restent
    accessibles en jouant mieux, pas en payant.
</p>

<h2>Ticket, validation et indépendance des paris</h2>
<p>
    Vous pouvez préparer plusieurs choix dans le ticket (bandeau bas sur mobile) avant de valider.
    Une fois validés, les pronostics sont enregistrés jusqu’au résultat. Chaque marché est traité
    séparément&nbsp;: rater le score exact n’annule pas un vainqueur correct sur le même match, et
    inversement.
</p>
<p>
    Inutile d’être connecté pour commencer à composer&nbsp;; la connexion sert à enregistrer.
    Après validation, un prono ne se modifie plus — comme dans un vrai défi entre potes.
</p>

<h2>Résultats, reports et annulations</h2>
<p>
    Les scores arrivent via une synchronisation avec des données sportives externes. Si le score
    n’est pas fiable à temps (quota, match introuvable, doublon d’adversaires, etc.), {app}
    préfère <strong>reporter ou annuler</strong> plutôt que d’afficher un résultat inventé. Les
    pronostics concernés sont alors tranchés de façon neutre pour tous les joueurs concernés.
</p>
<p>
    Dans Mon espace, l’historique montre les tickets déjà tranchés. Un bandeau peut signaler de
    nouveaux résultats. Si un match vous semble mal classé, vérifiez d’abord s’il est marqué
    reporté / annulé, puis consultez la <a href="{faq_url}">FAQ</a> ou contactez
    {contact_mailto}.
</p>

<h2>Équité et fair-play</h2>
<p>
    Les barèmes sont les mêmes pour tout le monde. Les communautés peuvent avoir leur propre
    classement saison, mais les règles de points du site restent communes. Tricher (multi-comptes
    abusifs, harcèlement, spam) est interdit par les
    <a href="{cgu_url}">CGU</a> et peut entraîner des sanctions.
</p>
<p>
    Pour une vue courte des barèmes, ouvre aussi l’aide points (icône de points) une fois
    connecté, ou relis <a href="{howto_url}">Comment ça marche</a>. Pour le projet en bref&nbsp;:
    <a href="{about_url}">À propos de {app}</a>.
</p>
HTML;
