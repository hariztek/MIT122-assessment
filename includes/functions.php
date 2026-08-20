<?php
/**
 * includes/functions.php
 *
 * Shared server-side validation and small utility helpers for Student
 * SkillBridge (per AGENTS.md: "one validation approach shared via
 * includes/functions.php — don't reinvent validation per page").
 *
 * Every write path (register, profile edits, session requests, reviews)
 * must call these — or equivalent direct checks — server-side, no
 * matter what client-side JS in assets/js/validation.js already
 * checked. Client-side validation is a convenience only, never a
 * security boundary (AGENTS.md, NFRs).
 */

/**
 * Is a single value present and non-blank after trimming whitespace?
 *
 * @param mixed $value
 */
function required($value): bool
{
    return is_string($value) ? trim($value) !== '' : !empty($value);
}

/**
 * Check a set of required keys against a data array (e.g. $_POST) and
 * return the list of keys that are missing or blank.
 *
 * @param array<string,mixed> $data
 * @param string[]            $requiredKeys
 * @return string[] empty array means all required fields were present
 */
function missing_fields(array $data, array $requiredKeys): array
{
    $missing = [];
    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $data) || !required($data[$key])) {
            $missing[] = $key;
        }
    }
    return $missing;
}

/**
 * Validate an email address using PHP's built-in filter.
 */
function valid_email(string $email): bool
{
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Check a trimmed string's length falls within [min, max] characters
 * inclusive. Useful for names, passwords, goals, comments, etc.
 */
function valid_length(string $value, int $min, int $max): bool
{
    $length = mb_strlen(trim($value));
    return $length >= $min && $length <= $max;
}

/**
 * Check a value is one of a fixed set of allowed values — used to
 * re-validate anything backed by a MySQL ENUM column (role, level,
 * mode, type, status, category) before it ever reaches a query, since
 * an invalid value would otherwise fail as a raw, unfriendly DB error.
 *
 * @param string[] $allowed
 */
function valid_enum(string $value, array $allowed): bool
{
    return in_array($value, $allowed, true);
}

/**
 * Validate a proposed session date/time is a real, parseable date and
 * strictly in the future. Used by FR-07 (send a session request).
 */
function valid_future_datetime(string $value): bool
{
    $timestamp = strtotime($value);
    return $timestamp !== false && $timestamp > time();
}

/**
 * Escape a value for safe HTML output. Short name is a deliberate,
 * common convention (e.g. `<?= e($user['name']) ?>`) so escaping is
 * never skipped for being inconvenient to type.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect the browser to a path relative to the site root and stop
 * script execution. Always call this before any HTML output, since
 * headers cannot be sent after output has started.
 */
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}
