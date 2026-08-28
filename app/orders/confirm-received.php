<?php
require '../_base.php';
require '_status.php';
auth('member');

if (!is_post()) {
    redirect('/orders/history.php');
}

$orderId = filter_var(req('order_id'), FILTER_VALIDATE_INT);

if (!$orderId) {
    redirect('/orders/history.php');
}

$stmt = $_db->prepare('SELECT order_id, status, cancellation_requested_at FROM orders WHERE order_id = ? AND user_id = ?');
$stmt->execute([$orderId, $_user->user_id]);
$order = $stmt->fetch();

if (!$order) {
    temp('info', 'Order not found.');
    redirect('/orders/history.php');
}

if ($order->status !== 'shipped' || order_has_pending_cancellation($order)) {
    temp('info', 'This order cannot be marked as received.');
    redirect('/orders/detail.php?id=' . $orderId);
}

// Guarded the same way the admin mark-*.php endpoints are: the UPDATE itself
// re-checks the precondition, so this can't fire twice from a stale tab.
$confirmStmt = $_db->prepare(
    "UPDATE orders SET status = 'completed'
     WHERE order_id = ? AND user_id = ? AND status = 'shipped' AND cancellation_requested_at IS NULL"
);
$confirmStmt->execute([$orderId, $_user->user_id]);

if ($confirmStmt->rowCount() === 0) {
    temp('info', 'This order cannot be marked as received.');
    redirect('/orders/detail.php?id=' . $orderId);
}

temp('info', 'Thanks for confirming! Your order has been marked as completed.');
redirect('/orders/detail.php?id=' . $orderId);
