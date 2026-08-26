<?php
require '../../_base.php';
require '_voucher_expire.php';
auth('admin');

apply_voucher_expiry($_db);

$id = req('id');
$stmt = $_db->prepare(
    "SELECT vc.*, c.name AS category_name
     FROM voucher_configuration vc
     LEFT JOIN category c ON c.category_id = vc.category_id
     WHERE vc.voucher_id = ? AND vc.status = 'expired'"
);
$stmt->execute([$id]);
$voucher = $stmt->fetch();

if (!$voucher) {
    redirect('voucher-archived.php');
}

$codesStmt = $_db->prepare('SELECT id, code, status FROM voucher WHERE voucher_id = ? ORDER BY id');
$codesStmt->execute([$id]);
$existingCodes = $codesStmt->fetchAll();

$categoryDisplay = $voucher->category_name ?? 'All';
$discountDisplay = $voucher->discount_type === 'percentage'
    ? number_format((float) $voucher->discount_percentage, 2) . '%'
    : 'RM ' . number_format((float) $voucher->discount_value, 2);

$_title = 'Archived Voucher';
include '../../_head.php';
?>

<div class="form">
    <div class="archived-field full-width-label"><label>Name</label><span><?= encode($voucher->name) ?></span></div>
    <div class="archived-field full-width-label"><label>Category</label><span><?= encode($categoryDisplay) ?></span></div>
    <div class="archived-field full-width-label"><label>Start Date</label><span><?= encode($voucher->start_date) ?></span></div>
    <div class="archived-field full-width-label"><label>End Date</label><span><?= encode($voucher->end_date) ?></span></div>
    <div class="archived-field full-width-label"><label>Discount</label><span><?= encode($discountDisplay) ?></span></div>
    <div class="archived-field full-width-label"><label>Minimum Spend</label><span>RM <?= number_format((float) $voucher->minimum_spend, 2) ?></span></div>
    <div class="archived-field full-width-label"><label>Status</label><span>Expired</span></div>

    <button type="button" class="btn-blue full-width-label" id="individual-voucher-btn">Individual Voucher Configuration</button>

    <dialog id="individual-voucher-dialog" aria-labelledby="individual-voucher-title">
        <p id="individual-voucher-title">Individual Voucher Configuration</p>
        <div class="individual-voucher-list" id="individual-voucher-list">
            <?php foreach ($existingCodes as $existingCode): ?>
                <?php $used = $existingCode->status === 'used'; ?>
                <div class="individual-voucher-row">
                    <span class="individual-voucher-row__code"><?= encode($existingCode->code) ?></span>
                    <div class="individual-voucher-row__buttons">
                        <span class="individual-voucher-row__status individual-voucher-row__status--<?= encode($existingCode->status) ?>">
                            <?= ucfirst(encode($existingCode->status)) ?>
                        </span>
                        <?php if (!$used): ?>
                            <button
                                type="button"
                                class="individual-voucher-row__toggle"
                                data-post="voucher-archived-code-delete.php?id=<?= encode($id) ?>&row_id=<?= encode($existingCode->id) ?>"
                                data-confirm="Delete this voucher code?"
                                title="Delete this code"
                            >
                                <img src="/images/delete.png" alt="Delete">
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="individual-voucher-dialog-actions">
            <button type="button" class="btn-dark" id="individual-voucher-close">Close</button>
        </div>
    </dialog>

    <section class="buttons">
        <button type="button" class="btn-dark" data-get="voucher-archived.php">Back</button>
    </section>
</div>

<script>
;(function () {
    const openBtn = document.getElementById('individual-voucher-btn');
    const closeBtn = document.getElementById('individual-voucher-close');
    const dialog = document.getElementById('individual-voucher-dialog');
    if (!openBtn || !dialog) return;

    openBtn.addEventListener('click', () => dialog.showModal());
    closeBtn?.addEventListener('click', () => dialog.close());
})();
</script>

<?php
include '../../_foot.php';
