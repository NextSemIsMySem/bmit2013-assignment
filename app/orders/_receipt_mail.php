<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/_status.php';

/*
 * Emails the e-receipt for an order to its buyer. Called once the order is
 * actually placed — immediately for COD, or once Stripe confirms payment for
 * card orders (see cart/checkout.php and cart/stripe-return.php).
 *
 * Styled to match the printed receipt in orders/_order_detail.php (a formal
 * invoice layout) — inline styles throughout since email clients don't
 * reliably honor external/`<style>` CSS.
 *
 * Never throws: a failed send is logged and swallowed so it can't block an
 * order that has already gone through.
 */
function send_order_receipt_email(PDO $db, int $orderId): bool {
    $orderStmt = $db->prepare(
        'SELECT o.*, u.name AS customer_name, u.email AS customer_email
         FROM orders o
         JOIN user u ON u.user_id = o.user_id
         WHERE o.order_id = ?'
    );
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();

    if (!$order) {
        return false;
    }

    $itemsStmt = $db->prepare(
        'SELECT op.quantity, op.unit_price, op.final_price, p.product_name
         FROM order_product op
         JOIN product p ON p.product_id = op.product_id
         WHERE op.order_id = ?'
    );
    $itemsStmt->execute([$orderId]);
    $items = $itemsStmt->fetchAll();

    $paymentStmt = $db->prepare('SELECT payment_method, status FROM payment WHERE order_id = ?');
    $paymentStmt->execute([$orderId]);
    $payment = $paymentStmt->fetch();

    $paymentMethods = ['cod' => 'Cash on Delivery', 'card' => 'Card (Stripe)'];
    $total = $order->subtotal - $order->discount_amount + $order->shipping_fee;

    $rows = '';
    foreach ($items as $item) {
        $rows .= '<tr>'
            . '<td style="padding:7px 10px;border:1px solid #000000;">' . encode($item->product_name) . '</td>'
            . '<td style="padding:7px 10px;border:1px solid #000000;text-align:right;">' . (int) $item->quantity . '</td>'
            . '<td style="padding:7px 10px;border:1px solid #000000;text-align:right;">RM ' . number_format($item->unit_price, 2) . '</td>'
            . '<td style="padding:7px 10px;border:1px solid #000000;text-align:right;">RM ' . number_format($item->final_price, 2) . '</td>'
            . '</tr>';
    }

    $body = '
        <div style="font-family:Georgia,\'Times New Roman\',serif;color:#000000;max-width:700px;margin:0 auto;">
            <div style="text-align:center;margin:0 0 20px;padding-bottom:14px;border-bottom:2px solid #000000;">
                <h2 style="margin:0 0 4px;font-size:24px;letter-spacing:0.04em;">ForgeFit Fitness Market</h2>
                <p style="margin:0;font-size:13px;color:#333333;">Fitness Equipment &amp; Supplements Store &middot; Official Receipt</p>
            </div>

            <table style="width:100%;border-collapse:collapse;margin:0 0 18px;font-size:13px;line-height:1.7;">
                <tr>
                    <td style="vertical-align:top;">
                        <strong>Receipt #' . (int) $order->order_id . '</strong><br>
                        Date: ' . encode(date('d M Y, g:ia', strtotime($order->created_at))) . '<br>
                        Status: ' . encode(order_status_label($order->status)) . '
                    </td>
                    <td style="vertical-align:top;text-align:right;">
                        <strong>Payment Method</strong><br>
                        ' . encode($paymentMethods[$payment->payment_method ?? ''] ?? ($payment->payment_method ?? '-')) . '<br>
                        Payment Status: ' . encode(ucfirst($payment->status ?? '-')) . '
                    </td>
                </tr>
            </table>

            <table style="width:100%;border-collapse:collapse;margin:0 0 18px;font-size:13px;line-height:1.7;">
                <tr>
                    <td style="vertical-align:top;">
                        <strong>Billed To</strong><br>
                        ' . encode($order->customer_name) . '<br>
                        ' . encode($order->customer_email) . '
                    </td>
                    <td style="vertical-align:top;text-align:right;">
                        <strong>Shipping Address</strong><br>
                        ' . encode($order->shipping_street) . '<br>
                        ' . encode($order->shipping_city) . ', ' . encode($order->shipping_state) . ' ' . encode($order->shipping_postal_code) . '<br>
                        ' . encode($order->shipping_country) . '
                    </td>
                </tr>
            </table>

            <table style="width:100%;border-collapse:collapse;margin:0 0 18px;font-size:13px;">
                <thead>
                    <tr>
                        <th style="padding:7px 10px;border:1px solid #000000;text-align:left;">Item</th>
                        <th style="padding:7px 10px;border:1px solid #000000;text-align:right;">Qty</th>
                        <th style="padding:7px 10px;border:1px solid #000000;text-align:right;">Unit Price</th>
                        <th style="padding:7px 10px;border:1px solid #000000;text-align:right;">Amount</th>
                    </tr>
                </thead>
                <tbody>' . $rows . '</tbody>
            </table>

            <table style="width:280px;margin:0 0 24px auto;font-size:13px;">
                <tr><td>Subtotal</td><td style="text-align:right;">RM ' . number_format($order->subtotal, 2) . '</td></tr>
                <tr><td>Discount</td><td style="text-align:right;">-RM ' . number_format($order->discount_amount, 2) . '</td></tr>
                <tr><td>Shipping</td><td style="text-align:right;">RM ' . number_format($order->shipping_fee, 2) . '</td></tr>
                <tr>
                    <td style="padding-top:8px;border-top:1px solid #000000;font-weight:bold;font-size:16px;">Total</td>
                    <td style="padding-top:8px;border-top:1px solid #000000;text-align:right;font-weight:bold;font-size:16px;">RM ' . number_format($total, 2) . '</td>
                </tr>
            </table>

            <div style="margin-top:24px;padding-top:14px;border-top:1px dashed #000000;text-align:center;font-size:13px;font-style:italic;">
                Thank you for shopping with ForgeFit Fitness Market!
            </div>
        </div>
    ';

    try {
        $m = get_mail();
        $m->addAddress($order->customer_email, $order->customer_name);
        $m->isHTML(true);
        $m->Subject = 'Your ForgeFit Order #' . $order->order_id . ' Receipt';
        $m->Body = $body;
        $m->send();
        return true;
    } catch (Exception $e) {
        error_log('Order receipt email failed for order #' . $orderId . ': ' . $e->getMessage());
        return false;
    }
}
