<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

/*
 * Reusable admin CRUD table.
 *
 * Required variables before including this file:
 * - $adminColumns: ['property_name' => 'Column label', ...]
 * - $adminRows: iterable rows (objects or associative arrays)
 * - $adminActions: action definitions with label, icon, method, and url callback
 *   (an action may also set 'disabled' => true while its endpoint is not ready, or
 *   'hidden' => true|callable(row) to omit the button entirely for that row instead
 *   of just greying it out — e.g. an action that isn't valid for that row at all)
 *
 * Optional variable:
 * - $adminEmptyMessage: message shown when there are no rows
 * - $adminPaginate: set to true to show page-size and navigation controls
 * - $adminSearch: search form configuration with `name`, `label`, and `placeholder`
 *   (the including page owns the database query and chooses its table/field)
 * - $adminToolbarButtons: [['label' => 'Button text', 'url' => 'target.php',
 *   'icon' => optional /images/ filename, 'class' => optional CSS class, defaults
 *   to 'btn-green'], ...] rendered on the right side of the search row (e.g. a
 *   "+ Add" link, or a link to a filtered view)
 * - $adminIconColumns: field names that should render as an /images/ icon instead
 *   of plain text. The row's `{field}` property holds the icon filename, and
 *   `{field}_label` holds the accessible text (used for alt/title).
 * - $adminInlineIcon: ['column' => 'field_name', 'field' => 'icon_field', 'label_field' => 'label_field']
 *   renders a small icon next to an existing column's text (no separate column/header).
 *   The icon is only shown when the row's `{field}` property is non-empty, so it can be
 *   used for conditional badges (e.g. only flag low/out-of-stock rows).
 * - $adminColumnDisplay: ['field_name' => callable(row): string, ...] overrides the
 *   text shown (and the cell's title tooltip) for that column, without touching the
 *   row's actual property — sorting and actions still read the real value.
 * - $adminColumnClass: ['field_name' => callable(row): ?string, ...] adds a CSS class
 *   to that column's cell, e.g. to color status text green/red based on the row.
 * - $adminActionsRenderer: callable(row): string returning raw HTML for the Action
 *   cell, used instead of the default $adminActions icon-button list when set
 *   (pass $adminActions = [] in that case).
 * - $adminActionsWidth: pixel width for the Action column, overriding the default
 *   120px (useful when $adminActionsRenderer needs more room).
 * - $adminFilter: ['fields' => [['name' => 'query param name', 'label' =>
 *   'Field label', 'type' => 'select' (default) or any <input> type like
 *   'date'/'number'/'text', 'options' => ['value' => 'Option label', ...]
 *   (for type 'select'), 'placeholder' => optional "no filter" option text,
 *   default 'All'], ...]]. Adds a "Filter" button beside the search box
 *   that opens a dialog with the given fields; applying submits them as GET
 *   params (resetting to page 1), same as search/sort. Leave 'fields' empty
 *   to still show the button while its filters are being decided later.
 *   Two extra field types cover the toggle patterns from the voucher
 *   create/update forms: type 'toggle' is an optional filter — ['name' =>
 *   'query param for the checkbox', 'label' => toggle text, 'fields' =>
 *   [nested field spec, ...]] — the nested fields only apply once the
 *   toggle is checked (e.g. "Require a minimum spend" revealing an amount
 *   input). Type 'discount' reproduces the percentage/amount switch:
 *   ['type_name' => 'query param for which side is active (\'fixed\' or
 *   default \'percentage\')', 'percentage_name'/'amount_name' => query
 *   params for each side's input, 'percentage_label'/'amount_label' =>
 *   optional input labels]. Type 'range' renders paired minimum/maximum
 *   number inputs and validates that the minimum does not exceed the maximum.
 *   Type 'checkbox-group' renders several independent checkboxes under one
 *   optional legend: ['label' => optional legend text, 'options' =>
 *   [['name' => 'query param for this checkbox', 'label' => checkbox text,
 *   'icon' => optional /images/ filename shown next to the label], ...]] —
 *   each checkbox applies (or doesn't) on its own, not mutually exclusive.
 * - $adminBulkSelect: ['key' => 'row id property', 'storageKey' => unique
 *   localStorage key for this table, 'selectAllUrl' => optional GET endpoint
 *   returning a JSON array of every id matching the current search/filter
 *   (ignoring pagination), used by the header checkbox to select the full list,
 *   'statusKey' => optional row property (e.g. 'availability') stamped onto
 *   each row checkbox as data-bulk-status, used for the live count below,
 *   'actions' => [['label' => 'Delete', 'icon' => optional /images/ filename,
 *   'url' => POST endpoint accepting ids[], 'confirm' => optional confirm
 *   message, 'class' => optional extra CSS class (e.g. an
 *   'admin-bulk-bar__action--green' color variant), 'countWhen' => optional
 *   value — when set, the button label gets " (N)" appended, N being how
 *   many of the currently-selected rows have that value in 'statusKey'],
 *   ...]]. Adds a checkbox column and a selection bar (count +
 *   select-this-page/select-all-matching + the given actions + a built-in
 *   Cancel that just clears the selection) above the table. Selection
 *   persists across page navigation via localStorage, keyed by 'storageKey'.
 */

