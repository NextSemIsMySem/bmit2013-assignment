<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

/*
 * Creates an order from a set of cart items inside a single DB transaction:
 * decrements stock (guarding against a stale/insufficient quantity), inserts
 * the order + its line items + its payment row, marks the voucher used (if
 * any), upserts the user's default shipping address, and clears their cart.
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
    ?string $transactionReference = null
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
            $voucherUpdate = $db->prepare(
                "UPDATE voucher SET status = 'used', used_at = NOW() WHERE id = ?"
            );
            $voucherUpdate->execute([$voucher->id]);
        }

        $existingAddress = $db->prepare('SELECT address_id FROM address WHERE user_id = ? AND is_default = 1');
        $existingAddress->execute([$user->user_id]);
        $addressId = $existingAddress->fetchColumn();

        if ($addressId) {
            $addressUpdate = $db->prepare(
                'UPDATE address SET street = ?, city = ?, state = ?, postal_code = ?, country = ? WHERE address_id = ?'
            );
            $addressUpdate->execute([
                $address['street'],
                $address['city'],
                $address['state'],
                $address['postal_code'],
                $address['country'],
                $addressId,
            ]);
        } else {
            $addressInsert = $db->prepare(
                'INSERT INTO address (user_id, street, city, state, postal_code, country, is_default)
                 VALUES (?, ?, ?, ?, ?, ?, 1)'
            );
            $addressInsert->execute([
                $user->user_id,
                $address['street'],
                $address['city'],
                $address['state'],
                $address['postal_code'],
                $address['country'],
            ]);
        }

        $clearCart = $db->prepare('DELETE FROM cart_item WHERE user_id = ?');
        $clearCart->execute([$user->user_id]);

        $db->commit();

        return $orderId;
    } catch (RuntimeException $e) {
        $db->rollBack();
        throw $e;
    }
}
