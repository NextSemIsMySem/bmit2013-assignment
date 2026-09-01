<?php
require '_base.php';

$returnUrl = safe_local_url(req('return'), '/index.php');

if ($_user) {
    redirect(is_admin() ? '/admin/member/index.php' : $returnUrl);
}

if (is_post()) {
    verify_csrf();
    $identifier = req('identifier');
    $password = req('password');
    $passwordInvalid = false;

    if ($identifier === '') {
        $_err['identifier'] = 'Email or username is required.';
    } elseif (!preg_match('/^[\x21-\x7E]+$/', $identifier)) {
        $_err['identifier'] = 'Email or username may only contain English characters and keyboard symbols.';
    } elseif (!is_email($identifier) && !preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        $_err['identifier'] = 'Username may only contain English letters, numbers and underscores.';
    }

    if ($password === '') {
        $_err['password'] = 'Password is required.';
        $passwordInvalid = true;
    } elseif (!preg_match('/^[\x21-\x7E]+$/', $password)) {
        $_err['password'] = 'Password may only contain English letters, numbers and keyboard symbols.';
        $passwordInvalid = true;
    }

    if (!$_err) {
        // A valid email is always treated as an email; every other identifier is a username.
        // Members only — an admin with correct credentials simply isn't found here and
        // falls through to the generic message, revealing neither the account nor the
        // separate admin entrance.
        $loginColumn = is_email($identifier) ? 'email' : 'username';
        $stm = $_db->prepare("SELECT * FROM user WHERE `$loginColumn` = ? AND password = SHA1(?) AND role = 'member'");
        $stm->execute([$identifier, $password]);
        $u = $stm->fetch();

        if ($u && !$u->active) {
            $_err['password'] = 'This account has been disabled.';
            $passwordInvalid = true;
        } elseif ($u && !$u->email_verified) {
            $_err['password'] = 'Please verify your email before logging in.';
            $passwordInvalid = true;
        } elseif ($u) {
            temp('info', 'Login successful.');
            login($u, $returnUrl);
        } else {
            $_err['password'] = 'Invalid email or password'; // generic — don't reveal which
            $passwordInvalid = true;
        }
    }

    if ($passwordInvalid) {
        $_REQUEST['password'] = '';
    }
}

$_title = 'Login';
include '_head.php';
?>

<form class="form" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="return" value="<?= encode($returnUrl) ?>">
    <?php html_text('identifier', 'Email or Username', 'text', false, '[!-~]+', 'identifier'); ?>
    <?php html_password('password', 'Password'); ?>
    <section class="buttons">
        <button type="submit">Login</button>
        <button type="reset">Reset</button>
    </section>
</form>

<p><a href="/user/reset.php">Forgot your password?</a></p>

<?php
include '_foot.php';
