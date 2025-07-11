<?php
/**
 * 🎫 My Events - EMS User Dashboard
 * Manage Your Event Registrations! 
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
$status_filter = $_GET['status'] ?? 'all';
$time_filter = $_GET['time'] ?? 'all';

// Build WHERE clause for tickets
$whereConditions = ["t.user_id = ?"];
$params = [$userId];
$types = 'i';

// Add status filter
if ($status_filter !== 'all') {
    $whereConditions[] = "t.payment_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

// Add time filter
if ($time_filter !== 'all') {
    switch ($time_filter) {
        case 'upcoming':
            $whereConditions[] = "e.start_datetime > NOW()";
            break;
        case 'past':
            $whereConditions[] = "e.start_datetime < NOW()";
            break;
        case 'today':
            $whereConditions[] = "DATE(e.start_datetime) = CURDATE()";
            break;
        case 'this_week':
            $whereConditions[] = "WEEK(e.start_datetime) = WEEK(NOW())";
            break;
    }
}

$whereClause = implode(' AND ', $whereConditions);

// Get user's events
$myEvents = [];
$eventStats = [
    'total' => 0,
    'upcoming' => 0,
    'past' => 0,
    'paid' => 0,
    'pending' => 0
];

try {
    // Get events with ticket information
    $sql = "
        SELECT e.*, t.ticket_id, t.payment_status, t.amount_paid, t.created_at as registration_date,
               u.first_name as organizer_first, u.last_name as organizer_last, u.email as organizer_email,
               CASE 
                   WHEN e.start_datetime > NOW() THEN 'upcoming'
                   WHEN e.start_datetime < NOW() THEN 'past'
                   ELSE 'today'
               END as time_status
        FROM tickets t
        JOIN events e ON t.event_id = e.event_id
        LEFT JOIN users u ON e.organizer_id = u.user_id
        WHERE {$whereClause}
        ORDER BY e.start_datetime DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $myEvents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Calculate stats
    foreach ($myEvents as $event) {
        $eventStats['total']++;
        if ($event['time_status'] === 'upcoming') $eventStats['upcoming']++;
        if ($event['time_status'] === 'past') $eventStats['past']++;
        if ($event['payment_status'] === 'completed') $eventStats['paid']++;
        if ($event['payment_status'] === 'pending') $eventStats['pending']++;
    }
    
} catch (Exception $e) {
    error_log("My events fetch error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Events - EMS</title>
    
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
        
        /* 🎨 Sidebar Styles (Same as events.php) */
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
        
        /* 📊 Stats Cards */
        .stats-section {
            padding: 2rem;
            margin-bottom: 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
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
        }
        
        .stat-card.primary::before { background: var(--primary-gradient); }
        .stat-card.success::before { background: var(--success-gradient); }
        .stat-card.warning::before { background: var(--warning-gradient); }
        .stat-card.info::before { background: var(--info-gradient); }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-hover-shadow);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: white;
        }
        
        .stat-icon.primary { background: var(--primary-gradient); }
        .stat-icon.success { background: var(--success-gradient); }
        .stat-icon.warning { background: var(--warning-gradient); }
        .stat-icon.info { background: var(--info-gradient); }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--sidebar-bg);
            margin-bottom: 0.3rem;
        }
        
        .stat-label {
            color: #6c757d;
            font-weight: 500;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* 🔍 Filter Section */
        .filter-section {
            background: white;
            padding: 1.5rem 2rem;
            margin: 0 2rem 2rem 2rem;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
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
        
        /* 🎫 Events List */
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
        
        .events-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
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
            transform: translateX(10px);
            box-shadow: var(--card-hover-shadow);
        }
        
        .event-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
        }
        
        .event-card.upcoming::before { background: var(--success-gradient); }
        .event-card.past::before { background: var(--info-gradient); }
        .event-card.today::before { background: var(--warning-gradient); }
        
        .event-content {
            padding: 2rem;
        }
        
        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        
        .event-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--sidebar-bg);
            margin: 0;
            margin-bottom: 0.5rem;
        }
        
        .event-subtitle {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .event-status-group {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
        }
        
        .event-status {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-completed {
            background: var(--success-gradient);
            color: white;
        }
        
        .status-pending {
            background: var(--warning-gradient);
            color: white;
        }
        
        .status-cancelled {
            background: var(--danger-gradient);
            color: white;
        }
        
        .time-status {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .time-upcoming {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }
        
        .time-past {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
            border: 1px solid #6c757d;
        }
        
        .time-today {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
            border: 1px solid #ff9800;
        }
        
        .event-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .event-meta-item {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .event-meta-item i {
            color: #667eea;
            width: 18px;
            text-align: center;
        }
        
        .event-meta-item strong {
            color: var(--sidebar-bg);
        }
        
        .event-actions {
            display: flex;
            gap: 0.7rem;
            flex-wrap: wrap;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
        }
        
        .btn {
            padding: 0.6rem 1.2rem;
            border-radius: 20px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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
        
        .btn-warning {
            background: var(--warning-gradient);
            color: white;
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.3);
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
        
        .btn-danger {
            background: var(--danger-gradient);
            color: white;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(244, 67, 54, 0.3);
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
            
            .stats-section {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .filter-section {
                margin: 0 1rem 1rem 1rem;
                padding: 1rem;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .events-section {
                padding: 0 1rem 2rem 1rem;
            }
            
            .event-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .event-status-group {
                align-items: flex-start;
                flex-direction: row;
                gap: 0.5rem;
            }
            
            .event-meta {
                grid-template-columns: 1fr;
                gap: 0.7rem;
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
                <a href="events.php" class="nav-link">
                    <i class="fas fa-calendar-alt nav-icon"></i>
                    <span class="nav-text">Browse Events</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="my-events.php" class="nav-link active">
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
                    <i class="fas fa-ticket-alt"></i> My Events
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

        <!-- 📊 Stats Section -->
        <div class="stats-section">
            <div class="stats-grid fade-in">
                <div class="stat-card primary">
                    <div class="stat-header">
                        <div class="stat-icon primary">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $eventStats['total']; ?></div>
                    <div class="stat-label">Total Events</div>
                </div>

                <div class="stat-card success">
                    <div class="stat-header">
                        <div class="stat-icon success">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $eventStats['upcoming']; ?></div>
                    <div class="stat-label">Upcoming Events</div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-header">
                        <div class="stat-icon warning">
                            <i class="fas fa-credit-card"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $eventStats['paid']; ?></div>
                    <div class="stat-label">Paid Events</div>
                </div>

                <div class="stat-card info">
                    <div class="stat-header">
                        <div class="stat-icon info">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $eventStats['pending']; ?></div>
                    <div class="stat-label">Pending Payments</div>
                </div>
            </div>
        </div>

        <!-- 🔍 Filter Section -->
        <div class="filter-section fade-in">
            <form method="GET" action="my-events.php" id="filterForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label class="filter-label">Payment Status</label>
                        <select name="status" class="filter-select">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Paid</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending Payment</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Time Period</label>
                        <select name="time" class="filter-select">
                            <option value="all" <?php echo $time_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                            <option value="upcoming" <?php echo $time_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                            <option value="past" <?php echo $time_filter === 'past' ? 'selected' : ''; ?>>Past Events</option>
                            <option value="today" <?php echo $time_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="this_week" <?php echo $time_filter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="filter-btn">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>
                    
                    <div class="filter-group">
                        <a href="my-events.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- 🎫 Events Section -->
        <div class="events-section">
            <div class="section-header">
                <h2 class="section-title">
                    <?php if ($status_filter !== 'all' || $time_filter !== 'all'): ?>
                        Filtered Events (<?php echo count($myEvents); ?>)
                    <?php else: ?>
                        All My Events (<?php echo count($myEvents); ?>)
                    <?php endif; ?>
                </h2>
                
                <div class="section-actions">
                    <a href="events.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Find More Events
                    </a>
                </div>
            </div>

            <?php if (!empty($myEvents)): ?>
                <div class="events-list">
                    <?php foreach ($myEvents as $event): ?>
                        <div class="event-card <?php echo $event['time_status']; ?> slide-in">
                            <div class="event-content">
                                <div class="event-header">
                                    <div class="event-title-section">
                                        <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                                        <p class="event-subtitle">
                                            Organized by <?php echo htmlspecialchars($event['organizer_first'] . ' ' . $event['organizer_last']); ?>
                                        </p>
                                    </div>
                                    
                                    <div class="event-status-group">
                                        <span class="event-status status-<?php echo $event['payment_status']; ?>">
                                            <?php echo ucfirst($event['payment_status']); ?>
                                        </span>
                                        <span class="time-status time-<?php echo $event['time_status']; ?>">
                                            <?php echo ucfirst($event['time_status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="event-meta">
                                    <div class="event-meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><strong>Date:</strong> <?php echo date('M j, Y', strtotime($event['start_datetime'])); ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-clock"></i>
                                        <span><strong>Time:</strong> <?php echo date('g:i A', strtotime($event['start_datetime'])); ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><strong>Location:</strong> <?php echo htmlspecialchars($event['location']); ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-money-bill-wave"></i>
                                        <span><strong>Amount Paid:</strong> 
                                            <?php echo $event['amount_paid'] > 0 ? 'K' . number_format($event['amount_paid']) : 'FREE'; ?>
                                        </span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-calendar-plus"></i>
                                        <span><strong>Registered:</strong> <?php echo date('M j, Y', strtotime($event['registration_date'])); ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-envelope"></i>
                                        <span><strong>Organizer:</strong> <?php echo htmlspecialchars($event['organizer_email']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="event-actions">
                                    <a href="../events/view.php?id=<?php echo $event['event_id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> View Event
                                    </a>
                                    
                                    <a href="../tickets/view.php?id=<?php echo $event['ticket_id']; ?>" class="btn btn-success">
                                        <i class="fas fa-ticket-alt"></i> View Ticket
                                    </a>
                                    
                                    <?php if ($event['payment_status'] === 'pending'): ?>
                                        <a href="../payments/complete.php?ticket=<?php echo $event['ticket_id']; ?>" class="btn btn-warning">
                                            <i class="fas fa-credit-card"></i> Complete Payment
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($event['time_status'] === 'upcoming'): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($event['organizer_email']); ?>" class="btn btn-outline">
                                            <i class="fas fa-envelope"></i> Contact Organizer
                                        </a>
                                        
                                        <button onclick="cancelRegistration(<?php echo $event['ticket_id']; ?>)" class="btn btn-danger">
                                            <i class="fas fa-times"></i> Cancel Registration
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($event['time_status'] === 'past'): ?>
                                        <button onclick="rateEvent(<?php echo $event['event_id']; ?>)" class="btn btn-outline">
                                            <i class="fas fa-star"></i> Rate Event
                                        </button>
                                        
                                        <a href="../tickets/download.php?id=<?php echo $event['ticket_id']; ?>" class="btn btn-outline">
                                            <i class="fas fa-download"></i> Download Receipt
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- 🎯 Quick Actions -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="content-card fade-in" style="background: white; border-radius: 15px; box-shadow: var(--card-shadow); padding: 2rem; margin-bottom: 2rem;">
                            <div class="card-header" style="padding: 0 0 1rem 0; border-bottom: 1px solid #e9ecef; margin-bottom: 2rem;">
                                <h3 style="font-size: 1.3rem; font-weight: 600; color: var(--sidebar-bg); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-bolt"></i>
                                    Quick Actions
                                </h3>
                            </div>
                            <div class="row">
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <a href="events.php" class="btn btn-primary w-100 p-3" style="height: auto; flex-direction: column; gap: 0.5rem;">
                                        <i class="fas fa-search fa-2x"></i>
                                        <span>Browse More Events</span>
                                    </a>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <a href="?status=pending" class="btn btn-warning w-100 p-3" style="height: auto; flex-direction: column; gap: 0.5rem;">
                                        <i class="fas fa-credit-card fa-2x"></i>
                                        <span>Pending Payments</span>
                                    </a>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <a href="?time=upcoming" class="btn btn-success w-100 p-3" style="height: auto; flex-direction: column; gap: 0.5rem;">
                                        <i class="fas fa-calendar-check fa-2x"></i>
                                        <span>Upcoming Events</span>
                                    </a>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <a href="../tickets/history.php" class="btn btn-outline w-100 p-3" style="height: auto; flex-direction: column; gap: 0.5rem;">
                                        <i class="fas fa-history fa-2x"></i>
                                        <span>Event History</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- 🎯 Empty State -->
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Events Found</h3>
                    <?php if ($status_filter !== 'all' || $time_filter !== 'all'): ?>
                        <p>No events match your current filters. Try adjusting your filter criteria.</p>
                        <a href="my-events.php" class="btn btn-primary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php else: ?>
                        <p>You haven't registered for any events yet. Start exploring and join exciting events!</p>
                        <a href="events.php" class="btn btn-primary">
                            <i class="fas fa-search"></i> Browse Events
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 🎨 Modals -->
    <!-- Cancel Registration Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius: 15px; border: none;">
                <div class="modal-header" style="background: var(--danger-gradient); color: white; border-radius: 15px 15px 0 0;">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle"></i> Cancel Registration
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 2rem;">
                    <p>Are you sure you want to cancel your registration for this event?</p>
                    <div class="alert alert-warning" style="border-radius: 10px;">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> Cancellation policies may apply. Please check the event terms.
                    </div>
                </div>
                <div class="modal-footer" style="border: none; padding: 1rem 2rem 2rem 2rem;">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Keep Registration
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmCancel">
                        <i class="fas fa-check"></i> Yes, Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Rate Event Modal -->
    <div class="modal fade" id="rateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius: 15px; border: none;">
                <div class="modal-header" style="background: var(--primary-gradient); color: white; border-radius: 15px 15px 0 0;">
                    <h5 class="modal-title">
                        <i class="fas fa-star"></i> Rate Event
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 2rem;">
                    <form id="ratingForm">
                        <div class="mb-3">
                            <label class="form-label">How was your experience?</label>
                            <div class="rating-stars" style="font-size: 2rem; color: #ffc107; margin: 1rem 0;">
                                <i class="fas fa-star" data-rating="1"></i>
                                <i class="fas fa-star" data-rating="2"></i>
                                <i class="fas fa-star" data-rating="3"></i>
                                <i class="fas fa-star" data-rating="4"></i>
                                <i class="fas fa-star" data-rating="5"></i>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Comments (Optional)</label>
                            <textarea class="form-control" rows="3" placeholder="Share your thoughts about the event..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="border: none; padding: 1rem 2rem 2rem 2rem;">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="submitRating">
                                                <i class="fas fa-check"></i> Submit Rating
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 📱 Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 🎨 My Events JavaScript Controller
        class MyEventsController {
            constructor() {
                this.currentTicketId = null;
                this.currentEventId = null;
                this.selectedRating = 0;
                this.init();
            }
            
            init() {
                this.bindEvents();
                this.initAnimations();
                this.initFilters();
                this.initRatingSystem();
            }
            
            bindEvents() {
                // Sidebar toggle for mobile
                const sidebarToggle = document.getElementById('sidebarToggle');
                const sidebar = document.getElementById('sidebar');
                
                if (sidebarToggle) {
                    sidebarToggle.addEventListener('click', () => {
                        sidebar.classList.toggle('show');
                    });
                }
                
                // Close sidebar when clicking outside on mobile
                document.addEventListener('click', (e) => {
                    if (window.innerWidth <= 768) {
                        if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                            sidebar.classList.remove('show');
                        }
                    }
                });
                
                // Cancel registration modal
                window.cancelRegistration = (ticketId) => {
                    this.currentTicketId = ticketId;
                    const modal = new bootstrap.Modal(document.getElementById('cancelModal'));
                    modal.show();
                };
                
                // Rate event modal
                window.rateEvent = (eventId) => {
                    this.currentEventId = eventId;
                    this.selectedRating = 0;
                    this.updateStarDisplay();
                    const modal = new bootstrap.Modal(document.getElementById('rateModal'));
                    modal.show();
                };
                
                // Confirm cancellation
                document.getElementById('confirmCancel').addEventListener('click', () => {
                    this.processCancellation();
                });
                
                // Submit rating
                document.getElementById('submitRating').addEventListener('click', () => {
                    this.submitRating();
                });
            }
            
            initAnimations() {
                // Animate cards on scroll
                const observerOptions = {
                    threshold: 0.1,
                    rootMargin: '0px 0px -50px 0px'
                };
                
                const observer = new IntersectionObserver((entries) => {
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
                
                // Observe stat cards
                const statCards = document.querySelectorAll('.stat-card');
                statCards.forEach(card => observer.observe(card));
            }
            
            initFilters() {
                // Auto-submit form on filter change
                const filterSelects = document.querySelectorAll('.filter-select');
                filterSelects.forEach(select => {
                    select.addEventListener('change', () => {
                        document.getElementById('filterForm').submit();
                    });
                });
            }
            
            initRatingSystem() {
                const stars = document.querySelectorAll('.rating-stars i');
                
                stars.forEach(star => {
                    star.addEventListener('click', () => {
                        this.selectedRating = parseInt(star.dataset.rating);
                        this.updateStarDisplay();
                    });
                    
                    star.addEventListener('mouseenter', () => {
                        const rating = parseInt(star.dataset.rating);
                        this.highlightStars(rating);
                    });
                });
                
                document.querySelector('.rating-stars').addEventListener('mouseleave', () => {
                    this.updateStarDisplay();
                });
            }
            
            highlightStars(rating) {
                const stars = document.querySelectorAll('.rating-stars i');
                stars.forEach((star, index) => {
                    if (index < rating) {
                        star.style.color = '#ffc107';
                        star.style.opacity = '1';
                    } else {
                        star.style.color = '#e9ecef';
                        star.style.opacity = '0.5';
                    }
                });
            }
            
            updateStarDisplay() {
                this.highlightStars(this.selectedRating);
            }
            
            async processCancellation() {
                if (!this.currentTicketId) return;
                
                const button = document.getElementById('confirmCancel');
                const originalText = button.innerHTML;
                
                try {
                    // Show loading state
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';
                    button.disabled = true;
                    
                    const response = await fetch('../api/cancel_registration.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            ticket_id: this.currentTicketId
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.showNotification('Registration cancelled successfully!', 'success');
                        
                        // Close modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('cancelModal'));
                        modal.hide();
                        
                        // Reload page after short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        this.showNotification(data.message || 'Failed to cancel registration', 'error');
                    }
                } catch (error) {
                    console.error('Cancellation error:', error);
                    this.showNotification('Network error occurred', 'error');
                } finally {
                    // Reset button
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            }
            
            async submitRating() {
                if (!this.currentEventId || this.selectedRating === 0) {
                    this.showNotification('Please select a rating', 'error');
                    return;
                }
                
                const button = document.getElementById('submitRating');
                const originalText = button.innerHTML;
                const comment = document.querySelector('#ratingForm textarea').value;
                
                try {
                    // Show loading state
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                    button.disabled = true;
                    
                    const response = await fetch('../api/rate_event.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            event_id: this.currentEventId,
                            rating: this.selectedRating,
                            comment: comment
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.showNotification('Thank you for your rating!', 'success');
                        
                        // Close modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('rateModal'));
                        modal.hide();
                        
                        // Reset form
                        this.selectedRating = 0;
                        document.querySelector('#ratingForm textarea').value = '';
                        this.updateStarDisplay();
                    } else {
                        this.showNotification(data.message || 'Failed to submit rating', 'error');
                    }
                } catch (error) {
                    console.error('Rating error:', error);
                    this.showNotification('Network error occurred', 'error');
                } finally {
                    // Reset button
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            }
            
            showNotification(message, type = 'info') {
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
        }
        
        // 🚀 Initialize Controller
        document.addEventListener('DOMContentLoaded', () => {
            new MyEventsController();
        });
        
        // 🎯 Additional Utility Functions
        function downloadTicket(ticketId) {
            const link = document.createElement('a');
            link.href = `../tickets/download.php?id=${ticketId}`;
            link.download = `ticket-${ticketId}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        function shareEvent(eventId, eventTitle) {
            if (navigator.share) {
                navigator.share({
                    title: eventTitle,
                    text: `Check out this event: ${eventTitle}`,
                    url: `${window.location.origin}/events/view.php?id=${eventId}`
                });
            } else {
                // Fallback to clipboard
                const url = `${window.location.origin}/events/view.php?id=${eventId}`;
                navigator.clipboard.writeText(url).then(() => {
                    showNotification('Event link copied to clipboard!', 'success');
                });
            }
        }
        
        // 🎨 Add notification styles
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
                    border-radius: 3px;
                }
                
                .notification-close:hover { 
                    color: #666; 
                    background: #f0f0f0;
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
                
                .rating-stars i {
                    cursor: pointer;
                    transition: all 0.2s ease;
                    margin: 0 0.1rem;
                }
                
                .rating-stars i:hover {
                    transform: scale(1.2);
                }
                
                @media (max-width: 768px) {
                    .notification {
                        right: 10px;
                        left: 10px;
                        min-width: auto;
                    }
                }
                
                /* Enhanced button loading states */
                .btn:disabled {
                    opacity: 0.7;
                    cursor: not-allowed;
                    transform: none !important;
                }
                
                .btn .fa-spinner {
                    animation: spin 1s linear infinite;
                }
                
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
                /* Enhanced modal styles */
                .modal-content {
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                }
                
                .modal-header {
                    border: none;
                }
                
                .modal-footer {
                    border: none;
                }
                
                .form-control {
                    border: 2px solid #e9ecef;
                    border-radius: 10px;
                    padding: 0.7rem 1rem;
                    transition: all 0.3s ease;
                }
                
                .form-control:focus {
                    border-color: #667eea;
                    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                }
                
                .alert {
                    border: none;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                
                /* Enhanced card hover effects */
                .event-card {
                    cursor: pointer;
                }
                
                .event-card:hover .event-title {
                    color: #667eea;
                }
                
                .event-card:hover .event-meta-item i {
                    color: #764ba2;
                }
                
                /* Loading overlay for actions */
                .loading-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(255, 255, 255, 0.9);
                                        display: none;
                    justify-content: center;
                    align-items: center;
                    z-index: 10000;
                }
                
                .loading-spinner {
                    width: 50px;
                    height: 50px;
                    border: 5px solid #f3f3f3;
                    border-top: 5px solid #667eea;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                }
                
                /* Enhanced empty state */
                .empty-state {
                    background: white;
                    border-radius: 20px;
                    box-shadow: var(--card-shadow);
                    margin: 2rem 0;
                }
                
                .empty-state i {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                
                /* Responsive enhancements */
                @media (max-width: 576px) {
                    .event-actions {
                        flex-direction: column;
                    }
                    
                    .event-actions .btn {
                        width: 100%;
                        justify-content: center;
                    }
                    
                    .stats-grid {
                        grid-template-columns: 1fr 1fr;
                    }
                    
                    .stat-card {
                        padding: 1rem;
                    }
                    
                    .stat-number {
                        font-size: 1.5rem;
                    }
                }
            </style>
        `;
        
        document.head.insertAdjacentHTML('beforeend', notificationStyles);
    </script>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>
</body>
</html>


