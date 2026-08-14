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

// A pending cancellation request is shown/filtered as "Cancelled" even
// though the order's real status hasn't changed yet — it's only final once
// an admin approves it (admin side not built yet).
function order_has_pending_cancellation($order) {
    return !empty($order->cancellation_requested_at) && $order->status !== 'cancelled';
}

function order_display_label($order) {
    if (order_has_pending_cancellation($order)) {
        return 'Cancellation Requested';
    }

    return order_status_label($order->status);
}

function order_display_class($order) {
    if (order_has_pending_cancellation($order)) {
        return 'order-status--requested';
    }

    return order_status_class($order->status);
}

// Shared between orders/detail.php (renders the radio list) and
// orders/cancel.php (validates the submitted reason against it).
function order_cancellation_reasons() {
    return [
        'Changed my mind',
        'Found a better price elsewhere',
        'Ordered by mistake',
        'Item no longer needed',
        'Others',
    ];
}
