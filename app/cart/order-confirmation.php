<?php
require '../_base.php';
auth('member');

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    redirect('/index.php');
}

$orderStmt = $_db->prepare('SELECT * FROM orders WHERE order_id = ? AND user_id = ?');
$orderStmt->execute([$id, $_user->user_id]);
$order = $orderStmt->fetch();

if (!$order) {
    redirect('/index.php');
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

$_title = 'Order Confirmation';
include '../_head.php';
?>

<section class="order-confirmation">
    <p class="order-confirmation-message">Thank you! Your order #<?= $order->order_id ?> has been placed.</p>

    <?php include '../orders/_order_detail.php'; ?>

    <section class="buttons">
        <a class="purchase-button" href="/index.php">Continue Shopping</a>
        <a class="checkout-back" href="/orders/history.php">View Order History</a>
    </section>
</section>

<?php
include '../_foot.php';
