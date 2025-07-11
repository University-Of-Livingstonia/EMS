<?php
/**
 * 🎪 Browse Events - EMS User Dashboard
 * Discover Amazing Campus Events! 
 */

require_once '../includes/functions.php';

// Get database connection
$conn = require_once '../config/database.php';

// Initialize session manager
require_once '../includes/session.php';
$sessionManager = new SessionManager($conn);

// Require login
$sessionManager->requireLogin();
$currentUser = $sessionManager->getCurrentUser();
$userId = $currentUser['user_id'];

// Get filter parameters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$date_filter = $_GET['date'] ?? '';
$sort = $_GET['sort'] ?? 'date_asc';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$whereConditions = ["e.status = 'approved'", "e.start_datetime > NOW()"];
$params = [];
$types = '';

if ($category) {
    $whereConditions[] = "e.category = ?";
    $params[] = $category;
    $types .= 's';
}

if ($search) {
    $whereConditions[] = "(e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

if ($date_filter) {
    switch ($date_filter) {
        case 'today':
            $whereConditions[] = "DATE(e.start_datetime) = CURDATE()";
            break;
        case 'tomorrow':
            $whereConditions[] = "DATE(e.start_datetime) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'this_week':
            $whereConditions[] = "WEEK(e.start_datetime) = WEEK(NOW())";
            break;
        case 'this_month':
            $whereConditions[] = "MONTH(e.start_datetime) = MONTH(NOW())";
            break;
    }
}

$whereClause = implode(' AND ', $whereConditions);

// Build ORDER BY clause
$orderBy = match($sort) {
    'date_asc' => 'e.start_datetime ASC',
    'date_desc' => 'e.start_datetime DESC',
    'title_asc' => 'e.title ASC',
    'title_desc' => 'e.title DESC',
    'price_asc' => 'e.price ASC',
    'price_desc' => 'e.price DESC',
    default => 'e.start_datetime ASC'
};

// Get events
$events = [];
$totalEvents = 0;

try {
    // Count total events
    $countSql = "SELECT COUNT(*) as total FROM events e WHERE {$whereClause}";
    if ($params) {
        $countStmt = $conn->prepare($countSql);
        $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $totalEvents = $countStmt->get_result()->fetch_assoc()['total'];
    } else {
        $totalEvents = $conn->query($countSql)->fetch_assoc()['total'];
    }
    
    // Get events with pagination
    $sql = "
        SELECT e.*, u.first_name as organizer_first, u.last_name as organizer_last,
               (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.event_id) as registered_count,
               (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.event_id AND t.user_id = ?) as user_registered
        FROM events e 
        LEFT JOIN users u ON e.organizer_id = u.user_id
        WHERE {$whereClause}
        ORDER BY {$orderBy}
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $allParams = [$userId, ...$params, $limit, $offset];
    $allTypes = 'i' . $types . 'ii';
    $stmt->bind_param($allTypes, ...$allParams);
    $stmt->execute();
    $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    error_log("Events fetch error: " . $e->getMessage());
}

// Get categories for filter
$categories = [];
try {
    $result = $conn->query("SELECT DISTINCT category FROM events WHERE status = 'approved' AND category IS NOT NULL ORDER BY category");
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
} catch (Exception $e) {
    error_log("Categories fetch error: " . $e->getMessage());
}

$totalPages = ceil($totalEvents / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Events - EMS</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            --success-gradient: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            --warning-gradient: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            --info-gradient: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            --danger-gradient: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            --sidebar-bg: #2c3e50;
            --sidebar-hover: #34495e;
            --content-bg: #f8f9fa;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --card-hover-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--content-bg);
            overflow-x: hidden;
        }
        
        /* 🎨 Sidebar Styles (Same as dashboard) */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: var(--sidebar-bg);
            color: white;
            transition: all 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar.collapsed {
            width: 80px;
        }
        
        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .sidebar-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: var(--secondary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .nav-item {
            margin: 0.5rem 0;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .nav-link:hover,
        .nav-link.active {
            background: var(--sidebar-hover);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--secondary-gradient);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }
        
        .nav-link:hover::before,
        .nav-link.active::before {
            transform: scaleY(1);
        }
        
        .nav-icon {
            font-size: 1.2rem;
            margin-right: 1rem;
            width: 20px;
            text-align: center;
        }
        
        .nav-text {
            font-weight: 500;
        }
        
        /* 📱 Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        .main-content.expanded {
            margin-left: 80px;
        }
        
        /* 🎯 Top Bar */
        .top-bar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--sidebar-bg);
            margin: 0;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        /* 🔍 Search & Filter Section */
        .search-filter-section {
            background: white;
            padding: 2rem;
            margin: 2rem;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
        }
        
        .search-bar {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .search-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid #e9ecef;
            border-radius: 50px;
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
            color: #6c757d;
            font-size: 1.2rem;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-label {
            font-weight: 600;
            color: var(--sidebar-bg);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .filter-select {
            padding: 0.7rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .filter-btn {
            padding: 0.7rem 1.5rem;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .clear-filters {
            padding: 0.7rem 1.5rem;
            background: transparent;
            color: #6c757d;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .clear-filters:hover {
            border-color: #6c757d;
            color: var(--sidebar-bg);
        }
        
        /* 🎪 Events Grid */
        .events-section {
            padding: 0 2rem 2rem 2rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--sidebar-bg);
        }
        
        .results-info {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .event-card {
            background: white;
                        border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .event-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--card-hover-shadow);
        }
        
        .event-image {
            height: 200px;
            background: var(--primary-gradient);
            position: relative;
            overflow: hidden;
        }
        
        .event-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(102, 126, 234, 0.8), rgba(118, 75, 162, 0.8));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .event-image::after {
            content: '🎪';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 3rem;
            z-index: 1;
        }
        
        .event-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            z-index: 2;
        }
        
        .badge-free {
            background: var(--success-gradient);
            color: white;
        }
        
        .badge-paid {
            background: var(--warning-gradient);
            color: white;
        }
        
        .badge-registered {
            background: var(--info-gradient);
            color: white;
        }
        
        .event-content {
            padding: 1.5rem;
        }
        
        .event-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--sidebar-bg);
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }
        
        .event-description {
            color: #6c757d;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .event-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .event-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .event-meta-item i {
            color: #667eea;
            width: 16px;
            text-align: center;
        }
        
        .event-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }
        
        .event-price {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--sidebar-bg);
        }
        
        .price-free {
            color: #4CAF50;
        }
        
        .event-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-success {
            background: var(--success-gradient);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: #667eea;
            border: 2px solid #667eea;
        }
        
        .btn-outline:hover {
            background: #667eea;
            color: white;
        }
        
        .btn-registered {
            background: var(--info-gradient);
            color: white;
            cursor: default;
        }
        
        /* 📄 Pagination */
        .pagination-section {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-top: 3rem;
        }
        
        .pagination {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .page-btn {
            padding: 0.7rem 1rem;
            border: 2px solid #e9ecef;
            background: white;
            color: #6c757d;
            text-decoration: none;
            border-radius: 10px;
                       font-weight: 600;
            transition: all 0.3s ease;
            min-width: 45px;
            text-align: center;
        }
        
        .page-btn:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-2px);
        }
        
        .page-btn.active {
            background: var(--primary-gradient);
            border-color: transparent;
            color: white;
        }
        
        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        /* 🎯 Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--sidebar-bg);
        }
        
        .empty-state p {
            font-size: 1rem;
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* 📱 Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .search-filter-section {
                margin: 1rem;
                padding: 1.5rem;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .events-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .events-section {
                padding: 0 1rem 2rem 1rem;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .event-meta {
                grid-template-columns: 1fr;
            }
            
            .event-footer {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .event-actions {
                justify-content: center;
            }
        }
        
        /* 🎨 Animations */
        .fade-in {
            animation: fadeIn 0.6s ease-in;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .slide-in {
            animation: slideIn 0.6s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* 🔄 Loading States */
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 3rem;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* 🎯 Quick Filters */
        .quick-filters {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .quick-filter {
            padding: 0.5rem 1rem;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 20px;
            color: #6c757d;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .quick-filter:hover,
        .quick-filter.active {
            border-color: #667eea;
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
    </style>
</head>

<body>
    <!-- 🎨 Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>🎓 EMS</h3>
            <p>Event Management</p>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-home nav-icon"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="events.php" class="nav-link active">
                    <i class="fas fa-calendar-alt nav-icon"></i>
                    <span class="nav-text">Browse Events</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="my-events.php" class="nav-link">
                    <i class="fas fa-ticket-alt nav-icon"></i>
                    <span class="nav-text">My Events</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="notifications.php" class="nav-link">
                    <i class="fas fa-bell nav-icon"></i>
                    <span class="nav-text">Notifications</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="profile.php" class="nav-link">
                    <i class="fas fa-user nav-icon"></i>
                    <span class="nav-text">Profile</span>
                </a>
            </div>

            <?php if ($currentUser['role'] === 'organizer'): ?>
                <div class="nav-item">
                    <a href="../organizer/dashboard.php" class="nav-link">
                        <i class="fas fa-plus-circle nav-icon"></i>
                        <span class="nav-text">Create Event</span>
                    </a>
                </div>
            <?php endif; ?>

            <div class="nav-item">
                <a href="settings.php" class="nav-link">
                    <i class="fas fa-cog nav-icon"></i>
                    <span class="nav-text">Settings</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="../auth/logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt nav-icon"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- 📱 Main Content -->
    <div class="main-content" id="mainContent">
        <!-- 🎯 Top Bar -->
        <div class="top-bar">
            <div class="d-flex align-items-center">
                <button class="btn btn-link d-md-none" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">
                    <i class="fas fa-calendar-alt"></i> Browse Events
                </h1>
            </div>

            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)); ?>
                </div>
                <div class="user-details d-none d-md-block">
                    <h6><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></h6>
                    <small><?php echo htmlspecialchars($currentUser['role']); ?></small>
                </div>
            </div>
        </div>

        <!-- 🔍 Search & Filter Section -->
        <div class="search-filter-section fade-in">
            <form method="GET" action="events.php" id="filterForm">
                <!-- Search Bar -->
                <div class="search-bar">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="search" class="search-input" 
                           placeholder="Search events by title, description, or location..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <!-- Quick Filters -->
                <div class="quick-filters">
                    <a href="?date=today" class="quick-filter <?php echo $date_filter === 'today' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-day"></i> Today
                    </a>
                    <a href="?date=tomorrow" class="quick-filter <?php echo $date_filter === 'tomorrow' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-plus"></i> Tomorrow
                    </a>
                    <a href="?date=this_week" class="quick-filter <?php echo $date_filter === 'this_week' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-week"></i> This Week
                    </a>
                    <a href="?date=this_month" class="quick-filter <?php echo $date_filter === 'this_month' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar"></i> This Month
                    </a>
                    <?php if ($search || $category || $date_filter): ?>
                        <a href="events.php" class="quick-filter">
                            <i class="fas fa-times"></i> Clear All
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Advanced Filters -->
                <div class="filter-row">
                    <div class="filter-group">
                        <label class="filter-label">Category</label>
                        <select name="category" class="filter-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" 
                                        <?php echo $category === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst($cat)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Date</label>
                        <select name="date" class="filter-select">
                            <option value="">Any Time</option>
                            <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="tomorrow" <?php echo $date_filter === 'tomorrow' ? 'selected' : ''; ?>>Tomorrow</option>
                            <option value="this_week" <?php echo $date_filter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="this_month" <?php echo $date_filter === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Sort By</label>
                        <select name="sort" class="filter-select">
                            <option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Date (Earliest First)</option>
                            <option value="date_desc" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>Date (Latest First)</option>
                            <option value="title_asc" <?php echo $sort === 'title_asc' ? 'selected' : ''; ?>>Title (A-Z)</option>
                            <option value="title_desc" <?php echo $sort === 'title_desc' ? 'selected' : ''; ?>>Title (Z-A)</option>
                            <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price (Low to High)</option>
                            <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price (High to Low)</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="filter-btn">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>
                    
                    <div class="filter-group">
                        <a href="events.php" class="clear-filters">
                            <i class="fas fa-times"></i> Clear All
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- 🎪 Events Section -->
        <div class="events-section">
            <div class="section-header">
                <h2 class="section-title">
                    <?php if ($search): ?>
                        Search Results for "<?php echo htmlspecialchars($search); ?>"
                    <?php elseif ($category): ?>
                        <?php echo htmlspecialchars(ucfirst($category)); ?> Events
                    <?php else: ?>
                        Upcoming Events
                    <?php endif; ?>
                </h2>
                <div class="results-info">
                    Showing <?php echo count($events); ?> of <?php echo $totalEvents; ?> events
                </div>
            </div>

            <?php if (!empty($events)): ?>
                <div class="events-grid">
                    <?php foreach ($events as $event): ?>
                        <div class="event-card slide-in">
                            <div class="event-image">
                                <?php if ($event['user_registered'] > 0): ?>
                                    <div class="event-badge badge-registered">Registered</div>
                                <?php elseif ($event['price'] > 0): ?>
                                    <div class="event-badge badge-paid">K<?php echo number_format($event['price']); ?></div>
                                <?php else: ?>
                                    <div class="event-badge badge-free">Free</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="event-content">
                                                                <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                                <p class="event-description"><?php echo htmlspecialchars($event['description']); ?></p>
                                
                                <div class="event-meta">
                                    <div class="event-meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php echo date('M j, Y', strtotime($event['start_datetime'])); ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-clock"></i>
                                        <span><?php echo date('g:i A', strtotime($event['start_datetime'])); ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($event['location']); ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-users"></i>
                                        <span><?php echo $event['registered_count']; ?>/<?php echo $event['max_attendees']; ?> registered</span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-user"></i>
                                        <span><?php echo htmlspecialchars($event['organizer_first'] . ' ' . $event['organizer_last']); ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-tag"></i>
                                        <span><?php echo htmlspecialchars(ucfirst($event['category'] ?? 'General')); ?></span>
                                    </div>
                                </div>
                                
                                <div class="event-footer">
                                    <div class="event-price <?php echo $event['price'] == 0 ? 'price-free' : ''; ?>">
                                        <?php echo $event['price'] > 0 ? 'K' . number_format($event['price']) : 'FREE'; ?>
                                    </div>
                                    
                                    <div class="event-actions">
                                        <a href="../events/view.php?id=<?php echo $event['event_id']; ?>" class="btn btn-outline">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <?php if ($event['user_registered'] > 0): ?>
                                            <span class="btn btn-registered">
                                                <i class="fas fa-check"></i> Registered
                                            </span>
                                        <?php elseif ($event['registered_count'] >= $event['max_attendees']): ?>
                                            <span class="btn btn-outline" style="opacity: 0.5; cursor: not-allowed;">
                                                <i class="fas fa-times"></i> Full
                                            </span>
                                        <?php else: ?>
                                            <a href="../events/register.php?id=<?php echo $event['event_id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-ticket-alt"></i> Register
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- 📄 Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-section">
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-btn">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            if ($startPage > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-btn">1</a>
                                <?php if ($startPage > 2): ?>
                                    <span class="page-btn" style="border: none; cursor: default;">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <span class="page-btn" style="border: none; cursor: default;">...</span>
                                <?php endif; ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" class="page-btn"><?php echo $totalPages; ?></a>
                            <?php endif; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-btn">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- 🎯 Empty State -->
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Events Found</h3>
                    <?php if ($search || $category || $date_filter): ?>
                        <p>No events match your current filters. Try adjusting your search criteria or browse all events.</p>
                        <a href="events.php" class="btn btn-primary">
                            <i class="fas fa-calendar-alt"></i> View All Events
                        </a>
                    <?php else: ?>
                        <p>There are no upcoming events at the moment. Check back later for exciting new events!</p>
                        <?php if ($currentUser['role'] === 'organizer'): ?>
                            <a href="../organizer/create-event.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> Create Your First Event
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 📱 Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 🎨 Events Page JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle for mobile
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                        sidebar.classList.remove('show');
                    }
                }
            });

            // Auto-submit form on filter change
            const filterSelects = document.querySelectorAll('.filter-select');
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    document.getElementById('filterForm').submit();
                });
            });

            // Search input with debounce
            const searchInput = document.querySelector('.search-input');
            let searchTimeout;
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        if (this.value.length >= 3 || this.value.length === 0) {
                            document.getElementById('filterForm').submit();
                        }
                    }, 500);
                });
            }

            // Animate cards on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationDelay = Math.random() * 0.3 + 's';
                        entry.target.classList.add('slide-in');
                    }
                });
            }, observerOptions);

            // Observe all event cards
            const eventCards = document.querySelectorAll('.event-card');
            eventCards.forEach(card => observer.observe(card));

            // Add loading states to registration buttons
            const registerButtons = document.querySelectorAll('a[href*="register.php"]');
            registerButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering...';
                    this.style.pointerEvents = 'none';
                });
            });

            // Smooth scroll for pagination
            const pageButtons = document.querySelectorAll('.page-btn');
            pageButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (this.href) {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                });
            });

            // Add hover effects to event cards
            eventCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-10px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Quick filter animations
            const quickFilters = document.querySelectorAll('.quick-filter');
            quickFilters.forEach(filter => {
                filter.addEventListener('click', function(e) {
                    // Add loading state
                    const originalContent = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                    
                    // Reset after a short delay if navigation doesn't happen
                    setTimeout(() => {
                        this.innerHTML = originalContent;
                    }, 2000);
                });
            });

            // Add keyboard navigation for accessibility
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    // Close mobile sidebar
                    sidebar.classList.remove('show');
                }
                
                if (e.key === '/' && !e.target.matches('input, textarea')) {
                    // Focus search input
                    e.preventDefault();
                    searchInput.focus();
                }
            });

            // Add tooltips to full events
            const fullButtons = document.querySelectorAll('.btn[style*="cursor: not-allowed"]');
            fullButtons.forEach(button => {
                button.title = 'This event is fully booked';
            });

            // Real-time updates (placeholder for WebSocket implementation)
            function updateEventCounts() {
                // This would typically use WebSocket or periodic AJAX calls
                // to update registration counts in real-time
                console.log('Checking for event updates...');
            }

            // Update every 30 seconds
            setInterval(updateEventCounts, 30000);
        });

        // 🎯 Utility Functions
        function showNotification(message, type = 'info') {
            // Create notification element
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
            
            // Add to page
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.style.animation = 'slideOutRight 0.3s ease-out';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }

        // Add notification styles
        const notificationStyles = `
            <style>
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
                }
                
                .notification-close:hover { color: #666; }
                
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
                
                @keyframes slideOutRight {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                }
                
                @media (max-width: 768px) {
                    .notification {
                        right: 10px;
                        left: 10px;
                        min-width: auto;
                    }
                }
            </style>
        `;
        
        document.head.insertAdjacentHTML('beforeend', notificationStyles);
    </script>
</body>
</html>


            
