<?php
/**
 * 📊 Admin Analytics - EMS
 * Ekwendeni Mighty Campus Event Management System
 * Deep Dive Analytics Dashboard! 📈
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

// Get date range from query parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
$period = $_GET['period'] ?? 'month';

// Comprehensive Analytics Data
$analytics = [];

try {
    // User Growth Analytics
    $stmt = $conn->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as new_users,
            SUM(COUNT(*)) OVER (ORDER BY DATE(created_at)) as cumulative_users
        FROM users 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $analytics['user_growth'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Event Performance Analytics
    $stmt = $conn->prepare("
        SELECT 
            e.event_id,
            e.title,
            e.category,
            e.start_datetime,
            e.max_attendees,
            e.ticket_price,
            COUNT(t.ticket_id) as total_registrations,
            SUM(CASE WHEN t.payment_status = 'completed' THEN 1 ELSE 0 END) as paid_registrations,
            SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as revenue,
            (COUNT(t.ticket_id) / e.max_attendees * 100) as capacity_utilization,
            (SUM(CASE WHEN t.payment_status = 'completed' THEN 1 ELSE 0 END) / COUNT(t.ticket_id) * 100) as conversion_rate
        FROM events e
        LEFT JOIN tickets t ON e.event_id = t.event_id
        WHERE e.start_datetime BETWEEN ? AND ?
        GROUP BY e.event_id
        ORDER BY revenue DESC
        LIMIT 20
    ");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $analytics['event_performance'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Revenue Analytics
    $stmt = $conn->prepare("
        SELECT 
            DATE(t.created_at) as date,
            SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as daily_revenue,
            COUNT(CASE WHEN t.payment_status = 'completed' THEN 1 END) as completed_transactions,
            COUNT(CASE WHEN t.payment_status = 'pending' THEN 1 END) as pending_transactions,
            AVG(CASE WHEN t.payment_status = 'completed' THEN t.price END) as avg_ticket_price
        FROM tickets t
        WHERE t.created_at BETWEEN ? AND ?
        GROUP BY DATE(t.created_at)
        ORDER BY date
    ");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $analytics['revenue_trend'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Category Performance
    $stmt = $conn->prepare("
        SELECT 
            e.category,
            COUNT(DISTINCT e.event_id) as total_events,
            COUNT(t.ticket_id) as total_tickets,
            SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as category_revenue,
            AVG(COUNT(t.ticket_id)) OVER () as avg_tickets_per_category
        FROM events e
        LEFT JOIN tickets t ON e.event_id = t.event_id
        WHERE e.created_at BETWEEN ? AND ?
        GROUP BY e.category
        ORDER BY category_revenue DESC
    ");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $analytics['category_performance'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // User Engagement Analytics
    $stmt = $conn->prepare("
        SELECT 
            u.role,
            COUNT(DISTINCT u.user_id) as total_users,
            COUNT(DISTINCT t.ticket_id) as total_tickets,
            AVG(user_tickets.ticket_count) as avg_tickets_per_user,
            MAX(user_tickets.ticket_count) as max_tickets_per_user
        FROM users u
        LEFT JOIN tickets t ON u.user_id = t.user_id
        LEFT JOIN (
            SELECT user_id, COUNT(*) as ticket_count
            FROM tickets
            WHERE created_at BETWEEN ? AND ?
            GROUP BY user_id
        ) user_tickets ON u.user_id = user_tickets.user_id
        WHERE u.created_at BETWEEN ? AND ?
        GROUP BY u.role
    ");
    $stmt->bind_param("ssss", $startDate, $endDate, $startDate, $endDate);
    $stmt->execute();
    $analytics['user_engagement'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Top Organizers
    $stmt = $conn->prepare("
        SELECT 
            u.user_id,
            u.first_name,
            u.last_name,
            u.email,
            COUNT(DISTINCT e.event_id) as total_events,
            COUNT(t.ticket_id) as total_tickets_sold,
            SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as total_revenue,
            AVG(event_ratings.rating) as avg_rating
        FROM users u
        JOIN events e ON u.user_id = e.organizer_id
        LEFT JOIN tickets t ON e.event_id = t.event_id
        LEFT JOIN (
            SELECT event_id, AVG(rating) as rating
            FROM event_ratings
            GROUP BY event_id
        ) event_ratings ON e.event_id = event_ratings.event_id
        WHERE u.role = 'organizer' AND e.created_at BETWEEN ? AND ?
        GROUP BY u.user_id
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $analytics['top_organizers'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // System Health Metrics
    $analytics['system_health'] = [
        'total_users' => getSingleStat($conn, "SELECT COUNT(*) as count FROM users"),
        'active_events' => getSingleStat($conn, "SELECT COUNT(*) as count FROM events WHERE status = 'approved' AND start_datetime > NOW()"),
        'total_revenue' => getSingleStat($conn, "SELECT SUM(price) as total FROM tickets WHERE payment_status = 'completed'"),
        'pending_approvals' => getSingleStat($conn, "SELECT COUNT(*) as count FROM events WHERE status = 'pending'"),
        'failed_payments' => getSingleStat($conn, "SELECT COUNT(*) as count FROM tickets WHERE payment_status = 'failed'"),
        'avg_event_capacity' => getSingleStat($conn, "SELECT AVG(max_attendees) as avg FROM events"),
        'conversion_rate' => 0 // Will calculate below
    ];
    
    // Calculate overall conversion rate
    $totalTickets = getSingleStat($conn, "SELECT COUNT(*) as count FROM tickets");
    $paidTickets = getSingleStat($conn, "SELECT COUNT(*) as count FROM tickets WHERE payment_status = 'completed'");
    $analytics['system_health']['conversion_rate'] = $totalTickets > 0 ? ($paidTickets / $totalTickets * 100) : 0;
    
} catch (Exception $e) {
    error_log("Analytics error: " . $e->getMessage());
    $analytics = [
        'user_growth' => [],
        'event_performance' => [],
        'revenue_trend' => [],
        'category_performance' => [],
        'user_engagement' => [],
        'top_organizers' => [],
        'system_health' => []
    ];
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Admin | EMS</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    
    <style>
        :root {
            --admin-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --admin-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --admin-success: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            --admin-warning: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            --admin-danger: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            --admin-info: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            --sidebar-bg: #1a1a2e;
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
            margin-left: 300px;
            transition: margin-left 0.3s ease;
        }
        
        .main-content {
            padding: 2rem;
            min-height: 100vh;
        }
        
        /* Page Header */
        .analytics-header {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .analytics-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--admin-primary);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            background: var(--admin-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }
        
        .page-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin-top: 0.5rem;
        }
        
        /* Date Range Picker */
        .date-range-picker {
            display: flex;
            gap: 1rem;
            align-items: center;
            background: var(--content-bg);
            padding: 1rem;
            border-radius: 15px;
            flex-wrap: wrap;
        }
        
        .date-input {
            padding: 0.5rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .date-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .period-selector {
            display: flex;
            gap: 0.5rem;
        }
        
        .period-btn {
            padding: 0.5rem 1rem;
            border: 2px solid var(--border-color);
            background: white;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .period-btn.active {
            background: var(--admin-primary);
            color: white;
            border-color: transparent;
        }
        
        .period-btn:hover {
            transform: translateY(-2px);
        }
        
        /* Analytics Cards */
        .analytics-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .analytics-card:hover {
            box-shadow: var(--card-hover-shadow);
            transform: translateY(-5px);
        }
        
        .card-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }
        
        .card-title {
            font-size: 1.3rem;
                      font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }
        
        .card-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        /* Chart Containers */
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
        
        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .kpi-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .kpi-card.primary::before { background: var(--admin-primary); }
        .kpi-card.success::before { background: var(--admin-success); }
        .kpi-card.warning::before { background: var(--admin-warning); }
        .kpi-card.danger::before { background: var(--admin-danger); }
        .kpi-card.info::before { background: var(--admin-info); }
        
        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-hover-shadow);
        }
        
        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .kpi-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .kpi-icon.primary { background: var(--admin-primary); }
        .kpi-icon.success { background: var(--admin-success); }
        .kpi-icon.warning { background: var(--admin-warning); }
        .kpi-icon.danger { background: var(--admin-danger); }
        .kpi-icon.info { background: var(--admin-info); }
        
        .kpi-value {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .kpi-label {
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .kpi-trend {
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 0.5rem;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            display: inline-block;
        }
        
        .trend-up {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .trend-down {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }
        
        .trend-neutral {
            background: rgba(108, 117, 125, 0.1);
            color: var(--text-secondary);
        }
        
        /* Data Tables */
        .analytics-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .analytics-table th,
        .analytics-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .analytics-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .analytics-table tr:hover {
            background: #f8f9fa;
        }
        
        .performance-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .performance-fill {
            height: 100%;
            background: var(--admin-success);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        /* Export Controls */
        .export-controls {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .export-btn {
            padding: 0.7rem 1.5rem;
            border: 2px solid var(--border-color);
            background: white;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .export-btn:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-2px);
            text-decoration: none;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                margin-left: 0;
            }
            
            .main-content {
                padding: 1rem;
            }
            
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .date-range-picker {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .kpi-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-container {
                height: 300px;
            }
        }
        
        /* Loading States */
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
        
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
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
    <!-- Include Admin Navigation -->
    <?php include 'includes/navigation.php'; ?>
    
    <div class="main-content">
        <!-- Analytics Header -->
        <div class="analytics-header">
            <div class="header-content">
                <div>
                    <h1 class="page-title">📊 Analytics Dashboard</h1>
                    <p class="page-subtitle">Comprehensive insights into your event management system</p>
                </div>
                
                <div class="date-range-picker">
                    <div class="period-selector">
                        <button class="period-btn <?= $period === 'week' ? 'active' : '' ?>" onclick="setPeriod('week')">Week</button>
                        <button class="period-btn <?= $period === 'month' ? 'active' : '' ?>" onclick="setPeriod('month')">Month</button>
                        <button class="period-btn <?= $period === 'quarter' ? 'active' : '' ?>" onclick="setPeriod('quarter')">Quarter</button>
                        <button class="period-btn <?= $period === 'year' ? 'active' : '' ?>" onclick="setPeriod('year')">Year</button>
                    </div>
                    <input type="date" class="date-input" id="startDate" value="<?= $startDate ?>">
                    <span>to</span>
                    <input type="date" class="date-input" id="endDate" value="<?= $endDate ?>">
                    <button class="period-btn" onclick="applyDateRange()">Apply</button>
                </div>
            </div>
        </div>
        
        <!-- Export Controls -->
        <div class="export-controls">
            <a href="?export=pdf&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="export-btn">
                <i class="fas fa-file-pdf"></i> Export PDF
            </a>
            <a href="?export=excel&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="export-btn">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
            <button class="export-btn" onclick="printAnalytics()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
        
        <!-- KPI Overview -->
        <div class="kpi-grid">
            <div class="kpi-card primary">
                <div class="kpi-header">
                    <div class="kpi-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="kpi-value"><?= number_format($analytics['system_health']['total_users']) ?></div>
                <div class="kpi-label">Total Users</div>
                <div class="kpi-trend trend-up">
                    <i class="fas fa-arrow-up"></i> +12% this month
                </div>
            </div>
            
            <div class="kpi-card success">
                <div class="kpi-header">
                    <div class="kpi-icon success">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
                <div class="kpi-value"><?= number_format($analytics['system_health']['active_events']) ?></div>
                <div class="kpi-label">Active Events</div>
                <div class="kpi-trend trend-up">
                    <i class="fas fa-arrow-up"></i> +8% this month
                </div>
            </div>
            
            <div class="kpi-card warning">
                <div class="kpi-header">
                    <div class="kpi-icon warning">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
                <div class="kpi-value">K<?= number_format($analytics['system_health']['total_revenue'] / 1000, 1) ?></div>
                <div class="kpi-label">Total Revenue</div>
                <div class="kpi-trend trend-up">
                    <i class="fas fa-arrow-up"></i> +25% this month
                </div>
            </div>
            
            <div class="kpi-card info">
                <div class="kpi-header">
                    <div class="kpi-icon info">
                        <i class="fas fa-percentage"></i>
                    </div>
                </div>
                <div class="kpi-value"><?= number_format($analytics['system_health']['conversion_rate'], 1) ?>%</div>
                <div class="kpi-label">Conversion Rate</div>
                <div class="kpi-trend trend-neutral">
                    <i class="fas fa-minus"></i> No change
                </div>
            </div>
            
            <div class="kpi-card danger">
                <div class="kpi-header">
                    <div class="kpi-icon danger">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="kpi-value"><?= number_format($analytics['system_health']['pending_approvals']) ?></div>
                <div class="kpi-label">Pending Approvals</div>
                <div class="kpi-trend trend-down">
                    <i class="fas fa-arrow-down"></i> -5% this week
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- User Growth Chart -->
            <div class="col-lg-8">
                <div class="analytics-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            User Growth Trend
                        </h5>
                        <p class="card-subtitle">Daily user registrations and cumulative growth</p>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="userGrowthChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Category Performance -->
            <div class="col-lg-4">
                <div class="analytics-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-pie-chart"></i>
                            Category Performance
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mini-chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Revenue Trend -->
            <div class="col-lg-6">
                <div class="analytics-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-money-bill-trend-up"></i>
                            Revenue Trend
                        </h5>
                        <p class="card-subtitle">Daily revenue and transaction volume</p>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
                      <!-- User Engagement -->
            <div class="col-lg-6">
                <div class="analytics-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-users-cog"></i>
                            User Engagement by Role
                        </h5>
                        <p class="card-subtitle">Activity levels across different user types</p>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="engagementChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Performing Events -->
        <div class="analytics-card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-trophy"></i>
                    Top Performing Events
                </h5>
                <p class="card-subtitle">Events ranked by revenue and attendance</p>
            </div>
            <div class="card-body">
                <?php if (empty($analytics['event_performance'])): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                        <h5>No Event Data</h5>
                        <p class="text-muted">No events found for the selected period</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="analytics-table">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Category</th>
                                    <th>Date</th>
                                    <th>Registrations</th>
                                    <th>Capacity</th>
                                    <th>Revenue</th>
                                    <th>Conversion</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analytics['event_performance'] as $event): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($event['title']) ?></div>
                                            <small class="text-muted">ID: <?= $event['event_id'] ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?= htmlspecialchars($event['category']) ?></span>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($event['start_datetime'])) ?></td>
                                        <td>
                                            <strong><?= $event['total_registrations'] ?></strong>
                                            <small class="text-success d-block"><?= $event['paid_registrations'] ?> paid</small>
                                        </td>
                                        <td>
                                            <?= $event['max_attendees'] ?>
                                            <div class="performance-bar">
                                                <div class="performance-fill" style="width: <?= min(100, $event['capacity_utilization']) ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?= number_format($event['capacity_utilization'], 1) ?>% filled</small>
                                        </td>
                                        <td>
                                            <strong>K<?= number_format($event['revenue'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge <?= $event['conversion_rate'] > 80 ? 'bg-success' : ($event['conversion_rate'] > 60 ? 'bg-warning' : 'bg-danger') ?>">
                                                <?= number_format($event['conversion_rate'], 1) ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $performance = ($event['capacity_utilization'] + $event['conversion_rate']) / 2;
                                            $performanceClass = $performance > 75 ? 'success' : ($performance > 50 ? 'warning' : 'danger');
                                            ?>
                                            <div class="performance-bar">
                                                <div class="performance-fill bg-<?= $performanceClass ?>" style="width: <?= $performance ?>%"></div>
                                            </div>
                                            <small class="text-<?= $performanceClass ?>"><?= number_format($performance, 1) ?>% overall</small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Top Organizers -->
        <div class="analytics-card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-star"></i>
                    Top Organizers
                </h5>
                <p class="card-subtitle">Most successful event organizers by revenue</p>
            </div>
            <div class="card-body">
                <?php if (empty($analytics['top_organizers'])): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                        <h5>No Organizer Data</h5>
                        <p class="text-muted">No organizer activity found for the selected period</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="analytics-table">
                            <thead>
                                <tr>
                                    <th>Organizer</th>
                                    <th>Events</th>
                                    <th>Tickets Sold</th>
                                    <th>Revenue</th>
                                    <th>Avg Rating</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analytics['top_organizers'] as $index => $organizer): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="organizer-avatar me-2">
                                                    <?= strtoupper(substr($organizer['first_name'], 0, 1) . substr($organizer['last_name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?= htmlspecialchars($organizer['first_name'] . ' ' . $organizer['last_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($organizer['email']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><strong><?= $organizer['total_events'] ?></strong></td>
                                        <td><strong><?= $organizer['total_tickets_sold'] ?></strong></td>
                                        <td><strong>K<?= number_format($organizer['total_revenue'], 2) ?></strong></td>
                                        <td>
                                            <?php if ($organizer['avg_rating']): ?>
                                                <div class="d-flex align-items-center">
                                                    <span class="me-1"><?= number_format($organizer['avg_rating'], 1) ?></span>
                                                    <div class="stars">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?= $i <= $organizer['avg_rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">No ratings</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($index === 0): ?>
                                                <span class="badge bg-warning"><i class="fas fa-crown"></i> #1</span>
                                            <?php elseif ($index === 1): ?>
                                                <span class="badge bg-secondary"><i class="fas fa-medal"></i> #2</span>
                                            <?php elseif ($index === 2): ?>
                                                <span class="badge bg-info"><i class="fas fa-award"></i> #3</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark">#<?= $index + 1 ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Analytics Dashboard JavaScript
        
        // Period Selection
        function setPeriod(period) {
            const now = new Date();
            let startDate, endDate;
            
            switch(period) {
                case 'week':
                    startDate = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 7);
                    endDate = now;
                    break;
                case 'month':
                    startDate = new Date(now.getFullYear(), now.getMonth(), 1);
                    endDate = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                    break;
                case 'quarter':
                    const quarter = Math.floor(now.getMonth() / 3);
                    startDate = new Date(now.getFullYear(), quarter * 3, 1);
                    endDate = new Date(now.getFullYear(), quarter * 3 + 3, 0);
                    break;
                case 'year':
                    startDate = new Date(now.getFullYear(), 0, 1);
                    endDate = new Date(now.getFullYear(), 11, 31);
                    break;
            }
            
            document.getElementById('startDate').value = startDate.toISOString().split('T')[0];
            document.getElementById('endDate').value = endDate.toISOString().split('T')[0];
            
            applyDateRange();
        }
        
        function applyDateRange() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            if (startDate && endDate) {
                window.location.href = `?start_date=${startDate}&end_date=${endDate}`;
            }
        }
        
        // Print Analytics
        function printAnalytics() {
            window.print();
        }
        
        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // User Growth Chart
            const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
            new Chart(userGrowthCtx, {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_column($analytics['user_growth'], 'date')) ?>,
                    datasets: [{
                        label: 'New Users',
                        data: <?= json_encode(array_column($analytics['user_growth'], 'new_users')) ?>,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Cumulative Users',
                        data: <?= json_encode(array_column($analytics['user_growth'], 'cumulative_users')) ?>,
                        borderColor: '#f093fb',
                        backgroundColor: 'rgba(240, 147, 251, 0.1)',
                        tension: 0.4,
                        fill: false
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
            
            // Category Performance Chart
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode(array_column($analytics['category_performance'], 'category')) ?>,
                    datasets: [{
                        data: <?= json_encode(array_column($analytics['category_performance'], 'category_revenue')) ?>,
                        backgroundColor: [
                            '#667eea', '#f093fb', '#4CAF50', '#ff9800', 
                            '#f44336', '#2196F3', '#9c27b0', '#00bcd4'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Revenue Trend Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($analytics['revenue_trend'], 'date')) ?>,
                    datasets: [{
                        label: 'Daily Revenue',
                        data: <?= json_encode(array_column($analytics['revenue_trend'], 'daily_revenue')) ?>,
                        backgroundColor: 'rgba(102, 126, 234, 0.8)',
                        borderColor: '#667eea',
                        borderWidth: 1
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
            
            // User Engagement Chart
            const engagementCtx = document.getElementById('engagementChart').getContext('2d');
            new Chart(engagementCtx, {
                               type: 'radar',
                data: {
                    labels: <?= json_encode(array_column($analytics['user_engagement'], 'role')) ?>,
                    datasets: [{
                        label: 'Total Users',
                        data: <?= json_encode(array_column($analytics['user_engagement'], 'total_users')) ?>,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.2)',
                        pointBackgroundColor: '#667eea',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: '#667eea'
                    }, {
                        label: 'Avg Tickets per User',
                        data: <?= json_encode(array_column($analytics['user_engagement'], 'avg_tickets_per_user')) ?>,
                        borderColor: '#f093fb',
                        backgroundColor: 'rgba(240, 147, 251, 0.2)',
                        pointBackgroundColor: '#f093fb',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: '#f093fb'
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
                        r: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        }
                    }
                }
            });
        });
        
        // Auto-refresh data every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);
        
        // Add organizer avatar styles
        const additionalStyles = `
            <style>
                .organizer-avatar {
                    width: 35px;
                    height: 35px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-weight: 600;
                    font-size: 0.8rem;
                }
                
                .stars {
                    font-size: 0.8rem;
                }
                
                @media print {
                    .export-controls,
                    .date-range-picker {
                        display: none !important;
                    }
                    
                    .analytics-card {
                        break-inside: avoid;
                        margin-bottom: 1rem;
                    }
                    
                    .chart-container {
                        height: 300px;
                    }
                }
            </style>
        `;
        
        document.head.insertAdjacentHTML('beforeend', additionalStyles);
        
        console.log('📊 Analytics Dashboard Loaded Successfully!');
    </script>
</body>
</html>

