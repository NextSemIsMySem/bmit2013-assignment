<?php
require '../_base.php';
auth();

$_title = 'Settings';
$_navSection = 'settings';
$_backUrl = '/';
$_backLabel = 'Back to Home';
include '../_head.php';
?>

<p>Manage your account details and password.</p>
<section class="buttons">
    <a class="btn-green" href="/user/profile.php">Change Profile</a>
    <a class="btn-green" href="/user/change-password.php">Change Password</a>
</section>

<?php
include '../_foot.php';