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
    return '<span class="err">' . (isset($_err[$name]) ? encode($_err[$name]) : '') . '</span>';
}

// ============================================================================
// Form field helpers
// ============================================================================

function html_required_star() {
    return ' <span class="required-star">*</span>';
}

function html_text($name, $label, $type = 'text', $required = false) {
    $value = encode(req($name));
    echo '<label for="' . $name . '">' . encode($label) . ($required ? html_required_star() : '') . '</label>';
    echo '<input type="' . $type . '" id="' . $name . '" name="' . $name . '" value="' . $value . '">';
    echo err($name);
}

function html_password($name, $attr = '') {
    $value = encode(req($name));
    echo '<label for="' . $name . '">' . encode($attr !== '' ? $attr : 'Password') . '</label>';
    echo '<input type="password" id="' . $name . '" name="' . $name . '" value="' . $value . '">';
    echo err($name);
}

function html_file($key, $accept = '', $attr = '') {
    echo "<input type='file' id='$key' name='$key' accept='$accept' $attr>";
}

function html_select($name, $label, $options, $required = false) {
    $selected = req($name);
    echo '<label for="' . $name . '">' . encode($label) . ($required ? html_required_star() : '') . '</label>';
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

function is_email($v) {
    return filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
}

// Shared password policy for register / reset / change-password / admin create.
// Returns an error message, or null if the password is acceptable.
function password_error($password, $label = 'Password') {
    if ($password === '')                                   return "$label is required.";
    if (strlen($password) < 8 || strlen($password) > 50)     return "$label must be between 8-50 characters.";
    $ok = preg_match('/[a-z]/', $password) && preg_match('/[A-Z]/', $password)
       && preg_match('/[0-9]/', $password) && preg_match('/[^a-zA-Z0-9]/', $password);
    if (!$ok) return "$label must include upper/lowercase letters, a number and a symbol.";
    return null;
}

// ============================================================================
// Security
// ============================================================================

// Global user object (the logged-in user's row, or null)
$_user = $_SESSION['user'] ?? null;

function login($user, $url = '/') { $_SESSION['user'] = $user; redirect($url); }
function logout($url = '/')       { unset($_SESSION['user']); redirect($url); }

// True for any admin-level account. Use this instead of comparing role to
// 'admin' directly, so a superadmin is never mistaken for a member.
// Note this is about admin *level*, not the exact role — auth('superadmin')
// remains satisfiable only by a superadmin.
function is_admin($user = null) {
    global $_user;
    $user ??= $_user;
    return $user && in_array($user->role, ['admin', 'superadmin'], true);
}

function auth(...$roles) {
    global $_user;
    if ($_user) {
        if ($roles) {
            if (in_array($_user->role, $roles)) return;
            // A superadmin satisfies any admin-level requirement.
            if (in_array('admin', $roles) && $_user->role === 'superadmin') return;
        } else {
            return;
        }
    }
    // Admin-level pages send you to the admin entrance; everything else to the member login.
    redirect(in_array('admin', $roles) || in_array('superadmin', $roles)
        ? '/admin/a4mi3.php' : '/login.php');
}

// ============================================================================
// Photos
// ============================================================================

function get_file($key) {
    $f = $_FILES[$key] ?? null;
    if ($f && $f['error'] == 0) return (object) $f;
    return null;
}

function save_photo($f, $folder = null, $width = 200, $height = 200) {
    $folder ??= __DIR__ . '/photos';
    $photo = bin2hex(random_bytes(8)) . '.jpg';
    require_once __DIR__ . '/lib/SimpleImage.php';
    $img = new \claviska\SimpleImage();
    $img->fromFile($f->tmp_name)->thumbnail($width, $height)->toFile("$folder/$photo", 'image/jpeg');
    return $photo;
}

// ============================================================================
// Mail
// ============================================================================

// Return local root path
function root($path = '') {
    return __DIR__ . "/$path";
}

// Return base url (host + port)
function base($path = '') {
    return "http://$_SERVER[SERVER_NAME]:$_SERVER[SERVER_PORT]/$path";
}

// Mail credentials live in app/_config.php, which is gitignored so they are
// never committed. Copy app/_config.sample.php to app/_config.php and fill in
// your own values.
function mail_config() {
    $file = __DIR__ . '/_config.php';
    return file_exists($file) ? require $file : [];
}

// Initialize and return mail object
function get_mail() {
    require_once __DIR__ . '/lib/PHPMailer.php';
    require_once __DIR__ . '/lib/SMTP.php';

    $cfg = mail_config();

    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->SMTPAuth = true;
    $m->Host     = 'smtp.gmail.com';
    $m->Port     = 587;
    $m->Username = $cfg['mail_username'] ?? '';
    $m->Password = $cfg['mail_password'] ?? '';
    $m->CharSet  = 'utf-8';
    $m->setFrom($m->Username, 'ForgeFit Admin');
    return $m;
}
