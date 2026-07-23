<?php
require '../_base.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$product = null;

if ($id) {
    $stmt = $_db->prepare(
        'SELECT p.product_id,
                p.category_id,
                p.product_name,
                p.price,
                p.weight,
                p.description,
                p.stock,
                p.availability,
                c.name AS category_name
         FROM product AS p
         LEFT JOIN category AS c ON c.category_id = p.category_id
         WHERE p.product_id = ?'
    );
    $stmt->execute([$id]);
    $product = $stmt->fetch();
}

$_title = $product ? $product->product_name : 'Product Not Found';
include '../_head.php';
?>

<?php if ($product): ?>
    <article class="product-detail">
        <img src="/images/sport.png" alt="<?= htmlspecialchars($product->product_name) ?>">

        <section class="product-detail-content">
            <h2><?= htmlspecialchars($product->product_name) ?></h2>
            <p class="product-detail-price">RM <?= htmlspecialchars($product->price) ?></p>
            <p><?= htmlspecialchars($product->description) ?></p>

            <dl class="product-details-list">
                <div>
                    <dt>Category</dt>
                    <dd><?= htmlspecialchars($product->category_name ?? 'Unknown') ?></dd>
                </div>
                <div>
                    <dt>Weight</dt>
                    <dd><?= htmlspecialchars($product->weight) ?> kg</dd>
                </div>
                <div>
                    <dt>Stock</dt>
                    <dd><?= htmlspecialchars($product->stock) ?></dd>
                </div>
            </dl>

            <?php if ($product->stock > 0 && $product->stock < 30): ?>
                <p class="low-stock">Low in stock—only <?= htmlspecialchars($product->stock) ?> remaining.</p>
            <?php elseif ($product->stock < 1): ?>
                <p class="out-of-stock">Out of stock.</p>
            <?php endif; ?>

            <div
                class="purchase-controls"
                data-quantity-control
                data-stock="<?= htmlspecialchars($product->stock) ?>"
            >
                <div class="quantity-box" aria-label="Product quantity">
                    <button type="button" data-quantity-minus aria-label="Decrease quantity">−</button>
                    <input
                        type="number"
                        name="quantity"
                        value="<?= $product->stock > 0 ? 1 : 0 ?>"
                        min="<?= $product->stock > 0 ? 1 : 0 ?>"
                        max="<?= htmlspecialchars($product->stock) ?>"
                        aria-label="Quantity"
                        readonly
                    >
                    <button type="button" data-quantity-plus aria-label="Increase quantity">+</button>
                </div>

                <button
                    class="purchase-button"
                    type="button"
                    <?= (!$product->availability || $product->stock < 1) ? 'disabled' : '' ?>
                >
                    Purchase
                </button>
            </div>

            <p class="quantity-warning" data-quantity-warning role="status" hidden>
                Maximum amount you can buy reached.
            </p>
        </section>
    </article>
<?php else: ?>
    <p>The requested product could not be found.</p>
<?php endif; ?>

<?php include '../_foot.php'; ?>
