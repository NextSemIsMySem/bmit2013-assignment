<?php
require '../../_base.php';
auth('admin');

$fields = [
    'product_name' => 'Product Name',
    'price'        => 'Price',
    'stock'        => 'Stock',
];

$sort = req('sort', 'product_name');
array_key_exists($sort, $fields) || $sort = 'product_name';

$dir = req('dir', 'asc');
in_array($dir, ['asc', 'desc'], true) || $dir = 'asc';

$name = req('name', '');
$stmt = $_db->prepare(
    "SELECT product_id, product_name, price, stock, availability
     FROM product
     WHERE product_name LIKE ?
     ORDER BY $sort $dir"
);
$stmt->execute(["%$name%"]);
$products = $stmt->fetchAll();

$_title = 'Manage Products';
include '../../_head.php';

$adminColumns = $fields;
$adminRows = $products;
$adminPaginate = true;
$adminSearch = [
    'name' => 'name',
    'label' => 'Search products',
    'placeholder' => 'Search by product name...',
];
$adminActions = [
    [
        'label'  => 'Modify product',
        'icon'   => 'modify.png',
        'method' => 'get',
        'url'    => fn($row) => 'product-update.php?id=' . $row->product_id,
    ],
    [
        'label'   => fn($row) => $row->availability ? 'Disable product' : 'Activate product',
        'icon'    => fn($row) => $row->availability ? 'disable.png' : 'activate.png',
        'method'  => 'post',
        'url'     => fn($row) => ($row->availability ? 'product-disable.php' : 'product-activate.php') . '?id=' . $row->product_id,
        'confirm' => fn($row) => $row->availability ? 'Disable this product?' : 'Activate this product?',
        'class'   => fn($row) => $row->availability ? '' : 'admin-action-button--inactive',
    ],
];
include '../admin_table.php';

include '../../_foot.php';
