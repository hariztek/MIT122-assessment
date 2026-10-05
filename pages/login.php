<?php
/**
 * pages/login.php
 *
 * Authentication (FR-02 / SB-013). Students sign in with the email and
 * password they registered. A matching password_verify() starts a PHP
 * session; a mismatch is rejected without saying which field was wrong.
 *
 * Logout lives here too — the shared header already links to
 * /pages/login.php?action=logout — so one page owns the full session
 * start/end cycle. Protected pages call require_login() in
 * includes/auth.php (SB-014).
 *
 * Every check here is server-side. HTML5 attributes are a convenience
 * only; this page re-validates on POST even if JavaScript is disabled.
 */

require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
    redirect('/pages/login.php');
}

$errors = [];
$email  = '';
$justRegistered = isset($_GET['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!csrf_valid()) {
        $errors['form'] = 'Your session expired. Please try again.';
    }

    $missing = missing_fields(
        [
            'email'    => $email,
            'password' => $password,
        ],
        ['email', 'password']
    );

    if (in_array('email', $missing, true)) {
        $errors['email'] = 'Enter your email address.';
    } elseif (!valid_email($email)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if (in_array('password', $missing, true)) {
        $errors['password'] = 'Enter your password.';
    }

    if ($errors === []) {
        require_once __DIR__ . '/../includes/db.php';

        $lookup = $pdo->prepare(
            'SELECT user_id, name, email, password_hash, role, status
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $lookup->execute(['email' => $email]);
        $user = $lookup->fetch();

        // Dummy hash so password_verify() still runs when the email is
        // unknown, keeping the timing closer to a real miss.
        $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

        if (!$user || !password_verify($password, $hash)) {
            $errors['form'] = 'Email or password is incorrect.';
        } elseif ($user['status'] !== 'active') {
            $errors['form'] = 'This account has been suspended.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id']   = (int) $user['user_id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            redirect('/pages/profile.php');
        }
    }
}

if (isset($_SESSION['user_id'])) {
    redirect('/pages/profile.php');
}

$pageTitle = 'Log in';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-shell">
<div class="auth-layout">
    <div class="card">
            <h1 class="headline-lg">Log in</h1>
            <p class="body-md auth-layout__lede">Use the email and password you registered with.</p>

            <?php if (isset($_GET['suspended'])): ?>
                <div class="alert alert--error" role="alert">Your account has been suspended by an administrator, so you have been logged out.</div>
            <?php endif; ?>

            <?php if ($justRegistered): ?>
                <div class="alert alert--success" role="status">Account created. Log in to continue.</div>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <div class="form-error-summary" role="alert">
                    <?= e($errors['form'] ?? 'Please fix the highlighted fields and try again.') ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/pages/login.php" novalidate data-validate="login">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= e($email) ?>"
                        maxlength="150"
                        autocomplete="username"
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
                        maxlength="72"
                        autocomplete="current-password"
                        aria-required="true"
                        aria-invalid="<?= isset($errors['password']) ? 'true' : 'false' ?>"
                        <?php if (isset($errors['password'])): ?>aria-describedby="password-error"<?php endif; ?>
                    >
                    <?php if (isset($errors['password'])): ?>
                        <p class="field-error" id="password-error"><?= e($errors['password']) ?></p>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn--primary btn--block">Log in</button>
            </form>
        </div>

        <p class="auth-switch">New here? <a href="/pages/register.php">Create an account</a></p>
</div>
<?php require __DIR__ . '/../includes/auth_aside.php'; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
