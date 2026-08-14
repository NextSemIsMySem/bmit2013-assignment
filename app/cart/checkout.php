<?php
require '../_base.php';
require '_order.php';
require '../_stripe_config.php';
require '../lib/stripe.php';
auth('member');

$shippingFee = 10.00;
$paymentMethods = [
    'cod' => 'Cash on Delivery',
    'card' => 'Pay with Card (Stripe)',
];

$stmt = $_db->prepare(
    'SELECT p.product_id, p.product_name, p.price, p.stock, p.availability, ci.quantity
     FROM cart_item ci
     JOIN product p ON p.product_id = ci.product_id
     WHERE ci.user_id = ?'
);
$stmt->execute([$_user->user_id]);
$items = $stmt->fetchAll();

if (!$items) {
    temp('info', 'Your cart is empty.');
    redirect('/cart/cart.php');
}

$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item->price * $item->quantity;
}

if (is_get()) {
    $addressStmt = $_db->prepare('SELECT * FROM address WHERE user_id = ? AND is_default = 1 LIMIT 1');
    $addressStmt->execute([$_user->user_id]);
    $defaultAddress = $addressStmt->fetch();

    if ($defaultAddress) {
        $_REQUEST['street'] = $defaultAddress->street;
        $_REQUEST['city'] = $defaultAddress->city;
        $_REQUEST['state'] = $defaultAddress->state;
        $_REQUEST['postal_code'] = $defaultAddress->postal_code;
        $_REQUEST['country'] = $defaultAddress->country;
    }
}

if (is_post()) {
    $street = req('street');
    $city = req('city');
    $state = req('state');
    $postalCode = req('postal_code');
    $country = req('country');
    $paymentMethod = req('payment_method');
    $voucherCode = req('voucher_code');

    if ($street === '') {
        $_err['street'] = 'Street address is required.';
    } elseif (strlen($street) > 255) {
        $_err['street'] = 'Street address must be at most 255 characters.';
    }

    if ($city === '') {
        $_err['city'] = 'City is required.';
    } elseif (strlen($city) > 100) {
        $_err['city'] = 'City must be at most 100 characters.';
    }

    if ($state === '') {
        $_err['state'] = 'State is required.';
    } elseif (strlen($state) > 100) {
        $_err['state'] = 'State must be at most 100 characters.';
    }

    if ($postalCode === '') {
        $_err['postal_code'] = 'Postal code is required.';
    } elseif (strlen($postalCode) > 20) {
        $_err['postal_code'] = 'Postal code must be at most 20 characters.';
    }

    if ($country === '') {
        $_err['country'] = 'Country is required.';
    } elseif (strlen($country) > 100) {
        $_err['country'] = 'Country must be at most 100 characters.';
    }

    if (!array_key_exists($paymentMethod, $paymentMethods)) {
        $_err['payment_method'] = 'Please select a payment method.';
    }

    $voucher = null;
    $discountAmount = 0;

    if ($voucherCode !== '') {
        $voucherStmt = $_db->prepare(
            "SELECT * FROM voucher
             WHERE code = ? AND status = 'active' AND NOW() BETWEEN start_date AND end_date"
        );
        $voucherStmt->execute([$voucherCode]);
        $voucher = $voucherStmt->fetch();

        if (!$voucher) {
            $_err['voucher_code'] = 'This voucher code is invalid or expired.';
        } elseif ($subtotal < $voucher->minimum_spend) {
            $_err['voucher_code'] = 'Minimum spend of RM ' . number_format($voucher->minimum_spend, 2) . ' not met.';
        } else {
            $discountAmount = $voucher->discount_type === 'percentage'
                ? round($subtotal * ((float) $voucher->discount_percentage / 100), 2)
                : (float) $voucher->discount_value;
            $discountAmount = min($discountAmount, $subtotal);
        }
    }

    if (!$_err) {
        $addressFields = [
            'street' => $street,
            'city' => $city,
            'state' => $state,
            'postal_code' => $postalCode,
            'country' => $country,
        ];

        if ($paymentMethod === 'cod') {
            try {
                $orderId = create_order_from_cart(
                    $_db,
                    $_user,
                    $items,
                    $addressFields,
                    'cod',
                    $voucher,
                    $discountAmount,
                    $subtotal,
                    $shippingFee,
                    'pending'
                );

                temp('info', 'Order placed successfully.');
                redirect('/cart/order-confirmation.php?id=' . $orderId);
            } catch (RuntimeException $e) {
                $_err['stock'] = $e->getMessage();
            }
        } else {
            // 'card' — hand off to Stripe Checkout. The order itself is only
            // created once Stripe confirms payment (see stripe-return.php),
            // so stash everything needed for that here.
            $_SESSION['checkout_pending'] = [
                'items' => array_map(fn($item) => [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                ], $items),
                'address' => $addressFields,
                'voucher_id' => $voucher->voucher_id ?? null,
                'discount_amount' => $discountAmount,
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
            ];

            $total = $subtotal - $discountAmount + $shippingFee;
            $baseUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

            $session = stripe_request('POST', 'checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $baseUrl . '/cart/stripe-return.php?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $baseUrl . '/cart/checkout.php',
                'customer_email' => $_user->email,
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => STRIPE_CURRENCY,
                        'unit_amount' => (int) round($total * 100),
                        'product_data' => [
                            'name' => 'ForgeFit Order (' . count($items) . ' item' . (count($items) === 1 ? '' : 's') . ')',
                        ],
                    ],
                ]],
            ]);

            if ($session['code'] === 200 && !empty($session['body']->url)) {
                redirect($session['body']->url);
            } else {
                unset($_SESSION['checkout_pending']);
                $_err['payment_method'] = 'Unable to start payment right now. Please try again.';
                error_log('Stripe checkout session error: ' . json_encode($session['body'] ?? null));
            }
        }
    }
}

$_title = 'Checkout';
include '../_head.php';
?>

<form class="checkout-layout" method="post">
    <section class="checkout-section">
        <h2>Shipping Address</h2>
        <div class="form checkout-form">
            <?php html_text('street', 'Street'); ?>
            <?php html_text('city', 'City'); ?>
            <?php html_text('state', 'State'); ?>
            <?php html_text('postal_code', 'Postal Code'); ?>
            <?php html_text('country', 'Country'); ?>
        </div>
    </section>

    <section class="checkout-section">
        <h2>Payment</h2>
        <div class="form checkout-form">
            <?php html_select('payment_method', 'Payment Method', $paymentMethods); ?>
            <?php html_text('voucher_code', 'Voucher Code (optional)'); ?>
        </div>
    </section>

    <section class="checkout-section checkout-summary">
        <h2>Order Summary</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= encode($item->product_name) ?></td>
                        <td><?= encode($item->quantity) ?></td>
                        <td>RM <?= encode(number_format($item->price * $item->quantity, 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="checkout-totals-line">Subtotal: RM <?= encode(number_format($subtotal, 2)) ?></p>
        <p class="checkout-totals-line">Shipping: RM <?= encode(number_format($shippingFee, 2)) ?></p>
        <p class="checkout-totals-line checkout-totals-note">Voucher discount (if any) is applied when you place the order.</p>

        <?= err('stock') ?>

        <section class="buttons">
            <button type="submit">Place Order</button>
            <a href="/cart/cart.php" class="checkout-back">Back to Cart</a>
        </section>
    </section>
</form>

<?php
include '../_foot.php';
