<?php
require '../../_base.php';
require '../../lib/SimplePager.php';
require '../../orders/_status.php';
auth('admin');

$fields = [
    'order_id'      => 'Order #',
    'customer_name' => 'Customer',
    'created_at'    => 'Date',
    'total'         => 'Total',
    'status'        => 'Status',
];

$sortColumns = [
    'order_id'      => 'o.order_id',
    'customer_name' => 'u.name',
    'created_at'    => 'o.created_at',
    'total'         => '(o.subtotal - o.discount_amount + o.shipping_fee)',
    'status'        => 'o.status',
];

$sort = req('sort', 'created_at');
array_key_exists($sort, $sortColumns) || $sort = 'created_at';

$dir = req('dir', 'desc');
in_array($dir, ['asc', 'desc'], true) || $dir = 'desc';

$q = req('q', '');
$status = req('status', '');

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(CAST(o.order_id AS CHAR) LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}

if ($status === 'cancel_requested') {
    $where[] = "o.cancellation_requested_at IS NOT NULL AND o.status != 'cancelled'";
} elseif (in_array($status, ['pending', 'paid', 'shipped', 'completed', 'cancelled'], true)) {
    $where[] = 'o.status = ?';
    $params[] = $status;
}

$sql = 'SELECT o.order_id, o.subtotal, o.discount_amount, o.shipping_fee, o.status,
               o.cancellation_requested_at, o.created_at, u.name AS customer_name
        FROM orders o
        JOIN user u ON u.user_id = o.user_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY {$sortColumns[$sort]} $dir";

$pageSizes = [10, 25, 50, 100];
$pageSize = (int) ($_GET['per_page'] ?? 10);
in_array($pageSize, $pageSizes, true) || $pageSize = 10;

$page = (int) req('page', 1);
$page < 1 && $page = 1;

$pager = new SimplePager($sql, $params, $pageSize, $page);

foreach ($pager->result as $order) {
    $order->total = number_format($order->subtotal - $order->discount_amount + $order->shipping_fee, 2);

    if (order_has_pending_cancellation($order)) {
        $order->cancellation_flag = 'redalert.png';
        $order->cancellation_flag_label = 'Cancellation requested';
    } else {
        $order->cancellation_flag = null;
    }

    $order->status = order_status_label($order->status);
}

$_title = 'Manage Orders';
include '../../_head.php';
?>

<form method="get" class="admin-order-status-filter">
    <?php foreach ($_GET as $key => $value): ?>
        <?php if ($key !== 'status' && $key !== 'page' && !is_array($value)): ?>
            <input type="hidden" name="<?= encode($key) ?>" value="<?= encode($value) ?>">
        <?php endif; ?>
    <?php endforeach; ?>
    <label for="status-filter">Status</label>
    <select id="status-filter" name="status" onchange="this.form.submit()">
        <option value="">All</option>
        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>To Pay (Pending)</option>
        <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>To Ship (Paid)</option>
        <option value="shipped" <?= $status === 'shipped' ? 'selected' : '' ?>>To Receive (Shipped)</option>
        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        <option value="cancel_requested" <?= $status === 'cancel_requested' ? 'selected' : '' ?>>Cancellation Requested</option>
    </select>
</form>

<?php
$adminColumns = $fields;
$adminRows = $pager->result;
$adminTotal = $pager->item_count;
$adminPage = $pager->page;
$adminPaginate = true;
$adminSearch = [
    'name' => 'q',
    'label' => 'Search orders',
    'placeholder' => 'Search by order # or customer...',
];
$adminInlineIcon = ['column' => 'status', 'field' => 'cancellation_flag', 'label_field' => 'cancellation_flag_label'];
$adminActions = [
    [
        'label'  => 'View order',
        'icon'   => 'search.png',
        'method' => 'get',
        'url'    => fn($row) => 'order-detail.php?id=' . $row->order_id,
    ],
];
include '../admin_table.php';

include '../../_foot.php';
