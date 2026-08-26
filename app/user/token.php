<?php
require '../_base.php';

$_db->query('DELETE FROM token WHERE expire < NOW()');   // purge expired first

// Runs on GET *and* POST (req() reads $_REQUEST), so the hidden field below
// is re-verified on submit — a token that expired or was used in the meantime
// is rejected rather than trusted from the earlier GET.
$id = req('id');
if (!is_exists('token', 'id', $id)) {
    temp('info', 'Invalid or expired link. Please try again.');
    redirect('/user/reset.php');
}

if (is_post()) {
    $password = req('password');
    $confirm = req('confirm');
    $passwordFailed = false;

    if ($pwError = password_error($password, 'New password')) {
        $_err['password'] = $pwError;
        $passwordFailed = true;
    }

    if ($confirm === '') {
        $_err['confirm'] = 'Please confirm your new password.';
    } elseif ($confirm !== $password) {
        $_err['confirm'] = 'Does not match with new password.';
    }

    if ($passwordFailed) {
        $_REQUEST['password'] = '';
        $_REQUEST['confirm'] = '';
    }

    if (!$_err) {
        $stm = $_db->prepare('UPDATE user SET password = SHA1(?)
                              WHERE user_id = (SELECT user_id FROM token WHERE id = ?)');
        $stm->execute([$password, $id]);

        $stm = $_db->prepare('DELETE FROM token WHERE id = ?');   // single-use token
        $stm->execute([$id]);

        temp('info', 'Password updated. Please log in.');
        redirect('/login.php');
    }
}

$_title = 'Reset Password';
$_backUrl = '/user/reset.php';
$_backLabel = 'Back to Forgot Password';
include '../_head.php';
?>

<form class="form" method="post">
    <input type="hidden" name="id" value="<?= encode($id) ?>">
    <?php html_password('password', 'New Password'); ?>

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

    <?php html_password('confirm', 'Confirm New Password'); ?>
    <section class="buttons">
        <button type="submit">Save</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php
include '../_foot.php';
