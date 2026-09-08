<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

return <<<'HTML'
<p>
    {app} is not designed as one giant anonymous leaderboard. From day one the goal has been the
    vibe of a challenge among friends: who called the derby, who dared the exact score, who keeps
    the streak. <strong>Friends</strong> and <strong>private communities</strong> carry that spirit.
</p>

<h2>Friends</h2>
<p>
    The friend list helps you quickly find the profiles that matter to you. You see their points,
    streak and season — enough to start a chat or a healthy rivalry. Adding someone is voluntary:
    nobody has to accept a request.
</p>
<p>
    Friends do not replace the slip: everyone builds and confirms their own picks. Comparing
    history and rankings simply feels more meaningful when you know who you are playing for.
</p>

<h2>Private communities</h2>
<p>
    A community is a closed space with:
</p>
<ul>
    <li>a name chosen by the creator;</li>
    <li>invited members (invite link);</li>
    <li>a season ranking local to the group;</li>
    <li>a chat to comment on matches, gently roast a miss, or celebrate a streak.</li>
</ul>
<p>
    Communities are <strong>private by design</strong>: they are not a public showcase for search
    engines. Access is invite-only. That is intentional — discussion content belongs to the circle,
    not an open feed.
</p>
<p>
    The creator (or community admin, depending on rights) can manage members. Site respect rules
    also apply in chat: no harassment, illegal content or spam. Details are in the
    <a href="{cgu_url}">Terms</a>.
</p>

<h2>Chat and privacy</h2>
<p>
    Community messages are handled with protections described in the
    <a href="{privacy_url}">Privacy policy</a>. The publisher may step in on reports or rule
    breaches, proportionately. Chat is not a free-for-all — it is a tool to talk sport among members.
</p>
<p>
    Push notifications (if enabled) can alert you to a message or a winning pick. You can decline
    them in account and browser settings.
</p>

<h2>Global ranking vs community ranking</h2>
<p>
    Two useful readings:
</p>
<ul>
    <li><strong>Season / global profile</strong> — your progress across the whole site, with the
        same point rules for everyone (see
        <a href="{points_url}">Points, seasons &amp; streaks</a>).</li>
    <li><strong>Community ranking</strong> — local competition among group members. Ideal for an
        office challenge, a supporters’ crew or a family.</li>
</ul>
<p>
    You can join several communities. Each group has its own dynamic; the account stays unique and free.
</p>

<h2>Inviting without pressure</h2>
<p>
    An invite link lets someone join after login or registration. {app} stays free for invitees as
    for you. There is no paid referral scheme or monetised invite quota. If someone only wants to
    browse matches without joining a group, that is fine too.
</p>

<h2>Community etiquette</h2>
<ul>
    <li>Clarify the group tone early (casual, competitive, family-friendly).</li>
    <li>Avoid spoiling a result if members have not watched the match yet — simple courtesy.</li>
    <li>Do not pile on someone who loses a streak — the game should stay fun.</li>
    <li>Use reporting / contact ({contact_mailto}) if behaviour crosses a line.</li>
</ul>

<h2>How this fits the rest of the site</h2>
<p>
    Communities do not change the product’s nature: still <strong>free predictions</strong>, still
    <strong>game points</strong>, still a friends-first spirit. For the bigger picture, read
    <a href="{about_url}">About {app}</a>. For a five-step start,
    <a href="{howto_url}">How it works</a>. Common questions:
    <a href="{faq_url}">FAQ</a>.
</p>
HTML;
