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
 * intentionally more robust than the "../" relative paths implied by
 * AGENTS.md's directory note, since it never breaks based on the
 * caller's depth.
 */

require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle  = $pageTitle ?? 'Student SkillBridge';
$isLoggedIn = isset($_SESSION['user_id']);

// Nav badge: incoming requests still waiting for this user's reply.
$pendingRequests = 0;
if ($isLoggedIn) {
    require_once __DIR__ . '/db.php';
    $pendingStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM session_requests WHERE receiver_id = :me AND status = \'pending\''
    );
    $pendingStmt->execute(['me' => (int) $_SESSION['user_id']]);
    $pendingRequests = (int) $pendingStmt->fetchColumn();
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

        <button type="button" class="nav-toggle" aria-controls="site-nav" aria-expanded="false">
            <span class="nav-toggle__label">Menu</span>
            <span class="nav-toggle__bars" aria-hidden="true"><span></span><span></span><span></span></span>
        </button>

        <nav class="site-nav" id="site-nav" aria-label="Main navigation">
            <a href="/index.php" <?= $navAttrs('index.php') ?>>Home</a>
            <a href="/pages/search.php" <?= $navAttrs('search.php') ?>>Search</a>
            <a href="/pages/matches.php" <?= $navAttrs('matches.php') ?>>Matches</a>
            <a href="/pages/dashboard.php" <?= $navAttrs('dashboard.php') ?>>Dashboard<?php if ($pendingRequests > 0): ?><span class="nav-badge" aria-hidden="true"><?= $pendingRequests > 9 ? '9+' : $pendingRequests ?></span><span class="visually-hidden"> (<?= $pendingRequests ?> new <?= $pendingRequests === 1 ? 'request' : 'requests' ?>)</span><?php endif; ?></a>
            <a href="/pages/profile.php" <?= $navAttrs('profile.php') ?>>Profile</a>
            <?php if (($_SESSION['user_role'] ?? null) === 'admin'): ?>
                <a href="/pages/admin.php" <?= $navAttrs('admin.php') ?>>Admin</a>
            <?php endif; ?>

            <?php if ($isLoggedIn): ?>
                <a href="/pages/login.php?action=logout" class="btn btn--secondary btn--sm">Log out</a>
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
