<?php
require '../../_base.php';
auth('admin');

if (!is_post()) {
    redirect('vouchers.php');
}

$ids = array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])));

if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $_db->prepare("UPDATE voucher_configuration SET status = 'active' WHERE status = 'disabled' AND voucher_id IN ($placeholders)");
    $stmt->execute($ids);
    temp('info', 'Selected vouchers activated.');
} else {
    temp('info', 'No vouchers selected.');
}

redirect('vouchers.php');
