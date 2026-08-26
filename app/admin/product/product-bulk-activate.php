<?php
require '../../_base.php';
auth('admin');

if (!is_post()) {
    redirect('products.php');
}

$ids = array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])));

if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $_db->prepare("UPDATE product SET availability = 1 WHERE product_id IN ($placeholders)");
    $stmt->execute($ids);
    temp('info', count($ids) . ' product' . (count($ids) === 1 ? '' : 's') . ' activated.');
} else {
    temp('info', 'No products selected.');
}

redirect('products.php');
