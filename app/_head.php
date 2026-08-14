<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?? 'Untitled' ?></title>
    <link rel="stylesheet" href="/css/app.css">
    <link rel="icon" href="data:,">
    <link rel="shortcut icon" href="data:,">
    <script>const isLoggedIn = <?= $_user ? 'true' : 'false' ?>;</script>
</head>

<body>
    <header>
        <div class="title">
            <a href="/">
                <img class="sport" src="/images/sport.png" alt="Logo">
            </a>
            <h1 class="demotitle">ForgeFit Fitness Market</h1>
            <?php if ($_user): ?>
                <a href="/user/profile.php" class="profile-link" id="profile-link" aria-haspopup="true" aria-expanded="false">
                    <h1 class="heading"><?= encode($_user->name) . ' (' . encode($_user->role) . ')' ?></h1>
                    <img class="profile-icon" src="<?= $_user->photo ? '/photos/' . encode($_user->photo) : '/images/profile.png' ?>" alt="Profile">
                </a>
                <div class="profile-menu" id="profile-menu" role="menu" aria-hidden="true">
                    <a role="menuitem" href="/user/profile.php">Change Profile</a>
                    <a role="menuitem" href="/user/password.php">Change Password</a>
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
        <?php if (($_navSection ?? null) === 'profile'): ?>
        <a href="/user/profile.php">Profile</a>
        <a href="/user/password.php">Password</a>
        <?php else: ?>
        <a href="/index.php">Home</a>
        <?php if ($_user?->role === 'admin'): ?>
        <a href="/admin/member/index.php">Members</a>
        <a href="/admin/product/products.php">Products</a>
        <?php endif; ?>
        <!-- Further module nav links are added here per phase (e.g. Product, Cart) -->
        <?php if ($_user?->role !== 'admin'): ?>
        <a href="/product/category.php?category=dumbbells">Dumbbells</a>
        <a href="/product/category.php?category=protein_powder">Protein Powder</a>
        <a href="/product/category.php?category=supplements">Supplements</a>
        <a href="/product/category.php?category=other">Others</a>
        <a href="/orders/history.php">My Orders</a>
        <form id="search-form" class="search-bar" action="/product/search.php" method="get">
            <input id="search-input" type="search" name="name" placeholder="Search products..." aria-label="Search products">
            <button type="submit" aria-label="Search">&#128269;</button>
        </form>
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
    </nav>

    <dialog id="search-empty-dialog" aria-labelledby="search-empty-message">
        <p id="search-empty-message">Please enter a product name.</p>
        <button id="search-empty-close" type="button">OK</button>
    </dialog>

    <div id="info"><?= temp('info') ?></div>

    <main>
        <h1><?= $_title ?? 'Untitled' ?></h1>
