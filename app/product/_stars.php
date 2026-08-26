<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

// Read-only star display for an average or single rating. Shared between
// product/product.php (product reviews) and orders/_order_detail.php
// (per-line "you rated this" indicator).
function render_stars($rating, $max = 5) {
    $rounded = (int) round((float) $rating);
    $html = '<span class="star-display" aria-label="' . $rounded . ' out of ' . $max . ' stars">';
    for ($i = 1; $i <= $max; $i++) {
        $html .= '<span class="star-display__star' . ($i <= $rounded ? ' is-filled' : '') . '">&#9733;</span>';
    }
    $html .= '</span>';
    return $html;
}
