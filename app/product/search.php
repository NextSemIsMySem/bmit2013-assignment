<?php
require '../_base.php';

$name = trim($_GET['name'] ?? '');
$words = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
$words = array_values(array_unique(array_map('strtolower', $words)));
$searchTerms = $name === ''
    ? []
    : array_values(array_unique(array_merge([strtolower($name)], $words)));

$least = req('least', '');
$most = req('most', '');
$leastPrice = is_numeric($least) && (float) $least >= 0 ? (float) $least : null;
$mostPrice = is_numeric($most) && (float) $most >= 0 ? (float) $most : null;

if ($leastPrice !== null && $mostPrice !== null && $mostPrice < $leastPrice) {
    temp('info', 'Maximum price cannot be lower than minimum price.');
    $leastPrice = null;
    $mostPrice = null;
}

$extraConditions = '';
$extraParams = [];
if ($leastPrice !== null) {
    $extraConditions .= ' AND price >= ?';
    $extraParams[] = $leastPrice;
}
if ($mostPrice !== null) {
    $extraConditions .= ' AND price <= ?';
    $extraParams[] = $mostPrice;
}

// Same category grouping as category.php, so "Others" means the same thing
// in both places.
$categories = [
    'equipment' => ['label' => 'Equipment', 'ids' => [1]],
    'protein_powder' => ['label' => 'Protein Powder', 'ids' => [2]],
    'supplements' => ['label' => 'Supplements', 'ids' => [3]],
    'other' => ['label' => 'Others', 'ids' => [4, 5]],
];

$category = req('category', 'all');
if ($category !== 'all' && !isset($categories[$category])) {
    $category = 'all';
}

if ($category !== 'all') {
    $categoryIds = $categories[$category]['ids'];
    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
    $extraConditions .= " AND category_id IN ($placeholders)";
    $extraParams = array_merge($extraParams, $categoryIds);
}

$resultGroups = [
    'exact' => [
        'heading' => 'Exact matches',
        'products' => [],
    ],
    'starts' => [
        'heading' => 'Starts with a search word',
        'products' => [],
    ],
    'ends' => [
        'heading' => 'Ends with a search word',
        'products' => [],
    ],
    'contains' => [
        'heading' => 'Contains a search word',
        'products' => [],
    ],
];

$seenIds = [];

if ($searchTerms) {
    $imageSubquery = '(SELECT product_imageid FROM product_image WHERE product_id = product.product_id ORDER BY product_imageid LIMIT 1) AS image';

    $queries = [
        'exact' => "SELECT product_id AS id, product_name AS name, price, stock, $imageSubquery
                    FROM product
                    WHERE product_name = ? AND availability = 1$extraConditions
                    ORDER BY product_name",
        'starts' => "SELECT product_id AS id, product_name AS name, price, stock, $imageSubquery
                     FROM product
                     WHERE LEFT(product_name, CHAR_LENGTH(?)) = ? AND availability = 1$extraConditions
                     ORDER BY product_name",
        'ends' => "SELECT product_id AS id, product_name AS name, price, stock, $imageSubquery
                   FROM product
                   WHERE RIGHT(product_name, CHAR_LENGTH(?)) = ? AND availability = 1$extraConditions
                   ORDER BY product_name",
        'contains' => "SELECT product_id AS id, product_name AS name, price, stock, $imageSubquery
                       FROM product
                       WHERE LOCATE(?, product_name) > 0 AND availability = 1$extraConditions
                       ORDER BY product_name",
    ];

    foreach ($queries as $group => $sql) {
        $stmt = $_db->prepare($sql);

        foreach ($searchTerms as $term) {
            $params = in_array($group, ['starts', 'ends'], true)
                ? [$term, $term]
                : [$term];
            $params = array_merge($params, $extraParams);
            $stmt->execute($params);

            foreach ($stmt->fetchAll() as $product) {
                if (isset($seenIds[$product->id])) {
                    continue;
                }

                $seenIds[$product->id] = true;
                $resultGroups[$group]['products'][] = $product;
            }
        }
    }
}

$_title = 'Search Results';
include '../_head.php';
?>

<section>

    <?php if ($name !== ''): ?>
        <p>Showing results for: <strong><?= htmlspecialchars($name) ?></strong></p>

        <form class="price-range" method="get">
            <input type="hidden" name="name" value="<?= htmlspecialchars($name) ?>">
            <div class="price-range__fields">
                <label>
                    Category
                    <select name="category">
                        <option value="all" <?= $category === 'all' ? 'selected' : '' ?>>All</option>
                        <?php foreach ($categories as $key => $info): ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= $category === $key ? 'selected' : '' ?>><?= htmlspecialchars($info['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Min (RM)
                    <input type="text" inputmode="decimal" name="least" value="<?= htmlspecialchars($least) ?>" data-decimal-input>
                </label>
                <label>
                    Max (RM)
                    <input type="text" inputmode="decimal" name="most" value="<?= htmlspecialchars($most) ?>" data-decimal-input>
                </label>
            </div>
            <section class="price-range-actions">
                <button type="submit">Apply price range</button>
                <a href="?name=<?= htmlspecialchars($name) ?>">Reset</a>
            </section>
        </form>

        <?php if ($seenIds): ?>
            <?php
            $products = [];
            foreach ($resultGroups as $group) {
                $products = array_merge($products, $group['products']);
            }
            $productGridClass = 'product-grid--search';
            include 'product_template.php';
            ?>
        <?php else: ?>
            <p>No matching products found.</p>
        <?php endif; ?>
    <?php else: ?>
        <p>Enter a product name to search.</p>
    <?php endif; ?>
</section>

<?php
include '../_foot.php';
?>
