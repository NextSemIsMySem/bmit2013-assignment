<?php
require '../../_base.php';
auth('admin');

$fields = [
    'product_name'  => 'Product Name',
    'price'         => 'Price',
    'stock'         => 'Stock',
];

$sortableFields = ['product_name', 'price', 'stock'];

$sort = req('sort', 'product_name');
in_array($sort, $sortableFields, true) || $sort = 'product_name';

$dir = req('dir', 'asc');
in_array($dir, ['asc', 'desc'], true) || $dir = 'asc';

$name = req('name', '');
$priceMin = req('price_min', '');
$priceMax = req('price_max', '');
$weightMin = req('weight_min', '');
$weightMax = req('weight_max', '');
$stockMin = req('stock_min', '');
$stockMax = req('stock_max', '');
$availability = req('availability', '');

$conditions = ['product_name LIKE ?'];
$params = ["%$name%"];

if ($priceMin !== '') {
    $conditions[] = 'price >= ?';
    $params[] = (float) $priceMin;
}
if ($priceMax !== '') {
    $conditions[] = 'price <= ?';
    $params[] = (float) $priceMax;
}
if ($weightMin !== '') {
    $conditions[] = 'weight >= ?';
    $params[] = (float) $weightMin;
}
if ($weightMax !== '') {
    $conditions[] = 'weight <= ?';
    $params[] = (float) $weightMax;
}
if ($stockMin !== '') {
    $conditions[] = 'stock >= ?';
    $params[] = (int) $stockMin;
}
if ($stockMax !== '') {
    $conditions[] = 'stock <= ?';
    $params[] = (int) $stockMax;
}
if ($availability !== '') {
    $conditions[] = 'availability = ?';
    $params[] = (int) $availability;
}

$sql = 'SELECT product_id, product_name, price, weight, stock, availability
        FROM product
        WHERE ' . implode(' AND ', $conditions) . "
        ORDER BY $sort $dir";
$stmt = $_db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$hasOutOfStock = (bool) $_db->query('SELECT 1 FROM product WHERE stock <= 0 LIMIT 1')->fetchColumn();

foreach ($products as $product) {
    if ($product->stock <= 0) {
        $product->stock_alert = 'redalert.png';
        $product->stock_alert_label = 'Out of Stock';
    } elseif ($product->stock < 20) {
        $product->stock_alert = 'yellowalert.png';
        $product->stock_alert_label = 'Low Stock';
    } else {
        $product->stock_alert = null;
    }
}

$_title = 'Manage Products';
include '../../_head.php';

$adminColumns = $fields;
$adminRows = $products;
$adminPaginate = true;
$adminFilter = [
    'fields' => [
        ['name' => 'price_min', 'label' => 'Min Price (RM)', 'type' => 'number'],
        ['name' => 'price_max', 'label' => 'Max Price (RM)', 'type' => 'number'],
        ['name' => 'weight_min', 'label' => 'Min Weight (kg)', 'type' => 'number'],
        ['name' => 'weight_max', 'label' => 'Max Weight (kg)', 'type' => 'number'],
        ['name' => 'stock_min', 'label' => 'Min Stock', 'type' => 'number'],
        ['name' => 'stock_max', 'label' => 'Max Stock', 'type' => 'number'],
        [
            'name' => 'availability',
            'label' => 'Availability',
            'options' => ['1' => 'Available', '0' => 'Unavailable'],
        ],
    ],
];
$adminSearch = [
    'name' => 'name',
    'label' => 'Search products',
    'placeholder' => 'Search by product name...',
];
$adminToolbarButtons = [
    ['label' => '+ Add Product', 'url' => 'product-create.php'],
];
if ($hasOutOfStock) {
    $adminToolbarButtons[] = ['label' => 'Out Of Stock', 'url' => 'outofstock.php', 'icon' => 'redalert.png', 'class' => 'btn-red'];
}
$adminInlineIcon = ['column' => 'product_name', 'field' => 'stock_alert', 'label_field' => 'stock_alert_label'];
$adminActions = [
    [
        'label'  => 'Modify product',
        'icon'   => 'modify.png',
        'method' => 'get',
        'url'    => fn($row) => 'product-update.php?id=' . $row->product_id,
    ],
    [
        'label'   => fn($row) => $row->availability ? 'Disable product' : 'Activate product',
        'icon'    => fn($row) => $row->availability ? 'activate.png' : 'disable.png',
        'method'  => 'post',
        'url'     => fn($row) => ($row->availability ? 'product-disable.php' : 'product-activate.php') . '?id=' . $row->product_id,
        'confirm' => fn($row) => $row->availability ? 'Disable this product?' : 'Activate this product?',
        'class'   => fn($row) => $row->availability ? '' : 'admin-action-button--inactive',
    ],
];
$adminBulkSelect = [
    'key'          => 'product_id',
    'storageKey'   => 'bulk-select-products',
    'selectAllUrl' => 'product-ids.php',
    'statusKey'    => 'availability',
    'actions'      => [
        [
            'label'     => 'Disable',
            'icon'      => 'disable.png',
            'url'       => 'product-bulk-disable.php',
            'confirm'   => 'Disable the selected products?',
            'countWhen' => 1,
        ],
        [
            'label'     => 'Activate',
            'icon'      => 'activate.png',
            'url'       => 'product-bulk-activate.php',
            'confirm'   => 'Activate the selected products?',
            'class'     => 'admin-bulk-bar__action--green',
            'countWhen' => 0,
        ],
    ],
];
include '../admin_table.php';

include '../../_foot.php';
