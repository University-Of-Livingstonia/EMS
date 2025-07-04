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
        }</style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">EMS</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn-nav btn-login" href="./auth/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn-nav btn-register" href="./auth/register.php">Register</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Welcome to EMS</h1>
                <p class="lead">Discover, register, and manage campus events at Ekwendeni Mighty Campus. Your gateway to amazing experiences!</p>
                <div class="hero-buttons">
                    <a href="#" class="btn-hero btn-primary-hero">Get Started</a>
                    <a href="#" class="btn-hero btn-outline-hero">Learn More</a>
                </div>
            </div>
            <div class="hero-image">
                <img src="assets/images/hero.jpg" alt="Hero Image">
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-calendar-alt stat-icon"></i>
                        <div class="stat-number">120</div>
                        <div class="stat-label">Events</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-users stat-icon"></i>
                        <div class="stat-number">500</div>
                        <div class="stat-label">Users</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-ticket-alt stat-icon"></i>
                        <div class="stat-number">2000</div>
                        <div class="stat-label">Tickets</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-user-tie stat-icon"></i>
                        <div class="stat-number">20</div>
                        <div class="stat-label">Organizers</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Events Section -->
    <section class="featured-events">
        <div class="container">
            <div class="section-title">
                <h2>Featured Events</h2>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <img src="assets/images/event1.jpg" class="card-img-top" alt="Event 1">
                        <div class="card-body">
                            <h5 class="card-title">Event 1</h5>
                            <p class="card-text">Description of Event 1</p>
                            <a href="#" class="btn btn-primary">Register</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <img src="assets/images/event2.jpg" class="card-img-top" alt="Event 2">
                        <div class="card-body">
                            <h5 class="card-title">Event 2</h5>
                            <p class="card-text">Description of Event 2</p>
                            <a href="#" class="btn btn-primary">Register</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <img src="assets/images/event3.jpg" class="card-img-top" alt="Event 3">
                        <div class="card-body">
                            <h5 class="card-title">Event 3</h5>
                            <p class="card-text">Description of Event 3</p>
                            <a href="#" class="btn btn-primary">Register</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Upcoming Events Section -->
    <section class="featured-events">
        <div class="container">
            <div class="section-title">
                <h2>Upcoming Events</h2>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <img src="assets/images/event4.jpg" class="card-img-top" alt="Event 4">
                        <div class="card-body">
                            <h5 class="card-title">Event 4</h5>
                            <p class="card-text">Description of Event 4</p>
                            <a href="#" class="btn btn-primary">Register</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <img src="assets/images/event5.jpg" class="card-img-top" alt="Event 5">
                        <div class="card-body">
                            <h5 class="card-title">Event 5</h5>
                            <p class="card-text">Description of Event 5</p>
                            <a href="#" class="btn btn-primary">Register</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <img src="assets/images/event6.jpg" class="card-img-top" alt="Event 6">
                        <div class="card-body">
                            <h5 class="card-title">Event 6</h5>
                            <p class="card-text">Description of Event 6</p>
                            <a href="#" class="btn btn-primary">Register</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <img src="assets/images/event7.jpg" class="card-img-top" alt="Event 7">
                        <div class="card-body">
                            <h5 class="card-title">Event 7</h5>
                            <p class="card-text">Description of Event 7</p>
                            <a href="#" class="btn btn-primary">Register</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <img src="assets/images/event8.jpg" class="card-img-top" alt="Event 8">
                        <div class="card-body">
                            <h5 class="card-title">Event 8</h5>
                            <p class="card-text">Description of Event 8</p>
                            <a href="#" class="btn btn-primary">Register</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; 2023 EMS - Ekwendeni Mighty Campus Event Management System</p>
                </div>
                <div class="col-md-6 text-end">
                    <a href="#" class="text-white">Privacy Policy</a> | <a href="#" class="text-white">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>