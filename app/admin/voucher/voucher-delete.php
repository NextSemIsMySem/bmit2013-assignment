<?php
require '../../_base.php';
auth('admin');

if (!is_post()) {
    redirect('vouchers.php');
}

$id = req('id');

$configStmt = $_db->prepare('SELECT status, start_date FROM voucher_configuration WHERE voucher_id = ?');
$configStmt->execute([$id]);
$config = $configStmt->fetch();

// Deletable in two cases: it's expired (done, nothing left to protect), or
// it hasn't started yet (start_date still in the future, active or
// disabled) — either way no code under it could possibly have been used.
$notStarted = $config && strtotime($config->start_date) > time();

if (!$config || (!$notStarted && $config->status !== 'expired')) {
    temp('info', 'Cannot delete: this voucher is still active. Disable it and wait for it to expire first, or delete it before its start date.');
    redirect('vouchers.php');
}

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
