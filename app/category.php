<?php
require '_base.php';
require 'db.php';

$cat = $_GET['category'] ?? 'all';

if ($cat === 'all') {
    $stmt = mysqli_prepare($conn, 'SELECT name, price FROM products');
} else {
    $stmt = mysqli_prepare($conn, 'SELECT name, price FROM products WHERE category = ?');
    mysqli_stmt_bind_param($stmt, 's', $cat);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$_title = 'Category | ' . ucfirst(str_replace('_', ' ', $cat));
include '_head.php';
?>

<h2><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $cat))) ?></h2>
<ul>
    <?php while ($product = mysqli_fetch_assoc($result)): ?>
        <li><?= htmlspecialchars($product['name']) ?> - <?= htmlspecialchars($product['price']) ?></li>
    <?php endwhile; ?>
</ul>

<?php include '_foot.php';
