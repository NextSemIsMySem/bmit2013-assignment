<?php
require '../_base.php';

if (is_post()) {
    $email    = req('email');
    $password = req('password');
    $confirm  = req('confirm');
    $name     = req('name');
    $f = get_file('photo');

    // Validate: email
    if ($email === '') {
        $_err['email'] = 'Email is required.';
    } elseif (strlen($email) > 100) {
        $_err['email'] = 'Maximum 100 characters.';
    } elseif (!is_email($email)) {
        $_err['email'] = 'Please enter a valid email address.';
    } elseif (!is_unique('user', 'email', $email)) {
        $_err['email'] = 'Email is already registered.';
    }

    // Validate: password (server-side)
    if ($password === '') {
        $_err['password'] = 'Password is required.';
    } elseif (strlen($password) < 8 || strlen($password) > 50) {
        $_err['password'] = 'Password must be between 8-50 characters.';
    } else {
        $pw_ok = preg_match('/[a-z]/', $password)
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^a-zA-Z0-9]/', $password);

        if (!$pw_ok) {
            $_err['password'] = 'Password must include upper/lowercase letters, a number and a symbol.';
        }
    }

    // Validate: confirm
    if ($confirm === '') {
        $_err['confirm'] = 'Please confirm your password.';
    } elseif ($confirm !== $password) {
        $_err['confirm'] = 'Passwords do not match.';
    }

    // Validate: name
    if ($name === '') {
        $_err['name'] = 'Name is required.';
    } elseif (strlen($name) < 5) {
        $_err['name'] = 'Name must be at least 5 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $name)) {
        $_err['name'] = 'Name must contain alphabetic characters.';
    } elseif (strlen($name) > 100) {
        $_err['name'] = 'Maximum 100 characters.';
    }

    // Validate: photo
    if (!$f) {
        $_err['photo'] = 'Photo is required.';
    } elseif (!str_starts_with($f->type, 'image/')) {
        $_err['photo'] = 'Uploaded file must be an image.';
    } elseif ($f->size > 1 * 1024 * 1024) {
        $_err['photo'] = 'Maximum 1MB file size.';
    }

    if (!$_err) {
        // Save photo
        $photo = save_photo($f, __DIR__ . '/../photos');

        // Insert user as Member
        $stm = $_db->prepare(
            'INSERT INTO user (email, password, name, photo, role) VALUES (?, SHA1(?), ?, ?, "Member")'
        );
        $stm->execute([$email, $password, $name, $photo]);

        temp('info', 'Registration successful. You may now log in.');
        redirect('/login.php');
    }
}

$_title = 'Register';
include '../_head.php';
?>

<form method="post" class="form" enctype="multipart/form-data">
    <?php html_text('email', 'Email', 'email'); ?>

    <?php html_password('password', 'Password'); ?>

    <div class="password-requirements" id="password-requirements">
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

    <?php html_text('name', 'Name'); ?>

    <label for="photo">Photo</label>
    <label class="upload" tabindex="0">
        <?php html_file('photo', 'image/*', 'hidden'); ?>
        <img src="/images/photo.jpg">
    </label>
    <?php echo err('photo'); ?>

    <section class="buttons">
        <button type="submit">Register</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php include '../_foot.php'; ?>

<script>
// Password requirements dynamic checker
;(function () {
    const init = () => {
        const pw = document.getElementById('password');
        const reqBox = document.getElementById('password-requirements');
        if (!pw || !reqBox) return;

        const checks = {
            length: v => v.length >= 8 && v.length <= 50,
            lower: v => /[a-z]/.test(v),
            upper: v => /[A-Z]/.test(v),
            number: v => /[0-9]/.test(v),
            symbol: v => /[^a-zA-Z0-9]/.test(v),
        };

        function update() {
            const v = pw.value || '';
            Object.keys(checks).forEach(k => {
                const li = reqBox.querySelector('[data-req="' + k + '"]');
                if (!li) return;
                if (checks[k](v)) {
                    li.classList.add('met');
                    li.querySelector('.indicator').textContent = '✓';
                } else {
                    li.classList.remove('met');
                    li.querySelector('.indicator').textContent = '✖';
                }
            });
        }

        // Listen to several events to ensure live updates while typing/pasting
        ['input', 'keyup', 'change', 'paste'].forEach(ev => pw.addEventListener(ev, update));

        // init
        update();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
