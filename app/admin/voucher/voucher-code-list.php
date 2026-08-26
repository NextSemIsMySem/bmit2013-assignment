<?php
require '../../_base.php';
auth('admin');

header('Content-Type: application/json');

$stmt = $_db->query('SELECT code FROM voucher');

echo json_encode(array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'code'));
