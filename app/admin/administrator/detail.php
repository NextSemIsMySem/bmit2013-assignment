<?php
require '../../_base.php';
auth('superadmin');

$id = req('id');
// Admin-level accounts only — members are viewed under admin/member/.
$stmt = $_db->prepare("SELECT * FROM user WHERE user_id = ? AND role IN ('admin','superadmin')");
$stmt->execute([$id]);
$admin = $stmt->fetch();

if (!$admin) {
    redirect('index.php');
}

$photoUrl = $admin->photo ? '/photos/' . encode($admin->photo) : '/images/profile.png';
// Same wording as the listing's derived `status` column.
$status = $admin->active ? 'Active' : 'Disabled';

$_title = 'Admin Detail';
include '../../_head.php';
?>

<table class="table detail">
    <tr><th>Photo</th><td><img class="detail-photo" src="<?= $photoUrl ?>" alt="Photo"></td></tr>
    <tr><th>Id</th><td><?= encode($admin->user_id) ?></td></tr>
    <tr><th>Username</th><td><?= encode($admin->username) ?></td></tr>
    <tr><th>Name</th><td><?= encode($admin->name) ?></td></tr>
    <tr><th>Email</th><td><?= encode($admin->email) ?></td></tr>
    <tr><th>Role</th><td><?= encode($admin->role) ?></td></tr>
    <tr><th>Status</th><td><?= encode($status) ?></td></tr>
    <tr><th>Joined</th><td><?= encode($admin->created_at) ?></td></tr>
</table>

<div class="buttons">
    <button type="button" data-get="index.php">Back</button>
    <?php if ($admin->active): ?>
        <button
            type="button"
            data-post="admin-disable.php?id=<?= encode($admin->user_id) ?>"
            data-confirm="Disable this admin?"
        >Disable admin</button>
    <?php else: ?>
        <button
            type="button"
            data-post="admin-activate.php?id=<?= encode($admin->user_id) ?>"
            data-confirm="Activate this admin?"
        >Activate admin</button>
    <?php endif; ?>
</div>

<?php
include '../../_foot.php';
