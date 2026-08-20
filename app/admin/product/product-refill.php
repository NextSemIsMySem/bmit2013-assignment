<?php
require '../../_base.php';
auth('admin');

if (!is_post()) {
    redirect('outofstock.php');
}

$id = req('id');
$quantity = max(1, (int) req('quantity', 1));

$stmt = $_db->prepare('UPDATE product SET stock = stock + ? WHERE product_id = ?');
$stmt->execute([$quantity, $id]);

temp('info', "Refilled by $quantity unit" . ($quantity === 1 ? '' : 's') . '.');

$refilledStmt = $_db->prepare('SELECT product_name, availability FROM product WHERE product_id = ?');
$refilledStmt->execute([$id]);
$refilledProduct = $refilledStmt->fetch();

if ($refilledProduct && !$refilledProduct->availability) {
    temp('activate_prompt', ['id' => $id, 'name' => $refilledProduct->product_name]);
    redirect('outofstock.php');
}

$remainingStmt = $_db->prepare('SELECT 1 FROM product WHERE stock <= 0 LIMIT 1');
$remainingStmt->execute();
$hasRemaining = (bool) $remainingStmt->fetchColumn();

redirect($hasRemaining ? 'outofstock.php' : 'products.php');
