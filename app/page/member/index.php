<?php
require '../../_base.php';
require '../../lib/SimplePager.php';

$fields = [
    'user_id'    => 'Id',
    'username'   => 'Username',
    'name'       => 'Name',
    'email'      => 'Email',
    'role'       => 'Role',
    'created_at' => 'Joined',
];

$name = req('name', '');

$sort = req('sort', 'user_id');
key_exists($sort, $fields) || $sort = 'user_id';

$dir = req('dir', 'asc');
in_array($dir, ['asc', 'desc']) || $dir = 'asc';

$page = (int) req('page', 1);
$page < 1 && $page = 1;

$sql = "SELECT * FROM user
        WHERE username LIKE ? OR name LIKE ? OR email LIKE ?
        ORDER BY $sort $dir";
$p = new SimplePager($sql, ["%$name%", "%$name%", "%$name%"], 10, $page);
$arr = $p->result;

$_title = 'Members';
include '../../_head.php';
?>

<form class="form" method="get">
    <?php html_text('name', 'Search'); ?>
    <section class="buttons">
        <button type="submit">Search</button>
        <a href="index.php">Reset</a>
    </section>
</form>

<p><?= $p->item_count ?> member(s) found.</p>

<table class="table">
    <?php table_headers($fields); ?>
    <?php foreach ($arr as $row): ?>
    <tr>
        <td><?= encode($row->user_id) ?></td>
        <td><?= encode($row->username) ?></td>
        <td><?= encode($row->name) ?></td>
        <td><?= encode($row->email) ?></td>
        <td><?= encode($row->role) ?></td>
        <td><?= encode($row->created_at) ?></td>
        <td>
            <button type="button" data-get="detail.php?id=<?= encode($row->user_id) ?>">View</button>
            <button type="button" data-get="update.php?id=<?= encode($row->user_id) ?>">Edit</button>
            <button type="button" data-post="delete.php?id=<?= encode($row->user_id) ?>">Delete</button>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<?= $p->html(http_build_query(['name' => $name, 'sort' => $sort, 'dir' => $dir])) ?>

<?php
include '../../_foot.php';
