<?php
require '../../_base.php';
auth('admin');

$id = req('id');
$stmt = $_db->prepare('SELECT * FROM voucher WHERE voucher_id = ?');
$stmt->execute([$id]);
$voucher = $stmt->fetch();

if (!$voucher) {
    redirect('vouchers.php');
}

$categories = $_db->query('SELECT category_id, name FROM category ORDER BY name')->fetchAll();
$categoryOptions = [];
foreach ($categories as $category) {
    $categoryOptions[$category->category_id] = $category->name;
}

if (is_post()) {
    $code = req('code');
    $categoryToggle = req('category_toggle') === 'on';
    $categoryId = req('category_id');
    $startDate = req('start_date');
    $startTime = req('start_time', '00:00');
    $endDate = req('end_date');
    $endTime = req('end_time', '00:00');
    $discountType = req('discount_type', 'percentage');
    in_array($discountType, ['percentage', 'fixed'], true) || $discountType = 'percentage';
    $discountPercentage = req('discount_percentage');
    $discountValue = req('discount_value');
    $minimumSpendToggle = req('minimum_spend_toggle') === 'on';
    $minimumSpend = req('minimum_spend');

    $startDateTime = $startDate !== '' ? $startDate . ' ' . ($startTime !== '' ? $startTime : '00:00') . ':00' : '';
    $endDateTime = $endDate !== '' ? $endDate . ' ' . ($endTime !== '' ? $endTime : '00:00') . ':00' : '';

    if ($code === '') {
        $_err['code'] = 'Voucher code is required.';
    } elseif (strlen($code) > 50) {
        $_err['code'] = 'Voucher code must be at most 50 characters.';
    } else {
        $codeCheck = $_db->prepare('SELECT 1 FROM voucher WHERE code = ? AND voucher_id != ?');
        $codeCheck->execute([$code, $id]);
        if ($codeCheck->fetchColumn()) {
            $_err['code'] = 'This voucher code is already in use.';
        }
    }

    if ($categoryToggle && $categoryId === '') {
        $_err['category_id'] = 'Please select a category, or turn this off.';
    } elseif ($categoryId !== '' && !array_key_exists($categoryId, $categoryOptions)) {
        $_err['category_id'] = 'Please select a valid category.';
    }

    if ($startDate === '') {
        $_err['start_date'] = 'Start date is required.';
    }

    if ($endDate === '') {
        $_err['end_date'] = 'End date is required.';
    } elseif ($startDateTime !== '' && $endDateTime < $startDateTime) {
        $_err['end_date'] = 'End date/time must be on or after the start date/time.';
    }

    if ($discountType === 'percentage') {
        if ($discountPercentage === '') {
            $_err['discount_percentage'] = 'Discount percentage is required.';
        } elseif (!is_numeric($discountPercentage) || $discountPercentage <= 0 || $discountPercentage > 100) {
            $_err['discount_percentage'] = 'Please enter a percentage between 0 and 100.';
        }
    } else {
        if ($discountValue === '') {
            $_err['discount_value'] = 'Discount amount is required.';
        } elseif (!is_numeric($discountValue) || (float) $discountValue <= 0) {
            $_err['discount_value'] = 'Please enter a valid discount amount.';
        }
    }

    if ($minimumSpendToggle && $minimumSpend === '') {
        $_err['minimum_spend'] = 'Please enter a minimum spend, or turn this off.';
    } elseif ($minimumSpend !== '' && (!is_numeric($minimumSpend) || (float) $minimumSpend < 0)) {
        $_err['minimum_spend'] = 'Please enter a valid minimum spend.';
    }

    if (!$_err) {
        $stmt = $_db->prepare(
            'UPDATE voucher
             SET code = ?, category_id = ?, discount_type = ?, discount_value = ?, discount_percentage = ?, minimum_spend = ?, start_date = ?, end_date = ?
             WHERE voucher_id = ?'
        );
        $stmt->execute([
            $code,
            $categoryId !== '' ? $categoryId : null,
            $discountType,
            $discountType === 'fixed' ? $discountValue : null,
            $discountType === 'percentage' ? $discountPercentage : null,
            $minimumSpend !== '' ? $minimumSpend : 0,
            $startDateTime,
            $endDateTime,
            $id,
        ]);

        temp('info', 'Voucher updated.');
        redirect('vouchers.php');
    }
} else {
    // Pre-fill sticky form fields from the DB on first (GET) load.
    $_REQUEST['code'] = $voucher->code;
    $_REQUEST['category_id'] = $voucher->category_id;
    $_REQUEST['category_toggle'] = $voucher->category_id !== null ? 'on' : '';
    $_REQUEST['start_date'] = substr($voucher->start_date, 0, 10);
    $_REQUEST['start_time'] = substr($voucher->start_date, 11, 5);
    $_REQUEST['end_date'] = substr($voucher->end_date, 0, 10);
    $_REQUEST['end_time'] = substr($voucher->end_date, 11, 5);
    $_REQUEST['discount_type'] = $voucher->discount_type;
    $_REQUEST['discount_percentage'] = $voucher->discount_type === 'percentage' ? $voucher->discount_percentage : 0;
    $_REQUEST['discount_value'] = $voucher->discount_type === 'fixed' ? $voucher->discount_value : 0;
    $_REQUEST['minimum_spend'] = (float) $voucher->minimum_spend > 0 ? $voucher->minimum_spend : '';
    $_REQUEST['minimum_spend_toggle'] = (float) $voucher->minimum_spend > 0 ? 'on' : '';
}

