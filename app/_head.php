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
                <h1 class="heading">Profile</h1>
                <img class="profile-icon" src="/images/profile.png" alt="Profile">
            </a>
        </div>
    </header>

    <nav>
        <a href="/index.php">Home</a>
        <!-- TODO Phase 2: wrap in an admin-only auth() check -->
        <a href="/page/member/index.php">Members</a>
        <!-- TODO Phase 2: only show Register when logged out (replace with a login link when logged in) -->
        <a href="/page/member/register.php">Register</a>
        <!-- Further module nav links are added here per phase (e.g. Product, Cart) -->
    </nav>

    <div id="info"><?= temp('info') ?></div>

    <main>
        <h1><?= $_title ?? 'Untitled' ?></h1>
