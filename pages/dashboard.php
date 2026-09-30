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
 * requests are listed separately.
 *
 * SB-028 / FR-08: the RECEIVER of a PENDING request can accept or
 * decline it. The UPDATE itself is guarded (request_id + receiver_id +
 * status = 'pending'), so a forged, repeated or stale action changes
 * nothing. A request whose proposed time has already passed can only be
 * declined.
 *
 * SB-029 / FR-08: either participant (sender or receiver) of an
 * ACCEPTED request can cancel it, or mark it completed once its proposed
 * time has passed. Completing sets the request to 'completed' and
 * inserts exactly one `sessions` row, inside one transaction (the
 * UNIQUE key on sessions.request_id is the backstop). Reviews (SB-030)
 * are only possible against that sessions row.
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
/* Accept / decline an incoming request (POST)                          */
/* ------------------------------------------------------------------ */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'respond') {
    $requestId = filter_var($_POST['request_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $decision  = (string) ($_POST['decision'] ?? '');

    if (!csrf_valid()) {
        $_SESSION['flash_error'] = 'Your session expired. Please try again.';
        redirect('/pages/dashboard.php#incoming');
    }
    if ($requestId === false || !valid_enum($decision, ['accept', 'decline'])) {
        $_SESSION['flash_error'] = 'That action was not valid.';
        redirect('/pages/dashboard.php#incoming');
    }

    $find = $pdo->prepare(
        'SELECT r.request_id, r.proposed_time, r.status, u.name AS sender_name, s.name AS skill_name
         FROM session_requests r
         JOIN users  u ON u.user_id  = r.sender_id
         JOIN skills s ON s.skill_id = r.skill_id
         WHERE r.request_id = :id AND r.receiver_id = :me
         LIMIT 1'
    );
    $find->execute(['id' => $requestId, 'me' => $userId]);
    $req = $find->fetch();

    if (!$req) {
        // Not found, or not addressed to this user: same message either way.
        $_SESSION['flash_error'] = 'That request could not be found.';
    } elseif ($req['status'] !== 'pending') {
        $_SESSION['flash_error'] = 'That request has already been ' . $req['status'] . '.';
    } elseif ($decision === 'accept' && new DateTimeImmutable($req['proposed_time']) <= new DateTimeImmutable('now')) {
        $_SESSION['flash_error'] = 'The proposed time has already passed, so this request can only be declined.';
    } else {
        $newStatus = $decision === 'accept' ? 'accepted' : 'declined';
        $update = $pdo->prepare(
            'UPDATE session_requests SET status = :status
             WHERE request_id = :id AND receiver_id = :me AND status = \'pending\''
        );
        $update->execute(['status' => $newStatus, 'id' => $requestId, 'me' => $userId]);

        if ($update->rowCount() === 1) {
            $_SESSION['flash_success'] = $newStatus === 'accepted'
                ? 'You accepted ' . $req['sender_name'] . '\'s ' . $req['skill_name'] . ' session.'
                : 'You declined ' . $req['sender_name'] . '\'s ' . $req['skill_name'] . ' request.';
        } else {
            $_SESSION['flash_error'] = 'That request changed before your reply was saved. Please check it again.';
        }
    }
    redirect('/pages/dashboard.php#incoming');
}

/* ------------------------------------------------------------------ */
/* Cancel / complete an accepted request (POST)                         */
/* ------------------------------------------------------------------ */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'close') {
    $requestId = filter_var($_POST['request_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $change    = (string) ($_POST['change'] ?? '');
    $back      = '/pages/dashboard.php' . (($_POST['from'] ?? '') === 'outgoing' ? '#outgoing' : '#incoming');

    if (!csrf_valid()) {
        $_SESSION['flash_error'] = 'Your session expired. Please try again.';
        redirect($back);
    }
    if ($requestId === false || !valid_enum($change, ['cancel', 'complete'])) {
        $_SESSION['flash_error'] = 'That action was not valid.';
        redirect($back);
    }

    // Only a participant can act: sender OR receiver.
    $find = $pdo->prepare(
        'SELECT r.request_id, r.proposed_time, r.status, r.sender_id, s.name AS skill_name,
                CASE WHEN r.sender_id = :me1 THEN rcv.name ELSE snd.name END AS other_name
         FROM session_requests r
         JOIN skills s  ON s.skill_id  = r.skill_id
         JOIN users snd ON snd.user_id = r.sender_id
         JOIN users rcv ON rcv.user_id = r.receiver_id
         WHERE r.request_id = :id AND (r.sender_id = :me2 OR r.receiver_id = :me3)
         LIMIT 1'
    );
    $find->execute(['id' => $requestId, 'me1' => $userId, 'me2' => $userId, 'me3' => $userId]);
    $req = $find->fetch();

    if (!$req) {
        $_SESSION['flash_error'] = 'That request could not be found.';
    } elseif ($req['status'] !== 'accepted') {
        $_SESSION['flash_error'] = 'Only accepted sessions can be ' . ($change === 'cancel' ? 'cancelled' : 'completed')
            . '. This one is ' . $req['status'] . '.';
    } elseif ($change === 'complete' && new DateTimeImmutable($req['proposed_time']) > new DateTimeImmutable('now')) {
        $_SESSION['flash_error'] = 'You can mark this session completed after its scheduled time.';
    } elseif ($change === 'cancel') {
        $update = $pdo->prepare(
            'UPDATE session_requests SET status = \'cancelled\'
             WHERE request_id = :id AND status = \'accepted\'
               AND (sender_id = :me1 OR receiver_id = :me2)'
        );
        $update->execute(['id' => $requestId, 'me1' => $userId, 'me2' => $userId]);
        $_SESSION[$update->rowCount() === 1 ? 'flash_success' : 'flash_error'] = $update->rowCount() === 1
            ? 'Your ' . $req['skill_name'] . ' session with ' . $req['other_name'] . ' was cancelled.'
            : 'That session changed before your update was saved. Please check it again.';
    } else {
        // Complete: status change + sessions row succeed or fail together.
        try {
            $pdo->beginTransaction();
            $update = $pdo->prepare(
                'UPDATE session_requests SET status = \'completed\'
                 WHERE request_id = :id AND status = \'accepted\'
                   AND (sender_id = :me1 OR receiver_id = :me2)'
            );
            $update->execute(['id' => $requestId, 'me1' => $userId, 'me2' => $userId]);

            if ($update->rowCount() !== 1) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = 'That session changed before your update was saved. Please check it again.';
            } else {
                $pdo->prepare('INSERT INTO sessions (request_id) VALUES (:id)')->execute(['id' => $requestId]);
                $pdo->commit();
                $_SESSION['flash_success'] = 'Marked your ' . $req['skill_name'] . ' session with ' . $req['other_name'] . ' as completed.';
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Complete session failed: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Something went wrong saving that. Please try again.';
        }
    }
    redirect($back);
}

/* ------------------------------------------------------------------ */
/* Load incoming + outgoing requests                                    */
/* ------------------------------------------------------------------ */

$statusOrder = 'FIELD(r.status, \'pending\', \'accepted\', \'completed\', \'declined\', \'cancelled\')';

$incomingStmt = $pdo->prepare(
    'SELECT r.request_id, r.goal, r.proposed_time, r.status, r.created_at,
            s.name AS skill_name, u.name AS other_name, u.campus AS other_campus,
            ses.completed_at
     FROM session_requests r
     JOIN skills s ON s.skill_id = r.skill_id
     LEFT JOIN sessions ses ON ses.request_id = r.request_id
     JOIN users  u ON u.user_id  = r.sender_id
     WHERE r.receiver_id = :me
     ORDER BY ' . $statusOrder . ', r.proposed_time ASC'
);
$incomingStmt->execute(['me' => $userId]);
$incoming = $incomingStmt->fetchAll();

$outgoingStmt = $pdo->prepare(
    'SELECT r.request_id, r.goal, r.proposed_time, r.status, r.created_at,
            s.name AS skill_name, u.name AS other_name, u.campus AS other_campus,
            ses.completed_at
     FROM session_requests r
     JOIN skills s ON s.skill_id = r.skill_id
     LEFT JOIN sessions ses ON ses.request_id = r.request_id
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

        <?php if ($r['status'] === 'pending' && $direction === 'incoming'): ?>
            <?php $isPast = $when <= new DateTimeImmutable('now'); ?>
            <form method="post" action="/pages/dashboard.php" class="btn-row request-card__actions">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="respond">
                <input type="hidden" name="request_id" value="<?= (int) $r['request_id'] ?>">
                <?php if (!$isPast): ?>
                    <button type="submit" name="decision" value="accept" class="btn btn--primary btn--sm">Accept<span class="visually-hidden"> request from <?= e($r['other_name']) ?></span></button>
                <?php endif; ?>
                <button type="submit" name="decision" value="decline" class="btn btn--danger btn--sm"
                        data-confirm="Decline <?= e($r['other_name']) ?>'s <?= e($r['skill_name']) ?> request? This can't be undone.">Decline<span class="visually-hidden"> request from <?= e($r['other_name']) ?></span></button>
                <?php if ($isPast): ?>
                    <span class="card__meta">This time has passed, so it can only be declined.</span>
                <?php endif; ?>
            </form>
        <?php elseif ($r['status'] === 'pending'): ?>
            <p class="request-card__note">Waiting for <?= e($r['other_name']) ?> to reply.</p>
        <?php elseif ($r['status'] === 'accepted'): ?>
            <?php $isPast = $when <= new DateTimeImmutable('now'); ?>
            <p class="request-card__note request-card__note--ok">
                <?= $isPast ? 'Did the session happen? Mark it completed so you can both leave a review.' : 'Confirmed. Meet at the time above.' ?>
            </p>
            <form method="post" action="/pages/dashboard.php" class="btn-row request-card__actions">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="close">
                <input type="hidden" name="from" value="<?= e($direction) ?>">
                <input type="hidden" name="request_id" value="<?= (int) $r['request_id'] ?>">
                <?php if ($isPast): ?>
                    <button type="submit" name="change" value="complete" class="btn btn--primary btn--sm">Mark as completed<span class="visually-hidden">: <?= e($r['skill_name']) ?> with <?= e($r['other_name']) ?></span></button>
                <?php else: ?>
                    <span class="card__meta">You can mark it completed after the session time.</span>
                <?php endif; ?>
                <button type="submit" name="change" value="cancel" class="btn btn--danger btn--sm"
                        data-confirm="Cancel your <?= e($r['skill_name']) ?> session with <?= e($r['other_name']) ?>? This can't be undone.">Cancel session<span class="visually-hidden">: <?= e($r['skill_name']) ?> with <?= e($r['other_name']) ?></span></button>
            </form>
        <?php elseif ($r['status'] === 'completed'): ?>
            <p class="request-card__note request-card__note--ok">
                Completed<?= !empty($r['completed_at']) ? ' on ' . e((new DateTimeImmutable($r['completed_at']))->format('j M Y')) : '' ?>.
            </p>
        <?php endif; ?>
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
