<?php
require '../../_base.php';
auth('superadmin');

require '../../lib/SimplePager.php';

$fields = [
    'user_id'     => 'Id',
    'username'    => 'Username',
    'name'        => 'Name',
    'email'       => 'Email',
    'role'        => 'Role',
    'status'      => 'Status',
    'created_at'  => 'Joined',
];

$sort = req('sort', 'user_id');
array_key_exists($sort, $fields) || $sort = 'user_id';
// `status` is a derived column, so sort by the underlying flag.
$sortColumn = $sort === 'status' ? 'active' : $sort;

$dir = req('dir', 'asc');
in_array($dir, ['asc', 'desc'], true) || $dir = 'asc';

$name = req('name', '');

// Matches admin_table.php's own per_page whitelist so the page-size
// selector there drives this SQL-level LIMIT correctly.
$pageSizes = [10, 25, 50, 100];
$pageSize = (int) ($_GET['per_page'] ?? 10);
in_array($pageSize, $pageSizes, true) || $pageSize = 10;

$page = (int) req('page', 1);
$page < 1 && $page = 1;

// The role filter must be parenthesised against the OR'd search terms,
// otherwise AND/OR precedence would let member accounts leak in.
$sql = "SELECT user_id, username, name, email, role, active, created_at,
               IF(active, 'Active', 'Disabled') AS status
        FROM user
        WHERE role IN ('admin','superadmin')
          AND (username LIKE ? OR name LIKE ? OR email LIKE ?)
        ORDER BY $sortColumn $dir";
$p = new SimplePager($sql, ["%$name%", "%$name%", "%$name%"], $pageSize, $page);

$_title = 'Manage Admins';
include '../../_head.php';
?>

<section class="buttons">
    <button type="button" data-get="create.php">Create Admin</button>
</section>

<?php
$adminColumns = $fields;
$adminRows = $p->result;
$adminTotal = $p->item_count;
$adminPage = $p->page;
$adminPaginate = true;
$adminSearch = [
    'name' => 'name',
    'label' => 'Search admins',
    'placeholder' => 'Search by name, username, or email...',
];
$adminActions = [
    [
        'label'  => 'View detail',
        'icon'   => 'search.png',
        'method' => 'get',
        'url'    => fn($row) => 'detail.php?id=' . $row->user_id,
    ],
    [
        'label'   => fn($row) => $row->active ? 'Disable admin' : 'Activate admin',
        'icon'    => fn($row) => $row->active ? 'activate.png' : 'disable.png',
        'method'  => 'post',
        'url'     => fn($row) => ($row->active ? 'admin-disable.php' : 'admin-activate.php') . '?id=' . $row->user_id,
        'confirm' => fn($row) => $row->active ? 'Disable this admin?' : 'Activate this admin?',
        'class'   => fn($row) => $row->active ? '' : 'admin-action-button--inactive',
    ],
];
$adminBulkSelect = [
    'key'          => 'user_id',
    'storageKey'   => 'bulk-select-administrators',
    'selectAllUrl' => 'admin-ids.php',
    'statusKey'    => 'active',
    'actions'      => [
        [
            'label'     => 'Disable',
            'icon'      => 'disable.png',
            'url'       => 'admin-bulk-disable.php',
            'confirm'   => 'Disable the selected administrators?',
            'countWhen' => 1,
        ],
        [
            'label'     => 'Activate',
            'icon'      => 'activate.png',
            'url'       => 'admin-bulk-activate.php',
            'confirm'   => 'Activate the selected administrators?',
            'class'     => 'admin-bulk-bar__action--green',
            'countWhen' => 0,
        ],
    ],
];
include '../admin_table.php';

include '../../_foot.php';
