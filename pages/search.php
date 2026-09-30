<?php
/**
 * pages/search.php — browse/filter skills (SB-020, SB-021, SB-022).
 *
 * FR-05: keyword and category search across skills. Other students'
 * active skill entries are listed as cards; the current user's own
 * entries, hidden entries and suspended accounts are always excluded.
 *
 * Filters arrive via GET (so results are bookmarkable and the landing
 * page's category pills can deep-link here, e.g. ?category=technology).
 * Every filter is validated server-side before it reaches SQL: ENUM
 * filters must be in their allowed list, the keyword is length-capped,
 * and all values are bound as PDO parameters — nothing is concatenated
 * into the query string except fixed, code-defined SQL fragments.
 *
 * Login is required because results show students' names, campus and
 * availability (see .agent/DECISIONS.md).
 */
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/db.php';

$userId = current_user_id();

$categories = ['technology', 'creative', 'languages', 'career_study', 'practical'];
$perPage    = 12;

/* ------------------------------------------------------------------ */
/* Read + validate filters                                             */
/* ------------------------------------------------------------------ */

$errors = [];

$keyword = trim((string) ($_GET['q'] ?? ''));
if (!valid_length($keyword, 0, 100)) {
    $errors[] = 'Keep your search to 100 characters or fewer.';
    $keyword  = mb_substr($keyword, 0, 100);
}

// Invalid ENUM values are ignored (treated as "any") rather than
// erroring, so a tampered or stale URL still shows sensible results.
$category = (string) ($_GET['category'] ?? '');
if (!valid_enum($category, $categories)) {
    $category = '';
}

// Default to "offer": the common case is "who can teach me X?".
$type = (string) ($_GET['type'] ?? 'offer');
if (!valid_enum($type, ['offer', 'want', 'all'])) {
    $type = 'offer';
}

$mode = (string) ($_GET['mode'] ?? '');
if (!valid_enum($mode, ['online', 'in_person'])) {
    $mode = '';
}

$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $page === false ? 1 : $page;

/* ------------------------------------------------------------------ */
/* Build the parameterised WHERE clause                                */
/* ------------------------------------------------------------------ */

$where  = [
    'us.status = \'active\'',
    'u.status = \'active\'',
    'us.user_id <> :me',
];
$params = [':me' => $userId];

if ($keyword !== '') {
    // Escape LIKE wildcards so "%" or "_" typed by the user match literally.
    $like = '%' . addcslashes($keyword, '%_\\') . '%';
    // Native prepares can't reuse a named placeholder, hence :kw1/:kw2.
    $where[]        = '(s.name LIKE :kw1 OR us.description LIKE :kw2)';
    $params[':kw1'] = $like;
    $params[':kw2'] = $like;
}

if ($category !== '') {
    $where[]             = 's.category = :category';
    $params[':category'] = $category;
}

if ($type !== 'all') {
    $where[]         = 'us.type = :type';
    $params[':type'] = $type;
}

if ($mode !== '') {
    // An entry marked "both" suits either preference.
    $where[]         = '(us.mode = :mode OR us.mode = \'both\')';
    $params[':mode'] = $mode;
}

$from = 'FROM user_skills us
         JOIN skills s ON s.skill_id = us.skill_id
         JOIN users  u ON u.user_id  = us.user_id
         WHERE ' . implode(' AND ', $where);

/* ------------------------------------------------------------------ */
/* Count + fetch current page                                          */
/* ------------------------------------------------------------------ */

