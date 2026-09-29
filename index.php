<?php
/**
 * index.php — landing page (SB-016).
 *
 * Explains what Student SkillBridge is, how the deterministic matching
 * works, and links to register/login (or profile/search when logged in).
 *
 * The only database work is a read-only count of catalogue skills per
 * category, so the numbers on the page always reflect the real seed data
 * rather than hard-coded marketing figures. The "mock" cards in the hero
 * and feature sections are decorative illustrations (aria-hidden), not
 * live user data.
 */
$pageTitle = 'Home';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/header.php';

// Category labels match the skills.category ENUM (see database/schema.sql).
$categoryLabels = [
    'technology'   => 'Technology',
    'creative'     => 'Creative',
    'languages'    => 'Languages',
    'career_study' => 'Career & study',
    'practical'    => 'Practical',
];

// No user input involved, but still a fixed SELECT via PDO.
$categoryCounts = array_fill_keys(array_keys($categoryLabels), 0);
$stmt = $pdo->query('SELECT category, COUNT(*) AS total FROM skills GROUP BY category');
foreach ($stmt as $row) {
    if (isset($categoryCounts[$row['category']])) {
        $categoryCounts[$row['category']] = (int) $row['total'];
    }
}
$totalSkills = array_sum($categoryCounts);

$primaryHref  = $isLoggedIn ? '/pages/profile.php' : '/pages/register.php';
$primaryLabel = $isLoggedIn ? 'Edit your skills' : 'Create a free account';
?>

<!-- Hero -------------------------------------------------------------->
<section class="landing-hero">
    <div class="landing-hero__copy">
        <p class="label-md landing-eyebrow">Free &middot; Student-run &middot; No payments</p>
        <h1 class="headline-xl landing-hero__title">Teach what you know.<br>Learn what you don't.</h1>
        <p class="body-lg landing-muted">Student SkillBridge connects university and college students who want to swap skills. List what you can teach, what you want to learn, and we'll show you who fits &mdash; and exactly why.</p>
        <div class="btn-row">
            <a href="<?= e($primaryHref) ?>" class="btn btn--primary"><?= e($primaryLabel) ?></a>
            <?php if ($isLoggedIn): ?>
                <a href="/pages/matches.php" class="btn btn--secondary">See your matches</a>
            <?php else: ?>
                <a href="/pages/login.php" class="btn btn--secondary">Log in</a>
            <?php endif; ?>
        </div>
        <a href="#how-it-works" class="btn btn--tertiary landing-hero__more">Find out more &darr;</a>
    </div>

    <div class="landing-hero__visual" aria-hidden="true">
        <div class="landing-blob"></div>
        <div class="mock-card mock-card--tilt-left">
            <p class="label-md landing-muted">Your match</p>
            <p class="mock-card__name">Priya S.</p>
            <p class="card__meta">Teaches Python &middot; wants Spanish</p>
            <p class="match-score mock-card__score">100 match</p>
            <p class="match-score__breakdown">+50 skill &middot; +25 mode &middot; +15 availability &middot; +10 experience</p>
        </div>
        <div class="mock-card mock-card--tilt-right">
            <p class="label-md landing-muted">Request</p>
            <p class="mock-card__name">Intro to SQL joins</p>
            <p class="card__meta">Thu 4:00 pm &middot; Online</p>
            <p class="chip-row"><span class="chip chip--status-accepted">Accepted</span></p>
        </div>
    </div>
</section>

<!-- Two feature cards ------------------------------------------------->
<section class="section" id="how-it-works" aria-labelledby="features-heading">
    <h2 class="headline-lg landing-section-title" id="features-heading">Get the most out of<br>your time on campus</h2>
    <div class="feature-grid">
        <article class="feature-card">
            <div class="feature-card__text">
                <h3 class="card__title">One profile, both sides</h3>
                <p class="card__meta">Record the skills you offer and the skills you want, with level, mode and availability, all in one place.</p>
                <a href="<?= e($primaryHref) ?>" class="btn btn--tertiary">Set up your profile &rarr;</a>
            </div>
            <div class="feature-card__art feature-card__art--pills" aria-hidden="true">
                <span class="chip">Teach: Excel</span>
                <span class="chip">Learn: Auslan</span>
                <span class="chip">Online</span>
            </div>
        </article>
        <article class="feature-card">
            <div class="feature-card__text">
                <h3 class="card__title">Matches you can explain</h3>
                <p class="card__meta">Every suggestion is a fixed score out of 100, with a plain-language breakdown of which parts matched.</p>
                <a href="/pages/matches.php" class="btn btn--tertiary">View matches &rarr;</a>
            </div>
            <div class="feature-card__art" aria-hidden="true">
                <div class="score-ring"><span>85</span></div>
            </div>
        </article>
    </div>
</section>

