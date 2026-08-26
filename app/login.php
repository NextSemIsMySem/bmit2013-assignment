<?php
require '_base.php';

if (is_post()) {
    $identifier = req('identifier');
    $password = req('password');

    if ($identifier === '') {
        $_err['identifier'] = 'Email or username is required.';
    }

    if ($password === '') {
        $_err['password'] = 'Password is required.';
    }

    if (!$_err) {
        // A valid email is always treated as an email; every other identifier is a username.
        $loginColumn = is_email($identifier) ? 'email' : 'username';
        $stm = $_db->prepare(
            "SELECT * FROM user WHERE `$loginColumn` = ? AND password = SHA1(?)"
        );
        $stm->execute([$identifier, $password]);
        $u = $stm->fetch();

        if ($u && !$u->active) {
            $_err['password'] = 'This account has been disabled.';
        } elseif ($u) {
            temp('info', 'Login successful.');
            login($u, $u->role === 'admin' ? '/admin/member/index.php' : '/index.php');
        } else {
            $_err['password'] = 'Invalid email or password'; // generic — don't reveal which
        }
    }
}

$_title = 'Login';
include '_head.php';
?>

<form class="form" method="post">
    <?php html_text('identifier', 'Email or Username'); ?>
    <?php html_password('password', 'Password'); ?>
    <section class="buttons">
        <button type="submit">Login</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php
include '_foot.php';
