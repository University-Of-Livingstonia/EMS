<?php
/**
 * 📊 Analytics Dashboard - Organizer
 * Ekwendeni Mighty Campus Event Management System
 * Deep Insights Into Your Events! 📈
 */

require_once '../../includes/functions.php';

// Get database connection
$conn = require_once '../../config/database.php';

// Initialize session manager
require_once '../../includes/session.php';
$sessionManager = new SessionManager($conn);

// Require organizer login
$sessionManager->requireLogin();
$currentUser = $sessionManager->getCurrentUser();

// Check if user is organizer
if ($currentUser['role'] !== 'organizer') {
    header('Location: ../../dashboard/index.php');
    exit;
}

// Get date range from URL parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
$eventId = intval($_GET['event_id'] ?? 0);

// Get organizer's events for filter
$organizerEvents = [];
try {
    $stmt = $conn->prepare("
        SELECT event_id, title, start_datetime, status,
               (SELECT COUNT(*) FROM tickets WHERE event_id = events.event_id) as total_registrations
        FROM events 
        WHERE organizer_id = ? 
        ORDER BY start_datetime DESC
    ");
    $stmt->bind_param("i", $currentUser['user_id']);
    $stmt->execute();
    $organizerEvents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Organizer events error: " . $e->getMessage());
}

// Build analytics query conditions
$whereConditions = ["e.organizer_id = ?"];
$params = [$currentUser['user_id']];
$paramTypes = "i";

if ($eventId) {
    $whereConditions[] = "e.event_id = ?";
    $params[] = $eventId;
    $paramTypes .= "i";
}

$whereConditions[] = "e.created_at BETWEEN ? AND ?";
$params[] = $startDate . ' 00:00:00';
$params[] = $endDate . ' 23:59:59';
$paramTypes .= "ss";

// Get comprehensive analytics data
$analytics = [];

try {
    // Overall Statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT e.event_id) as total_events,
            COUNT(DISTINCT CASE WHEN e.status = 'approved' THEN e.event_id END) as approved_events,
            COUNT(DISTINCT t.ticket_id) as total_registrations,
            COUNT(DISTINCT CASE WHEN t.status = 'confirmed' THEN t.ticket_id END) as confirmed_registrations,
            COUNT(DISTINCT CASE WHEN t.checked_in = 1 THEN t.ticket_id END) as checked_in_attendees,
            SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as total_revenue,
            AVG(CASE WHEN t.payment_status = 'completed' THEN t.price END) as avg_ticket_price
        FROM events e
        LEFT JOIN tickets t ON e.event_id = t.event_id
        WHERE " . implode(' AND ', $whereConditions)
    );
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $analytics['overview'] = $stmt->get_result()->fetch_assoc();
    
    // Event Performance
    $stmt = $conn->prepare("
        SELECT 
            e.event_id,
            e.title,
            e.start_datetime,
            e.max_attendees,
            COUNT(t.ticket_id) as total_registrations,
            COUNT(CASE WHEN t.status = 'confirmed' THEN 1 END) as confirmed_registrations,
            COUNT(CASE WHEN t.checked_in = 1 THEN 1 END) as checked_in_count,
            SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as event_revenue,
            AVG(CASE WHEN t.payment_status = 'completed' THEN t.price END) as avg_price
        FROM events e
        LEFT JOIN tickets t ON e.event_id = t.event_id
        WHERE " . implode(' AND ', $whereConditions) . "
        GROUP BY e.event_id
        ORDER BY total_registrations DESC
        LIMIT 10
    ");
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $analytics['event_performance'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Registration Trends (Daily)
    $stmt = $conn->prepare("
        SELECT 
            DATE(t.created_at) as registration_date,
            COUNT(t.ticket_id) as daily_registrations,
            SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as daily_revenue
        FROM tickets t
        JOIN events e ON t.event_id = e.event_id
        WHERE " . implode(' AND ', $whereConditions) . "
        GROUP BY DATE(t.created_at)
        ORDER BY registration_date ASC
    ");
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $analytics['daily_trends'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Department Analysis
    $stmt = $conn->prepare("
        SELECT 
            u.department,
            COUNT(t.ticket_id) as registrations,
            COUNT(CASE WHEN t.status = 'confirmed' THEN 1 END) as confirmed,
            SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as revenue
        FROM tickets t
        JOIN events e ON t.event_id = e.event_id
        JOIN users u ON t.user_id = u.user_id
        WHERE " . implode(' AND ', $whereConditions) . "
        AND u.department IS NOT NULL AND u.department != ''
        GROUP BY u.department
        ORDER BY registrations DESC
        LIMIT 10
    ");
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $analytics['department_analysis'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Payment Analysis
    $stmt = $conn->prepare("
        SELECT 
            t.payment_status,
            COUNT(t.ticket_id) as count,
            SUM(t.price) as total_amount
        FROM tickets t
        JOIN events e ON t.event_id = e.event_id
        WHERE " . implode(' AND ', $whereConditions) . "
        GROUP BY t.payment_status
    ");
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $analytics['payment_analysis'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Monthly Comparison
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(e.start_datetime, '%Y-%m') as month,
            COUNT(DISTINCT e.event_id) as events_count,
            COUNT(t.ticket_id) as registrations,
            SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as revenue
        FROM events e
        LEFT JOIN tickets t ON e.event_id = t.event_id
        WHERE e.organizer_id = ?
        AND e.start_datetime >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
               GROUP BY DATE_FORMAT(e.start_datetime, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->bind_param("i", $currentUser['user_id']);
    $stmt->execute();
    $analytics['monthly_comparison'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    error_log("Analytics error: " . $e->getMessage());
    $analytics = [
        'overview' => [
            'total_events' => 0,
            'approved_events' => 0,
            'total_registrations' => 0,
            'confirmed_registrations' => 0,
            'checked_in_attendees' => 0,
            'total_revenue' => 0,
            'avg_ticket_price' => 0
        ],
        'event_performance' => [],
        'daily_trends' => [],
        'department_analysis' => [],
        'payment_analysis' => [],
        'monthly_comparison' => []
    ];
}

// Calculate conversion rates and other metrics
$conversionRate = $analytics['overview']['total_registrations'] > 0 ? 
    ($analytics['overview']['confirmed_registrations'] / $analytics['overview']['total_registrations']) * 100 : 0;

$attendanceRate = $analytics['overview']['confirmed_registrations'] > 0 ? 
    ($analytics['overview']['checked_in_attendees'] / $analytics['overview']['confirmed_registrations']) * 100 : 0;

// Prepare data for charts
$dailyTrendsData = [
    'labels' => array_column($analytics['daily_trends'], 'registration_date'),
    'registrations' => array_column($analytics['daily_trends'], 'daily_registrations'),
    'revenue' => array_column($analytics['daily_trends'], 'daily_revenue')
];

$departmentData = [
    'labels' => array_column($analytics['department_analysis'], 'department'),
    'data' => array_column($analytics['department_analysis'], 'registrations')
];

$monthlyData = [
    'labels' => array_column($analytics['monthly_comparison'], 'month'),
    'events' => array_column($analytics['monthly_comparison'], 'events_count'),
    'registrations' => array_column($analytics['monthly_comparison'], 'registrations'),
    'revenue' => array_column($analytics['monthly_comparison'], 'revenue')
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - Organizer | EMS</title>
    
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
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            --success-gradient: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            --warning-gradient: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            --danger-gradient: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            --info-gradient: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
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
            background: var(--light-bg);
            color: var(--text-dark);
        }
        
        /* 🎨 Header */
        .page-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .breadcrumb-nav {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 25px;
            margin-top: 1rem;
        }
        
        .breadcrumb-nav a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .breadcrumb-nav a:hover {
            color: white;
        }
        
        /* 🔍 Filters */
        .filters-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .filters-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-dark);
        }
        
        .form-control, .form-select {
            padding: 0.5rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-filter {
            padding: 0.5rem 1.5rem;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        /* 📊 Stats Cards */
        .stats-row {
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            text-align: center;
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
        
        .stat-card.primary::before { background: var(--primary-gradient); }
        .stat-card.success::before { background: var(--success-gradient); }
        .stat-card.warning::before { background: var(--warning-gradient); }
        .stat-card.info::before { background: var(--info-gradient); }
        .stat-card.danger::before { background: var(--danger-gradient); }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-icon.primary { color: #667eea; }
        .stat-icon.success { color: #4CAF50; }
        .stat-icon.warning { color: #ff9800; }
        .stat-icon.info { color: #2196F3; }
        .stat-icon.danger { color: #f44336; }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-change {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
        }
        
        .stat-change.positive {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .stat-change.negative {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }
        
        /* 📈 Chart Containers */
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .chart-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .chart-canvas {
            position: relative;
            height: 400px;
        }
        
        .mini-chart-canvas {
            position: relative;
            height: 250px;
        }
        
        /* 📋 Performance Table */
        .performance-table {
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .table-header {
            background: var(--primary-gradient);
            color: white;
            padding: 1.5rem;
        }
        
        .table-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
        }
        
        .performance-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .performance-table th,
        .performance-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .performance-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }
        
        .performance-table tr:hover {
            background: #f8f9fa;
        }
        
        .event-title {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .event-date {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .metric-value {
            font-weight: 600;
        }
        
        .metric-good { color: #4CAF50; }
        .metric-average { color: #ff9800; }
        .metric-poor { color: #f44336; }
        
        /* 📱 Responsive Design */
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .stats-row .col-md-3,
            .stats-row .col-md-4 {
                margin-bottom: 1rem;
            }
            
            .chart-canvas {
                height: 300px;
            }
            
            .performance-table {
                overflow-x: auto;
            }
            
            .performance-table table {
                min-width: 600px;
            }
        }
        
        /* 🎨 Animations */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .slide-up {
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="page-title">📊 Analytics Dashboard</h1>
                    <p class="page-subtitle">Deep insights into your event performance</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="breadcrumb-nav">
                        <a href="../../dashboard/">Dashboard</a> / 
                        <a href="../organizer/dashboard.php">Organizer</a> / 
                        <span>Analytics</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Filters -->
        <div class="filters-container fade-in">
            <h5 class="filters-title">
                <i class="fas fa-filter me-2"></i>
                Filter Analytics
            </h5>
            
            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                    <label class="form-label">Event</label>
                    <select name="event_id" class="form-select">
                        <option value="">All Events</option>
                        <?php foreach ($organizerEvents as $event): ?>
                            <option value="<?= $event['event_id'] ?>" 
                                    <?= $eventId == $event['event_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($event['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-chart-line me-1"></i>
                            Analyze
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Overview Statistics -->
        <div class="row stats-row fade-in">
            <div class="col-md-3">
                <div class="stat-card primary">
                    <i class="fas fa-calendar-alt stat-icon primary"></i>
                    <div class="stat-number"><?= $analytics['overview']['total_events'] ?></div>
                    <div class="stat-label">Total Events</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <?= $analytics['overview']['approved_events'] ?> Approved
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card success">
                    <i class="fas fa-users stat-icon success"></i>
                    <div class="stat-number"><?= $analytics['overview']['total_registrations'] ?></div>
                    <div class="stat-label">Total Registrations</div>
                    <div class="stat-change positive">
                        <i class="fas fa-percentage"></i>
                        <?= number_format($conversionRate, 1) ?>% Confirmed
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card warning">
                    <i class="fas fa-sign-in-alt stat-icon warning"></i>
                    <div class="stat-number"><?= $analytics['overview']['checked_in_attendees'] ?></div>
                    <div class="stat-label">Checked In</div>
                    <div class="stat-change positive">
                        <i class="fas fa-percentage"></i>
                        <?= number_format($attendanceRate, 1) ?>% Attendance
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card info">
                    <i class="fas fa-money-bill-wave stat-icon info"></i>
                    <div class="stat-number">K<?= number_format($analytics['overview']['total_revenue'], 0) ?></div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-change positive">
                        <i class="fas fa-coins"></i>
                        K<?= number_format($analytics['overview']['avg_ticket_price'] ?? 0, 0) ?> Avg
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <!-- Daily Trends Chart -->
            <div class="col-md-8">
                <div class="chart-container slide-up">
                    <h5 class="chart-title">
                        <i class="fas fa-chart-line"></i>
                        Daily Registration Trends
                    </h5>
                    <div class="chart-canvas">
                        <canvas id="dailyTrendsChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Department Distribution -->
            <div class="col-md-4">
                <div class="chart-container slide-up">
                    <h5 class="chart-title">
                        <i class="fas fa-chart-pie"></i>
                        Department Distribution
                    </h5>
                    <div class="mini-chart-canvas">
                        <canvas id="departmentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Comparison Chart -->
        <div class="chart-container slide-up">
            <h5 class="chart-title">
                <i class="fas fa-chart-bar"></i>
                Monthly Performance Comparison
            </h5>
            <div class="chart-canvas">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>

        <!-- Event Performance Table -->
        <div class="performance-table slide-up">
            <div class="table-header">
                <h5 class="table-title">
                    <i class="fas fa-trophy me-2"></i>
                    Event Performance Rankings
                </h5>
            </div>
            
            <?php if (empty($analytics['event_performance'])): ?>
                <div class="text-center py-5">
                    <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No event data available for the selected period.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Registrations</th>
                            <th>Confirmed</th>
                            <th>Attendance</th>
                            <th>Revenue</th>
                            <th>Avg Price</th>
                            <th>Capacity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analytics['event_performance'] as $event): ?>
                            <?php
                            $capacityRate = $event['max_attendees'] > 0 ? 
                                ($event['confirmed_registrations'] / $event['max_attendees']) * 100 : 0;
                            $attendanceRate = $event['confirmed_registrations'] > 0 ? 
                                ($event['checked_in_count'] / $event['confirmed_registrations']) * 100 : 0;
                            ?>
                            <tr>
                                <td>
                                    <div class="event-title"><?= htmlspecialchars($event['title']) ?></div>
                                    <div class="event-date"><?= date('M j, Y', strtotime($event['start_datetime'])) ?></div>
                                </td>
                                <td>
                                    <span class="metric-value"><?= $event['total_registrations'] ?></span>
                                </td>
                                <td>
                                    <span class="metric-value"><?= $event['confirmed_registrations'] ?></span>
                                </td>
                                <td>
                                    <span class="metric-value <?= $attendanceRate >= 80 ? 'metric-good' : ($attendanceRate >= 60 ? 'metric-average' : 'metric-poor') ?>">
                                        <?= $event['checked_in_count'] ?> (<?= number_format($attendanceRate, 1) ?>%)
                                    </span>
                                </td>
                                <td>
                                    <span class="metric-value">K<?= number_format($event['event_revenue'], 0) ?></span>
                                </td>
                                <td>
                                    <span class="metric-value">K<?= number_format($event['avg_price'] ?? 0, 0) ?></span>
                                </td>
                                <td>
                                    <span class="metric-value <?= $capacityRate >= 80 ? 'metric-good' : ($capacityRate >= 50 ? 'metric-average' : 'metric-poor') ?>">
                                        <?= number_format($capacityRate, 1) ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Payment Analysis -->
        <?php if (!empty($analytics['payment_analysis'])): ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="chart-container slide-up">
                        <h5 class="chart-title">
                            <i class="fas fa-credit-card"></i>
                            Payment Status Distribution
                        </h5>
                        <div class="mini-chart-canvas">
                            <canvas id="paymentChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="chart-container slide-up">
                        <h5 class="chart-title">
                            <i class="fas fa-building"></i>
                            Top Departments
                        </h5>
                        <div class="mini-chart-canvas">
                            <canvas id="topDepartmentsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Chart.js Configuration
        Chart.defaults.font.family = 'Poppins';
        Chart.defaults.color = '#6c757d';
        
        // Daily Trends Chart
        const dailyTrendsCtx = document.getElementById('dailyTrendsChart').getContext('2d');
        new Chart(dailyTrendsCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($dailyTrendsData['labels']) ?>,
                datasets: [{
                    label: 'Registrations',
                    data: <?= json_encode($dailyTrendsData['registrations']) ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Revenue (K)',
                    data: <?= json_encode($dailyTrendsData['revenue']) ?>,
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
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
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Registrations'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Revenue (K)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // Department Distribution Chart
        const departmentCtx = document.getElementById('departmentChart').getContext('2d');
        new Chart(departmentCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($departmentData['labels']) ?>,
                datasets: [{
                    data: <?= json_encode($departmentData['data']) ?>,
                    backgroundColor: [
                        '#667eea', '#4CAF50', '#ff9800', '#f44336', '#2196F3',
                        '#9c27b0', '#ff5722', '#795548', '#607d8b', '#e91e63'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        // Monthly Comparison Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($monthlyData['labels']) ?>,
                datasets: [{
                    label: 'Events',
                    data: <?= json_encode($monthlyData['events']) ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: '#667eea',
                    borderWidth: 1
                }, {
                    label: 'Registrations',
                    data: <?= json_encode($monthlyData['registrations']) ?>,
                    backgroundColor: 'rgba(76, 175, 80, 0.8)',
                    borderColor: '#4CAF50',
                    borderWidth: 1
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
                        beginAtZero: true
                    }
                }
            }
        });

        // Payment Status Chart
        <?php if (!empty($analytics['payment_analysis'])): ?>
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        new Chart(paymentCtx, {
            type: 'pie',
            data: {
                labels: <?= json_encode(array_column($analytics['payment_analysis'], 'payment_status')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($analytics['payment_analysis'], 'count')) ?>,
                    backgroundColor: ['#4CAF50', '#ff9800', '#f44336']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        // Top Departments Chart
        const topDeptCtx = document.getElementById('topDepartmentsChart').getContext('2d');
        new Chart(topDeptCtx, {
            type: 'horizontalBar',
            data: {
                labels: <?= json_encode(array_slice(array_column($analytics['department_analysis'], 'department'), 0, 5)) ?>,
                datasets: [{
                                        label: 'Registrations',
                    data: <?= json_encode(array_slice(array_column($analytics['department_analysis'], 'registrations'), 0, 5)) ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: '#667eea',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true
                    }
                }
            }
        });
        <?php endif; ?>

        // Auto-refresh data every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);

        console.log('📊 Analytics Dashboard Loaded');
    </script>
</body>
</html>

