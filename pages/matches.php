<?php
/**
 * pages/matches.php — ranked, explained matches (SB-023, SB-024, SB-025; FR-06).
 *
 * For every skill the logged-in student WANTS, finds other active
 * students who OFFER that skill, scores each pair with the fixed rules in
 * includes/matching.php (+50 skill, +25 mode, +15 availability,
 * +10 experience), and lists them highest score first. Each card shows
 * every rule — met or not — so the score is fully explained. No AI/ML.
 *
 * Also notes (for information only, no points) when the other student
 * wants to learn something this student teaches, i.e. a two-way swap.
 *
 * All queries are parameterised; suspended accounts and hidden skill
 * entries are excluded, as in search.
 */
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/matching.php';

$userId = current_user_id();

/* ------------------------------------------------------------------ */
/* The learner's wanted skills                                          */
/* ------------------------------------------------------------------ */

$wantStmt = $pdo->prepare(
    'SELECT us.skill_id, us.level, us.mode, us.availability, s.name AS skill_name
     FROM user_skills us
     JOIN skills s ON s.skill_id = us.skill_id
     WHERE us.user_id = :me AND us.type = \'want\' AND us.status = \'active\'
     ORDER BY s.name'
);
$wantStmt->execute(['me' => $userId]);
$wants = [];
foreach ($wantStmt as $row) {
    $wants[(int) $row['skill_id']] = $row;
}

// Optional filter to a single wanted skill; ignored if it isn't one of theirs.
$skillFilter = filter_var($_GET['skill'] ?? '', FILTER_VALIDATE_INT) ?: 0;
if (!isset($wants[$skillFilter])) {
    $skillFilter = 0;
}

/* ------------------------------------------------------------------ */
/* Candidate teachers + scoring                                         */
/* ------------------------------------------------------------------ */

$matches = [];

if ($wants !== []) {
    $offerStmt = $pdo->prepare(
        'SELECT o.skill_id, o.level, o.mode, o.availability, o.description,
                u.user_id, u.name, u.campus
         FROM user_skills o
         JOIN users u ON u.user_id = o.user_id
         WHERE o.type = \'offer\'
           AND o.status = \'active\'
           AND u.status = \'active\'
           AND o.user_id <> :me
           AND o.skill_id IN (
               SELECT w.skill_id FROM user_skills w
               WHERE w.user_id = :me2 AND w.type = \'want\' AND w.status = \'active\'
           )'
    );
    $offerStmt->execute(['me' => $userId, 'me2' => $userId]);

    // What each candidate wants that this student teaches (swap hint).
    $swapStmt = $pdo->prepare(
        'SELECT w.user_id, s.name
         FROM user_skills w
         JOIN skills s       ON s.skill_id = w.skill_id
         JOIN user_skills mine
              ON mine.skill_id = w.skill_id
             AND mine.user_id  = :me
             AND mine.type     = \'offer\'
             AND mine.status   = \'active\'
         WHERE w.type = \'want\' AND w.status = \'active\' AND w.user_id <> :me2
         ORDER BY s.name'
    );
    $swapStmt->execute(['me' => $userId, 'me2' => $userId]);
    $swaps = [];
    foreach ($swapStmt as $row) {
        $swaps[(int) $row['user_id']][] = $row['name'];
    }

    foreach ($offerStmt as $offer) {
        $skillId = (int) $offer['skill_id'];
        if ($skillFilter && $skillId !== $skillFilter) {
            continue;
        }
        $want   = $wants[$skillId];
        $result = score_match($want, $offer);

        $matches[] = [
            'skill_name' => $want['skill_name'],
            'teacher'    => $offer,
            'score'      => $result['score'],
            'reasons'    => $result['reasons'],
            'swap'       => $swaps[(int) $offer['user_id']] ?? [],
        ];
    }

    // Highest score first; ties broken alphabetically so order is stable.
    usort($matches, static function (array $a, array $b): int {
        return [$b['score'], $a['skill_name'], $a['teacher']['name']]
           <=> [$a['score'], $b['skill_name'], $b['teacher']['name']];
    });
}

$pageTitle = 'Matches';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <p class="label-md landing-eyebrow">Your matches</p>
    <h1 class="headline-lg">Students who can teach you</h1>
    <p class="page-header__lede">Every match is a fixed score out of 100. Each card shows exactly which rules scored and which didn't.</p>
</div>

<details class="score-key">
    <summary>How the score works</summary>
    <ul class="score-key__list">
        <li><strong>+<?= MATCH_WEIGHT_SKILL ?></strong> Skill: they offer a skill you want to learn</li>
        <li><strong>+<?= MATCH_WEIGHT_MODE ?></strong> Mode: you can both meet the same way (online / in person), or one of you is fine with either</li>
        <li><strong>+<?= MATCH_WEIGHT_AVAILABILITY ?></strong> Availability: your availability notes share a day or time of day</li>
        <li><strong>+<?= MATCH_WEIGHT_EXPERIENCE ?></strong> Experience: their level is above your current level</li>
    </ul>
