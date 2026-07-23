<?php
require '_base.php';
require 'db.php';

$result = mysqli_query($conn, 'SELECT product_name AS name, price FROM Product ORDER BY RAND() LIMIT 5');

$_title = 'Recommended Products';
include '_head.php';

?>

<section class="welcome">
    <h2>Welcome to the Fitness & Gym Equipment Online Store</h2>
    <p>Browse our catalogue, manage your account, and check out securely.</p>
</section>
?>

<section class="product-grid">
    <?php if (mysqli_num_rows($result) > 0): ?>
        <?php while ($product = mysqli_fetch_assoc($result)): ?>
            <article class="product-card">
                <h2><?= htmlspecialchars($product['name']) ?></h2>
                <img src="/images/sport.png" alt="<?= htmlspecialchars($product['name']) ?>">
                <p>RM <?= htmlspecialchars($product['price']) ?></p>
                <button type="examine">Examine</button>
            </article>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No products available.</p>
    <?php endif; ?>
</section>

<?php
include '_foot.php';
