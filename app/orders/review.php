<?php
require '../_base.php';
auth('member');

$orderId = filter_var(req('order_id'), FILTER_VALIDATE_INT);
$productId = filter_var(req('product_id'), FILTER_VALIDATE_INT);

if (!$orderId || !$productId) {
    redirect('/orders/history.php');
}

$orderStmt = $_db->prepare('SELECT order_id, status FROM orders WHERE order_id = ? AND user_id = ?');
$orderStmt->execute([$orderId, $_user->user_id]);
$order = $orderStmt->fetch();

if (!$order) {
    temp('info', 'Order not found.');
    redirect('/orders/history.php');
}

if ($order->status !== 'completed') {
    temp('info', 'You can only review products from completed orders.');
    redirect('/orders/detail.php?id=' . $orderId);
}

$productStmt = $_db->prepare(
    'SELECT op.product_id, p.product_name
     FROM order_product op
     JOIN product p ON p.product_id = op.product_id
     WHERE op.order_id = ? AND op.product_id = ?'
);
$productStmt->execute([$orderId, $productId]);
$product = $productStmt->fetch();

if (!$product) {
    temp('info', 'That product was not part of this order.');
    redirect('/orders/detail.php?id=' . $orderId);
}

if (is_get()) {
    $existingStmt = $_db->prepare('SELECT rating, comment FROM review WHERE order_id = ? AND product_id = ?');
    $existingStmt->execute([$orderId, $productId]);
    $existing = $existingStmt->fetch();

    if ($existing) {
        $_REQUEST['rating'] = (string) $existing->rating;
        $_REQUEST['comment'] = (string) $existing->comment;
    }
}

if (is_post()) {
    $rating = filter_var(req('rating'), FILTER_VALIDATE_INT);
    $comment = trim(req('comment'));

    if (!$rating || $rating < 1 || $rating > 5) {
        $_err['rating'] = 'Please choose a rating from 1 to 5 stars.';
    }

    if (strlen($comment) > 2000) {
        $_err['comment'] = 'Comment must be at most 2000 characters.';
    }

    if (!$_err) {
        $upsert = $_db->prepare(
            'INSERT INTO review (order_id, product_id, user_id, rating, comment)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)'
        );
        $upsert->execute([$orderId, $productId, $_user->user_id, $rating, $comment !== '' ? $comment : null]);

        temp('info', 'Thanks for your review!');
        redirect('/orders/detail.php?id=' . $orderId);
    }
}

$_title = 'Rate ' . $product->product_name;
include '../_head.php';
?>

<div class="review-page">
    <form method="post" class="review-form">
        <fieldset class="star-rating-input">
            <legend class="sr-only">Your rating</legend>
            <?php for ($star = 5; $star >= 1; $star--): ?>
                <input
                    type="radio"
                    id="rating-<?= $star ?>"
                    name="rating"
                    value="<?= $star ?>"
                    <?= req('rating') === (string) $star ? 'checked' : '' ?>
                >
                <label for="rating-<?= $star ?>" title="<?= $star ?> star<?= $star === 1 ? '' : 's' ?>">&#9733;</label>
            <?php endfor; ?>
        </fieldset>
        <?= err('rating') ?>

        <label for="comment">Comment (optional)</label>
        <textarea id="comment" name="comment" rows="4"><?= encode(req('comment')) ?></textarea>
        <?= err('comment') ?>

        <section class="buttons">
            <button type="submit" class="place-order-button">Submit Review</button>
            <a href="/orders/detail.php?id=<?= $orderId ?>" class="checkout-back">Cancel</a>
        </section>
    </form>
</div>

<?php
include '../_foot.php';
