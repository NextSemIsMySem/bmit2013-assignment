<?php
require '../../_base.php';
auth('admin');

if (!is_post()) {
    redirect('products.php');
}

$id = req('id');
$image = req('image');

$stmt = $_db->prepare('DELETE FROM product_image WHERE product_id = ? AND product_imageid = ?');
$stmt->execute([$id, $image]);

if ($stmt->rowCount()) {
    @unlink(__DIR__ . '/../../photos/' . $image);
    temp('info', 'Picture removed.');
}

redirect('product-update.php?id=' . $id);
