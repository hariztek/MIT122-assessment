<?php
/**
 * includes/auth.php
 *
 * Session/authorisation guards reused by every protected page (SB-014).
 * Call require_login() before any HTML output — including before
 * includes/header.php — so a redirect can still send headers.
 */

require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Send anonymous visitors to login. Authenticated requests continue.
 */
function require_login(): void
{
    if (!isset($_SESSION['user_id'])) {
        redirect('/pages/login.php');
    }
}

/**
 * Logged-in user's id, or 0 if the session is missing (call after
 * require_login() so the 0 case should not happen).
 */
function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

/**
 * Role stored at login: 'student' or 'admin'.
 */
function current_user_role(): ?string
{
    $role = $_SESSION['user_role'] ?? null;
    return is_string($role) ? $role : null;
}

/**
 * Restrict a page to admin accounts. Non-admins are sent home.
 * Used by admin.php (SB-032); defined here so there is one guard.
 */
function require_admin(): void
{
    require_login();
    if (current_user_role() !== 'admin') {
        redirect('/index.php');
    }
}
