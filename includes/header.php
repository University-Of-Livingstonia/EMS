<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'UNILIA - Campus Events'; ?></title>
    
    <!-- Futuristic Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Your Enhanced Stylesheets -->
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/futuristic.css">
    <link rel="stylesheet" href="../../assets/css/homepage.css">
    
    <!-- Meta Tags for Better SEO -->
    <meta name="description" content="<?php echo $page_description ?? 'Advanced Campus Event Management System'; ?>">
    <meta name="keywords" content="campus events, university, student activities">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../assets/images/favicon.ico">
</head>
<body class="<?php echo $body_class ?? ''; ?>">
    
    <!-- Accessibility Panel -->
    <div class="accessibility-panel glass-morphism" id="accessibilityPanel">
        <button class="accessibility-toggle" aria-label="Accessibility Options">
            ♿ <span>Accessibility</span>
        </button>
        <div class="accessibility-options">
            <button data-action="high-contrast" aria-label="Toggle High Contrast">🎨 High Contrast</button>
            <button data-action="large-text" aria-label="Increase Text Size">🔍 Large Text</button>
            <button data-action="reduce-motion" aria-label="Reduce Animations">⏸️ Reduce Motion</button>
            <button data-action="focus-mode" aria-label="Focus Mode">🎯 Focus Mode</button>
        </div>
    </div>

    <!-- Enhanced Navigation with Logo + Text -->
    <header class="main-header glass-morphism">
        <nav class="navbar perspective-container">
            <!-- Logo Section with Image + Text -->
            <div class="logo-container">
                <a href="../../index.php" class="logo-link" aria-label="UNILIA Home">
                    <div class="logo-wrapper">
                        <img src="../../assets/images/logo.png" 
                             alt="UNILIA School Logo" 
                             class="logo-image">
                        <span class="logo-text gradient-text">UNILIA</span>
                    </div>
                </a>
            </div>
            
            <div class="nav-links">
                <a href="../../index.php" class="nav-link <?php echo ($current_page == 'home') ? 'active' : ''; ?>">
                    🏠 <span>Home</span>
                </a>
                <a href="events.php" class="nav-link <?php echo ($current_page == 'events') ? 'active' : ''; ?>">
                    📅 <span>Events</span>
                </a>
                <a href="about.php" class="nav-link <?php echo ($current_page == 'about') ? 'active' : ''; ?>">
                    ℹ️ <span>About</span>
                </a>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="nav-link">
                        👤 <span>Dashboard</span>
                    </a>
                    <a href="logout.php" class="nav-link">
                        🚪 <span>Logout</span>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="nav-link btn-3d">
                        🔐 <span>Login</span>
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Mobile Menu Toggle -->
            <button class="mobile-menu-toggle" aria-label="Toggle Mobile Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </nav>
    </header>