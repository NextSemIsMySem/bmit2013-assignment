<?php
require '../../_base.php';

if (is_post()) {
    $name = req('name');
    $username = req('username');
    $email = req('email');
    $password = req('password');
    $confirm = req('confirm');

    if ($name === '') {
        $_err['name'] = 'Name is required.';
    } elseif (strlen($name) > 100) {
        $_err['name'] = 'Name must be at most 100 characters.';
    }

    if ($username === '') {
        $_err['username'] = 'Username is required.';
    } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
        $_err['username'] = 'Username may only contain letters, digits, and underscores.';
    } elseif (strlen($username) > 50) {
        $_err['username'] = 'Username must be at most 50 characters.';
    } elseif (!is_unique('user', 'username', $username)) {
        $_err['username'] = 'Duplicated.';
    }

    if ($email === '') {
        $_err['email'] = 'Email is required.';
    } elseif (!is_email($email)) {
        $_err['email'] = 'Please enter a valid email address.';
    } elseif (strlen($email) > 255) {
        $_err['email'] = 'Email must be at most 255 characters.';
    } elseif (!is_unique('user', 'email', $email)) {
        $_err['email'] = 'Duplicated.';
    }

    if ($password === '') {
        $_err['password'] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $_err['password'] = 'Password must be at least 6 characters.';
    }

    if ($confirm === '') {
        $_err['confirm'] = 'Please confirm your password.';
    } elseif ($confirm !== $password) {
        $_err['confirm'] = 'Not matched.';
    }

    $f = get_file('photo');
    $photo = null;
    if ($f) {
        if (!str_starts_with($f->type, 'image/')) {
            $_err['photo'] = 'Photo must be an image file.';
        } elseif ($f->size > 1 * 1024 * 1024) {
            $_err['photo'] = 'Photo must be 1MB or smaller.';
        } else {
            $photo = save_photo($f);
        }
    }

    if (!$_err) {
        $stmt = $_db->prepare('INSERT INTO user (username, name, email, password, role, photo) VALUES (?, ?, ?, SHA1(?), ?, ?)');
        $stmt->execute([$username, $name, $email, $password, 'member', $photo]);

        temp('info', 'Registration successful.');
        redirect('/index.php');
    }
}

$_title = 'Register';
include '../../_head.php';
?>

<form class="form" method="post" enctype="multipart/form-data">
    <?php html_text('name', 'Name'); ?>
    <?php html_text('username', 'Username'); ?>
    <?php html_text('email', 'Email', 'email'); ?>
    <?php html_password('password', 'Password'); ?>
    <?php html_password('confirm', 'Confirm Password'); ?>
    <label for="photo">Photo</label>
    <label class="upload" tabindex="0">
        <?= html_file('photo', 'image/*', 'hidden') ?>
        <img src="/images/profile.png" data-src="/images/profile.png">
    </label>
    <?= err('photo') ?>
    <section class="buttons">
        <button type="submit">Register</button>
    </section>
</form>

<?php
include '../../_foot.php';
