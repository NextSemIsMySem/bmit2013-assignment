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
                <a href="/page/profile/profile.php" class="profile-link">
                    <h1 class="heading"><?= encode($_user->name) . ' (' . encode($_user->role) . ')' ?></h1>
                    <img class="profile-icon" src="<?= $_user->photo ? '/photos/' . encode($_user->photo) : '/images/profile.png' ?>" alt="Profile">
                </a>
            <?php else: ?>
                <div class="guest-actions" aria-label="Account actions">
                    <a class="guest-action" href="/login.php">Login</a>
                    <a class="guest-action" href="/page/member/register.php">Register</a>
                    <img class="profile-icon" src="/images/profile.png" alt="Profile">
                </div>
            <?php endif; ?>
        </div>
    </header>

    <nav>
        <?php if (($_navSection ?? null) === 'profile'): ?>
        <a href="/page/profile/profile.php">Profile</a>
        <a href="/page/profile/password.php">Password</a>
        <?php else: ?>
        <a href="/index.php">Home</a>
        <?php if ($_user?->role === 'admin'): ?>
        <a href="/page/admin/member/index.php">Members</a>
        <a href="/page/admin/products.php">Products</a>
        <?php endif; ?>
        <!-- Further module nav links are added here per phase (e.g. Product, Cart) -->
        <?php if ($_user?->role !== 'admin'): ?>
        <a href="/page/category.php?category=dumbbells">Dumbbells</a>
        <a href="/page/category.php?category=protein_powder">Protein Powder</a>
        <a href="/page/category.php?category=supplements">Supplements</a>
        <a href="/page/category.php?category=other">Others</a>
        <form id="search-form" class="search-bar" action="/page/search.php" method="get">
            <input id="search-input" type="search" name="name" placeholder="Search products..." aria-label="Search products">
            <button type="submit" aria-label="Search">&#128269;</button>
        </form>
        <button class="cart-button" type="button" aria-label="Shopping cart">
            <img src="/images/cart.png" alt="">
        </button>
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
