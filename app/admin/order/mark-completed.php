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

$stmt = $_db->prepare('SELECT order_id, status, cancellation_requested_at FROM orders WHERE order_id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    temp('info', 'Order not found.');
    redirect('orders.php');
}

if ($order->status !== 'shipped' || order_has_pending_cancellation($order)) {
    temp('info', 'This order cannot be marked as completed.');
    redirect('order-detail.php?id=' . $id);
}

$markCompleted = $_db->prepare("UPDATE orders SET status = 'completed' WHERE order_id = ? AND status = 'shipped' AND cancellation_requested_at IS NULL");
$markCompleted->execute([$id]);

if ($markCompleted->rowCount() === 0) {
    temp('info', 'This order cannot be marked as completed.');
    redirect('order-detail.php?id=' . $id);
}

temp('info', 'Order marked as completed.');
redirect('order-detail.php?id=' . $id);
