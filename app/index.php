<?php
require '_base.php';

$products = $_db->query(
    'SELECT product_id AS id, product_name AS name, price FROM product ORDER BY RAND() LIMIT 5'
)->fetchAll();

$_title = 'Recommended Products';
include '_head.php';
?>

<section class="welcome">
    <h2>Welcome to the Fitness & Gym Equipment Online Store</h2>
    <p>Browse our catalogue, manage your account, and check out securely.</p>
</section>
<br>
    <?php include 'product_template.php'; ?>

<?php
include '_foot.php';
?>
