<?php
require '../_base.php';
require '_order.php';
require '_stripe_session.php';
require '../_stripe_config.php';
require '../lib/stripe.php';
require '../admin/voucher/_voucher_expire.php';
require '../orders/_receipt_mail.php';
auth('member');

apply_voucher_expiry($_db);

$shippingFee = 10.00;
$paymentMethods = [
    'cod' => 'Cash on Delivery',
    'card' => 'Pay with Card (Stripe)',
];

// Three ways to land here: "Buy Now" from a product page (never touches the
// cart), or a normal cart checkout — either the whole cart or just the
// items the member ticked on cart.php.
$mode = req('mode');
if ($mode === '') {
    $mode = isset($_GET['product_id']) ? 'buy_now' : 'cart';
}

if ($mode === 'buy_now') {
    $buyProductId = filter_var(req('product_id'), FILTER_VALIDATE_INT);
    $buyQuantity = filter_var(req('quantity'), FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);

    if (!$buyProductId) {
        redirect('/index.php');
    }

    $productStmt = $_db->prepare(
        'SELECT product_id, product_name, price, stock, availability FROM product WHERE product_id = ?'
    );
    $productStmt->execute([$buyProductId]);
    $product = $productStmt->fetch();

    if (!$product || !$product->availability || $product->stock < 1) {
        temp('info', 'This product is not available right now.');
        redirect('/index.php');
    }

    $buyQuantity = max(1, min($product->stock, $buyQuantity));

    $items = [(object) [
        'product_id' => $product->product_id,
        'product_name' => $product->product_name,
        'price' => $product->price,
        'quantity' => $buyQuantity,
    ]];
} else {
    $mode = 'cart';
    $selectedIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_REQUEST['selected'] ?? [])))));

    $sql = 'SELECT p.product_id, p.product_name, p.price, p.stock, p.availability, ci.quantity
            FROM cart_item ci
            JOIN product p ON p.product_id = ci.product_id
            WHERE ci.user_id = ?';
    $params = [$_user->user_id];

    if ($selectedIds) {
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $sql .= " AND ci.product_id IN ($placeholders)";
        $params = array_merge($params, $selectedIds);
    }

    $stmt = $_db->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    if (!$items) {
        temp('info', $selectedIds ? 'Please select at least one item to check out.' : 'Your cart is empty.');
        redirect('/cart/cart.php');
    }
}

$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item->price * $item->quantity;
}

// Shipping address always comes from the buyer's address book (managed at
// /user/address.php) — checkout only lets them pick which saved address to
// ship to, never type one in freehand.
$returnUrl = $_SERVER['REQUEST_URI'];

$addressStmt = $_db->prepare('SELECT * FROM address WHERE user_id = ? AND deleted_at IS NULL ORDER BY is_default DESC, address_id DESC');
$addressStmt->execute([$_user->user_id]);
$addresses = $addressStmt->fetchAll();

if (!$addresses) {
    temp('info', 'Please add a shipping address before checking out.');
    redirect('/user/address-form.php?return=' . urlencode($returnUrl));
}

$voucher = null;
$discountAmount = 0;

