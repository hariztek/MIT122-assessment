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
 * Strictly parse an <input type="datetime-local"> value ("2026-10-02T14:30").
 * Returns null for anything that isn't exactly that format and a real
 * calendar date/time (so "2026-02-31T10:00" is rejected, not rolled over).
 */
function parse_datetime_local(string $value): ?DateTimeImmutable
{
    $dt     = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', trim($value));
    $errors = DateTimeImmutable::getLastErrors();
    if ($dt === false || ($errors && ($errors['warning_count'] || $errors['error_count']))) {
        return null;
    }
    return $dt->format('Y-m-d\TH:i') === trim($value) ? $dt : null;
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

/**
 * Per-session CSRF token for state-changing forms (used by admin
 * moderation). Embed with csrf_field(); verify with csrf_valid().
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $sent);
}

/**
 * Allowed ENUM values for user_skills.level / mode / type.
 *
 * @return string[]
 */
function allowed_skill_levels(): array
{
    return ['beginner', 'intermediate', 'advanced', 'expert'];
}

/**
 * @return string[]
 */
function allowed_skill_modes(): array
{
    return ['online', 'in_person', 'both'];
}

/**
 * @return string[]
 */
function allowed_skill_types(): array
{
    return ['offer', 'want'];
}

/**
 * Human-readable label for a stored ENUM / slug value.
 */
function enum_label(string $value): string
{
    return match ($value) {
        'in_person'    => 'In person',
        'career_study' => 'Career / study',
        default        => ucfirst(str_replace('_', ' ', $value)),
    };
}

/**
 * Print <option> elements for an ENUM list.
 *
 * @param string[] $allowed
 */
function enum_select_options(array $allowed, string $selected): void
{
    foreach ($allowed as $value) {
        $sel = $value === $selected ? ' selected' : '';
        echo '<option value="' . e($value) . '"' . $sel . '>' . e(enum_label($value)) . '</option>';
    }
}

/**
 * Print grouped <option> elements for the skills catalogue.
 *
 * @param array<int,array<string,mixed>> $catalogue
 * @param int[]                          $excludeIds
 */
function skill_optgroup_options(array $catalogue, array $excludeIds, string $selectedId): void
{
    $groups = [];
    foreach ($catalogue as $skill) {
        $id = (int) $skill['skill_id'];
        if (in_array($id, $excludeIds, true)) {
            continue;
        }
        $groups[$skill['category']][] = $skill;
    }

    echo '<option value="">Choose a skill</option>';
    foreach ($groups as $category => $skills) {
        echo '<optgroup label="' . e(enum_label((string) $category)) . '">';
        foreach ($skills as $skill) {
            $id  = (string) $skill['skill_id'];
            $sel = $id === $selectedId ? ' selected' : '';
            echo '<option value="' . e($id) . '"' . $sel . '>' . e((string) $skill['name']) . '</option>';
        }
        echo '</optgroup>';
    }
}