$countStmt = $pdo->prepare('SELECT COUNT(*) ' . $from);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$listStmt = $pdo->prepare(
    'SELECT us.user_skill_id, us.type, us.level, us.mode, us.availability,
            us.description, s.name AS skill_name, s.category,
            u.user_id, u.name AS student_name, u.campus
     ' . $from . '
     ORDER BY s.name ASC, u.name ASC
     LIMIT :limit OFFSET :offset'
);
foreach ($params as $key => $value) {
    $listStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$results = $listStmt->fetchAll();

$hasFilters = $keyword !== '' || $category !== '' || $mode !== '' || $type !== 'offer';

/**
 * Build a search URL keeping the current filters, overriding some.
 *
 * @param array<string,string|int> $overrides
 */
function search_url(array $current, array $overrides): string
{
    $query = array_filter(
        array_merge($current, $overrides),
        static fn ($v) => $v !== '' && $v !== null
    );
    return '/pages/search.php' . ($query ? '?' . http_build_query($query) : '');
}

$currentQuery = [
    'q'        => $keyword,
    'category' => $category,
    'type'     => $type === 'offer' ? '' : $type,
    'mode'     => $mode,
];

$pageTitle = 'Search';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <p class="label-md landing-eyebrow">Browse</p>
    <h1 class="headline-lg">Search skills</h1>
    <p class="page-header__lede">Find students who can teach what you want to learn, or who want to learn what you can teach.</p>
</div>

<form class="search-form" method="get" action="/pages/search.php" role="search">
    <div class="search-form__keyword">
        <label for="q">Keyword</label>
        <input type="search" id="q" name="q" value="<?= e($keyword) ?>"
               maxlength="100" placeholder="e.g. Python, photography, resume"
               <?= $errors ? 'aria-invalid="true" aria-describedby="q-error"' : '' ?>>
        <?php foreach ($errors as $error): ?>
            <p class="field-error" id="q-error"><?= e($error) ?></p>
        <?php endforeach; ?>
    </div>

    <div>
        <label for="category">Category</label>
        <select id="category" name="category" data-autosubmit>
            <option value="">All categories</option>
            <?php enum_select_options($categories, $category); ?>
        </select>
    </div>

    <div>
        <label for="type">Showing</label>
        <select id="type" name="type" data-autosubmit>
            <option value="offer"<?= $type === 'offer' ? ' selected' : '' ?>>Students who teach it</option>
            <option value="want"<?= $type === 'want' ? ' selected' : '' ?>>Students who want to learn it</option>
            <option value="all"<?= $type === 'all' ? ' selected' : '' ?>>Both</option>
        </select>
    </div>

    <div>
        <label for="mode">Mode</label>
        <select id="mode" name="mode" data-autosubmit>
            <option value="">Any mode</option>
            <option value="online"<?= $mode === 'online' ? ' selected' : '' ?>>Online</option>
            <option value="in_person"<?= $mode === 'in_person' ? ' selected' : '' ?>>In person</option>
        </select>
    </div>

    <div class="search-form__actions">
        <button type="submit" class="btn btn--primary">Search</button>
        <?php if ($hasFilters): ?>
            <a href="/pages/search.php" class="btn btn--tertiary">Clear</a>
        <?php endif; ?>
    </div>
</form>

<p class="search-summary" role="status">
    <?php if ($total === 0): ?>
        No skills found.
    <?php else: ?>
        <strong><?= $total ?></strong> <?= $total === 1 ? 'result' : 'results' ?>
        <?php if ($keyword !== ''): ?>for &ldquo;<?= e($keyword) ?>&rdquo;<?php endif; ?>
        <?php if ($category !== ''): ?>in <?= e(enum_label($category)) ?><?php endif; ?>
        <?php if ($totalPages > 1): ?>&middot; page <?= $page ?> of <?= $totalPages ?><?php endif; ?>
    <?php endif; ?>
</p>

<?php if ($total === 0): ?>
    <div class="card search-empty">
        <h2 class="card__title">Nothing matches yet</h2>
        <p class="card__meta">Try a broader keyword, another category, or switch &ldquo;Showing&rdquo; to Both. The catalogue only lists skills other students have added to their profiles.</p>
        <div class="btn-row">
            <a href="/pages/search.php" class="btn btn--secondary btn--sm">Clear filters</a>
            <a href="/pages/profile.php" class="btn btn--tertiary">Add your own skills &rarr;</a>
        </div>
    </div>
<?php else: ?>
    <ul class="result-grid">
        <?php foreach ($results as $row): ?>
            <li class="result-card">
                <div class="result-card__top">
                    <span class="chip<?= $row['type'] === 'offer' ? '' : ' chip--want' ?>">
                        <?= $row['type'] === 'offer' ? 'Teaches' : 'Wants to learn' ?>
                    </span>
                    <span class="card__meta"><?= e(enum_label($row['category'])) ?></span>
                </div>
                <h2 class="card__title"><?= e($row['skill_name']) ?></h2>
                <p class="result-card__student">
                    <?= e($row['student_name']) ?>
                    <?php if (!empty($row['campus'])): ?>
                        <span class="card__meta">&middot; <?= e($row['campus']) ?></span>
                    <?php endif; ?>
                </p>
                <?php if (!empty($row['description'])): ?>
                    <p class="result-card__desc"><?= e(mb_strimwidth($row['description'], 0, 160, '…')) ?></p>
                <?php endif; ?>
                <dl class="result-card__facts">
                    <div><dt>Level</dt><dd><?= e(enum_label($row['level'])) ?></dd></div>
                    <div><dt>Mode</dt><dd><?= e($row['mode'] === 'both' ? 'Online or in person' : enum_label($row['mode'])) ?></dd></div>
                    <?php if (!empty($row['availability'])): ?>
                        <div><dt>Available</dt><dd><?= e($row['availability']) ?></dd></div>
                    <?php endif; ?>
                </dl>
                <?php if ($row['type'] === 'offer'): ?>
                    <a href="/pages/dashboard.php?new=<?= (int) $row['user_skill_id'] ?>" class="btn btn--secondary btn--sm result-card__cta">Request session<span class="visually-hidden"> with <?= e($row['student_name']) ?> for <?= e($row['skill_name']) ?></span></a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Search results pages">
            <?php if ($page > 1): ?>
                <a class="btn btn--secondary btn--sm" href="<?= e(search_url($currentQuery, ['page' => $page - 1])) ?>" rel="prev">&larr; Previous</a>
            <?php endif; ?>
            <span class="card__meta">Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a class="btn btn--secondary btn--sm" href="<?= e(search_url($currentQuery, ['page' => $page + 1])) ?>" rel="next">Next &rarr;</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
