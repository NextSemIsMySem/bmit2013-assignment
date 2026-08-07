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
    echo json_encode(['message' => 'Please log in to manage your wishlist.']);
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

$wishlistCheck = $_db->prepare(
    'SELECT 1 FROM wishlist_item WHERE user_id = ? AND product_id = ?'
);
$wishlistCheck->execute([$_user->user_id, $productId]);

if ($wishlistCheck->fetchColumn()) {
    $delete = $_db->prepare('DELETE FROM wishlist_item WHERE user_id = ? AND product_id = ?');
    $delete->execute([$_user->user_id, $productId]);

    echo json_encode(['wishlisted' => false, 'message' => 'Removed from Wishlist']);
    exit;
}

$add = $_db->prepare('INSERT INTO wishlist_item (user_id, product_id) VALUES (?, ?)');
$add->execute([$_user->user_id, $productId]);

echo json_encode(['wishlisted' => true, 'message' => 'Added to Wishlist']);