<!-- Advantages -------------------------------------------------------->
<section class="section advantages" aria-labelledby="advantages-heading">
    <div class="advantages__intro">
        <h2 class="headline-lg" id="advantages-heading">Why SkillBridge</h2>
        <p class="body-md landing-muted">Built by a student, for students. Simple rules, no hidden algorithms, and nothing to pay.</p>
    </div>
    <div class="advantages__grid">
        <div class="advantage">
            <span class="advantage__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/><path d="M4 4l16 16"/></svg>
            </span>
            <h3 class="label-lg advantage__title">Always free</h3>
            <p class="card__meta">No payments, no credits, no premium tier. You trade time for time.</p>
            <a href="/pages/register.php" class="btn btn--secondary btn--sm">Sign up</a>
        </div>
        <div class="advantage">
            <span class="advantage__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            </span>
            <h3 class="label-lg advantage__title">Transparent matching</h3>
            <p class="card__meta">A fixed point score &mdash; skill, mode, availability, experience. No AI guesswork.</p>
            <a href="/pages/matches.php" class="btn btn--secondary btn--sm">How it scores</a>
        </div>
        <div class="advantage">
            <span class="advantage__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            </span>
            <h3 class="label-lg advantage__title">Structured requests</h3>
            <p class="card__meta">Every session request states a goal and a proposed time, so nobody's left guessing.</p>
            <a href="/pages/dashboard.php" class="btn btn--secondary btn--sm">Open dashboard</a>
        </div>
        <div class="advantage">
            <span class="advantage__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>
            </span>
            <h3 class="label-lg advantage__title">Honest reviews</h3>
            <p class="card__meta">You can only review someone after you've completed a session together.</p>
            <a href="/pages/search.php" class="btn btn--secondary btn--sm">Browse skills</a>
        </div>
    </div>
</section>

<!-- Categories (in place of the reference's "partners" row) ----------->
<section class="section categories" aria-labelledby="categories-heading">
    <h2 class="headline-lg" id="categories-heading">Five ways to grow</h2>
    <p class="body-md landing-muted">Skills are grouped into five categories. Pick one to see who's teaching it.</p>
    <ul class="categories__list">
        <?php foreach ($categoryLabels as $key => $label): ?>
            <li>
                <a class="category-pill" href="/pages/search.php?category=<?= e(urlencode($key)) ?>">
                    <span class="category-pill__count"><?= $categoryCounts[$key] ?></span>
                    <span class="category-pill__label"><?= e($label) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<!-- Dark band CTA ---------------------------------------------------->
<section class="section band" aria-labelledby="band-heading">
    <div class="band__copy">
        <h2 class="headline-lg" id="band-heading">Keep every session on track</h2>
        <p class="body-md landing-muted">Accept, decline, cancel or complete requests from one dashboard.</p>
        <a href="<?= e($isLoggedIn ? '/pages/dashboard.php' : '/pages/register.php') ?>" class="btn btn--primary"><?= $isLoggedIn ? 'Open dashboard' : 'Get started' ?></a>
    </div>
    <div class="band__visual" aria-hidden="true">
        <div class="mock-card mock-card--list">
            <p class="label-md landing-muted">Your requests</p>
            <div class="mock-row"><span>Photography basics</span><span class="chip chip--status-pending">Pending</span></div>
            <div class="mock-row"><span>Interview prep</span><span class="chip chip--status-accepted">Accepted</span></div>
            <div class="mock-row"><span>Mandarin tones</span><span class="chip chip--status-completed">Completed</span></div>
        </div>
    </div>
</section>

<!-- Image left, text right ------------------------------------------->
<section class="section split" aria-labelledby="goal-heading">
    <div class="split__visual" aria-hidden="true">
        <div class="split__backdrop"></div>
        <div class="mock-card">
            <p class="label-md landing-muted">New request</p>
            <p class="mock-card__name">Goal: build my first website</p>
            <p class="card__meta">Proposed: Mon 2:00 pm &middot; Library, level 2</p>
            <div class="btn-row mock-card__actions">
                <span class="btn btn--primary btn--sm">Accept</span>
                <span class="btn btn--secondary btn--sm">Decline</span>
            </div>
        </div>
    </div>
    <div class="split__copy">
        <h2 class="headline-lg" id="goal-heading">Start with a clear goal</h2>
        <p class="body-md landing-muted">Requests aren't vague "want to meet?" messages. You say what you want to achieve and when, and the other student decides whether it works for them.</p>
    </div>
</section>

<!-- Skill catalogue ------------------------------------------------->
<section class="section split split--reverse" aria-labelledby="catalogue-heading">
    <div class="split__copy">
        <h2 class="headline-lg" id="catalogue-heading"><span data-count="<?= $totalSkills ?>"><?= $totalSkills ?></span> skills<br>ready to swap</h2>
        <p class="body-md landing-muted">From Python and SQL to Auslan, public speaking and bike repair. Add them to your profile as something you teach, something you want to learn, or both.</p>
        <a href="/pages/search.php" class="btn btn--tertiary">Browse the catalogue &rarr;</a>
    </div>
    <div class="split__visual split__visual--stack" aria-hidden="true">
        <div class="landing-blob landing-blob--right"></div>
        <div class="mock-row mock-row--float"><span>Python</span><span class="card__meta">Technology</span></div>
        <div class="mock-row mock-row--float mock-row--offset"><span>Photography</span><span class="card__meta">Creative</span></div>
        <div class="mock-row mock-row--float"><span>Public speaking</span><span class="card__meta">Career &amp; study</span></div>
    </div>
</section>

<!-- Final CTA ------------------------------------------------------->
<section class="section final-cta" aria-labelledby="final-heading">
    <h2 class="headline-lg" id="final-heading">Free for every student.<br>Start swapping today.</h2>
    <a href="<?= e($primaryHref) ?>" class="btn btn--primary"><?= e($primaryLabel) ?></a>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
