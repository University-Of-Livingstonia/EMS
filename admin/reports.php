<?php
/**
 * 📊 Admin Reports - EMS
 * Ekwendeni Mighty Campus Event Management System
 * Comprehensive Analytics & Reporting Dashboard! 📈
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

// Handle report generation requests
$reportType = $_GET['type'] ?? 'overview';
$dateRange = $_GET['range'] ?? '30';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Generate comprehensive reports
$reports = [];

try {
    // 📊 Overview Report
    if ($reportType === 'overview' || $reportType === 'all') {
        $reports['overview'] = [
            'total_users' => getSingleStat($conn, "SELECT COUNT(*) as count FROM users WHERE created_at BETWEEN '$startDate' AND '$endDate 23:59:59'"),
            'total_events' => getSingleStat($conn, "SELECT COUNT(*) as count FROM events WHERE created_at BETWEEN '$startDate' AND '$endDate 23:59:59'"),
            'total_tickets' => getSingleStat($conn, "SELECT COUNT(*) as count FROM tickets WHERE created_at BETWEEN '$startDate' AND '$endDate 23:59:59'"),
            'total_revenue' => getSingleStat($conn, "SELECT COALESCE(SUM(price), 0) as count FROM tickets WHERE payment_status = 'completed' AND created_at BETWEEN '$startDate' AND '$endDate 23:59:59'"),
            'pending_revenue' => getSingleStat($conn, "SELECT COALESCE(SUM(price), 0) as count FROM tickets WHERE payment_status = 'pending' AND created_at BETWEEN '$startDate' AND '$endDate 23:59:59'"),
            'active_organizers' => getSingleStat($conn, "SELECT COUNT(DISTINCT organizer_id) as count FROM events WHERE created_at BETWEEN '$startDate' AND '$endDate 23:59:59'")
        ];
    }
    
    // 👥 User Analytics
    if ($reportType === 'users' || $reportType === 'all') {
        // User registration trends
        $stmt = $conn->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as registrations
            FROM users 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date DESC
            LIMIT 30
        ");
        $stmt->bind_param('ss', $startDate, $endDate . ' 23:59:59');
        $stmt->execute();
        $reports['user_trends'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // User role distribution
        $stmt = $conn->prepare("
            SELECT role, COUNT(*) as count
            FROM users 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY role
        ");
        $stmt->bind_param('ss', $startDate, $endDate . ' 23:59:59');
        $stmt->execute();
        $reports['user_roles'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Top active users (by event participation)
        $stmt = $conn->prepare("
            SELECT u.user_id, u.first_name, u.last_name, u.email, 
                   COUNT(t.ticket_id) as events_attended,
                   SUM(t.price) as total_spent
            FROM users u
            JOIN tickets t ON u.user_id = t.user_id
            WHERE t.created_at BETWEEN ? AND ?
            GROUP BY u.user_id
            ORDER BY events_attended DESC, total_spent DESC
            LIMIT 10
        ");
        $stmt->bind_param('ss', $startDate, $endDate . ' 23:59:59');
        $stmt->execute();
        $reports['top_users'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // 🎪 Event Analytics
    if ($reportType === 'events' || $reportType === 'all') {
        // Event performance
        $stmt = $conn->prepare("
            SELECT e.event_id, e.title, e.start_datetime, e.max_attendees,
                   COUNT(t.ticket_id) as tickets_sold,
                   COALESCE(SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END), 0) as revenue,
                   ROUND((COUNT(t.ticket_id) / e.max_attendees) * 100, 2) as attendance_rate,
                   u.first_name, u.last_name
            FROM events e
            LEFT JOIN tickets t ON e.event_id = t.event_id
            JOIN users u ON e.organizer_id = u.user_id
            WHERE e.created_at BETWEEN ? AND ?
            GROUP BY e.event_id
            ORDER BY revenue DESC, tickets_sold DESC
        ");
        $stmt->bind_param('ss', $startDate, $endDate . ' 23:59:59');
        $stmt->execute();
        $reports['event_performance'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Event status distribution
        $stmt = $conn->prepare("
            SELECT status, COUNT(*) as count
            FROM events 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY status
        ");
        $stmt->bind_param('ss', $startDate, $endDate . ' 23:59:59');
        $stmt->execute();
        $reports['event_status'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Monthly event trends
        $stmt = $conn->prepare("
            SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as events_created
            FROM events 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
            LIMIT 12
        ");
        $stmt->bind_param('ss', $startDate, $endDate . ' 23:59:59');
        $stmt->execute();
        $reports['event_trends'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // 💰 Financial Reports
    if ($reportType === 'financial' || $reportType === 'all') {
        // Revenue by payment status
        $stmt = $conn->prepare("
            SELECT payment_status, COUNT(*) as transactions, SUM(price) as total_amount
            FROM tickets 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY payment_status
        ");
        $stmt->bind_param('ss', $startDate, $endDate . ' 23:59:59');
        $stmt->execute();
        $reports['payment_status'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Daily revenue trends
        $stmt = $conn->prepare("
            SELECT DATE(created_at) as date, 
                   COUNT(*) as transactions,
                   SUM(CASE WHEN payment_status = 'completed' THEN price ELSE 0 END) as completed_revenue,
                   SUM(CASE WHEN payment_status = 'pending' THEN price ELSE 0 END) as pending_revenue
            FROM tickets 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date DESC
            LIMIT 30
        ");
        $stmt->bind_param('ss', $startDate, $endDate . ' 23:59:59');
        $stmt->execute();
        $reports['revenue_trends'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Top revenue generating events
        $stmt = $conn->prepare("
            SELECT e.title, e.start_datetime,
                   COUNT(t.ticket_id) as tickets_sold,
                   SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as revenue,
                   AVG(t.price) as avg_ticket_price
            FROM events e
            JOIN tickets t ON e.event_id = t.event_id
            WHERE t.created_at BETWEEN ? AND ?
            GROUP BY e.event_id
            HAVING revenue > 0
            ORDER BY revenue DESC
            LIMIT 10
        ");
        $stmt->bind_param('ss', $startDate, $endDate . ' 23:59:59');
        $stmt->execute();
        $reports['top_revenue_events'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // 🎯 Organizer Performance
    if ($reportType === 'organizers' || $reportType === 'all') {
        $stmt = $conn->prepare("
            SELECT u.user_id, u.first_name, u.last_name, u.email,
                   COUNT(e.event_id) as events_created,
                   COUNT(t.ticket_id) as total_tickets_sold,
                   SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as total_revenue,
                   AVG(CASE WHEN e.max_attendees > 0 THEN (COUNT(t.ticket_id) / e.max_attendees) * 100 ELSE 0 END) as avg_attendance_rate
            FROM users u
            JOIN events e ON u.user_id = e.organizer_id
            LEFT JOIN tickets t ON e.event_id = t.event_id
            WHERE u.role = 'organizer' AND e.created_at BETWEEN ? AND ?
            GROUP BY u.user_id
            ORDER BY total_revenue DESC, events_created DESC
        ");
        $stmt->bind_param('ss', $startDate, $endDate . ' 23:59:59');
        $stmt->execute();
        $reports['organizer_performance'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
} catch (Exception $e) {
    error_log("Reports error: " . $e->getMessage());
    $reports = [];
}

// Export functionality
if (isset($_GET['export'])) {
    $exportType = $_GET['export'];
    $exportData = $reports[$_GET['data']] ?? [];
    
    if ($exportType === 'csv') {
        exportToCSV($exportData, $_GET['data']);
    }
}

function exportToCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        // Write headers
        fputcsv($output, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Reports - EMS</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Chart.js for Analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>
    
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
        
        /* 👑 Admin Sidebar - Reuse from dashboard */
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
        
        /* 📱 Main Content */
        .admin-main {
            margin-left: 300px;
            min-height: 100vh;
            transition: all 0.3s ease;
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
        
        .admin-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-top: 0.5rem;
        }
        
        /* 📊 Reports Content */
        .reports-content {
            padding: 2rem;
        }
        
        /* 🎛️ Report Controls */
        .report-controls {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }
        
        .control-section {
            margin-bottom: 1.5rem;
        }
        
        .control-section:last-child {
            margin-bottom: 0;
        }
        
        .control-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .control-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .control-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .control-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }
        
        .report-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .report-btn-primary {
            background: var(--admin-primary);
            color: white;
        }
        
        .report-btn-success {
            background: var(--admin-success);
            color: white;
        }
        
        .report-btn-info {
            background: var(--admin-info);
            color: white;
        }
        
        .report-btn-warning {
            background: var(--admin-warning);
            color: white;
        }
        
        .report-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .report-btn.active {
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.3);
        }
        
        /* 📊 Report Cards */
        .report-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }
        
        .report-card:hover {
            box-shadow: var(--card-hover-shadow);
            transform: translateY(-5px);
        }
        
        .report-card-header {
            padding: 2rem 2rem 1rem 2rem;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            position: relative;
        }
        
        .report-card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--admin-primary);
        }
        
        .report-card-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }
        
        .report-card-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .report-card-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .export-btn {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            border-radius: 15px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .export-csv {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }
        
        .export-pdf {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid #f44336;
        }
        
        .export-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .report-card-body {
            padding: 2rem;
        }
        
        /* 📈 Charts */
        .chart-container {
            position: relative;
            height: 400px;
            margin: 1rem 0;
        }
        
        .mini-chart-container {
            position: relative;
            height: 250px;
            margin: 1rem 0;
        }
        
        /* 📊 Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 15px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .stat-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #667eea;
        }
        
        /* 📋 Data Tables */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .report-table th,
        .report-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .report-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .report-table tr:hover {
            background: #f8f9fa;
        }
        
        .report-table .number {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .report-table .currency {
            color: #4CAF50;
            font-weight: 600;
        }
        
        .report-table .percentage {
            font-weight: 600;
        }
        
        .percentage.good { color: #4CAF50; }
        .percentage.average { color: #ff9800; }
        .percentage.poor { color: #f44336; }
        
        /* 🎯 Status Badges */
        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-completed {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .status-pending {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
        }
        
        .status-failed {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }
        
        .status-approved {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .status-draft {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
        
        /* 📱 Responsive Design */
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            
            .admin-main {
                margin-left: 0;
            }
            
            .reports-content {
                padding: 1rem;
            }
            
            .control-buttons {
                flex-direction: column;
            }
            
            .report-btn {
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-container {
                height: 300px;
            }
            
            .report-table {
                font-size: 0.9rem;
            }
            
            .report-table th,
            .report-table td {
                padding: 0.7rem 0.5rem;
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
        
        /* 🔄 Loading States */
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
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }
        
        .no-data i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .no-data h4 {
            margin-bottom: 1rem;
            color: var(--text-primary);
        }
        
        /* 📊 Chart Legends */
        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
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
        
        /* 🎯 Quick Filters */
        .quick-filters {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        
        .filter-chip {
            padding: 0.4rem 0.8rem;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            border: 1px solid #667eea;
            border-radius: 20px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .filter-chip:hover,
        .filter-chip.active {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <!-- 👑 Admin Sidebar -->
    <div class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <h3>👑 EMS Admin</h3>
            <p>Reports & Analytics</p>
        </div>
        
        <nav class="admin-nav">
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <div class="admin-nav-item">
                    <a href="dashboard.php" class="admin-nav-link">
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
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="events.php" class="admin-nav-link">
                        <i class="fas fa-calendar-alt nav-icon"></i>
                        <span class="nav-text">Events</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="proposals.php" class="admin-nav-link">
                        <i class="fas fa-file-alt nav-icon"></i>
                        <span class="nav-text">Proposals</span>
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
                    <a href="reports.php" class="admin-nav-link active">
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
                <h1 class="admin-title">📊 Reports & Analytics</h1>
                <p class="admin-subtitle">Comprehensive system insights and data analysis</p>
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
        
        <!-- 📊 Reports Content -->
        <div class="reports-content">
            <!-- 🎛️ Report Controls -->
            <div class="report-controls fade-in-up">
                <form method="GET" action="reports.php" id="reportForm">
                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <div class="control-section">
                                <label class="control-label">Report Type</label>
                                <select name="type" class="control-input" onchange="updateReportType()">
                                    <option value="overview" <?= $reportType === 'overview' ? 'selected' : '' ?>>📊 Overview</option>
                                    <option value="users" <?= $reportType === 'users' ? 'selected' : '' ?>>👥 Users</option>
                                    <option value="events" <?= $reportType === 'events' ? 'selected' : '' ?>>🎪 Events</option>
                                    <option value="financial" <?= $reportType === 'financial' ? 'selected' : '' ?>>💰 Financial</option>
                                    <option value="organizers" <?= $reportType === 'organizers' ? 'selected' : '' ?>>🎯 Organizers</option>
                                    <option value="all" <?= $reportType === 'all' ? 'selected' : '' ?>>📋 All Reports</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-lg-2 col-md-6">
                            <div class="control-section">
                                <label class="control-label">Quick Range</label>
                                <select name="range" class="control-input" onchange="setQuickRange()">
                                    <option value="7" <?= $dateRange === '7' ? 'selected' : '' ?>>Last 7 days</option>
                                    <option value="30" <?= $dateRange === '30' ? 'selected' : '' ?>>Last 30 days</option>
                                    <option value="90" <?= $dateRange === '90' ? 'selected' : '' ?>>Last 90 days</option>
                                    <option value="365" <?= $dateRange === '365' ? 'selected' : '' ?>>Last year</option>
                                    <option value="custom">Custom range</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-lg-2 col-md-6">
                            <div class="control-section">
                                <label class="control-label">Start Date</label>
                                <input type="date" name="start_date" value="<?= $startDate ?>" class="control-input">
                            </div>
                        </div>
                        
                        <div class="col-lg-2 col-md-6">
                            <div class="control-section">
                                <label class="control-label">End Date</label>
                                <input type="date" name="end_date" value="<?= $endDate ?>" class="control-input">
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-12">
                            <div class="control-section">
                                <label class="control-label">&nbsp;</label>
                                <div class="control-buttons">
                                    <button type="submit" class="report-btn report-btn-primary">
                                        <i class="fas fa-chart-bar"></i> Generate Report
                                    </button>
                                    <button type="button" onclick="exportAllData()" class="report-btn report-btn-success">
                                        <i class="fas fa-download"></i> Export All
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- Quick Filters -->
                <div class="quick-filters">
                    <a href="?type=overview&range=7" class="filter-chip <?= $reportType === 'overview' && $dateRange === '7' ? 'active' : '' ?>">
                        📊 Weekly Overview
                    </a>
                    <a href="?type=financial&range=30" class="filter-chip <?= $reportType === 'financial' && $dateRange === '30' ? 'active' : '' ?>">
                        💰 Monthly Revenue
                    </a>
                    <a href="?type=events&range=90" class="filter-chip <?= $reportType === 'events' && $dateRange === '90' ? 'active' : '' ?>">
                        🎪 Quarterly Events
                    </a>
                    <a href="?type=users&range=365" class="filter-chip <?= $reportType === 'users' && $dateRange === '365' ? 'active' : '' ?>">
                        👥 Yearly Users
                    </a>
                </div>
            </div>
            
            <!-- 📊 Overview Report -->
            <?php if ($reportType === 'overview' || $reportType === 'all'): ?>
            <div class="report-card fade-in-up">
                <div class="report-card-header">
                    <h3 class="report-card-title">
                        <i class="fas fa-chart-pie"></i>
                        System Overview
                    </h3>
                    <p class="report-card-subtitle">
                        Comprehensive system metrics from <?= date('M j, Y', strtotime($startDate)) ?> to <?= date('M j, Y', strtotime($endDate)) ?>
                    </p>
                    <div class="report-card-actions">
                        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv', 'data' => 'overview'])) ?>" class="export-btn export-csv">
                            <i class="fas fa-file-csv"></i> CSV
                        </a>
                        <button onclick="printReport('overview')" class="export-btn export-pdf">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
                <div class="report-card-body">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-number"><?= number_format($reports['overview']['total_users'] ?? 0) ?></div>
                            <div class="stat-label">New Users</div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="stat-number"><?= number_format($reports['overview']['total_events'] ?? 0) ?></div>
                            <div class="stat-label">Events Created</div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-ticket-alt"></i>
                            </div>
                            <div class="stat-number"><?= number_format($reports['overview']['total_tickets'] ?? 0) ?></div>
                            <div class="stat-label">Tickets Sold</div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stat-number">K<?= number_format($reports['overview']['total_revenue'] ?? 0) ?>
                            <div class="stat-number">K<?= number_format($reports['overview']['total_revenue'] ?? 0) ?></div>
                            <div class="stat-label">Total Revenue</div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div class="stat-number">K<?= number_format($reports['overview']['pending_revenue'] ?? 0) ?></div>
                            <div class="stat-label">Pending Revenue</div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div class="stat-number"><?= number_format($reports['overview']['active_organizers'] ?? 0) ?></div>
                            <div class="stat-label">Active Organizers</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 👥 User Analytics -->
            <?php if ($reportType === 'users' || $reportType === 'all'): ?>
            <div class="row">
                <div class="col-lg-8">
                    <div class="report-card slide-in-right">
                        <div class="report-card-header">
                            <h3 class="report-card-title">
                                <i class="fas fa-users"></i>
                                User Registration Trends
                            </h3>
                            <p class="report-card-subtitle">Daily user registration patterns</p>
                            <div class="report-card-actions">
                                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv', 'data' => 'user_trends'])) ?>" class="export-btn export-csv">
                                    <i class="fas fa-file-csv"></i> CSV
                                </a>
                            </div>
                        </div>
                        <div class="report-card-body">
                            <?php if (!empty($reports['user_trends'])): ?>
                                <div class="chart-container">
                                    <canvas id="userTrendsChart"></canvas>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-chart-line"></i>
                                    <h4>No User Data</h4>
                                    <p>No user registrations found for the selected period</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="report-card slide-in-right">
                        <div class="report-card-header">
                            <h3 class="report-card-title">
                                <i class="fas fa-pie-chart"></i>
                                User Roles
                            </h3>
                            <p class="report-card-subtitle">Distribution by role</p>
                        </div>
                        <div class="report-card-body">
                            <?php if (!empty($reports['user_roles'])): ?>
                                <div class="mini-chart-container">
                                    <canvas id="userRolesChart"></canvas>
                                </div>
                                <div class="chart-legend">
                                    <?php foreach ($reports['user_roles'] as $role): ?>
                                        <div class="legend-item">
                                            <span class="legend-color" style="background: <?= $role['role'] === 'admin' ? '#667eea' : ($role['role'] === 'organizer' ? '#ff9800' : '#2196F3') ?>;"></span>
                                            <span><?= ucfirst($role['role']) ?>s (<?= $role['count'] ?>)</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-user-friends"></i>
                                    <h4>No Role Data</h4>
                                    <p>No user role data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Active Users -->
            <?php if (!empty($reports['top_users'])): ?>
            <div class="report-card fade-in-up">
                <div class="report-card-header">
                    <h3 class="report-card-title">
                        <i class="fas fa-trophy"></i>
                        Top Active Users
                    </h3>
                    <p class="report-card-subtitle">Most engaged users by event participation</p>
                    <div class="report-card-actions">
                        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv', 'data' => 'top_users'])) ?>" class="export-btn export-csv">
                            <i class="fas fa-file-csv"></i> CSV
                        </a>
                    </div>
                </div>
                <div class="report-card-body">
                    <div class="table-responsive">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>User</th>
                                    <th>Events Attended</th>
                                    <th>Total Spent</th>
                                    <th>Avg per Event</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports['top_users'] as $index => $user): ?>
                                <tr>
                                    <td>
                                        <span class="number">#<?= $index + 1 ?></span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                                    </td>
                                    <td>
                                        <span class="number"><?= $user['events_attended'] ?></span>
                                    </td>
                                    <td>
                                        <span class="currency">K<?= number_format($user['total_spent']) ?></span>
                                    </td>
                                    <td>
                                        <span class="currency">K<?= $user['events_attended'] > 0 ? number_format($user['total_spent'] / $user['events_attended']) : '0' ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <!-- 🎪 Event Analytics -->
            <?php if ($reportType === 'events' || $reportType === 'all'): ?>
            <div class="row">
                <div class="col-lg-6">
                    <div class="report-card slide-in-right">
                        <div class="report-card-header">
                            <h3 class="report-card-title">
                                <i class="fas fa-chart-bar"></i>
                                Event Status Distribution
                            </h3>
                            <p class="report-card-subtitle">Events by approval status</p>
                        </div>
                        <div class="report-card-body">
                            <?php if (!empty($reports['event_status'])): ?>
                                <div class="mini-chart-container">
                                    <canvas id="eventStatusChart"></canvas>
                                </div>
                                <div class="chart-legend">
                                    <?php foreach ($reports['event_status'] as $status): ?>
                                        <div class="legend-item">
                                            <span class="legend-color" style="background: <?= $status['status'] === 'approved' ? '#4CAF50' : ($status['status'] === 'pending' ? '#ff9800' : '#6c757d') ?>;"></span>
                                            <span><?= ucfirst($status['status']) ?> (<?= $status['count'] ?>)</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-calendar-times"></i>
                                    <h4>No Event Data</h4>
                                    <p>No events found for the selected period</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="report-card slide-in-right">
                        <div class="report-card-header">
                            <h3 class="report-card-title">
                                <i class="fas fa-chart-line"></i>
                                Monthly Event Trends
                            </h3>
                            <p class="report-card-subtitle">Event creation over time</p>
                        </div>
                        <div class="report-card-body">
                            <?php if (!empty($reports['event_trends'])): ?>
                                <div class="mini-chart-container">
                                    <canvas id="eventTrendsChart"></canvas>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-chart-line"></i>
                                    <h4>No Trend Data</h4>
                                    <p>Insufficient data for trend analysis</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Event Performance Table -->
            <?php if (!empty($reports['event_performance'])): ?>
            <div class="report-card fade-in-up">
                <div class="report-card-header">
                    <h3 class="report-card-title">
                        <i class="fas fa-star"></i>
                        Event Performance Analysis
                    </h3>
                    <p class="report-card-subtitle">Detailed performance metrics for all events</p>
                    <div class="report-card-actions">
                        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv', 'data' => 'event_performance'])) ?>" class="export-btn export-csv">
                            <i class="fas fa-file-csv"></i> CSV
                        </a>
                    </div>
                </div>
                <div class="report-card-body">
                    <div class="table-responsive">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Organizer</th>
                                    <th>Date</th>
                                    <th>Capacity</th>
                                    <th>Tickets Sold</th>
                                    <th>Attendance Rate</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports['event_performance'] as $event): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($event['title']) ?></strong>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($event['first_name'] . ' ' . $event['last_name']) ?>
                                    </td>
                                    <td>
                                        <?= date('M j, Y', strtotime($event['start_datetime'])) ?>
                                    </td>
                                    <td>
                                        <span class="number"><?= $event['max_attendees'] ?></span>
                                    </td>
                                    <td>
                                        <span class="number"><?= $event['tickets_sold'] ?></span>
                                    </td>
                                    <td>
                                        <span class="percentage <?= $event['attendance_rate'] >= 80 ? 'good' : ($event['attendance_rate'] >= 50 ? 'average' : 'poor') ?>">
                                            <?= number_format($event['attendance_rate'], 1) ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="currency">K<?= number_format($event['revenue']) ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <!-- 💰 Financial Reports -->
            <?php if ($reportType === 'financial' || $reportType === 'all'): ?>
            <div class="row">
                <div class="col-lg-8">
                    <div class="report-card slide-in-right">
                        <div class="report-card-header">
                            <h3 class="report-card-title">
                                <i class="fas fa-chart-area"></i>
                                Revenue Trends
                            </h3>
                            <p class="report-card-subtitle">Daily revenue and transaction patterns</p>
                            <div class="report-card-actions">
                                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv', 'data' => 'revenue_trends'])) ?>" class="export-btn export-csv">
                                    <i class="fas fa-file-csv"></i> CSV
                                </a>
                            </div>
                        </div>
                        <div class="report-card-body">
                            <?php if (!empty($reports['revenue_trends'])): ?>
                                <div class="chart-container">
                                    <canvas id="revenueTrendsChart"></canvas>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-chart-area"></i>
                                    <h4>No Revenue Data</h4>
                                    <p>No financial transactions found for the selected period</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="report-card slide-in-right">
                        <div class="report-card-header">
                            <h3 class="report-card-title">
                                <i class="fas fa-credit-card"></i>
                                Payment Status
                            </h3>
                            <p class="report-card-subtitle">Transaction breakdown</p>
                        </div>
                        <div class="report-card-body">
                            <?php if (!empty($reports['payment_status'])): ?>
                                <div class="mini-chart-container">
                                    <canvas id="paymentStatusChart"></canvas>
                                </div>
                                <div class="chart-legend">
                                                                      <?php foreach ($reports['payment_status'] as $status): ?>
                                        <div class="legend-item">
                                            <span class="legend-color" style="background: <?= $status['payment_status'] === 'completed' ? '#4CAF50' : ($status['payment_status'] === 'pending' ? '#ff9800' : '#f44336') ?>;"></span>
                                            <span><?= ucfirst($status['payment_status']) ?> (K<?= number_format($status['total_amount']) ?>)</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-credit-card"></i>
                                    <h4>No Payment Data</h4>
                                    <p>No payment transactions available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Revenue Events -->
            <?php if (!empty($reports['top_revenue_events'])): ?>
            <div class="report-card fade-in-up">
                <div class="report-card-header">
                    <h3 class="report-card-title">
                        <i class="fas fa-money-bill-wave"></i>
                        Top Revenue Generating Events
                    </h3>
                    <p class="report-card-subtitle">Highest earning events in the selected period</p>
                    <div class="report-card-actions">
                        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv', 'data' => 'top_revenue_events'])) ?>" class="export-btn export-csv">
                            <i class="fas fa-file-csv"></i> CSV
                        </a>
                    </div>
                </div>
                <div class="report-card-body">
                    <div class="table-responsive">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Event</th>
                                    <th>Date</th>
                                    <th>Tickets Sold</th>
                                    <th>Avg Ticket Price</th>
                                    <th>Total Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports['top_revenue_events'] as $index => $event): ?>
                                <tr>
                                    <td>
                                        <span class="number">#<?= $index + 1 ?></span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($event['title']) ?></strong>
                                    </td>
                                    <td>
                                        <?= date('M j, Y', strtotime($event['start_datetime'])) ?>
                                    </td>
                                    <td>
                                        <span class="number"><?= $event['tickets_sold'] ?></span>
                                    </td>
                                    <td>
                                        <span class="currency">K<?= number_format($event['avg_ticket_price']) ?></span>
                                    </td>
                                    <td>
                                        <span class="currency">K<?= number_format($event['revenue']) ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <!-- 🎯 Organizer Performance -->
            <?php if ($reportType === 'organizers' || $reportType === 'all'): ?>
            <?php if (!empty($reports['organizer_performance'])): ?>
            <div class="report-card fade-in-up">
                <div class="report-card-header">
                    <h3 class="report-card-title">
                        <i class="fas fa-user-tie"></i>
                        Organizer Performance Analysis
                    </h3>
                    <p class="report-card-subtitle">Performance metrics for event organizers</p>
                    <div class="report-card-actions">
                        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv', 'data' => 'organizer_performance'])) ?>" class="export-btn export-csv">
                            <i class="fas fa-file-csv"></i> CSV
                        </a>
                    </div>
                </div>
                <div class="report-card-body">
                    <div class="table-responsive">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Organizer</th>
                                    <th>Events Created</th>
                                    <th>Total Tickets Sold</th>
                                    <th>Total Revenue</th>
                                    <th>Avg Attendance Rate</th>
                                    <th>Performance Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports['organizer_performance'] as $organizer): ?>
                                <?php 
                                    $performanceScore = ($organizer['total_revenue'] / 1000) + ($organizer['avg_attendance_rate'] / 10) + ($organizer['events_created'] * 5);
                                    $scoreClass = $performanceScore >= 50 ? 'good' : ($performanceScore >= 25 ? 'average' : 'poor');
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($organizer['first_name'] . ' ' . $organizer['last_name']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($organizer['email']) ?></small>
                                    </td>
                                    <td>
                                        <span class="number"><?= $organizer['events_created'] ?></span>
                                    </td>
                                    <td>
                                        <span class="number"><?= $organizer['total_tickets_sold'] ?></span>
                                    </td>
                                    <td>
                                        <span class="currency">K<?= number_format($organizer['total_revenue']) ?></span>
                                    </td>
                                    <td>
                                        <span class="percentage <?= $organizer['avg_attendance_rate'] >= 70 ? 'good' : ($organizer['avg_attendance_rate'] >= 50 ? 'average' : 'poor') ?>">
                                            <?= number_format($organizer['avg_attendance_rate'], 1) ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="percentage <?= $scoreClass ?>">
                                            <?= number_format($performanceScore, 1) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <!-- 📊 Summary Cards -->
            <div class="row">
                <div class="col-lg-4">
                    <div class="report-card fade-in-up">
                        <div class="report-card-header">
                            <h3 class="report-card-title">
                                <i class="fas fa-calendar-week"></i>
                                This Week
                            </h3>
                        </div>
                        <div class="report-card-body">
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <div class="stat-number"><?= getSingleStat($conn, "SELECT COUNT(*) as count FROM events WHERE WEEK(created_at) = WEEK(NOW()) AND YEAR(created_at) = YEAR(NOW())") ?></div>
                                    <div class="stat-label">New Events</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?= getSingleStat($conn, "SELECT COUNT(*) as count FROM users WHERE WEEK(created_at) = WEEK(NOW()) AND YEAR(created_at) = YEAR(NOW())") ?></div>
                                    <div class="stat-label">New Users</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="report-card fade-in-up">
                        <div class="report-card-header">
                            <h3 class="report-card-title">
                                <i class="fas fa-calendar-alt"></i>
                                This Month
                            </h3>
                        </div>
                        <div class="report-card-body">
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <div class="stat-number">K<?= number_format(getSingleStat($conn, "SELECT COALESCE(SUM(price), 0) as count FROM tickets WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND payment_status = 'completed'")) ?></div>
                                    <div class="stat-label">Revenue</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?= getSingleStat($conn, "SELECT COUNT(*) as count FROM tickets WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())") ?></div>
                                    <div class="stat-label">Tickets</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="report-card fade-in-up">
                        <div class="report-card-header">
                            <h3 class="report-card-title">
                                <i class="fas fa-chart-line"></i>
                                Growth Rate
                            </h3>
                        </div>
                        <div class="report-card-body">
                            <div class="stats-grid">
                                <?php 
                                    $thisMonth = getSingleStat($conn, "SELECT COUNT(*) as count FROM users WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
                                    $lastMonth = getSingleStat($conn, "SELECT COUNT(*) as count FROM users WHERE MONTH(created_at) = MONTH(NOW()) - 1 AND YEAR(created_at) = YEAR(NOW())");
                                    $growthRate = $lastMonth > 0 ? (($thisMonth - $lastMonth) / $lastMonth) * 100 : 0;
                                ?>
                                <div class="stat-item">
                                    <div class="stat-number <?= $growthRate >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= $growthRate >= 0 ? '+' : '' ?><?= number_format($growthRate, 1) ?>%
                                    </div>
                                    <div class="stat-label">User Growth</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?= number_format((getSingleStat($conn, "SELECT COUNT(*) as count FROM tickets WHERE payment_status = 'completed'") / max(1, getSingleStat($conn, "SELECT COUNT(*) as count FROM tickets"))) * 100, 1) ?>%</div>
                                    <div class="stat-label">Payment Success</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 📱 JavaScript for Charts and Interactions -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 🎯 Reports Dashboard Controller
        class ReportsDashboard {
            constructor() {
                this.init();
            }
            
            init() {
                this.initCharts();
                this.bindEvents();
            }
            
            // 📊 Initialize Charts
            initCharts() {
                // User Trends Chart
                const userTrendsCtx = document.getElementById('userTrendsChart');
                if (userTrendsCtx) {
                    const userTrendsData = <?= json_encode($reports['user_trends'] ?? []) ?>;
                    new Chart(userTrendsCtx, {
                        type: 'line',
                        data: {
                            labels: userTrendsData.map(item => new Date(item.date).toLocaleDateString()),
                            datasets: [{
                                label: 'New Users',
                                data: userTrendsData.map(item => item.registrations),
                                borderColor: 'rgb(102, 126, 234)',
                                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
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
                
                // User Roles Chart
                const userRolesCtx = document.getElementById('userRolesChart');
                if (userRolesCtx) {
                    const userRolesData = <?= json_encode($reports['user_roles'] ?? []) ?>;
                    new Chart(userRolesCtx, {
                        type: 'doughnut',
                        data: {
                            labels: userRolesData.map(item => item.role.charAt(0).toUpperCase() + item.role.slice(1) + 's'),
                            datasets: [{
                                data: userRolesData.map(item => item.count),
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
                
                               // Event Status Chart
                const eventStatusCtx = document.getElementById('eventStatusChart');
                if (eventStatusCtx) {
                    const eventStatusData = <?= json_encode($reports['event_status'] ?? []) ?>;
                    new Chart(eventStatusCtx, {
                        type: 'bar',
                        data: {
                            labels: eventStatusData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
                            datasets: [{
                                label: 'Events',
                                data: eventStatusData.map(item => item.count),
                                backgroundColor: eventStatusData.map(item => 
                                    item.status === 'approved' ? '#4CAF50' : 
                                    item.status === 'pending' ? '#ff9800' : '#6c757d'
                                ),
                                borderRadius: 8
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
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
                                        display: false
                                    }
                                }
                            }
                        }
                    });
                }
                
                // Event Trends Chart
                const eventTrendsCtx = document.getElementById('eventTrendsChart');
                if (eventTrendsCtx) {
                    const eventTrendsData = <?= json_encode($reports['event_trends'] ?? []) ?>;
                    new Chart(eventTrendsCtx, {
                        type: 'line',
                        data: {
                            labels: eventTrendsData.map(item => item.month),
                            datasets: [{
                                label: 'Events Created',
                                data: eventTrendsData.map(item => item.count),
                                borderColor: 'rgb(76, 175, 80)',
                                backgroundColor: 'rgba(76, 175, 80, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
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
                
                // Revenue Trends Chart
                const revenueTrendsCtx = document.getElementById('revenueTrendsChart');
                if (revenueTrendsCtx) {
                    const revenueTrendsData = <?= json_encode($reports['revenue_trends'] ?? []) ?>;
                    new Chart(revenueTrendsCtx, {
                        type: 'area',
                        data: {
                            labels: revenueTrendsData.map(item => new Date(item.date).toLocaleDateString()),
                            datasets: [{
                                label: 'Revenue (K)',
                                data: revenueTrendsData.map(item => item.revenue),
                                borderColor: 'rgb(255, 152, 0)',
                                backgroundColor: 'rgba(255, 152, 0, 0.1)',
                                tension: 0.4,
                                fill: true
                            }, {
                                label: 'Transactions',
                                data: revenueTrendsData.map(item => item.transactions),
                                borderColor: 'rgb(33, 150, 243)',
                                backgroundColor: 'rgba(33, 150, 243, 0.1)',
                                tension: 0.4,
                                fill: true,
                                yAxisID: 'y1'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top'
                                }
                            },
                            scales: {
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    beginAtZero: true,
                                    grid: {
                                        color: 'rgba(0,0,0,0.1)'
                                    }
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    beginAtZero: true,
                                    grid: {
                                        drawOnChartArea: false,
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
                
                // Payment Status Chart
                const paymentStatusCtx = document.getElementById('paymentStatusChart');
                if (paymentStatusCtx) {
                    const paymentStatusData = <?= json_encode($reports['payment_status'] ?? []) ?>;
                    new Chart(paymentStatusCtx, {
                        type: 'doughnut',
                        data: {
                            labels: paymentStatusData.map(item => item.payment_status.charAt(0).toUpperCase() + item.payment_status.slice(1)),
                            datasets: [{
                                data: paymentStatusData.map(item => item.total_amount),
                                backgroundColor: paymentStatusData.map(item => 
                                    item.payment_status === 'completed' ? '#4CAF50' : 
                                    item.payment_status === 'pending' ? '#ff9800' : '#f44336'
                                ),
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
            
            // 🎯 Event Handlers
            bindEvents() {
                // Quick range selector
                window.setQuickRange = () => {
                    const rangeSelect = document.querySelector('select[name="range"]');
                    const startDateInput = document.querySelector('input[name="start_date"]');
                    const endDateInput = document.querySelector('input[name="end_date"]');
                    
                    if (rangeSelect.value !== 'custom') {
                        const days = parseInt(rangeSelect.value);
                        const endDate = new Date();
                        const startDate = new Date();
                        startDate.setDate(endDate.getDate() - days);
                        
                        startDateInput.value = startDate.toISOString().split('T')[0];
                        endDateInput.value = endDate.toISOString().split('T')[0];
                    }
                };
                
                // Report type updater
                window.updateReportType = () => {
                    // Auto-submit form when report type changes
                    document.getElementById('reportForm').submit();
                };
                
                // Export functions
                window.exportAllData = () => {
                    const params = new URLSearchParams(window.location.search);
                    params.set('export', 'all');
                    window.location.href = '?' + params.toString();
                };
                
                window.printReport = (reportType) => {
                    const printWindow = window.open('', '_blank');
                    const reportCard = document.querySelector(`[data-report="${reportType}"]`) || 
                                     document.querySelector('.report-card');
                    
                    printWindow.document.write(`
                        <html>
                            <head>
                                <title>EMS Report - ${reportType}</title>
                                <style>
                                    body { font-family: Arial, sans-serif; margin: 20px; }
                                    .report-header { text-align: center; margin-bottom: 30px; }
                                    .report-title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
                                    .report-date { color: #666; }
                                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                                    th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                                    th { background-color: #f5f5f5; font-weight: bold; }
                                    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
                                    .stat-item { text-align: center; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                                    .stat-number { font-size: 32px; font-weight: bold; margin-bottom: 10px; }
                                    .stat-label { color: #666; text-transform: uppercase; font-size: 12px; }
                                    @media print { body { margin: 0; } }
                                </style>
                            </head>
                            <body>
                                <div class="report-header">
                                    <div class="report-title">EMS System Report</div>
                                    <div class="report-date">Generated on ${new Date().toLocaleDateString()}</div>
                                </div>
                                ${reportCard ? reportCard.innerHTML : 'Report content not found'}
                            </body>
                        </html>
                    `);
                    
                    printWindow.document.close();
                    printWindow.print();
                };
            }
        }
        
        // 🚀 Initialize Reports Dashboard
        document.addEventListener('DOMContentLoaded', () => {
            new ReportsDashboard();
        });
        
        // 📱 Responsive sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const main = document.getElementById('adminMain');
            
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                main.classList.toggle('expanded');
            }
        }
        
        // Auto-refresh data every 5 minutes
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 300000);
    </script>
</body>
</html>