$_title = 'Update Voucher';
include '../../_head.php';
?>

<form class="form" method="post">
    <?php html_text('code', 'Voucher Code', 'text', true); ?>

    <?php $categoryChecked = req('category_toggle') === 'on'; ?>
    <label class="toggle-field">
        <input type="checkbox" name="category_toggle" data-toggle-target="#category-field" <?= $categoryChecked ? 'checked' : '' ?>>
        <span class="toggle-switch"><span class="toggle-switch__thumb"></span></span>
        Restrict to a specific category
    </label>
    <div id="category-field" <?= $categoryChecked ? '' : 'hidden' ?>>
        <?php html_select('category_id', 'Category', $categoryOptions, true); ?>
    </div>

    <?php $minimumSpendChecked = req('minimum_spend_toggle') === 'on'; ?>
    <label class="toggle-field">
        <input type="checkbox" name="minimum_spend_toggle" data-toggle-target="#minimum-spend-field" <?= $minimumSpendChecked ? 'checked' : '' ?>>
        <span class="toggle-switch"><span class="toggle-switch__thumb"></span></span>
        Require a minimum spend
    </label>
    <div id="minimum-spend-field" <?= $minimumSpendChecked ? '' : 'hidden' ?>>
        <?php html_text('minimum_spend', 'Minimum Spend (RM)', 'text', true); ?>
    </div>

    <label for="start_date">Start Date <span class="required-star">*</span></label>
    <div class="datetime-pair">
        <input type="date" id="start_date" name="start_date" value="<?= encode(req('start_date')) ?>">
        <input type="time" id="start_time" name="start_time" value="<?= encode(req('start_time')) ?>">
    </div>
    <?= err('start_date') ?>

    <label for="end_date">End Date <span class="required-star">*</span></label>
    <div class="datetime-pair">
        <input type="date" id="end_date" name="end_date" value="<?= encode(req('end_date')) ?>">
        <input type="time" id="end_time" name="end_time" value="<?= encode(req('end_time')) ?>">
    </div>
    <?= err('end_date') ?>

    <?php $discountTypeSelected = req('discount_type', 'percentage'); ?>
    <label class="full-width-label">Discount Type</label>
    <label class="toggle-field toggle-field--discount">
        <span class="toggle-field__side-label">Discount Percentage</span>
        <input type="checkbox" name="discount_type" value="fixed" data-toggle-target=".discount-amount-row" data-toggle-target-off=".discount-percentage-row" <?= $discountTypeSelected === 'fixed' ? 'checked' : '' ?>>
        <span class="toggle-switch toggle-switch--discount"><span class="toggle-switch__thumb"></span></span>
        <span class="toggle-field__side-label">Discount Amount</span>
    </label>

    <label for="discount_percentage" class="discount-percentage-row" <?= $discountTypeSelected === 'fixed' ? 'hidden' : '' ?>>Discount Percentage (%) <span class="required-star">*</span></label>
    <input type="text" id="discount_percentage" name="discount_percentage" value="<?= encode(req('discount_percentage')) ?>" class="discount-percentage-row" <?= $discountTypeSelected === 'fixed' ? 'hidden' : '' ?>>
    <span class="err discount-percentage-row" <?= $discountTypeSelected === 'fixed' ? 'hidden' : '' ?>><?= isset($_err['discount_percentage']) ? encode($_err['discount_percentage']) : '' ?></span>

    <label for="discount_value" class="discount-amount-row" <?= $discountTypeSelected !== 'fixed' ? 'hidden' : '' ?>>Discount Amount (RM) <span class="required-star">*</span></label>
    <input type="text" id="discount_value" name="discount_value" value="<?= encode(req('discount_value')) ?>" class="discount-amount-row" <?= $discountTypeSelected !== 'fixed' ? 'hidden' : '' ?>>
    <span class="err discount-amount-row" <?= $discountTypeSelected !== 'fixed' ? 'hidden' : '' ?>><?= isset($_err['discount_value']) ? encode($_err['discount_value']) : '' ?></span>
    <section class="buttons">
        <button type="submit" class="btn-green">Save</button>
        <button type="button" class="btn-dark" data-get="vouchers.php">Cancel</button>
    </section>
</form>

<?php
include '../../_foot.php';
