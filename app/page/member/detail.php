<?php
require '../../_base.php';
auth('admin');

$id = req('id');
$stmt = $_db->prepare('SELECT * FROM user WHERE user_id = ?');
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    redirect('index.php');
}

$_title = 'Member Detail';
include '../../_head.php';
?>

<table class="table detail">
    <tr><th>Id</th><td><?= encode($member->user_id) ?></td></tr>
    <tr><th>Username</th><td><?= encode($member->username) ?></td></tr>
    <tr><th>Name</th><td><?= encode($member->name) ?></td></tr>
    <tr><th>Email</th><td><?= encode($member->email) ?></td></tr>
    <tr><th>Role</th><td><?= encode($member->role) ?></td></tr>
    <tr><th>Joined</th><td><?= encode($member->created_at) ?></td></tr>
</table>

<div class="buttons">
    <button type="button" data-get="index.php">Back</button>
    <button type="button" data-get="update.php?id=<?= encode($member->user_id) ?>">Edit</button>
    <button type="button" data-post="delete.php?id=<?= encode($member->user_id) ?>">Delete</button>
</div>

<?php
include '../../_foot.php';
