<?php
/**
 * 🎪 Events Page - EMS
 * Ekwendeni Mighty Campus Event Management System
 * Discover Amazing Events! 🎉
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

// Get filter parameters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$date_filter = $_GET['date'] ?? '';
$sort = $_GET['sort'] ?? 'date_asc';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$whereConditions = ["e.status = 'approved'", "e.is_public = 1", "e.start_datetime > NOW()"];
$params = [];
$types = '';

if (!empty($category)) {
    $whereConditions[] = "e.category = ?";
    $params[] = $category;
    $types .= 's';
}

if (!empty($search)) {
    $whereConditions[] = "(e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

if (!empty($date_filter)) {
    switch ($date_filter) {
        case 'today':
            $whereConditions[] = "DATE(e.start_datetime) = CURDATE()";
            break;
        case 'tomorrow':
            $whereConditions[] = "DATE(e.start_datetime) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'this_week':
            $whereConditions[] = "WEEK(e.start_datetime) = WEEK(CURDATE()) AND YEAR(e.start_datetime) = YEAR(CURDATE())";
            break;
        case 'this_month':
            $whereConditions[] = "MONTH(e.start_datetime) = MONTH(CURDATE()) AND YEAR(e.start_datetime) = YEAR(CURDATE())";
            break;
    }
}

$whereClause = implode(' AND ', $whereConditions);

// Build ORDER BY clause
$orderBy = "e.start_datetime ASC";
switch ($sort) {
    case 'date_desc':
        $orderBy = "e.start_datetime DESC";
        break;
    case 'title_asc':
        $orderBy = "e.title ASC";
        break;
    case 'title_desc':
        $orderBy = "e.title DESC";
        break;
    case 'popular':
        $orderBy = "registered_count DESC, e.start_datetime ASC";
        break;
}

// Get events
$events = [];
$totalEvents = 0;

try {
    // Count total events
    $countQuery = "
        SELECT COUNT(*) as total
        FROM events e 
        WHERE $whereClause
    ";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($countQuery);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $totalEvents = $stmt->get_result()->fetch_assoc()['total'];
    } else {
        $totalEvents = $conn->query($countQuery)->fetch_assoc()['total'];
    }
    
    // Get events with pagination
    $eventsQuery = "
        SELECT e.*, u.first_name, u.last_name,
               (SELECT COUNT(*) FROM tickets WHERE event_id = e.event_id) as registered_count,
               (SELECT COUNT(*) FROM tickets WHERE event_id = e.event_id AND payment_status = 'completed') as paid_count
        FROM events e 
        LEFT JOIN users u ON e.organizer_id = u.user_id 
        WHERE $whereClause
        ORDER BY $orderBy
        LIMIT ? OFFSET ?
    ";
    
    $allParams = array_merge($params, [$limit, $offset]);
    $allTypes = $types . 'ii';
    
    $stmt = $conn->prepare($eventsQuery);
    if (!empty($allParams)) {
        $stmt->bind_param($allTypes, ...$allParams);
    }
    $stmt->execute();
    $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    error_log("Events query error: " . $e->getMessage());
}

// Get event categories for filter
$categories = [];
try {
    $stmt = $conn->query("
        SELECT DISTINCT category, COUNT(*) as count 
        FROM events 
        WHERE status = 'approved' AND is_public = 1 AND start_datetime > NOW()
        GROUP BY category 
        ORDER BY category
    ");
    $categories = $stmt->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Categories query error: " . $e->getMessage());
}

// Calculate pagination
$totalPages = ceil($totalEvents / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - EMS | Ekwendeni Mighty Campus</title>
    
    <!-- Meta Tags -->
    <meta name="description" content="Discover and register for amazing events at Ekwendeni Mighty Campus. Academic conferences, workshops, cultural events, and more!">
    <meta name="keywords" content="events, campus, university, Ekwendeni, conferences, workshops, cultural">
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
            --info-gradient: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
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
            background: var(--light-bg);
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
        
        /* 🎪 Events Hero Section */
        .events-hero {
            background: var(--primary-gradient);
            padding: 8rem 0 4rem 0;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .events-hero::before {
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
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .events-hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }
        
        .events-hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }
        
        .events-hero .lead {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* 🔍 Search and Filter Section */
        .search-filter-section {
            background: white;
            padding: 2rem 0;
            margin-top: -2rem;
            position: relative;
            z-index: 3;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .search-filter-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .search-bar {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .search-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid var(--border-color);
            border-radius: 25px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.2rem;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .filter-select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: 15px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .filter-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 1.5rem;
        }
        
        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .clear-filters {
            background: transparent;
            color: var(--text-muted);
            border: 2px solid var(--border-color);
            padding: 0.75rem 1.5rem;
            border-radius: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 1.5rem;
            margin-left: 0.5rem;
        }
        
        .clear-filters:hover {
            border-color: var(--text-muted);
            color: var(--text-dark);
        }
        
        /* 📊 Results Header */
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
        }
        
        .results-count {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .results-count .count {
            color: #667eea;
            font-weight: 700;
        }
        
        .sort-dropdown {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .sort-label {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .sort-select {
            padding: 0.5rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.9rem;
        }
        
        /* 🎪 Event Cards */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .event-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .event-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-hover);
        }
        
        .event-image {
            position: relative;
            height: 200px;
            overflow: hidden;
        }
        
        .event-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .event-card:hover .event-image img {
            transform: scale(1.05);
        }
        
        .event-category-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            color: white;
            z-index: 2;
        }
        
        .category-academic { background: var(--info-gradient); }
        .category-cultural { background: var(--secondary-gradient); }
        .category-sports { background: var(--success-gradient); }
        .category-workshop { background: var(--warning-gradient); }
        .category-conference { background: var(--primary-gradient); }
        .category-social { background: var(--danger-gradient); }
        
        .event-date-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.5rem;
            border-radius: 10px;
            text-align: center;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 2;
        }
        
        .event-content {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .event-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }
        
        .event-title a {
            color: inherit;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .event-title a:hover {
            color: #667eea;
        }
        
        .event-description {
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .event-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        .event-meta-item {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .event-meta-item i {
            color: #667eea;
            width: 14px;
        }
        
        .event-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        
        .event-attendance {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        .attendance-bar {
            width: 60px;
            height: 4px;
            background: var(--border-color);
            border-radius: 2px;
            overflow: hidden;
        }
        
        .attendance-fill {
            height: 100%;
            background: var(--success-gradient);
            transition: width 0.3s ease;
        }
        
        .event-price {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-dark);
        }
        
        .event-price.free {
            color: #4CAF50;
        }
        
        .event-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn-event {
            flex: 1;
            padding: 0.7rem;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary-event {
            background: var(--primary-gradient);
            color: white;
        }
        
        .btn-primary-event:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            color: white;
        }
        
        .btn-outline-event {
            background: transparent;
            color: #667eea;
            border: 2px solid #667eea;
        }
        
        .btn-outline-event:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        
        /* 📄 Pagination */
        .pagination-section {
            display: flex;
            justify-content: center;
            margin-top: 3rem;
        }
        
        .pagination {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .pagination-item {
            padding: 0.7rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .pagination-item:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-2px);
        }
        
        .pagination-item.active {
            background: var(--primary-gradient);
            border-color: transparent;
            color: white;
        }
        
        .pagination-item.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        /* 🚫 No Results */
        .no-results {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
        }
        
        .no-results-icon {
            font-size: 4rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        
        .no-results h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }
        
        .no-results p {
            color: var(--text-muted);
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }
        
        .no-results-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        /* 📱 Responsive Design */
        @media (max-width: 768px) {
            .events-hero h1 {
                font-size: 2.5rem;
            }
            
            .events-hero .lead {
                font-size: 1.1rem;
            }
            
            .search-filter-card {
                padding: 1.5rem;
            }
            
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                min-width: auto;
            }
            
            .results-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .events-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .event-meta {
                grid-template-columns: 1fr;
            }
            
            .pagination {
                flex-wrap: wrap;
                justify-content: center;
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
        
        /* 🎯 Loading States */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--border-color);
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                        <a class="nav-link active" href="events.php">Events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
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

    <!-- Events Hero Section -->
    <section class="events-hero">
        <div class="container">
            <div class="events-hero-content fade-in-up">
                <h1>Discover Events</h1>
                <p class="lead">Explore amazing events happening at Ekwendeni Mighty Campus. From academic conferences to cultural celebrations!</p>
            </div>
        </div>
    </section>

    <!-- Search and Filter Section -->
    <section class="search-filter-section">
        <div class="container">
            <div class="search-filter-card fade-in-up">
                <form method="GET" action="events.php" id="filterForm">
                    <!-- Search Bar -->
                    <div class="search-bar">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" 
                               class="search-input" 
                               name="search" 
                               placeholder="Search events by title, description, or location..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    
                    <!-- Filters -->
                    <div class="filter-row">
                        <div class="filter-group">
                            <label class="filter-label">Category</label>
                            <select name="category" class="filter-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat['category']) ?>" 
                                            <?= $category === $cat['category'] ? 'selected' : '' ?>>
                                        <?= ucfirst(htmlspecialchars($cat['category'])) ?> (<?= $cat['count'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Date</label>
                            <select name="date" class="filter-select">
                                <option value="">Any Time</option>
                                <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Today</option>
                                <option value="tomorrow" <?= $date_filter === 'tomorrow' ? 'selected' : '' ?>>Tomorrow</option>
                                <option value="this_week" <?= $date_filter === 'this_week' ? 'selected' : '' ?>>This Week</option>
                                <option value="this_month" <?= $date_filter === 'this_month' ? 'selected' : '' ?>>This Month</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Sort By</label>
                            <select name="sort" class="filter-select">
                                <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>Date (Earliest First)</option>
                                <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Date (Latest First)</option>
                                <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Title (A-Z)</option>
                                <option value="title_desc" <?= $sort === 'title_desc' ? 'selected' : '' ?>>Title (Z-A)</option>
                                <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Popular</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="filter-btn">
                            <i class="fas fa-filter me-2"></i>Apply Filters
                        </button>
                        <a href="events.php" class="clear-filters">
                            <i class="fas fa-times me-2"></i>Clear All
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Events Section -->
    <section class="py-5">
        <div class="container">
            <!-- Results Header -->
            <div class="results-header slide-in-left">
                <div class="results-count">
                    Found <span class="count"><?= number_format($totalEvents) ?></span> 
                    event<?= $totalEvents !== 1 ? 's' : '' ?>
                    <?php if (!empty($search)): ?>
                        for "<strong><?= htmlspecialchars($search) ?></strong>"
                    <?php endif; ?>
                </div>
                <div class="sort-dropdown">
                    <span class="sort-label">Page <?= $page ?> of <?= max(1, $totalPages) ?></span>
                </div>
            </div>

            <?php if (empty($events)): ?>
                <!-- No Results -->
                <div class="no-results fade-in-up">
                    <div class="no-results-icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <h3>No Events Found</h3>
                    <p>We couldn't find any events matching your criteria. Try adjusting your filters or search terms.</p>
                    <div class="no-results-actions">
                        <a href="events.php" class="btn-event btn-primary-event">
                            <i class="fas fa-refresh me-2"></i>View All Events
                        </a>
                        <?php if ($isLoggedIn && $currentUser['role'] === 'organizer'): ?>
                            <a href="organizer/create-event.php" class="btn-event btn-outline-event">
                                <i class="fas fa-plus me-2"></i>Create Event
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Events Grid -->
                <div class="events-grid">
                    <?php foreach ($events as $event): ?>
                        <?php
                        $eventDate = new DateTime($event['start_datetime']);
                        $attendancePercentage = $event['max_attendees'] > 0 ? 
                            ($event['registered_count'] / $event['max_attendees']) * 100 : 0;
                        $categoryClass = 'category-' . strtolower($event['category']);
                        ?>
                        <div class="event-card fade-in-up">
                            <div class="event-image">
                                <?php if (!empty($event['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($event['image_url']) ?>" 
                                         alt="<?= htmlspecialchars($event['title']) ?>">
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/400x200/667eea/ffffff?text=<?= urlencode($event['title']) ?>" 
                                         alt="<?= htmlspecialchars($event['title']) ?>">
                                <?php endif; ?>
                                
                                <div class="event-category-badge <?= $categoryClass ?>">
                                    <?= ucfirst(htmlspecialchars($event['category'])) ?>
                                </div>
                                
                                <div class="event-date-badge">
                                    <div><?= $eventDate->format('M') ?></div>
                                    <div><?= $eventDate->format('d') ?></div>
                                </div>
                            </div>
                            
                            <div class="event-content">
                                <h3 class="event-title">
                                    <a href="event-details.php?id=<?= $event['event_id'] ?>">
                                        <?= htmlspecialchars($event['title']) ?>
                                    </a>
                                </h3>
                                
                                <p class="event-description">
                                    <?= htmlspecialchars(substr($event['description'], 0, 150)) ?>
                                    <?= strlen($event['description']) > 150 ? '...' : '' ?>
                                </p>
                                
                                <div class="event-meta">
                                    <div class="event-meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?= $eventDate->format('M j, Y') ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-clock"></i>
                                        <span><?= $eventDate->format('g:i A') ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?= htmlspecialchars($event['location']) ?></span>
                                    </div>
                                                                       <div class="event-meta-item">
                                        <i class="fas fa-user"></i>
                                        <span><?= htmlspecialchars($event['first_name'] . ' ' . $event['last_name']) ?></span>
                                    </div>
                                </div>
                                
                                <div class="event-stats">
                                    <div class="event-attendance">
                                        <span><?= $event['registered_count'] ?>/<?= $event['max_attendees'] ?></span>
                                        <div class="attendance-bar">
                                            <div class="attendance-fill" style="width: <?= min(100, $attendancePercentage) ?>%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="event-price <?= $event['price'] == 0 ? 'free' : '' ?>">
                                        <?php if ($event['price'] == 0): ?>
                                            FREE
                                        <?php else: ?>
                                            MWK <?= number_format($event['price']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="event-actions">
                                    <a href="event-details.php?id=<?= $event['event_id'] ?>" 
                                       class="btn-event btn-outline-event">
                                        <i class="fas fa-info-circle me-1"></i>Details
                                    </a>
                                    
                                    <?php if ($isLoggedIn): ?>
                                        <?php if ($attendancePercentage < 100): ?>
                                            <a href="register-event.php?id=<?= $event['event_id'] ?>" 
                                               class="btn-event btn-primary-event">
                                                <i class="fas fa-ticket-alt me-1"></i>Register
                                            </a>
                                        <?php else: ?>
                                            <button class="btn-event btn-primary-event" disabled>
                                                <i class="fas fa-times me-1"></i>Full
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="auth/login.php?redirect=<?= urlencode('event-details.php?id=' . $event['event_id']) ?>" 
                                           class="btn-event btn-primary-event">
                                            <i class="fas fa-sign-in-alt me-1"></i>Login to Register
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-section">
                        <div class="pagination">
                            <!-- Previous Page -->
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                                   class="pagination-item">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php else: ?>
                                <span class="pagination-item disabled">
                                    <i class="fas fa-chevron-left"></i>
                                </span>
                            <?php endif; ?>
                            
                            <!-- Page Numbers -->
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            if ($startPage > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" 
                                   class="pagination-item">1</a>
                                <?php if ($startPage > 2): ?>
                                    <span class="pagination-item disabled">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                   class="pagination-item <?= $i === $page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <span class="pagination-item disabled">...</span>
                                <?php endif; ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>" 
                                   class="pagination-item"><?= $totalPages ?></a>
                            <?php endif; ?>
                            
                            <!-- Next Page -->
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                                   class="pagination-item">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php else: ?>
                                <span class="pagination-item disabled">
                                    <i class="fas fa-chevron-right"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
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
                        <li><i class="fas fa-phone me-2"></i>+265 1 362 333</li>
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

        // Auto-submit form on filter change
        document.addEventListener('DOMContentLoaded', function() {
            const filterSelects = document.querySelectorAll('.filter-select');
            const searchInput = document.querySelector('.search-input');
            
            // Auto-submit on select change
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    document.getElementById('filterForm').submit();
                });
            });
            
            // Search with debounce
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (this.value.length >= 3 || this.value.length === 0) {
                        document.getElementById('filterForm').submit();
                    }
                }, 500);
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
        document.querySelectorAll('.fade-in-up, .slide-in-left').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            observer.observe(el);
        });

        // Loading overlay for form submissions
        function showLoading() {
            const overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div class="loading-spinner"></div>';
            document.body.appendChild(overlay);
        }

        // Event card hover effects
        document.querySelectorAll('.event-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Smooth scroll for pagination
        document.querySelectorAll('.pagination-item').forEach(item => {
            item.addEventListener('click', function(e) {
                if (!this.classList.contains('disabled') && !this.classList.contains('active')) {
                    showLoading();
                }
            });
        });

        console.log('🎪 Events Page Loaded Successfully!');
        console.log(`📊 Showing ${<?= count($events) ?>} events out of ${<?= $totalEvents ?>} total`);
    </script>
</body>
</html>
