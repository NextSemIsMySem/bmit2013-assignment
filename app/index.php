<?php
require '_base.php';

$products = $_db->query(
    'SELECT product_id AS id, product_name AS name, price,
            (SELECT product_imageid FROM product_image WHERE product_id = product.product_id ORDER BY product_imageid LIMIT 1) AS image
     FROM product WHERE availability = 1 AND stock > 0 ORDER BY RAND() LIMIT 5'
)->fetchAll();

$_title = 'Recommended Products';
include '_head.php';
?>

<section class="welcome">
    <h2>Welcome to the Fitness & Gym Equipment Online Store</h2>
    <p>Browse our catalogue, manage your account, and check out securely.</p>
</section>
<br>
    <?php
    $productGridClass = 'product-grid--homepage';
    include 'product/product_template.php';
    ?>

<?php
include '_foot.php';
?>
