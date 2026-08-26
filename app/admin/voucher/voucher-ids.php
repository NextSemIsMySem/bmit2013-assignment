<?php
require '../../_base.php';
require '_voucher_expire.php';
auth('admin');

apply_voucher_expiry($_db);

header('Content-Type: application/json');

$name = req('name', '');
$stmt = $_db->prepare("SELECT voucher_id FROM voucher_configuration WHERE name LIKE ? AND status != 'expired'");
$stmt->execute(["%$name%"]);

echo json_encode(array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'voucher_id'));
