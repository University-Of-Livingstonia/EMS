<?php
/**
 * 🏠 Epic Landing Page - EMS
 * Ekwendeni Mighty Campus Event Management System
 * The Gateway to Event Paradise! 🎪
 */

require_once 'includes/functions.php';

// Get database connection
$conn = require_once 'config/database.php';

// Initialize session manager
require_once 'includes/session.php';
$sessionManager = new SessionManager($conn);

// Get current user if logged in
$currentUser = $sessionManager->getCurrentUser();
$isLoggedIn = $sessionManager->isLoggedIn();

// Get featured events (approved, public, upcoming)
$featuredEvents = [];
try {
    $stmt = $conn->prepare("
        SELECT e.*, u.first_name, u.last_name,
               (SELECT COUNT(*) FROM tickets WHERE event_id = e.event_id) as registered_count
        FROM events e 
        LEFT JOIN users u ON e.organizer_id = u.user_id 
        WHERE e.status = 'approved' 
        AND e.is_public = 1 
        AND e.start_datetime > NOW() 
        AND e.featured = 1
        ORDER BY e.start_datetime ASC 
        LIMIT 6
    ");
    $stmt->execute();
    $featuredEvents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Featured events error: " . $e->getMessage());
}

// Get upcoming events
$upcomingEvents = [];
try {
    $stmt = $conn->prepare("
        SELECT e.*, u.first_name, u.last_name,
               (SELECT COUNT(*) FROM tickets WHERE event_id = e.event_id) as registered_count
        FROM events e 
        LEFT JOIN users u ON e.organizer_id = u.user_id 
        WHERE e.status = 'approved' 
        AND e.is_public = 1 
        AND e.start_datetime > NOW() 
        ORDER BY e.start_datetime ASC 
        LIMIT 8
    ");
    $stmt->execute();
    $upcomingEvents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Upcoming events error: " . $e->getMessage());
}

// Get system statistics
$stats = [];
try {
    $stmt = $conn->query("SELECT COUNT(*) as total_events FROM events WHERE status = 'approved'");
    $stats['total_events'] = $stmt->fetch_assoc()['total_events'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total_users FROM users WHERE role != 'admin'");
    $stats['total_users'] = $stmt->fetch_assoc()['total_users'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total_tickets FROM tickets");
    $stats['total_tickets'] = $stmt->fetch_assoc()['total_tickets'];
    
    $stmt = $conn->query("SELECT COUNT(*) as active_organizers FROM users WHERE role = 'organizer'");
    $stats['active_organizers'] = $stmt->fetch_assoc()['active_organizers'];
} catch (Exception $e) {
    $stats = ['total_events' => 0, 'total_users' => 0, 'total_tickets' => 0, 'active_organizers' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMS - Ekwendeni Mighty Campus Event Management System</title>
    
    <!-- Meta Tags -->
    <meta name="description" content="Discover, register, and manage campus events at Ekwendeni Mighty Campus. Your gateway to amazing experiences!">
    <meta name="keywords" content="events, campus, university, Ekwendeni, management, tickets">
    <meta name="author" content="Ekwendeni Mighty Campus">
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom Styles -->
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            --success-gradient: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            --warning-gradient: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            --danger-gradient: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            --light-bg: #f8f9fa;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            overflow-x: hidden;
        }
        
        /* 🎨 Navigation Bar */
        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            padding: 1rem 0;
        }
        
        .navbar.scrolled {
            padding: 0.5rem 0;
            background: rgba(255, 255, 255, 0.98) !important;
        }
        
        .navbar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .navbar-nav .nav-link {
            font-weight: 500;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .navbar-nav .nav-link:hover {
            color: #667eea !important;
            transform: translateY(-2px);
        }
        
        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 50%;
            background: var(--primary-gradient);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        
        .navbar-nav .nav-link:hover::after {
            width: 100%;
        }
        
        .btn-nav {
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-left: 0.5rem;
        }
        
        .btn-login {
            background: transparent;
            border: 2px solid #667eea;
            color: #667eea;
        }
        
        .btn-login:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-register {
            background: var(--primary-gradient);
            border: none;
            color: white;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        /* 🚀 Hero Section */
        .hero {
            background: var(--primary-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="60" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="60" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="30" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/><circle cx="70" cy="20" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
            animation: float 20s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            color: white;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }
        
        .hero .lead {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        .hero-buttons {
            margin-top: 2rem;
        }
        
        .btn-hero {
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px;
            margin: 0.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary-hero {
            background: white;
            color: #667eea;
            border: none;
        }
        
        .btn-primary-hero:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(255, 255, 255, 0.3);
            color: #667eea;
        }
        
        .btn-outline-hero {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .btn-outline-hero:hover {
            background: white;
            color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(255, 255, 255, 0.3);
        }
        
        .hero-image {
            position: relative;
            z-index: 2;
        }
        
        .hero-image img {
            max-width: 100%;
            height: auto;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            animation: heroFloat 6s ease-in-out infinite;
        }
        
        @keyframes heroFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        /* 📊 Stats Section */
        .stats-section {
            background: var(--light-bg);
            padding: 5rem 0;
            position: relative;
        }
        
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-hover);
        }
        
        .stat-icon {
            font-size: 3rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--text-muted);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9rem;
        }
        
        /* 🎪 Featured Events Section */
        .featured-events {
            padding: 5rem 0;
            background: white;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .section-title p {
            font-size: 1.1rem;
            color: var(--text-muted);
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* 🎫 Event Cards */
               .event-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .event-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-hover);
        }
        
        .event-image {
            position: relative;
            height: 200px;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            overflow: hidden;
        }
        
        .event-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .event-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.9);
            color: #667eea;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .event-content {
            padding: 1.5rem;
        }
        
        .event-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }
        
        .event-description {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            line-height: 1.5;
        }
        
        .event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .event-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .event-meta-item i {
            color: #667eea;
            width: 16px;
        }
        
        .event-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        
        .event-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-dark);
        }
        
        .event-price.free {
            color: #4CAF50;
        }
        
        .btn-event {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .btn-event:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            color: white;
        }
        
        /* 🎯 Upcoming Events Section */
        .upcoming-events {
            padding: 5rem 0;
            background: var(--light-bg);
        }
        
        .event-list-item {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .event-list-item:hover {
            transform: translateX(10px);
            box-shadow: var(--shadow-hover);
        }
        
        .event-date-badge {
            background: var(--primary-gradient);
            color: white;
            padding: 1rem;
            border-radius: 15px;
            text-align: center;
            min-width: 80px;
            flex-shrink: 0;
        }
        
        .event-date-day {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
        }
        
        .event-date-month {
            font-size: 0.8rem;
            text-transform: uppercase;
            opacity: 0.9;
        }
        
        .event-list-content {
            flex: 1;
        }
        
        .event-list-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }
        
        .event-list-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .event-list-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }
        
        /* 🎨 Features Section */
        .features-section {
            padding: 5rem 0;
            background: white;
        }
        
        .feature-card {
            text-align: center;
            padding: 2rem;
            border-radius: 20px;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
        }
        
        .feature-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-dark);
        }
        
        .feature-description {
            color: var(--text-muted);
            line-height: 1.6;
        }
        
        /* 🎪 CTA Section */
        .cta-section {
            background: var(--primary-gradient);
            padding: 5rem 0;
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="10" cy="10" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="90" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="30" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="30" cy="70" r="0.5" fill="rgba(255,255,255,0.1)"/></svg>');
            animation: float 15s ease-in-out infinite;
        }
        
        .cta-content {
            position: relative;
            z-index: 2;
        }
        
        .cta-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .cta-description {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        /* 🦶 Footer */
        .footer {
            background: var(--text-dark);
            color: white;
            padding: 3rem 0 1rem;
        }
        
        .footer-content {
            margin-bottom: 2rem;
        }
        
        .footer-section h5 {
            font-weight: 600;
            margin-bottom: 1rem;
            color: white;
        }
        
        .footer-section ul {
            list-style: none;
            padding: 0;
        }
        
        .footer-section ul li {
            margin-bottom: 0.5rem;
        }
        
        .footer-section ul li a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .footer-section ul li a:hover {
            color: white;
            transform: translateX(5px);
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 2rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .social-links {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .social-links a {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .social-links a:hover {
            background: white;
            color: #667eea;
            transform: translateY(-3px);
        }
        
        /* 📱 Responsive Design */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .hero .lead {
                font-size: 1.1rem;
            }
            
            .btn-hero {
                display: block;
                margin: 0.5rem 0;
                text-align: center;
            }
            
            .event-list-item {
                flex-direction: column;
                text-align: center;
            }
            
            .event-list-actions {
                justify-content: center;
            }
            
            .section-title h2 {
                font-size: 2rem;
            }
            
            .cta-title {
                font-size: 2rem;
            }
        }
        
        /* 🎨 Animations */
        .fade-in-up {
            animation: fadeInUp 0.8s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .slide-in-left {
            animation: slideInLeft 0.8s ease-out;
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .slide-in-right {
            animation: slideInRight 0.8s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* 🎯 Loading States */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* 🎪 Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .empty-state h4 {
            margin-bottom: 1rem;
            color: var(--text-dark);
        }
    </style>
</head>

<body>
    <!-- 🎨 Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top" id="mainNavbar">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-calendar-star"></i> EMS
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="events.php">Events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                </ul>
                
                <div class="navbar-nav">
                    <?php if ($isLoggedIn): ?>
                        <div class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($currentUser['first_name']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="dashboard/index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                                                               <li><a class="dropdown-item" href="dashboard/profile.php"><i class="fas fa-user"></i> Profile</a></li>
                                <li><a class="dropdown-item" href="dashboard/my-events.php"><i class="fas fa-calendar-alt"></i> My Events</a></li>
                                <?php if ($currentUser['role'] === 'organizer'): ?>
                                    <li><a class="dropdown-item" href="organizer/dashboard.php"><i class="fas fa-plus-circle"></i> Create Event</a></li>
                                <?php endif; ?>
                                <?php if ($currentUser['role'] === 'admin'): ?>
                                    <li><a class="dropdown-item" href="admin/dashboard.php"><i class="fas fa-crown"></i> Admin Panel</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="auth/login.php" class="btn btn-nav btn-login">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                        <a href="auth/register.php" class="btn btn-nav btn-register">
                            <i class="fas fa-user-plus"></i> Register
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- 🚀 Hero Section -->
    <section class="hero" id="home">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content fade-in-up">
                        <h1>Welcome to <span class="text-warning">EMS</span></h1>
                        <p class="lead">Discover amazing events, connect with your campus community, and create unforgettable memories at Ekwendeni Mighty Campus!</p>
                        
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="hero-image slide-in-right">
                        <img src="assets/images/logo.png" alt="Campus Events" class="img-fluid">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 📊 Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card fade-in-up">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-number" data-count="<?php echo $stats['total_events']; ?>">0</div>
                        <div class="stat-label">Total Events</div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card fade-in-up">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number" data-count="<?php echo $stats['total_users']; ?>">0</div>
                        <div class="stat-label">Active Users</div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card fade-in-up">
                        <div class="stat-icon">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <div class="stat-number" data-count="<?php echo $stats['total_tickets']; ?>">0</div>
                        <div class="stat-label">Tickets Sold</div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card fade-in-up">
                        <div class="stat-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stat-number" data-count="<?php echo $stats['active_organizers']; ?>">0</div>
                        <div class="stat-label">Event Organizers</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 🎯 Upcoming Events Section -->
    <section class="upcoming-events">
        <div class="container">
            <div class="section-title fade-in-up">
                <h2><i class="fas fa-clock"></i> Upcoming Events</h2>
                <p>Stay updated with all the exciting events happening soon on campus!</p>
            </div>
            
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <?php if (!empty($upcomingEvents)): ?>
                        <?php foreach ($upcomingEvents as $index => $event): ?>
                            <div class="event-list-item slide-in-left" style="animation-delay: <?php echo $index * 0.1; ?>s">
                                <div class="event-date-badge">
                                    <div class="event-date-day"><?php echo date('j', strtotime($event['start_datetime'])); ?></div>
                                    <div class="event-date-month"><?php echo date('M', strtotime($event['start_datetime'])); ?></div>
                                </div>
                                
                                <div class="event-list-content">
                                    <h4 class="event-list-title"><?php echo htmlspecialchars($event['title']); ?></h4>
                                    <div class="event-list-meta">
                                        <span><i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($event['start_datetime'])); ?></span>
                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['venue']); ?></span>
                                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($event['first_name'] . ' ' . $event['last_name']); ?></span>
                                        <span><i class="fas fa-users"></i> <?php echo $event['registered_count']; ?> registered</span>
                                        <span class="event-price <?php echo $event['price'] == 0 ? 'free' : ''; ?>">
                                            <i class="fas fa-tag"></i> <?php echo $event['price'] == 0 ? 'FREE' : 'K' . number_format($event['price'], 2); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="event-list-actions">
                                    <a href="events/view.php?id=<?php echo $event['event_id']; ?>" class="btn-event">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($isLoggedIn): ?>
                                        <a href="events/register.php?id=<?php echo $event['event_id']; ?>" class="btn-event">
                                            <i class="fas fa-ticket-alt"></i> Register
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h4>No Upcoming Events</h4>
                            <p>There are no upcoming events scheduled at the moment. Check back soon!</p>
                            <?php if ($isLoggedIn && $currentUser['role'] === 'organizer'): ?>
                                <a href="organizer/create-event.php" class="btn-event">
                                    <i class="fas fa-plus"></i> Create Event
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($upcomingEvents)): ?>
                <div class="text-center mt-4">
                    <a href="events/browse.php" class="btn-hero btn-outline-hero">
                        <i class="fas fa-calendar-alt"></i> View All Events
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

  
    <!-- 🦶 Footer -->
    <footer class="footer" id="contact">
        <div class="container">
            
                        <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> EMS - Ekwendeni Mighty Campus. All rights reserved.</p>
                           </div>
        </div>
    </footer>

    <!-- 📱 Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 🎨 Landing Page JavaScript Controller
        class LandingPageController {
            constructor() {
                this.init();
            }
            
            init() {
                this.initNavbar();
                this.initAnimations();
                this.initCounters();
                this.initSmoothScroll();
                this.initEventHandlers();
            }
            
            // 🎯 Navbar Effects
            initNavbar() {
                const navbar = document.getElementById('mainNavbar');
                
                window.addEventListener('scroll', () => {
                    if (window.scrollY > 100) {
                        navbar.classList.add('scrolled');
                    } else {
                        navbar.classList.remove('scrolled');
                    }
                });
            }
            
            // 🎨 Scroll Animations
            initAnimations() {
                const observerOptions = {
                    threshold: 0.1,
                    rootMargin: '0px 0px -50px 0px'
                };
                
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }
                    });
                }, observerOptions);
                
                // Observe all animated elements
                document.querySelectorAll('.fade-in-up, .slide-in-left, .slide-in-right').forEach(el => {
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(30px)';
                    observer.observe(el);
                });
            }
            
            // 📊 Counter Animation
            initCounters() {
                const counters = document.querySelectorAll('.stat-number');
                const speed = 200;
                
                const animateCounter = (counter) => {
                    const target = parseInt(counter.getAttribute('data-count'));
                    const count = parseInt(counter.innerText);
                    const increment = target / speed;
                    
                    if (count < target) {
                        counter.innerText = Math.ceil(count + increment);
                        setTimeout(() => animateCounter(counter), 1);
                    } else {
                        counter.innerText = target;
                    }
                };
                
                const counterObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            animateCounter(entry.target);
                            counterObserver.unobserve(entry.target);
                        }
                    });
                });
                
                counters.forEach(counter => {
                    counterObserver.observe(counter);
                });
            }
            
            // 🎯 Smooth Scrolling
            initSmoothScroll() {
                document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                    anchor.addEventListener('click', function (e) {
                        e.preventDefault();
                        const target = document.querySelector(this.getAttribute('href'));
                        if (target) {
                            target.scrollIntoView({
                                behavior: 'smooth',
                                block: 'start'
                            });
                        }
                    });
                });
            }
            
            // 🎪 Event Handlers
            initEventHandlers() {
                // Loading states for buttons
                document.querySelectorAll('.btn-hero, .btn-event').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        if (this.href && !this.href.includes('#')) {
                            const originalText = this.innerHTML;
                            this.innerHTML = '<span class="loading"></span> Loading...';
                            this.style.pointerEvents = 'none';
                            
                            // Reset after 3 seconds if page doesn't change
                            setTimeout(() => {
                                this.innerHTML = originalText;
                                this.style.pointerEvents = 'auto';
                            }, 3000);
                        }
                    });
                });
                
                // Event card hover effects
                document.querySelectorAll('.event-card').forEach(card => {
                    card.addEventListener('mouseenter', function() {
                        this.style.transform = 'translateY(-10px) scale(1.02)';
                    });
                    
                    card.addEventListener('mouseleave', function() {
                        this.style.transform = 'translateY(0) scale(1)';
                    });
                });
                
                // Feature card animations
                document.querySelectorAll('.feature-card').forEach(card => {
                    card.addEventListener('mouseenter', function() {
                        const icon = this.querySelector('.feature-icon');
                        icon.style.transform = 'scale(1.1) rotate(5deg)';
                    });
                    
                    card.addEventListener('mouseleave', function() {
                        const icon = this.querySelector('.feature-icon');
                        icon.style.transform = 'scale(1) rotate(0deg)';
                    });
                });
                
                // Parallax effect for hero section
                window.addEventListener('scroll', () => {
                    const scrolled = window.pageYOffset;
                    const hero = document.querySelector('.hero');
                    if (hero) {
                        hero.style.transform = `translateY(${scrolled * 0.5}px)`;
                    }
                });
            }
            
            // 🎯 Utility Methods
            showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `notification notification-${type}`;
                notification.innerHTML = `
                    <div class="notification-content">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                        <span>${message}</span>
                    </div>
                    <button class="notification-close" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                
                document.body.appendChild(notification);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 5000);
            }
        }
        
              // 🚀 Initialize Landing Page
        document.addEventListener('DOMContentLoaded', () => {
            new LandingPageController();
        });
        
        // 🎯 Additional Interactive Features
        
        // Dynamic greeting based on time
        function updateGreeting() {
            const hour = new Date().getHours();
            const greetingElement = document.querySelector('.hero h1');
            
            if (greetingElement) {
                let greeting = 'Welcome to';
                
                if (hour < 12) {
                    greeting = 'Good Morning! Welcome to';
                } else if (hour < 17) {
                    greeting = 'Good Afternoon! Welcome to';
                } else {
                    greeting = 'Good Evening! Welcome to';
                }
                
                greetingElement.innerHTML = `${greeting} <span class="text-warning">EMS</span>`;
            }
        }
        
        // Update greeting on page load
        updateGreeting();
        
        // 🎪 Event Registration Preview
        function previewEventRegistration(eventId) {
            // This would show a modal with event details and registration form
            console.log('Previewing registration for event:', eventId);
            
            // You can implement a modal here
            const modal = document.createElement('div');
            modal.className = 'event-preview-modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h5>Event Registration</h5>
                        <button class="modal-close" onclick="this.closest('.event-preview-modal').remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="loading-spinner">
                            <div class="spinner"></div>
                            <p>Loading event details...</p>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Simulate loading
            setTimeout(() => {
                modal.remove();
                window.location.href = `events/view.php?id=${eventId}`;
            }, 1000);
        }
        
        // 🔔 Real-time Updates (WebSocket placeholder)
        function initializeRealTimeUpdates() {
            // This is where you'd implement WebSocket connections
            // for real-time event updates, new event notifications, etc.
            
            // Simulate periodic updates
            setInterval(() => {
                // Check for new events or updates
                updateEventCounts();
            }, 30000); // Every 30 seconds
        }
        
        function updateEventCounts() {
            // This would fetch updated counts via AJAX
            fetch('api/get_stats.php')
                .then(response => response.json())
                .then(data => {
                    // Update stat counters
                    const counters = document.querySelectorAll('.stat-number');
                    counters.forEach(counter => {
                        const statType = counter.closest('.stat-card').querySelector('.stat-icon i').className;
                        
                        if (statType.includes('calendar-alt') && data.total_events) {
                            counter.setAttribute('data-count', data.total_events);
                        } else if (statType.includes('users') && data.total_users) {
                            counter.setAttribute('data-count', data.total_users);
                        } else if (statType.includes('ticket-alt') && data.total_tickets) {
                            counter.setAttribute('data-count', data.total_tickets);
                        } else if (statType.includes('star') && data.active_organizers) {
                            counter.setAttribute('data-count', data.active_organizers);
                        }
                    });
                })
                .catch(error => {
                    console.log('Stats update failed:', error);
                });
        }
        
        // Initialize real-time updates
        initializeRealTimeUpdates();
        
        // 🎨 Dynamic Theme Switching (Optional)
        function initThemeToggle() {
            const themeToggle = document.createElement('button');
            themeToggle.className = 'theme-toggle';
            themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
            themeToggle.setAttribute('aria-label', 'Toggle Dark Mode');
            
            themeToggle.addEventListener('click', () => {
                document.body.classList.toggle('dark-theme');
                const isDark = document.body.classList.contains('dark-theme');
                themeToggle.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
            });
            
            // Add to navbar
            const navbar = document.querySelector('.navbar .container');
            if (navbar) {
                navbar.appendChild(themeToggle);
            }
            
            // Load saved theme
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-theme');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            }
        }
        
        // Uncomment to enable theme toggle
        // initThemeToggle();
        
        // 🎯 Search Functionality (if you want to add a search bar)
        function initQuickSearch() {
            const searchContainer = document.createElement('div');
            searchContainer.className = 'quick-search-container';
            searchContainer.innerHTML = `
                <div class="quick-search">
                    <input type="text" placeholder="Quick search events..." id="quickSearchInput">
                    <button type="button" id="quickSearchBtn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <div class="search-results" id="searchResults"></div>
            `;
            
            // Add to hero section
            const heroButtons = document.querySelector('.hero-buttons');
            if (heroButtons) {
                heroButtons.appendChild(searchContainer);
            }
            
            // Search functionality
            const searchInput = document.getElementById('quickSearchInput');
            const searchBtn = document.getElementById('quickSearchBtn');
            const searchResults = document.getElementById('searchResults');
            
            function performSearch(query) {
                if (query.length < 2) {
                    searchResults.style.display = 'none';
                    return;
                }
                
                // Show loading
                searchResults.innerHTML = '<div class="search-loading">Searching...</div>';
                searchResults.style.display = 'block';
                
                // Simulate search (replace with actual AJAX call)
                setTimeout(() => {
                    // This would be replaced with actual search results
                    searchResults.innerHTML = `
                        <div class="search-result-item">
                            <h6>Sample Event</h6>
                            <p>Event description...</p>
                        </div>
                    `;
                }, 500);
            }
            
            searchInput.addEventListener('input', (e) => {
                performSearch(e.target.value);
            });
            
            searchBtn.addEventListener('click', () => {
                const query = searchInput.value.trim();
                if (query) {
                    window.location.href = `events/browse.php?search=${encodeURIComponent(query)}`;
                }
            });
        }
        
        // Uncomment to enable quick search
        // initQuickSearch();
    </script>
    
    <!-- Additional CSS for notifications and modals -->
    <style>
        /* 🔔 Notification Styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-width: 300px;
            z-index: 9999;
            animation: slideInRight 0.3s ease-out;
            border-left: 5px solid;
        }
        
        .notification-success { border-left-color: #4CAF50; }
        .notification-error { border-left-color: #f44336; }
        .notification-info { border-left-color: #2196F3; }
        
        .notification-content {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .notification-success .notification-content i { color: #4CAF50; }
        .notification-error .notification-content i { color: #f44336; }
        .notification-info .notification-content i { color: #2196F3; }
        
        .notification-close {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            padding: 0.2rem;
            border-radius: 3px;
        }
        
        .notification-close:hover { 
            background: #f5f5f5; 
            color: #666; 
        }
        
        /* 🎪 Modal Styles */
        .event-preview-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            animation: fadeIn 0.3s ease-out;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h5 {
            margin: 0;
            font-weight: 600;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 5px;
        }
        
        .modal-close:hover {
            background: #f5f5f5;
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .loading-spinner {
            text-align: center;
            padding: 2rem;
        }
        
        .loading-spinner .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        
        /* 🎯 Theme Toggle */
        .theme-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .theme-toggle:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        
        /* 🔍 Quick Search */
        .quick-search-container {
            margin-top: 2rem;
            position: relative;
        }
        
        .quick-search {
            display: flex;
            background: rgba(255,255,255,0.1);
            border-radius: 25px;
            padding: 0.5rem;
            backdrop-filter: blur(10px);
        }
        
        .quick-search input {
            flex: 1;
            background: none;
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            font-size: 1rem;
        }
        
        .quick-search input::placeholder {
            color: rgba(255,255,255,0.7);
        }
        
        .quick-search input:focus {
            outline: none;
        }
        
        .quick-search button {
            background: white;
            color: #667eea;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .quick-search button:hover {
            transform: scale(1.1);
        }
        
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-top: 0.5rem;
            max-height: 300px;
            overflow-y: auto;
            display: none;
            z-index: 1000;
        }
        
        .search-result-item {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        
        .search-result-item:hover {
            background: #f8f9fa;
        }
        
        .search-result-item h6 {
            margin: 0 0 0.5rem 0;
            color: var(--text-dark);
        }
        
        .search-result-item p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .search-loading {
            padding: 1rem;
            text-align: center;
            color: var(--text-muted);
        }
        
        /* 🎨 Dark Theme  */
        .dark-theme {
            --light-bg: #1a1a1a;
            --white: #2d2d2d;
            --text-dark: #ffffff;
            --text-muted: #cccccc;
            --border-color: #404040;
        }
        
        .dark-theme .navbar {
            background: rgba(45, 45, 45, 0.95) !important;
        }
        
        .dark-theme .event-card,
        .dark-theme .stat-card,
        .dark-theme .feature-card {
            background: var(--white);
            color: var(--text-dark);
        }
        
        /* 📱 Mobile Responsive Enhancements */
        @media (max-width: 576px) {
            .hero h1 {
                font-size: 2rem;
            }
            
            .section-title h2 {
                font-size: 1.8rem;
            }
            
                        .event-card {
                margin-bottom: 1rem;
            }
            
            .event-list-item {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .event-date-badge {
                align-self: center;
            }
            
            .event-list-actions {
                justify-content: center;
                width: 100%;
            }
            
            .hero-buttons {
                flex-direction: column;
                gap: 1rem;
            }
            
            .btn-hero {
                width: 100%;
                text-align: center;
            }
            
            .stats-section .row {
                gap: 1rem;
            }
            
            .stat-card {
                margin-bottom: 1rem;
            }
            
            .feature-card {
                margin-bottom: 1.5rem;
            }
            
            .notification {
                left: 10px;
                right: 10px;
                min-width: auto;
            }
            
            .theme-toggle {
                bottom: 10px;
                right: 10px;
                width: 45px;
                height: 45px;
            }
            
            .quick-search-container {
                margin-top: 1rem;
            }
            
            .cta-title {
                font-size: 1.8rem;
            }
            
            .cta-description {
                font-size: 1rem;
            }
        }
        
        /* 🎯 Performance Optimizations */
        .event-card img,
        .hero-image img {
            transition: transform 0.3s ease;
            will-change: transform;
        }
        
        .event-card:hover img {
            transform: scale(1.05);
        }
        
        /* 🎪 Accessibility Improvements */
        .btn-hero:focus,
        .btn-event:focus,
        .nav-link:focus {
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }
        
        .event-card:focus-within {
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }
        
        /* 🎨 Print Styles */
        @media print {
            .navbar,
            .hero,
            .cta-section,
            .footer,
            .theme-toggle,
            .notification {
                display: none !important;
            }
            
            .event-card,
            .feature-card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            body {
                font-size: 12pt;
                line-height: 1.4;
            }
        }
        
        /* 🚀 Advanced Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .hero-image {
            animation: float 6s ease-in-out infinite;
        }
        
        .stat-icon {
            animation: pulse 2s ease-in-out infinite;
        }
        
        /* 🎯 Hover Effects Enhancement */
        .event-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        
        .event-card:hover::before {
            opacity: 1;
        }
        
        /* 🎪 Loading Skeleton (for dynamic content) */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        .skeleton-text {
            height: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 4px;
        }
        
        .skeleton-title {
            height: 1.5rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        
        .skeleton-image {
            height: 200px;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        /* 🔥 Advanced Gradient Effects */
        .gradient-text {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .gradient-border {
            position: relative;
            background: white;
            border-radius: 15px;
        }
        
        .gradient-border::before {
            content: '';
            position: absolute;
            inset: 0;
            padding: 2px;
            background: var(--primary-gradient);
            border-radius: inherit;
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask-composite: exclude;
        }
        
        /* 🎯 Scroll Progress Indicator */
        .scroll-progress {
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 4px;
            background: var(--primary-gradient);
            z-index: 9999;
            transition: width 0.1s ease;
        }
        
        /* 🎨 Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-gradient);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #5a6fd8;
        }
        
        /* 🎪 Tooltip Styles */
        .tooltip {
            position: relative;
            cursor: pointer;
        }
        
        .tooltip::before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.8rem;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            z-index: 1000;
        }
        
        .tooltip:hover::before {
            opacity: 1;
        }
        
        /* 🚀 Performance Hints */
        .event-image,
        .hero-image img,
        .feature-icon {
            transform: translateZ(0);
            backface-visibility: hidden;
        }
    </style>
    
    <!-- 📊 Analytics and Performance Monitoring -->
    <script>
        // 🎯 Performance Monitoring
        window.addEventListener('load', () => {
            // Log page load time
            const loadTime = performance.now();
            console.log(`Page loaded in ${loadTime.toFixed(2)}ms`);
            
            // Monitor Core Web Vitals
            if ('web-vital' in window) {
                // This would integrate with actual web vitals library
                console.log('Monitoring Core Web Vitals...');
            }
        });
        
        // 📊 User Interaction Tracking
        function trackUserInteraction(action, element) {
            // This would send data to your analytics service
            console.log('User interaction:', { action, element, timestamp: new Date() });
            
            // Example: Track button clicks
            if (action === 'click' && element.classList.contains('btn-event')) {
                console.log('Event button clicked:', element.href);
            }
        }
        
        // Add event listeners for tracking
        document.addEventListener('click', (e) => {
            if (e.target.matches('.btn-hero, .btn-event, .nav-link')) {
                trackUserInteraction('click', e.target);
            }
        });
        
        // 🎯 Scroll Progress Indicator
        function initScrollProgress() {
            const progressBar = document.createElement('div');
            progressBar.className = 'scroll-progress';
            document.body.appendChild(progressBar);
            
            window.addEventListener('scroll', () => {
                const scrollTop = window.pageYOffset;
                const docHeight = document.body.scrollHeight - window.innerHeight;
                const scrollPercent = (scrollTop / docHeight) * 100;
                progressBar.style.width = scrollPercent + '%';
            });
        }
        
        // Initialize scroll progress
        initScrollProgress();
        
        // 🎪 Lazy Loading for Images
        function initLazyLoading() {
            const images = document.querySelectorAll('img[data-src]');
            
            const imageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });
            
            images.forEach(img => imageObserver.observe(img));
        }
        
        // Initialize lazy loading
        initLazyLoading();
        
        // 🎨 Dynamic Content Loading
        function loadMoreEvents() {
            const loadMoreBtn = document.getElementById('loadMoreEvents');
            if (loadMoreBtn) {
                loadMoreBtn.addEventListener('click', function() {
                    this.innerHTML = '<span class="loading"></span> Loading...';
                    this.disabled = true;
                    
                    // Simulate loading more events
                    setTimeout(() => {
                        // This would be replaced with actual AJAX call
                        console.log('Loading more events...');
                        this.innerHTML = '<i class="fas fa-plus"></i> Load More Events';
                        this.disabled = false;
                    }, 2000);
                });
            }
        }
        
        // Initialize load more functionality
        loadMoreEvents();
        
        // 🎯 Service Worker Registration (for PWA)
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(registration => {
                        console.log('SW registered: ', registration);
                    })
                    .catch(registrationError => {
                        console.log('SW registration failed: ', registrationError);
                    });
            });
        }
        
        // 🔔 Push Notification Setup
        function initPushNotifications() {
            if ('Notification' in window && 'serviceWorker' in navigator) {
                // Request permission for notifications
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        console.log('Notification permission granted');
                        // You can now send push notifications
                    }
                });
            }
        }
        
        // Initialize push notifications
        initPushNotifications();
        
        // 🎪 Error Handling and Reporting
        window.addEventListener('error', (e) => {
            console.error('JavaScript error:', e.error);
            // This would send error reports to your logging service
        });
        
        window.addEventListener('unhandledrejection', (e) => {
            console.error('Unhandled promise rejection:', e.reason);
            // This would send error reports to your logging service
        });
        
        // 🎯 Accessibility Enhancements
        function initAccessibility() {
            // Add skip link
            const skipLink = document.createElement('a');
            skipLink.href = '#main-content';
            skipLink.textContent = 'Skip to main content';
            skipLink.className = 'skip-link';
            document.body.insertBefore(skipLink, document.body.firstChild);
            
            // Keyboard navigation for cards
            document.querySelectorAll('.event-card').forEach(card => {
                card.setAttribute('tabindex', '0');
                card.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        const link = card.querySelector('.btn-event');
                        if (link) link.click();
                    }
                });
            });
            
            // Focus management for modals
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const modal = document.querySelector('.event-preview-modal');
                    if (modal) modal.remove();
                }
            });
        }
        
        // Initialize accessibility features
        initAccessibility();
        
        // 🎨 Final Initialization
        console.log('🎉 EMS Landing Page Initialized Successfully!');
        console.log('🚀 Ready for amazing campus events!');
    </script>
    
    <!-- 🎯 Additional CSS for Skip Link and Accessibility -->
    <style>
        .skip-link {
            position: absolute;
            top: -40px;
            left: 6px;
            background: #000;
            color: #fff;
            padding: 8px;
            text-decoration: none;
            border-radius: 4px;
            z-index: 10000;
            transition: top 0.3s ease;
        }
        
        .skip-link:focus {
            top: 6px;
        }
        
        /* 🎪 Focus Styles for Better Accessibility */
        .event-card:focus {
            outline: 3px solid #667eea;
            outline-offset: 2px;
        }
        
        .btn-hero:focus,
        .btn-event:focus {
            outline: 3px solid #667eea;
            outline-offset: 2px;
        }
        
        /* 🎯 High Contrast Mode Support */
        @media (prefers-contrast: high) {
            .event-card,
            .stat-card,
            .feature-card {
                border: 2px solid #000;
            }
            
            .btn-hero,
            .btn-event {
                border: 2px solid #000;
            }
        }
        
        /* 🎨 Reduced Motion Support */
              @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
            
            .hero-image,
            .stat-icon {
                animation: none !important;
            }
        }
        
        /* 🎯 Loading States */
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* 🎪 Error States */
        .error-message {
            background: #fee;
            border: 1px solid #fcc;
            color: #c66;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        
        .success-message {
            background: #efe;
            border: 1px solid #cfc;
            color: #6c6;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        
        /* 🎨 Final Responsive Touches */
        @media (max-width: 480px) {
            .hero {
                padding: 3rem 0;
            }
            
            .hero h1 {
                font-size: 1.8rem;
                line-height: 1.2;
            }
            
            .hero .lead {
                font-size: 1rem;
            }
            
            .section-title h2 {
                font-size: 1.6rem;
            }
            
            .event-meta {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .event-actions {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .btn-event {
                width: 100%;
                text-align: center;
            }
            
            .stats-section {
                padding: 2rem 0;
            }
            
            .stat-card {
                padding: 1.5rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .feature-card {
                padding: 1.5rem;
            }
            
            .cta-section {
                padding: 3rem 0;
            }
            
            .footer {
                padding: 2rem 0;
            }
            
            .footer-section h5 {
                font-size: 1.1rem;
            }
        }
        
        /* 🎯 Ultra-wide Screen Support */
        @media (min-width: 1400px) {
            .container {
                max-width: 1320px;
            }
            
            .hero h1 {
                font-size: 4rem;
            }
            
            .hero .lead {
                font-size: 1.4rem;
            }
            
            .section-title h2 {
                font-size: 3rem;
            }
            
            .event-card {
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }
            
            .event-card:hover {
                transform: translateY(-15px) scale(1.02);
            }
        }
        
        /* 🎪 Print Optimization */
        @media print {
            * {
                color: #000 !important;
                background: transparent !important;
                box-shadow: none !important;
            }
            
            .container {
                max-width: none !important;
                width: 100% !important;
            }
            
            .event-card,
            .feature-card,
            .stat-card {
                border: 1px solid #000 !important;
                margin-bottom: 1rem !important;
                page-break-inside: avoid;
            }
            
            .section-title {
                page-break-after: avoid;
            }
            
            h1, h2, h3, h4, h5, h6 {
                page-break-after: avoid;
            }
        }
    </style>
</body>
</html>


