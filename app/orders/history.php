<?php
require '../_base.php';
require_once '../lib/SimplePager.php';
require '_status.php';
auth('member');

$statusTabs = [
    '' => 'All',
    'pending' => 'To Pay',
    'paid' => 'To Ship',
    'shipped' => 'To Receive',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];

$status = req('status', '');
array_key_exists($status, $statusTabs) || $status = '';

$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1;

$sql = 'SELECT o.order_id, o.subtotal, o.shipping_fee, o.discount_amount, o.status, o.cancellation_requested_at, o.created_at
        FROM orders o
        WHERE o.user_id = ?';
$params = [$_user->user_id];

if ($status === 'cancelled') {
    // Bucket pending cancellation requests in with confirmed cancellations —
    // the order's real status hasn't changed yet, but from the member's view
    // it's already "in" cancellation.
    $sql .= " AND (o.status = 'cancelled' OR o.cancellation_requested_at IS NOT NULL)";
} elseif ($status !== '') {
    $sql .= ' AND o.status = ? AND o.cancellation_requested_at IS NULL';
    $params[] = $status;
}

$sql .= ' ORDER BY o.created_at DESC';

$pager = new SimplePager($sql, $params, 5, $page);

$itemsStmt = $_db->prepare(
    'SELECT op.quantity, op.unit_price, p.product_name
     FROM order_product op
     JOIN product p ON p.product_id = op.product_id
     WHERE op.order_id = ?'
);

$_title = 'My Orders';
include '../_head.php';
?>

<nav class="order-tabs">
    <?php foreach ($statusTabs as $value => $label): ?>
        <a
            class="order-tab<?= $status === $value ? ' active' : '' ?>"
            href="?status=<?= encode($value) ?>"
        ><?= encode($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($pager->result): ?>
    <div class="order-list">
        <?php foreach ($pager->result as $order): ?>
            <?php
                $itemsStmt->execute([$order->order_id]);
                $orderItems = $itemsStmt->fetchAll();
                $total = $order->subtotal - $order->discount_amount + $order->shipping_fee;
            ?>
            <article class="order-card">
                <header class="order-card-header">
                    <span class="order-card-date"><?= encode(date('d M Y, g:ia', strtotime($order->created_at))) ?></span>
                    <span class="order-card-id">Order #<?= $order->order_id ?></span>
                    <span class="order-status <?= order_display_class($order) ?>"><?= encode(order_display_label($order)) ?></span>
                </header>

                <div class="order-card-items">
                    <?php foreach ($orderItems as $item): ?>
                        <div class="order-card-item">
                            <img src="/images/sport.png" alt="">
                            <div class="order-card-item-info">
                                <p class="order-card-item-name"><?= encode($item->product_name) ?></p>
                                <p class="order-card-item-qty">x<?= encode($item->quantity) ?></p>
                            </div>
                            <p class="order-card-item-price">RM <?= encode(number_format($item->unit_price, 2)) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <footer class="order-card-footer">
                    <p class="order-card-total">Order Total: <strong>RM <?= encode(number_format($total, 2)) ?></strong></p>
                    <div class="order-card-actions">
                        <?php if (in_array($order->status, ['completed', 'cancelled'], true)): ?>
                            <form method="post" action="buy-again.php">
                                <input type="hidden" name="order_id" value="<?= $order->order_id ?>">
                                <button class="order-card-button" type="submit">Buy Again</button>
                            </form>
                        <?php endif; ?>
                        <a class="order-card-button order-card-button--primary" href="detail.php?id=<?= $order->order_id ?>">View Order Details</a>
                    </div>
                </footer>
            </article>
        <?php endforeach; ?>
    </div>

    <?= $pager->html('status=' . urlencode($status)) ?>
<?php else: ?>
    <p>No orders in this category yet. <a href="/index.php">Start shopping</a>.</p>
<?php endif; ?>

<?php
include '../_foot.php';
