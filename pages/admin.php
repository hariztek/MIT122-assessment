<?php
/**
 * pages/admin.php — moderation (SB-032, SB-033; FR-10).
 *
 * SB-032: role-restricted. require_admin() re-checks the role from the
 * database on every request, so a non-admin is redirected even when
 * typing the URL directly, and the Admin nav link is only rendered for
 * admins (includes/header.php).
 *
 * SB-033: admins can suspend or reactivate student accounts. A
 * suspended student cannot log in, is logged out on their next page
 * load (require_login()), and disappears from search results.
 *
 * SB-035 (FR-10): admins can hide or restore reviews (?view=reviews).
 * A hidden review disappears from the reviewee's dashboard and average;
 * its writer sees it marked "Hidden by an admin". Nothing is deleted, so
 * a mistaken hide can be undone.
 *
 * Write safety: POST only, CSRF token checked, target validated as an
 * existing *student* account (admins cannot suspend themselves or other
 * admins), status validated against the ENUM, parameterised UPDATE, then
 * Post/Redirect/Get so a refresh never re-submits.
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';

$view = (string) ($_GET['view'] ?? 'users');
if (!valid_enum($view, ['users', 'reviews'])) {
    $view = 'users';
}

/**
 * Rebuild a filtered admin URL from the JSON "return" field, keeping
 * only known keys so nothing arbitrary is echoed into the redirect.
 */
function admin_back_url(string $view): string
{
    $query = ['view' => $view === 'users' ? '' : $view];
    if (!empty($_POST['return']) && is_string($_POST['return'])) {
        $query += array_intersect_key((array) json_decode($_POST['return'], true), ['q' => 1, 'status' => 1]);
    }
    $query = array_filter($query, static fn ($v) => is_string($v) && $v !== '');
    return '/pages/admin.php' . ($query ? '?' . http_build_query($query) : '');
}

