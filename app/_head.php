<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?? 'Untitled' ?></title>
    <link rel="stylesheet" href="/css/app.css?v=<?= filemtime(__DIR__ . '/css/app.css') ?>">
    <?php if (!empty($_photoEditor)): ?>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
    <?php endif; ?>
    <link rel="icon" href="data:,">
    <link rel="shortcut icon" href="data:,">
    <script>const isLoggedIn = <?= $_user ? 'true' : 'false' ?>;</script>
</head>

<body>
    <header>
        <div class="title">
            <?php if (!empty($_backUrl)): ?>
                <a class="back-link" href="<?= encode($_backUrl) ?>" aria-label="<?= encode($_backLabel ?? 'Back') ?>" title="<?= encode($_backLabel ?? 'Back') ?>">&larr;</a>
            <?php endif; ?>
            <a href="<?= is_admin() ? '/admin/member/index.php' : '/' ?>">
                <img class="sport" src="/images/sport.png" alt="Logo">
            </a>
            <h1 class="demotitle">ForgeFit Fitness Market</h1>
            <?php if ($_user): ?>
                <a href="/user/settings.php" class="profile-link" id="profile-link" aria-haspopup="true" aria-expanded="false">
                    <h1 class="heading"><?= encode($_user->name) . ' (' . encode($_user->role) . ')' ?></h1>
                    <img class="profile-icon" src="<?= $_user->photo ? '/photos/' . encode($_user->photo) : '/images/profile.png' ?>" alt="Profile">
                </a>
                <div class="profile-menu" id="profile-menu" role="menu" aria-hidden="true">
                    <a role="menuitem" href="/user/settings.php">Settings</a>
                    <a role="menuitem" href="/user/reset.php">Forgot Password</a>
                    <a role="menuitem" href="/logout.php">Logout</a>
                </div>
            <?php else: ?>
                <div class="guest-actions" aria-label="Account actions">
                    <a class="guest-action" href="/login.php">Login</a>
                    <a class="guest-action" href="/user/register.php">Register</a>
                    <img class="profile-icon" src="/images/profile.png" alt="Profile">
                </div>
            <?php endif; ?>
        </div>
    </header>

    <nav>
        <?php $reminders = []; $newRestocks = []; ?>
        <?php if (($_navSection ?? null) === 'settings'): ?>
        <a href="/user/settings.php">Settings</a>
        <?php elseif (($_navSection ?? null) === 'forgot-password'): ?>
        <a href="/user/reset.php">Forgot Password</a>
        <?php else: ?>
        <?php if (is_admin()): ?>
        <?php if ($_user?->role === 'superadmin'): ?>
        <a href="/admin/administrator/index.php">Admins</a>
        <?php endif; ?>
        <a href="/admin/member/index.php">Members</a>
        <a href="/admin/product/products.php">Products</a>
        <a href="/admin/voucher/vouchers.php">Vouchers</a>
        <a href="/admin/order/orders.php">Orders</a>
        <?php endif; ?>
        <!-- Further module nav links are added here per phase (e.g. Product, Cart) -->
        <?php if (!is_admin()): ?>
        <a href="/product/category.php?category=dumbbells" class="<?= ($_GET['category'] ?? '') === 'dumbbells' ? 'active' : '' ?>">Dumbbells</a>
        <a href="/product/category.php?category=protein_powder" class="<?= ($_GET['category'] ?? '') === 'protein_powder' ? 'active' : '' ?>">Protein Powder</a>
        <a href="/product/category.php?category=supplements" class="<?= ($_GET['category'] ?? '') === 'supplements' ? 'active' : '' ?>">Supplements</a>
        <a href="/product/category.php?category=other" class="<?= ($_GET['category'] ?? '') === 'other' ? 'active' : '' ?>">Others</a>
        <a href="/orders/history.php">My Orders</a>
        <form id="search-form" class="search-bar" action="/product/search.php" method="get">
            <input id="search-input" type="search" name="name" placeholder="Search products..." aria-label="Search products">
            <button type="submit" aria-label="Search">&#128269;</button>
        </form>
        <?php
            $reminders = [];
            $newRestocks = [];
            if ($_user) {
                $reminderStmt = $_db->prepare(
                    'SELECT p.product_id AS id, p.product_name AS name, sr.shown,
                            (SELECT product_imageid FROM product_image WHERE product_id = p.product_id ORDER BY product_imageid LIMIT 1) AS image
                     FROM stock_reminder sr
                     JOIN product p ON p.product_id = sr.product_id
                     WHERE sr.user_id = ? AND p.stock > 0 AND p.availability = 1
                     ORDER BY p.product_name ASC'
                );
                $reminderStmt->execute([$_user->user_id]);
                $reminders = $reminderStmt->fetchAll();

                $newRestocks = array_filter($reminders, fn($r) => !$r->shown);
                if ($newRestocks) {
                    $markShown = $_db->prepare('UPDATE stock_reminder SET shown = 1 WHERE user_id = ? AND product_id = ?');
                    foreach ($newRestocks as $restock) {
                        $markShown->execute([$_user->user_id, $restock->id]);
                    }
                }
            }
            $reminderCount = count($reminders);
        ?>
        <div class="notify-wrapper">
            <button
                type="button"
                class="cart-button"
                id="notify-toggle"
                aria-haspopup="true"
                aria-expanded="false"
                aria-label="Reminders (<?= $reminderCount ?> back in stock)"
            >
                <img class="notify-icon" src="/images/notify.png" alt="">
                <?php if ($reminderCount > 0): ?>
                    <span class="cart-badge"><?= $reminderCount ?></span>
                <?php endif; ?>
            </button>
            <div class="notify-dropdown" id="notify-dropdown" hidden>
                <?php if ($reminders): ?>
                    <ul class="reminder-list">
                        <?php foreach ($reminders as $reminderProduct): ?>
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
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="/product/reminders.php" class="notify-dropdown__show-all">Show All</a>
                <?php else: ?>
                    <p class="notify-dropdown__empty">You don't have any stock reminders yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <a class="cart-button" href="/product/wishlist.php" aria-label="Wishlist">
            <img src="/images/wishlist.png" alt="">
        </a>

        <?php
            $cartCount = 0;
            if ($_user) {
                $cartCountStmt = $_db->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cart_item WHERE user_id = ?');
                $cartCountStmt->execute([$_user->user_id]);
                $cartCount = (int) $cartCountStmt->fetchColumn();
            }
        ?>
        <a class="cart-button" href="/cart/cart.php" aria-label="Shopping cart (<?= $cartCount ?> item<?= $cartCount === 1 ? '' : 's' ?>)">
            <img src="/images/cart.png" alt="">
            <?php if ($cartCount > 0): ?>
                <span class="cart-badge"><?= $cartCount ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        <?php endif; ?>
        <?php if ($_user): ?>
        <a href="/logout.php">Logout</a>
        <?php endif; ?>
    </nav>

    <dialog id="search-empty-dialog" aria-labelledby="search-empty-message">
        <p id="search-empty-message">Please enter a product name.</p>
        <button id="search-empty-close" type="button">OK</button>
    </dialog>

    <?php $activatePrompt = temp('activate_prompt'); ?>
    <?php if ($activatePrompt): ?>
        <dialog id="activate-prompt-dialog" aria-labelledby="activate-prompt-message">
            <p id="activate-prompt-message">
                <?= htmlspecialchars($activatePrompt['name']) ?> is still marked unavailable. Make it available now?
            </p>
            <div class="activate-prompt-actions">
                <form method="post" action="/admin/product/product-activate.php">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($activatePrompt['id']) ?>">
                    <button type="submit" class="btn-green">Yes</button>
                </form>
                <button type="button" class="btn-red" data-get="/admin/product/products.php">No</button>
            </div>
        </dialog>
    <?php endif; ?>

    <?php if ($newRestocks): ?>
        <dialog id="restock-alert-dialog" aria-labelledby="restock-alert-message">
            <p id="restock-alert-message">
                <?php if (count($newRestocks) === 1): ?>
                    <?= htmlspecialchars(reset($newRestocks)->name) ?> is back in stock! Quickly buy now.
                <?php else: ?>
                    Multiple items have been restocked!
                <?php endif; ?>
            </p>
            <?php if (count($newRestocks) === 1): ?>
                <div class="restock-alert-actions">
                    <a class="btn-green" href="/product/product.php?id=<?= htmlspecialchars(reset($newRestocks)->id) ?>">Check It Out</a>
                    <button id="restock-alert-close" class="btn-red" type="button">Later</button>
                </div>
            <?php else: ?>
                <div class="restock-alert-actions">
                    <a class="btn-green" href="/product/reminders.php">Check Reminder</a>
                    <button id="restock-alert-close" class="btn-red" type="button">Later</button>
                </div>
            <?php endif; ?>
        </dialog>
    <?php endif; ?>

    <div id="info"><?= temp('info') ?></div>

    <main>
        <?php if (empty($_hideHeading)): ?>
        <h1><?= $_title ?? 'Untitled' ?></h1>
        <?php endif; ?>
