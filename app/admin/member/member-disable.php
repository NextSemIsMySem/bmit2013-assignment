<?php
require '../../_base.php';
auth('admin');

if (!is_post()) {
    redirect('index.php');
}

$id = req('id');

$stmt = $_db->prepare('UPDATE user SET active = 0 WHERE user_id = ?');
$stmt->execute([$id]);

temp('info', 'Member disabled.');
redirect('index.php');
