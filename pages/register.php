<?php
/**
 * pages/register.php
 *
 * Account creation (FR-01 / SB-012). Students can create an account
 * with a name, email, and password. The password is hashed with
 * password_hash() before insert; the role is always 'student'
 * (the first admin is promoted later with a one-off UPDATE in
 * phpMyAdmin — register.php never offers an admin option).
 *
 * Every check here is server-side. Client-side HTML5 attributes are a
 * convenience only and are never trusted: this page re-validates on
 * POST even if JavaScript is disabled.
 */

require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    redirect('/index.php');
}

$errors = [];
$name   = '';
$email  = '';
$registered = isset($_GET['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim((string) ($_POST['name'] ?? ''));
    $email    = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');

    $missing = missing_fields(
        [
            'name'             => $name,
            'email'            => $email,
            'password'         => $password,
            'password_confirm' => $confirm,
        ],
        ['name', 'email', 'password', 'password_confirm']
    );

    if (in_array('name', $missing, true)) {
        $errors['name'] = 'Enter your name.';
    } elseif (!valid_length($name, 2, 100)) {
        $errors['name'] = 'Name must be between 2 and 100 characters.';
    }

    if (in_array('email', $missing, true)) {
        $errors['email'] = 'Enter your email address.';
    } elseif (!valid_email($email) || !valid_length($email, 5, 150)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if (in_array('password', $missing, true)) {
        $errors['password'] = 'Choose a password.';
    } elseif (!valid_length($password, 8, 72)) {
        $errors['password'] = 'Password must be between 8 and 72 characters.';
    }

    if (in_array('password_confirm', $missing, true)) {
        $errors['password_confirm'] = 'Re-enter your password to confirm it.';
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }

    if ($errors === []) {
        require_once __DIR__ . '/../includes/db.php';

        $existing = $pdo->prepare(
            'SELECT user_id FROM users WHERE email = :email LIMIT 1'
        );
        $existing->execute(['email' => $email]);

        if ($existing->fetch()) {
            $errors['email'] = 'An account with this email already exists.';
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role)
                 VALUES (:name, :email, :password_hash, :role)'
            );

            try {
                $insert->execute([
                    'name'          => $name,
                    'email'         => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role'          => 'student',
                ]);
                redirect('/pages/register.php?registered=1');
            } catch (PDOException $e) {
                // Unique email is also enforced by uq_users_email; treat a
                // race as the same user-facing duplicate-email error.
                if ($e->getCode() === '23000') {
                    $errors['email'] = 'An account with this email already exists.';
                } else {
                    error_log('Registration insert failed: ' . $e->getMessage());
                    $errors['form'] = 'Something went wrong creating your account. Please try again.';
                }
            }
        }
    }
}

$pageTitle = 'Create an account';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-layout">
    <?php if ($registered): ?>
        <div class="card">
            <p class="label-md auth-layout__eyebrow">Account created</p>
            <h1 class="headline-lg">You're in</h1>
            <p class="body-md auth-layout__lede">Your account is ready. Log in with the email you just registered to start listing skills.</p>
            <a href="/pages/login.php?registered=1" class="btn btn--primary btn--block">Log in</a>
        </div>
    <?php else: ?>
        <div class="card">
            <h1 class="headline-lg">Create an account</h1>
            <p class="body-md auth-layout__lede">Join Student SkillBridge to list skills you can teach and skills you want to learn.</p>

            <?php if ($errors !== []): ?>
                <div class="form-error-summary" role="alert">
                    <?= e($errors['form'] ?? 'Please fix the highlighted fields and try again.') ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/pages/register.php" novalidate>
                <div class="field">
                    <label for="name">Name</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= e($name) ?>"
                        maxlength="100"
                        autocomplete="name"
                        aria-required="true"
                        aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>"
                        <?php if (isset($errors['name'])): ?>aria-describedby="name-error"<?php endif; ?>
                    >
                    <?php if (isset($errors['name'])): ?>
                        <p class="field-error" id="name-error"><?= e($errors['name']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= e($email) ?>"
                        maxlength="150"
                        autocomplete="email"
                        spellcheck="false"
                        aria-required="true"
                        aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>"
                        <?php if (isset($errors['email'])): ?>aria-describedby="email-error"<?php endif; ?>
                    >
                    <?php if (isset($errors['email'])): ?>
                        <p class="field-error" id="email-error"><?= e($errors['email']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        maxlength="72"
                        autocomplete="new-password"
                        aria-required="true"
                        aria-describedby="password-hint<?= isset($errors['password']) ? ' password-error' : '' ?>"
                        aria-invalid="<?= isset($errors['password']) ? 'true' : 'false' ?>"
                    >
                    <p class="field-hint" id="password-hint">At least 8 characters.</p>
                    <?php if (isset($errors['password'])): ?>
                        <p class="field-error" id="password-error"><?= e($errors['password']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="password_confirm">Confirm password</label>
                    <input
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        minlength="8"
                        maxlength="72"
                        autocomplete="new-password"
                        aria-required="true"
                        aria-invalid="<?= isset($errors['password_confirm']) ? 'true' : 'false' ?>"
                        <?php if (isset($errors['password_confirm'])): ?>aria-describedby="password-confirm-error"<?php endif; ?>
                    >
                    <?php if (isset($errors['password_confirm'])): ?>
                        <p class="field-error" id="password-confirm-error"><?= e($errors['password_confirm']) ?></p>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn--primary btn--block">Create account</button>
            </form>
        </div>

        <p class="auth-switch">Already have an account? <a href="/pages/login.php">Log in</a></p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
