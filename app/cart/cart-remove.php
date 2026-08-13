<?php
require '../_base.php';
auth('member');

if (!is_post()) {
    redirect('/cart/cart.php');
}

$productId = filter_var($_REQUEST['product_id'] ?? null, FILTER_VALIDATE_INT);

if ($productId) {
    $delete = $_db->prepare('DELETE FROM cart_item WHERE user_id = ? AND product_id = ?');
    $delete->execute([$_user->user_id, $productId]);
    temp('info', 'Item removed from cart.');
}

redirect('/cart/cart.php');
