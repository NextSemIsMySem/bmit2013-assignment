<?php
require '../../_base.php';
auth('admin');

if (!is_post()) {
    redirect('vouchers.php');
}

$ids = array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])));

if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $_db->prepare("DELETE FROM voucher_configuration WHERE voucher_id IN ($placeholders)");
    $stmt->execute($ids);
    temp('info', count($ids) . ' voucher' . (count($ids) === 1 ? '' : 's') . ' deleted.');
} else {
    temp('info', 'No vouchers selected.');
}

redirect('vouchers.php');
