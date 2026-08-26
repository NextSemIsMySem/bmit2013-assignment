<?php
require '../../_base.php';
require '../../orders/_status.php';
auth('admin');

if (!is_post()) {
    redirect('orders.php');
}

$id = filter_var(req('id'), FILTER_VALIDATE_INT);

if (!$id) {
    redirect('orders.php');
}

$stmt = $_db->prepare('SELECT * FROM orders WHERE order_id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    temp('info', 'Order not found.');
    redirect('orders.php');
}

if (!order_has_pending_cancellation($order)) {
    temp('info', 'This order has no pending cancellation request.');
    redirect('order-detail.php?id=' . $id);
}

$_db->beginTransaction();

// Approving releases the stock this order had reserved at checkout — same
// restock logic that member-side cancellation used before it was gated
// behind admin approval.
$itemsStmt = $_db->prepare('SELECT product_id, quantity FROM order_product WHERE order_id = ?');
$itemsStmt->execute([$id]);

$restockStmt = $_db->prepare('UPDATE product SET stock = stock + ? WHERE product_id = ?');
foreach ($itemsStmt->fetchAll() as $item) {
    $restockStmt->execute([$item->quantity, $item->product_id]);
}

$_db->prepare(
    "UPDATE orders SET status = 'cancelled', cancellation_requested_at = NULL WHERE order_id = ?"
)->execute([$id]);

$_db->commit();

temp('info', 'Cancellation approved. Order marked cancelled and stock restored.');
redirect('order-detail.php?id=' . $id);
