<?php
require '../../_base.php';
auth('admin');

$defaultStartDate = date('Y-m-d');
$defaultEndDate = date('Y-m-d', strtotime('+1 month'));
$defaultTime = date('H:i', strtotime('+1 hour'));

if (is_post()) {
    $quantity = max(1, (int) req('quantity', 1));
    $name = req('name');
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

    if ($quantity > 100) {
        $_err['quantity'] = 'Please generate at most 100 vouchers at a time.';
    }

    if ($name === '') {
        $_err['name'] = 'Name is required.';
    } elseif (strlen($name) > 100) {
        $_err['name'] = 'Maximum 100 characters.';
    }

    if ($startDate === '') {
        $_err['start_date'] = 'Start date is required.';
    }

    if ($endDate === '') {
        $_err['end_date'] = 'End date is required.';
    } elseif ($startDateTime !== '' && $endDateTime <= $startDateTime) {
        $_err['end_date'] = 'End date/time must be after the start date/time.';
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
        $configStmt = $_db->prepare(
            'INSERT INTO voucher_configuration (name, discount_type, discount_value, discount_percentage, minimum_spend, start_date, end_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $configStmt->execute([
            $name,
            $discountType,
            $discountType === 'fixed' ? $discountValue : null,
            $discountType === 'percentage' ? $discountPercentage : null,
            $minimumSpend !== '' ? $minimumSpend : 0,
            $startDateTime,
            $endDateTime,
        ]);
        $configId = (int) $_db->lastInsertId();

        $codeCharset = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $requestedCodes = (array) ($_POST['row_code'] ?? []);
        $codes = [];
        $codeCheck = $_db->prepare('SELECT 1 FROM voucher WHERE code = ?');

        for ($i = 0; $i < $quantity; $i++) {
            $candidate = strtoupper(trim((string) ($requestedCodes[$i] ?? '')));
            if (!preg_match('/^[' . $codeCharset . ']{1,50}$/', $candidate)) {
                $candidate = '';
            }

            // Prefer the code chosen in the "Individual Voucher Configuration"
            // dialog; fall back to a fresh random one if it's missing,
            // malformed, or collides with an existing/already-picked code.
            while ($candidate === '' || in_array($candidate, $codes, true) || ($codeCheck->execute([$candidate]) && $codeCheck->fetchColumn())) {
                $candidate = '';
                for ($j = 0; $j < 7; $j++) {
                    $candidate .= $codeCharset[random_int(0, strlen($codeCharset) - 1)];
                }
            }

            $codes[] = $candidate;
        }

        $rowStatuses = array_map(
            fn($status) => $status === 'disabled' ? 'disabled' : 'active',
            (array) ($_POST['row_status'] ?? [])
        );

        $stmt = $_db->prepare('INSERT INTO voucher (voucher_id, code, status) VALUES (?, ?, ?)');
        foreach ($codes as $i => $generatedCode) {
            $stmt->execute([$configId, $generatedCode, $rowStatuses[$i] ?? 'active']);
        }

        temp('info', $quantity === 1 ? 'Voucher added.' : "$quantity vouchers added.");
        redirect('vouchers.php');
    }
}

$_title = 'Add Voucher Configuration';
include '../../_head.php';
?>

