<?php
require '../_base.php';
require '../_stripe_config.php';
require '../lib/stripe.php';
require '../orders/_receipt_mail.php';
auth('member');

$sessionId = req('session_id');

if ($sessionId === '') {
    temp('info', 'Missing payment session.');
    redirect('/orders/history.php');
}

// The order already exists (created up front at checkout) — find it by the
// Stripe session tied to it, scoped to this user so a guessed session_id
// can't be used to poke at someone else's order.
$orderStmt = $_db->prepare(
    'SELECT o.order_id, o.status
     FROM orders o
     JOIN payment p ON p.order_id = o.order_id
     WHERE p.transaction_reference = ? AND o.user_id = ?'
);
$orderStmt->execute([$sessionId, $_user->user_id]);
$order = $orderStmt->fetch();

if (!$order) {
    temp('info', 'We could not find an order for this payment session.');
    redirect('/orders/history.php');
}

// Already confirmed (refresh, back button, double callback) — nothing more to do.
if ($order->status !== 'pending') {
    redirect('/cart/order-confirmation.php?id=' . $order->order_id);
}

$session = stripe_request('GET', 'checkout/sessions/' . urlencode($sessionId));

if ($session['code'] !== 200) {
    temp('info', 'We could not verify your payment. If you were charged, please contact support.');
    redirect('/orders/detail.php?id=' . $order->order_id);
}

if (($session['body']->payment_status ?? null) !== 'paid') {
    temp('info', 'Payment was not completed. You can try paying again from your order.');
    redirect('/orders/detail.php?id=' . $order->order_id);
}

$_db->beginTransaction();
$_db->prepare("UPDATE orders SET status = 'paid' WHERE order_id = ?")->execute([$order->order_id]);
$_db->prepare("UPDATE payment SET status = 'success' WHERE order_id = ?")->execute([$order->order_id]);
$_db->commit();

send_order_receipt_email($_db, $order->order_id);

temp('info', 'Payment successful.');
redirect('/cart/order-confirmation.php?id=' . $order->order_id);
