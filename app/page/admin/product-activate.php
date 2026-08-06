<?php
require '../../_base.php';
auth('admin');

if (!is_post()) {
    redirect('products.php');
}

$id = req('id');

$stmt = $_db->prepare('UPDATE product SET availability = 1 WHERE product_id = ?');
$stmt->execute([$id]);

temp('info', 'Product activated.');
redirect('products.php');
