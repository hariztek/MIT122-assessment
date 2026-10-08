<?php
/**
 * includes/db.php
 *
 * Single PDO connection point for Student SkillBridge (project
 * convention: one DB-connection file, included wherever needed). Every page that
 * needs database access should `require_once __DIR__ . '/db.php';`
 * (or the relative equivalent from pages/) and then use the $pdo
 * variable it defines.
 *
 * Local development credentials only (MAMP defaults: root/root on the
 * non-standard port MAMP uses for MySQL). This is a MIT122 Assessment 2
 * local build, not a deployed production system.
 */

if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_PORT', '8889');
    define('DB_NAME', 'skillbridge');
    define('DB_USER', 'root');
    define('DB_PASS', 'root');
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    DB_HOST,
    DB_PORT,
    DB_NAME
);

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // Never leak connection details (host/user/password) to the browser.
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('A database error occurred. Please try again later.');
}
