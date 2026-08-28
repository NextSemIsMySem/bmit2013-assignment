<?php
require '../../_base.php';
auth('admin');

if (!is_post()) {
    redirect('voucher-archived.php');
}

$id = req('id');
$rowId = req('row_id');

// This endpoint is only linked to from the archived (expired) detail view —
// re-checked here rather than trusted, since a pending config's codes get
// deleted through voucher-update.php instead.
$stmt = $_db->prepare(
    "DELETE v FROM voucher v
     JOIN voucher_configuration vc ON vc.voucher_id = v.voucher_id
     WHERE v.id = ? AND v.voucher_id = ? AND v.status != 'used' AND vc.status = 'expired'"
);
$stmt->execute([$rowId, $id]);

temp('info', $stmt->rowCount() ? 'Voucher code deleted.' : 'This voucher code could not be deleted.');
redirect('voucher-archived-detail.php?id=' . $id);
