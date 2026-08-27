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

$stmt = $_db->prepare(
    'SELECT o.order_id, o.status, o.cancellation_requested_at, p.payment_method
     FROM orders o
     JOIN payment p ON p.order_id = o.order_id
     WHERE o.order_id = ?'
);
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    temp('info', 'Order not found.');
    redirect('orders.php');
}

if ($order->status !== 'pending' || $order->payment_method !== 'cod' || order_has_pending_cancellation($order)) {
    temp('info', 'This order cannot be marked as paid.');
    redirect('order-detail.php?id=' . $id);
}

$_db->beginTransaction();
$markPaid = $_db->prepare("UPDATE orders SET status = 'paid' WHERE order_id = ? AND status = 'pending' AND cancellation_requested_at IS NULL");
$markPaid->execute([$id]);

if ($markPaid->rowCount() === 0) {
    $_db->rollBack();
    temp('info', 'This order cannot be marked as paid.');
    redirect('order-detail.php?id=' . $id);
}

$_db->prepare("UPDATE payment SET status = 'success' WHERE order_id = ?")->execute([$id]);
$_db->commit();

temp('info', 'Order marked as paid.');
redirect('order-detail.php?id=' . $id);
