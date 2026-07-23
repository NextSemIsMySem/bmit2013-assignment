<?php
require '../_base.php';

$name = trim($_GET['name'] ?? '');
$words = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
$words = array_values(array_unique(array_map('strtolower', $words)));
$searchTerms = $name === ''
    ? []
    : array_values(array_unique(array_merge([strtolower($name)], $words)));

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
    $queries = [
        'exact' => 'SELECT product_id AS id, product_name AS name, price
                    FROM product
                    WHERE product_name = ?
                    ORDER BY product_name',
        'starts' => 'SELECT product_id AS id, product_name AS name, price
                     FROM product
                     WHERE LEFT(product_name, CHAR_LENGTH(?)) = ?
                     ORDER BY product_name',
        'ends' => 'SELECT product_id AS id, product_name AS name, price
                   FROM product
                   WHERE RIGHT(product_name, CHAR_LENGTH(?)) = ?
                   ORDER BY product_name',
        'contains' => 'SELECT product_id AS id, product_name AS name, price
                       FROM product
                       WHERE LOCATE(?, product_name) > 0
                       ORDER BY product_name',
    ];

    foreach ($queries as $group => $sql) {
        $stmt = $_db->prepare($sql);

        foreach ($searchTerms as $term) {
            $params = in_array($group, ['starts', 'ends'], true)
                ? [$term, $term]
                : [$term];
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

        <?php if ($seenIds): ?>
            <?php
            $products = [];
            foreach ($resultGroups as $group) {
                $products = array_merge($products, $group['products']);
            }
            include '../product_template.php';
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
