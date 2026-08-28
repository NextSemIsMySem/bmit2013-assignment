<?php
require '../../_base.php';
auth('superadmin');

if (!is_post()) {
    redirect('index.php');
}

$ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])))));
$disabled = 0;

foreach ($ids as $id) {
    if ($id === (int) $_user->user_id) {
        continue;
    }

    $stmt = $_db->prepare("SELECT role, active FROM user WHERE user_id = ? AND role IN ('admin','superadmin')");
    $stmt->execute([$id]);
    $target = $stmt->fetch();

    if (!$target || !$target->active) {
        continue;
    }

    if ($target->role === 'superadmin') {
        $activeSuperadmins = $_db->query("SELECT COUNT(*) FROM user WHERE role = 'superadmin' AND active = 1")->fetchColumn();
        if ($activeSuperadmins <= 1) {
            continue;
        }
    }

    $stmt = $_db->prepare('UPDATE user SET active = 0 WHERE user_id = ?');
    $stmt->execute([$id]);
    $disabled += $stmt->rowCount();
}

temp('info', $disabled . ' administrator' . ($disabled === 1 ? '' : 's') . ' disabled.');
redirect('index.php');
