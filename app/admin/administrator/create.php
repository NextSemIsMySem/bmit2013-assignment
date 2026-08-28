<?php
require '../../_base.php';
auth('superadmin');

if (is_post()) {
    $name     = req('name');
    $username = req('username');
    $email    = req('email');
    $password = req('password');
    $confirm  = req('confirm');
    $passwordFieldsInvalid = false;

    if ($name === '') {
        $_err['name'] = 'Name is required.';
    } elseif (strlen($name) > 100) {
        $_err['name'] = 'Name must be at most 100 characters.';
    }

    if ($username === '') {
        $_err['username'] = 'Username is required.';
    } elseif (strlen($username) < 5) {
        $_err['username'] = 'Username must be at least 5 characters.';
    } elseif (strlen($username) > 50) {
        $_err['username'] = 'Username must be at most 50 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $username)) {
        $_err['username'] = 'Username must contain alphabetic characters.';
    } elseif (!is_unique('user', 'username', $username)) {
        $_err['username'] = 'Username is already registered.';
    }

    if ($email === '') {
        $_err['email'] = 'Email is required.';
    } elseif (strlen($email) > 255) {
        $_err['email'] = 'Maximum 255 characters.';
    } elseif (!is_email($email)) {
        $_err['email'] = 'Please enter a valid email address.';
    } elseif (!is_unique('user', 'email', $email)) {
        $_err['email'] = 'Email is already registered.';
    }

    if ($pwError = password_error($password)) {
        $_err['password'] = $pwError;
        $passwordFieldsInvalid = true;
    }

    if ($confirm === '') {
        $_err['confirm'] = 'Please confirm the password.';
        $passwordFieldsInvalid = true;
    } elseif ($confirm !== $password) {
        $_err['confirm'] = 'Passwords do not match.';
        $passwordFieldsInvalid = true;
    }

    if ($passwordFieldsInvalid) {
        $_REQUEST['password'] = '';
        $_REQUEST['confirm'] = '';
    }

    if (!$_err) {
        // Role is hard-coded: this page creates admins only. A superadmin
        // cannot mint another superadmin — those are provisioned via seed data.
        $stm = $_db->prepare(
            "INSERT INTO user (email, username, password, name, role, active)
             VALUES (?, ?, SHA1(?), ?, 'admin', 1)"
        );
        $stm->execute([$email, $username, $password, $name]);

        temp('info', 'Admin account created.');
        redirect('index.php');
    }
}

$_title = 'Create Admin';
include '../../_head.php';
?>

<form class="form" method="post">
    <?php html_text('name', 'Name'); ?>
    <?php html_text('username', 'Username'); ?>
    <?php html_text('email', 'Email', 'email'); ?>
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

    <?php html_password('confirm', 'Confirm Password'); ?>

    <section class="buttons">
        <button type="submit">Create</button>
        <button type="button" data-get="index.php">Cancel</button>
    </section>
</form>

<?php
include '../../_foot.php';
