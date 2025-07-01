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