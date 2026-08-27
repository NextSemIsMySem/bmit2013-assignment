<?php
require '../../_base.php';
require '_voucher_expire.php';
auth('admin');

apply_voucher_expiry($_db);

header('Content-Type: application/json');

$stmt = $_db->query("SELECT voucher_id FROM voucher_configuration WHERE status = 'expired'");

echo json_encode(array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'voucher_id'));
