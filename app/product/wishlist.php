<?php
require '../_base.php';
auth();

$stmt = $_db->prepare(
    'SELECT p.product_id AS id, p.product_name AS name, p.price, p.availability, p.stock,
            (SELECT product_imageid FROM product_image WHERE product_id = p.product_id ORDER BY product_imageid LIMIT 1) AS image
     FROM wishlist_item AS w
     JOIN product AS p ON p.product_id = w.product_id
     WHERE w.user_id = ?
     ORDER BY w.added_at DESC'
);
$stmt->execute([$_user->user_id]);
$products = $stmt->fetchAll();

$_title = 'My Wishlist';
include '../_head.php';
?>

<?php
$productGridClass = 'product-grid--wishlist';
$showWishlistDelete = true;
include 'product_template.php';
?>

<dialog id="wishlist-confirm-dialog" aria-labelledby="wishlist-confirm-message">
    <p id="wishlist-confirm-message">Remove this item from your wishlist?</p>
    <div class="wishlist-confirm-actions">
        <button id="wishlist-confirm-cancel" class="btn-dark" type="button">Cancel</button>
        <button id="wishlist-confirm-remove" class="btn-red" type="button">Remove</button>
    </div>
</dialog>

<?php include '../_foot.php'; ?>
