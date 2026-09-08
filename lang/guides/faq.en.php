<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

return <<<'HTML'
<p>
    Answers to the most common questions about {app}. This FAQ complements
    <a href="{howto_url}">How it works</a> and the guides
    <a href="{about_url}">About</a>,
    <a href="{points_url}">Points</a> and
    <a href="{communities_url}">Communities</a>.
    If your case is missing, email {contact_mailto}.
</p>

<h2>Account and freeness</h2>
<h3>Is it really free?</h3>
<p>
    Yes. Playing, confirming picks, joining friends and communities, earning points and using the
    cosmetic shop happens <strong>without paying</strong>. Points have no monetary value. {app} is
    not a bookmaker.
</p>
<h3>Do I need an account for everything?</h3>
<p>
    No to browse matches and start a slip. Yes to confirm picks, keep history, friends, communities,
    chat, the shop and some notifications.
</p>
<h3>Minimum age?</h3>
<p>
    {min_age}+ at registration. Legal detail is in the
    <a href="{cgu_url}">Terms</a> and <a href="{privacy_url}">Privacy policy</a>.
</p>

<h2>Picks and slips</h2>
<h3>Can I edit a pick after confirming?</h3>
<p>
    No. Once confirmed, the choice stays locked until the result — like a challenge set for your
    friends. You can remove selections from the slip <em>before</em> confirming.
</p>
<h3>Are markets linked?</h3>
<p>
    No. On the same match, a correct winner and a missed exact score (or the reverse) are settled
    separately. See the <a href="{points_url}">Points</a> guide.
</p>
<h3>What do 1, D and 2 mean?</h3>
<p>
    Classic outcomes: home side (1), draw when the sport allows it (D), away side (2). In tennis or
    basketball depending on format, the draw may be absent.
</p>

<h2>Points, streaks and seasons</h2>
<h3>I got it wrong — will I lose points?</h3>
<p>
    Not from your balance. Bonus markets (exact score, scorer, favourite team) award points or 0.
    A wrong 1x2 only resets the streak. No total goes below 0.
</p>
<h3>What is the season for?</h3>
<p>
    About {season_days} days of a fresh ranking, with possible podium and badges
    (+{podium_1} / +{podium_2} / +{podium_3}). The profile total keeps long-term history.
</p>
<h3>Is the shop paid?</h3>
<p>
    Not with money. You spend points earned by playing on visual items (backgrounds, animated
    names…). None of that artificially boosts sporting results.
</p>

<h2>Results and fixtures</h2>
<h3>Why is a match “postponed” when it was on TV?</h3>
<p>
    Because a reliable score is not always available immediately from sync (external data, delay,
    identity ambiguity). {app} prefers postpone / void over assigning a doubtful score. Affected
    players are treated the same way.
</p>
<h3>Who provides fixtures and odds?</h3>
<p>
    Third-party sports data. Displayed odds or percentages are indicative and are not a money-betting offer.
</p>
<h3>How many matches do I see?</h3>
<p>
    Roughly a {match_horizon}-day horizon, split by sport. The list follows the real calendar.
</p>

<h2>Communities</h2>
<h3>Are my chats public?</h3>
<p>
    No. Communities are private, invite-only. See
    <a href="{communities_url}">Communities &amp; friends</a>.
</p>
<h3>Can I create several groups?</h3>
<p>
    Yes. One free account can join or create multiple communities.
</p>

<h2>Data, ads and contact</h2>
<h3>Where do you explain what you do with my data?</h3>
<p>
    In the <a href="{privacy_url}">Privacy policy</a>. Cookies and advertising (if enabled after
    consent) are described there. You can review cookie choices from the footer.
</p>
<h3>How do I contact the team?</h3>
<p>
    {contact_mailto}. For publisher and host identity:
    <a href="{mentions_url}">Legal notice</a>.
</p>
<p>
    Want the project spirit on one page?
    <a href="{about_url}">About {app}</a>. All guides:
    <a href="{guides_url}">index</a>.
</p>
HTML;
