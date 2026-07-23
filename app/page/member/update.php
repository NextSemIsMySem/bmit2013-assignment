<?php
require '../../_base.php';

$id = req('id');
$stmt = $_db->prepare('SELECT * FROM user WHERE user_id = ?');
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    redirect('index.php');
}

if (is_post()) {
    $name = req('name');
    $email = req('email');
    $role = req('role');

    if ($name === '') {
        $_err['name'] = 'Name is required.';
    } elseif (strlen($name) > 100) {
        $_err['name'] = 'Name must be at most 100 characters.';
    }

    if ($email === '') {
        $_err['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_err['email'] = 'Please enter a valid email address.';
    } elseif (strlen($email) > 255) {
        $_err['email'] = 'Email must be at most 255 characters.';
    } elseif (!is_unique('user', 'email', $email, 'user_id', $id)) {
        $_err['email'] = 'Duplicated.';
    }

    if (!in_array($role, ['admin', 'customer'], true)) {
        $_err['role'] = 'Please select a valid role.';
    }

    if (!$_err) {
        $stmt = $_db->prepare('UPDATE user SET name = ?, email = ?, role = ? WHERE user_id = ?');
        $stmt->execute([$name, $email, $role, $id]);

        temp('info', 'Member updated.');
        redirect('detail.php?id=' . $id);
    }
} else {
    // Pre-fill sticky form fields from the DB on first (GET) load.
    $_REQUEST['name'] = $member->name;
    $_REQUEST['email'] = $member->email;
    $_REQUEST['role'] = $member->role;
}

$_title = 'Update Member';
include '../../_head.php';
?>

<form class="form" method="post">
    <?php html_text('name', 'Name'); ?>
    <?php html_text('email', 'Email', 'email'); ?>
    <?php html_select('role', 'Role', ['admin' => 'Admin', 'customer' => 'Customer']); ?>
    <section class="buttons">
        <button type="submit">Save</button>
        <button type="button" data-get="detail.php?id=<?= encode($id) ?>">Cancel</button>
    </section>
</form>

<?php
include '../../_foot.php';
