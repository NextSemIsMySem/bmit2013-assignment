<?php
require '../../_base.php';
auth('admin');

require '../../lib/SimplePager.php';

$fields = [
    'user_id'    => 'Id',
    'username'   => 'Username',
    'name'       => 'Name',
    'email'      => 'Email',
    'role'       => 'Role',
    'created_at' => 'Joined',
];

$sort = req('sort', 'user_id');
array_key_exists($sort, $fields) || $sort = 'user_id';

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

$sql = "SELECT * FROM user
        WHERE username LIKE ? OR name LIKE ? OR email LIKE ?
        ORDER BY $sort $dir";
$p = new SimplePager($sql, ["%$name%", "%$name%", "%$name%"], $pageSize, $page);

$_title = 'Manage Members';
include '../../_head.php';

$adminColumns = $fields;
$adminRows = $p->result;
$adminTotal = $p->item_count;
$adminPage = $p->page;
$adminPaginate = true;
$adminSearch = [
    'name' => 'name',
    'label' => 'Search members',
    'placeholder' => 'Search by name, username, or email...',
];
$adminActions = [
    [
        'label'  => 'View member',
        'icon'   => 'search.png',
        'method' => 'get',
        'url'    => fn($row) => 'detail.php?id=' . $row->user_id,
    ],
    [
        'label'  => 'Edit member',
        'icon'   => 'modify.png',
        'method' => 'get',
        'url'    => fn($row) => 'update.php?id=' . $row->user_id,
    ],
    [
        'label'   => 'Delete member',
        'icon'    => 'disable.png',
        'method'  => 'post',
        'url'     => fn($row) => 'delete.php?id=' . $row->user_id,
        'confirm' => 'Delete this member?',
    ],
];
include '../admin_table.php';

include '../../_foot.php';
