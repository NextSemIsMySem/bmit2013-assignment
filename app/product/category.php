<?php
require '../_base.php';

$cat = $_GET['category'] ?? 'all';
$least = req('least', '');
$most = req('most', '');
$leastPrice = is_numeric($least) && (float) $least >= 0 ? (float) $least : null;
$mostPrice = is_numeric($most) && (float) $most >= 0 ? (float) $most : null;

if ($leastPrice !== null && $mostPrice !== null && $mostPrice < $leastPrice) {
    temp('info', 'Maximum price cannot be lower than minimum price.');
    $leastPrice = null;
    $mostPrice = null;
}

$categories = [
    'dumbbells' => ['label' => 'Dumbbell', 'ids' => [1]],
    'protein_powder' => ['label' => 'Protein Powder', 'ids' => [2]],
    'supplements' => ['label' => 'Supplements', 'ids' => [3]],
    'other' => ['label' => 'Others', 'ids' => [4, 5]],
];

if ($cat === 'all') {
    $where = [];
    $params = [];
    $label = 'All Products';
} elseif (isset($categories[$cat])) {
    $category = $categories[$cat];
    $placeholders = implode(',', array_fill(0, count($category['ids']), '?'));
    $where = ["category_id IN ($placeholders)"];
    $params = $category['ids'];
    $label = $category['label'];
} else {
    $cat = 'all';
    $label = 'All Products';
    $where = [];
    $params = [];
}

$where[] = 'availability = 1';

if ($leastPrice !== null) {
    $where[] = 'price >= ?';
    $params[] = $leastPrice;
}

if ($mostPrice !== null) {
    $where[] = 'price <= ?';
    $params[] = $mostPrice;
}

$sql = 'SELECT product_id AS id, product_name AS name, price, stock,
               (SELECT product_imageid FROM product_image WHERE product_id = product.product_id ORDER BY product_imageid LIMIT 1) AS image
        FROM product';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY product_name';

$stmt = $_db->prepare($sql);
$stmt->execute($params);

$_title = 'Category | ' . $label;
$_hideHeading = true;
$products = $stmt->fetchAll();
include '../_head.php';
?>

<h2><?= htmlspecialchars($label) ?></h2>

<form class="price-range" method="get">
    <input type="hidden" name="category" value="<?= htmlspecialchars($cat) ?>">
    <label>
        Least (RM)
        <input type="number" name="least" value="<?= htmlspecialchars($least) ?>" min="0" step="1" inputmode="decimal">
    </label>
    <label>
        Most (RM)
        <input type="number" name="most" value="<?= htmlspecialchars($most) ?>" min="0" step="1" inputmode="decimal">
    </label>
    <section class="price-range-actions">
        <button type="submit">Apply price range</button>
        <a href="?category=<?= htmlspecialchars($cat) ?>">Reset</a>
    </section>
</form>
<?php
$productGridClass = 'product-grid--category';
include 'product_template.php';
?>

<?php
include '../_foot.php'
?>
