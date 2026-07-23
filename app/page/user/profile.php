<?php
require '../../_base.php';
auth();

if (is_get()) {
    $stm = $_db->prepare('SELECT * FROM user WHERE user_id = ?');
    $stm->execute([$_user->user_id]);
    $u = $stm->fetch();

    if (!$u) {
        redirect('/');
    }

    $_REQUEST['name'] = $u->name;
    $_REQUEST['email'] = $u->email;
}

if (is_post()) {
    $name = req('name');
    $email = req('email');

    if ($name === '') {
        $_err['name'] = 'Name is required.';
    } elseif (strlen($name) > 100) {
        $_err['name'] = 'Name must be at most 100 characters.';
    }

    if ($email === '') {
        $_err['email'] = 'Email is required.';
    } elseif (strlen($email) > 100) {
        $_err['email'] = 'Email must be at most 100 characters.';
    } elseif (!is_email($email)) {
        $_err['email'] = 'Please enter a valid email address.';
    } elseif (!is_unique('user', 'email', $email, 'user_id', $_user->user_id)) {
        $_err['email'] = 'Duplicated';
    }

    if (!$_err) {
        $stm = $_db->prepare('UPDATE user SET name = ?, email = ? WHERE user_id = ?');
        $stm->execute([$name, $email, $_user->user_id]);

        $_user->name = $name;
        $_user->email = $email;

        temp('info', 'Profile updated.');
        redirect('/page/user/profile.php');
    }
}

$_title = 'My Profile';
include '../../_head.php';
?>

<!-- TODO Practical 6: profile photo upload -->
<form class="form" method="post">
    <?php html_text('name', 'Name'); ?>
    <?php html_text('email', 'Email', 'email'); ?>
    <section class="buttons">
        <button type="submit">Save</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php
include '../../_foot.php';
