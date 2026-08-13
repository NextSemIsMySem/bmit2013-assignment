<?php
require '../_base.php';
auth('member');

if (!is_post()) {
    redirect('/cart/cart.php');
}

$productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
$quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);

if (!$productId || !$quantity) {
    temp('info', 'Invalid quantity.');
    redirect('/cart/cart.php');
}

$stmt = $_db->prepare(
    'SELECT p.stock
     FROM cart_item ci
     JOIN product p ON p.product_id = ci.product_id
     WHERE ci.user_id = ? AND ci.product_id = ?'
);
$stmt->execute([$_user->user_id, $productId]);
$stock = $stmt->fetchColumn();

if ($stock === false) {
    temp('info', 'That item is not in your cart.');
    redirect('/cart/cart.php');
}

$quantity = max(1, min((int) $stock, $quantity));

$update = $_db->prepare('UPDATE cart_item SET quantity = ? WHERE user_id = ? AND product_id = ?');
$update->execute([$quantity, $_user->user_id, $productId]);

temp('info', 'Cart updated.');
redirect('/cart/cart.php');
