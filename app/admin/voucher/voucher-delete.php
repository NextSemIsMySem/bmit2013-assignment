<?php
require '../../_base.php';
auth('admin');

if (!is_post()) {
    redirect('vouchers.php');
}

$id = req('id');

$usedCheck = $_db->prepare("SELECT 1 FROM voucher WHERE voucher_id = ? AND status = 'used'");
$usedCheck->execute([$id]);

if ($usedCheck->fetchColumn()) {
    temp('info', 'Cannot delete: this voucher has been used in an order.');
    redirect('vouchers.php');
}

$stmt = $_db->prepare('DELETE FROM voucher_configuration WHERE voucher_id = ?');
$stmt->execute([$id]);

temp('info', 'Voucher deleted.');
redirect('vouchers.php');
