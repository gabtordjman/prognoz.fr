<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

return <<<'HTML'
<p>
    {app} is a <strong>free social sports-prediction game</strong>. Since the first release the
    idea has stayed the same: challenge your friends on football, basketball and tennis, earn
    <strong>game points</strong>, compare rankings and chat in communities — never wagering money,
    never using a bookmaker, never cashing out.
</p>
<p>
    This page explains what the site is (and is not). For a short how-to, see
    <a href="{howto_url}">How it works</a>.
</p>

<h2>No money betting</h2>
<p>
    Here a “bet” means a <strong>free pick</strong>: you choose a winner (1 / D / 2), sometimes an
    exact score or a scorer, confirm a slip, wait for the result. Wins earn points. Bonus markets
    never take points from your balance on a miss; a wrong winner only resets the streak. No bank
    card required to play.
</p>
<p>
    Odds or percentages shown come from a third party and are indicative only. They are not a
    money-betting offer. {app} is not a gambling operator and does not need a bookmaker licence:
    <strong>no stake, no cash prizes</strong>.
</p>

<h2>Three pillars since 1.0</h2>
<h3>1. Free picks</h3>
<p>
    You can browse upcoming matches (about {match_horizon} days) without an account. An account
    saves picks, history, friends, communities, and spending points in the cosmetic shop.
    Registration is for people aged {min_age}+.
</p>
<h3>2. Friends and communities</h3>
<p>
    The global leaderboard is fine. The real fun is <strong>private communities</strong>, invites,
    chat and comparing slips in a circle you choose — team, flatmates, family. Details in
    <a href="{communities_url}">Communities &amp; friends</a>.
</p>
<h3>3. Earn points (not money)</h3>
<p>
    Points measure good picks. They feed the season ranking (about {season_days} days), badges,
    1x2 streaks and the shop (profile backgrounds, animated names). They have
    <strong>no monetary value</strong>, cannot be cashed out and cannot be bought. See
    <a href="{points_url}">Points, seasons &amp; streaks</a>.
</p>

<h2>What {app} is not</h2>
<ul>
    <li>Not a bookmaker or real-money betting site.</li>
    <li>Not a paid tipster or “sure picks” service.</li>
    <li>Not a general social network — we are here for sport and the slip.</li>
    <li>Not freemium that locks gameplay behind a subscription.</li>
</ul>
<p>
    The shop only uses points earned by playing. The goal is fun with friends, not monetising the
    pick itself.
</p>

<h2>Sports and fixtures</h2>
<p>
    Depending on the period: football, basketball, tennis. Calendars come from third-party sports
    data. If a result is not reliable in time or the fixture is ambiguous, we postpone or void
    rather than invent a score. A postponed match beats a wrong result for everyone.
</p>

<h2>Who is it for?</h2>
<p>
    Sports fans who want a light excuse to hang out: colleagues on the league, NBA friends, family
    during a Grand Slam. You do not need to be an odds expert to build a slip and compete in your
    community.
</p>
<p>
    To get started:
</p>
<ol>
    <li><a href="{howto_url}">How it works</a> (overview).</li>
    <li>Matches on the <a href="{home_url}">home page</a> — build a slip.</li>
    <li>Free account via <a href="{register_url}">registration</a> to confirm.</li>
    <li>Invite friends into a private community.</li>
</ol>

<h2>Transparency and contact</h2>
<p>
    Rules, data and publisher:
    <a href="{cgu_url}">Terms</a>,
    <a href="{privacy_url}">Privacy</a>,
    <a href="{mentions_url}">Legal notice</a>.
    Practical question: start with the <a href="{faq_url}">FAQ</a>, otherwise {contact_mailto}.
</p>
<p>
    Short version: {app} is a prediction game among friends. Free, community-first, points-driven —
    as it has been since version&nbsp;1.0.
</p>
HTML;
