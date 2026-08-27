<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

/*
 * Creates an order from a set of items inside a single DB transaction:
 * decrements stock (guarding against a stale/insufficient quantity), inserts
 * the order + its line items + its payment row, marks the voucher used (if
 * any), and clears whichever cart_item rows the order actually covers.
 *
 * $address is a snapshot of an existing address book entry the buyer picked
 * at checkout — the address book itself is managed entirely from
 * user/address.php, so this never writes back to the `address` table.
 *
 * $cartProductIdsToClear controls that last step: leave it null (the
 * default) to clear exactly the product_ids in $items — correct for a
 * normal cart checkout, whether that's the whole cart or a selected
 * subset. Pass an explicit [] for a "Buy Now" purchase that never touched
 * cart_item in the first place, so nothing gets deleted from the cart.
 *
 * Throws RuntimeException (and rolls back) if any line no longer has enough
 * stock. Returns the new order_id on success.
 */
function create_order_from_cart(
    PDO $db,
    object $user,
    array $items,
    array $address,
    string $paymentMethod,
    ?object $voucher,
    float $discountAmount,
    float $subtotal,
    float $shippingFee,
    string $paymentStatus,
    ?string $transactionReference = null,
    ?array $cartProductIdsToClear = null
): int {
    $total = $subtotal - $discountAmount + $shippingFee;
    $orderStatus = $paymentStatus === 'success' ? 'paid' : 'pending';

    $db->beginTransaction();

    try {
        foreach ($items as $item) {
            $stockUpdate = $db->prepare(
                'UPDATE product SET stock = stock - ? WHERE product_id = ? AND stock >= ? AND availability = 1'
            );
            $stockUpdate->execute([$item->quantity, $item->product_id, $item->quantity]);

            if ($stockUpdate->rowCount() === 0) {
                throw new RuntimeException('Not enough stock for "' . $item->product_name . '". Please update your cart.');
            }
        }

        $orderStmt = $db->prepare(
            'INSERT INTO orders
                (user_id, voucher_id, subtotal, shipping_fee, discount_amount,
                 shipping_street, shipping_city, shipping_state, shipping_postal_code, shipping_country, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $orderStmt->execute([
            $user->user_id,
            $voucher->id ?? null,
            $subtotal,
            $shippingFee,
            $discountAmount,
            $address['street'],
            $address['city'],
            $address['state'],
            $address['postal_code'],
            $address['country'],
            $orderStatus,
        ]);
        $orderId = (int) $db->lastInsertId();

        $lineStmt = $db->prepare(
            'INSERT INTO order_product (order_id, product_id, quantity, unit_price, final_price)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $lineStmt->execute([
                $orderId,
                $item->product_id,
                $item->quantity,
                $item->price,
                $item->price * $item->quantity,
            ]);
        }

        $paymentStmt = $db->prepare(
            'INSERT INTO payment (order_id, amount, payment_method, status, transaction_reference)
             VALUES (?, ?, ?, ?, ?)'
        );
        $paymentStmt->execute([$orderId, $total, $paymentMethod, $paymentStatus, $transactionReference]);

        if ($voucher) {
            // Guarded the same way as the stock decrement above: only claims
            // the voucher if it's still active, so two concurrent checkouts
            // racing on the same one-time code can't both redeem it.
            $voucherUpdate = $db->prepare(
                "UPDATE voucher SET status = 'used', used_at = NOW() WHERE id = ? AND status = 'active'"
            );
            $voucherUpdate->execute([$voucher->id]);

            if ($voucherUpdate->rowCount() === 0) {
                throw new RuntimeException('This voucher was just used elsewhere. Please remove it and try again.');
            }
        }

        $cartProductIdsToClear ??= array_map(fn($item) => $item->product_id, $items);

        if ($cartProductIdsToClear) {
            $placeholders = implode(',', array_fill(0, count($cartProductIdsToClear), '?'));
            $clearCart = $db->prepare(
                "DELETE FROM cart_item WHERE user_id = ? AND product_id IN ($placeholders)"
            );
            $clearCart->execute([$user->user_id, ...$cartProductIdsToClear]);
        }

        $db->commit();

        return $orderId;
    } catch (RuntimeException $e) {
        $db->rollBack();
        throw $e;
    }
}
