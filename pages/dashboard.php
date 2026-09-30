<?php
/**
 * pages/dashboard.php — session requests (SB-026, SB-027; FR-07).
 *
 * SB-026 / FR-07: send a session request stating a goal and a proposed
 * time. The form is opened from a "Request session" button on a match or
 * search card (?new=<user_skill_id of the teacher's OFFER entry>). The
 * server resolves the receiver and skill from that offer entry itself;
 * nothing about who/what is trusted from hidden form fields.
 *
 * Server-side rules for a new request:
 *   - CSRF token valid; sender logged in and active (require_login()).
 *   - The offer entry exists, is active, is an OFFER, and belongs to an
 *     active student who is not the sender (no self-requests).
 *   - Goal 10–500 characters.
 *   - Proposed time is a real date/time, in the future, within 180 days.
 *   - No existing pending/accepted request from this sender to this
 *     teacher for the same skill (prevents duplicates).
 *
 * SB-027: incoming (you are the teacher) and outgoing (you asked)
 * requests are listed separately. Accept/decline (SB-028) and
 * cancel/complete (SB-029) are the next cards.
 */
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/db.php';

$userId = current_user_id();

const GOAL_MIN        = 10;
const GOAL_MAX        = 500;
const MAX_DAYS_AHEAD  = 180;

/**
 * Load a bookable offer entry (active offer, active owner, not me).
 *
 * @return array<string,mixed>|null
 */
function find_bookable_offer(PDO $pdo, int $userSkillId, int $me): ?array
{
    $stmt = $pdo->prepare(
        'SELECT us.user_skill_id, us.user_id, us.skill_id, us.level, us.mode, us.availability,
                s.name AS skill_name, u.name AS teacher_name, u.campus
         FROM user_skills us
         JOIN skills s ON s.skill_id = us.skill_id
         JOIN users  u ON u.user_id  = us.user_id
         WHERE us.user_skill_id = :id
           AND us.type = \'offer\'
           AND us.status = \'active\'
           AND u.status = \'active\'
           AND us.user_id <> :me
         LIMIT 1'
    );
    $stmt->execute(['id' => $userSkillId, 'me' => $me]);
    return $stmt->fetch() ?: null;
}

/* ------------------------------------------------------------------ */
/* Send a request (POST)                                                */
/* ------------------------------------------------------------------ */

$errors = [];
$values = ['goal' => '', 'proposed_time' => ''];
$offer  = null;

