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

if ($order->status !== 'paid' || order_has_pending_cancellation($order)) {
    temp('info', 'This order cannot be marked as shipped.');
    redirect('order-detail.php?id=' . $id);
}

$markShipped = $_db->prepare("UPDATE orders SET status = 'shipped' WHERE order_id = ? AND status = 'paid' AND cancellation_requested_at IS NULL");
$markShipped->execute([$id]);

if ($markShipped->rowCount() === 0) {
    temp('info', 'This order cannot be marked as shipped.');
    redirect('order-detail.php?id=' . $id);
}

temp('info', 'Order marked as shipped.');
redirect('order-detail.php?id=' . $id);
