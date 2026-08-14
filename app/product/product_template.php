<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}
?>

<section class="product-grid<?= !empty($productGridClass) ? ' ' . htmlspecialchars($productGridClass) : '' ?>">
    <?php if ($products): ?>
        <?php foreach ($products as $product): ?>
            <?php
                $isUnavailable = isset($product->availability) && !$product->availability;
                $isSoldOut = !$isUnavailable && isset($product->stock) && $product->stock <= 0;
            ?>
            <article
                class="product-card"
                <?php if (isset($product->id)): ?>
                    data-product-id="<?= htmlspecialchars($product->id) ?>"
                <?php endif; ?>
            >
                <?php if (!empty($showWishlistDelete) && isset($product->id)): ?>
                    <button
                        class="round-delete-button"
                        type="button"
                        data-wishlist-delete
                        data-product-id="<?= htmlspecialchars($product->id) ?>"
                        aria-label="Remove <?= htmlspecialchars($product->name) ?> from wishlist"
                    >
                        <img src="/images/delete.png" alt="">
                    </button>
                <?php endif; ?>
                <h2><?= htmlspecialchars($product->name) ?></h2>
                <div class="product-card__photo">
                    <img
                        src="<?= !empty($product->image) ? '/photos/' . htmlspecialchars($product->image) : '/images/sport.png' ?>"
                        alt="<?= htmlspecialchars($product->name) ?>"
                    >
                    <?php if ($isUnavailable): ?>
                        <div class="product-card__photo-overlay"><span>Unavailable</span></div>
                    <?php elseif ($isSoldOut): ?>
                        <div class="product-card__photo-overlay"><span>Sold Out</span></div>
                    <?php endif; ?>
                </div>
                <?php if (isset($product->price)): ?>
                    <p>RM <?= htmlspecialchars($product->price) ?></p>
                <?php endif; ?>
                <a class="examine-button" href="/product/product.php?id=<?= htmlspecialchars($product->id) ?>">
                    <img src="/images/search.png" alt="">
                    Examine
                </a>
            </article>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No products available.</p>
    <?php endif; ?>
</section>
