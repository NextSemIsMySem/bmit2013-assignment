<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?? 'Untitled' ?></title>
    <link rel="stylesheet" href="/css/app.css">
    <link rel="icon" href="data:,">
    <link rel="shortcut icon" href="data:,">
</head>

<body>
    <header>
        <div class="title">
            <a href="/">
                <img class="faker" src="/images/sport.png" alt="Logo">
            </a>
            <h1 class="demotitle">ForgeFit Fitness Market</h1>
            <a href="/" class="profile-link">
                <h1 class="heading"><?= $_user ? encode($_user->name) . ' (' . encode($_user->role) . ')' : 'Login/Register' ?></h1>
                <img class="profile-icon" src="<?= $_user?->photo ? '/photos/' . encode($_user->photo) : '/images/profile.png' ?>" alt="Profile">
            </a>
        </div>
    </header>

    <nav>
        <a href="/index.php">Home</a>
        <?php if ($_user?->role === 'admin'): ?>
        <a href="/page/member/index.php">Members</a>
        <?php endif; ?>
        <?php if ($_user): ?>
        <a href="/page/user/profile.php">Profile</a>
        <a href="/page/user/password.php">Password</a>
        <a href="/logout.php">Logout</a>
        <?php else: ?>
        <a href="/login.php">Login</a>
        <a href="/page/member/register.php">Register</a>
        <?php endif; ?>
        <!-- Further module nav links are added here per phase (e.g. Product, Cart) -->
        <a href="/page/category.php?category=dumbbells">Dumbbells</a>
        <a href="/page/category.php?category=protein_powder">Protein Powder</a>
        <a href="/page/category.php?category=supplements">Supplements</a>
        <a href="/page/category.php?category=other">Others</a>
        <form id="search-form" class="search-bar" action="/page/search.php" method="get">
            <input id="search-input" type="search" name="name" placeholder="Search products..." aria-label="Search products">
            <button type="submit" aria-label="Search">&#128269;</button>
        </form>
    </nav>

    <dialog id="search-empty-dialog" aria-labelledby="search-empty-message">
        <p id="search-empty-message">Please enter a product name.</p>
        <button id="search-empty-close" type="button">OK</button>
    </dialog>

    <div id="info"><?= temp('info') ?></div>

    <main>
        <h1><?= $_title ?? 'Untitled' ?></h1>