<form class="form" method="post">
    <label for="name">Name <span class="required-star">*</span></label>
    <input type="text" id="name" name="name" value="<?= encode(req('name')) ?>" autocomplete="off">
    <?= err('name') ?>

    <label for="quantity">Quantity</label>
    <input type="text" id="quantity" name="quantity" value="<?= encode(req('quantity', '1')) ?>">
    <?= err('quantity') ?>
    <p class="field-note full-width-label">Each voucher gets a random 7-character code (0-9, A-Z) automatically. Set Quantity above 1 to create several at once with the same settings below.</p>

    <div id="individual-voucher-hidden-inputs"></div>
    <button type="button" class="btn-blue full-width-label" id="individual-voucher-btn">Individual Voucher Configuration</button>

    <dialog id="individual-voucher-dialog" aria-labelledby="individual-voucher-title">
        <p id="individual-voucher-title">Individual Voucher Configuration</p>
        <div class="individual-voucher-list" id="individual-voucher-list"></div>
        <button type="button" class="btn-blue individual-voucher-dialog-add" id="individual-voucher-add">+ Add Voucher</button>
        <div class="individual-voucher-dialog-actions">
            <button type="button" class="btn-red" id="individual-voucher-cancel">Cancel</button>
            <button type="button" class="btn-green" id="individual-voucher-close">Confirm</button>
        </div>
    </dialog>

    <dialog class="voucher-code-duplicate-dialog" id="voucher-code-duplicate-dialog" aria-labelledby="voucher-code-duplicate-message">
        <p id="voucher-code-duplicate-message">This voucher code is occupied.</p>
        <button type="button" id="voucher-code-duplicate-ok">OK</button>
    </dialog>

    <?php $minimumSpendChecked = is_post() ? req('minimum_spend_toggle') === 'on' : false; ?>
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
        <input type="date" id="start_date" name="start_date" value="<?= encode(req('start_date', $defaultStartDate)) ?>">
        <input type="time" id="start_time" name="start_time" value="<?= encode(req('start_time', $defaultTime)) ?>">
    </div>
    <?= err('start_date') ?>

    <label for="end_date">End Date <span class="required-star">*</span></label>
    <div class="datetime-pair">
        <input type="date" id="end_date" name="end_date" value="<?= encode(req('end_date', $defaultEndDate)) ?>">
        <input type="time" id="end_time" name="end_time" value="<?= encode(req('end_time', $defaultTime)) ?>">
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

