<?php
require '../../_base.php';
require '_voucher_expire.php';
auth('admin');

apply_voucher_expiry($_db);

header('Content-Type: application/json');

$stmt = $_db->query("SELECT voucher_id, status FROM voucher_configuration WHERE status = 'expired'");

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
