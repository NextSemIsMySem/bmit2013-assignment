<?php
require '../../_base.php';
require '../../orders/_status.php';
auth('admin');

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    redirect('orders.php');
}

$orderStmt = $_db->prepare(
    'SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.username AS customer_username
     FROM orders o
     JOIN user u ON u.user_id = o.user_id
     WHERE o.order_id = ?'
);
$orderStmt->execute([$id]);
$order = $orderStmt->fetch();

if (!$order) {
    temp('info', 'Order not found.');
    redirect('orders.php');
}

$itemsStmt = $_db->prepare(
    'SELECT op.product_id, op.quantity, op.unit_price, op.final_price, p.product_name
     FROM order_product op
     JOIN product p ON p.product_id = op.product_id
     WHERE op.order_id = ?'
);
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

$paymentStmt = $_db->prepare('SELECT * FROM payment WHERE order_id = ?');
$paymentStmt->execute([$id]);
$payment = $paymentStmt->fetch();

$hasPendingCancellation = order_has_pending_cancellation($order);

$canMarkPaid = !$hasPendingCancellation
    && $order->status === 'pending'
    && ($payment->payment_method ?? '') === 'cod';

$canMarkShipped = !$hasPendingCancellation && $order->status === 'paid';
$canMarkCompleted = !$hasPendingCancellation && $order->status === 'shipped';

$_title = 'Order #' . $order->order_id;
include '../../_head.php';
?>

<section class="order-confirmation admin-order-detail">
    <section class="order-detail-card admin-order-customer">
        <h3>Customer</h3>
        <p><?= encode($order->customer_name) ?> (<?= encode($order->customer_username) ?>)</p>
        <p><?= encode($order->customer_email) ?></p>
    </section>

    <?php include '../../orders/_order_detail.php'; ?>

    <section class="buttons admin-order-actions">
        <a class="checkout-back" href="/admin/order/orders.php">Back to Orders</a>

        <?php if ($hasPendingCancellation): ?>
            <button
                class="btn-red"
                type="button"
                data-post="approve-cancellation.php?id=<?= $order->order_id ?>"
                data-confirm="Approve this cancellation? Stock will be restored and the order marked cancelled."
            >Approve Cancellation</button>
            <button class="btn-green" type="button" id="reject-cancellation-open">Reject Cancellation</button>
        <?php endif; ?>

        <?php if ($canMarkPaid): ?>
            <button
                class="btn-green"
                type="button"
                data-post="mark-paid.php?id=<?= $order->order_id ?>"
                data-confirm="Mark this order as paid? Use this once cash has been collected on delivery."
            >Mark as Paid</button>
        <?php endif; ?>

        <?php if ($canMarkShipped): ?>
            <button
                class="btn-green"
                type="button"
                data-post="mark-shipped.php?id=<?= $order->order_id ?>"
                data-confirm="Mark this order as shipped?"
            >Mark as Shipped</button>
        <?php endif; ?>

        <?php if ($canMarkCompleted): ?>
            <button
                class="btn-green"
                type="button"
                data-post="mark-completed.php?id=<?= $order->order_id ?>"
                data-confirm="Mark this order as completed?"
            >Mark as Completed</button>
        <?php endif; ?>
    </section>
</section>

<?php if ($hasPendingCancellation): ?>
    <dialog id="reject-cancellation-dialog" aria-labelledby="reject-cancellation-title">
        <form method="post" action="reject-cancellation.php">
            <input type="hidden" name="id" value="<?= $order->order_id ?>">
            <h2 id="reject-cancellation-title">Reject this cancellation request?</h2>
            <p>Let the member know why &mdash; this will be shown on their order.</p>

            <label class="sr-only" for="reject-cancellation-reason">Reason</label>
            <textarea
                id="reject-cancellation-reason"
                name="reason"
                rows="3"
                required
                placeholder="e.g. Order has already been shipped"
            ></textarea>

            <section class="buttons">
                <button class="btn-red" type="submit">Reject Cancellation</button>
                <button type="button" id="reject-cancellation-close">Never Mind</button>
            </section>
        </form>
    </dialog>
<?php endif; ?>

<?php
include '../../_foot.php';
