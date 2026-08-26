<?php
require '../_base.php';
require '_order.php';
require '_stripe_session.php';
require '../_stripe_config.php';
require '../lib/stripe.php';
require '../admin/voucher/_voucher_expire.php';
auth('member');

apply_voucher_expiry($_db);

$shippingFee = 10.00;
$paymentMethods = [
    'cod' => 'Cash on Delivery',
    'card' => 'Pay with Card (Stripe)',
];

$stmt = $_db->prepare(
    'SELECT p.product_id, p.product_name, p.price, p.stock, p.availability, p.category_id, ci.quantity
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
            "SELECT v.id, v.code, v.status, vc.category_id, vc.discount_type, vc.discount_value, vc.discount_percentage, vc.minimum_spend
             FROM voucher v
             JOIN voucher_configuration vc ON vc.voucher_id = v.voucher_id
             WHERE v.code = ? AND v.status = 'active' AND vc.status = 'active' AND NOW() BETWEEN vc.start_date AND vc.end_date"
        );
        $voucherStmt->execute([$voucherCode]);
        $voucher = $voucherStmt->fetch();

        $categoryMatches = $voucher && $voucher->category_id !== null
            ? array_reduce($items, fn($found, $item) => $found || (int) $item->category_id === (int) $voucher->category_id, false)
            : true;

        if (!$voucher) {
            $_err['voucher_code'] = 'This voucher code is invalid or expired.';
        } elseif (!$categoryMatches) {
            $_err['voucher_code'] = 'This voucher only applies to products in a specific category not in your cart.';
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

        try {
            // Both payment methods create the order immediately (Shopee-style
            // for card — the order exists in a 'pending' state before the
            // buyer has actually paid; COD just never leaves 'pending' until
            // the courier collects on delivery, or an admin marks it later).
            $orderId = create_order_from_cart(
                $_db,
                $_user,
                $items,
                $addressFields,
                $paymentMethod,
                $voucher,
                $discountAmount,
                $subtotal,
                $shippingFee,
                'pending'
            );
        } catch (RuntimeException $e) {
            $_err['stock'] = $e->getMessage();
        }

        if (!$_err) {
            if ($paymentMethod === 'cod') {
                temp('info', 'Order placed successfully.');
                redirect('/cart/order-confirmation.php?id=' . $orderId);
            }

            // 'card' — the order is already saved; now send the buyer to
            // Stripe to actually pay for it. stripe-return.php marks it paid
            // once Stripe confirms.
            $total = $subtotal - $discountAmount + $shippingFee;
            $session = start_stripe_payment_for_order($orderId, $total, $_user->email);

            if ($session['code'] === 200 && !empty($session['body']->url)) {
                $refStmt = $_db->prepare('UPDATE payment SET transaction_reference = ? WHERE order_id = ?');
                $refStmt->execute([$session['body']->id, $orderId]);
                redirect($session['body']->url);
            }

            error_log('Stripe checkout session error: ' . json_encode($session['body'] ?? null));
            temp('info', 'Order placed, but we could not start the payment page. You can try paying again from your order.');
            redirect('/orders/detail.php?id=' . $orderId);
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
