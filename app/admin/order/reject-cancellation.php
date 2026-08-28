<?php
require '../../_base.php';
require '../../orders/_status.php';
auth('admin');

if (!is_post()) {
    redirect('orders.php');
}

$id = filter_var(req('id'), FILTER_VALIDATE_INT);
$reason = trim(req('reason'));

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

if ($reason === '') {
    temp('info', 'Please provide a reason for rejecting this cancellation.');
    redirect('order-detail.php?id=' . $id);
}

$reason = mb_substr($reason, 0, 255);

// Keep the member's original cancellation_reason as context for why they
// asked — only the "pending" flag clears; the admin's reason is recorded
// alongside it. The WHERE guard makes this a no-op if the request was
// already approved/rejected elsewhere in the meantime (e.g. two admin tabs).
$rejectStmt = $_db->prepare(
    'UPDATE orders
     SET cancellation_requested_at = NULL,
         cancellation_rejected_at = NOW(),
         cancellation_rejection_reason = ?
     WHERE order_id = ? AND cancellation_requested_at IS NOT NULL'
);
$rejectStmt->execute([$reason, $id]);

if ($rejectStmt->rowCount() === 0) {
    temp('info', 'This cancellation request could not be rejected — it may have already been handled.');
    redirect('order-detail.php?id=' . $id);
}

temp('info', 'Cancellation request rejected. The order continues as normal.');
redirect('order-detail.php?id=' . $id);
