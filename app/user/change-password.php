<?php
require '../_base.php';
auth();

if (is_post()) {
    verify_csrf();
    $password = req('password');
    $new_password = req('new_password');
    $confirm = req('confirm');
    $passwordFieldsInvalid = false;

    if ($password === '') {
        $_err['password'] = 'Password is required.';
        $passwordFieldsInvalid = true;
    } else {
        $stm = $_db->prepare('SELECT COUNT(*) FROM user WHERE password = SHA1(?) AND user_id = ?');
        $stm->execute([$password, $_user->user_id]);

        if ($stm->fetchColumn() == 0) {
            $_err['password'] = 'Incorrect current password.';
            $passwordFieldsInvalid = true;
        }
    }

    if ($pwError = password_error($new_password, 'New password')) {
        $_err['new_password'] = $pwError;
        $passwordFieldsInvalid = true;
    }

    if ($confirm === '') {
        $_err['confirm'] = 'Please confirm your new password.';
        $passwordFieldsInvalid = true;
    } elseif ($confirm !== $new_password) {
        $_err['confirm'] = 'Does not match with new password.';
        $passwordFieldsInvalid = true;
    }

    if ($passwordFieldsInvalid) {
        $_REQUEST['password'] = '';
        $_REQUEST['new_password'] = '';
        $_REQUEST['confirm'] = '';
    }

    if (!$_err) {
        $stm = $_db->prepare('UPDATE user SET password = SHA1(?) WHERE user_id = ?');
        $stm->execute([$new_password, $_user->user_id]);
        $_SESSION['password_hash'] = sha1($new_password);

        temp('info', 'Password updated.');
        redirect('/');
    }
}

$_title = 'Change Password';
$_navSection = 'settings';
$_backUrl = '/user/settings.php';
$_backLabel = 'Back to Settings';
include '../_head.php';
?>

<form class="form" method="post">
    <?= csrf_field() ?>
    <?php html_password('password', 'Current Password'); ?>
    <?php html_password('new_password', 'New Password'); ?>

    <div class="password-requirements" data-password-requirements="new_password">
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
?>
