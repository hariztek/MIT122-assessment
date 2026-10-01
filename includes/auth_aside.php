<?php
/**
 * includes/auth_aside.php
 *
 * Decorative side panel shown beside the login/register forms on wide
 * screens (hidden below 1024px by CSS). Static copy only; the match card
 * is an illustration, marked aria-hidden, not real user data.
 */
?>
<aside class="auth-aside" aria-label="About Student SkillBridge">
    <div class="landing-blob auth-aside__blob" aria-hidden="true"></div>
    <p class="label-md landing-eyebrow">Free &middot; Student-run</p>
    <h2 class="headline-lg auth-aside__title">Teach what you know.<br>Learn what you don't.</h2>
    <ul class="auth-aside__points">
        <li><span class="advantage__icon" aria-hidden="true">1</span> List skills you can teach and want to learn</li>
        <li><span class="advantage__icon" aria-hidden="true">2</span> Get matched with a score you can understand</li>
        <li><span class="advantage__icon" aria-hidden="true">3</span> Book a session and review it afterwards</li>
    </ul>
    <div class="mock-card mock-card--tilt-left auth-aside__card" aria-hidden="true">
        <p class="label-md landing-muted">Example match</p>
        <p class="mock-card__name">Python with Priya</p>
        <p class="match-score mock-card__score">85 match</p>
        <p class="match-score__breakdown">+50 skill &middot; +25 mode &middot; +10 experience</p>
    </div>
</aside>
