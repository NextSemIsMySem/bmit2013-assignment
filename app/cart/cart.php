<?php
require '../_base.php';
auth('member');

$stmt = $_db->prepare(
    'SELECT p.product_id, p.product_name, p.price, p.stock, p.availability, ci.quantity
     FROM cart_item ci
     JOIN product p ON p.product_id = ci.product_id
     WHERE ci.user_id = ?
     ORDER BY ci.added_at DESC'
);
$stmt->execute([$_user->user_id]);
$items = $stmt->fetchAll();

$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item->price * $item->quantity;
}

$_title = 'My Cart';
include '../_head.php';
?>

<?php if ($items): ?>
    <section class="cart-list">
        <?php foreach ($items as $item): ?>
            <article class="cart-item" data-cart-item data-product-id="<?= $item->product_id ?>">
                <img src="/images/sport.png" alt="<?= encode($item->product_name) ?>">

                <div class="cart-item-info">
                    <a href="/product/product.php?id=<?= $item->product_id ?>"><?= encode($item->product_name) ?></a>
                    <p class="cart-item-price">RM <?= encode($item->price) ?> each</p>
                    <?php if (!$item->availability || $item->stock < 1): ?>
                        <p class="out-of-stock">No longer available &mdash; please remove this item.</p>
                    <?php elseif ($item->quantity > $item->stock): ?>
                        <p class="low-stock">Only <?= encode($item->stock) ?> left in stock.</p>
                    <?php endif; ?>
                </div>

                <div
                    class="quantity-box"
                    data-cart-quantity
                    data-product-id="<?= $item->product_id ?>"
                    data-stock="<?= $item->stock ?>"
                    aria-label="Quantity for <?= encode($item->product_name) ?>"
                >
                    <button
                        type="button"
                        data-quantity-minus
                        aria-label="Decrease quantity"
                        <?= $item->quantity <= 1 || $item->stock < 1 ? 'disabled' : '' ?>
                    >&minus;</button>
                    <input
                        type="number"
                        class="cart-quantity-value"
                        value="<?= $item->quantity ?>"
                        min="1"
                        max="<?= max(1, $item->stock) ?>"
                        aria-label="Quantity"
                        readonly
                    >
                    <button
                        type="button"
                        data-quantity-plus
                        aria-label="Increase quantity"
                        <?= $item->quantity >= $item->stock ? 'disabled' : '' ?>
                    >+</button>
                </div>

                <p class="cart-item-subtotal" data-cart-line-total>RM <?= encode(number_format($item->price * $item->quantity, 2)) ?></p>

                <button
                    class="cart-remove-button"
                    type="button"
                    data-post="cart-remove.php?product_id=<?= $item->product_id ?>"
                    data-confirm="Remove this item from your cart?"
                    aria-label="Remove <?= encode($item->product_name) ?> from cart"
                    title="Remove from cart"
                >
                    &times;
                </button>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="cart-summary">
        <p class="cart-summary-total">Subtotal: <strong data-cart-grand-total>RM <?= encode(number_format($subtotal, 2)) ?></strong></p>
        <a class="purchase-button cart-checkout-button" href="/cart/checkout.php">Proceed to Checkout</a>
    </section>
<?php else: ?>
    <p>Your cart is empty. <a href="/index.php">Continue shopping</a>.</p>
<?php endif; ?>

<?php
include '../_foot.php';
