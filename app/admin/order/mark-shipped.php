<?php
require '../../_base.php';
auth('admin');

if (!is_post()) {
    redirect('orders.php');
}

$id = filter_var(req('id'), FILTER_VALIDATE_INT);

if (!$id) {
    redirect('orders.php');
}

$stmt = $_db->prepare('SELECT order_id, status FROM orders WHERE order_id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    temp('info', 'Order not found.');
    redirect('orders.php');
}

if ($order->status !== 'paid') {
    temp('info', 'This order cannot be marked as shipped.');
    redirect('order-detail.php?id=' . $id);
}

$_db->prepare("UPDATE orders SET status = 'shipped' WHERE order_id = ?")->execute([$id]);

temp('info', 'Order marked as shipped.');
redirect('order-detail.php?id=' . $id);
