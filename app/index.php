<?php
require '_base.php';

$products = $_db->query(
    'SELECT product_id AS id, product_name AS name, price,
            (SELECT product_imageid FROM product_image WHERE product_id = product.product_id ORDER BY product_imageid LIMIT 1) AS image
     FROM product WHERE availability = 1 AND stock > 0 ORDER BY RAND() LIMIT 5'
)->fetchAll();

$preferredProducts = [];
$preferredCategoryNames = [];
if ($_user) {
    $categoryStmt = $_db->prepare(
        'SELECT p.category_id, c.name, COUNT(*) AS cnt
         FROM wishlist_item wi
         JOIN product p ON p.product_id = wi.product_id
         JOIN category c ON c.category_id = p.category_id
         WHERE wi.user_id = ? AND p.category_id IS NOT NULL
         GROUP BY p.category_id, c.name'
    );
    $categoryStmt->execute([$_user->user_id]);

    $categoryCounts = [];
    foreach ($categoryStmt->fetchAll() as $row) {
        $categoryCounts[$row->category_id] = (int) $row->cnt;
        $preferredCategoryNames[$row->category_id] = $row->name;
    }

    if ($categoryCounts) {
        $totalWishlistItems = array_sum($categoryCounts);
        $slotsTotal = 5;

        // Proportional allocation of the 5 slots (largest-remainder method),
        // e.g. 1 item in category A + 1 in category B -> 50/50 -> a coin
        // flip decides who gets the leftover odd slot.
        $allocations = [];
        $fractions = [];
        foreach ($categoryCounts as $categoryId => $cnt) {
            $exact = ($cnt / $totalWishlistItems) * $slotsTotal;
            $allocations[$categoryId] = (int) floor($exact);
            $fractions[$categoryId] = $exact - floor($exact);
        }

        $remaining = $slotsTotal - array_sum($allocations);
        $categoryIds = array_keys($categoryCounts);
        shuffle($categoryIds);
        usort($categoryIds, fn($a, $b) => $fractions[$b] <=> $fractions[$a]);

        for ($i = 0; $i < $remaining; $i++) {
            $allocations[$categoryIds[$i]]++;
        }

        foreach ($allocations as $categoryId => $slots) {
            if ($slots < 1) {
                continue;
            }

            $categoryProductsStmt = $_db->prepare(
                'SELECT product_id AS id, product_name AS name, price,
                        (SELECT product_imageid FROM product_image WHERE product_id = product.product_id ORDER BY product_imageid LIMIT 1) AS image
                 FROM product
                 WHERE category_id = ? AND availability = 1 AND stock > 0
                 ORDER BY RAND() LIMIT ?'
            );
            $categoryProductsStmt->bindValue(1, $categoryId, PDO::PARAM_INT);
            $categoryProductsStmt->bindValue(2, $slots, PDO::PARAM_INT);
            $categoryProductsStmt->execute();
            $preferredProducts = array_merge($preferredProducts, $categoryProductsStmt->fetchAll());
        }
    }
}

$_title = 'Recommended Products';
$_hideHeading = true;
include '_head.php';
?>

    <h2 class="section-title" style="margin-top: 0;">Recommended Products</h2>
    <?php
    $productGridClass = 'product-grid--homepage';
    include 'product/product_template.php';
    ?>

<?php if ($preferredProducts): ?>
    <h2 class="section-title">User Preferred Category</h2>
    <p class="section-subtitle">
        You added this category in your wishlist:
        <strong><?= htmlspecialchars(implode(', ', $preferredCategoryNames)) ?></strong>
    </p>
    <?php
    $products = $preferredProducts;
    include 'product/product_template.php';
    ?>
<?php endif; ?>

<?php
include '_foot.php';
?>
