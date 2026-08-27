<?php
require '../../_base.php';
auth('admin');

header('Content-Type: application/json');

$name = req('name', '');
// Same role filter (and parenthesising) as index.php, so bulk "select all"
// can never pick up admin accounts.
$stmt = $_db->prepare(
    "SELECT user_id FROM user
     WHERE role = 'member'
       AND (username LIKE ? OR name LIKE ? OR email LIKE ?)"
);
$stmt->execute(["%$name%", "%$name%", "%$name%"]);

echo json_encode(array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'user_id'));
