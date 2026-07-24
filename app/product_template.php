<section class="product-grid<?= !empty($productGridClass) ? ' ' . htmlspecialchars($productGridClass) : '' ?>">
    <?php if ($products): ?>
        <?php foreach ($products as $product): ?>
            <article
                class="product-card"
                <?php if (isset($product->id)): ?>
                    data-product-id="<?= htmlspecialchars($product->id) ?>"
                <?php endif; ?>
            >
                <h2><?= htmlspecialchars($product->name) ?></h2>
                <img src="/images/sport.png" alt="<?= htmlspecialchars($product->name) ?>">
                <?php if (isset($product->price)): ?>
                    <p>RM <?= htmlspecialchars($product->price) ?></p>
                <?php endif; ?>
                <a class="examine-button" href="/page/product.php?id=<?= htmlspecialchars($product->id) ?>">
                    Examine
                </a>
            </article>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No products available.</p>
    <?php endif; ?>
</section>
