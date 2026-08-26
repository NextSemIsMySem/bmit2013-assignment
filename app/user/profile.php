<?php
require '../_base.php';
auth();

if (is_get()) {
    $stm = $_db->prepare('SELECT * FROM user WHERE user_id = ?');
    $stm->execute([$_user->user_id]);
    $u = $stm->fetch();

    if (!$u) {
        redirect('/');
    }

    $_REQUEST['username'] = $u->username;
    $_REQUEST['name'] = $u->name;
    $_REQUEST['email'] = $u->email;
}

if (is_post()) {
    $username = req('username');
    $name = req('name');
    $email = req('email');

    if ($username === '') {
        $_err['username'] = 'Username is required.';
    } elseif (strlen($username) < 5) {
        $_err['username'] = 'Username must be at least 5 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $username)) {
        $_err['username'] = 'Username must contain alphabetic characters.';
    } elseif (strlen($username) > 50) {
        $_err['username'] = 'Username must be at most 50 characters.';
    } elseif (!is_unique('user', 'username', $username, 'user_id', $_user->user_id)) {
        $_err['username'] = 'Username is already registered.';
    }

    if (strlen($name) > 50) {
        $_err['name'] = 'Display name must be at most 50 characters.';
    }

    if ($email === '') {
        $_err['email'] = 'Email is required.';
    } elseif (strlen($email) > 100) {
        $_err['email'] = 'Email must be at most 100 characters.';
    } elseif (!is_email($email)) {
        $_err['email'] = 'Please enter a valid email address.';
    } elseif (!is_unique('user', 'email', $email, 'user_id', $_user->user_id)) {
        $_err['email'] = 'Email is already registered.';
    }

    $photo = $_user->photo;
    $f = get_file('photo');
    if ($f) {
        if (!str_starts_with($f->type, 'image/')) {
            $_err['photo'] = 'Photo must be an image file.';
        } elseif ($f->size > 1 * 1024 * 1024) {
            $_err['photo'] = 'Photo must be 1MB or smaller.';
        } else {
            if ($_user->photo) {
                @unlink(__DIR__ . '/../photos/' . $_user->photo);
            }
            $photo = save_photo($f);
        }
    }

    if (!$_err) {
        $stm = $_db->prepare('UPDATE user SET username = ?, name = ?, email = ?, photo = ? WHERE user_id = ?');
        $stm->execute([$username, $name, $email, $photo, $_user->user_id]);

        $_user->username = $username;
        $_user->name = $name;
        $_user->email = $email;
        $_user->photo = $photo;

        temp('info', 'Profile updated.');
        redirect('/user/profile.php');
    }
}

$photoUrl = $_user->photo ? '/photos/' . encode($_user->photo) : '/images/profile.png';

$_title = 'My Profile';
$_navSection = 'profile';
include '../_head.php';
?>

<form class="form" method="post" enctype="multipart/form-data">
    <?php html_text('username', 'Username'); ?>
    <?php html_text('name', 'Display Name'); ?>
    <?php html_text('email', 'Email', 'email'); ?>
    <label for="photo">Photo</label>
    <label class="upload" tabindex="0">
        <?= html_file('photo', 'image/*', 'hidden') ?>
        <img src="<?= $photoUrl ?>" data-src="<?= $photoUrl ?>">
    </label>
    <?= err('photo') ?>
    <section class="buttons">
        <button type="submit">Save</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php
include '../_foot.php';
