<?php
require '../../_base.php';
require '../voucher/_voucher_expire.php';
auth('admin');

apply_voucher_expiry($_db);

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

// The role filter must be parenthesised against the OR'd search terms,
// otherwise AND/OR precedence lets admin accounts leak into the member list.
$sql = "SELECT * FROM user
        WHERE role = 'member'
          AND (username LIKE ? OR name LIKE ? OR email LIKE ?)
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
        'label'   => fn($row) => $row->active ? 'Disable member' : 'Activate member',
        'icon'    => fn($row) => $row->active ? 'activate.png' : 'disable.png',
        'method'  => 'post',
        'url'     => fn($row) => ($row->active ? 'member-disable.php' : 'member-activate.php') . '?id=' . $row->user_id,
        'confirm' => fn($row) => $row->active ? 'Disable this member?' : 'Activate this member?',
        'class'   => fn($row) => $row->active ? '' : 'admin-action-button--inactive',
    ],
];
$adminBulkSelect = [
    'key'          => 'user_id',
    'storageKey'   => 'bulk-select-members',
    'selectAllUrl' => 'member-ids.php',
    'statusKey'    => 'active',
    'actions'      => [
        [
            'label'     => 'Disable',
            'icon'      => 'disable.png',
            'url'       => 'member-bulk-disable.php',
            'confirm'   => 'Disable the selected members?',
            'countWhen' => 1,
        ],
        [
            'label'     => 'Activate',
            'icon'      => 'activate.png',
            'url'       => 'member-bulk-activate.php',
            'confirm'   => 'Activate the selected members?',
            'class'     => 'admin-bulk-bar__action--green',
            'countWhen' => 0,
        ],
        [
            'label'   => 'Delete',
            'icon'    => 'delete.png',
            'url'     => 'member-bulk-delete.php',
            'confirm' => 'Delete the selected members? Members with orders will be skipped.',
        ],
    ],
];
include '../admin_table.php';

include '../../_foot.php';
