<?php
require '../../_base.php';
auth();

if (is_post()) {
    $password = req('password');
    $new_password = req('new_password');
    $confirm = req('confirm');

    if ($password === '') {
        $_err['password'] = 'Password is required.';
    } else {
        $stm = $_db->prepare('SELECT COUNT(*) FROM user WHERE password = SHA1(?) AND user_id = ?');
        $stm->execute([$password, $_user->user_id]);

        if ($stm->fetchColumn() == 0) {
            $_err['password'] = 'Not matched';
        }
    }

    if ($new_password === '') {
        $_err['new_password'] = 'New password is required.';
    } elseif (strlen($new_password) < 5 || strlen($new_password) > 100) {
        $_err['new_password'] = 'New password must be 5 to 100 characters.';
    }

    if ($confirm === '') {
        $_err['confirm'] = 'Please confirm your new password.';
    } elseif ($confirm !== $new_password) {
        $_err['confirm'] = 'Not matched';
    }

    if (!$_err) {
        $stm = $_db->prepare('UPDATE user SET password = SHA1(?) WHERE user_id = ?');
        $stm->execute([$new_password, $_user->user_id]);

        temp('info', 'Password updated.');
        redirect('/page/user/profile.php');
    }
}

$_title = 'Change Password';
include '../../_head.php';
?>

<form class="form" method="post">
    <?php html_password('password', 'Current Password'); ?>
    <?php html_password('new_password', 'New Password'); ?>
    <?php html_password('confirm', 'Confirm New Password'); ?>
    <section class="buttons">
        <button type="submit">Save</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php
include '../../_foot.php';
