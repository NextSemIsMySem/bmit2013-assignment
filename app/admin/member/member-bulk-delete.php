<?php
require '../../_base.php';
auth('admin');

if (!is_post()) {
    redirect('index.php');
}

$ids = array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])));

$deleted = 0;
$skipped = 0;

if ($ids) {
    $stmt = $_db->prepare("DELETE FROM user WHERE user_id = ? AND role = 'member'");
    foreach ($ids as $id) {
        if (is_exists('orders', 'user_id', $id)) {
            $skipped++;
            continue;
        }
        $stmt->execute([$id]);
        $deleted++;
    }
}

if ($deleted === 0 && $skipped === 0) {
    temp('info', 'No members selected.');
} else {
    $message = $deleted . ' member' . ($deleted === 1 ? '' : 's') . ' deleted.';
    if ($skipped > 0) {
        $message .= ' ' . $skipped . ' skipped (has orders).';
    }
    temp('info', $message);
}

redirect('index.php');
