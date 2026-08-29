<?php
require '../../_base.php';
require '_voucher_expire.php';
auth('admin');

apply_voucher_expiry($_db);

$id = req('id');
$stmt = $_db->prepare('SELECT * FROM voucher_configuration WHERE voucher_id = ?');
$stmt->execute([$id]);
$voucher = $stmt->fetch();

if (!$voucher) {
    redirect('vouchers.php');
}

// Expired vouchers are read-only — editing them here would never actually
// revive them (this page never touches `status`), so send admins straight
// to the archived detail view instead of a form that can't do anything.
if ($voucher->status === 'expired') {
    redirect('voucher-archived-detail.php?id=' . $id);
}

// A code can only reach 'used' once its configuration's window has opened,
// so a not-yet-started configuration's codes are always safe to remove
// outright — this gates the Remove button on existing rows below.
$notStarted = strtotime($voucher->start_date) > time();

$codesStmt = $_db->prepare('SELECT id, code, status FROM voucher WHERE voucher_id = ? ORDER BY id');
$codesStmt->execute([$id]);
$existingCodes = $codesStmt->fetchAll();

if (is_post()) {
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
        $stmt = $_db->prepare(
            'UPDATE voucher_configuration
             SET name = ?, discount_type = ?, discount_value = ?, discount_percentage = ?, minimum_spend = ?, start_date = ?, end_date = ?
             WHERE voucher_id = ?'
        );
        $stmt->execute([
            $name,
            $discountType,
            $discountType === 'fixed' ? $discountValue : null,
            $discountType === 'percentage' ? $discountPercentage : null,
            $minimumSpend !== '' ? $minimumSpend : 0,
            $startDateTime,
            $endDateTime,
            $id,
        ]);

        // Apply any code/status edits made in the Individual Voucher
        // Configuration popup, and insert any brand-new rows added there
        // (blank row_id). Codes already 'used' are left untouched — no
        // removing/replacing a code that's tied to a real order.
        $codeCharset = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $rowIds = (array) ($_POST['row_id'] ?? []);
        $rowCodes = (array) ($_POST['row_code'] ?? []);
        $rowStatuses = (array) ($_POST['row_status'] ?? []);

        $currentStatuses = [];
        foreach ($existingCodes as $existingCode) {
            $currentStatuses[$existingCode->id] = $existingCode->status;
        }

        $codeCheck = $_db->prepare('SELECT 1 FROM voucher WHERE code = ? AND id != ?');
        $updateCode = $_db->prepare('UPDATE voucher SET code = ?, status = ? WHERE id = ? AND voucher_id = ?');
        $insertCode = $_db->prepare('INSERT INTO voucher (voucher_id, code, status) VALUES (?, ?, ?)');
        $pickedCodes = [];

        foreach ($rowIds as $index => $rowIdRaw) {
            $rowIdRaw = trim((string) $rowIdRaw);
            $isNew = $rowIdRaw === '';
            $rowId = $isNew ? 0 : (int) $rowIdRaw;

            if (!$isNew && (!isset($currentStatuses[$rowId]) || $currentStatuses[$rowId] === 'used')) {
                continue;
            }

            // Mark this existing row as accounted for as soon as we know it's
            // a real, editable row that was actually submitted — regardless
            // of what happens with its code below. Otherwise a submitted row
            // with a blank code (skipped further down, left unchanged) would
            // never get unset here, and the not-yet-started deletion pass
            // below would wrongly treat it as removed and delete it.
            if (!$isNew) {
                unset($currentStatuses[$rowId]);
            }

            $status = ($rowStatuses[$index] ?? 'active') === 'disabled' ? 'disabled' : 'active';

            $candidate = strtoupper(trim((string) ($rowCodes[$index] ?? '')));
            if ($candidate === '') {
                continue;
            }
            if (!preg_match('/^[' . $codeCharset . ']{1,50}$/', $candidate)) {
                // Client-side JS already restricts typed codes to this
                // charset, so this only matters for a crafted request —
                // fall back to a fresh random code rather than accepting an
                // out-of-charset value, matching voucher-create.php.
                $candidate = '';
            }

            // Prefer the code chosen in the popup; fall back to a fresh
            // random one if it collides with an existing/already-picked
            // code, matching voucher-create.php's behavior.
            while ($candidate === '' || in_array($candidate, $pickedCodes, true) || ($codeCheck->execute([$candidate, $rowId]) && $codeCheck->fetchColumn())) {
                $candidate = '';
                for ($j = 0; $j < 7; $j++) {
                    $candidate .= $codeCharset[random_int(0, strlen($codeCharset) - 1)];
                }
            }
            $pickedCodes[] = $candidate;

            if ($isNew) {
                $insertCode->execute([$id, $candidate, $status]);
            } else {
                $updateCode->execute([$candidate, $status, $rowId, $id]);
            }
        }

        // Whatever's left in $currentStatuses is an existing code the popup
        // no longer submitted — i.e. the admin clicked Remove on it. Only
        // act on that while the configuration hasn't started yet; the
        // Remove button itself is only rendered in that case, but this
        // re-checks server-side rather than trusting the submitted rows.
        if ($notStarted && $currentStatuses) {
            $removableIds = array_keys(array_filter($currentStatuses, fn($status) => $status !== 'used'));
            if ($removableIds) {
                $placeholders = implode(',', array_fill(0, count($removableIds), '?'));
                $deleteCode = $_db->prepare("DELETE FROM voucher WHERE voucher_id = ? AND id IN ($placeholders)");
                $deleteCode->execute([$id, ...$removableIds]);
            }
        }

        temp('info', 'Voucher updated.');
        redirect('vouchers.php');
    }
} else {
    // Pre-fill sticky form fields from the DB on first (GET) load.
    $_REQUEST['name'] = $voucher->name;
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

$_title = 'Update Voucher Configuration';
include '../../_head.php';
?>

<form class="form" method="post">
    <label for="name">Name <span class="required-star">*</span></label>
    <input type="text" id="name" name="name" value="<?= encode(req('name')) ?>" autocomplete="off">
    <?= err('name') ?>

    <button type="button" class="btn-blue full-width-label" id="individual-voucher-btn">Individual Voucher Configuration</button>

    <dialog id="individual-voucher-dialog" aria-labelledby="individual-voucher-title">
        <p id="individual-voucher-title">Individual Voucher Configuration</p>
        <div class="individual-voucher-list<?= $voucher->status === 'disabled' ? ' individual-voucher-list--config-disabled' : '' ?>" id="individual-voucher-list">
            <?php foreach ($existingCodes as $existingCode): ?>
                <?php $locked = $existingCode->status === 'used'; ?>
                <div class="individual-voucher-row" data-locked="<?= $locked ? '1' : '0' ?>">
                    <span class="individual-voucher-row__code" <?= $locked ? '' : 'tabindex="0" title="Click to edit this code"' ?>><?= encode($existingCode->code) ?></span>
                    <div class="individual-voucher-row__buttons">
                        <?php if ($locked): ?>
                            <span class="individual-voucher-row__used">Used</span>
                        <?php else: ?>
                            <button type="button" class="individual-voucher-row__randomize">Randomize</button>
                            <button type="button" class="individual-voucher-row__toggle" title="<?= $existingCode->status === 'active' ? 'Disable' : 'Activate' ?>">
                                <img src="/images/<?= $existingCode->status === 'active' ? 'activate.png' : 'disable.png' ?>" alt="<?= $existingCode->status === 'active' ? 'Disable' : 'Activate' ?>">
                            </button>
                            <?php if ($notStarted): ?>
                                <button type="button" class="individual-voucher-row__remove" aria-label="Remove this voucher" title="Remove this voucher">&times;</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="row_id[]" value="<?= encode($existingCode->id) ?>">
                    <input type="hidden" name="row_code[]" value="<?= encode($existingCode->code) ?>" data-row-code-input>
                    <input type="hidden" name="row_status[]" value="<?= encode($existingCode->status) ?>" data-row-status-input>
                </div>
            <?php endforeach; ?>
        </div>
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

<script>
;(function () {
    const openBtn = document.getElementById('individual-voucher-btn');
    const closeBtn = document.getElementById('individual-voucher-close');
    const cancelBtn = document.getElementById('individual-voucher-cancel');
    const addBtn = document.getElementById('individual-voucher-add');
    const dialog = document.getElementById('individual-voucher-dialog');
    const list = document.getElementById('individual-voucher-list');
    const duplicateDialog = document.getElementById('voucher-code-duplicate-dialog');
    const duplicateOkBtn = document.getElementById('voucher-code-duplicate-ok');
    if (!openBtn || !dialog || !list) return;

    duplicateOkBtn?.addEventListener('click', () => duplicateDialog.close());

    const CODE_CHARSET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const MAX_ROWS = 100;
    let snapshotHtml = '';
    let existingDbCodes = [];

    fetch('voucher-code-list.php')
        .then(res => res.json())
        .then(codes => { existingDbCodes = codes; })
        .catch(e => console.error('Failed to load existing voucher codes:', e));

    const generateCode = () => {
        let code = '';
        for (let i = 0; i < 7; i++) {
            code += CODE_CHARSET[Math.floor(Math.random() * CODE_CHARSET.length)];
        }
        return code;
    };

    const getAllCodes = (excludeRow = null) => {
        return Array.from(list.querySelectorAll('[data-row-code-input]'))
            .filter(input => input.closest('.individual-voucher-row') !== excludeRow)
            .map(input => input.value);
    };

    const generateUniqueCode = (excludeRow = null) => {
        const taken = getAllCodes(excludeRow).concat(existingDbCodes);
        let code = generateCode();
        while (taken.includes(code)) {
            code = generateCode();
        }
        return code;
    };

    const applyCode = (row, code) => {
        row.querySelector('[data-row-code-input]').value = code;
        const codeEl = row.querySelector('.individual-voucher-row__code');
        if (codeEl) codeEl.textContent = code;
    };

    const applyStatus = (row, status) => {
        row.querySelector('[data-row-status-input]').value = status;
        const toggle = row.querySelector('.individual-voucher-row__toggle');
        const img = toggle?.querySelector('img');
        if (toggle && img) {
            img.src = '/images/' + (status === 'active' ? 'activate.png' : 'disable.png');
            img.alt = status === 'active' ? 'Disable' : 'Activate';
            toggle.title = img.alt;
        }
    };

    const updateAddButtonState = () => {
        if (addBtn) addBtn.disabled = list.querySelectorAll('.individual-voucher-row').length >= MAX_ROWS;
    };

    const wireRow = row => {
        if (row.dataset.locked === '1') return;

        const codeEl = row.querySelector('.individual-voucher-row__code');
        const codeInput = row.querySelector('[data-row-code-input]');
        const randomize = row.querySelector('.individual-voucher-row__randomize');
        const toggle = row.querySelector('.individual-voucher-row__toggle');
        const remove = row.querySelector('.individual-voucher-row__remove');

        const editCode = () => {
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'individual-voucher-row__code-input';
            input.value = codeInput.value;

            input.addEventListener('input', () => {
                input.value = input.value.toUpperCase().replace(new RegExp('[^' + CODE_CHARSET + ']', 'g'), '');
            });

            const commit = async () => {
                const value = input.value;

                if (getAllCodes(row).includes(value)) {
                    duplicateDialog?.showModal();
                    input.replaceWith(codeEl);
                    return;
                }

                if (value.length === 0) {
                    input.replaceWith(codeEl);
                    return;
                }

                let accepted = false;
                try {
                    const rowId = row.querySelector('[name="row_id[]"]')?.value || '0';
                    const res = await fetch(
                        'voucher-code-check.php?code=' + encodeURIComponent(value) + '&exclude_id=' + encodeURIComponent(rowId)
                    );
                    if (!res.ok) throw new Error('Unexpected response: ' + res.status);
                    const data = await res.json();

                    if (data.occupied) {
                        duplicateDialog?.showModal();
                    } else {
                        accepted = true;
                    }
                } catch (e) {
                    // Could not verify uniqueness (session expired,
                    // network error, etc.) — keep the previous code
                    // rather than silently accepting an unverified one.
                    console.error('Voucher code uniqueness check failed:', e);
                }

                // Restore the span to the DOM first — applyCode() looks it
                // up via querySelector, which finds nothing while it's
                // still replaced by this input.
                input.replaceWith(codeEl);
                if (accepted) applyCode(row, value);
            };

            input.addEventListener('blur', commit);
            input.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    input.blur();
                } else if (e.key === 'Escape') {
                    input.replaceWith(codeEl);
                }
            });

            codeEl.replaceWith(input);
            input.focus();
            input.select();
        };

        codeEl?.addEventListener('click', editCode);
        codeEl?.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                editCode();
            }
        });

        randomize?.addEventListener('click', () => applyCode(row, generateUniqueCode(row)));

        toggle?.addEventListener('click', () => {
            const statusInput = row.querySelector('[data-row-status-input]');
            applyStatus(row, statusInput.value === 'active' ? 'disabled' : 'active');
        });

        remove?.addEventListener('click', () => {
            // A brand-new row is just a discarded draft — nothing saved yet,
            // so no prompt needed. An existing row is a real, already-saved
            // code that will be permanently deleted once Save is clicked, so
            // it gets the same confirm every other delete action in this app
            // uses.
            const isExisting = !!row.querySelector('[name="row_id[]"]')?.value;
            if (isExisting && !confirm('Remove this voucher code? It will be permanently deleted once you save.')) {
                return;
            }

            row.remove();
            updateAddButtonState();
        });
    };

    const buildNewRow = () => {
        const code = generateUniqueCode();

        const row = document.createElement('div');
        row.className = 'individual-voucher-row';
        row.dataset.locked = '0';

        const codeEl = document.createElement('span');
        codeEl.className = 'individual-voucher-row__code';
        codeEl.textContent = code;
        codeEl.tabIndex = 0;
        codeEl.title = 'Click to edit this code';
        row.appendChild(codeEl);

        const buttons = document.createElement('div');
        buttons.className = 'individual-voucher-row__buttons';

        const randomize = document.createElement('button');
        randomize.type = 'button';
        randomize.className = 'individual-voucher-row__randomize';
        randomize.textContent = 'Randomize';
        buttons.appendChild(randomize);

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'individual-voucher-row__toggle';
        toggle.title = 'Disable';
        const img = document.createElement('img');
        img.src = '/images/activate.png';
        img.alt = 'Disable';
        toggle.appendChild(img);
        buttons.appendChild(toggle);

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'individual-voucher-row__remove';
        remove.textContent = '×';
        remove.setAttribute('aria-label', 'Remove this voucher');
        remove.title = 'Remove this voucher';
        buttons.appendChild(remove);

        row.appendChild(buttons);

        const rowIdInput = document.createElement('input');
        rowIdInput.type = 'hidden';
        rowIdInput.name = 'row_id[]';
        rowIdInput.value = '';
        row.appendChild(rowIdInput);

        const codeInput = document.createElement('input');
        codeInput.type = 'hidden';
        codeInput.name = 'row_code[]';
        codeInput.value = code;
        codeInput.setAttribute('data-row-code-input', '');
        row.appendChild(codeInput);

        const statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'row_status[]';
        statusInput.value = 'active';
        statusInput.setAttribute('data-row-status-input', '');
        row.appendChild(statusInput);

        wireRow(row);
        return row;
    };

    list.querySelectorAll('.individual-voucher-row').forEach(wireRow);

    addBtn?.addEventListener('click', () => {
        if (list.querySelectorAll('.individual-voucher-row').length >= MAX_ROWS) return;
        list.appendChild(buildNewRow());
        updateAddButtonState();
    });

    const restore = () => {
        list.innerHTML = snapshotHtml;
        list.querySelectorAll('.individual-voucher-row').forEach(wireRow);
        updateAddButtonState();
    };

    openBtn.addEventListener('click', () => {
        snapshotHtml = list.innerHTML;
        updateAddButtonState();
        dialog.showModal();
    });

    closeBtn?.addEventListener('click', () => dialog.close());

    cancelBtn?.addEventListener('click', () => {
        restore();
        dialog.close();
    });

    dialog.addEventListener('cancel', restore);
})();
</script>

<?php
include '../../_foot.php';
