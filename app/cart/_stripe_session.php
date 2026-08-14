<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

/*
 * Creates a Stripe Checkout Session for an order that already exists in the
 * database (the order is created up front, in a 'pending' state — this just
 * asks Stripe for a page to collect payment for it). Used both by the first
 * checkout attempt and by "Pay Now" retries on an already-placed order.
 *
 * Returns the raw stripe_request() result — caller decides what to do with
 * a failure (order still exists either way, nothing rolls back here).
 */
function start_stripe_payment_for_order(int $orderId, float $total, string $email) {
    $baseUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

    return stripe_request('POST', 'checkout/sessions', [
        'mode' => 'payment',
        'client_reference_id' => $orderId,
        'success_url' => $baseUrl . '/cart/stripe-return.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $baseUrl . '/orders/detail.php?id=' . $orderId,
        'customer_email' => $email,
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => STRIPE_CURRENCY,
                'unit_amount' => (int) round($total * 100),
                'product_data' => [
                    'name' => 'ForgeFit Order #' . $orderId,
                ],
            ],
        ]],
    ]);
}
