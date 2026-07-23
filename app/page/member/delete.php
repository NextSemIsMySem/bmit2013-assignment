<?php
require '../../_base.php';

if (!is_post()) {
    redirect('index.php');
}

$id = req('id');

if (is_exists('orders', 'user_id', $id)) {
    temp('info', 'Cannot delete: member has orders.');
    redirect('index.php');
}

$stmt = $_db->prepare('DELETE FROM user WHERE user_id = ?');
$stmt->execute([$id]);

temp('info', 'Member deleted.');
redirect('index.php');
