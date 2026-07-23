<?php
require '../_base.php';

$cat = $_GET['category'] ?? 'all';
$categories = [
    'dumbbells' => ['label' => 'Dumbbell', 'ids' => [1]],
    'protein_powder' => ['label' => 'Protein Powder', 'ids' => [2]],
    'supplements' => ['label' => 'Supplements', 'ids' => [3]],
    'other' => ['label' => 'Others', 'ids' => [4, 5]],
];

if ($cat === 'all') {
    $stmt = $_db->query(
        'SELECT product_id AS id, product_name AS name, price FROM product ORDER BY product_name'
    );
    $label = 'All Products';
} elseif (isset($categories[$cat])) {
    $category = $categories[$cat];
    $placeholders = implode(',', array_fill(0, count($category['ids']), '?'));
    $stmt = $_db->prepare(
        "SELECT product_id AS id, product_name AS name, price FROM product WHERE category_id IN ($placeholders) ORDER BY product_name"
    );
    $stmt->execute($category['ids']);
    $label = $category['label'];
} else {
    $cat = 'all';
    $label = 'All Products';
    $stmt = $_db->query(
        'SELECT product_id AS id, product_name AS name, price FROM product ORDER BY product_name'
    );
}

$_title = 'Category | ' . $label;
$products = $stmt->fetchAll();
include '../_head.php';
?>

<h2><?= htmlspecialchars($label) ?></h2>
<?php include '../product_template.php'; ?>

<?php
include '../_foot.php'
?>
