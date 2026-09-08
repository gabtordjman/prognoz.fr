<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

return <<<'HTML'
<p>
    On {app}, <strong>points</strong> are the in-game currency — and only that. They reward good
    picks, feed rankings and unlock cosmetics. They cannot be bought, sold or cashed out. This
    guide covers markets, streaks, seasons and what happens when a result is unavailable.
</p>

<h2>Markets and points earned</h2>
<p>
    Each confirmed pick is settled after the match. Base awards are:
</p>
<ul>
    <li><strong>+{pts_1x2}&nbsp;pt</strong> — correct winner (1 / D / 2 depending on the sport).
        This market can be multiplied by a <em>streak</em> or a temporary site <em>event</em>.</li>
    <li><strong>+{pts_score}&nbsp;pts</strong> — exact score (football only). Bonus market.</li>
    <li><strong>+{pts_buteur}&nbsp;pts</strong> — correct scorer (football). Bonus market.</li>
    <li><strong>+{pts_fav_win}&nbsp;pts</strong> — “my team wins / loses” market (base {pts_fav_base},
        multiplied on a hit). Up to one club plus {fav_max} national selections in My space.</li>
</ul>
<p>
    Important: <strong>no market removes points</strong> on a miss. Exact score, scorer and favourite
    team are pure bonuses (you win or score 0). A wrong winner (1x2) also removes nothing — only the
    streak resets. Profile total and season score never go below zero.
</p>

<h2>1x2 streak</h2>
<p>
    The streak counts consecutive correct winners. It multiplies only 1x2 gains (not score / scorer /
    fav bonuses), with tiers:
</p>
<ul>
    <li>from 2 in a row: ×1.5</li>
    <li>from 3: ×2</li>
    <li>from 5: ×2.5</li>
</ul>
<p>
    A site event may add a temporary multiplier, stackable with the streak on 1x2. Losing a 1x2
    resets the streak without touching your point balance.
</p>

<h2>Season, profile total and podium</h2>
<p>
    Two counters coexist:
</p>
<ul>
    <li><strong>Season points</strong> — current season ranking (about {season_days} days). They
        reset every new season.</li>
    <li><strong>Profile total</strong> — cumulative history since account creation. It does not reset.</li>
</ul>
<p>
    At season end, a podium may award bonuses: +{podium_1} / +{podium_2} / +{podium_3} pts for
    1st, 2nd and 3rd, with related badges. Season points then lock for the cosmetic shop: profile
    backgrounds, animated names, and so on. Rare items stay reachable by playing better, not by paying.
</p>

<h2>Slip, confirmation and independent markets</h2>
<p>
    You can prepare several choices in the slip (bottom bar on mobile) before confirming. Once
    confirmed, picks stay locked until the result. Each market is settled separately: missing the
    exact score does not cancel a correct winner on the same match, and vice versa.
</p>
<p>
    You do not need to be logged in to start building a slip; login is for saving. After
    confirmation a pick cannot be edited — like a real challenge among friends.
</p>

<h2>Results, postponements and voids</h2>
<p>
    Scores arrive via sync with external sports data. If a score is not reliable in time (quota,
    missing fixture, duplicate opponents, etc.), {app} prefers to <strong>postpone or void</strong>
    rather than invent a result. Affected picks are then settled neutrally for everyone involved.
</p>
<p>
    In My space, history shows settled slips. A banner may flag new results. If a match looks wrong,
    first check whether it is marked postponed / void, then read the
    <a href="{faq_url}">FAQ</a> or email {contact_mailto}.
</p>

<h2>Fairness</h2>
<p>
    Awards are the same for everyone. Communities can have their own season ranking, but site point
    rules stay shared. Cheating (abusive multi-accounts, harassment, spam) is forbidden by the
    <a href="{cgu_url}">Terms</a> and may lead to sanctions.
</p>
<p>
    For a short award summary, also open the points help (points icon) once logged in, or reread
    <a href="{howto_url}">How it works</a>. For product philosophy, see
    <a href="{about_url}">About {app}</a>.
</p>
HTML;
