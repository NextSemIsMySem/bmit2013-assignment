<?php
require '../_base.php';

if (is_post()) {
    $email    = req('email');
    $password = req('password');
    $confirm  = req('confirm');
    $username = req('username');
    $name     = req('name');
    $f = get_file('photo');

    // Validate: email
    if ($email === '') {
        $_err['email'] = 'Email is required.';
    } elseif (strlen($email) > 100) {
        $_err['email'] = 'Maximum 100 characters.';
    } elseif (!is_email($email)) {
        $_err['email'] = 'Please enter a valid email address.';
    } elseif (!is_unique('user', 'email', $email)) {
        $_err['email'] = 'Email is already registered.';
    }

    // Validate: password (server-side)
    $passwordFailed = false;
    if ($pwError = password_error($password)) {
        $_err['password'] = $pwError;
        $passwordFailed = true;
    }

    // Validate: confirm
    if ($confirm === '') {
        $_err['confirm'] = 'Please confirm your password.';
    } elseif ($confirm !== $password) {
        $_err['confirm'] = 'Passwords do not match.';
    }

    // Validate: username
    if ($username === '') {
        $_err['username'] = 'Username is required.';
    } elseif (strlen($username) < 5) {
        $_err['username'] = 'Username must be at least 5 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $username)) {
        $_err['username'] = 'Username must contain alphabetic characters.';
    } elseif (strlen($username) > 50) {
        $_err['username'] = 'Username must be at most 50 characters.';
    } elseif (!is_unique('user', 'username', $username)) {
        $_err['username'] = 'Username is already registered.';
    }

    // Validate: display name
    if (strlen($name) > 50) {
        $_err['name'] = 'Display name must be at most 50 characters.';
    }

    // Validate: photo
    if (!$f) {
        $_err['photo'] = 'Photo is required.';
    } elseif (!str_starts_with($f->type, 'image/')) {
        $_err['photo'] = 'Uploaded file must be an image.';
    } elseif ($f->size > 1 * 1024 * 1024) {
        $_err['photo'] = 'Maximum 1MB file size.';
    }

    // Clear invalid password fields after failed submit only for actual password-related errors.
    if (isset($_err['confirm']) && $_err['confirm'] === 'Passwords do not match.') {
        $_REQUEST['confirm'] = '';
    }

    if ($passwordFailed) {
        $_REQUEST['password'] = '';
        $_REQUEST['confirm'] = '';
    }

    if (!$_err) {
        // Save photo
        $photo = save_photo($f, __DIR__ . '/../photos');

        // Insert user as Member
        $stm = $_db->prepare(
            'INSERT INTO user (email, username, password, name, photo, role) VALUES (?, ?, SHA1(?), ?, ?, "member")'
        );
        $stm->execute([$email, $username, $password, $name, $photo]);

        temp('info', 'Registration successful. You may now log in.');
        redirect('/login.php');
    }
}

$_title = 'Register';
include '../_head.php';
?>

<form method="post" class="form" enctype="multipart/form-data">
    <?php html_text('email', 'Email', 'email'); ?>

    <?php html_text('username', 'Username'); ?>

    <?php html_password('password', 'Password'); ?>

    <div class="password-requirements" data-password-requirements="password">
        <small>Password requirements:</small>
        <ul>
            <li data-req="length"><span class="indicator">✖</span> At least 8 characters (max 50)</li>
            <li data-req="lower"><span class="indicator">✖</span> Contains a lowercase letter</li>
            <li data-req="upper"><span class="indicator">✖</span> Contains an uppercase letter</li>
            <li data-req="number"><span class="indicator">✖</span> Contains a number</li>
            <li data-req="symbol"><span class="indicator">✖</span> Contains a symbol (e.g. @#$%)</li>
        </ul>
    </div>

    <?php html_password('confirm', 'Confirm'); ?>

    <?php html_text('name', 'Display Name'); ?>

    <label for="photo">Photo</label>
    <label class="upload" tabindex="0">
        <?php html_file('photo', 'image/*', 'hidden'); ?>
        <img src="/images/photo.jpg">
    </label>
    <?php echo err('photo'); ?>

    <section class="buttons">
        <button type="submit">Register</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php include '../_foot.php'; ?>
