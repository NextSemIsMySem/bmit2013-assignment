<?php
require '../../_base.php';
auth('admin');

header('Content-Type: application/json');

$name = req('name', '');
$stmt = $_db->prepare('SELECT product_id FROM product WHERE product_name LIKE ?');
$stmt->execute(["%$name%"]);

echo json_encode(array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'product_id'));
