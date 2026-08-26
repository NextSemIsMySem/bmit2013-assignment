<?php
require '../../_base.php';
auth('admin');

header('Content-Type: application/json');

$code = strtoupper(trim(req('code', '')));
$excludeId = (int) req('exclude_id', 0);

$stmt = $_db->prepare('SELECT 1 FROM voucher WHERE code = ? AND id != ?');
$stmt->execute([$code, $excludeId]);

echo json_encode(['occupied' => (bool) $stmt->fetchColumn()]);
