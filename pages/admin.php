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
 * Write safety: POST only, CSRF token checked, target validated as an
 * existing *student* account (admins cannot suspend themselves or other
 * admins), status validated against the ENUM, parameterised UPDATE, then
 * Post/Redirect/Get so a refresh never re-submits.
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';

/* ------------------------------------------------------------------ */
/* Handle a moderation action                                          */
/* ------------------------------------------------------------------ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetId  = filter_var($_POST['user_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $newStatus = (string) ($_POST['status'] ?? '');
    $back      = '/pages/admin.php' . (!empty($_POST['return']) && is_string($_POST['return'])
        ? '?' . http_build_query(array_intersect_key(
            (array) json_decode($_POST['return'], true),
            ['q' => 1, 'status' => 1]
        ))
        : '');

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
    <p class="page-header__lede">Suspend or reactivate student accounts. Suspended students can't log in and are hidden from search.</p>
</div>

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
                        <td data-label="Campus"><?= e($u['campus'] ?: '—') ?></td>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
