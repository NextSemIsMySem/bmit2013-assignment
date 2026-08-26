<?php
require '../_base.php';
auth();

if (is_post()) {
    $password = req('password');
    $new_password = req('new_password');
    $confirm = req('confirm');
    $newPasswordFailed = false;

    if ($password === '') {
        $_err['password'] = 'Password is required.';
    } else {
        $stm = $_db->prepare('SELECT COUNT(*) FROM user WHERE password = SHA1(?) AND user_id = ?');
        $stm->execute([$password, $_user->user_id]);

        if ($stm->fetchColumn() == 0) {
            $_err['password'] = 'Incorrect current password.';
        }
    }

    if ($new_password === '') {
        $_err['new_password'] = 'New password is required.';
        $newPasswordFailed = true;
    } elseif (strlen($new_password) < 8 || strlen($new_password) > 50) {
        $_err['new_password'] = 'New password must be between 8-50 characters.';
        $newPasswordFailed = true;
    } else {
        $pw_ok = preg_match('/[a-z]/', $new_password)
            && preg_match('/[A-Z]/', $new_password)
            && preg_match('/[0-9]/', $new_password)
            && preg_match('/[^a-zA-Z0-9]/', $new_password);

        if (!$pw_ok) {
            $_err['new_password'] = 'New password must include upper/lowercase letters, a number and a symbol.';
            $newPasswordFailed = true;
        }
    }

    if ($confirm === '') {
        $_err['confirm'] = 'Please confirm your new password.';
    } elseif ($confirm !== $new_password) {
        $_err['confirm'] = 'Does not match with new password.';
    }

    if ($newPasswordFailed) {
        $_REQUEST['new_password'] = '';
        $_REQUEST['confirm'] = '';
    }

    if (!$_err) {
        $stm = $_db->prepare('UPDATE user SET password = SHA1(?) WHERE user_id = ?');
        $stm->execute([$new_password, $_user->user_id]);

        temp('info', 'Password updated.');
        redirect('/user/profile.php');
    }
}

$_title = 'Change Password';
$_navSection = 'settings';
$_backUrl = '/user/settings.php';
$_backLabel = 'Back to Settings';
include '../_head.php';
?>

<form class="form" method="post">
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