<script>
;(function () {
    const quantityInput = document.getElementById('quantity');
    const openBtn = document.getElementById('individual-voucher-btn');
    const closeBtn = document.getElementById('individual-voucher-close');
    const cancelBtn = document.getElementById('individual-voucher-cancel');
    const addBtn = document.getElementById('individual-voucher-add');
    const dialog = document.getElementById('individual-voucher-dialog');
    const list = document.getElementById('individual-voucher-list');
    const hiddenInputs = document.getElementById('individual-voucher-hidden-inputs');
    const duplicateDialog = document.getElementById('voucher-code-duplicate-dialog');
    const duplicateOkBtn = document.getElementById('voucher-code-duplicate-ok');
    if (!quantityInput || !openBtn || !dialog || !list || !hiddenInputs) return;

    duplicateOkBtn?.addEventListener('click', () => duplicateDialog.close());

    const CODE_CHARSET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    let statuses = [];
    let codes = [];
    let snapshotStatuses = [];
    let snapshotCodes = [];
    let existingDbCodes = [];

    fetch('voucher-code-list.php')
        .then(res => res.json())
        .then(list => { existingDbCodes = list; })
        .catch(e => console.error('Failed to load existing voucher codes:', e));

    const clampQuantity = () => {
        let n = parseInt(quantityInput.value, 10);
        if (!Number.isFinite(n) || n < 1) n = 1;
        if (n > 100) n = 100;
        return n;
    };

    const generateCode = (exclude = codes.concat(existingDbCodes)) => {
        let code;
        do {
            code = '';
            for (let i = 0; i < 7; i++) {
                code += CODE_CHARSET[Math.floor(Math.random() * CODE_CHARSET.length)];
            }
        } while (exclude.includes(code));
        return code;
    };

    const syncHiddenInputs = () => {
        hiddenInputs.innerHTML = '';
        statuses.forEach((status, i) => {
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'row_status[]';
            statusInput.value = status;
            hiddenInputs.appendChild(statusInput);

            const codeInput = document.createElement('input');
            codeInput.type = 'hidden';
            codeInput.name = 'row_code[]';
            codeInput.value = codes[i];
            hiddenInputs.appendChild(codeInput);
        });
    };

    const renderRows = () => {
        const quantity = clampQuantity();
        while (statuses.length < quantity) statuses.push('active');
        while (codes.length < quantity) codes.push(generateCode());
        statuses.length = quantity;
        codes.length = quantity;

        list.innerHTML = '';
        statuses.forEach((status, i) => {
            const row = document.createElement('div');
            row.className = 'individual-voucher-row';

            const code = document.createElement('span');
            code.className = 'individual-voucher-row__code';
            code.textContent = codes[i];
            code.tabIndex = 0;
            code.title = 'Click to edit this code';

            const editCode = () => {
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'individual-voucher-row__code-input';
                input.value = codes[i];

                input.addEventListener('input', () => {
                    input.value = input.value.toUpperCase().replace(new RegExp('[^' + CODE_CHARSET + ']', 'g'), '');
                });

                const commit = async () => {
                    const value = input.value;
                    const isLocalDuplicate = codes.some((c, idx) => idx !== i && c === value);

                    if (isLocalDuplicate) {
                        duplicateDialog?.showModal();
                        renderRows();
                        return;
                    }

                    if (value.length === 0) {
                        renderRows();
                        return;
                    }

                    try {
                        const res = await fetch('voucher-code-check.php?code=' + encodeURIComponent(value));
                        if (!res.ok) throw new Error('Unexpected response: ' + res.status);
                        const data = await res.json();

                        if (data.occupied) {
                            duplicateDialog?.showModal();
                        } else {
                            codes[i] = value;
                        }
                    } catch (e) {
                        // Could not verify uniqueness (session expired,
                        // network error, etc.) — keep the previous code
                        // rather than silently accepting an unverified one.
                        console.error('Voucher code uniqueness check failed:', e);
                    }

                    renderRows();
                };

                input.addEventListener('blur', commit);
                input.addEventListener('keydown', e => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        input.blur();
                    } else if (e.key === 'Escape') {
                        renderRows();
                    }
                });

                code.replaceWith(input);
                input.focus();
                input.select();
            };

            code.addEventListener('click', editCode);
            code.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    editCode();
                }
            });

            row.appendChild(code);

            const buttons = document.createElement('div');
            buttons.className = 'individual-voucher-row__buttons';

            const randomize = document.createElement('button');
            randomize.type = 'button';
            randomize.className = 'individual-voucher-row__randomize';
            randomize.textContent = 'Randomize';
            randomize.addEventListener('click', () => {
                codes[i] = generateCode();
                renderRows();
            });
            buttons.appendChild(randomize);

            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'individual-voucher-row__toggle';

            const img = document.createElement('img');
            img.src = '/images/' + (status === 'active' ? 'activate.png' : 'disable.png');
            img.alt = status === 'active' ? 'Disable' : 'Activate';
            toggle.title = img.alt;
            toggle.appendChild(img);

            toggle.addEventListener('click', () => {
                statuses[i] = statuses[i] === 'active' ? 'disabled' : 'active';
                renderRows();
            });

            buttons.appendChild(toggle);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'individual-voucher-row__remove';
            remove.textContent = '×';
            remove.setAttribute('aria-label', 'Remove this voucher');
            remove.title = 'Remove this voucher';
            remove.disabled = statuses.length <= 1;
            remove.addEventListener('click', () => {
                statuses.splice(i, 1);
                codes.splice(i, 1);
                quantityInput.value = statuses.length;
                renderRows();
            });
            buttons.appendChild(remove);

            row.appendChild(buttons);
            list.appendChild(row);
        });

        if (addBtn) addBtn.disabled = quantity >= 100;
        syncHiddenInputs();
    };

    renderRows();
    quantityInput.addEventListener('input', renderRows);

    addBtn?.addEventListener('click', () => {
        const quantity = clampQuantity();
        if (quantity >= 100) return;
        quantityInput.value = quantity + 1;
        renderRows();
    });

    openBtn.addEventListener('click', () => {
        renderRows();
        snapshotStatuses = [...statuses];
        snapshotCodes = [...codes];
        dialog.showModal();
    });

    closeBtn?.addEventListener('click', () => dialog.close());

    cancelBtn?.addEventListener('click', () => {
        statuses = [...snapshotStatuses];
        codes = [...snapshotCodes];
        renderRows();
        dialog.close();
    });

    // Pressing Escape fires 'cancel' (before 'close') and should discard
    // changes too, same as clicking the Cancel button.
    dialog.addEventListener('cancel', () => {
        statuses = [...snapshotStatuses];
        codes = [...snapshotCodes];
        renderRows();
    });
})();
</script>

<?php
include '../../_foot.php';