$newId = filter_var($_POST['offer_id'] ?? $_GET['new'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($newId !== false) {
    $offer = find_bookable_offer($pdo, $newId, $userId);
    // Also covers POSTs: without a bookable offer there is no form to
    // show an inline error in, so explain via a flash message instead.
    if ($offer === null) {
        $_SESSION['flash_error'] = 'That skill listing is no longer available to book.';
        redirect('/pages/dashboard.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_request') {
    $values['goal']          = trim((string) ($_POST['goal'] ?? ''));
    $values['proposed_time'] = trim((string) ($_POST['proposed_time'] ?? ''));

    if (!csrf_valid()) {
        $errors['form'] = 'Your session expired. Please submit the form again.';
    }

    if (!required($values['goal'])) {
        $errors['goal'] = 'Say what you want to get out of the session.';
    } elseif (!valid_length($values['goal'], GOAL_MIN, GOAL_MAX)) {
        $errors['goal'] = 'Keep your goal between ' . GOAL_MIN . ' and ' . GOAL_MAX . ' characters.';
    }

    $when = parse_datetime_local($values['proposed_time']);
    if (!required($values['proposed_time'])) {
        $errors['proposed_time'] = 'Choose a proposed date and time.';
    } elseif ($when === null) {
        $errors['proposed_time'] = 'Enter a valid date and time.';
    } elseif ($when <= new DateTimeImmutable('now')) {
        $errors['proposed_time'] = 'Choose a time in the future.';
    } elseif ($when > new DateTimeImmutable('+' . MAX_DAYS_AHEAD . ' days')) {
        $errors['proposed_time'] = 'Choose a time within the next ' . MAX_DAYS_AHEAD . ' days.';
    }

    if ($errors === []) {
        $dupe = $pdo->prepare(
            'SELECT COUNT(*) FROM session_requests
             WHERE sender_id = :me AND receiver_id = :them AND skill_id = :skill
               AND status IN (\'pending\', \'accepted\')'
        );
        $dupe->execute(['me' => $userId, 'them' => $offer['user_id'], 'skill' => $offer['skill_id']]);
        if ((int) $dupe->fetchColumn() > 0) {
            $errors['form'] = 'You already have an open request with ' . $offer['teacher_name'] . ' for ' . $offer['skill_name'] . '.';
        }
    }

    if ($errors === []) {
        $insert = $pdo->prepare(
            'INSERT INTO session_requests (sender_id, receiver_id, skill_id, goal, proposed_time)
             VALUES (:sender, :receiver, :skill, :goal, :proposed_time)'
        );
        $insert->execute([
            'sender'        => $userId,
            'receiver'      => $offer['user_id'],
            'skill'         => $offer['skill_id'],
            'goal'          => $values['goal'],
            'proposed_time' => $when->format('Y-m-d H:i:s'),
        ]);
        $_SESSION['flash_success'] = 'Request sent to ' . $offer['teacher_name'] . '. You\'ll see their reply here.';
        redirect('/pages/dashboard.php#outgoing');
    }
}

/* ------------------------------------------------------------------ */
/* Load incoming + outgoing requests                                    */
/* ------------------------------------------------------------------ */

$statusOrder = 'FIELD(r.status, \'pending\', \'accepted\', \'completed\', \'declined\', \'cancelled\')';

$incomingStmt = $pdo->prepare(
    'SELECT r.request_id, r.goal, r.proposed_time, r.status, r.created_at,
            s.name AS skill_name, u.name AS other_name, u.campus AS other_campus
     FROM session_requests r
     JOIN skills s ON s.skill_id = r.skill_id
     JOIN users  u ON u.user_id  = r.sender_id
     WHERE r.receiver_id = :me
     ORDER BY ' . $statusOrder . ', r.proposed_time ASC'
);
$incomingStmt->execute(['me' => $userId]);
$incoming = $incomingStmt->fetchAll();

$outgoingStmt = $pdo->prepare(
    'SELECT r.request_id, r.goal, r.proposed_time, r.status, r.created_at,
            s.name AS skill_name, u.name AS other_name, u.campus AS other_campus
     FROM session_requests r
     JOIN skills s ON s.skill_id = r.skill_id
     JOIN users  u ON u.user_id  = r.receiver_id
     WHERE r.sender_id = :me
     ORDER BY ' . $statusOrder . ', r.proposed_time ASC'
);
$outgoingStmt->execute(['me' => $userId]);
$outgoing = $outgoingStmt->fetchAll();

$pendingIncoming = count(array_filter($incoming, static fn ($r) => $r['status'] === 'pending'));

// Earliest allowed value for the datetime picker (client hint only).
$minPicker = (new DateTimeImmutable('+1 hour'))->format('Y-m-d\TH:i');
$maxPicker = (new DateTimeImmutable('+' . MAX_DAYS_AHEAD . ' days'))->format('Y-m-d\TH:i');

/**
 * Render one request row for either list.
 *
 * @param array<string,mixed> $r
 */
function render_request(array $r, string $direction): void
{
    $when = new DateTimeImmutable($r['proposed_time']);
    ?>
    <li class="request-card request-card--<?= e($r['status']) ?>">
        <div class="request-card__head">
            <div>
                <p class="label-md request-card__skill"><?= e($r['skill_name']) ?></p>
                <h3 class="card__title">
                    <?= $direction === 'incoming' ? 'From' : 'To' ?> <?= e($r['other_name']) ?>
                </h3>
                <?php if (!empty($r['other_campus'])): ?>
                    <p class="card__meta"><?= e($r['other_campus']) ?></p>
                <?php endif; ?>
            </div>
            <span class="chip chip--status-<?= e($r['status']) ?>"><?= e(enum_label($r['status'])) ?></span>
        </div>
        <p class="request-card__goal"><span class="visually-hidden">Goal: </span><?= e($r['goal']) ?></p>
        <p class="request-card__when">
            <time datetime="<?= e($when->format('Y-m-d\TH:i')) ?>"><?= e($when->format('D j M Y, g:i a')) ?></time>
        </p>
    </li>
    <?php
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert--error" role="alert"><?= e($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div class="page-header">
    <p class="label-md landing-eyebrow">Dashboard</p>
    <h1 class="headline-lg">Your sessions</h1>
    <p class="page-header__lede">Requests other students sent you, and requests you've sent.</p>
</div>

<?php if ($offer !== null): ?>
    <section class="request-form-panel" aria-labelledby="request-heading">
        <p class="label-md landing-eyebrow">New request</p>
        <h2 class="headline-md" id="request-heading">Request a <?= e($offer['skill_name']) ?> session with <?= e($offer['teacher_name']) ?></h2>
        <p class="card__meta request-form-panel__meta">
            <?= e(enum_label($offer['level'])) ?>
            &middot; <?= e($offer['mode'] === 'both' ? 'Online or in person' : enum_label($offer['mode'])) ?>
            <?php if (!empty($offer['availability'])): ?>&middot; Usually free: <?= e($offer['availability']) ?><?php endif; ?>
        </p>

        <?php if (isset($errors['form'])): ?>
            <div class="form-error-summary" role="alert"><?= e($errors['form']) ?></div>
        <?php endif; ?>

        <form method="post" action="/pages/dashboard.php" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="send_request">
            <input type="hidden" name="offer_id" value="<?= (int) $offer['user_skill_id'] ?>">

            <div class="field">
                <label for="goal">What do you want to achieve?</label>
                <textarea id="goal" name="goal" rows="3" required
                          minlength="<?= GOAL_MIN ?>" maxlength="<?= GOAL_MAX ?>"
                          placeholder="e.g. Understand loops and write my first small script"
                          aria-describedby="goal-hint<?= isset($errors['goal']) ? ' goal-error' : '' ?>"
                          <?= isset($errors['goal']) ? 'aria-invalid="true"' : '' ?>><?= e($values['goal']) ?></textarea>
                <p class="field-hint" id="goal-hint"><?= GOAL_MIN ?> to <?= GOAL_MAX ?> characters.</p>
                <?php if (isset($errors['goal'])): ?>
                    <p class="field-error" id="goal-error"><?= e($errors['goal']) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="proposed_time">Proposed date and time</label>
                <input type="datetime-local" id="proposed_time" name="proposed_time" required
                       value="<?= e($values['proposed_time']) ?>"
                       min="<?= e($minPicker) ?>" max="<?= e($maxPicker) ?>"
                       <?= isset($errors['proposed_time']) ? 'aria-invalid="true" aria-describedby="time-error"' : '' ?>>
                <?php if (isset($errors['proposed_time'])): ?>
                    <p class="field-error" id="time-error"><?= e($errors['proposed_time']) ?></p>
                <?php endif; ?>
            </div>

            <div class="btn-row">
                <button type="submit" class="btn btn--primary">Send request</button>
                <a href="/pages/dashboard.php" class="btn btn--tertiary">Cancel</a>
            </div>
        </form>
    </section>
<?php endif; ?>

<div class="request-columns">
    <section class="section request-section" id="incoming" aria-labelledby="incoming-heading">
        <h2 class="headline-md" id="incoming-heading">
            Incoming
            <?php if ($pendingIncoming > 0): ?>
                <span class="count-badge"><?= $pendingIncoming ?> new</span>
            <?php endif; ?>
        </h2>
        <?php if ($incoming === []): ?>
            <p class="empty-state">No one has asked you for a session yet. Add skills you can teach on your <a href="/pages/profile.php">profile</a> so others can find you.</p>
        <?php else: ?>
            <ul class="request-list">
                <?php foreach ($incoming as $r) { render_request($r, 'incoming'); } ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="section request-section" id="outgoing" aria-labelledby="outgoing-heading">
        <h2 class="headline-md" id="outgoing-heading">Sent by you</h2>
        <?php if ($outgoing === []): ?>
            <p class="empty-state">You haven't requested a session yet. Find someone on your <a href="/pages/matches.php">matches</a> page.</p>
        <?php else: ?>
            <ul class="request-list">
                <?php foreach ($outgoing as $r) { render_request($r, 'outgoing'); } ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
