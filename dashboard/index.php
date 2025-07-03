<?php

/**
 * 👤 User Dashboard - EMS
 * Ekwendeni Mighty Campus Event Management System
 * Personal Event Management Paradise! 🎪
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

// Get user statistics
// Define getDashboardStats if not already defined
if (!function_exists('getDashboardStats')) {
    /**
     * Get dashboard statistics for the user.
     * @param mysqli $conn
     * @param int $userId
     * @param string $role
     * @return array
     */
    function getDashboardStats($conn, $userId, $role)
    {
        $stats = [
            'registered_events' => 0,
            'attended_events' => 0,
            'upcoming_events' => 0,
            'total_spent' => 0
        ];

        // Registered events
        $stmt = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($stats['registered_events']);
        $stmt->fetch();
        $stmt->close();

        // Attended events (assuming you have an 'attended' status or similar)
        $stmt = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = ? AND status = 'attended'");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($stats['attended_events']);
        $stmt->fetch();
        $stmt->close();

        // Upcoming events
        $stmt = $conn->prepare("SELECT COUNT(*) FROM tickets t JOIN events e ON t.event_id = e.event_id WHERE t.user_id = ? AND e.start_datetime > NOW()");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($stats['upcoming_events']);
        $stmt->fetch();
        $stmt->close();

        // Total spent (assuming you have a 'price' column in tickets or events)
        $stmt = $conn->prepare("SELECT SUM(t.amount_paid) FROM tickets t WHERE t.user_id = ? AND t.payment_status = 'paid'");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($totalSpent);
        $stmt->fetch();
        $stats['total_spent'] = $totalSpent ?: 0;
        $stmt->close();

        return $stats;
    }
}

$userStats = getDashboardStats($conn, $userId, $currentUser['role']);

