<?php
require '../../_base.php';
auth('superadmin');

header('Content-Type: application/json');

$name = req('name', '');
$stmt = $_db->prepare(
    "SELECT user_id, active FROM user
     WHERE role IN ('admin','superadmin')
       AND (username LIKE ? OR name LIKE ? OR email LIKE ?)"
);
$stmt->execute(["%$name%", "%$name%", "%$name%"]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
