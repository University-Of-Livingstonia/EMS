<?php

/**
 * 📖 About Page - EMS
 * Ekwendeni Mighty Campus Event Management System
 * Learn About Our Amazing Platform! 🎓
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

// Get some stats for the about page
$aboutStats = [];
try {
    $stmt = $conn->query("SELECT COUNT(*) as total_events FROM events WHERE status = 'approved'");
    $aboutStats['total_events'] = $stmt->fetch_assoc()['total_events'];

    $stmt = $conn->query("SELECT COUNT(*) as total_users FROM users WHERE role != 'admin'");
    $aboutStats['total_users'] = $stmt->fetch_assoc()['total_users'];

    $stmt = $conn->query("SELECT COUNT(*) as total_tickets FROM tickets");
    $aboutStats['total_tickets'] = $stmt->fetch_assoc()['total_tickets'];

    $stmt = $conn->query("SELECT COUNT(*) as active_organizers FROM users WHERE role = 'organizer'");
    $aboutStats['active_organizers'] = $stmt->fetch_assoc()['active_organizers'];
} catch (Exception $e) {
    $aboutStats = ['total_events' => 0, 'total_users' => 0, 'total_tickets' => 0, 'active_organizers' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - EMS | Ekwendeni Mighty Campus</title>

    <!-- Meta Tags -->
    <meta name="description" content="Learn about EMS - Ekwendeni Mighty Campus Event Management System. Discover our mission, vision, and the team behind the platform.">
    <meta name="keywords" content="about, EMS, Ekwendeni, campus, event management, university, team">
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

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
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

        .navbar-nav .nav-link:hover::after,
        .navbar-nav .nav-link.active::after {
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

        /* 🎓 About Hero Section */
        .about-hero {
            background: var(--primary-gradient);
            padding: 8rem 0 4rem 0;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .about-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="60" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="60" cy="40" r="1" fill="rgba(255,255,255,0.1)"/></svg>');
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px) rotate(0deg);
            }

            50% {
                transform: translateY(-20px) rotate(180deg);
            }
        }

        .about-hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .about-hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .about-hero .lead {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* 📊 Stats Section */
        .stats-section {
            background: var(--light-bg);
            padding: 5rem 0;
            margin-top: -2rem;
            position: relative;
            z-index: 3;
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

        /* 🎯 Mission & Vision Section */
        .mission-vision {
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

        .mission-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .mission-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
        }

        .mission-card.mission::before {
            background: var(--success-gradient);
        }

        .mission-card.vision::before {
            background: var(--warning-gradient);
        }

        .mission-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-hover);
        }

        .mission-icon {
            font-size: 4rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .mission-icon.mission {
            background: var(--success-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .mission-icon.vision {
            background: var(--warning-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .mission-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .mission-text {
            font-size: 1.1rem;
            line-height: 1.8;
            color: var(--text-muted);
            text-align: center;
        }

        /* 👥 Team Section */
        .team-section {
            padding: 5rem 0;
            background: var(--light-bg);
        }

        .team-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .team-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-hover);
        }

        .team-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 1.5rem auto;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 800;
            color: white;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .team-name {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .team-role {
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9rem;
        }

        .team-bio {
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .team-social {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .social-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--light-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .social-link:hover {
            background: var(--primary-gradient);
            color: white;
            transform: translateY(-3px);
        }

        /* 🎯 Features Section */
        .features-section {
            padding: 5rem 0;
            background: white;
        }

        .feature-card {
            text-align: center;
            padding: 2rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            margin: 0 auto 1.5rem auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .feature-icon.primary {
            background: var(--primary-gradient);
        }

        .feature-icon.success {
            background: var(--success-gradient);
        }

        .feature-icon.warning {
            background: var(--warning-gradient);
        }

        .feature-icon.danger {
            background: var(--danger-gradient);
        }

        .feature-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-dark);
        }

        .feature-text {
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
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="2" fill="rgba(255,255,255,0.1)"/></svg>');
            animation: float 15s ease-in-out infinite;
        }

        .cta-content {
            position: relative;
            z-index: 2;
        }

        .cta-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
        }

        .cta-text {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .btn-cta {
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px;
            margin: 0.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary-cta {
            background: white;
            color: #667eea;
            border: none;
        }

        .btn-primary-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(255, 255, 255, 0.3);
            color: #667eea;
        }

        .btn-outline-cta {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn-outline-cta:hover {
            background: white;
            color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(255, 255, 255, 0.3);
        }

        /* 📱 Responsive Design */
        @media (max-width: 768px) {
            .about-hero h1 {
                font-size: 2.5rem;
            }

            .about-hero .lead {
                font-size: 1.1rem;
            }

            .section-title h2 {
                font-size: 2rem;
            }

            .mission-card {
                padding: 2rem;
            }

            .cta-title {
                font-size: 2rem;
            }

            .cta-text {
                font-size: 1rem;
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
    </style>
</head>

<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">🎪 EMS</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="events.php">Events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item">
                            <a class="btn-nav btn-login" href="dashboard/">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn-nav btn-register" href="auth/logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="btn-nav btn-login" href="auth/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn-nav btn-register" href="auth/register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- About Hero Section -->
    <section class="about-hero">
        <div class="container">
            <div class="about-hero-content fade-in-up">
                <h1>About EMS</h1>
                <p class="lead">Empowering Ekwendeni Mighty Campus with seamless event management, connecting students, faculty, and the community through unforgettable experiences.</p>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-card fade-in-up">
                        <i class="fas fa-calendar-alt stat-icon"></i>
                        <div class="stat-number"><?= $aboutStats['total_events'] ?>+</div>
                        <div class="stat-label">Events Hosted</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card fade-in-up">
                        <i class="fas fa-users stat-icon"></i>
                        <div class="stat-number"><?= $aboutStats['total_users'] ?>+</div>
                        <div class="stat-label">Active Users</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card fade-in-up">
                        <i class="fas fa-ticket-alt stat-icon"></i>
                        <div class="stat-number"><?= $aboutStats['total_tickets'] ?>+</div>
                        <div class="stat-label">Tickets Sold</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card fade-in-up">
                        <i class="fas fa-user-tie stat-icon"></i>
                        <div class="stat-number"><?= $aboutStats['active_organizers'] ?>+</div>
                        <div class="stat-label">Event Organizers</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Mission & Vision Section -->
    <section class="mission-vision">
        <div class="container">
            <div class="section-title fade-in-up">
                <h2>Our Mission & Vision</h2>
                <p>Driving excellence in campus event management through innovation and community engagement</p>
            </div>

            <div class="row">
                <div class="col-lg-6">
                    <div class="mission-card mission slide-in-left">
                        <div class="mission-icon mission">
                            <i class="fas fa-bullseye"></i>
                        </div>
                        <h3 class="mission-title">Our Mission</h3>
                        <p class="mission-text">
                            To provide a comprehensive, user-friendly platform that streamlines event management
                            at Ekwendeni Mighty Campus, fostering community engagement, academic excellence,
                            and memorable experiences for all students, faculty, and staff.
                        </p>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="mission-card vision slide-in-right">
                        <div class="mission-icon vision">
                            <i class="fas fa-eye"></i>
                        </div>
                        <h3 class="mission-title">Our Vision</h3>
                        <p class="mission-text">
                            To become the leading campus event management system in Malawi, setting the standard
                            for digital innovation in higher education while building stronger campus communities
                            through seamless event experiences.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <div class="section-title fade-in-up">
                <h2>Why Choose EMS?</h2>
                <p>Discover the powerful features that make event management effortless</p>
            </div>

            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card fade-in-up">
                        <div class="feature-icon primary">
                            <i class="fas fa-rocket"></i>
                        </div>
                        <h4 class="feature-title">Easy Event Creation</h4>
                        <p class="feature-text">Create and manage events with our intuitive interface. From academic conferences to social gatherings, set up events in minutes.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card fade-in-up">
                        <div class="feature-icon success">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4 class="feature-title">Seamless Registration</h4>
                        <p class="feature-text">Streamlined registration process for attendees with instant confirmations and digital tickets.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card fade-in-up">
                        <div class="feature-icon warning">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4 class="feature-title">Real-time Analytics</h4>
                        <p class="feature-text">Track event performance with comprehensive analytics and insights to improve future events.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card fade-in-up">
                        <div class="feature-icon danger">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h4 class="feature-title">Mobile Friendly</h4>
                        <p class="feature-text">Access EMS anywhere, anytime with our responsive design that works perfectly on all devices.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="team-section">
        <div class="container">
            <div class="section-title fade-in-up">
                <h2>Meet Our Team</h2
                    <p>The passionate individuals behind EMS who make it all possible</p>
            </div>

            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="team-card fade-in-up">
                        <div class="team-avatar">
                            IS
                        </div>
                        <h4 class="team-name">Isaac Precept</h4>
                        <p class="team-role">Lead Developer</p>
                        <p class="team-bio">
                            Passionate full-stack developer with expertise in PHP, JavaScript, and modern web technologies.
                            Leading the technical development of EMS with a focus on user experience and performance.
                        </p>
                        <div class="team-social">
                            <a href="#" class="social-link">
                                <i class="fab fa-linkedin"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fab fa-github"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fab fa-twitter"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="team-card fade-in-up">
                        <div class="team-avatar">
                            SN
                        </div>
                        <h4 class="team-name">Sam Nyirenda</h4>
                        <p class="team-role">UI/UX Designer</p>
                        <p class="team-bio">
                            Creative designer focused on creating intuitive and beautiful user interfaces.
                            Ensuring EMS provides the best possible user experience for all campus community members.
                        </p>
                        <div class="team-social">
                            <a href="#" class="social-link">
                                <i class="fab fa-behance"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fab fa-dribbble"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fab fa-instagram"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="team-card fade-in-up">
                        <div class="team-avatar">
                            PM
                        </div>
                        <h4 class="team-name">Kenneth Msosa</h4>
                        <p class="team-role">Project Manager</p>
                        <p class="team-bio">
                            Experienced project manager coordinating development efforts and ensuring EMS meets
                            the evolving needs of Ekwendeni Mighty Campus community.
                        </p>
                        <div class="team-social">
                            <a href="#" class="social-link">
                                <i class="fab fa-linkedin"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fas fa-envelope"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content fade-in-up">
                <h2 class="cta-title">Ready to Get Started?</h2>
                <p class="cta-text">Join thousands of students and faculty already using EMS to create amazing events</p>
                <div>
                    <?php if ($isLoggedIn): ?>
                        <a href="dashboard/" class="btn-cta btn-primary-cta">Go to Dashboard</a>
                        <a href="events.php" class="btn-cta btn-outline-cta">Browse Events</a>
                    <?php else: ?>
                        <a href="auth/register.php" class="btn-cta btn-primary-cta">Join EMS Today</a>
                        <a href="contact.php" class="btn-cta btn-outline-cta">Contact Us</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5 class="mb-3">🎪 EMS</h5>
                    <p class="text-muted">
                        Ekwendeni Mighty Campus Event Management System -
                        Connecting our community through amazing events and experiences.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-white">
                            <i class="fab fa-facebook fa-lg"></i>
                        </a>
                        <a href="#" class="text-white">
                            <i class="fab fa-twitter fa-lg"></i>
                        </a>
                        <a href="#" class="text-white">
                            <i class="fab fa-instagram fa-lg"></i>
                        </a>
                        <a href="#" class="text-white">
                            <i class="fab fa-linkedin fa-lg"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-muted">Home</a></li>
                        <li><a href="events.php" class="text-muted">Events</a></li>
                        <li><a href="about.php" class="text-muted">About</a></li>
                        <li><a href="contact.php" class="text-muted">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <h6 class="mb-3">For Users</h6>
                    <ul class="list-unstyled">
                        <li><a href="auth/register.php" class="text-muted">Register</a></li>
                        <li><a href="auth/login.php" class="text-muted">Login</a></li>
                        <li><a href="dashboard/" class="text-muted">Dashboard</a></li>
                        <li><a href="#" class="text-muted">Help Center</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <h6 class="mb-3">Contact Info</h6>
                    <ul class="list-unstyled text-muted">
                        <li><i class="fas fa-map-marker-alt me-2"></i>Ekwendeni, Mzimba, Malawi</li>
                        <li><i class="fas fa-phone me-2"></i>---</li>
                        <li><i class="fas fa-envelope me-2"></i>info@unilia.ac.mw</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-muted">&copy; 2024 EMS - Ekwendeni Mighty Campus. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-muted me-3">Privacy Policy</a>
                    <a href="#" class="text-muted">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
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

        // Animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
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

        console.log('📖 About Page Loaded Successfully!');
    </script>
</body>

</html>