<?php
require '../_base.php';
require '../cart/_stripe_session.php';
require '../_stripe_config.php';
require '../lib/stripe.php';
auth('member');

if (!is_post()) {
    redirect('/orders/history.php');
}

$orderId = filter_var(req('order_id'), FILTER_VALIDATE_INT);

if (!$orderId) {
    redirect('/orders/history.php');
}

$stmt = $_db->prepare(
    'SELECT o.order_id, o.subtotal, o.shipping_fee, o.discount_amount, o.status, p.payment_method
     FROM orders o
     JOIN payment p ON p.order_id = o.order_id
     WHERE o.order_id = ? AND o.user_id = ?'
);
$stmt->execute([$orderId, $_user->user_id]);
$order = $stmt->fetch();

if (!$order) {
    temp('info', 'Order not found.');
    redirect('/orders/history.php');
}

if ($order->status !== 'pending' || $order->payment_method !== 'card') {
    temp('info', 'This order cannot be paid for again.');
    redirect('/orders/detail.php?id=' . $orderId);
}

$total = $order->subtotal - $order->discount_amount + $order->shipping_fee;
$session = start_stripe_payment_for_order($orderId, $total, $_user->email);

if ($session['code'] === 200 && !empty($session['body']->url)) {
    $refStmt = $_db->prepare('UPDATE payment SET transaction_reference = ? WHERE order_id = ?');
    $refStmt->execute([$session['body']->id, $orderId]);
    redirect($session['body']->url);
}

error_log('Stripe retry-payment session error: ' . json_encode($session['body'] ?? null));
temp('info', 'Unable to start payment right now. Please try again.');
redirect('/orders/detail.php?id=' . $orderId);
