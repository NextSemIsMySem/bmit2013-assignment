<?php
require '../../_base.php';
require '_voucher_expire.php';
auth('admin');

apply_voucher_expiry($_db);
cleanup_stale_expired_vouchers($_db);

$stmt = $_db->query(
    "SELECT vc.*,
            EXISTS (SELECT 1 FROM voucher v WHERE v.voucher_id = vc.voucher_id AND v.status = 'used') AS has_used
     FROM voucher_configuration vc
     WHERE vc.status = 'expired'
     ORDER BY vc.end_date DESC"
);
$vouchers = $stmt->fetchAll();

$_title = 'Archived Vouchers';
$_hideHeading = true;
include '../../_head.php';
?>

<div class="admin-page-header">
    <h1>Archived Vouchers</h1>
    <a class="btn-dark" href="vouchers.php">&larr; Back to Vouchers</a>
</div>

<?php
$adminColumns = [
    'name'            => 'Name',
    'start_date'      => 'Start Date',
    'end_date'        => 'End Date',
];
$adminRows = $vouchers;
$adminPaginate = true;
$adminEmptyMessage = 'No archived vouchers. A voucher is archived once it expires, and automatically removed after sitting expired for 3 days.';
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
$adminBulkSelect = [
    'key'          => 'voucher_id',
    'storageKey'   => 'bulk-select-archived-vouchers',
    'selectAllUrl' => 'voucher-archived-ids.php',
    'actions'      => [
        [
            'label'   => 'Delete',
            'icon'    => 'delete.png',
            'url'     => 'voucher-bulk-delete.php',
            'confirm' => 'Delete the selected vouchers? Any with real order history will be skipped.',
        ],
    ],
];
include '../admin_table.php';

include '../../_foot.php';