/* ------------------------------------------------------------------ */
/* Hide / restore a review (SB-035)                                     */
/* ------------------------------------------------------------------ */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['target'] ?? '') === 'review') {
    $reviewId  = filter_var($_POST['review_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $newStatus = (string) ($_POST['status'] ?? '');
    $back      = admin_back_url('reviews');

    if (!csrf_valid()) {
        $_SESSION['flash_error'] = 'Your session expired. Please try again.';
        redirect($back);
    }
    if ($reviewId === false || !valid_enum($newStatus, ['visible', 'hidden'])) {
        $_SESSION['flash_error'] = 'That moderation request was not valid.';
        redirect($back);
    }

    $update = $pdo->prepare('UPDATE reviews SET status = :status WHERE review_id = :id');
    $update->execute(['status' => $newStatus, 'id' => $reviewId]);

    $exists = $pdo->prepare('SELECT COUNT(*) FROM reviews WHERE review_id = :id');
    $exists->execute(['id' => $reviewId]);
    if ((int) $exists->fetchColumn() === 0) {
        $_SESSION['flash_error'] = 'That review no longer exists.';
    } else {
        $_SESSION['flash_success'] = $newStatus === 'hidden'
            ? 'Review hidden. The student it was about can no longer see it.'
            : 'Review restored and visible again.';
    }
    redirect($back);
}

/* ------------------------------------------------------------------ */
/* Suspend / reactivate an account (SB-033)                             */
/* ------------------------------------------------------------------ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetId  = filter_var($_POST['user_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $newStatus = (string) ($_POST['status'] ?? '');
    $back      = admin_back_url('users');

    if (!csrf_valid()) {
        $_SESSION['flash_error'] = 'Your session expired. Please try again.';
        redirect($back);
    }

    if ($targetId === false || !valid_enum($newStatus, ['active', 'suspended'])) {
        $_SESSION['flash_error'] = 'That moderation request was not valid.';
        redirect($back);
    }

    $find = $pdo->prepare('SELECT user_id, name, role FROM users WHERE user_id = :id LIMIT 1');
    $find->execute(['id' => $targetId]);
    $target = $find->fetch();

    if (!$target) {
        $_SESSION['flash_error'] = 'That account no longer exists.';
    } elseif ($target['role'] !== 'student' || (int) $target['user_id'] === current_user_id()) {
        $_SESSION['flash_error'] = 'Admin accounts cannot be suspended from here.';
    } else {
        $update = $pdo->prepare('UPDATE users SET status = :status WHERE user_id = :id AND role = \'student\'');
        $update->execute(['status' => $newStatus, 'id' => $targetId]);
        $_SESSION['flash_success'] = $newStatus === 'suspended'
            ? $target['name'] . ' has been suspended.'
            : $target['name'] . ' has been reactivated.';
    }

    redirect($back);
}

/* ------------------------------------------------------------------ */
/* List accounts                                                       */
/* ------------------------------------------------------------------ */

$keyword = trim((string) ($_GET['q'] ?? ''));
$keyword = mb_substr($keyword, 0, 100);

$statusFilter = (string) ($_GET['status'] ?? '');
if (!valid_enum($statusFilter, ['active', 'suspended'])) {
    $statusFilter = '';
}

$where  = ['1 = 1'];
$params = [];

if ($keyword !== '') {
    $like             = '%' . addcslashes($keyword, '%_\\') . '%';
    $where[]          = '(u.name LIKE :kw1 OR u.email LIKE :kw2 OR u.campus LIKE :kw3)';
    $params[':kw1']   = $like;
    $params[':kw2']   = $like;
    $params[':kw3']   = $like;
}

if ($statusFilter !== '') {
    $where[]           = 'u.status = :status';
    $params[':status'] = $statusFilter;
}

$list = $pdo->prepare(
    'SELECT u.user_id, u.name, u.email, u.campus, u.role, u.status, u.created_at,
            COUNT(us.user_skill_id) AS skill_count
     FROM users u
     LEFT JOIN user_skills us ON us.user_id = u.user_id
     WHERE ' . implode(' AND ', $where) . '
     GROUP BY u.user_id
     ORDER BY u.role = \'admin\' DESC, u.status = \'suspended\' DESC, u.name ASC'
);
$list->execute($params);
$users = $list->fetchAll();

$stats = $pdo->query(
    'SELECT COUNT(*) AS total,
            SUM(status = \'active\')    AS active,
            SUM(status = \'suspended\') AS suspended
     FROM users WHERE role = \'student\''
)->fetch();

$returnQuery = json_encode(array_filter(['q' => $keyword, 'status' => $statusFilter]));

/* ------------------------------------------------------------------ */
/* List reviews (SB-035)                                                */
/* ------------------------------------------------------------------ */

$reviews = [];
$reviewStats = $pdo->query(
    'SELECT COUNT(*) AS total, SUM(status = \'visible\') AS visible, SUM(status = \'hidden\') AS hidden FROM reviews'
)->fetch();

if ($view === 'reviews') {
    $rWhere  = ['1 = 1'];
    $rParams = [];
    if ($keyword !== '') {
        $like = '%' . addcslashes($keyword, '%_\\') . '%';
        $rWhere[] = '(rv.name LIKE :kw1 OR re.name LIKE :kw2 OR r.comment LIKE :kw3)';
        $rParams += [':kw1' => $like, ':kw2' => $like, ':kw3' => $like];
    }
    $reviewStatus = (string) ($_GET['status'] ?? '');
    if (!valid_enum($reviewStatus, ['visible', 'hidden'])) {
        $reviewStatus = '';
    }
    if ($reviewStatus !== '') {
        $rWhere[] = 'r.status = :rstatus';
        $rParams[':rstatus'] = $reviewStatus;
    }
    $list = $pdo->prepare(
        'SELECT r.review_id, r.rating, r.comment, r.status, r.created_at,
                rv.name AS reviewer_name, re.name AS reviewee_name, s.name AS skill_name
         FROM reviews r
         JOIN users rv            ON rv.user_id    = r.reviewer_id
         JOIN users re            ON re.user_id    = r.reviewee_id
         JOIN sessions ses        ON ses.session_id = r.session_id
         JOIN session_requests sr ON sr.request_id  = ses.request_id
         JOIN skills s            ON s.skill_id     = sr.skill_id
         WHERE ' . implode(' AND ', $rWhere) . '
         ORDER BY r.status = \'hidden\' DESC, r.rating ASC, r.created_at DESC'
    );
    $list->execute($rParams);
    $reviews = $list->fetchAll();
}

$pageTitle = 'Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert--error" role="alert"><?= e($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div class="page-header">
    <p class="label-md landing-eyebrow">Moderation</p>
    <h1 class="headline-lg">Admin</h1>
    <p class="page-header__lede">Moderate student accounts and reviews. Nothing is deleted, so every action can be undone.</p>
</div>

<nav class="admin-tabs" aria-label="Admin sections">
    <a href="/pages/admin.php" class="filter-chip<?= $view === 'users' ? ' is-active' : '' ?>"<?= $view === 'users' ? ' aria-current="page"' : '' ?>>Accounts</a>
    <a href="/pages/admin.php?view=reviews" class="filter-chip<?= $view === 'reviews' ? ' is-active' : '' ?>"<?= $view === 'reviews' ? ' aria-current="page"' : '' ?>>
        Reviews<?php if ((int) $reviewStats['hidden'] > 0): ?> <span class="card__meta">(<?= (int) $reviewStats['hidden'] ?> hidden)</span><?php endif; ?>
    </a>
</nav>

<?php if ($view === 'users'): ?>

<ul class="stat-row" aria-label="Student account totals">
    <li class="stat"><span class="stat__num"><?= (int) $stats['total'] ?></span><span class="stat__label">Students</span></li>
    <li class="stat"><span class="stat__num"><?= (int) $stats['active'] ?></span><span class="stat__label">Active</span></li>
    <li class="stat stat--warn"><span class="stat__num"><?= (int) $stats['suspended'] ?></span><span class="stat__label">Suspended</span></li>
</ul>

<form class="search-form admin-filter" method="get" action="/pages/admin.php" role="search">
    <div class="search-form__keyword">
        <label for="q">Find an account</label>
        <input type="search" id="q" name="q" value="<?= e($keyword) ?>" maxlength="100" placeholder="Name, email or campus">
    </div>
    <div>
        <label for="status">Status</label>
        <select id="status" name="status" data-autosubmit>
            <option value="">All</option>
            <option value="active"<?= $statusFilter === 'active' ? ' selected' : '' ?>>Active</option>
            <option value="suspended"<?= $statusFilter === 'suspended' ? ' selected' : '' ?>>Suspended</option>
        </select>
    </div>
    <div class="search-form__actions">
        <button type="submit" class="btn btn--primary">Filter</button>
        <?php if ($keyword !== '' || $statusFilter !== ''): ?>
            <a href="/pages/admin.php" class="btn btn--tertiary">Clear</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($users === []): ?>
    <p class="empty-state">No accounts match that filter.</p>
<?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <caption class="visually-hidden">User accounts</caption>
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Campus</th>
                    <th scope="col">Skills</th>
                    <th scope="col">Joined</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="visually-hidden">Action</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php
                    $isSelf      = (int) $u['user_id'] === current_user_id();
                    $isAdmin     = $u['role'] === 'admin';
                    $isSuspended = $u['status'] === 'suspended';
                    ?>
                    <tr class="<?= $isSuspended ? 'is-suspended' : '' ?>">
                        <th scope="row" data-label="Name">
                            <?= e($u['name']) ?>
                            <?php if ($isAdmin): ?><span class="chip chip--sm">Admin</span><?php endif; ?>
                            <?php if ($isSelf): ?><span class="card__meta">(you)</span><?php endif; ?>
                        </th>
                        <td data-label="Email"><?= e($u['email']) ?></td>
                        <td data-label="Campus"><?= e($u['campus'] ?: 'Not set') ?></td>
                        <td data-label="Skills"><?= (int) $u['skill_count'] ?></td>
                        <td data-label="Joined"><?= e(date('j M Y', strtotime($u['created_at']))) ?></td>
                        <td data-label="Status">
                            <span class="chip <?= $isSuspended ? 'chip--status-declined' : 'chip--status-accepted' ?>">
                                <?= $isSuspended ? 'Suspended' : 'Active' ?>
                            </span>
                        </td>
                        <td class="admin-table__action">
                            <?php if (!$isAdmin): ?>
                                <form method="post" action="/pages/admin.php" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
                                    <input type="hidden" name="return" value="<?= e($returnQuery) ?>">
                                    <?php if ($isSuspended): ?>
                                        <input type="hidden" name="status" value="active">
                                        <button type="submit" class="btn btn--secondary btn--sm">Reactivate<span class="visually-hidden"> <?= e($u['name']) ?></span></button>
                                    <?php else: ?>
                                        <input type="hidden" name="status" value="suspended">
                                        <button type="submit" class="btn btn--danger btn--sm" data-confirm="Suspend <?= e($u['name']) ?>? They will be logged out and hidden from search.">Suspend<span class="visually-hidden"> <?= e($u['name']) ?></span></button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php else: /* ---------------- Reviews view ---------------- */ ?>

<ul class="stat-row" aria-label="Review totals">
    <li class="stat"><span class="stat__num"><?= (int) $reviewStats['total'] ?></span><span class="stat__label">Reviews</span></li>
    <li class="stat"><span class="stat__num"><?= (int) $reviewStats['visible'] ?></span><span class="stat__label">Visible</span></li>
    <li class="stat stat--warn"><span class="stat__num"><?= (int) $reviewStats['hidden'] ?></span><span class="stat__label">Hidden</span></li>
</ul>

<form class="search-form admin-filter" method="get" action="/pages/admin.php" role="search">
    <input type="hidden" name="view" value="reviews">
    <div class="search-form__keyword">
        <label for="q">Find a review</label>
        <input type="search" id="q" name="q" value="<?= e($keyword) ?>" maxlength="100" placeholder="Reviewer, reviewee or comment text">
    </div>
    <div>
        <label for="status">Status</label>
        <select id="status" name="status" data-autosubmit>
            <option value="">All</option>
            <option value="visible"<?= ($_GET['status'] ?? '') === 'visible' ? ' selected' : '' ?>>Visible</option>
            <option value="hidden"<?= ($_GET['status'] ?? '') === 'hidden' ? ' selected' : '' ?>>Hidden</option>
        </select>
    </div>
    <div class="search-form__actions">
        <button type="submit" class="btn btn--primary">Filter</button>
        <?php if ($keyword !== '' || !empty($_GET['status'])): ?>
            <a href="/pages/admin.php?view=reviews" class="btn btn--tertiary">Clear</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($reviews === []): ?>
    <p class="empty-state">No reviews match that filter.</p>
<?php else: ?>
    <?php $reviewReturn = json_encode(array_filter(['q' => $keyword, 'status' => (string) ($_GET['status'] ?? '')])); ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <caption class="visually-hidden">Reviews</caption>
            <thead>
                <tr>
                    <th scope="col">Review</th>
                    <th scope="col">Skill</th>
                    <th scope="col">Rating</th>
                    <th scope="col">Comment</th>
                    <th scope="col">Date</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="visually-hidden">Action</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reviews as $rv): ?>
                    <?php $isHidden = $rv['status'] === 'hidden'; ?>
                    <tr class="<?= $isHidden ? 'is-suspended' : '' ?>">
                        <th scope="row" data-label="Review"><?= e($rv['reviewer_name']) ?> <span class="card__meta">&rarr;</span> <?= e($rv['reviewee_name']) ?></th>
                        <td data-label="Skill"><?= e($rv['skill_name']) ?></td>
                        <td data-label="Rating"><span class="stars" role="img" aria-label="<?= (int) $rv['rating'] ?> out of 5 stars"><?= str_repeat('★', (int) $rv['rating']) ?><span class="stars__off"><?= str_repeat('★', 5 - (int) $rv['rating']) ?></span></span></td>
                        <td data-label="Comment" class="admin-table__comment"><?= $rv['comment'] !== null && $rv['comment'] !== '' ? e(mb_strimwidth($rv['comment'], 0, 140, '…')) : '<span class="card__meta">No comment</span>' ?></td>
                        <td data-label="Date"><?= e(date('j M Y', strtotime($rv['created_at']))) ?></td>
                        <td data-label="Status">
                            <span class="chip <?= $isHidden ? 'chip--status-declined' : 'chip--status-accepted' ?>"><?= $isHidden ? 'Hidden' : 'Visible' ?></span>
                        </td>
                        <td class="admin-table__action">
                            <form method="post" action="/pages/admin.php?view=reviews" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="target" value="review">
                                <input type="hidden" name="review_id" value="<?= (int) $rv['review_id'] ?>">
                                <input type="hidden" name="return" value="<?= e($reviewReturn) ?>">
                                <?php if ($isHidden): ?>
                                    <input type="hidden" name="status" value="visible">
                                    <button type="submit" class="btn btn--secondary btn--sm">Restore<span class="visually-hidden"> review by <?= e($rv['reviewer_name']) ?></span></button>
                                <?php else: ?>
                                    <input type="hidden" name="status" value="hidden">
                                    <button type="submit" class="btn btn--danger btn--sm" data-confirm="Hide this review by <?= e($rv['reviewer_name']) ?>? <?= e($rv['reviewee_name']) ?> will no longer see it.">Hide<span class="visually-hidden"> review by <?= e($rv['reviewer_name']) ?></span></button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
