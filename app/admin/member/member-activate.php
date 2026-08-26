<?php
require '../../_base.php';
auth('admin');

if (!is_post()) {
    redirect('index.php');
}

$id = req('id');

$stmt = $_db->prepare("UPDATE user SET active = 1 WHERE user_id = ? AND role = 'member'");
$stmt->execute([$id]);

temp('info', 'Member activated.');
redirect('index.php');
