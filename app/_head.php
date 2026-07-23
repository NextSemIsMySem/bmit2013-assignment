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
            <h1 class="demotitle">Shopping Center Demo</h1>
            <a href="/" class="profile-link">
                <h1 class="heading"><?= $_user ? encode($_user->name) . ' (' . encode($_user->role) . ')' : 'Login/Register' ?></h1>
                <img class="profile-icon" src="/images/profile.png" alt="Profile">
            </a>
        </div>
    </header>

    <nav>
        <a href="/index.php">Home</a>
        <?php if ($_user?->role === 'admin'): ?>
        <a href="/page/member/index.php">Members</a>
        <?php endif; ?>
        <?php if ($_user): ?>
        <a href="/logout.php">Logout</a>
        <?php else: ?>
        <a href="/login.php">Login</a>
        <a href="/page/member/register.php">Register</a>
        <?php endif; ?>
        <!-- Further module nav links are added here per phase (e.g. Product, Cart) -->
    </nav>

    <div id="info"><?= temp('info') ?></div>

    <main>
        <h1><?= $_title ?? 'Untitled' ?></h1>
