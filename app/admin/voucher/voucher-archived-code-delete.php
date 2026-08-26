<?php
require '../../_base.php';
auth('admin');

if (!is_post()) {
    redirect('voucher-archived.php');
}

$id = req('id');
$rowId = req('row_id');

$stmt = $_db->prepare("DELETE FROM voucher WHERE id = ? AND voucher_id = ? AND status != 'used'");
$stmt->execute([$rowId, $id]);

temp('info', 'Voucher code deleted.');
redirect('voucher-archived-detail.php?id=' . $id);
