<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

// Shopee-style labels for the orders.status enum (pending, paid, shipped, completed, cancelled).
function order_status_label($status) {
    $labels = [
        'pending' => 'To Pay',
        'paid' => 'To Ship',
        'shipped' => 'To Receive',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    return $labels[$status] ?? ucfirst($status);
}

function order_status_class($status) {
    return 'order-status--' . $status;
}
