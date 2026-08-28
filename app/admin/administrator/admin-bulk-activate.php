<?php
require '../../_base.php';
auth('superadmin');

if (!is_post()) {
    redirect('index.php');
}

$ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])))));

if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $_db->prepare("UPDATE user SET active = 1 WHERE user_id IN ($placeholders) AND role IN ('admin','superadmin')");
    $stmt->execute($ids);
    temp('info', $stmt->rowCount() . ' administrator' . ($stmt->rowCount() === 1 ? '' : 's') . ' activated.');
} else {
    temp('info', 'No administrators selected.');
}

redirect('index.php');
