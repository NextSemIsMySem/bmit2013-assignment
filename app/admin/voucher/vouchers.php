<?php
require '../../_base.php';
require '_voucher_expire.php';
auth('admin');

apply_voucher_expiry($_db);

$fields = [
    'name'            => 'Name',
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
$status = req('status', '');
$discountFilterOn = req('discount_filter_toggle') === 'on';
$discountType = req('discount_type') === 'fixed' ? 'fixed' : 'percentage';
$discountPercentageMin = req('discount_percentage_min', '');
$discountValueMin = req('discount_value_min', '');
$minSpendRequired = req('min_spend_toggle') === 'on';
$minSpendValue = req('min_spend_value', '');

$conditions = ['v.name LIKE ?', "v.status != 'expired'"];
$params = ["%$name%"];

if ($status !== '') {
    $conditions[] = 'v.status = ?';
    $params[] = $status;
}

if ($discountFilterOn) {
    $conditions[] = 'v.discount_type = ?';
    $params[] = $discountType;

    if ($discountType === 'percentage' && $discountPercentageMin !== '') {
        $conditions[] = 'v.discount_percentage >= ?';
        $params[] = (float) $discountPercentageMin;
    } elseif ($discountType === 'fixed' && $discountValueMin !== '') {
        $conditions[] = 'v.discount_value >= ?';
        $params[] = (float) $discountValueMin;
    }
}

if ($minSpendRequired) {
    if ($minSpendValue !== '' && is_numeric($minSpendValue)) {
        $conditions[] = 'v.minimum_spend >= ?';
        $params[] = (float) $minSpendValue;
    } else {
        $conditions[] = 'v.minimum_spend > 0';
    }
}

$sql = "SELECT v.*,
               (v.status = 'active' AND v.start_date > NOW()) AS is_pending,
               (v.start_date > NOW()) AS is_not_started,
               EXISTS (SELECT 1 FROM voucher vv WHERE vv.voucher_id = v.voucher_id AND vv.status = 'used') AS has_used_code
        FROM voucher_configuration v
        WHERE " . implode(' AND ', $conditions) . "
        ORDER BY $sort $dir";
$stmt = $_db->prepare($sql);
$stmt->execute($params);
$vouchers = $stmt->fetchAll();

$_title = 'Manage Vouchers';
include '../../_head.php';

$adminColumns = $fields;
$adminRows = $vouchers;
$adminPaginate = true;
$adminColumnDisplay = [
    'status' => fn($row) => $row->is_pending ? 'Pending' : ucfirst($row->status),
];
$adminColumnClass = [
    'status' => fn($row) => $row->is_pending
        ? 'admin-table-status--pending'
        : ($row->status === 'active' ? 'admin-table-status--active' : 'admin-table-status--disabled'),
];
$adminFilter = [
    'fields' => [
        [
            'name' => 'status',
            'label' => 'Status',
            'options' => ['active' => 'Active', 'disabled' => 'Disabled'],
        ],
        [
            'type' => 'toggle',
            'name' => 'discount_filter_toggle',
            'label' => 'Filter by discount type',
            'fields' => [
                [
                    'type' => 'discount',
                    'label' => 'Discount Type',
                    'type_name' => 'discount_type',
                    'percentage_name' => 'discount_percentage_min',
                    'amount_name' => 'discount_value_min',
                    'percentage_label' => 'Min Discount Percentage (%)',
                    'amount_label' => 'Min Discount Amount (RM)',
                ],
            ],
        ],
        [
            'type' => 'toggle',
            'name' => 'min_spend_toggle',
            'label' => 'Require a minimum spend',
            'fields' => [
                ['name' => 'min_spend_value', 'label' => 'Minimum Spend (RM)', 'type' => 'number'],
            ],
        ],
    ],
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
        'icon'    => fn($row) => $row->status === 'disabled' ? 'disable.png' : 'activate.png',
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
        // Deleting is permanent and cascades to every code under it, so it's
        // only offered once expired, or before its start date (either way no
        // code under it could possibly have been used) — matches the rule
        // voucher-delete.php enforces.
        'hidden'  => fn($row) => ($row->status !== 'expired' && !$row->is_not_started) || $row->has_used_code,
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
        // No bulk Delete here — voucher-bulk-delete.php only removes
        // expired vouchers, and this list never shows expired ones (see
        // Archived Vouchers for that).
    ],
];
include '../admin_table.php';

include '../../_foot.php';
