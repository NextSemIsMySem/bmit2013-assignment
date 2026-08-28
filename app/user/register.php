<?php
require '../_base.php';

if (is_post()) {
    $email    = req('email');
    $password = req('password');
    $confirm  = req('confirm');
    $username = req('username');
    $name     = req('name');
    $f = get_file('photo');
    $passwordFieldsInvalid = false;

    // Validate: email
    if ($email === '') {
        $_err['email'] = 'Email is required.';
    } elseif (!preg_match('/^[\x21-\x7E]+$/', $email)) {
        $_err['email'] = 'Email may only contain English characters and keyboard symbols.';
    } elseif (strlen($email) > 255) {
        $_err['email'] = 'Maximum 255 characters.';
    } elseif (!is_email($email)) {
        $_err['email'] = 'Please enter a valid email address.';
    } elseif (!is_unique('user', 'email', $email)) {
        $_err['email'] = 'Email is already registered.';
    }

    // Validate: password (server-side)
    if ($pwError = password_error($password)) {
        $_err['password'] = $pwError;
        $passwordFieldsInvalid = true;
    }

    // Validate: confirm
    if ($confirm === '') {
        $_err['confirm'] = 'Please confirm your password.';
        $passwordFieldsInvalid = true;
    } elseif ($confirm !== $password) {
        $_err['confirm'] = 'Passwords do not match.';
        $passwordFieldsInvalid = true;
    }

    // Validate: username
    if ($username === '') {
        $_err['username'] = 'Username is required.';
    } elseif (strlen($username) < 5) {
        $_err['username'] = 'Username must be at least 5 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $username)) {
        $_err['username'] = 'Username must contain alphabetic characters.';
    } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
        $_err['username'] = 'Username may only contain English letters, numbers and underscores.';
    } elseif (strlen($username) > 50) {
        $_err['username'] = 'Username must be at most 50 characters.';
    } elseif (!is_unique('user', 'username', $username)) {
        $_err['username'] = 'Username is already registered.';
    }

    // Validate: display name
    if (strlen($name) > 50) {
        $_err['name'] = 'Display name must be at most 50 characters.';
    } elseif ($name !== '' && !preg_match("/^[A-Za-z0-9 .,'-]+$/", $name)) {
        $_err['name'] = 'Display name may only contain English letters, numbers, spaces and common punctuation.';
    }

    // Validate: photo (optional)
    if ($f && !str_starts_with($f->type, 'image/')) {
        $_err['photo'] = 'Uploaded file must be an image.';
    } elseif ($f && $f->size > 1 * 1024 * 1024) {
        $_err['photo'] = 'Maximum 1MB file size.';
    }

    if ($passwordFieldsInvalid) {
        $_REQUEST['password'] = '';
        $_REQUEST['confirm'] = '';
    }

    if (!$_err) {
        // Save photo
        $photo = $f ? save_photo($f, __DIR__ . '/../photos') : '';

        // Create the account inactive until the email link is verified.
        $stm = $_db->prepare(
            'INSERT INTO user (email, username, password, name, photo, role, active, email_verified) VALUES (?, ?, SHA1(?), ?, ?, "member", 1, 0)'
        );
        $stm->execute([$email, $username, $password, $name, $photo]);

        $tokenId = bin2hex(random_bytes(32));
        $tokenStmt = $_db->prepare(
            'INSERT INTO token (id, expire, user_id, type) VALUES (?, ADDTIME(NOW(), "24:00"), ?, "verification")'
        );
        $tokenStmt->execute([$tokenId, $_db->lastInsertId()]);

        $sent = false;
        try {
            $m = get_mail();
            $m->addAddress($email, $name);
            $m->isHTML(true);
            $m->Subject = 'Verify your ForgeFit account';
            $url = base("user/verify.php?id=$tokenId");
            $m->Body = "<p>Dear " . encode($name) . ",</p>
                        <p>Thank you for registering with ForgeFit Fitness Market.</p>
                        <p>Please click <a href='$url'>here</a> to verify your email address. This link expires in 24 hours.</p>
                        <p>From, ForgeFit Admin</p>";
            $m->send();
            $sent = true;
        } catch (Exception $e) {
            $_db->prepare('DELETE FROM token WHERE id = ?')->execute([$tokenId]);
            $_db->prepare('DELETE FROM user WHERE email = ? AND active = 0')->execute([$email]);
        }

        if (!$sent) {
            $_err['email'] = 'Could not send the verification email right now. Please try again later.';
        } else {
            temp('info', 'A verification email has been sent to your email address, if exists.');
            redirect('/login.php');
        }
    }
}

$_title = 'Register';
$_backUrl = '/';
$_backLabel = 'Back to Home';
$_photoEditor = true;
include '../_head.php';
?>

<form method="post" class="form" enctype="multipart/form-data">
    <?php html_text('email', 'Email', 'email', false, '[!-~]+', 'email'); ?>

    <?php html_restricted_text('username', 'Username', '[A-Za-z0-9_]+', 'username', 50); ?>

    <?php html_password('password', 'Password'); ?>

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

    <?php html_password('confirm', 'Confirm'); ?>

    <?php html_restricted_text('name', 'Display Name', "[A-Za-z0-9 .,'-]*", 'display-name', 50); ?>

    <label for="photo">Photo (optional)</label>
    <label class="upload" tabindex="0">
        <?php html_file('photo', 'image/*', 'hidden'); ?>
        <img src="/images/profile.png">
    </label>
    <?php echo err('photo'); ?>

    <section class="buttons">
        <button type="submit">Register</button>
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

<?php include '../_foot.php'; ?>