if (is_post()) {
    $applyVoucherOnly = req('apply_voucher') === '1';
    $clearVoucher = req('clear_voucher') === '1';

    $addressId = filter_var(req('address_id'), FILTER_VALIDATE_INT);
    $selectedAddress = null;
    foreach ($addresses as $candidate) {
        if ((int) $candidate->address_id === $addressId) {
            $selectedAddress = $candidate;
            break;
        }
    }

    $paymentMethod = req('payment_method');
    // Clearing ignores whatever the (readonly) field still holds and blanks
    // the field on re-render too, rather than just skipping the lookup below
    // and leaving the old code sitting there uneditable.
    $voucherCode = $clearVoucher ? '' : strtoupper(trim(req('voucher_code')));
    $_REQUEST['voucher_code'] = $voucherCode;

    if ($voucherCode !== '' && !preg_match('/^[0-9A-Z]+$/', $voucherCode)) {
        // Client-side JS already restricts typed input to this charset, so
        // this only matters for a crafted request — a code outside it can
        // never match a real one anyway, so treat it the same as not found
        // rather than running a query that can't possibly match.
        $_err['voucher_code'] = 'This voucher code is invalid or expired.';
        $_REQUEST['voucher_code'] = '';
    } elseif ($voucherCode !== '') {
        $voucherStmt = $_db->prepare(
            "SELECT v.id, v.code, v.status, vc.discount_type, vc.discount_value, vc.discount_percentage, vc.minimum_spend
             FROM voucher v
             JOIN voucher_configuration vc ON vc.voucher_id = v.voucher_id
             WHERE v.code = ? AND v.status = 'active' AND vc.status = 'active' AND NOW() BETWEEN vc.start_date AND vc.end_date"
        );
        $voucherStmt->execute([$voucherCode]);
        $voucher = $voucherStmt->fetch();

        if (!$voucher) {
            // The code itself is wrong/expired, not just currently
            // inapplicable — retyping the same value would fail again, so
            // clear it rather than leave it sitting there to retry as-is.
            // A code that's valid but blocked by minimum spend (below) stays
            // in the field, since re-submitting it later can still work.
            $_err['voucher_code'] = 'This voucher code is invalid or expired.';
            $_REQUEST['voucher_code'] = '';
        } elseif ($subtotal < $voucher->minimum_spend) {
            $_err['voucher_code'] = 'Minimum spend of RM ' . number_format($voucher->minimum_spend, 2) . ' not met.';
        } else {
            $discountAmount = $voucher->discount_type === 'percentage'
                ? round($subtotal * ((float) $voucher->discount_percentage / 100), 2)
                : (float) $voucher->discount_value;
            $discountAmount = min($discountAmount, $subtotal);
        }
    } elseif ($applyVoucherOnly) {
        // Placing an order without a voucher is fine (it's optional) — but
        // clicking Apply specifically implies there should be a code to
        // apply, so an empty field there is worth flagging rather than
        // silently doing nothing.
        $_err['voucher_code'] = 'Please enter a voucher code.';
    }

    if (!$applyVoucherOnly && !$clearVoucher) {
        if (!$selectedAddress) {
            $_err['address_id'] = 'Please select a shipping address.';
        }

        if (!array_key_exists($paymentMethod, $paymentMethods)) {
            $_err['payment_method'] = 'Please select a payment method.';
        }

        if (!$_err) {
            $addressFields = [
                'street' => $selectedAddress->street,
                'city' => $selectedAddress->city,
                'state' => $selectedAddress->state,
                'postal_code' => $selectedAddress->postal_code,
                'country' => $selectedAddress->country,
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
                    'pending',
                    null,
                    $mode === 'buy_now' ? [] : null
                );
            } catch (RuntimeException $e) {
                $_err['stock'] = $e->getMessage();
            }

            if (!$_err) {
                if ($paymentMethod === 'cod') {
                    send_order_receipt_email($_db, $orderId);
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
}

$selectedAddressId = filter_var(req('address_id'), FILTER_VALIDATE_INT) ?: (int) $addresses[0]->address_id;

// Once a code has actually been validated and its discount applied, the
// field locks in place rather than staying editable — matches place-order's
// error-recovery path too: e.g. a missing address bounces back here with the
// voucher still applied, so it shouldn't invite retyping a code that already worked.
$voucherApplied = $voucher && !isset($_err['voucher_code']);

$_title = 'Checkout';
include '../_head.php';
?>

<form class="checkout-layout" method="post">
    <input type="hidden" name="mode" value="<?= encode($mode) ?>">
    <?php if ($mode === 'buy_now'): ?>
        <input type="hidden" name="product_id" value="<?= $items[0]->product_id ?>">
        <input type="hidden" name="quantity" value="<?= $items[0]->quantity ?>">
    <?php else: ?>
        <?php foreach ($items as $item): ?>
            <input type="hidden" name="selected[]" value="<?= $item->product_id ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <section class="checkout-section">
        <h2>Shipping Address</h2>
        <div class="address-list checkout-address-list">
            <?php foreach ($addresses as $address): ?>
                <label class="address-card address-card--selectable">
                    <input
                        type="radio"
                        name="address_id"
                        value="<?= (int) $address->address_id ?>"
                        <?= $selectedAddressId === (int) $address->address_id ? 'checked' : '' ?>
                    >
                    <h3><?= encode($address->label) ?> <?php if ($address->is_default): ?><span class="address-default">Default</span><?php endif; ?></h3>
                    <p><?= encode($address->street) ?><br><?= encode($address->city) ?>, <?= encode($address->state) ?> <?= encode($address->postal_code) ?><br><?= encode($address->country) ?></p>
                </label>
            <?php endforeach; ?>
        </div>
        <?= err('address_id') ?>
        <p><a href="/user/address-form.php?return=<?= encode(urlencode($returnUrl)) ?>">+ Add New Address</a></p>
    </section>

    <section class="checkout-section">
        <h2>Payment</h2>
        <div class="form checkout-form">
            <?php html_select('payment_method', 'Payment Method', $paymentMethods); ?>

            <label for="voucher_code">Voucher Code (optional)</label>
            <div class="voucher-code-field">
                <input
                    type="text"
                    id="voucher_code"
                    name="voucher_code"
                    value="<?= encode(req('voucher_code')) ?>"
                    <?= $voucherApplied ? 'readonly' : '' ?>
                >
                <button type="submit" name="apply_voucher" value="1" class="btn-blue" <?= $voucherApplied ? 'disabled' : '' ?>>Apply</button>
                <?php if ($voucherApplied): ?>
                    <button type="submit" name="clear_voucher" value="1" class="btn-dark">Clear</button>
                <?php endif; ?>
            </div>
            <?= err('voucher_code') ?>
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
        <?php if ($discountAmount > 0): ?>
            <p class="checkout-totals-line">Discount: -RM <?= encode(number_format($discountAmount, 2)) ?></p>
        <?php endif; ?>
        <p class="checkout-totals-line">Shipping: RM <?= encode(number_format($shippingFee, 2)) ?></p>
        <p class="checkout-totals-line checkout-totals-grand">Total: RM <?= encode(number_format($subtotal - $discountAmount + $shippingFee, 2)) ?></p>

        <?= err('stock') ?>

        <section class="buttons">
            <button type="submit" class="place-order-button">Place Order</button>
            <?php if ($mode === 'buy_now'): ?>
                <a href="/product/product.php?id=<?= $items[0]->product_id ?>" class="checkout-back">Back to Product</a>
            <?php else: ?>
                <a href="/cart/cart.php" class="checkout-back">Back to Cart</a>
            <?php endif; ?>
        </section>
    </section>
</form>

<?php
include '../_foot.php';
