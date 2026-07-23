<?php
require '_base.php';

if (is_post()) {
    $email = req('email');
    $password = req('password');

    if ($email === '') {
        $_err['email'] = 'Email is required.';
    } elseif (!is_email($email)) {
        $_err['email'] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $_err['password'] = 'Password is required.';
    }

    if (!$_err) {
        $stm = $_db->prepare('SELECT * FROM user WHERE email = ? AND password = SHA1(?)');
        $stm->execute([$email, $password]);
        $u = $stm->fetch();

        if ($u) {
            temp('info', 'Login successful.');
            login($u, '/index.php');
        } else {
            $_err['password'] = 'Invalid email or password'; // generic — don't reveal which
        }
    }
}

$_title = 'Login';
include '_head.php';
?>

<form class="form" method="post">
    <?php html_text('email', 'Email', 'email'); ?>
    <?php html_password('password', 'Password'); ?>
    <section class="buttons">
        <button type="submit">Login</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php
include '_foot.php';
