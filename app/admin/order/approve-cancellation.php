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

// Atomically claim the cancellation before touching stock: the WHERE guard
// makes this succeed at most once even if two admins click Approve on the
// same order at the same time, and refuses an order that's already moved
// past pending/paid (e.g. shipped in another tab while this one was stale).
$claimStmt = $_db->prepare(
    "UPDATE orders SET status = 'cancelled', cancellation_requested_at = NULL
     WHERE order_id = ? AND cancellation_requested_at IS NOT NULL AND status IN ('pending', 'paid')"
);
$claimStmt->execute([$id]);

if ($claimStmt->rowCount() === 0) {
    $_db->rollBack();
    temp('info', 'This cancellation request could not be approved — it may have already been handled or the order has since moved on.');
    redirect('order-detail.php?id=' . $id);
}

// Approving releases the stock this order had reserved at checkout — same
// restock logic that member-side cancellation used before it was gated
// behind admin approval.
$itemsStmt = $_db->prepare('SELECT product_id, quantity FROM order_product WHERE order_id = ?');
$itemsStmt->execute([$id]);

$restockStmt = $_db->prepare('UPDATE product SET stock = stock + ? WHERE product_id = ?');
foreach ($itemsStmt->fetchAll() as $item) {
    $restockStmt->execute([$item->quantity, $item->product_id]);
}

$_db->commit();

temp('info', 'Cancellation approved. Order marked cancelled and stock restored.');
redirect('order-detail.php?id=' . $id);
