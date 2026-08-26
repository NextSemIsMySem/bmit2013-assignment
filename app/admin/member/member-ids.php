<?php
require '../../_base.php';
auth('admin');

header('Content-Type: application/json');

$name = req('name', '');
$stmt = $_db->prepare(
    'SELECT user_id FROM user WHERE username LIKE ? OR name LIKE ? OR email LIKE ?'
);
$stmt->execute(["%$name%", "%$name%", "%$name%"]);

echo json_encode(array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'user_id'));
