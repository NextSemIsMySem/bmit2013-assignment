<?php
require '../../_base.php';
auth('admin');

if (!is_post()) {
    redirect('index.php');
}

$ids = array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])));

if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $_db->prepare("UPDATE user SET active = 0 WHERE user_id IN ($placeholders) AND role = 'member'");
    $stmt->execute($ids);
    temp('info', count($ids) . ' member' . (count($ids) === 1 ? '' : 's') . ' disabled.');
} else {
    temp('info', 'No members selected.');
}

redirect('index.php');
