<?php
require '../_base.php';
require '_status.php';
auth('member');

if (!is_post()) {
    redirect('/orders/history.php');
}

$reasons = order_cancellation_reasons();

$orderId = filter_var(req('order_id'), FILTER_VALIDATE_INT);
$reason = req('reason');
$reasonNote = trim(req('reason_note'));

if (!$orderId) {
    redirect('/orders/history.php');
}

if (!in_array($reason, $reasons, true)) {
    temp('info', 'Please select a cancellation reason.');
    redirect('/orders/detail.php?id=' . $orderId);
}

if ($reason === 'Others' && $reasonNote === '') {
    temp('info', 'Please tell us more when selecting "Others".');
    redirect('/orders/detail.php?id=' . $orderId);
}

$stmt = $_db->prepare(
    'SELECT order_id, status, cancellation_requested_at FROM orders WHERE order_id = ? AND user_id = ?'
);
$stmt->execute([$orderId, $_user->user_id]);
$order = $stmt->fetch();

if (!$order) {
    temp('info', 'Order not found.');
    redirect('/orders/history.php');
}

if ($order->cancellation_requested_at) {
    temp('info', 'A cancellation request is already pending for this order.');
    redirect('/orders/detail.php?id=' . $orderId);
}

if (!in_array($order->status, ['pending', 'paid'], true)) {
    temp('info', 'This order can no longer be cancelled.');
    redirect('/orders/detail.php?id=' . $orderId);
}

$finalReason = ($reason === 'Others' && $reasonNote !== '') ? $reasonNote : $reason;
$finalReason = mb_substr($finalReason, 0, 255);

// This only records the request — the order's real status is left as-is
// until an admin approves or rejects it from admin/order/order-detail.php.
$update = $_db->prepare(
    'UPDATE orders SET cancellation_requested_at = NOW(), cancellation_reason = ? WHERE order_id = ?'
);
$update->execute([$finalReason, $orderId]);

temp('info', 'Cancellation requested. This is pending admin approval.');
redirect('/orders/detail.php?id=' . $orderId);
