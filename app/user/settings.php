<?php
require '../_base.php';
auth();

$_title = 'Settings';
$_navSection = 'settings';
$_backUrl = '/';
$_backLabel = 'Back to Home';
include '../_head.php';
?>

<p>Here you can manage your account settings.</p>
<section class="buttons">
    <a class="btn-green" href="/user/profile.php">Change Profile</a>
    <a class="btn-green" href="/user/change-password.php">Change Password</a>
    <?php if (!is_admin()): ?>
    <a class="btn-green" href="/user/address.php">Manage Shipping Addresses</a>
    <?php endif; ?>
</section>

<?php
include '../_foot.php';