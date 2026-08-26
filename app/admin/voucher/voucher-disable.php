<?php
require '../../_base.php';
auth('admin');

if (!is_post()) {
    redirect('vouchers.php');
}

$id = req('id');

$stmt = $_db->prepare("UPDATE voucher_configuration SET status = 'disabled' WHERE voucher_id = ? AND status = 'active'");
$stmt->execute([$id]);

temp('info', 'Voucher disabled.');
redirect('vouchers.php');
