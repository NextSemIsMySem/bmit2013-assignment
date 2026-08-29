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
    verify_csrf();
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
    } elseif (strlen($email) > 255) {
        $_err['email'] = 'Email must be at most 255 characters.';
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

    $emailChanged = $email !== $_user->email;

    if (!$_err) {
        $pendingEmail = $_user->pending_email ?? null;
        $verificationSent = false;

        if ($emailChanged) {
            $tokenId = bin2hex(random_bytes(32));
            $_db->prepare('DELETE FROM token WHERE user_id = ? AND type = "verification"')->execute([$_user->user_id]);
            $_db->prepare('INSERT INTO token (id, expire, user_id, type) VALUES (?, ADDTIME(NOW(), "24:00"), ?, "verification")')->execute([$tokenId, $_user->user_id]);

            try {
                $m = get_mail();
                $m->addAddress($email, $name !== '' ? $name : $username);
                $m->isHTML(true);
                $m->Subject = 'Verify your new email address';
                $url = base("user/verify.php?id=$tokenId");
                $m->Body = "<p>Dear " . encode($name !== '' ? $name : $username) . ",</p>
                            <p>Your ForgeFit account email change is awaiting confirmation.</p>
                            <p>Please click <a href='$url'>here</a> to verify your new email address. This link expires in 24 hours.</p>
                            <p>From, ForgeFit Admin</p>";
                $m->send();
                $verificationSent = true;
                $pendingEmail = $email;
            } catch (Exception $e) {
                $_db->prepare('DELETE FROM token WHERE id = ?')->execute([$tokenId]);
                $_err['email'] = 'Could not send the verification email right now. Please try again later.';
            }
        }

        if (!$_err) {
            $stm = $_db->prepare('UPDATE user SET username = ?, name = ?, photo = ?, pending_email = ? WHERE user_id = ?');
            $stm->execute([$username, $name, $photo, $pendingEmail, $_user->user_id]);

            $_user->username = $username;
            $_user->name = $name;
            $_user->photo = $photo;
            $_user->pending_email = $pendingEmail;

            if ($verificationSent) {
                temp('info', 'A verification email has been sent to your new email address. Your current email remains active until it is confirmed.');
            } else {
                temp('info', 'Profile updated.');
            }
            redirect('/user/profile.php');
        }
    }
}

$photoUrl = $_user->photo ? '/photos/' . encode($_user->photo) : '/images/profile.png';

$_title = 'Profile Settings';
$_navSection = 'settings';
$_backUrl = '/user/settings.php';
$_backLabel = 'Back to Settings';
$_photoEditor = true;
include '../_head.php';
?>

<form class="form" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
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

<dialog class="photo-editor-dialog" id="photo-editor-dialog" aria-labelledby="photo-editor-title">
    <h2 id="photo-editor-title">Edit Profile Photo</h2>
    <div class="photo-editor-canvas">
        <img id="photo-editor-image" alt="Profile photo preview">
    </div>
    <div class="photo-editor-tools" aria-label="Photo editing tools">
        <button type="button" data-photo-editor-action="rotate-left" title="Rotate left">&#8634;</button>
        <button type="button" data-photo-editor-action="rotate-right" title="Rotate right">&#8635;</button>
        <button type="button" data-photo-editor-action="flip-horizontal" title="Flip horizontally">&#8644;</button>
        <button type="button" data-photo-editor-action="flip-vertical" title="Flip vertically">&#8597;</button>
        <button type="button" data-photo-editor-action="reset">Reset</button>
    </div>
    <div class="photo-editor-actions">
        <button type="button" class="btn-dark" data-photo-editor-action="cancel">Cancel</button>
        <button type="button" class="btn-green" data-photo-editor-action="apply">Apply</button>
    </div>
</dialog>

<?php
include '../_foot.php';
