<?php
/**
 * 👑 Admin Dashboard - EMS
 * Ekwendeni Mighty Campus Event Management System
 * The Ultimate System Control Center! 🎛️
 */

require_once '../includes/functions.php';

// Get database connection
$conn = require_once '../config/database.php';

// Initialize session manager
require_once '../includes/session.php';
$sessionManager = new SessionManager($conn);

// Require admin login
$sessionManager->requireLogin();
$currentUser = $sessionManager->getCurrentUser();

// Check if user is admin
if ($currentUser['role'] !== 'admin') {
    header('Location: ../dashboard/index.php');
    exit;
}

// Get comprehensive admin statistics
$adminStats = [];
try {
    // Total users by role
    $stmt = $conn->query("
        SELECT role, COUNT(*) as count 
        FROM users 
        GROUP BY role
    ");
    $usersByRole = [];
    while ($row = $stmt->fetch_assoc()) {
        $usersByRole[$row['role']] = $row['count'];
    }
    $adminStats['users'] = $usersByRole;
    
    // Total events by status
    $stmt = $conn->query("
        SELECT status, COUNT(*) as count 
        FROM events 
        GROUP BY status
    ");
    $eventsByStatus = [];
    while ($row = $stmt->fetch_assoc()) {
        $eventsByStatus[$row['status']] = $row['count'];
    }
    $adminStats['events'] = $eventsByStatus;
    
    // Revenue statistics
    $stmt = $conn->query("
        SELECT 
            SUM(CASE WHEN payment_status = 'completed' THEN price ELSE 0 END) as total_revenue,
            SUM(CASE WHEN payment_status = 'pending' THEN price ELSE 0 END) as pending_revenue,
            COUNT(CASE WHEN payment_status = 'completed' THEN 1 END) as completed_payments,
            COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_payments
        FROM tickets
    ");
    $adminStats['revenue'] = $stmt->fetch_assoc();
    
    // Recent activity counts
    $adminStats['recent'] = [
        'new_users_today' => getSingleStat($conn, "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()"),
        'new_events_today' => getSingleStat($conn, "SELECT COUNT(*) as count FROM events WHERE DATE(created_at) = CURDATE()"),
        'tickets_today' => getSingleStat($conn, "SELECT COUNT(*) as count FROM tickets WHERE DATE(created_at) = CURDATE()"),
        'pending_approvals' => getSingleStat($conn, "SELECT COUNT(*) as count FROM events WHERE status = 'pending'")
    ];
    
} catch (Exception $e) {
    error_log("Admin stats error: " . $e->getMessage());
    $adminStats = [
        'users' => ['admin' => 0, 'organizer' => 0, 'user' => 0],
        'events' => ['approved' => 0, 'pending' => 0, 'draft' => 0],
        'revenue' => ['total_revenue' => 0, 'pending_revenue' => 0],
        'recent' => ['new_users_today' => 0, 'new_events_today' => 0, 'tickets_today' => 0, 'pending_approvals' => 0]
    ];
}

// Get recent users
$recentUsers = [];
try {
    $stmt = $conn->prepare("
        SELECT user_id, username, email, first_name, last_name, role, created_at
        FROM users 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recentUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Recent users error: " . $e->getMessage());
}

// Get pending events for approval
$pendingEvents = [];
try {
    $stmt = $conn->prepare("
        SELECT e.*, u.first_name, u.last_name, u.email
        FROM events e
        JOIN users u ON e.organizer_id = u.user_id
        WHERE e.status = 'pending'
        ORDER BY e.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $pendingEvents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Pending events error: " . $e->getMessage());
}

// Get system alerts
$systemAlerts = [];
try {
    // Check for system issues
    $alerts = [];
    
    // Check for events with high registration but low payment completion
    $stmt = $conn->query("
        SELECT e.title, COUNT(t.ticket_id) as total_tickets,
               SUM(CASE WHEN t.payment_status = 'completed' THEN 1 ELSE 0 END) as paid_tickets
        FROM events e
        JOIN tickets t ON e.event_id = t.event_id
        GROUP BY e.event_id
        HAVING total_tickets > 10 AND (paid_tickets / total_tickets) < 0.5
        LIMIT 5
    ");
    while ($row = $stmt->fetch_assoc()) {
        $alerts[] = [
            'type' => 'warning',
            'title' => 'Low Payment Completion',
            'message' => "Event '{$row['title']}' has low payment completion rate",
            'action' => 'Review payment issues'
        ];
    }
    
    // Check for events starting soon without enough registrations
    $stmt = $conn->query("
        SELECT e.title, e.start_datetime, COUNT(t.ticket_id) as registrations
        FROM events e
        LEFT JOIN tickets t ON e.event_id = t.event_id
        WHERE e.start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
        AND e.status = 'approved'
        GROUP BY e.event_id
        HAVING registrations < 5
        LIMIT 5
    ");
    while ($row = $stmt->fetch_assoc()) {
        $alerts[] = [
            'type' => 'info',
            'title' => 'Low Registration Alert',
            'message' => "Event '{$row['title']}' starts soon with only {$row['registrations']} registrations",
            'action' => 'Consider promotion'
        ];
    }
    
    $systemAlerts = $alerts;
} catch (Exception $e) {
    error_log("System alerts error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - EMS</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Chart.js for Analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --admin-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --admin-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --admin-success: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            --admin-warning: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            --admin-danger: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            --admin-info: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            --admin-dark: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            --sidebar-bg: #1a1a2e;
            --sidebar-hover: #16213e;
            --content-bg: #f8f9fa;
            --card-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            --card-hover-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --border-color: #e9ecef;
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
        
        /* 👑 Admin Sidebar */
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 300px;
            background: var(--sidebar-bg);
            color: white;
            transition: all 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
        }
        
        .admin-sidebar.collapsed {
            width: 80px;
        }
        
        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            background: var(--admin-primary);
        }
        
        .sidebar-header h3 {
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: white;
        }
        
        .sidebar-header p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .admin-nav {
            padding: 1.5rem 0;
        }
        
        .nav-section {
            margin-bottom: 2rem;
        }
        
        .nav-section-title {
            padding: 0 1.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 1rem;
        }
        
        .admin-nav-item {
            margin: 0.3rem 0;
        }
        
        .admin-nav-link {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            border-radius: 0 25px 25px 0;
            margin-right: 1rem;
        }
        
        .admin-nav-link:hover,
        .admin-nav-link.active {
            background: var(--sidebar-hover);
            color: white;
            transform: translateX(10px);
        }
        
        .admin-nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--admin-secondary);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }
        
        .admin-nav-link:hover::before,
        .admin-nav-link.active::before {
            transform: scaleY(1);
        }
        
        .nav-icon {
            font-size: 1.3rem;
            margin-right: 1rem;
            width: 25px;
            text-align: center;
        }
        
        .nav-text {
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .nav-badge {
            margin-left: auto;
            background: var(--admin-danger);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        /* 📱 Main Content */
        .admin-main {
            margin-left: 300px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        .admin-main.expanded {
            margin-left: 80px;
        }
        
        /* 🎯 Admin Top Bar */
        .admin-topbar {
            background: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .admin-title {
            font-size: 2rem;
            font-weight: 800;
            background: var(--admin-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }
        
        .admin-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .admin-search {
            position: relative;
        }
        
        .admin-search input {
            padding: 0.7rem 1rem 0.7rem 2.5rem;
            border: 2px solid var(--border-color);
            border-radius: 25px;
            width: 300px;
            transition: all 0.3s ease;
        }
        
        .admin-search input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .admin-search i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }
        
        .admin-user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .admin-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--admin-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .admin-user-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .admin-user-details small {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        /* 📊 Dashboard Content */
        .admin-content {
            padding: 2rem;
        }
        
        /* 🎯 Super Stats Cards */
        .super-stats {
            margin-bottom: 2rem;
        }
        
        .super-stat-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            height: 100%;
            border: 1px solid var(--border-color);
        }
        
        .super-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
        }
        
        .super-stat-card.primary::before { background: var(--admin-primary); }
        .super-stat-card.success::before { background: var(--admin-success); }
        .super-stat-card.warning::before { background: var(--admin-warning); }
        .super-stat-card.danger::before { background: var(--admin-danger); }
        .super-stat-card.info::before { background: var(--admin-info); }
        
        .super-stat-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--card-hover-shadow);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .stat-icon.primary { background: var(--admin-primary); }
        .stat-icon.success { background: var(--admin-success); }
        .stat-icon.warning { background: var(--admin-warning); }
        .stat-icon.danger { background: var(--admin-danger); }
        .stat-icon.info { background: var(--admin-info); }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 900;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-change {
            font-size: 0.9rem;
            font-weight: 600;
            margin-top: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            display: inline-block;
        }
        
        .stat-change.positive {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .stat-change.negative {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }
        
        .stat-change.neutral {
            background: rgba(108, 117, 125, 0.1);
            color: var(--text-secondary);
        }
        
        /* 🎪 Admin Content Cards */
        .admin-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }
        
        .admin-card:hover {
            box-shadow: var(--card-hover-shadow);
            transform: translateY(-5px);
        }
        
        .admin-card-header {
            padding: 2rem 2rem 1rem 2rem;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }
        
        .admin-card-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }
        
        .admin-card-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .admin-card-body {
            padding: 2rem;
        }
        
        /* 📊 Charts Container */
        .chart-container {
            position: relative;
            height: 400px;
            margin: 1rem 0;
        }
        
        .mini-chart-container {
            position: relative;
            height: 200px;
            margin: 1rem 0;
        }
        
        /* 👥 User Management Table */
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .admin-table th,
        .admin-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .admin-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .admin-table tr:hover {
            background: #f8f9fa;
        }
        
        .user-avatar-small {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: var(--admin-primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.8rem;
            margin-right: 0.5rem;
        }
        
        .role-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .role-admin {
            background: var(--admin-danger);
            color: white;
        }
        
        .role-organizer {
            background: var(--admin-warning);
            color: white;
        }
        
        .role-user {
            background: var(--admin-info);
            color: white;
        }
        
        /* 🎪 Event Cards */
        .admin-event-card {
            border: 1px solid var(--border-color);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            position: relative;
            background: white;
        }
        
        .admin-event-card:hover {
            transform: translateX(10px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .admin-event-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            background: var(--admin-warning);
            border-radius: 5px 0 0 5px;
        }
        
        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .event-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }
        
        .event-status {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
            border: 2px solid #ff9800;
        }
        
        .event-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .event-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .event-meta-item i {
            color: var(--admin-primary);
            width: 18px;
        }
        
        .event-actions {
            display: flex;
            gap: 0.7rem;
            flex-wrap: wrap;
        }
        
        .admin-btn {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            border-radius: 20px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .admin-btn-primary {
            background: var(--admin-primary);
            color: white;
        }
        
        .admin-btn-success {
            background: var(--admin-success);
            color: white;
        }
        
        .admin-btn-danger {
            background: var(--admin-danger);
            color: white;
        }
        
        .admin-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        /* 🚨 System Alerts */
        .alert-item {
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 1rem;
            border-left: 5px solid;
            transition: all 0.3s ease;
        }
        
        .alert-item:hover {
            transform: translateX(5px);
        }
        
        .alert-warning {
            background: rgba(255, 152, 0, 0.1);
            border-color: #ff9800;
        }
        
        .alert-info {
            background: rgba(33, 150, 243, 0.1);
            border-color: #2196F3;
        }
        
        .alert-danger {
            background: rgba(244, 67, 54, 0.1);
            border-color: #f44336;
        }
        
        .alert-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .alert-title {
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }
        
        .alert-message {
            color: var(--text-secondary);
            margin: 0;
            font-size: 0.9rem;
        }
        
        .alert-action {
            margin-top: 0.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #667eea;
        }
        
        /* 📱 Responsive Design */
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            
            .admin-sidebar.show {
                transform: translateX(0);
            }
            
            .admin-main {
                margin-left: 0;
            }
            
            .admin-topbar {
                padding: 1rem;
            }
            
            .admin-content {
                padding: 1rem;
            }
            
            .admin-search input {
                width: 200px;
            }
            
            .super-stat-card {
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
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
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
        
        .slide-in-right {
            animation: slideInRight 0.6s ease-out;
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
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10;
        }
    </style>
</head>
<body>
    <!-- 👑 Admin Sidebar -->
    <div class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <h3>👑 EMS Admin</h3>
            <p>System Control Center</p>
        </div>
        
        <nav class="admin-nav">
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <div class="admin-nav-item">
                    <a href="dashboard.php" class="admin-nav-link active">
                        <i class="fas fa-tachometer-alt nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="analytics.php" class="admin-nav-link">
                        <i class="fas fa-chart-line nav-icon"></i>
                        <span class="nav-text">Analytics</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Management</div>
                <div class="admin-nav-item">
                    <a href="users.php" class="admin-nav-link">
                        <i class="fas fa-users nav-icon"></i>
                        <span class="nav-text">Users</span>
                        <span class="nav-badge"><?= $adminStats['users']['user'] ?? 0 ?></span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="events.php" class="admin-nav-link">
                        <i class="fas fa-calendar-alt nav-icon"></i>
                        <span class="nav-text">Events</span>
                        <span class="nav-badge"><?= $adminStats['events']['pending'] ?? 0 ?></span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="proposals.php" class="admin-nav-link">
                        <i class="fas fa-file-alt nav-icon"></i>
                        <span class="nav-text">Proposals</span>
                        <span class="nav-badge"><?= $adminStats['recent']['pending_approvals'] ?? 0 ?></span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="tickets.php" class="admin-nav-link">
                        <i class="fas fa-ticket-alt nav-icon"></i>
                        <span class="nav-text">Tickets</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Financial</div>
                <div class="admin-nav-item">
                    <a href="payments.php" class="admin-nav-link">
                        <i class="fas fa-credit-card nav-icon"></i>
                        <span class="nav-text">Payments</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="reports.php" class="admin-nav-link">
                        <i class="fas fa-file-invoice-dollar nav-icon"></i>
                        <span class="nav-text">Reports</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">System</div>
                <div class="admin-nav-item">
                    <a href="settings.php" class="admin-nav-link">
                        <i class="fas fa-cog nav-icon"></i>
                        <span class="nav-text">Settings</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="logs.php" class="admin-nav-link">
                        <i class="fas fa-list-alt nav-icon"></i>
                        <span class="nav-text">System Logs</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="../dashboard/index.php" class="admin-nav-link">
                        <i class="fas fa-arrow-left nav-icon"></i>
                        <span class="nav-text">Back to User</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="../auth/logout.php" class="admin-nav-link">
                        <i class="fas fa-sign-out-alt nav-icon"></i>
                        <span class="nav-text">Logout</span>
                    </a>
                </div>
            </div>
        </nav>
    </div>
    
    <!-- 📱 Main Content Area -->
    <div class="admin-main" id="adminMain">
        <!-- 🎯 Admin Top Bar -->
        <div class="admin-topbar">
            <div class="admin-title-section">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="admin-title">Admin Dashboard</h1>
            </div>
            
            <div class="admin-controls">
                <div class="admin-search">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search users, events, tickets..." id="adminSearch">
                </div>
                
                <div class="admin-notifications">
                    <button class="notification-btn" onclick="toggleNotifications()">
                        <i class="fas fa-bell"></i>
                        <span class="notification-count"><?= count($systemAlerts) ?></span>
                    </button>
                </div>
                
                <div class="admin-user-info">
                    <div class="admin-avatar">
                        <?= strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)) ?>
                    </div>
                    <div class="admin-user-details">
                        <h6><?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></h6>
                        <small>System Administrator</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 📊 Dashboard Content -->
        <div class="admin-content">
            <!-- 🎯 Super Stats Cards -->
            <div class="super-stats">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="super-stat-card primary fade-in-up">
                            <div class="stat-header">
                                <div class="stat-icon primary">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                            <div class="stat-number"><?= array_sum($adminStats['users']) ?></div>
                            <div class="stat-label">Total Users</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i> +<?= $adminStats['recent']['new_users_today'] ?> today
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="super-stat-card success fade-in-up">
                            <div class="stat-header">
                                <div class="stat-icon success">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                            </div>
                            <div class="stat-number"><?= array_sum($adminStats['events']) ?></div>
                            <div class="stat-label">Total Events</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i> +<?= $adminStats['recent']['new_events_today'] ?> today
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="super-stat-card warning fade-in-up">
                            <div class="stat-header">
                                <div class="stat-icon warning">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                            </div>
                            <div class="stat-number">K<?= number_format($adminStats['revenue']['total_revenue'] ?? 0) ?></div>
                            <div class="stat-label">Total Revenue</div>
                            <div class="stat-change neutral">
                                <i class="fas fa-clock"></i> K<?= number_format($adminStats['revenue']['pending_revenue'] ?? 0) ?> pending
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="super-stat-card danger fade-in-up">
                            <div class="stat-header">
                                <div class="stat-icon danger">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                            </div>
                            <div class="stat-number"><?= $adminStats['recent']['pending_approvals'] ?></div>
                            <div class="stat-label">Pending Approvals</div>
                            <div class="stat-change negative">
                                <i class="fas fa-clock"></i> Requires attention
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 📊 Analytics Row -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="admin-card fade-in-up">
                        <div class="admin-card-header">
                            <h3 class="admin-card-title">
                                <i class="fas fa-chart-area"></i>
                                System Analytics
                            </h3>
                            <p class="admin-card-subtitle">Real-time system performance metrics</p>
                        </div>
                        <div class="admin-card-body">
                            <div class="chart-container">
                                <canvas id="systemAnalyticsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="admin-card fade-in-up">
                        <div class="admin-card-header">
                            <h3 class="admin-card-title">
                                <i class="fas fa-pie-chart"></i>
                                User Distribution
                            </h3>
                        </div>
                        <div class="admin-card-body">
                            <div class="mini-chart-container">
                                <canvas id="userDistributionChart"></canvas>
                            </div>
                            <div class="chart-legend mt-3">
                                <div class="legend-item">
                                    <span class="legend-color" style="background: var(--admin-primary);"></span>
                                    <span>Admins (<?= $adminStats['users']['admin'] ?? 0 ?>)</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background: var(--admin-warning);"></span>
                                    <span>Organizers (<?= $adminStats['users']['organizer'] ?? 0 ?>)</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background: var(--admin-info);"></span>
                                    <span>Users (<?= $adminStats['users']['user'] ?? 0 ?>)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 🚨 System Alerts & Recent Activity -->
            <div class="row mb-4">
                <div class="col-lg-6">
                    <div class="admin-card slide-in-right">
                        <div class="admin-card-header">
                            <h3 class="admin-card-title">
                                <i class="fas fa-exclamation-circle"></i>
                                System Alerts
                            </h3>
                            <p class="admin-card-subtitle">Important system notifications</p>
                        </div>
                        <div class="admin-card-body">
                            <?php if (empty($systemAlerts)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-check-circle text-success" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                                    <h5>All Systems Normal</h5>
                                    <p class="text-muted">No alerts at this time</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($systemAlerts as $alert): ?>
                                    <div class="alert-item alert-<?= $alert['type'] ?>">
                                        <div class="alert-header">
                                            <h6 class="alert-title"><?= htmlspecialchars($alert['title']) ?></h6>
                                        </div>
                                        <p class="alert-message"><?= htmlspecialchars($alert['message']) ?></p>
                                        <div class="alert-action"><?= htmlspecialchars($alert['action']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="admin-card slide-in-right">
                        <div class="admin-card-header">
                            <h3 class="admin-card-title">
                                <i class="fas fa-clock"></i>
                                Recent Users
                            </h3>
                            <p class="admin-card-subtitle">Latest user registrations</p>
                        </div>
                        <div class="admin-card-body">
                            <?php if (empty($recentUsers)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-user-plus text-muted" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                                    <h5>No Recent Users</h5>
                                    <p class="text-muted">No new registrations</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Role</th>
                                                <th>Joined</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentUsers as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="user-avatar-small">
                                                            <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                                                        </div>
                                                        <div>
                                                            <strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="role-badge role-<?= $user['role'] ?>">
                                                        <?= ucfirst($user['role']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?= date('M j, Y', strtotime($user['created_at'])) ?></small>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="users.php" class="admin-btn admin-btn-primary">
                                        <i class="fas fa-users"></i> View All Users
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 🎪 Pending Events for Approval -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="admin-card fade-in-up">
                        <div class="admin-card-header">
                            <h3 class="admin-card-title">
                                <i class="fas fa-calendar-times"></i>
                                Pending Event Approvals
                            </h3>
                            <p class="admin-card-subtitle">Events waiting for your approval</p>
                        </div>
                        <div class="admin-card-body">
                            <?php if (empty($pendingEvents)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-calendar-check text-success" style="font-size: 4rem; margin-bottom: 1.5rem;"></i>
                                    <h4>All Caught Up! 🎉</h4>
                                    <p class="text-muted">No events pending approval at this time</p>
                                    <a href="events.php" class="admin-btn admin-btn-primary mt-3">
                                        <i class="fas fa-calendar-alt"></i> View All Events
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($pendingEvents as $event): ?>
                                    <div class="admin-event-card">
                                        <div class="event-header">
                                            <div>
                                                <h4 class="event-title"><?= htmlspecialchars($event['title']) ?></h4>
                                                <p class="text-muted mb-2"><?= htmlspecialchars($event['description']) ?></p>
                                            </div>
                                            <span class="event-status status-pending">Pending</span>
                                        </div>
                                        
                                        <div class="event-meta">
                                            <div class="event-meta-item">
                                                <i class="fas fa-user"></i>
                                                <span>Organizer: <?= htmlspecialchars($event['first_name'] . ' ' . $event['last_name']) ?></span>
                                            </div>
                                            <div class="event-meta-item">
                                                <i class="fas fa-envelope"></i>
                                                <span><?= htmlspecialchars($event['email']) ?></span>
                                            </div>
                                            <div class="event-meta-item">
                                                <i class="fas fa-calendar"></i>
                                                <span><?= date('M j, Y g:i A', strtotime($event['start_datetime'])) ?></span>
                                            </div>
                                            <div class="event-meta-item">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span><?= htmlspecialchars($event['location']) ?></span>
                                            </div>
                                            <div class="event-meta-item">
                                                <i class="fas fa-users"></i>
                                                <span>Max: <?= $event['max_attendees'] ?> attendees</span>
                                            </div>
                                            <div class="event-meta-item">
                                                <i class="fas fa-clock"></i>
                                                <span>Submitted: <?= date('M j, Y', strtotime($event['created_at'])) ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="event-actions">
                                            <a href="event_details.php?id=<?= $event['event_id'] ?>" class="admin-btn admin-btn-primary">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                            <button onclick="approveEvent(<?= $event['event_id'] ?>)" class="admin-btn admin-btn-success">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button onclick="rejectEvent(<?= $event['event_id'] ?>)" class="admin-btn admin-btn-danger">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                            <a href="mailto:<?= htmlspecialchars($event['email']) ?>" class="admin-btn admin-btn-primary">
                                                <i class="fas fa-envelope"></i> Contact Organizer
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <div class="text-center mt-4">
                                    <a href="proposals.php" class="admin-btn admin-btn-primary">
                                        <i class="fas fa-list"></i> View All Proposals
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 📊 Quick Stats Row -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="admin-card text-center">
                        <div class="admin-card-body">
                            <div class="stat-icon info mb-3">
                                <i class="fas fa-ticket-alt"></i>
                            </div>
                            <h3 class="mb-2"><?= $adminStats['recent']['tickets_today'] ?></h3>
                            <p class="text-muted mb-0">Tickets Sold Today</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="admin-card text-center">
                        <div class="admin-card-body">
                            <div class="stat-icon success mb-3">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 class="mb-2"><?= $adminStats['revenue']['completed_payments'] ?? 0 ?></h3>
                            <p class="text-muted mb-0">Completed Payments</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="admin-card text-center">
                        <div class="admin-card-body">
                            <div class="stat-icon warning mb-3">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <h3 class="mb-2"><?= $adminStats['revenue']['pending_payments'] ?? 0 ?></h3>
                            <p class="text-muted mb-0">Pending Payments</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="admin-card text-center">
                        <div class="admin-card-body">
                            <div class="stat-icon primary mb-3">
                                <i class="fas fa-calendar-plus"></i>
                            </div>
                            <h3 class="mb-2"><?= $adminStats['events']['approved'] ?? 0 ?></h3>
                            <p class="text-muted mb-0">Approved Events</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 🎯 Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="admin-card">
                        <div class="admin-card-header">
                            <h3 class="admin-card-title">
                                <i class="fas fa-bolt"></i>
                                Quick Actions
                            </h3>
                            <p class="admin-card-subtitle">Frequently used admin functions</p>
                        </div>
                        <div class="admin-card-body">
                            <div class="row">
                                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                    <a href="users.php?action=create" class="admin-btn admin-btn-primary w-100">
                                        <i class="fas fa-user-plus"></i>
                                        <br>Add User
                                    </a>
                                </div>
                                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                    <a href="events.php?action=create" class="admin-btn admin-btn-success w-100">
                                        <i class="fas fa-calendar-plus"></i>
                                        <br>Create Event
                                    </a>
                                </div>
                                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                    <a href="reports.php" class="admin-btn admin-btn-info w-100">
                                        <i class="fas fa-chart-bar"></i>
                                        <br>Generate Report
                                    </a>
                                </div>
                                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                    <a href="settings.php" class="admin-btn admin-btn-warning w-100">
                                        <i class="fas fa-cog"></i>
                                        <br>System Settings
                                    </a>
                                </div>
                                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                    <a href="backup.php" class="admin-btn admin-btn-danger w-100">
                                        <i class="fas fa-database"></i>
                                        <br>Backup System
                                    </a>
                                </div>
                                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                    <a href="logs.php" class="admin-btn admin-btn-primary w-100">
                                        <i class="fas fa-list-alt"></i>
                                        <br>View Logs
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 🔔 Notification Panel -->
    <div class="notification-panel" id="notificationPanel">
        <div class="notification-header">
            <h5>System Notifications</h5>
            <button onclick="toggleNotifications()" class="btn-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="notification-body">
            <?php if (empty($systemAlerts)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-bell-slash text-muted" style="font-size: 2rem;"></i>
                    <p class="text-muted mt-2">No new notifications</p>
                </div>
            <?php else: ?>
                <?php foreach ($systemAlerts as $alert): ?>
                    <div class="notification-item">
                        <div class="notification-icon <?= $alert['type'] ?>">
                            <i class="fas fa-<?= $alert['type'] === 'warning' ? 'exclamation-triangle' : ($alert['type'] === 'info' ? 'info-circle' : 'exclamation-circle') ?>"></i>
                        </div>
                        <div class="notification-content">
                            <h6><?= htmlspecialchars($alert['title']) ?></h6>
                            <p><?= htmlspecialchars($alert['message']) ?></p>
                            <small class="text-muted"><?= htmlspecialchars($alert['action']) ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 📱 Mobile Menu Overlay -->
    <div class="mobile-overlay" id="mobileOverlay" onclick="toggleSidebar()"></div>
    
    <!-- 🎨 Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    
    <!-- 📊 JavaScript for Charts and Interactions -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 🎯 Global Admin Dashboard Controller
        class AdminDashboard {
            constructor() {
                this.sidebarCollapsed = false;
                this.notificationsPanelOpen = false;
                this.init();
            }
            
            init() {
                this.initCharts();
                this.initSearch();
                this.initRealTimeUpdates();
                this.bindEvents();
            }
            
            // 📊 Initialize Charts
            initCharts() {
                // System Analytics Chart
                const analyticsCtx = document.getElementById('systemAnalyticsChart');
                if (analyticsCtx) {
                    new Chart(analyticsCtx, {
                        type: 'line',
                        data: {
                            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                            datasets: [{
                                label: 'Users',
                                                                data: [12, 19, 25, 32, 28, 35],
                                borderColor: 'rgb(102, 126, 234)',
                                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                                tension: 0.4,
                                fill: true
                            }, {
                                label: 'Events',
                                data: [8, 12, 18, 22, 25, 30],
                                borderColor: 'rgb(76, 175, 80)',
                                backgroundColor: 'rgba(76, 175, 80, 0.1)',
                                tension: 0.4,
                                fill: true
                            }, {
                                label: 'Revenue (K)',
                                data: [5, 8, 12, 15, 18, 22],
                                borderColor: 'rgb(255, 152, 0)',
                                backgroundColor: 'rgba(255, 152, 0, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: 'rgba(0,0,0,0.1)'
                                    }
                                },
                                x: {
                                    grid: {
                                        color: 'rgba(0,0,0,0.1)'
                                    }
                                }
                            }
                        }
                    });
                }
                
                // User Distribution Pie Chart
                const userDistCtx = document.getElementById('userDistributionChart');
                if (userDistCtx) {
                    new Chart(userDistCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Admins', 'Organizers', 'Users'],
                            datasets: [{
                                data: [
                                    <?= $adminStats['users']['admin'] ?? 0 ?>,
                                    <?= $adminStats['users']['organizer'] ?? 0 ?>,
                                    <?= $adminStats['users']['user'] ?? 0 ?>
                                ],
                                backgroundColor: [
                                    'rgb(102, 126, 234)',
                                    'rgb(255, 152, 0)',
                                    'rgb(33, 150, 243)'
                                ],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    });
                }
            }
            
            // 🔍 Initialize Search
            initSearch() {
                const searchInput = document.getElementById('adminSearch');
                if (searchInput) {
                    searchInput.addEventListener('input', (e) => {
                        this.performSearch(e.target.value);
                    });
                }
            }
            
            performSearch(query) {
                if (query.length < 2) return;
                
                // Show loading
                this.showLoading();
                
                // Simulate search (replace with actual AJAX call)
                setTimeout(() => {
                    console.log('Searching for:', query);
                    this.hideLoading();
                    // Implement actual search logic here
                }, 500);
            }
            
            // ⚡ Real-time Updates
            initRealTimeUpdates() {
                // Update stats every 30 seconds
                setInterval(() => {
                    this.updateStats();
                }, 30000);
            }
            
            updateStats() {
                fetch('api/admin_stats.php')
                    .then(response => response.json())
                    .then(data => {
                        // Update stat cards
                        this.updateStatCards(data);
                    })
                    .catch(error => {
                        console.error('Error updating stats:', error);
                    });
            }
            
            updateStatCards(data) {
                // Update total users
                const totalUsersEl = document.querySelector('.super-stat-card.primary .stat-number');
                if (totalUsersEl && data.users) {
                    totalUsersEl.textContent = Object.values(data.users).reduce((a, b) => a + b, 0);
                }
                
                // Update other stats similarly
                // Implementation depends on your API structure
            }
            
            // 🎯 Event Handlers
            bindEvents() {
                // Sidebar toggle
                window.toggleSidebar = () => {
                    const sidebar = document.getElementById('adminSidebar');
                    const main = document.getElementById('adminMain');
                    const overlay = document.getElementById('mobileOverlay');
                    
                    if (window.innerWidth <= 768) {
                        // Mobile behavior
                        sidebar.classList.toggle('show');
                        overlay.style.display = sidebar.classList.contains('show') ? 'block' : 'none';
                    } else {
                        // Desktop behavior
                        sidebar.classList.toggle('collapsed');
                        main.classList.toggle('expanded');
                        this.sidebarCollapsed = !this.sidebarCollapsed;
                    }
                };
                
                // Notifications toggle
                window.toggleNotifications = () => {
                    const panel = document.getElementById('notificationPanel');
                    panel.classList.toggle('active');
                    this.notificationsPanelOpen = !this.notificationsPanelOpen;
                };
                
                // Event approval functions
                window.approveEvent = (eventId) => {
                    this.showConfirmDialog(
                        'Approve Event',
                        'Are you sure you want to approve this event?',
                        () => this.handleEventAction(eventId, 'approve')
                    );
                };
                
                window.rejectEvent = (eventId) => {
                    this.showConfirmDialog(
                        'Reject Event',
                        'Are you sure you want to reject this event? This action cannot be undone.',
                        () => this.handleEventAction(eventId, 'reject')
                    );
                };
                
                // Responsive handling
                window.addEventListener('resize', () => {
                    if (window.innerWidth > 768) {
                        document.getElementById('mobileOverlay').style.display = 'none';
                        document.getElementById('adminSidebar').classList.remove('show');
                    }
                });
            }
            
            // 🎪 Event Actions
            handleEventAction(eventId, action) {
                this.showLoading();
                
                fetch('api/event_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        event_id: eventId,
                        action: action
                    })
                })
                .then(response => response.json())
                .then(data => {
                    this.hideLoading();
                    if (data.success) {
                        this.showSuccessMessage(`Event ${action}d successfully!`);
                        // Remove the event card from DOM
                        const eventCard = document.querySelector(`[data-event-id="${eventId}"]`);
                        if (eventCard) {
                            eventCard.style.animation = 'fadeOut 0.5s ease-out';
                            setTimeout(() => eventCard.remove(), 500);
                        }
                        // Update pending count
                        this.updatePendingCount();
                    } else {
                        this.showErrorMessage(data.message || `Failed to ${action} event`);
                    }
                })
                .catch(error => {
                    this.hideLoading();
                    this.showErrorMessage('Network error occurred');
                    console.error('Error:', error);
                });
            }
            
            // 🎨 UI Helper Methods
            showLoading() {
                document.getElementById('loadingOverlay').style.display = 'flex';
            }
            
            hideLoading() {
                document.getElementById('loadingOverlay').style.display = 'none';
            }
            
            showConfirmDialog(title, message, onConfirm) {
                if (confirm(`${title}\n\n${message}`)) {
                    onConfirm();
                }
            }
            
            showSuccessMessage(message) {
                this.showToast(message, 'success');
            }
            
            showErrorMessage(message) {
                this.showToast(message, 'error');
            }
            
            showToast(message, type = 'info') {
                // Create toast element
                const toast = document.createElement('div');
                toast.className = `admin-toast toast-${type}`;
                toast.innerHTML = `
                    <div class="toast-content">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                        <span>${message}</span>
                    </div>
                    <button class="toast-close" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                
                // Add to page
                document.body.appendChild(toast);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.style.animation = 'slideOutRight 0.3s ease-out';
                        setTimeout(() => toast.remove(), 300);
                    }
                }, 5000);
            }
            
            updatePendingCount() {
                // Update pending approvals count in sidebar and stats
                fetch('api/pending_count.php')
                    .then(response => response.json())
                    .then(data => {
                        const pendingBadges = document.querySelectorAll('.nav-badge');
                        pendingBadges.forEach(badge => {
                            if (badge.closest('.admin-nav-link').href.includes('proposals.php')) {
                                badge.textContent = data.pending_approvals || 0;
                            }
                        });
                        
                        // Update danger stat card
                        const dangerStatNumber = document.querySelector('.super-stat-card.danger .stat-number');
                        if (dangerStatNumber) {
                            dangerStatNumber.textContent = data.pending_approvals || 0;
                        }
                    })
                    .catch(error => console.error('Error updating pending count:', error));
            }
        }
        
        // 🚀 Initialize Dashboard
        document.addEventListener('DOMContentLoaded', () => {
            new AdminDashboard();
        });
        
        // 🎨 Additional CSS for toasts and animations
        const additionalStyles = `
            <style>
                .admin-toast {
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
                
                .toast-success { border-left-color: #4CAF50; }
                .toast-error { border-left-color: #f44336; }
                .toast-info { border-left-color: #2196F3; }
                
                .toast-content {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                
                .toast-success .toast-content i { color: #4CAF50; }
                .toast-error .toast-content i { color: #f44336; }
                .toast-info .toast-content i { color: #2196F3; }
                
                .toast-close {
                    background: none;
                    border: none;
                    color: #999;
                    cursor: pointer;
                    padding: 0.2rem;
                }
                
                .toast-close:hover { color: #666; }
                
                .notification-panel {
                    position: fixed;
                    top: 0;
                    right: -400px;
                    width: 400px;
                    height: 100vh;
                    background: white;
                    box-shadow: -5px 0 15px rgba(0,0,0,0.1);
                    transition: right 0.3s ease;
                    z-index: 1001;
                    overflow-y: auto;
                }
                
                .notification-panel.active {
                    right: 0;
                }
                
                .notification-header {
                    padding: 1.5rem;
                    border-bottom: 1px solid #eee;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: var(--admin-primary);
                    color: white;
                }
                
                .notification-header h5 {
                    margin: 0;
                    font-weight: 600;
                }
                
                .btn-close {
                    background: none;
                    border: none;
                    color: white;
                    cursor: pointer;
                    padding: 0.5rem;
                    border-radius: 5px;
                }
                
                .btn-close:hover {
                    background: rgba(255,255,255,0.1);
                }
                
                .notification-body {
                    padding: 1rem;
                }
                
                .notification-item {
                    display: flex;
                    gap: 1rem;
                    padding: 1rem;
                    border-bottom: 1px solid #eee;
                    transition: background 0.2s ease;
                }
                
                .notification-item:hover {
                    background: #f8f9fa;
                }
                
                .notification-icon {
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    flex-shrink: 0;
                }
                
                .notification-icon.warning { background: #ff9800; }
                .notification-icon.info { background: #2196F3; }
                .notification-icon.danger { background: #f44336; }
                
                .notification-content h6 {
                    margin: 0 0 0.5rem 0;
                    font-weight: 600;
                }
                
                .notification-content p {
                    margin: 0 0 0.5rem 0;
                    font-size: 0.9rem;
                    color: #666;
                }
                
                .mobile-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                                       z-index: 999;
                    display: none;
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
                
                @keyframes fadeOut {
                    from {
                        opacity: 1;
                        transform: scale(1);
                    }
                    to {
                        opacity: 0;
                        transform: scale(0.8);
                    }
                }
                
                .sidebar-toggle {
                    background: none;
                    border: none;
                    font-size: 1.2rem;
                    color: var(--text-primary);
                    cursor: pointer;
                    padding: 0.5rem;
                    border-radius: 5px;
                    margin-right: 1rem;
                    transition: all 0.3s ease;
                }
                
                .sidebar-toggle:hover {
                    background: var(--border-color);
                }
                
                .admin-title-section {
                    display: flex;
                    align-items: center;
                }
                
                .notification-btn {
                    position: relative;
                    background: none;
                    border: none;
                    font-size: 1.2rem;
                    color: var(--text-primary);
                    cursor: pointer;
                    padding: 0.5rem;
                    border-radius: 50%;
                    transition: all 0.3s ease;
                }
                
                .notification-btn:hover {
                    background: var(--border-color);
                }
                
                .notification-count {
                    position: absolute;
                    top: -5px;
                    right: -5px;
                    background: var(--admin-danger);
                    color: white;
                    border-radius: 50%;
                    width: 20px;
                    height: 20px;
                    font-size: 0.7rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 600;
                }
                
                .chart-legend {
                    display: flex;
                    flex-direction: column;
                    gap: 0.5rem;
                }
                
                .legend-item {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    font-size: 0.9rem;
                }
                
                .legend-color {
                    width: 12px;
                    height: 12px;
                    border-radius: 2px;
                }
                
                @media (max-width: 768px) {
                    .notification-panel {
                        width: 100%;
                        right: -100%;
                    }
                    
                    .admin-search input {
                        width: 150px;
                    }
                    
                    .admin-user-details {
                        display: none;
                    }
                }
            </style>
        `;
        
        document.head.insertAdjacentHTML('beforeend', additionalStyles);
    </script>
</body>
</html>


