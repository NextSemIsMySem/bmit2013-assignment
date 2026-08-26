<?php
require '../_base.php';
auth('member');

if (!is_post()) {
    redirect('/cart/cart.php');
}

$productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
$quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);

$referer = $_SERVER['HTTP_REFERER'] ?? '/cart/cart.php';
$fallbackUrl = $productId ? '/product/product.php?id=' . $productId : $referer;

if (!$productId) {
    temp('info', 'Invalid product.');
    redirect($fallbackUrl);
}

$stmt = $_db->prepare('SELECT product_id, stock, availability FROM product WHERE product_id = ?');
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product || !$product->availability || $product->stock < 1) {
    temp('info', 'This product is not available right now.');
    redirect($fallbackUrl);
}

$existingStmt = $_db->prepare('SELECT quantity FROM cart_item WHERE user_id = ? AND product_id = ?');
$existingStmt->execute([$_user->user_id, $productId]);
$existingQty = (int) $existingStmt->fetchColumn();

$newQty = min($product->stock, $existingQty + $quantity);

$upsert = $_db->prepare(
    'INSERT INTO cart_item (user_id, product_id, quantity)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE quantity = ?'
);
$upsert->execute([$_user->user_id, $productId, $newQty, $newQty]);

temp('info', 'Added to cart.');
redirect($fallbackUrl);
