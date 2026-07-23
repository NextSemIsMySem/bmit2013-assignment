<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

$_db = new PDO(
    'mysql:host=localhost;dbname=fitnessdb;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]
);

$_err = [];

// ============================================================================
// Request helpers
// ============================================================================

function req($name, $default = '') {
    return isset($_REQUEST[$name]) ? trim($_REQUEST[$name]) : $default;
}

function is_get() {
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

function is_post() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// ============================================================================
// Flash messages (one-time session values)
// ============================================================================

function temp($name, $value = null) {
    if ($value !== null) {
        $_SESSION['temp'][$name] = $value;
        return '';
    }

    $val = $_SESSION['temp'][$name] ?? '';
    unset($_SESSION['temp'][$name]);
    return $val;
}

// ============================================================================
// Output helpers
// ============================================================================

function encode($str) {
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

function err($name) {
    global $_err;
    return isset($_err[$name]) ? '<span class="err">' . encode($_err[$name]) . '</span>' : '';
}

// ============================================================================
// Form field helpers
// ============================================================================

function html_text($name, $label, $type = 'text') {
    $value = encode(req($name));
    echo '<label for="' . $name . '">' . encode($label) . '</label>';
    echo '<input type="' . $type . '" id="' . $name . '" name="' . $name . '" value="' . $value . '">';
    echo err($name);
}

function html_select($name, $label, $options) {
    $selected = req($name);
    echo '<label for="' . $name . '">' . encode($label) . '</label>';
    echo '<select id="' . $name . '" name="' . $name . '">';
    echo '<option value="">-- Please Select --</option>';
    foreach ($options as $value => $text) {
        $sel = ((string) $value === $selected) ? ' selected' : '';
        echo '<option value="' . encode($value) . '"' . $sel . '>' . encode($text) . '</option>';
    }
    echo '</select>';
    echo err($name);
}

function html_radios($name, $label, $options) {
    $selected = req($name);
    echo '<label>' . encode($label) . '</label>';
    echo '<span class="radios">';
    foreach ($options as $value => $text) {
        $id = $name . '_' . $value;
        $chk = ((string) $value === $selected) ? ' checked' : '';
        echo '<label for="' . $id . '" class="radio">';
        echo '<input type="radio" id="' . $id . '" name="' . $name . '" value="' . encode($value) . '"' . $chk . '>';
        echo encode($text);
        echo '</label>';
    }
    echo '</span>';
    echo err($name);
}

// ============================================================================
// Sortable table headers
// ============================================================================

function table_headers($columns) {
    $sort = req('sort');
    $dir = req('dir') === 'desc' ? 'desc' : 'asc';

    echo '<tr>';
    foreach ($columns as $field => $label) {
        if ($field === '') {
            echo '<th>' . encode($label) . '</th>';
            continue;
        }

        $newDir = ($sort === $field && $dir === 'asc') ? 'desc' : 'asc';
        $class = ($sort === $field) ? ' class="' . $dir . '"' : '';

        $params = $_GET;
        $params['sort'] = $field;
        $params['dir'] = $newDir;
        unset($params['page']);

        echo '<th><a href="?' . http_build_query($params) . '"' . $class . '>' . encode($label) . '</a></th>';
    }
    echo '</tr>';
}

// ============================================================================
// Validation helpers
// ============================================================================

function is_unique($table, $column, $value, $excludeColumn = null, $excludeValue = null) {
    global $_db;

    $sql = "SELECT COUNT(*) FROM `$table` WHERE `$column` = ?";
    $params = [$value];

    if ($excludeColumn !== null) {
        $sql .= " AND `$excludeColumn` != ?";
        $params[] = $excludeValue;
    }

    $stm = $_db->prepare($sql);
    $stm->execute($params);
    return $stm->fetchColumn() == 0;
}

function is_exists($table, $column, $value) {
    global $_db;

    $stm = $_db->prepare("SELECT COUNT(*) FROM `$table` WHERE `$column` = ?");
    $stm->execute([$value]);
    return $stm->fetchColumn() > 0;
}
