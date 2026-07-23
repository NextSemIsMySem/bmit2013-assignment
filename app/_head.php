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
        <a href="/page/category.php?category=dumbbells">Dumbbells</a>
        <a href="/page/category.php?category=protein_powder">Protein Powder</a>
        <a href="/page/category.php?category=supplements">Supplements</a>
    </nav>

    <main>
        <h1><?= $_title ?? 'Untitled' ?></h1>