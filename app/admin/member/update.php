<?php
require '../../_base.php';
auth('admin');

$id = req('id');
// Members only — an admin must not be able to edit an admin/superadmin
// account through the member module.
$stmt = $_db->prepare("SELECT * FROM user WHERE user_id = ? AND role = 'member'");
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    redirect('index.php');
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
    } elseif (!is_email($email)) {
        $_err['email'] = 'Please enter a valid email address.';
    } elseif (strlen($email) > 255) {
        $_err['email'] = 'Email must be at most 255 characters.';
    } elseif (!is_unique('user', 'email', $email, 'user_id', $id)) {
        $_err['email'] = 'Duplicated.';
    }

    if (!$_err) {
        // Role is deliberately not editable: admins are created as fresh
        // accounts by a superadmin, never promoted from a member.
        $stmt = $_db->prepare("UPDATE user SET name = ?, email = ? WHERE user_id = ? AND role = 'member'");
        $stmt->execute([$name, $email, $id]);

        temp('info', 'Member updated.');
        redirect('detail.php?id=' . $id);
    }
} else {
    // Pre-fill sticky form fields from the DB on first (GET) load.
    $_REQUEST['name'] = $member->name;
    $_REQUEST['email'] = $member->email;
}

$_title = 'Update Member';
include '../../_head.php';
?>

<form class="form" method="post">
    <?php html_text('name', 'Name'); ?>
    <?php html_text('email', 'Email', 'email'); ?>
    <section class="buttons">
        <button type="submit">Save</button>
        <button type="button" data-get="detail.php?id=<?= encode($id) ?>">Cancel</button>
    </section>
</form>

<?php
include '../../_foot.php';
