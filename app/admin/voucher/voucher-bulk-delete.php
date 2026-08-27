<?php
require '../../_base.php';
auth('admin');

if (!is_post()) {
    redirect('vouchers.php');
}

$ids = array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])));

$deleted = 0;
$skipped = 0;

if ($ids) {
    $eligibleCheck = $_db->prepare(
        "SELECT 1 FROM voucher_configuration vc
         WHERE vc.voucher_id = ? AND vc.status = 'expired'
           AND NOT EXISTS (SELECT 1 FROM voucher v WHERE v.voucher_id = vc.voucher_id AND v.status = 'used')"
    );
    $deleteStmt = $_db->prepare('DELETE FROM voucher_configuration WHERE voucher_id = ?');

    foreach ($ids as $id) {
        $eligibleCheck->execute([$id]);
        if ($eligibleCheck->fetchColumn()) {
            $deleteStmt->execute([$id]);
            $deleted++;
        } else {
            $skipped++;
        }
    }
}

if ($deleted === 0 && $skipped === 0) {
    temp('info', 'No vouchers selected.');
} else {
    $message = $deleted . ' voucher' . ($deleted === 1 ? '' : 's') . ' deleted.';
    if ($skipped > 0) {
        $message .= ' ' . $skipped . ' skipped (not expired or already used).';
    }
    temp('info', $message);
}

redirect('vouchers.php');
