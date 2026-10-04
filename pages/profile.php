<?php
/**
 * pages/profile.php
 *
 * Edit the signed-in student's profile (SB-015) and their offered /
 * wanted skill entries (FR-03 / FR-04, SB-017 / SB-018).
 *
 * Only the owning student can add, edit, or remove their rows — every
 * write re-checks user_id server-side. Invalid input is rejected even
 * if JavaScript is disabled.
 */

require_once __DIR__ . '/../includes/auth.php';

require_login();

require_once __DIR__ . '/../includes/db.php';

$userId = current_user_id();
$errors = [];
$action = '';

$loadUser = $pdo->prepare(
    'SELECT user_id, name, email, bio, campus, status
     FROM users
     WHERE user_id = :user_id
     LIMIT 1'
);
$loadUser->execute(['user_id' => $userId]);
$user = $loadUser->fetch();

if (!$user || $user['status'] !== 'active') {
    $_SESSION = [];
    session_destroy();
    redirect('/pages/login.php');
}

$name    = $user['name'];
$email   = $user['email'];
$bio     = $user['bio'] ?? '';
$campus  = $user['campus'] ?? '';

$skillDefaults = [
    'skill_id'     => '',
    'level'        => 'intermediate',
    'mode'         => 'both',
    'availability' => '',
    'description'  => '',
];
$addOffer = $skillDefaults;
$addWant  = $skillDefaults;

