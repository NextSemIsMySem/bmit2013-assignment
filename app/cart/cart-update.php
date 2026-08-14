<?php
require '../_base.php';
auth('member');

header('Content-Type: application/json');

if (!is_post()) {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed.']);
    exit;
}

$productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
$quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);

if (!$productId || !$quantity) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid quantity.']);
    exit;
}

$stmt = $_db->prepare(
    'SELECT p.price, p.stock
     FROM cart_item ci
     JOIN product p ON p.product_id = ci.product_id
     WHERE ci.user_id = ? AND ci.product_id = ?'
);
$stmt->execute([$_user->user_id, $productId]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    echo json_encode(['message' => 'That item is not in your cart.']);
    exit;
}

if ($row->stock < 1) {
    http_response_code(409);
    echo json_encode(['message' => 'This item is no longer in stock.']);
    exit;
}

$quantity = max(1, min((int) $row->stock, $quantity));

$update = $_db->prepare('UPDATE cart_item SET quantity = ? WHERE user_id = ? AND product_id = ?');
$update->execute([$quantity, $_user->user_id, $productId]);

$totalStmt = $_db->prepare(
    'SELECT COALESCE(SUM(p.price * ci.quantity), 0)
     FROM cart_item ci
     JOIN product p ON p.product_id = ci.product_id
     WHERE ci.user_id = ?'
);
$totalStmt->execute([$_user->user_id]);
$cartSubtotal = (float) $totalStmt->fetchColumn();

echo json_encode([
    'quantity' => $quantity,
    'lineTotal' => number_format($row->price * $quantity, 2),
    'cartSubtotal' => number_format($cartSubtotal, 2),
    'atMin' => $quantity <= 1,
    'atMax' => $quantity >= (int) $row->stock,
]);
