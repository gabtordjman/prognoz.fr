<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

return <<<'HTML'
<p>
    These terms of use (the “Terms”) govern access to and use of the <strong>{app}</strong> website.
    By creating an account or using the service, you accept them. If you do not agree, do not use the site.
</p>

<h2>1. Purpose and nature of the service</h2>
<p>
    {app} is a <strong>free social sports prediction game</strong> (football, basketball, tennis, depending
    on the fixtures shown). It lets you predict matches, earn <strong>in-game points</strong>, take part in
    seasons and rankings, spend those points on visual items, and interact with other players (communities,
    friends, chat).
</p>
<p>
    <strong>This is not a gambling or real-money betting site.</strong> No financial stake is required, no
    cash prize is paid, points have no monetary value and cannot be converted into money or sold for money.
    Any odds or percentages shown come from a third-party source and are indicative only: they are not an
    offer to place a real-money bet.
</p>
<p>
    {app} is not a gambling operator, is not licensed by the French gambling authority (ANJ) and does not
    need to be for this free service with no stake. The site is not a bookmaker.
</p>

<h2>2. Publisher and legal notice</h2>
<p>
    The publisher’s identity, contact details and the hosting provider are set out in the
    <a href="{mentions_url}">legal notice</a>.
    Personal data is described in the
    <a href="{privacy_url}">privacy policy</a>.
</p>

<h2>3. Access, free use and age</h2>
<p>
    The service is <strong>free</strong>. Browsing matches does not require an account; saving predictions,
    joining communities, using chat, friends, the shop or notifications does.
</p>
<p>
    The service is for people aged <strong>{min_age} or over</strong>. By creating an account you confirm
    you have reached that age. We do not knowingly collect data about children under {min_age}. If an
    account was opened in breach of this rule, write to {contact_mailto} so we can delete it.
</p>
<p>
    You need an Internet connection and an up-to-date browser. Some features (push notifications, profile
    photo) depend on your device and the permissions you grant.
</p>

<h2>4. User account</h2>
<ul>
    <li>You must provide a username and an email address that are accurate and that you are entitled to use.</li>
    <li>You are responsible for keeping your password confidential and for activity on your account.</li>
    <li>Username and password can be changed from
        <a href="{settings_url}">Settings</a>, with a minimum of {cooldown} days between two changes
        (except a password reset by email when you are logged out).</li>
    <li>You may add a profile photo, display name, bio, favourite sport and favourite teams.
        You remain responsible for those being lawful and respectful.</li>
    <li>One account per person is required. Accounts created to distort a ranking, spam or bypass a
        sanction may be suspended.</li>
    <li>The “Friends” feature lets you add other players by username, with mutual acceptance.</li>
</ul>

<h2>5. Predictions, points, seasons and shop</h2>
<ul>
    <li>Points, badges, streaks, multipliers and season rewards have <strong>no monetary value</strong>.</li>
    <li>Predictions lock at kick-off (according to the time shown on the site).</li>
    <li>Your prediction history is available in “My space”.</li>
    <li>Season rankings may be reset periodically. Badges or visual items (with no cash value) may be awarded.</li>
    <li>The shop lets you spend already-locked in-game points on cosmetics (profile backgrounds, username
        colours, etc.). These items cannot be bought with real money and cannot be resold.</li>
    <li>The publisher may run temporary events (point multipliers, theming). They do not change the free,
        non-monetary nature of the game.</li>
    <li>Results, odds and line-ups come from third-party sources. Delays, gaps or errors can occur.
        The publisher may correct a score, void a match (related picks scored 0) or adjust points to keep
        the game fair. That does not create any right to financial compensation (there is no money at stake).</li>
</ul>
<p>
    Scoring is explained in the in-site help and on
    <a href="{howto_url}">How it works</a>. The publisher may update game rules (scoring, seasons, markets)
    for the proper running of the service. Such changes have no retroactive effect on money, because there
    is none.
</p>

<h2>6. Communities and content</h2>
<p>
    You remain responsible for messages, photos, bios, community names and any other content you post.
    In particular, the following are forbidden: unlawful, hateful, harassing, pornographic or defamatory
    content, or content that invades someone else’s privacy; apology for crimes; spam; impersonation;
    attempts to hack, bypass security or misuse the service; unauthorised advertising.
