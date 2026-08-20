<?php
require '../../_base.php';
auth('admin');

$fields = [
    'code'            => 'Code',
    'category_display' => 'Category',
    'start_date'      => 'Start Date',
    'end_date'        => 'End Date',
    'status'          => 'Status',
];

$sortableFields = ['code', 'start_date', 'end_date', 'status'];

$sort = req('sort', 'voucher_id');
in_array($sort, $sortableFields, true) || $sort = 'voucher_id';

$dir = req('dir', 'asc');
in_array($dir, ['asc', 'desc'], true) || $dir = 'asc';

$code = req('code', '');
$stmt = $_db->prepare(
    "SELECT v.*, c.name AS category_name
     FROM voucher v
     LEFT JOIN category c ON c.category_id = v.category_id
     WHERE v.code LIKE ?
     ORDER BY $sort $dir"
);
$stmt->execute(["%$code%"]);
$vouchers = $stmt->fetchAll();

foreach ($vouchers as $voucher) {
    $voucher->category_display = $voucher->category_name ?? 'All';
}

$_title = 'Manage Vouchers';
include '../../_head.php';

$adminColumns = $fields;
$adminRows = $vouchers;
$adminPaginate = true;
$adminSearch = [
    'name' => 'code',
    'label' => 'Search vouchers',
    'placeholder' => 'Search by voucher code...',
];
$adminToolbarButtons = [
    ['label' => '+ Add Voucher', 'url' => 'voucher-create.php'],
];
$adminActions = [
    [
        'label'  => 'Modify voucher',
        'icon'   => 'modify.png',
        'method' => 'get',
        'url'    => fn($row) => 'voucher-update.php?id=' . $row->voucher_id,
    ],
    [
        'label'   => 'Delete voucher',
        'icon'    => 'disable.png',
        'method'  => 'post',
        'url'     => fn($row) => 'voucher-delete.php?id=' . $row->voucher_id,
        'confirm' => 'Delete this voucher?',
    ],
];
include '../admin_table.php';

include '../../_foot.php';