if (!isset($adminColumns, $adminRows, $adminActions)) {
    throw new LogicException('Admin table requires columns, rows, and actions.');
}

$adminEmptyMessage ??= 'No records found.';
$adminSort = req('sort');
$adminDir = req('dir') === 'desc' ? 'desc' : 'asc';
$adminPaginate ??= false;
$adminSearch ??= null;
$adminFilter ??= null;
$adminToolbarButtons ??= [];
$adminIconColumns ??= [];
$adminInlineIcon ??= null;
$adminColumnDisplay ??= [];
$adminColumnClass ??= [];
$adminActionsRenderer ??= null;
$adminActionsWidth ??= null;
$adminBulkSelect ??= null;

if ($adminSearch) {
    $adminSearchName = $adminSearch['name'] ?? 'search';
    $adminSearchLabel = $adminSearch['label'] ?? 'Search';
    $adminSearchPlaceholder = $adminSearch['placeholder'] ?? 'Search...';
    $adminSearchValue = req($adminSearchName, '');
    $adminSearchParams = $_GET;
    unset($adminSearchParams[$adminSearchName], $adminSearchParams['page'], $adminSearchParams['per_page']);
}

if ($adminFilter) {
    $adminFilter['fields'] ??= [];

    // A field normally maps to one query param ('name'), but the composite
    // types below map to several ('toggle' nests sub-fields, 'discount'
    // splits into a type switch + one input per side) — this walks any
    // field down to the full list of param names it actually owns.
    $adminFilterFieldNames = function ($field) use (&$adminFilterFieldNames) {
        if (($field['type'] ?? '') === 'toggle') {
            $names = [$field['name']];
            foreach ($field['fields'] ?? [] as $subField) {
                $names = array_merge($names, $adminFilterFieldNames($subField));
            }
            return $names;
        }
        if (($field['type'] ?? '') === 'discount') {
            return [$field['type_name'], $field['percentage_name'], $field['amount_name']];
        }
        if (($field['type'] ?? '') === 'range') {
            return [$field['min_name'], $field['max_name']];
        }
        if (($field['type'] ?? '') === 'checkbox-group') {
            return array_column($field['options'], 'name');
        }
        return [$field['name']];
    };
    $adminFilterAllNames = array_merge(...array_map($adminFilterFieldNames, $adminFilter['fields']) ?: [[]]);

    // Renders one filter field. 'toggle' reveals nested fields when checked
    // (the "Restrict to a specific category" / "Require a minimum spend"
    // pattern from the voucher create form); 'discount' reproduces that same
    // form's percentage/amount switch, showing whichever input matches the
    // selected side.
    $adminRenderFilterField = function ($field) use (&$adminRenderFilterField) {
        $fieldType = $field['type'] ?? 'select';

        if ($fieldType === 'toggle') {
            $fieldName = $field['name'];
            $toggleChecked = req($fieldName) === 'on';
            $targetId = 'admin-filter-target-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $fieldName);
            ?>
            <label class="toggle-field full-width-label">
                <input type="checkbox" name="<?= encode($fieldName) ?>" data-toggle-target="#<?= $targetId ?>" <?= $toggleChecked ? 'checked' : '' ?>>
                <span class="toggle-switch"><span class="toggle-switch__thumb"></span></span>
                <?= encode($field['label'] ?? $fieldName) ?>
            </label>
            <div id="<?= $targetId ?>" class="full-width-label" <?= $toggleChecked ? '' : 'hidden' ?>>
                <?php foreach ($field['fields'] ?? [] as $subField): $adminRenderFilterField($subField); endforeach; ?>
            </div>
            <?php
            return;
        }

        if ($fieldType === 'discount') {
            $typeName = $field['type_name'];
            $percentageName = $field['percentage_name'];
            $amountName = $field['amount_name'];
            $typeValue = req($typeName) === 'fixed' ? 'fixed' : 'percentage';
            $percentageRowId = 'admin-filter-row-' . $percentageName;
            $amountRowId = 'admin-filter-row-' . $amountName;
            ?>
            <label class="full-width-label"><?= encode($field['label'] ?? 'Discount') ?></label>
            <label class="toggle-field toggle-field--discount">
                <span class="toggle-field__side-label">Percentage</span>
                <input type="checkbox" name="<?= encode($typeName) ?>" value="fixed" data-toggle-target="#<?= $amountRowId ?>" data-toggle-target-off="#<?= $percentageRowId ?>" <?= $typeValue === 'fixed' ? 'checked' : '' ?>>
                <span class="toggle-switch toggle-switch--discount"><span class="toggle-switch__thumb"></span></span>
                <span class="toggle-field__side-label">Amount</span>
            </label>
            <div id="<?= $percentageRowId ?>" class="full-width-label" <?= $typeValue === 'fixed' ? 'hidden' : '' ?>>
                <label for="admin-filter-<?= encode($percentageName) ?>"><?= encode($field['percentage_label'] ?? 'Min Discount %') ?></label>
                <input type="number" id="admin-filter-<?= encode($percentageName) ?>" name="<?= encode($percentageName) ?>" value="<?= encode(req($percentageName, '')) ?>">
            </div>
            <div id="<?= $amountRowId ?>" class="full-width-label" <?= $typeValue !== 'fixed' ? 'hidden' : '' ?>>
                <label for="admin-filter-<?= encode($amountName) ?>"><?= encode($field['amount_label'] ?? 'Min Discount Amount (RM)') ?></label>
                <input type="number" id="admin-filter-<?= encode($amountName) ?>" name="<?= encode($amountName) ?>" value="<?= encode(req($amountName, '')) ?>">
            </div>
            <?php
            return;
        }

        if ($fieldType === 'range') {
            $minName = $field['min_name'];
            $maxName = $field['max_name'];
            $minValue = req($minName, '');
            $maxValue = req($maxName, '');
            $rangeId = 'admin-filter-range-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $minName);
            ?>
            <fieldset class="admin-filter-range" data-admin-filter-range>
                <legend><?= encode($field['label'] ?? 'Range') ?></legend>
                <div class="admin-filter-range__inputs">
                    <label for="<?= encode($rangeId) ?>-min">Min</label>
                    <input type="text" inputmode="decimal" id="<?= encode($rangeId) ?>-min" name="<?= encode($minName) ?>" value="<?= encode($minValue) ?>" data-range-min>
                    <label for="<?= encode($rangeId) ?>-max">Max</label>
                    <input type="text" inputmode="decimal" id="<?= encode($rangeId) ?>-max" name="<?= encode($maxName) ?>" value="<?= encode($maxValue) ?>" data-range-max>
                </div>
            </fieldset>
            <?php
            return;
        }

        if ($fieldType === 'checkbox-group') {
            ?>
            <fieldset class="admin-filter-checkbox-group">
                <?php if (!empty($field['label'])): ?>
                    <legend><?= encode($field['label']) ?></legend>
                <?php endif; ?>
                <?php foreach ($field['options'] as $option): ?>
                    <?php $optionChecked = req($option['name']) === 'on'; ?>
                    <label class="admin-filter-checkbox-group__option">
                        <input type="checkbox" name="<?= encode($option['name']) ?>" <?= $optionChecked ? 'checked' : '' ?>>
                        <?php if (!empty($option['icon'])): ?>
                            <img src="/images/<?= encode($option['icon']) ?>" alt="">
                        <?php endif; ?>
                        <?= encode($option['label']) ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <?php
            return;
        }

        $fieldName = $field['name'];
        $fieldLabel = $field['label'] ?? $fieldName;
        $fieldValue = req($fieldName, '');
        ?>
        <label for="admin-filter-<?= encode($fieldName) ?>"><?= encode($fieldLabel) ?></label>
        <?php if ($fieldType === 'select'): ?>
            <select id="admin-filter-<?= encode($fieldName) ?>" name="<?= encode($fieldName) ?>">
                <option value=""><?= encode($field['placeholder'] ?? 'All') ?></option>
                <?php foreach ($field['options'] ?? [] as $optionValue => $optionLabel): ?>
                    <option value="<?= encode($optionValue) ?>" <?= (string) $optionValue === $fieldValue ? 'selected' : '' ?>>
                        <?= encode($optionLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <input
                type="<?= encode($fieldType) ?>"
                id="admin-filter-<?= encode($fieldName) ?>"
                name="<?= encode($fieldName) ?>"
                value="<?= encode($fieldValue) ?>"
            >
        <?php endif; ?>
        <?php
    };

    // Hidden inputs carry every current param except the filter fields
    // themselves and pagination (a new filter should land back on page 1).
    $adminFilterHiddenParams = $_GET;
    unset($adminFilterHiddenParams['page'], $adminFilterHiddenParams['per_page']);
    foreach ($adminFilterAllNames as $paramName) {
        unset($adminFilterHiddenParams[$paramName]);
    }

    // Reset clears just the filter fields, keeping search/sort/page as-is.
    $adminFilterResetParams = $_GET;
    unset($adminFilterResetParams['page']);
    foreach ($adminFilterAllNames as $paramName) {
        unset($adminFilterResetParams[$paramName]);
    }

    $adminFilterActive = false;
    foreach ($adminFilterAllNames as $paramName) {
        if (req($paramName, '') !== '') {
            $adminFilterActive = true;
            break;
        }
    }
}

if ($adminPaginate) {
    $adminPageSizes = [10, 25, 50, 100];
    $adminPageSize = (int) ($_GET['per_page'] ?? 10);
    if (!in_array($adminPageSize, $adminPageSizes, true)) {
        $adminPageSize = 10;
    }

    // Pre-paged mode: the including page already ran a LIMIT/OFFSET query
    // (e.g. via SimplePager) and supplies the resulting page of rows plus
    // the true total, so there is nothing left to count or slice here.
    $adminPrePaged = isset($adminTotal, $adminPage);

    if ($adminPrePaged) {
        $adminPageCount = max(1, (int) ceil($adminTotal / $adminPageSize));
        $adminPage = min(max(1, (int) $adminPage), $adminPageCount);
        $adminRowsToDisplay = $adminRows;
    } else {
        $adminTotal = count($adminRows);
        $adminPageCount = max(1, (int) ceil($adminTotal / $adminPageSize));
        $adminPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1;
        $adminPage = min(max(1, $adminPage), $adminPageCount);
        $adminRowsToDisplay = array_slice(
            $adminRows,
            ($adminPage - 1) * $adminPageSize,
            $adminPageSize
        );
    }

    $adminPageStart = max(1, min($adminPage - 1, $adminPageCount - 2));
    $adminPageEnd = min($adminPageCount, $adminPageStart + 2);
    $adminPaginationParams = $_GET;
    unset($adminPaginationParams['page'], $adminPaginationParams['per_page']);
    $adminPageUrl = function ($page) use ($adminPaginationParams, $adminPageSize) {
        return '?' . http_build_query($adminPaginationParams + [
            'per_page' => $adminPageSize,
            'page' => $page,
        ]);
    };
} else {
    $adminRowsToDisplay = $adminRows;
}
?>

<?php if ($adminSearch || $adminFilter || $adminToolbarButtons): ?>
    <div class="admin-table-toolbar">
        <div class="admin-table-toolbar-search-group">
        <?php if ($adminSearch): ?>
            <form class="admin-table-search search-bar" method="get">
                <?php foreach ($adminSearchParams as $key => $value): ?>
                    <?php if (!is_array($value)): ?>
                        <input type="hidden" name="<?= encode($key) ?>" value="<?= encode($value) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                <label class="sr-only" for="admin-table-search"><?= encode($adminSearchLabel) ?></label>
                <input
                    id="admin-table-search"
                    type="search"
                    name="<?= encode($adminSearchName) ?>"
                    value="<?= encode($adminSearchValue) ?>"
                    placeholder="<?= encode($adminSearchPlaceholder) ?>"
                >
                <button type="submit" class="admin-table-search__button" aria-label="<?= encode($adminSearchLabel) ?>">
                    <img src="/images/search.png" alt="">
                </button>
                <a class="admin-table-search-reset" href="?<?= encode(http_build_query($adminSearchParams)) ?>">Reset Search</a>
            </form>
        <?php endif; ?>
        <?php if ($adminFilter): ?>
            <button
                type="button"
                class="btn-blue admin-table-filter-btn<?= $adminFilterActive ? ' admin-table-filter-btn--active' : '' ?>"
                id="admin-table-filter-btn"
            >
                Filter<?= $adminFilterActive ? ' •' : '' ?>
            </button>

            <dialog id="admin-table-filter-dialog" aria-labelledby="admin-table-filter-title">
                <p id="admin-table-filter-title">Filter</p>
                <form class="form admin-table-filter-form" method="get">
                    <?php foreach ($adminFilterHiddenParams as $key => $value): ?>
                        <?php if (!is_array($value)): ?>
                            <input type="hidden" name="<?= encode($key) ?>" value="<?= encode($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php foreach ($adminFilter['fields'] as $field): $adminRenderFilterField($field); endforeach; ?>
                    <?php if (!$adminFilter['fields']): ?>
                        <p class="field-note full-width-label">No filters configured for this table yet.</p>
                    <?php endif; ?>
                    <section class="buttons">
                        <button type="submit" class="btn-green">Apply</button>
                        <a class="btn-dark" href="?<?= encode(http_build_query($adminFilterResetParams)) ?>">Reset</a>
                        <button type="button" class="btn-red" id="admin-table-filter-close">Exit</button>
                    </section>
                </form>
            </dialog>
        <?php endif; ?>
        </div>
        <?php if ($adminToolbarButtons): ?>
            <div class="admin-table-toolbar-actions">
                <?php foreach ($adminToolbarButtons as $button): ?>
                    <a class="<?= encode($button['class'] ?? 'btn-green') ?>" href="<?= encode($button['url']) ?>">
                        <?php if (!empty($button['icon'])): ?>
                            <img class="admin-table-toolbar-actions__icon" src="/images/<?= encode($button['icon']) ?>" alt="">
                        <?php endif; ?>
                        <?= encode($button['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($adminBulkSelect): ?>
    <div
        class="admin-bulk-bar"
        data-bulk-select
        data-storage-key="<?= encode($adminBulkSelect['storageKey']) ?>"
        <?php if (!empty($adminBulkSelect['selectAllUrl'])): ?>
            data-select-all-url="<?= encode($adminBulkSelect['selectAllUrl']) ?>"
            data-select-all-key="<?= encode($adminBulkSelect['key']) ?>"
            <?php if (!empty($adminBulkSelect['statusKey'])): ?>data-select-all-status-key="<?= encode($adminBulkSelect['statusKey']) ?>"<?php endif; ?>
        <?php endif; ?>
        hidden
    >
        <span class="admin-bulk-bar__count" data-bulk-count>0 selected</span>
        <button type="button" class="admin-bulk-bar__link" data-bulk-select-page>Select all on this page only</button>
        <div class="admin-bulk-bar__actions">
            <?php foreach ($adminBulkSelect['actions'] ?? [] as $bulkAction): ?>
                <button
                    type="button"
                    class="admin-bulk-bar__action<?= !empty($bulkAction['class']) ? ' ' . encode($bulkAction['class']) : '' ?>"
                    data-bulk-action
                    data-bulk-action-url="<?= encode($bulkAction['url']) ?>"
                    data-bulk-label="<?= encode($bulkAction['label']) ?>"
                    <?php if (isset($bulkAction['countWhen'])): ?>data-bulk-count-when="<?= encode($bulkAction['countWhen']) ?>"<?php endif; ?>
                    <?php if (!empty($bulkAction['confirm'])): ?>data-bulk-action-confirm="<?= encode($bulkAction['confirm']) ?>"<?php endif; ?>
                    disabled
                >
                    <?php if (!empty($bulkAction['icon'])): ?>
                        <img src="/images/<?= encode($bulkAction['icon']) ?>" alt="">
                    <?php endif; ?>
                    <span data-bulk-label-text><?= encode($bulkAction['label']) ?></span>
                </button>
            <?php endforeach; ?>
            <button type="button" class="admin-bulk-bar__action admin-bulk-bar__action--cancel" data-bulk-cancel disabled>Cancel</button>
        </div>
    </div>

    <dialog class="admin-bulk-confirm-dialog" data-bulk-confirm-dialog aria-labelledby="admin-bulk-confirm-message">
        <p id="admin-bulk-confirm-message" data-bulk-confirm-message>Are you sure?</p>
        <div class="admin-bulk-confirm-dialog__actions">
            <button type="button" class="btn-dark" data-bulk-confirm-cancel>Cancel</button>
            <button type="button" class="btn-red" data-bulk-confirm-ok>Confirm</button>
        </div>
    </dialog>
<?php endif; ?>

<table class="admin-table">
    <thead>
        <tr>
            <?php if ($adminBulkSelect): ?>
                <th class="admin-table__bulk-select">
                    <input type="checkbox" data-bulk-select-page-checkbox aria-label="Select all matching rows">
                </th>
            <?php endif; ?>
            <?php foreach ($adminColumns as $field => $label): ?>
                <?php
                    $isSortedField = $adminSort === $field;
                    $params = $_GET;
                    unset($params['page']);

                    if ($isSortedField && $adminDir === 'desc') {
                        // Third click: back to neutral (no sort applied).
                        unset($params['sort'], $params['dir']);
                    } else {
                        $params['sort'] = $field;
                        $params['dir'] = ($isSortedField && $adminDir === 'asc') ? 'desc' : 'asc';
                    }
                ?>
                <th>
                    <?php if (in_array($field, $adminIconColumns, true)): ?>
                        <?= encode($label) ?>
                    <?php else: ?>
                        <a href="?<?= encode(http_build_query($params)) ?>">
                            <?= encode($label) ?>
                            <?php if ($isSortedField): ?>
                                <span class="admin-table__sort-arrow"><?= $adminDir === 'asc' ? '&#9650;' : '&#9660;' ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                </th>
            <?php endforeach; ?>
            <th class="admin-table__actions" <?php if ($adminActionsWidth): ?>style="width: <?= (int) $adminActionsWidth ?>px"<?php endif; ?>>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($adminRowsToDisplay as $row): ?>
            <tr>
                <?php if ($adminBulkSelect): ?>
                    <?php $bulkId = is_array($row) ? ($row[$adminBulkSelect['key']] ?? '') : ($row->{$adminBulkSelect['key']} ?? ''); ?>
                    <?php $bulkStatus = !empty($adminBulkSelect['statusKey']) ? (is_array($row) ? ($row[$adminBulkSelect['statusKey']] ?? '') : ($row->{$adminBulkSelect['statusKey']} ?? '')) : null; ?>
                    <td class="admin-table__bulk-select">
                        <input
                            type="checkbox"
                            data-bulk-checkbox
                            value="<?= encode($bulkId) ?>"
                            <?php if ($bulkStatus !== null): ?>data-bulk-status="<?= encode($bulkStatus) ?>"<?php endif; ?>
                            aria-label="Select this row"
                        >
                    </td>
                <?php endif; ?>
                <?php foreach ($adminColumns as $field => $_): ?>
                    <?php $value = is_array($row) ? ($row[$field] ?? '') : ($row->$field ?? ''); ?>
                    <?php if (in_array($field, $adminIconColumns, true)): ?>
                        <?php $iconLabel = is_array($row) ? ($row[$field . '_label'] ?? '') : ($row->{$field . '_label'} ?? ''); ?>
                        <td>
                            <img class="admin-table__status-icon" src="/images/<?= encode($value) ?>" alt="<?= encode($iconLabel) ?>" title="<?= encode($iconLabel) ?>">
                        </td>
                    <?php else: ?>
                        <?php
                            $displayValue = isset($adminColumnDisplay[$field]) ? $adminColumnDisplay[$field]($row) : $value;
                            $columnClass = isset($adminColumnClass[$field]) ? $adminColumnClass[$field]($row) : null;
                        ?>
                        <td title="<?= encode($displayValue) ?>"<?= $columnClass ? ' class="' . encode($columnClass) . '"' : '' ?>>
                            <?= encode($displayValue) ?>
                            <?php if ($adminInlineIcon && $field === $adminInlineIcon['column']): ?>
                                <?php
                                    $inlineIconField = $adminInlineIcon['field'];
                                    $inlineIconValue = is_array($row) ? ($row[$inlineIconField] ?? '') : ($row->$inlineIconField ?? '');
                                ?>
                                <?php if ($inlineIconValue): ?>
                                    <?php
                                        $inlineLabelField = $adminInlineIcon['label_field'];
                                        $inlineIconLabel = is_array($row) ? ($row[$inlineLabelField] ?? '') : ($row->$inlineLabelField ?? '');
                                    ?>
                                    <img class="admin-table__status-icon admin-table__status-icon--inline" src="/images/<?= encode($inlineIconValue) ?>" alt="<?= encode($inlineIconLabel) ?>" title="<?= encode($inlineIconLabel) ?>">
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                <?php endforeach; ?>
                <td class="admin-table__actions" <?php if ($adminActionsWidth): ?>style="width: <?= (int) $adminActionsWidth ?>px"<?php endif; ?>>
                    <?php if ($adminActionsRenderer): ?>
                        <?= $adminActionsRenderer($row) ?>
                    <?php else: ?>
                        <?php foreach ($adminActions as $action): ?>
                            <?php
                                // Fields may be a plain value or a closure(row) for per-row state (e.g. disable/activate).
                                $resolve = fn($value) => $value instanceof Closure ? $value($row) : $value;

                                if (!empty($resolve($action['hidden'] ?? false))) {
                                    continue;
                                }

                                $disabled = !empty($resolve($action['disabled'] ?? false));
                                $url = $disabled ? null : $action['url']($row);
                                $icon = $resolve($action['icon']);
                                $label = $resolve($action['label']);
                                $confirm = $resolve($action['confirm'] ?? null);
                                $extraClass = $resolve($action['class'] ?? '');
                            ?>
                            <button
                                type="button"
                                class="admin-action-button<?= $extraClass ? ' ' . encode($extraClass) : '' ?>"
                                <?php if (!$disabled): ?>data-<?= $action['method'] ?>="<?= encode($url) ?>"<?php endif; ?>
                                <?php if ($confirm): ?>data-confirm="<?= encode($confirm) ?>"<?php endif; ?>
                                <?= $disabled ? 'disabled' : '' ?>
                                aria-label="<?= encode($label) ?>"
                                title="<?= encode($label) ?>"
                            >
                                <img src="/images/<?= encode($icon) ?>" alt="">
                            </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (!$adminRowsToDisplay): ?>
            <tr>
                <td colspan="<?= count($adminColumns) + 1 + ($adminBulkSelect ? 1 : 0) ?>"><?= encode($adminEmptyMessage) ?></td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php if ($adminPaginate && $adminTotal > 0): ?>
    <form class="admin-pager" method="get">
        <?php foreach ($adminPaginationParams as $key => $value): ?>
            <?php if (!is_array($value)): ?>
                <input type="hidden" name="<?= encode($key) ?>" value="<?= encode($value) ?>">
            <?php endif; ?>
        <?php endforeach; ?>

        <label class="admin-pager__size">
            Show
            <select name="per_page" aria-label="Records per page" onchange="this.form.submit()">
                <?php foreach ($adminPageSizes as $size): ?>
                    <option value="<?= $size ?>" <?= $adminPageSize === $size ? 'selected' : '' ?>><?= $size ?></option>
                <?php endforeach; ?>
            </select>
            per page
        </label>

        <?php if ($adminPageCount > 1): ?>
            <nav class="admin-pager__links" aria-label="Table pages">
                <a href="<?= encode($adminPageUrl(1)) ?>">First</a>
                <a href="<?= encode($adminPageUrl(max(1, $adminPage - 1))) ?>">Previous</a>
                <?php for ($number = $adminPageStart; $number <= $adminPageEnd; $number++): ?>
                    <?php if ($number === $adminPage): ?>
                        <span class="current" aria-current="page"><?= $number ?></span>
                    <?php else: ?>
                        <a href="<?= encode($adminPageUrl($number)) ?>"><?= $number ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <a href="<?= encode($adminPageUrl(min($adminPageCount, $adminPage + 1))) ?>">Next</a>
                <a href="<?= encode($adminPageUrl($adminPageCount)) ?>">Last</a>
            </nav>
        <?php endif; ?>

        <label class="admin-pager__jump">
            Page
            <input type="number" name="page" value="<?= $adminPage ?>" min="1" max="<?= $adminPageCount ?>" step="1" inputmode="numeric" aria-label="Go to page">
            of <?= $adminPageCount ?>
        </label>
        <button type="submit">Go</button>
    </form>
<?php endif; ?>