</p>
<p>
    Private communities are meant for people who know each other or who invite one another. The same
    respect is required there as on the rest of the site. You must not post other people’s personal data
    without a legal basis.
</p>
<p>
    Content you post remains yours. You grant the publisher a non-exclusive, royalty-free, worldwide
    licence to host, display and moderate it for the service, for as long as the service needs it.
</p>

<h2>7. Moderation</h2>
<p>
    To run the service, keep users safe and enforce these Terms, the publisher (through an administration
    access distinct from player accounts) may in particular:
</p>
<ul>
    <li><strong>Read community chat messages</strong> — including those stored encrypted in the database —
        <strong>only when necessary</strong> (a report, suspected abuse, a legal obligation, security, or
        a technical diagnosis tied to an incident). This is not systematic reading of all conversations.</li>
    <li><strong>Hide or delete</strong> a message, photo, bio or any content that breaches these Terms.</li>
    <li><strong>Suspend (disable) or restore</strong> an account, or reset a password at the user’s request
        or in a security incident.</li>
    <li><strong>Correct scores</strong>, void a match, add or remove in-game points, close or schedule a season.</li>
    <li>Take any reasonable step to protect the service (access limits, technical clean-up, etc.).</li>
</ul>
<p>
    A suspension does not give rise to compensation. If you disagree, you can write to {contact_mailto}.
</p>

<h2>8. Intellectual property</h2>
<p>
    The site, its visual identity, original texts and code remain the property of the publisher or its
    licensors. Unauthorised reproduction is forbidden, except as allowed by law (private copy, short
    quotation, etc.).
</p>
<p>
    Team names, competitions, players and sports brands belong to their respective owners. They appear
    on the site only to identify matches. That does not imply any affiliation, sponsorship or partnership.
</p>

<h2>9. Personal data</h2>
<p>
    See the <a href="{privacy_url}">privacy policy</a>.
    You may delete your account at any time from
    <a href="{settings_url}">Settings</a> (a definitive action on the production database).
</p>

<h2>10. Advertising</h2>
<p>
    Third-party ads (in particular Google AdSense) may be shown. Advertising scripts are loaded
    <strong>only after you agree</strong> via the cookie banner. You can refuse as easily as you can
    accept, and change your mind later. Refusing advertising cookies does not stop you from playing.
</p>

<h2>11. Availability and limitation of liability</h2>
<p>
    The service is provided free of charge, “as is”, including when a “beta” notice is shown. The
    publisher aims to keep it working but does not guarantee uninterrupted availability or the absence
    of errors (scores, sync, notifications, display).
</p>
<p>
    The publisher is not liable for content posted by users, outages of networks or third-party providers,
    or indirect damage. Because the game is free and has no money at stake, no financial loss tied to
    points, rankings or shop items can be claimed.
</p>
<p>
    These limitations <strong>do not apply</strong> in cases of wilful misconduct or gross negligence,
    harm to a person, or rights that the law does not allow you to waive (including consumer rights).
</p>

<h2>12. Changes to the Terms</h2>
<p>
    The publisher may change these Terms to reflect a change in the service or in the law. The date at
    the top of this page is authoritative. For a significant change, a reasonable notice may be used
    (for example a note on the site). Using the service after the new Terms take effect means you accept
    them. If you refuse a change, you may delete your account.
</p>

<h2>13. Duration and termination</h2>
<p>
    These Terms apply for as long as you use the service. You may stop using the site and delete your
    account at any time. The publisher may suspend or close an account for a serious or repeated breach,
    or discontinue the service (and will try to inform users if that is reasonably possible).
</p>

<h2>14. Governing law</h2>
<p>
    These Terms are governed by <strong>French law</strong>.
    Failing an amicable solution, the competent French courts shall have jurisdiction, without prejudice
    to mandatory consumer-protection rules (if you are a consumer you may in particular bring a claim
    in the courts of your place of residence).
</p>
<p>
    If a translation differs from the French text, the French version prevails.
</p>

<h2>15. Contact</h2>
<p>Questions about these Terms: {contact_mailto}</p>
HTML;
