<?php
require '../../_base.php';
auth('superadmin');

if (!is_post()) {
    redirect('index.php');
}

$id = (int) req('id');

if ($id === (int) $_user->user_id) {
    temp('info', 'You cannot disable your own account.');
    redirect('index.php');
}

$stmt = $_db->prepare("SELECT * FROM user WHERE user_id = ? AND role IN ('admin','superadmin')");
$stmt->execute([$id]);
$target = $stmt->fetch();

if (!$target) {
    temp('info', 'Admin not found.');
    redirect('index.php');
}

// Never let the last active superadmin be locked out.
if ($target->role === 'superadmin' && $target->active) {
    $count = $_db->query("SELECT COUNT(*) FROM user WHERE role = 'superadmin' AND active = 1")->fetchColumn();

    if ($count <= 1) {
        temp('info', 'At least one active superadmin must remain.');
        redirect('index.php');
    }
}

$stmt = $_db->prepare("UPDATE user SET active = 0 WHERE user_id = ? AND role IN ('admin','superadmin')");
$stmt->execute([$id]);

temp('info', 'Admin disabled.');
redirect('index.php');
