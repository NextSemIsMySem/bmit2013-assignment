<?php
require '../_base.php';

header('Content-Type: application/json');

if (!is_post()) {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed.']);
    exit;
}

if (!$_user) {
    http_response_code(401);
    echo json_encode(['message' => 'Please log in to get notified.']);
    exit;
}

$productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
if (!$productId) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid product.']);
    exit;
}

$productCheck = $_db->prepare('SELECT 1 FROM product WHERE product_id = ?');
$productCheck->execute([$productId]);
if (!$productCheck->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['message' => 'Product not found.']);
    exit;
}

$reminderCheck = $_db->prepare(
    'SELECT 1 FROM stock_reminder WHERE user_id = ? AND product_id = ?'
);
$reminderCheck->execute([$_user->user_id, $productId]);

if ($reminderCheck->fetchColumn()) {
    $delete = $_db->prepare('DELETE FROM stock_reminder WHERE user_id = ? AND product_id = ?');
    $delete->execute([$_user->user_id, $productId]);

    echo json_encode(['reminded' => false, 'message' => 'Reminder cancelled.']);
    exit;
}

$add = $_db->prepare('INSERT INTO stock_reminder (user_id, product_id) VALUES (?, ?)');
$add->execute([$_user->user_id, $productId]);

echo json_encode(['reminded' => true, 'message' => "You'll be notified when this product is back in stock."]);
