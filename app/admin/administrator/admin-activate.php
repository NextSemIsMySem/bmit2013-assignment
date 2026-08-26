<?php
require '../../_base.php';
auth('superadmin');

if (!is_post()) {
    redirect('index.php');
}

$id = (int) req('id');

$stmt = $_db->prepare("UPDATE user SET active = 1 WHERE user_id = ? AND role IN ('admin','superadmin')");
$stmt->execute([$id]);

temp('info', 'Admin activated.');
redirect('index.php');