// Get user's upcoming events
$upcomingEvents = [];
try {
    $stmt = $conn->prepare("
        SELECT e.*, t.ticket_id, t.payment_status, t.created_at as registration_date,
               u.first_name as organizer_first, u.last_name as organizer_last
        FROM events e 
        JOIN tickets t ON e.event_id = t.event_id
        LEFT JOIN users u ON e.organizer_id = u.user_id
        WHERE t.user_id = ? 
        AND e.start_datetime > NOW() 
        AND e.status = 'approved'
        ORDER BY e.start_datetime ASC 
        LIMIT 5
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $upcomingEvents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Upcoming events error: " . $e->getMessage());
}

// Get recent activity
$recentActivity = [];
try {
    $stmt = $conn->prepare("
        SELECT 'ticket_purchased' as activity_type, e.title, t.created_at, e.event_id
        FROM tickets t 
        JOIN events e ON t.event_id = e.event_id 
        WHERE t.user_id = ? 
        ORDER BY t.created_at DESC 
        LIMIT 10
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $recentActivity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Recent activity error: " . $e->getMessage());
}

// Get notifications
// Define getUserNotifications if not already defined
if (!function_exists('getUserNotifications')) {
    /**
     * Get recent notifications for a user.
     * @param mysqli $conn
     * @param int $userId
     * @param int $limit
     * @return array
     */
    function getUserNotifications($conn, $userId, $limit = 5)
    {
        $notifications = [];
        $stmt = $conn->prepare("SELECT notification_id, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();
        return $notifications;
    }
}

$notifications = getUserNotifications($conn, $userId, 5);
$unreadCount = 0;
foreach ($notifications as $notification) {
    if (!$notification['is_read']) $unreadCount++;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - EMS</title>

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

        /* 🎨 Sidebar Styles */
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

        .sidebar-header p {
            font-size: 0.9rem;
            opacity: 0.7;
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

        .nav-badge {
            margin-left: auto;
            background: var(--danger-gradient);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 600;
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

        .user-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--sidebar-bg);
        }

        .user-details small {
            color: #6c757d;
            text-transform: capitalize;
        }

        /* 📊 Dashboard Content */
        .dashboard-content {
            padding: 2rem;
        }

        /* 🎯 Stats Cards */
        .stats-row {
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            height: 100%;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-card.primary::before {
            background: var(--primary-gradient);
        }

        .stat-card.success::before {
            background: var(--success-gradient);
        }

        .stat-card.warning::before {
            background: var(--warning-gradient);
        }

        .stat-card.info::before {
            background: var(--info-gradient);
        }

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
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.primary {
            background: var(--primary-gradient);
        }

        .stat-icon.success {
            background: var(--success-gradient);
        }

        .stat-icon.warning {
            background: var(--warning-gradient);
        }

        .stat-icon.info {
            background: var(--info-gradient);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--sidebar-bg);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6c757d;
            font-weight: 500;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-change {
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .stat-change.positive {
            color: #4CAF50;
        }

        .stat-change.negative {
            color: #f44336;
        }

        /* 🎪 Content Cards */
        .content-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .content-card:hover {
            box-shadow: var(--card-hover-shadow);
        }

        .card-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e9ecef;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--sidebar-bg);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body {
            padding: 2rem;
        }

        /* 🎫 Event Cards */
        .event-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .event-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--primary-gradient);
        }

        .event-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .event-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--sidebar-bg);
            margin: 0;
        }

        .event-status {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-paid {
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

        .event-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .event-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .event-meta-item i {
            color: var(--primary-gradient);
            width: 16px;
        }

        .event-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            border-radius: 20px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-success {
            background: var(--success-gradient);
            color: white;
            border: none;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }

        .btn-warning {
            background: var(--warning-gradient);
            color: white;
            border: none;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.3);
        }

        /* 🔔 Notifications */
        .notification-item {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            transition: all 0.3s ease;
            position: relative;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background: #f8f9fa;
        }

        .notification-item.unread {
            background: rgba(102, 126, 234, 0.05);
            border-left: 4px solid #667eea;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .notification-title {
            font-weight: 600;
            color: var(--sidebar-bg);
            margin: 0;
            font-size: 0.9rem;
        }

        .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .notification-message {
            color: #6c757d;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        /* 📊 Activity Feed */
        .activity-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-item:hover {
            background: #f8f9fa;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 1rem;
            font-size: 0.9rem;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            color: var(--sidebar-bg);
            margin: 0;
            font-size: 0.9rem;
        }

        .activity-description {
            color: #6c757d;
            font-size: 0.8rem;
            margin: 0;
        }

        .activity-time {
            color: #6c757d;
            font-size: 0.8rem;
            white-space: nowrap;
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

            .top-bar {
                padding: 1rem;
            }

            .dashboard-content {
                padding: 1rem;
            }

            .stat-card {
                margin-bottom: 1rem;
            }

            .event-meta {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .event-actions {
                justify-content: flex-start;
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

        /* 🎯 Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h4 {
            margin-bottom: 1rem;
            color: var(--sidebar-bg);
        }

        .empty-state p {
            margin-bottom: 2rem;
        }

        /* 🔄 Loading States */
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
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
                <a href="index.php" class="nav-link active">
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
                <a href="my-events.php" class="nav-link">
                    <i class="fas fa-ticket-alt nav-icon"></i>
                    <span class="nav-text">My Events</span>
                    <?php if (count($upcomingEvents) > 0): ?>
                        <span class="nav-badge"><?php echo count($upcomingEvents); ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <div class="nav-item">
                <a href="notifications.php" class="nav-link">
                    <i class="fas fa-bell nav-icon"></i>
                    <span class="nav-text">Notifications</span>
                    <?php if ($unreadCount > 0): ?>
                        <span class="nav-badge"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
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
                    <i class="fas fa-home"></i> My Dashboard
                </h1>
            </div>

            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)); ?>
                </div>
                <div class="user-details">
                    <h6><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></h6>
                    <small><?php echo htmlspecialchars($currentUser['role']); ?></small>
                </div>
            </div>
        </div>

        <!-- 📊 Dashboard Content -->
        <div class="dashboard-content">
            <!-- Welcome Message -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="content-card fade-in">
                        <div class="card-body text-center">
                            <h2>🎉 Welcome back, <?php echo htmlspecialchars($currentUser['first_name']); ?>!</h2>
                            <p class="lead">Ready to discover amazing events and connect with your campus community?</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🎯 Stats Cards -->
            <div class="row stats-row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card primary fade-in">
                        <div class="stat-header">
                            <div class="stat-icon primary">
                                <i class="fas fa-ticket-alt"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $userStats['registered_events'] ?? 0; ?></div>
                        <div class="stat-label">Registered Events</div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> Active registrations
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card success fade-in">
                        <div class="stat-header">
                            <div class="stat-icon success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $userStats['attended_events'] ?? 0; ?></div>
                        <div class="stat-label">Events Attended</div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> Great participation!
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card warning fade-in">
                        <div class="stat-header">
                            <div class="stat-icon warning">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $userStats['upcoming_events'] ?? 0; ?></div>
                        <div class="stat-label">Upcoming Events</div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> Don't miss out!
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card info fade-in">
                        <div class="stat-header">
                            <div class="stat-icon info">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo formatCurrency($userStats['total_spent'] ?? 0); ?></div>
                        <div class="stat-label">Total Spent</div>
                        <div class="stat-change positive">
                            <i class="fas fa-chart-line"></i> Investment in experiences
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🎪 Main Content Row -->
            <div class="row">
                <!-- 🎫 Upcoming Events -->
                <div class="col-lg-8 mb-4">
                    <div class="content-card slide-in">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-calendar-alt"></i>
                                My Upcoming Events
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($upcomingEvents)): ?>
                                <?php foreach ($upcomingEvents as $event): ?>
                                    <div class="event-card">
                                        <div class="event-header">
                                            <h4 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h4>
                                            <span class="event-status status-<?php echo $event['payment_status']; ?>">
                                                <?php echo ucfirst($event['payment_status']); ?>
                                            </span>
                                        </div>

                                        <div class="event-meta">
                                            <div class="event-meta-item">
                                                <i class="fas fa-calendar"></i>
                                                <span><?php echo formatDateTime($event['start_datetime'], 'M j, Y'); ?></span>
                                            </div>
                                            <div class="event-meta-item">
                                                <i class="fas fa-clock"></i>
                                                <span><?php echo formatDateTime($event['start_datetime'], 'g:i A'); ?></span>
                                            </div>
                                            <div class="event-meta-item">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span><?php echo htmlspecialchars($event['venue']); ?></span>
                                            </div>
                                            <div class="event-meta-item">
                                                <i class="fas fa-user"></i>
                                                <span><?php echo htmlspecialchars($event['organizer_first'] . ' ' . $event['organizer_last']); ?></span>
                                            </div>
                                        </div>

                                        <div class="event-actions">
                                            <a href="../events/view.php?id=<?php echo $event['event_id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                            <a href="../tickets/view.php?id=<?php echo $event['ticket_id']; ?>" class="btn btn-success btn-sm">
                                                <i class="fas fa-ticket-alt"></i> My Ticket
                                            </a>
                                            <?php if ($event['payment_status'] === 'pending'): ?>
                                                <a href="../payments/complete.php?ticket=<?php echo $event['ticket_id']; ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-credit-card"></i> Complete Payment
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <div class="text-center mt-3">
                                    <a href="my-events.php" class="btn btn-primary">
                                        <i class="fas fa-calendar-alt"></i> View All My Events
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-calendar-times"></i>
                                    <h4>No Upcoming Events</h4>
                                    <p>You haven't registered for any upcoming events yet.</p>
                                    <a href="events.php" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Browse Events
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 🔔 Notifications & Activity -->
                <div class="col-lg-4">
                    <!-- Notifications -->
                    <div class="content-card slide-in mb-4">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-bell"></i>
                                Recent Notifications
                                <?php if ($unreadCount > 0): ?>
                                    <span class="badge bg-danger ms-2"><?php echo $unreadCount; ?></span>
                                <?php endif; ?>
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                                        <div class="notification-header">
                                            <h6 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                            <small class="notification-time"><?php echo timeAgo($notification['created_at']); ?></small>
                                        </div>
                                        <p class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    </div>
                                <?php endforeach; ?>

                                <div class="text-center p-3">
                                    <a href="notifications.php" class="btn btn-sm btn-primary">
                                        <i class="fas fa-bell"></i> View All Notifications
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-bell-slash"></i>
                                    <h6>No Notifications</h6>
                                    <p>You're all caught up!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="content-card slide-in">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-history"></i>
                                Recent Activity
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($recentActivity)): ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-ticket-alt"></i>
                                        </div>
                                        <div class="activity-content">
                                            <h6 class="activity-title">Event Registration</h6>
                                            <p class="activity-description">
                                                Registered for "<?php echo htmlspecialchars($activity['title']); ?>"
                                            </p>
                                        </div>
                                        <div class="activity-time">
                                            <?php echo timeAgo($activity['created_at']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-history"></i>
                                    <h6>No Recent Activity</h6>
                                    <p>Start exploring events!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🎯 Quick Actions -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-bolt"></i>
                                Quick Actions
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <a href="events.php" class="btn btn-primary w-100 p-3">
                                        <i class="fas fa-search fa-2x mb-2"></i>
                                        <br>Browse Events
                                    </a>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <a href="my-events.php" class="btn btn-success w-100 p-3">
                                        <i class="fas fa-calendar-check fa-2x mb-2"></i>
                                        <br>My Events
                                    </a>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <a href="profile.php" class="btn btn-info w-100 p-3">
                                        <i class="fas fa-user-edit fa-2x mb-2"></i>
                                        <br>Edit Profile
                                    </a>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <?php if ($currentUser['role'] === 'organizer'): ?>
                                        <a href="../organizer/create-event.php" class="btn btn-warning w-100 p-3">
                                            <i class="fas fa-plus-circle fa-2x mb-2"></i>
                                            <br>Create Event
                                        </a>
                                    <?php else: ?>
                                        <a href="settings.php" class="btn btn-secondary w-100 p-3">
                                            <i class="fas fa-cog fa-2x mb-2"></i>
                                            <br>Settings
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 📱 Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 🎨 Dashboard JavaScript
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

            // Mark notifications as read when clicked
            const notificationItems = document.querySelectorAll('.notification-item.unread');
            notificationItems.forEach(item => {
                item.addEventListener('click', function() {
                    this.classList.remove('unread');
                    // Here you would typically make an AJAX call to mark as read
                });
            });

            // Auto-refresh notifications every 30 seconds
            setInterval(function() {
                // You can implement AJAX refresh here
                console.log('Checking for new notifications...');
            }, 30000);

            // Add loading states to buttons
            const actionButtons = document.querySelectorAll('.btn');
            actionButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (this.href && !this.href.includes('#')) {
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                        this.disabled = true;
                    }
                });
            });

            // Animate stats on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationDelay = '0.1s';
                        entry.target.classList.add('fade-in');
                    }
                });
            }, observerOptions);

            // Observe all stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => observer.observe(card));

            // Welcome message personalization
            const welcomeMessages = [
                "Ready to discover amazing events? 🎉",
                "Your next adventure awaits! 🚀",
                "Let's make some memories! ✨",
                "Time to connect with your community! 🤝",
                "What exciting event will you join today? 🎪"
            ];

            const leadElement = document.querySelector('.lead');
            if (leadElement && Math.random() > 0.5) {
                leadElement.textContent = welcomeMessages[Math.floor(Math.random() * welcomeMessages.length)];
            }
        });

        // 🎯 Real-time updates (placeholder for WebSocket implementation)
        function initializeRealTimeUpdates() {
            // This is where you'd implement WebSocket connections
            // for real-time notifications and updates
            console.log('Real-time updates initialized');
        }

        // Initialize when page loads
        initializeRealTimeUpdates();
    </script>
</body>

</html>