$editId = filter_var($_GET['edit'] ?? '', FILTER_VALIDATE_INT) ?: 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_profile') {
        $name   = trim((string) ($_POST['name'] ?? ''));
        $campus = trim((string) ($_POST['campus'] ?? ''));
        $bio    = trim((string) ($_POST['bio'] ?? ''));

        if (!required($name)) {
            $errors['name'] = 'Enter your name.';
        } elseif (!valid_length($name, 2, 100)) {
            $errors['name'] = 'Name must be between 2 and 100 characters.';
        }

        if ($campus !== '' && !valid_length($campus, 1, 100)) {
            $errors['campus'] = 'Campus must be at most 100 characters.';
        }

        if ($bio !== '' && !valid_length($bio, 1, 1000)) {
            $errors['bio'] = 'Bio must be at most 1000 characters.';
        }

        if ($errors === []) {
            $update = $pdo->prepare(
                'UPDATE users
                 SET name = :name, campus = :campus, bio = :bio
                 WHERE user_id = :user_id'
            );
            $update->execute([
                'name'    => $name,
                'campus'  => $campus === '' ? null : $campus,
                'bio'     => $bio === '' ? null : $bio,
                'user_id' => $userId,
            ]);
            $_SESSION['user_name'] = $name;
            $_SESSION['flash_success'] = 'Profile saved.';
            redirect('/pages/profile.php');
        }
    }

    if ($action === 'add_skill' || $action === 'update_skill') {
        $type = (string) ($_POST['type'] ?? '');
        $skillId = filter_var($_POST['skill_id'] ?? '', FILTER_VALIDATE_INT);
        $level = (string) ($_POST['level'] ?? '');
        $mode = (string) ($_POST['mode'] ?? '');
        $availability = trim((string) ($_POST['availability'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $userSkillId = filter_var($_POST['user_skill_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;

        $posted = [
            'skill_id'     => $skillId === false ? '' : (string) $skillId,
            'level'        => $level,
            'mode'         => $mode,
            'availability' => $availability,
            'description'  => $description,
        ];

        if (!valid_enum($type, allowed_skill_types())) {
            $errors['form'] = 'Choose whether this is a skill you offer or want.';
        }

        if ($action === 'add_skill') {
            if ($type === 'offer') {
                $addOffer = $posted;
            } elseif ($type === 'want') {
                $addWant = $posted;
            }
        }

        if ($skillId === false || $skillId < 1) {
            $errors['skill_id'] = 'Choose a skill.';
        } else {
            $exists = $pdo->prepare(
                'SELECT skill_id FROM skills WHERE skill_id = :skill_id LIMIT 1'
            );
            $exists->execute(['skill_id' => $skillId]);
            if (!$exists->fetch()) {
                $errors['skill_id'] = 'Choose a skill from the list.';
            }
        }

        if (!valid_enum($level, allowed_skill_levels())) {
            $errors['level'] = 'Choose a valid level.';
        }
        if (!valid_enum($mode, allowed_skill_modes())) {
            $errors['mode'] = 'Choose a valid mode.';
        }
        if ($availability !== '' && !valid_length($availability, 1, 255)) {
            $errors['availability'] = 'Availability must be at most 255 characters.';
        }
        if ($description !== '' && !valid_length($description, 1, 1000)) {
            $errors['description'] = 'Description must be at most 1000 characters.';
        }

        if ($action === 'update_skill') {
            $owned = $pdo->prepare(
                'SELECT user_skill_id, type
                 FROM user_skills
                 WHERE user_skill_id = :id AND user_id = :user_id
                 LIMIT 1'
            );
            $owned->execute(['id' => $userSkillId, 'user_id' => $userId]);
            $row = $owned->fetch();
            if (!$row) {
                $errors['form'] = 'That skill entry was not found.';
            } else {
                $type = $row['type'];
                $editId = $userSkillId;
            }
        }

        if ($errors === []) {
            try {
                if ($action === 'add_skill') {
                    $insert = $pdo->prepare(
                        'INSERT INTO user_skills
                            (user_id, skill_id, type, level, mode, availability, description)
                         VALUES
                            (:user_id, :skill_id, :type, :level, :mode, :availability, :description)'
                    );
                    $insert->execute([
                        'user_id'       => $userId,
                        'skill_id'      => $skillId,
                        'type'          => $type,
                        'level'         => $level,
                        'mode'          => $mode,
                        'availability'  => $availability === '' ? null : $availability,
                        'description'   => $description === '' ? null : $description,
                    ]);
                    $_SESSION['flash_success'] = $type === 'offer'
                        ? 'Offered skill added.'
                        : 'Wanted skill added.';
                } else {
                    $update = $pdo->prepare(
                        'UPDATE user_skills
                         SET skill_id = :skill_id,
                             level = :level,
                             mode = :mode,
                             availability = :availability,
                             description = :description
                         WHERE user_skill_id = :id AND user_id = :user_id'
                    );
                    $update->execute([
                        'skill_id'      => $skillId,
                        'level'         => $level,
                        'mode'          => $mode,
                        'availability'  => $availability === '' ? null : $availability,
                        'description'   => $description === '' ? null : $description,
                        'id'            => $userSkillId,
                        'user_id'       => $userId,
                    ]);
                    $_SESSION['flash_success'] = 'Skill updated.';
                }
                redirect('/pages/profile.php');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors['skill_id'] = 'You already listed this skill here.';
                } else {
                    error_log('Profile skill write failed: ' . $e->getMessage());
                    $errors['form'] = 'Something went wrong saving that skill. Please try again.';
                }
            }
        }
    }

    if ($action === 'delete_skill') {
        $userSkillId = filter_var($_POST['user_skill_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
        $delete = $pdo->prepare(
            'DELETE FROM user_skills
             WHERE user_skill_id = :id AND user_id = :user_id'
        );
        $delete->execute(['id' => $userSkillId, 'user_id' => $userId]);
        if ($delete->rowCount() === 1) {
            $_SESSION['flash_success'] = 'Skill removed.';
        }
        redirect('/pages/profile.php');
    }
}

$catalogue = $pdo->query(
    'SELECT skill_id, name, category FROM skills ORDER BY category, name'
)->fetchAll();

$listSkills = $pdo->prepare(
    'SELECT us.user_skill_id, us.skill_id, us.type, us.level, us.mode,
            us.availability, us.description, us.status, s.name AS skill_name, s.category
     FROM user_skills us
     INNER JOIN skills s ON s.skill_id = us.skill_id
     WHERE us.user_id = :user_id
     ORDER BY s.name'
);
$listSkills->execute(['user_id' => $userId]);
$entries = $listSkills->fetchAll();

$offers = [];
$wants  = [];
foreach ($entries as $entry) {
    if ($entry['type'] === 'offer') {
        $offers[] = $entry;
    } else {
        $wants[] = $entry;
    }
}

$offerIds = array_map(static fn($row) => (int) $row['skill_id'], $offers);
$wantIds  = array_map(static fn($row) => (int) $row['skill_id'], $wants);

$editing = null;
if ($editId > 0) {
    foreach ($entries as $entry) {
        if ((int) $entry['user_skill_id'] === $editId) {
            $editing = $entry;
            break;
        }
    }
}

$pageTitle = 'Profile';
require_once __DIR__ . '/../includes/header.php';

$skillForm = static function (
    string $formAction,
    string $type,
    array $values,
    array $errors,
    array $catalogue,
    array $excludeIds,
    ?int $userSkillId
) use ($action): void {
    $prefix = $userSkillId ? 'edit' : 'add-' . $type;
    $isThisForm = ($formAction === 'add_skill' && $action === 'add_skill' && (($_POST['type'] ?? '') === $type))
        || ($formAction === 'update_skill' && $action === 'update_skill');
    $showErrors = $isThisForm && $errors !== [];
    ?>
    <form method="post" action="/pages/profile.php" novalidate data-validate="skill">
        <input type="hidden" name="action" value="<?= e($formAction) ?>">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <?php if ($userSkillId): ?>
            <input type="hidden" name="user_skill_id" value="<?= (int) $userSkillId ?>">
        <?php endif; ?>

        <?php if ($showErrors && isset($errors['form'])): ?>
            <div class="form-error-summary" role="alert"><?= e($errors['form']) ?></div>
        <?php endif; ?>

        <div class="form-grid form-grid--2">
            <div class="field">
                <label for="<?= e($prefix) ?>-skill">Skill</label>
                <select
                    id="<?= e($prefix) ?>-skill"
                    name="skill_id"
                    aria-required="true"
                    aria-invalid="<?= $showErrors && isset($errors['skill_id']) ? 'true' : 'false' ?>"
                >
                    <?php skill_optgroup_options($catalogue, $excludeIds, (string) $values['skill_id']); ?>
                </select>
                <?php if ($showErrors && isset($errors['skill_id'])): ?>
                    <p class="field-error"><?= e($errors['skill_id']) ?></p>
                <?php endif; ?>
            </div>
            <div class="field">
                <label for="<?= e($prefix) ?>-level">Level</label>
                <select id="<?= e($prefix) ?>-level" name="level">
                    <?php enum_select_options(allowed_skill_levels(), (string) $values['level']); ?>
                </select>
            </div>
            <div class="field">
                <label for="<?= e($prefix) ?>-mode">Mode</label>
                <select id="<?= e($prefix) ?>-mode" name="mode">
                    <?php enum_select_options(allowed_skill_modes(), (string) $values['mode']); ?>
                </select>
            </div>
            <div class="field">
                <label for="<?= e($prefix) ?>-availability">Availability</label>
                <input
                    type="text"
                    id="<?= e($prefix) ?>-availability"
                    name="availability"
                    maxlength="255"
                    value="<?= e((string) $values['availability']) ?>"
                    placeholder="e.g. Weekday evenings"
                >
            </div>
        </div>
        <div class="field">
            <label for="<?= e($prefix) ?>-description">Description</label>
            <textarea
                id="<?= e($prefix) ?>-description"
                name="description"
                maxlength="1000"
                rows="3"
            ><?= e((string) $values['description']) ?></textarea>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn btn--primary">
                <?= $userSkillId ? 'Save changes' : 'Add skill' ?>
            </button>
            <?php if ($userSkillId): ?>
                <a href="/pages/profile.php" class="btn btn--secondary">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
    <?php
};
?>

<div class="profile">
    <header class="page-header">
        <h1 class="headline-lg">Profile</h1>
        <p class="body-md page-header__lede">Update your details and the skills you can teach or want to learn.</p>
    </header>

    <section class="section" aria-labelledby="about-heading">
        <h2 id="about-heading" class="headline-md">About you</h2>
        <div class="card">
            <?php if ($action === 'save_profile' && $errors !== []): ?>
                <div class="form-error-summary" role="alert">
                    <?= e($errors['form'] ?? 'Please fix the highlighted fields and try again.') ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/pages/profile.php" novalidate data-validate="profile">
                <input type="hidden" name="action" value="save_profile">
                <div class="form-grid form-grid--2">
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
                            aria-invalid="<?= isset($errors['name']) && $action === 'save_profile' ? 'true' : 'false' ?>"
                        >
                        <?php if ($action === 'save_profile' && isset($errors['name'])): ?>
                            <p class="field-error"><?= e($errors['name']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="email">Email</label>
                        <input type="email" id="email" value="<?= e($email) ?>" readonly>
                        <p class="field-hint">Email is used to log in and cannot be changed here.</p>
                    </div>
                    <div class="field form-grid__full">
                        <label for="campus">Campus</label>
                        <input
                            type="text"
                            id="campus"
                            name="campus"
                            value="<?= e($campus) ?>"
                            maxlength="100"
                            placeholder="Optional"
                        >
                    </div>
                </div>
                <div class="field">
                    <label for="bio">Bio</label>
                    <textarea id="bio" name="bio" maxlength="1000" rows="4" placeholder="A short introduction, optional"><?= e($bio) ?></textarea>
                    <?php if ($action === 'save_profile' && isset($errors['bio'])): ?>
                        <p class="field-error"><?= e($errors['bio']) ?></p>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn--primary">Save profile</button>
            </form>
        </div>
    </section>

    <?php
    $skillSections = [
        [
            'type'    => 'offer',
            'id'      => 'offer-heading',
            'title'   => 'Skills I can teach',
            'empty'   => 'You have not listed any skills you can teach yet.',
            'rows'    => $offers,
            'exclude' => $offerIds,
            'add'     => $addOffer,
        ],
        [
            'type'    => 'want',
            'id'      => 'want-heading',
            'title'   => 'Skills I want to learn',
            'empty'   => 'You have not listed any skills you want to learn yet.',
            'rows'    => $wants,
            'exclude' => $wantIds,
            'add'     => $addWant,
        ],
    ];
    foreach ($skillSections as $section):
    ?>
        <section class="section" aria-labelledby="<?= e($section['id']) ?>">
            <h2 id="<?= e($section['id']) ?>" class="headline-md"><?= e($section['title']) ?></h2>

            <?php if ($section['rows'] === []): ?>
                <p class="empty-state"><?= e($section['empty']) ?></p>
            <?php endif; ?>

            <?php foreach ($section['rows'] as $row): ?>
                <article class="card">
                    <?php if ($editing && (int) $editing['user_skill_id'] === (int) $row['user_skill_id']): ?>
                        <?php
                        $skillForm(
                            'update_skill',
                            $section['type'],
                            [
                                'skill_id'     => (string) $row['skill_id'],
                                'level'        => $row['level'],
                                'mode'         => $row['mode'],
                                'availability' => $row['availability'] ?? '',
                                'description'  => $row['description'] ?? '',
                            ],
                            $errors,
                            $catalogue,
                            array_values(array_diff($section['exclude'], [(int) $row['skill_id']])),
                            (int) $row['user_skill_id']
                        );
                        ?>
                    <?php else: ?>
                        <h3 class="card__title"><?= e($row['skill_name']) ?></h3>
                        <p class="card__meta">
                            <?= e(enum_label($row['category'])) ?>
                            &middot; <?= e(enum_label($row['level'])) ?>
                            &middot; <?= e(enum_label($row['mode'])) ?>
                            <?php if (!empty($row['availability'])): ?>
                                &middot; <?= e($row['availability']) ?>
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($row['description'])): ?>
                            <p class="body-md"><?= e($row['description']) ?></p>
                        <?php endif; ?>
                        <?php if ($row['status'] === 'hidden'): ?>
                            <p class="card__meta"><span class="review-card__hidden">Hidden by an admin</span> &middot; Other students can't see this listing in search or matches.</p>
                        <?php endif; ?>
                        <div class="btn-row">
                            <a href="/pages/profile.php?edit=<?= (int) $row['user_skill_id'] ?>" class="btn btn--secondary btn--sm">Edit</a>
                            <form method="post" action="/pages/profile.php" class="inline-form">
                                <input type="hidden" name="action" value="delete_skill">
                                <input type="hidden" name="user_skill_id" value="<?= (int) $row['user_skill_id'] ?>">
                                <button type="submit" class="btn btn--secondary btn--sm">Remove</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>

            <?php if (!($editing && $editing['type'] === $section['type'])): ?>
                <div class="card">
                    <h3 class="card__title">Add a skill</h3>
                    <?php
                    $skillForm(
                        'add_skill',
                        $section['type'],
                        $section['add'],
                        $errors,
                        $catalogue,
                        $section['exclude'],
                        null
                    );
                    ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
