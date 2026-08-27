<?php
require '../../_base.php';
require '_voucher_expire.php';
auth('admin');

apply_voucher_expiry($_db);
cleanup_stale_expired_vouchers($_db);

$stmt = $_db->query(
    "SELECT vc.*, c.name AS category_name,
            EXISTS (SELECT 1 FROM voucher v WHERE v.voucher_id = vc.voucher_id AND v.status = 'used') AS has_used
     FROM voucher_configuration vc
     LEFT JOIN category c ON c.category_id = vc.category_id
     WHERE vc.status = 'expired'
     ORDER BY vc.end_date DESC"
);
$vouchers = $stmt->fetchAll();

foreach ($vouchers as $voucher) {
    $voucher->category_display = $voucher->category_name ?? 'All';
}

$_title = 'Archived Vouchers';
include '../../_head.php';

$adminColumns = [
    'name'            => 'Name',
    'category_display' => 'Category',
    'start_date'      => 'Start Date',
    'end_date'        => 'End Date',
];
$adminRows = $vouchers;
$adminPaginate = true;
$adminEmptyMessage = 'No archived vouchers. A voucher is archived once it expires, and automatically removed after sitting expired for 3 days.';
$adminToolbarButtons = [
    ['label' => '← Back to Vouchers', 'url' => 'vouchers.php', 'class' => 'btn-dark'],
];
$adminActions = [
    [
        'label'  => 'View voucher',
        'icon'   => 'search.png',
        'method' => 'get',
        'url'    => fn($row) => 'voucher-archived-detail.php?id=' . $row->voucher_id,
    ],
    [
        'label'   => 'Delete voucher',
        'icon'    => 'delete.png',
        'method'  => 'post',
        'url'     => fn($row) => 'voucher-delete.php?id=' . $row->voucher_id,
        'confirm' => 'Delete this voucher?',
        // A voucher with real usage history stays protected from deletion
        // here (same rule the 3-day auto-cleanup follows) — only unused
        // ones can be removed, whether manually now or automatically later.
        'disabled' => fn($row) => (bool) $row->has_used,
    ],
];
include '../admin_table.php';

include '../../_foot.php';
