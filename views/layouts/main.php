<?php
/**
 * Main Layout - Telegram Mini App SPA shell
 * Variables available: $currentPage, optional page-specific data
 */
$currentPage = isset($currentPage) ? $currentPage : 'home';
$version = '1.0.0';
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#007aff">
    <title>Shashka Game</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <link rel="stylesheet" href="/css/app.min.css?v=<?php echo $version; ?>">
</head>
<body>
    <!-- Loading screen -->
    <div class="loading-screen">
        <div class="title-lg">🎯 Shashka</div>
        <div class="spinner"></div>
        <div class="caption">Yuklanmoqda...</div>
    </div>

    <!-- Header -->
    <header class="app-header">
        <div class="row gap-8">
            <img id="userAvatar" class="avatar" src="/images/default-avatar.png" alt="avatar">
            <div>
                <div id="userName" style="font-weight:600;">Mehmon</div>
                <div class="caption">Reyting: <span id="userRating">1000</span></div>
            </div>
        </div>
        <div class="balances">
            <span class="currency diamond">💎 <span id="balanceDiamonds">0</span></span>
            <span class="currency coin">🪙 <span id="balanceCoins">0</span></span>
        </div>
    </header>

    <!-- Pages -->
    <main id="appContent">
        <?php
        // Include all page partials; JS controls which is active
        $pages = ['home', 'game', 'shop', 'profile'];
        foreach ($pages as $p) {
            $pagePath = __DIR__ . '/../pages/' . $p . '.php';
            if (file_exists($pagePath)) {
                $isActive = ($p === $currentPage) ? ' active' : '';
                echo '<section id="page-' . $p . '" class="page' . $isActive . '">';
                require $pagePath;
                echo '</section>';
            }
        }
        ?>
    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a class="nav-item<?php echo $currentPage === 'home' ? ' active' : ''; ?>" data-page="home">
            <span class="icon">🏠</span>
            <span>Bosh</span>
        </a>
        <a class="nav-item<?php echo $currentPage === 'game' ? ' active' : ''; ?>" data-page="game">
            <span class="icon">🎮</span>
            <span>O'yin</span>
        </a>
        <a class="nav-item<?php echo $currentPage === 'shop' ? ' active' : ''; ?>" data-page="shop">
            <span class="icon">🏪</span>
            <span>Do'kon</span>
        </a>
        <a class="nav-item<?php echo $currentPage === 'profile' ? ' active' : ''; ?>" data-page="profile">
            <span class="icon">👤</span>
            <span>Profil</span>
        </a>
    </nav>

    <!-- Toast container -->
    <div class="toast-container"></div>

    <!-- Scripts -->
    <script src="/js/app.min.js?v=<?php echo $version; ?>"></script>
    <script src="/js/board.min.js?v=<?php echo $version; ?>"></script>
    <script src="/js/game.min.js?v=<?php echo $version; ?>"></script>
</body>
</html>
