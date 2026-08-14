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

$isWishlisted = false;
$productImages = [];
if ($product && $_user) {
    $wishlistCheck = $_db->prepare(
        'SELECT 1 FROM wishlist_item WHERE user_id = ? AND product_id = ?'
    );
    $wishlistCheck->execute([$_user->user_id, $product->product_id]);
    $isWishlisted = (bool) $wishlistCheck->fetchColumn();
}

if ($product) {
    $imagesStmt = $_db->prepare(
        'SELECT product_imageid FROM product_image WHERE product_id = ? ORDER BY product_imageid'
    );
    $imagesStmt->execute([$product->product_id]);
    $productImages = array_column($imagesStmt->fetchAll(), 'product_imageid');
}

$mainImage = $productImages ? '/photos/' . htmlspecialchars($productImages[0]) : '/images/sport.png';
$isBlocked = !$product || !$product->availability;

$_title = $product ? $product->product_name : 'Product Not Found';
$_hideHeading = true;
include '../_head.php';
?>

<?php if ($product && !$isBlocked): ?>
    <article class="product-detail">
        <div class="product-detail-gallery">
            <div class="product-detail-main">
                <img id="product-main-image" src="<?= $mainImage ?>" alt="<?= htmlspecialchars($product->product_name) ?>">
            </div>
            <?php if (count($productImages) > 1): ?>
                <div class="product-detail-controls">
                    <button type="button" class="product-detail-arrow product-detail-arrow--prev" id="product-image-prev" aria-label="Previous picture">&lt;</button>
                    <span class="product-detail-counter" id="product-image-counter">1/<?= count($productImages) ?></span>
                    <button type="button" class="product-detail-arrow product-detail-arrow--next" id="product-image-next" aria-label="Next picture">&gt;</button>
                </div>
            <?php endif; ?>
            <?php if (count($productImages) > 1): ?>
                <div class="product-detail-thumbs">
                    <?php foreach ($productImages as $image): ?>
                        <button
                            type="button"
                            class="product-detail-thumb"
                            data-image="/photos/<?= htmlspecialchars($image) ?>"
                        >
                            <img src="/photos/<?= htmlspecialchars($image) ?>" alt="">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <section class="product-detail-content">
            <div class="product-detail-title">
                <h2><?= htmlspecialchars($product->product_name) ?></h2>
                <button
                    class="favourite-button"
                    type="button"
                    data-favourite-star
                    data-login-required
                    data-product-id="<?= htmlspecialchars($product->product_id) ?>"
                    aria-pressed="<?= $isWishlisted ? 'true' : 'false' ?>"
                    aria-label="<?= $isWishlisted ? 'Remove' : 'Add' ?> <?= htmlspecialchars($product->product_name) ?> <?= $isWishlisted ? 'from' : 'to' ?> wishlist"
                >
                    <img src="/images/<?= $isWishlisted ? 'yellowstar.png' : 'emptystar.png' ?>" alt="">
                </button>
            </div>
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
                    data-login-required
                    <?= (!$product->availability || $product->stock < 1) ? 'disabled' : '' ?>
                >
                    Purchase
                </button>

                <button
                    class="add-cart-button"
                    type="button"
                    data-login-required
                    <?= (!$product->availability || $product->stock < 1) ? 'disabled' : '' ?>
                >
                    <img src="/images/cart.png" alt="">
                    <span>Add to Cart</span>
                </button>
            </div>

            <p class="quantity-warning" data-quantity-warning role="status" hidden>
                Maximum amount you can buy reached.
            </p>
        </section>
    </article>
<?php endif; ?>

<dialog id="product-unavailable-dialog" data-redirect="<?= $isBlocked ? '1' : '' ?>" aria-labelledby="product-unavailable-message">
    <p id="product-unavailable-message">Sorry, this product is unavailable.</p>
    <button type="button" id="product-unavailable-confirm">OK</button>
</dialog>

<?php include '../_foot.php'; ?>
