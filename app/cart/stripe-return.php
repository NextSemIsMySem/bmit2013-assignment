<?php
require '../_base.php';
require '_order.php';
require '../_stripe_config.php';
require '../lib/stripe.php';
auth('member');

$sessionId = req('session_id');

if ($sessionId === '') {
    temp('info', 'Missing payment session.');
    redirect('/cart/checkout.php');
}

// Idempotency: if this session was already processed (refresh, back button,
// double callback), send them straight to the existing order instead of
// trying to create a second one.
$existingStmt = $_db->prepare(
    'SELECT o.order_id
     FROM payment p
     JOIN orders o ON o.order_id = p.order_id
     WHERE p.transaction_reference = ? AND o.user_id = ?'
);
$existingStmt->execute([$sessionId, $_user->user_id]);
$existingOrderId = $existingStmt->fetchColumn();

if ($existingOrderId) {
    redirect('/cart/order-confirmation.php?id=' . $existingOrderId);
}

$session = stripe_request('GET', 'checkout/sessions/' . urlencode($sessionId));

if ($session['code'] !== 200) {
    temp('info', 'We could not verify your payment. If you were charged, please contact support.');
    redirect('/cart/checkout.php');
}

if (($session['body']->payment_status ?? null) !== 'paid') {
    temp('info', 'Payment was not completed.');
    redirect('/cart/checkout.php');
}

$pending = $_SESSION['checkout_pending'] ?? null;

if (!$pending) {
    temp('info', "Payment succeeded, but we couldn't find your pending order. If you were charged, please contact support.");
    redirect('/index.php');
}

$items = array_map(fn($item) => (object) $item, $pending['items']);

$voucher = null;
if ($pending['voucher_id']) {
    $voucherStmt = $_db->prepare('SELECT * FROM voucher WHERE voucher_id = ?');
    $voucherStmt->execute([$pending['voucher_id']]);
    $voucher = $voucherStmt->fetch() ?: null;
}

try {
    $orderId = create_order_from_cart(
        $_db,
        $_user,
        $items,
        $pending['address'],
        'card',
        $voucher,
        $pending['discount_amount'],
        $pending['subtotal'],
        $pending['shipping_fee'],
        'success',
        $sessionId
    );

    unset($_SESSION['checkout_pending']);
    temp('info', 'Order placed successfully.');
    redirect('/cart/order-confirmation.php?id=' . $orderId);
} catch (RuntimeException $e) {
    // Payment already succeeded on Stripe's side but we can't fulfill it —
    // a real store would refund via the Stripe API here. Out of scope for
    // now, so surface it clearly instead of silently failing.
    temp('info', 'Payment received, but we could not reserve stock: ' . $e->getMessage() . ' Please contact support for a refund.');
    redirect('/index.php');
}
