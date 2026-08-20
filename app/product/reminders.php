<?php
require '../_base.php';
auth();

$stmt = $_db->prepare(
    'SELECT p.product_id AS id, p.product_name AS name,
            (SELECT product_imageid FROM product_image WHERE product_id = p.product_id ORDER BY product_imageid LIMIT 1) AS image
     FROM stock_reminder sr
     JOIN product p ON p.product_id = sr.product_id
     WHERE sr.user_id = ? AND p.stock > 0 AND p.availability = 1
     ORDER BY p.product_name ASC'
);
$stmt->execute([$_user->user_id]);
$myReminders = $stmt->fetchAll();

$_title = 'Reminders';
include '../_head.php';
?>

<ul class="reminder-list reminder-list--full" id="reminder-list" <?= $myReminders ? '' : 'hidden' ?>>
    <?php foreach ($myReminders as $reminderProduct): ?>
        <li class="reminder-list__item" data-product-id="<?= htmlspecialchars($reminderProduct->id) ?>">
            <img
                class="reminder-list__photo"
                src="<?= !empty($reminderProduct->image) ? '/photos/' . htmlspecialchars($reminderProduct->image) : '/images/sport.png' ?>"
                alt=""
            >
            <div class="reminder-list__content">
                <p>
                    <strong><?= htmlspecialchars($reminderProduct->name) ?></strong>
                    is back in stock! Quickly buy now.
                </p>
                <a class="btn-green" href="/product/product.php?id=<?= htmlspecialchars($reminderProduct->id) ?>">Check it Out</a>
            </div>
            <button
                type="button"
                class="round-delete-button"
                data-stock-reminder-cancel
                data-product-id="<?= htmlspecialchars($reminderProduct->id) ?>"
                aria-label="Dismiss reminder for <?= htmlspecialchars($reminderProduct->name) ?>"
            >
                <img src="/images/delete.png" alt="">
            </button>
        </li>
    <?php endforeach; ?>
</ul>
<p id="reminder-list-empty" <?= $myReminders ? 'hidden' : '' ?>>You don't have any stock reminders yet.</p>

<?php include '../_foot.php'; ?>
