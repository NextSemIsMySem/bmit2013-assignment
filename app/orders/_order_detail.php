<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

/*
 * Reusable order receipt: status timeline, payment/shipping info, line
 * items, totals. Caller loads $order (orders row), $items (order_product
 * joined to product), and $payment (payment row, may be null) and includes
 * this — same pattern as product/product_template.php for the product grid.
 */
if (!isset($order, $items, $payment)) {
    throw new LogicException('Order detail partial requires $order, $items, and $payment.');
}

require_once __DIR__ . '/_status.php';
require_once __DIR__ . '/../product/_stars.php';

$paymentMethods = [
    'cod' => 'Cash on Delivery',
    'card' => 'Card (Stripe)',
];

$total = $order->subtotal - $order->discount_amount + $order->shipping_fee;

$reviewsByProduct = [];
if ($order->status === 'completed') {
    $reviewStmt = $_db->prepare('SELECT product_id, rating FROM review WHERE order_id = ?');
    $reviewStmt->execute([$order->order_id]);
    foreach ($reviewStmt->fetchAll() as $review) {
        $reviewsByProduct[$review->product_id] = $review->rating;
    }
}
?>

<?php if ($order->status === 'cancelled'): ?>
    <div class="order-cancelled-banner">
        This order has been cancelled.
        <?php if (!empty($order->cancellation_reason)): ?>
            Reason: <?= encode($order->cancellation_reason) ?>
        <?php endif; ?>
    </div>
<?php elseif (order_has_pending_cancellation($order)): ?>
    <div class="order-request-banner">
        Cancellation requested on <?= encode(date('d M Y, g:ia', strtotime($order->cancellation_requested_at))) ?> &mdash; awaiting admin approval.
        <?php if (!empty($order->cancellation_reason)): ?>
            Reason: <?= encode($order->cancellation_reason) ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php if (order_has_cancellation_rejection($order)): ?>
        <div class="order-rejected-banner">
            Your cancellation request was rejected on <?= encode(date('d M Y, g:ia', strtotime($order->cancellation_rejected_at))) ?>.
            Admin's response: <?= encode($order->cancellation_rejection_reason) ?>
        </div>
    <?php endif; ?>
    <?php
        $steps = ['pending' => 'Order Placed', 'paid' => 'Paid', 'shipped' => 'Shipped', 'completed' => 'Completed'];
        $stepKeys = array_keys($steps);
        $currentIndex = array_search($order->status, $stepKeys, true);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;
    ?>
    <ol class="order-timeline">
        <?php foreach ($steps as $key => $label): ?>
            <?php $index = array_search($key, $stepKeys, true); ?>
            <li class="order-timeline-step<?= $index <= $currentIndex ? ' is-done' : '' ?><?= $index === $currentIndex ? ' is-current' : '' ?>">
                <span class="order-timeline-dot"></span>
                <span class="order-timeline-label"><?= encode($label) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<div class="order-detail-info">
    <section class="order-detail-card">
        <h3>Shipping Address</h3>
        <p>
            <?= encode($order->shipping_street) ?>,
            <?= encode($order->shipping_city) ?>,
            <?= encode($order->shipping_state) ?>
            <?= encode($order->shipping_postal_code) ?>,
            <?= encode($order->shipping_country) ?>
        </p>
    </section>

    <section class="order-detail-card">
        <h3>Payment</h3>
        <p><?= encode($paymentMethods[$payment->payment_method ?? ''] ?? ($payment->payment_method ?? '-')) ?></p>
        <p class="order-detail-payment-status">Status: <?= encode(ucfirst($payment->status ?? '-')) ?></p>
    </section>
</div>

<div class="order-detail-items">
    <?php foreach ($items as $item): ?>
        <div class="order-card-item">
            <img src="/images/sport.png" alt="">
            <div class="order-card-item-info">
                <p class="order-card-item-name"><?= encode($item->product_name) ?></p>
                <p class="order-card-item-qty">x<?= encode($item->quantity) ?> &middot; RM <?= encode(number_format($item->unit_price, 2)) ?> each</p>
                <?php if ($order->status === 'completed'): ?>
                    <?php if (isset($reviewsByProduct[$item->product_id])): ?>
                        <a class="order-item-review-link" href="/orders/review.php?order_id=<?= $order->order_id ?>&product_id=<?= $item->product_id ?>">
                            <?= render_stars($reviewsByProduct[$item->product_id]) ?> Edit Review
                        </a>
                    <?php else: ?>
                        <a class="order-item-review-link" href="/orders/review.php?order_id=<?= $order->order_id ?>&product_id=<?= $item->product_id ?>">
                            Rate Product
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <p class="order-card-item-price">RM <?= encode(number_format($item->final_price, 2)) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<div class="order-detail-totals">
    <p class="checkout-totals-line">Subtotal: RM <?= encode(number_format($order->subtotal, 2)) ?></p>
    <p class="checkout-totals-line">Discount: -RM <?= encode(number_format($order->discount_amount, 2)) ?></p>
    <p class="checkout-totals-line">Shipping: RM <?= encode(number_format($order->shipping_fee, 2)) ?></p>
    <p class="checkout-totals-line checkout-totals-grand">Total: RM <?= encode(number_format($total, 2)) ?></p>
</div>
