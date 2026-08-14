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
 *   (an action may also set 'disabled' => true while its endpoint is not ready)
 *
 * Optional variable:
 * - $adminEmptyMessage: message shown when there are no rows
 * - $adminPaginate: set to true to show page-size and navigation controls
 * - $adminSearch: search form configuration with `name`, `label`, and `placeholder`
 *   (the including page owns the database query and chooses its table/field)
 * - $adminToolbarButton: ['label' => 'Button text', 'url' => 'target.php'] rendered
 *   on the right side of the search row (e.g. a "+ Add" link)
 */

if (!isset($adminColumns, $adminRows, $adminActions)) {
    throw new LogicException('Admin table requires columns, rows, and actions.');
}

$adminEmptyMessage ??= 'No records found.';
$adminSort = req('sort');
$adminDir = req('dir') === 'desc' ? 'desc' : 'asc';
$adminPaginate ??= false;
$adminSearch ??= null;
$adminToolbarButton ??= null;

if ($adminSearch) {
    $adminSearchName = $adminSearch['name'] ?? 'search';
    $adminSearchLabel = $adminSearch['label'] ?? 'Search';
    $adminSearchPlaceholder = $adminSearch['placeholder'] ?? 'Search...';
    $adminSearchValue = req($adminSearchName, '');
    $adminSearchParams = $_GET;
    unset($adminSearchParams[$adminSearchName], $adminSearchParams['page'], $adminSearchParams['per_page']);
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

<?php if ($adminSearch || $adminToolbarButton): ?>
    <div class="admin-table-toolbar">
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
                <button type="submit" aria-label="<?= encode($adminSearchLabel) ?>">&#128269;</button>
                <a href="?<?= encode(http_build_query($adminSearchParams)) ?>">Reset</a>
            </form>
        <?php endif; ?>
        <?php if ($adminToolbarButton): ?>
            <a class="btn-green" href="<?= encode($adminToolbarButton['url']) ?>"><?= encode($adminToolbarButton['label']) ?></a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<table class="admin-table">
    <thead>
        <tr>
            <?php foreach ($adminColumns as $field => $label): ?>
                <?php
                    $nextDir = ($adminSort === $field && $adminDir === 'asc') ? 'desc' : 'asc';
                    $params = $_GET;
                    $params['sort'] = $field;
                    $params['dir'] = $nextDir;
                    unset($params['page']);
                ?>
                <th>
                    <a href="?<?= encode(http_build_query($params)) ?>">
                        <?= encode($label) ?>
                    </a>
                </th>
            <?php endforeach; ?>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($adminRowsToDisplay as $row): ?>
            <tr>
                <?php foreach ($adminColumns as $field => $_): ?>
                    <?php $value = is_array($row) ? ($row[$field] ?? '') : ($row->$field ?? ''); ?>
                    <td title="<?= encode($value) ?>"><?= encode($value) ?></td>
                <?php endforeach; ?>
                <td class="admin-table__actions">
                    <?php foreach ($adminActions as $action): ?>
                        <?php
                            // Fields may be a plain value or a closure(row) for per-row state (e.g. disable/activate).
                            $resolve = fn($value) => $value instanceof Closure ? $value($row) : $value;

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
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (!$adminRowsToDisplay): ?>
            <tr>
                <td colspan="<?= count($adminColumns) + 1 ?>"><?= encode($adminEmptyMessage) ?></td>
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
