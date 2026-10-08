<?php
/**
 * includes/header.php
 *
 * Shared page header: opens the HTML document, loads the stylesheet,
 * and renders the site navigation. Every page includes this before
 * any of its own output, and closes out with includes/footer.php.
 *
 * Usage from a page:
 *     <?php
 *     $pageTitle = 'Search skills';
 *     require_once __DIR__ . '/../includes/header.php'; // from pages/
 *     // or require_once __DIR__ . '/includes/header.php'; // from root
 *     ?>
 *     ... page content ...
 *     <?php require_once __DIR__ . '/../includes/footer.php'; ?>
 *
 * A page may set $pageTitle before requiring this file; it falls back
 * to a sensible default otherwise. All links use root-relative paths
 * (starting with /) so they work identically whether the including
 * page lives at the repo root or one level down in pages/ — this is
 * intentionally more robust than "../" relative paths, since it never
 * breaks based on the caller's depth.
 */

require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle  = $pageTitle ?? 'Student SkillBridge';
$isLoggedIn = isset($_SESSION['user_id']);

// Logged-in account details for the account menu, read fresh from the
// database so a renamed profile shows immediately, plus the nav badge
// count of incoming requests still waiting for this user's reply.
$pendingRequests = 0;
$account         = null;
if ($isLoggedIn) {
    require_once __DIR__ . '/db.php';
    $accountStmt = $pdo->prepare(
        'SELECT u.name, u.email, u.role,
                (SELECT COUNT(*) FROM session_requests r
                  WHERE r.receiver_id = u.user_id AND r.status = \'pending\') AS pending
         FROM users u WHERE u.user_id = :me LIMIT 1'
    );
    $accountStmt->execute(['me' => (int) $_SESSION['user_id']]);
    $account = $accountStmt->fetch() ?: null;
    $pendingRequests = (int) ($account['pending'] ?? 0);
}

// Up to two initials for the avatar, e.g. "Priya Shah" -> "PS".
$initials  = '';
$firstName = '';
if ($account) {
    $parts     = preg_split('/\s+/', trim($account['name'])) ?: [];
    $firstName = $parts[0] ?? '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
}
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');

$navAttrs = static function (string $file) use ($currentPage): string {
    $active = $currentPage === $file;
    $class  = 'site-nav__link' . ($active ? ' site-nav__link--active' : '');
    $aria   = $active ? ' aria-current="page"' : '';
    return 'class="' . $class . '"' . $aria;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · Student SkillBridge</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Zilla+Slab:wght@600;700&family=Work+Sans:wght@400;600&display=swap" rel="stylesheet">
    <?php // ?v=<mtime> busts the browser cache whenever style.css changes. ?>
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <?php // Flags JS support before paint so the mobile menu can start collapsed without a flash; without JS the nav stays visible. ?>
    <script>document.documentElement.classList.add('js');</script>
</head>
<body>
<header class="site-header">
    <div class="container site-header__inner">
        <a href="/index.php" class="brand">
            <img src="/assets/img/logo-nav.png" alt="" class="brand__logo" width="144" height="56">
            <span class="brand__text">Student<span class="brand__accent">SkillBridge</span></span>
        </a>

        <?php if ($account): ?>
            <?php // Phone-only: shows at a glance that you're logged in, links to your profile. ?>
            <a href="/pages/profile.php" class="header-avatar avatar" title="<?= e($account['name']) ?>">
                <span aria-hidden="true"><?= e($initials) ?></span><span class="visually-hidden">Your profile (<?= e($account['name']) ?>)</span>
            </a>
        <?php endif; ?>

        <button type="button" class="nav-toggle" aria-controls="site-nav" aria-expanded="false">
            <span class="nav-toggle__label">Menu</span>
            <span class="nav-toggle__bars" aria-hidden="true"><span></span><span></span><span></span></span>
        </button>

        <nav class="site-nav" id="site-nav" aria-label="Main navigation">
            <a href="/index.php" <?= $navAttrs('index.php') ?>>Home</a>

            <?php if ($account): ?>
                <a href="/pages/search.php" <?= $navAttrs('search.php') ?>>Search</a>
                <a href="/pages/matches.php" <?= $navAttrs('matches.php') ?>>Matches</a>
                <a href="/pages/dashboard.php" <?= $navAttrs('dashboard.php') ?>>Dashboard<?php if ($pendingRequests > 0): ?><span class="nav-badge" aria-hidden="true"><?= $pendingRequests > 9 ? '9+' : $pendingRequests ?></span><span class="visually-hidden"> (<?= $pendingRequests ?> new <?= $pendingRequests === 1 ? 'request' : 'requests' ?>)</span><?php endif; ?></a>

                <div class="account-menu">
                    <button type="button" class="account-menu__trigger" aria-expanded="false" aria-controls="account-menu-panel"
                            <?= in_array($currentPage, ['profile.php', 'admin.php'], true) ? 'data-active' : '' ?>>
                        <span class="avatar" aria-hidden="true"><?= e($initials) ?></span>
                        <span class="account-menu__name"><?= e($firstName) ?></span>
                        <svg class="account-menu__caret" aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                        <span class="visually-hidden">Account menu</span>
                    </button>
                    <div class="account-menu__panel" id="account-menu-panel">
                        <div class="account-menu__header">
                            <span class="avatar avatar--lg" aria-hidden="true"><?= e($initials) ?></span>
                            <div>
                                <p class="account-menu__fullname"><?= e($account['name']) ?></p>
                                <p class="account-menu__email"><?= e($account['email']) ?></p>
                                <?php if ($account['role'] === 'admin'): ?><span class="chip chip--sm">Admin</span><?php endif; ?>
                            </div>
                        </div>
                        <a href="/pages/profile.php" <?= $navAttrs('profile.php') ?>>Profile</a>
                        <?php if ($account['role'] === 'admin'): ?>
                            <a href="/pages/admin.php" <?= $navAttrs('admin.php') ?>>Admin</a>
                        <?php endif; ?>
                        <a href="/pages/login.php?action=logout&amp;token=<?= e(csrf_token()) ?>" class="site-nav__link account-menu__logout">Log out</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/pages/login.php" <?= $navAttrs('login.php') ?>>Log in</a>
                <a href="/pages/register.php" class="btn btn--primary btn--sm">Sign up</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="site-main">
    <div class="container">
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert--success" role="status"><?= e($_SESSION['flash_success']) ?></div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>