</details>

<?php if ($wants === []): ?>
    <div class="card search-empty">
        <h2 class="card__title">Add a skill you want to learn</h2>
        <p class="card__meta">Matches are built from the skills you want. Add at least one to your profile, with your level, preferred mode and availability.</p>
        <div class="btn-row">
            <a href="/pages/profile.php" class="btn btn--primary">Go to your profile</a>
        </div>
    </div>
<?php else: ?>

    <nav class="filter-chips" aria-label="Filter matches by skill">
        <a href="/pages/matches.php" class="filter-chip<?= $skillFilter === 0 ? ' is-active' : '' ?>"<?= $skillFilter === 0 ? ' aria-current="true"' : '' ?>>All skills</a>
        <?php foreach ($wants as $id => $w): ?>
            <a href="/pages/matches.php?skill=<?= (int) $id ?>" class="filter-chip<?= $skillFilter === $id ? ' is-active' : '' ?>"<?= $skillFilter === $id ? ' aria-current="true"' : '' ?>><?= e($w['skill_name']) ?></a>
        <?php endforeach; ?>
    </nav>

    <p class="search-summary" role="status">
        <?php if ($matches === []): ?>
            No one offers <?= $skillFilter ? e($wants[$skillFilter]['skill_name']) : 'your wanted skills' ?> yet.
        <?php else: ?>
            <strong><?= count($matches) ?></strong> <?= count($matches) === 1 ? 'match' : 'matches' ?>, best first.
        <?php endif; ?>
    </p>

    <?php if ($matches === []): ?>
        <div class="card search-empty">
            <h2 class="card__title">No teachers yet</h2>
            <p class="card__meta">Nobody currently offers this. Try adding more skills you want, or check back once more students have joined.</p>
            <div class="btn-row">
                <a href="/pages/search.php" class="btn btn--secondary btn--sm">Browse all skills</a>
                <a href="/pages/profile.php" class="btn btn--tertiary">Edit your wanted skills &rarr;</a>
            </div>
        </div>
    <?php else: ?>
        <ol class="match-list">
            <?php foreach ($matches as $i => $m): ?>
                <?php
                $t       = $m['teacher'];
                $summary = [];
                foreach ($m['reasons'] as $r) {
                    if ($r['met']) {
                        $summary[] = '+' . $r['points'] . ' ' . strtolower($r['label']);
                    }
                }
                ?>
                <li class="match-card">
                    <div class="match-card__head">
                        <div class="match-ring" style="--pct: <?= (int) $m['score'] ?>%" role="img" aria-label="Match score <?= (int) $m['score'] ?> out of 100">
                            <span><?= (int) $m['score'] ?></span>
                        </div>
                        <div class="match-card__who">
                            <p class="label-md match-card__rank">#<?= $i + 1 ?> &middot; <?= e($m['skill_name']) ?></p>
                            <h2 class="card__title"><?= e($t['name']) ?></h2>
                            <p class="card__meta">
                                <?= e(enum_label($t['level'])) ?>
                                &middot; <?= e($t['mode'] === 'both' ? 'Online or in person' : enum_label($t['mode'])) ?>
                                <?php if (!empty($t['campus'])): ?>&middot; <?= e($t['campus']) ?><?php endif; ?>
                            </p>
                            <p class="match-score__breakdown"><?= e(implode(' · ', $summary)) ?></p>
                        </div>
                    </div>

                    <ul class="reason-list" aria-label="Score breakdown">
                        <?php foreach ($m['reasons'] as $r): ?>
                            <li class="reason<?= $r['met'] ? ' is-met' : '' ?>">
                                <span class="reason__icon" aria-hidden="true"><?= $r['met'] ? '✓' : '–' ?></span>
                                <span class="reason__text">
                                    <strong><?= e($r['label']) ?></strong>
                                    <span><?= e($r['detail']) ?></span>
                                </span>
                                <span class="reason__pts">
                                    <span class="visually-hidden"><?= $r['met'] ? 'scored' : 'did not score' ?></span>
                                    +<?= (int) $r['points'] ?><span class="reason__max">/<?= (int) $r['max'] ?></span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if (!empty($t['availability']) || !empty($t['description'])): ?>
                        <div class="match-card__extra">
                            <?php if (!empty($t['availability'])): ?>
                                <p><span class="label-md">Available</span> <?= e($t['availability']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($t['description'])): ?>
                                <p class="card__meta"><?= e(mb_strimwidth($t['description'], 0, 180, '…')) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($m['swap'] !== []): ?>
                        <p class="chip-row match-card__swap">
                            <span class="chip chip--want">Swap possible</span>
                            <span class="card__meta">They want to learn <?= e(implode(', ', $m['swap'])) ?>, which you teach.</span>
                        </p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
