<?php
require '../_base.php';
auth('member');

if (!is_post()) {
    redirect('/orders/history.php');
}

$orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);

if (!$orderId) {
    redirect('/orders/history.php');
}

$ownerCheck = $_db->prepare('SELECT 1 FROM orders WHERE order_id = ? AND user_id = ?');
$ownerCheck->execute([$orderId, $_user->user_id]);

if (!$ownerCheck->fetchColumn()) {
    temp('info', 'Order not found.');
    redirect('/orders/history.php');
}

$itemsStmt = $_db->prepare(
    'SELECT op.product_id, op.quantity, p.stock, p.availability
     FROM order_product op
     JOIN product p ON p.product_id = op.product_id
     WHERE op.order_id = ?'
);
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll();

$added = 0;
$skipped = 0;

foreach ($items as $item) {
    if (!$item->availability || $item->stock < 1) {
        $skipped++;
        continue;
    }

    $existingStmt = $_db->prepare('SELECT quantity FROM cart_item WHERE user_id = ? AND product_id = ?');
    $existingStmt->execute([$_user->user_id, $item->product_id]);
    $existingQty = (int) $existingStmt->fetchColumn();

    $newQty = min($item->stock, $existingQty + $item->quantity);

    $upsert = $_db->prepare(
        'INSERT INTO cart_item (user_id, product_id, quantity)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE quantity = ?'
    );
    $upsert->execute([$_user->user_id, $item->product_id, $newQty, $newQty]);
    $added++;
}

if ($added && $skipped) {
    temp('info', "Added $added item(s) to your cart. $skipped item(s) are no longer available.");
} elseif ($added) {
    temp('info', 'Items added to your cart.');
} else {
    temp('info', 'None of the items in that order are available right now.');
}

redirect('/cart/cart.php');
