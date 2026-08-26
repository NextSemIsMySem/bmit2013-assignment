<?php
require '../../_base.php';
require '_voucher_expire.php';
auth('admin');

apply_voucher_expiry($_db);

$fields = [
    'name'            => 'Name',
    'category_display' => 'Category',
    'start_date'      => 'Start Date',
    'end_date'        => 'End Date',
    'status'          => 'Status',
];

$sortableFields = ['name', 'start_date', 'end_date', 'status'];

$sort = req('sort', 'voucher_id');
in_array($sort, $sortableFields, true) || $sort = 'voucher_id';

$dir = req('dir', 'asc');
in_array($dir, ['asc', 'desc'], true) || $dir = 'asc';

$name = req('name', '');
$stmt = $_db->prepare(
    "SELECT v.*, c.name AS category_name
     FROM voucher_configuration v
     LEFT JOIN category c ON c.category_id = v.category_id
     WHERE v.name LIKE ? AND v.status != 'expired'
     ORDER BY $sort $dir"
);
$stmt->execute(["%$name%"]);
$vouchers = $stmt->fetchAll();

foreach ($vouchers as $voucher) {
    $voucher->category_display = $voucher->category_name ?? 'All';
}

$_title = 'Manage Vouchers';
include '../../_head.php';

$adminColumns = $fields;
$adminRows = $vouchers;
$adminPaginate = true;
$adminFilter = [
    'fields' => [],
];
$adminSearch = [
    'name' => 'name',
    'label' => 'Search vouchers',
    'placeholder' => 'Search by voucher name...',
];
$adminToolbarButtons = [
    ['label' => '+ Add Voucher Configuration', 'url' => 'voucher-create.php'],
    ['label' => 'Archived Voucher', 'url' => 'voucher-archived.php', 'class' => 'btn-gray'],
];
$adminActions = [
    [
        'label'  => 'Modify voucher',
        'icon'   => 'modify.png',
        'method' => 'get',
        'url'    => fn($row) => 'voucher-update.php?id=' . $row->voucher_id,
    ],
    [
        'label'   => fn($row) => $row->status === 'disabled' ? 'Activate voucher' : 'Disable voucher',
        'icon'    => fn($row) => $row->status === 'disabled' ? 'activate.png' : 'disable.png',
        'method'  => 'post',
        'url'     => fn($row) => ($row->status === 'disabled' ? 'voucher-activate.php' : 'voucher-disable.php') . '?id=' . $row->voucher_id,
        'confirm' => fn($row) => $row->status === 'disabled' ? 'Activate this voucher?' : 'Disable this voucher?',
        'class'   => fn($row) => $row->status === 'active' ? '' : 'admin-action-button--inactive',
        // An expired voucher's dates have already passed — flipping its
        // status wouldn't make it usable again, so there's nothing this
        // toggle can meaningfully do until its dates are extended via
        // "Modify voucher".
        'disabled' => fn($row) => $row->status === 'expired',
    ],
    [
        'label'   => 'Delete voucher',
        'icon'    => 'delete.png',
        'method'  => 'post',
        'url'     => fn($row) => 'voucher-delete.php?id=' . $row->voucher_id,
        'confirm' => 'Delete this voucher?',
    ],
];
$adminBulkSelect = [
    'key'          => 'voucher_id',
    'storageKey'   => 'bulk-select-vouchers',
    'selectAllUrl' => 'voucher-ids.php',
    'statusKey'    => 'status',
    'actions'      => [
        [
            'label'     => 'Disable',
            'icon'      => 'disable.png',
            'url'       => 'voucher-bulk-disable.php',
            'confirm'   => 'Disable the selected vouchers?',
            'countWhen' => 'active',
        ],
        [
            'label'     => 'Activate',
            'icon'      => 'activate.png',
            'url'       => 'voucher-bulk-activate.php',
            'confirm'   => 'Activate the selected vouchers?',
            'class'     => 'admin-bulk-bar__action--green',
            'countWhen' => 'disabled',
        ],
        [
            'label'   => 'Delete',
            'icon'    => 'delete.png',
            'url'     => 'voucher-bulk-delete.php',
            'confirm' => 'Delete the selected vouchers?',
        ],
    ],
];
include '../admin_table.php';

include '../../_foot.php';
