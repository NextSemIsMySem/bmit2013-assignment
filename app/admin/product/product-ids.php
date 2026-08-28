<?php
require '../../_base.php';
auth('admin');

header('Content-Type: application/json');

$name = req('name', '');
$stmt = $_db->prepare('SELECT product_id, availability FROM product WHERE product_name LIKE ?');
$stmt->execute(["%$name%"]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
