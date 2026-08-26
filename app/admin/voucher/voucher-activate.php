<?php
require '../../_base.php';
auth('admin');

if (!is_post()) {
    redirect('vouchers.php');
}

$id = req('id');

$stmt = $_db->prepare("UPDATE voucher_configuration SET status = 'active' WHERE voucher_id = ? AND status = 'disabled'");
$stmt->execute([$id]);

temp('info', 'Voucher activated.');
redirect('vouchers.php');
