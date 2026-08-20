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
$userRole   = $_SESSION['user_role'] ?? null;
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
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="container site-header__inner">
        <a href="/index.php" class="brand">Student<span class="brand__accent">SkillBridge</span></a>

        <nav class="site-nav" aria-label="Main navigation">
            <a href="/pages/search.php" class="site-nav__link">Search</a>

            <?php if ($isLoggedIn): ?>
                <a href="/pages/matches.php" class="site-nav__link">Matches</a>
                <a href="/pages/dashboard.php" class="site-nav__link">Dashboard</a>
                <a href="/pages/profile.php" class="site-nav__link">Profile</a>
                <?php if ($userRole === 'admin'): ?>
                    <a href="/pages/admin.php" class="site-nav__link">Admin</a>
                <?php endif; ?>
                <a href="/pages/login.php?action=logout" class="btn btn--secondary btn--sm">Log out</a>
            <?php else: ?>
                <a href="/pages/login.php" class="site-nav__link">Log in</a>
                <a href="/pages/register.php" class="btn btn--primary btn--sm">Sign up</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="site-main">
    <div class="container">
