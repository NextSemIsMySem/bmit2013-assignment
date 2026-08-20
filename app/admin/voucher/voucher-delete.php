<?php
require '../../_base.php';
auth('admin');

if (!is_post()) {
    redirect('vouchers.php');
}

$id = req('id');

$stmt = $_db->prepare('DELETE FROM voucher WHERE voucher_id = ?');
$stmt->execute([$id]);

temp('info', 'Voucher deleted.');
redirect('vouchers.php');
