<?php
require '../_base.php';
require '_status.php';
auth('member');

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    redirect('/orders/history.php');
}

$orderStmt = $_db->prepare('SELECT * FROM orders WHERE order_id = ? AND user_id = ?');
$orderStmt->execute([$id, $_user->user_id]);
$order = $orderStmt->fetch();

if (!$order) {
    temp('info', 'Order not found.');
    redirect('/orders/history.php');
}

$itemsStmt = $_db->prepare(
    'SELECT op.quantity, op.unit_price, op.final_price, p.product_name
     FROM order_product op
     JOIN product p ON p.product_id = op.product_id
     WHERE op.order_id = ?'
);
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

$paymentStmt = $_db->prepare('SELECT * FROM payment WHERE order_id = ?');
$paymentStmt->execute([$id]);
$payment = $paymentStmt->fetch();

$canRequestCancellation = in_array($order->status, ['pending', 'paid'], true)
    && !order_has_pending_cancellation($order);

$_title = 'Order #' . $order->order_id;
include '../_head.php';
?>

<section class="order-confirmation">
    <?php include '_order_detail.php'; ?>

    <section class="buttons">
        <a class="checkout-back" href="/orders/history.php">Back to Order History</a>
        <?php if ($canRequestCancellation): ?>
            <button class="order-cancel-button" type="button" id="cancel-order-open">Cancel Order</button>
        <?php endif; ?>
    </section>
</section>

<?php if ($canRequestCancellation): ?>
    <dialog id="cancel-order-dialog" aria-labelledby="cancel-order-title">
        <form method="post" action="cancel.php">
            <input type="hidden" name="order_id" value="<?= $order->order_id ?>">
            <h2 id="cancel-order-title">Cancel this order?</h2>
            <p>Let us know why &mdash; this helps admin review the request.</p>

            <div class="cancel-reasons">
                <?php foreach (order_cancellation_reasons() as $reasonOption): ?>
                    <label class="cancel-reason-option">
                        <input
                            type="radio"
                            name="reason"
                            value="<?= encode($reasonOption) ?>"
                            required
                            <?= $reasonOption === 'Others' ? 'id="cancel-reason-others"' : '' ?>
                        >
                        <?= encode($reasonOption) ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <label class="sr-only" for="cancel-reason-note">Tell us more</label>
            <textarea
                id="cancel-reason-note"
                name="reason_note"
                placeholder="Tell us more (optional)"
                hidden
            ></textarea>

            <section class="buttons">
                <button type="submit">Confirm Cancellation</button>
                <button type="button" id="cancel-order-close">Never Mind</button>
            </section>
        </form>
    </dialog>
<?php endif; ?>

<?php
include '../_foot.php';
