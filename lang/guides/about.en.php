<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

return <<<'HTML'
<p>
    {app} is a <strong>free social sports-prediction game</strong>. The idea has been the same since
    the first release: challenge your friends on football, basketball and tennis, earn
    <strong>game points</strong>, compare rankings and chat in communities — never wagering money,
    never using a bookmaker, never cashing out winnings.
</p>
<p>
    This guide explains why the site exists, what it is (and is not), and how community spirit stays
    at the centre of the product. For a short how-to, start with
    <a href="{howto_url}">How it works</a>.
</p>

<h2>An alternative to money betting</h2>
<p>
    Many sites mix entertainment with real-money stakes. {app} deliberately does the opposite.
    “Bets” here are <strong>free predictions</strong>: you pick a winner (1 / D / 2), sometimes an
    exact score or a scorer, confirm a slip, then wait for the match result. Wins earn points; bonus
    markets never take points away on a miss, and a wrong winner only resets your streak. No bank
    card is required to play.
</p>
<p>
    Any odds or percentages shown come from a third-party source and are indicative only. They are
    not a money-betting offer and do not turn {app} into a gambling operator. The service does not
    need a sports-betting licence for one clear reason: <strong>no stake, no cash prizes</strong>.
</p>

<h2>Three pillars unchanged since 1.0</h2>
<h3>1. Free picks</h3>
<p>
    Browsing upcoming matches (roughly a {match_horizon}-day horizon) works without an account.
    An account lets you save picks, follow history, join friends and communities, and spend points
    in the cosmetic shop. Registration is for people aged {min_age}+.
</p>
<h3>2. Community spirit</h3>
<p>
    The global leaderboard is only part of the experience. The heart of the game is
    <strong>private communities</strong>, friend invites, chat and comparing slips inside a circle
    you choose. You do not have to compete with the whole internet — create a closed group for your
    team, flatmates or family. Details in
    <a href="{communities_url}">Communities &amp; friends</a>.
</p>
<h3>3. Earn points (not money)</h3>
<p>
    Points measure sporting success in the game. They feed the season ranking (about {season_days}
    days), badges, 1x2 streaks and the visual shop (profile backgrounds, animated names). They have
    <strong>no monetary value</strong>, cannot be cashed out and cannot be bought. See
    <a href="{points_url}">Points, seasons &amp; streaks</a>.
</p>

<h2>What {app} is not</h2>
<ul>
    <li>Not a bookmaker or real-money betting site.</li>
    <li>Not a paid tipster or “sure picks” service.</li>
    <li>Not a general social network — the thread is sport and the slip.</li>
    <li>Not a freemium product that locks gameplay behind a subscription.</li>
</ul>
<p>
    The shop uses only points earned by playing. The goal remains fun with friends, not monetising
    the prediction itself.
</p>

<h2>Sports and fixtures</h2>
<p>
    Depending on the period, {app} lists football, basketball and tennis fixtures. Calendars sync
    from third-party sports data. A match may be postponed or cancelled in-game if a result is
    unavailable in time or the fixture identity is ambiguous — in those cases picks are settled
    transparently (often voided / postponed) rather than inventing a score. Fairness between players
    beats rushing a doubtful result.
</p>

<h2>Who is it for?</h2>
<p>
    {app} is for sports fans who want a light excuse to hang out: colleagues following the league,
    friends watching the NBA, family during a Grand Slam. The tone stays accessible — you do not
    need to be an odds expert to build a slip and compete in your community.
</p>
<p>
    If you are new, the recommended path is:
</p>
<ol>
    <li>Read <a href="{howto_url}">How it works</a> (step overview).</li>
    <li>Browse matches on the <a href="{home_url}">home page</a> and build a slip.</li>
    <li>Create a free account via <a href="{register_url}">registration</a> to confirm picks.</li>
    <li>Invite friends into a private community.</li>
</ol>

<h2>Transparency and contact</h2>
<p>
    Usage rules, privacy and publisher identity are in the
    <a href="{cgu_url}">Terms</a>,
    <a href="{privacy_url}">Privacy policy</a> and
    <a href="{mentions_url}">Legal notice</a>.
    For practical questions (account, result, community), start with the
    <a href="{faq_url}">FAQ</a>, then email {contact_mailto}.
</p>
<p>
    In short: {app} is a sports playground for friends. Free, community-first, points-driven —
    as it has been since version&nbsp;1.0.
</p>
HTML;
