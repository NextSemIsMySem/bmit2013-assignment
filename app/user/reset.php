<?php
require '../_base.php';

if (is_post()) {
    verify_csrf();
    $email = req('email');

    if ($email === '') {
        $_err['email'] = 'Email is required.';
    } elseif (!is_email($email)) {
        $_err['email'] = 'Please enter a valid email address.';
    }

    if (!$_err) {
        // Only active accounts can be reset, but the reply never says so —
        // the same confirmation is shown either way (see login.php).
        $stm = $_db->prepare('SELECT * FROM user WHERE email = ? AND active = 1');
        $stm->execute([$email]);
        $u = $stm->fetch();

        $sent = true;

        if ($u) {
            $id = bin2hex(random_bytes(32));

            // One active reset link per user: drop any earlier ones.
            $stm = $_db->prepare('DELETE FROM token WHERE user_id = ?');
            $stm->execute([$u->user_id]);

            $stm = $_db->prepare('INSERT INTO token (id, expire, user_id, type) VALUES (?, ADDTIME(NOW(), "00:05"), ?, "reset")');
            $stm->execute([$id, $u->user_id]);

            $url = base("user/token.php?id=$id");
            $name = encode($u->name);

            try {
                $m = get_mail();
                $m->addAddress($u->email, $u->name);
                $m->isHTML(true);
                $m->Subject = 'Reset Password';

                $photo = '';
                if ($u->photo && file_exists(root("photos/$u->photo"))) {
                    $m->addEmbeddedImage(root("photos/$u->photo"), 'photo');
                    $photo = "<img src='cid:photo' width='100' height='100' alt=''>";
                }

                $m->Body = "<p>Dear $name,</p>
                            $photo
                            <h1 style='color:red'>Reset Password</h1>
                            <p>Please click <a href='$url'>here</a> to reset your password.
                               This link expires in 5 minutes.</p>
                            <p>From, ForgeFit Admin</p>";
                $m->send();
            } catch (Exception $e) {
                // Don't leak SMTP internals to the visitor; drop the unusable token.
                $stm = $_db->prepare('DELETE FROM token WHERE id = ?');
                $stm->execute([$id]);
                $sent = false;
            }
        }

        if ($sent) {
            temp('info', 'If that email is registered, a reset link has been sent.');
            redirect('/user/reset.php');
        }

        $_err['email'] = 'Could not send the email right now. Please try again later.';
    }
}

$_title = 'Forgot Password';
$_navSection = 'forgot-password';
$_backUrl = '/login.php';
$_backLabel = 'Back to Login';
include '../_head.php';
?>

<form class="form" method="post">
    <?= csrf_field() ?>
    <?php html_text('email', 'Email', 'email'); ?>
    <section class="buttons">
        <button type="submit">Submit</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php
include '../_foot.php';
