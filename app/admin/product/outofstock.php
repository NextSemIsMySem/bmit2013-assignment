<?php
require '../../_base.php';
auth('admin');

$stmt = $_db->prepare(
    'SELECT product_id, product_name, price, stock, availability
     FROM product
     WHERE stock <= 0
     ORDER BY product_name ASC'
);
$stmt->execute();
$products = $stmt->fetchAll();

$_title = 'Out of Stock Products';
include '../../_head.php';

if ($products):
    $adminColumns = [
        'product_name' => 'Product Name',
        'price'        => 'Price',
        'stock'        => 'Stock',
    ];
    $adminRows = $products;
    $adminPaginate = true;
    $adminToolbarButtons = [
        ['label' => '← Back to Products', 'url' => 'products.php', 'class' => 'btn-dark'],
    ];
    $adminActions = [];
    $adminActionsWidth = 220;
    $adminActionsRenderer = function ($row) {
        ob_start();
        ?>
        <form class="stock-refill" method="post" action="product-refill.php">
            <input type="hidden" name="id" value="<?= encode($row->product_id) ?>">
            <div class="quantity-box quantity-box--compact" data-refill-stepper>
                <button type="button" class="stock-refill__minus" data-refill-minus aria-label="Decrease quantity">&minus;</button>
                <input type="number" name="quantity" value="1" min="1" aria-label="Refill quantity" readonly>
                <button type="button" class="stock-refill__plus" data-refill-plus aria-label="Increase quantity">+</button>
            </div>
            <button type="submit" class="btn-green stock-refill__submit">Refill</button>
        </form>
        <?php
        return ob_get_clean();
    };
    include '../admin_table.php';
endif;
?>

<dialog id="outofstock-empty-dialog" data-redirect="<?= (!$products && !$activatePrompt) ? '1' : '' ?>" data-redirect-url="products.php" aria-labelledby="outofstock-empty-message">
    <p id="outofstock-empty-message">No products are currently out of stock.</p>
    <button type="button" id="outofstock-empty-confirm">OK</button>
</dialog>

<?php include '../../_foot.php'; ?>
