<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Get system statistics with error handling
try {
    $systemStats = getSystemStats($conn);

    // Ensure all required keys exist with default values
    $defaultStats = [
        'total_users' => 0,
        'total_events' => 0,
        'total_tickets' => 0,
        'total_revenue' => 0,
        'users_this_month' => 0,
        'events_this_month' => 0,
        'revenue_this_month' => 0,
        'events_by_status' => [],
        'popular_categories' => [],
        'recent_registrations' => 0,
        'recent_events' => 0
    ];

    $systemStats = array_merge($defaultStats, $systemStats ?: []);
} catch (Exception $e) {
    error_log("Analytics error: " . $e->getMessage());
    $systemStats = $defaultStats;
}

// Get additional analytics data
try {
    // User growth data
    $userGrowthData = [];
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at) 
        ORDER BY date
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $userGrowthData[] = $row;
    }

    // Event registration data
    $registrationData = [];
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM tickets 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at) 
        ORDER BY date
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $registrationData[] = $row;
    }

    // Revenue data
    $revenueData = [];
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, SUM(price) as revenue 
        FROM tickets 
        WHERE payment_status = 'completed' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at) 
        ORDER BY date
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $revenueData[] = [
            'date' => $row['date'],
            'revenue' => (float)$row['revenue']
        ];
    }

    // Department distribution
    $departmentData = [];
    $stmt = $conn->prepare("
        SELECT department, COUNT(*) as count 
        FROM users 
        WHERE department IS NOT NULL AND department != ''
        GROUP BY department 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $departmentData[] = $row;
    }

    // Event status distribution
    $eventStatusData = [];
    $stmt = $conn->prepare("
        SELECT status, COUNT(*) as count 
        FROM events 
        GROUP BY status
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $eventStatusData[] = $row;
    }
} catch (Exception $e) {
    error_log("Analytics data error: " . $e->getMessage());
    $userGrowthData = [];
    $registrationData = [];
    $revenueData = [];
    $departmentData = [];
    $eventStatusData = [];
}

// Calculate growth percentages safely
function calculateGrowthPercentage($current, $previous)
{
    if ($previous == 0) {
        return $current > 0 ? 100 : 0;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

// Get previous month data for comparison
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE MONTH(created_at) = MONTH(NOW() - INTERVAL 1 MONTH) AND YEAR(created_at) = YEAR(NOW() - INTERVAL 1 MONTH)");
    $stmt->execute();
    $prevMonthUsers = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM events WHERE MONTH(created_at) = MONTH(NOW() - INTERVAL 1 MONTH) AND YEAR(created_at) = YEAR(NOW() - INTERVAL 1 MONTH)");
    $stmt->execute();
    $prevMonthEvents = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

    $stmt = $conn->prepare("SELECT SUM(price) as revenue FROM tickets WHERE payment_status = 'completed' AND MONTH(created_at) = MONTH(NOW() - INTERVAL 1 MONTH) AND YEAR(created_at) = YEAR(NOW() - INTERVAL 1 MONTH)");
    $stmt->execute();
    $prevMonthRevenue = $stmt->get_result()->fetch_assoc()['revenue'] ?? 0;
} catch (Exception $e) {
    error_log("Previous month data error: " . $e->getMessage());
    $prevMonthUsers = 0;
    $prevMonthEvents = 0;
    $prevMonthRevenue = 0;
}

// Calculate growth rates
$userGrowth = calculateGrowthPercentage($systemStats['users_this_month'], $prevMonthUsers);
$eventGrowth = calculateGrowthPercentage($systemStats['events_this_month'], $prevMonthEvents);
$revenueGrowth = calculateGrowthPercentage($systemStats['revenue_this_month'], $prevMonthRevenue);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - EMS Admin</title>

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.0/index.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>

    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #4CAF50;
            --warning-color: #FF9800;
            --danger-color: #f44336;
            --info-color: #2196F3;
            --dark-color: #333;
            --light-color: #f8f9fa;
            --border-color: #e9ecef;
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 5px 25px rgba(0, 0, 0, 0.15);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--light-color);
            color: var(--dark-color);
        }

        .layout-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-x: auto;
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
        }

        .page-subtitle {
            color: #666;
            margin-top: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: var(--shadow);
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
            background: var(--primary-color);
        }

        .stat-card.success::before {
            background: var(--success-color);
        }

        .stat-card.warning::before {
            background: var(--warning-color);
        }

        .stat-card.danger::before {
            background: var(--danger-color);
        }

        .stat-card.info::before {
            background: var(--info-color);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
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
            background: var(--primary-color);
        }

        .stat-icon.success {
            background: var(--success-color);
        }

        .stat-icon.warning {
            background: var(--warning-color);
        }

        .stat-icon.danger {
            background: var(--danger-color);
        }

        .stat-icon.info {
            background: var(--info-color);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }

        .stat-label {
            color: #666;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .stat-change {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .stat-change.positive {
            color: var(--success-color);
        }

        .stat-change.negative {
            color: var(--danger-color);
        }

        .stat-change.neutral {
            color: #666;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: var(--shadow);
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--dark-color);
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .table-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem 2rem;
            background: var(--primary-color);
            color: white;
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }

        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
        }

        .table {
            margin: 0;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            border: none;
            padding: 1rem;
        }

        .table td {
            padding: 1rem;
            border-color: var(--border-color);
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }

        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 200px;
            color: #666;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 1rem;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .chart-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="layout-container">
        <!-- Include Navigation -->
        <?php include 'includes/navigation.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-chart-line me-3"></i>
                    Analytics Dashboard
                </h1>
                <p class="page-subtitle">
                    Comprehensive insights and statistics for your event management system
                </p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <!-- Total Users -->
                <div class="stat-card info">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Total Users</div>
                            <h3 class="stat-value"><?php echo number_format($systemStats['total_users'] ?? 0); ?></h3>
                        </div>
                        <div class="stat-icon info">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-change <?php echo $userGrowth > 0 ? 'positive' : ($userGrowth < 0 ? 'negative' : 'neutral'); ?>">
                        <i class="fas fa-<?php echo $userGrowth > 0 ? 'arrow-up' : ($userGrowth < 0 ? 'arrow-down' : 'minus'); ?>"></i>
                        <?php echo abs($userGrowth); ?>% from last month
                    </div>
                </div>

                <!-- Total Events -->
                <div class="stat-card success">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Total Events</div>
                            <h3 class="stat-value"><?php echo number_format($systemStats['total_events'] ?? 0); ?></h3>
                        </div>
                        <div class="stat-icon success">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="stat-change <?php echo $eventGrowth > 0 ? 'positive' : ($eventGrowth < 0 ? 'negative' : 'neutral'); ?>">
                        <i class="fas fa-<?php echo $eventGrowth > 0 ? 'arrow-up' : ($eventGrowth < 0 ? 'arrow-down' : 'minus'); ?>"></i>
                        <?php echo abs($eventGrowth); ?>% from last month
                    </div>
                </div>

                <!-- Total Tickets -->
                <div class="stat-card warning">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Total Tickets</div>
                            <h3 class="stat-value"><?php echo number_format($systemStats['total_tickets'] ?? 0); ?></h3>
                        </div>
                        <div class="stat-icon warning">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <?php echo number_format($systemStats['recent_registrations'] ?? 0); ?> in last 24h
                    </div>
                </div>

                <!-- Total Revenue -->
                <div class="stat-card danger">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Total Revenue</div>
                            <h3 class="stat-value"><?php echo formatCurrency($systemStats['total_revenue'] ?? 0); ?></h3>
                        </div>
                        <div class="stat-icon danger">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="stat-change <?php echo $revenueGrowth > 0 ? 'positive' : ($revenueGrowth < 0 ? 'negative' : 'neutral'); ?>">
                        <i class="fas fa-<?php echo $revenueGrowth > 0 ? 'arrow-up' : ($revenueGrowth < 0 ? 'arrow-down' : 'minus'); ?>"></i>
                        <?php echo abs($revenueGrowth); ?>% from last month
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-grid">
                <!-- User Growth Chart -->
                <div class="chart-card">
                    <h3 class="chart-title">
                        <i class="fas fa-user-plus me-2"></i>
                        User Growth (Last 30 Days)
                    </h3>
                    <div class="chart-container">
                        <canvas id="userGrowthChart"></canvas>
                    </div>
                </div>

                <!-- Event Registrations Chart -->
                <div class="chart-card">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-line me-2"></i>
                        Event Registrations (Last 30 Days)
                    </h3>
                    <div class="chart-container">
                        <canvas id="registrationChart"></canvas>
                    </div>
                </div>

                <!-- Revenue Chart -->
                <div class="chart-card">
                    <h3 class="chart-title">
                        <i class="fas fa-money-bill-wave me-2"></i>
                        Revenue Trend (Last 30 Days)
                    </h3>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <!-- Event Status Distribution -->
                <div class="chart-card">
                    <h3 class="chart-title">
                        <i class="fas fa-pie-chart me-2"></i>
                        Event Status Distribution
                    </h3>
                    <div class="chart-container">
                        <canvas id="eventStatusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Data Tables Section -->
            <div class="row">
                <!-- Department Distribution -->
                <div class="col-lg-6 mb-4">
                    <div class="table-card">
                        <div class="table-header">
                            <h3 class="table-title">
                                <i class="fas fa-building me-2"></i>
                                Users by Department
                            </h3>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Users</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($departmentData)): ?>
                                        <?php
                                        $totalDeptUsers = array_sum(array_column($departmentData, 'count'));
                                        foreach ($departmentData as $dept):
                                            $percentage = $totalDeptUsers > 0 ? round(($dept['count'] / $totalDeptUsers) * 100, 1) : 0;
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($dept['department']); ?></td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?php echo number_format($dept['count']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar" role="progressbar"
                                                            style="width: <?php echo $percentage; ?>%">
                                                            <?php echo $percentage; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">
                                                <i class="fas fa-info-circle me-2"></i>
                                                No department data available
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Popular Event Categories -->
                <div class="col-lg-6 mb-4">
                    <div class="table-card">
                        <div class="table-header">
                            <h3 class="table-title">
                                <i class="fas fa-tags me-2"></i>
                                Popular Event Categories
                            </h3>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Events</th>
                                        <th>Popularity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($systemStats['popular_categories'])): ?>
                                        <?php
                                        $maxCount = max(array_column($systemStats['popular_categories'], 'count'));
                                        foreach ($systemStats['popular_categories'] as $category):
                                            $popularity = $maxCount > 0 ? round(($category['count'] / $maxCount) * 100, 1) : 0;
                                        ?>
                                            <tr>
                                                <td>
                                                    <span class="badge" style="background-color: <?php echo getCategoryColor($category['category']); ?>">
                                                        <?php echo ucfirst($category['category']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($category['count']); ?></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-success" role="progressbar"
                                                            style="width: <?php echo $popularity; ?>%">
                                                            <?php echo $popularity; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">
                                                <i class="fas fa-info-circle me-2"></i>
                                                No category data available
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="table-card">
                <div class="table-header">
                    <h3 class="table-title">
                        <i class="fas fa-clock me-2"></i>
                        System Overview
                    </h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Metric</th>
                                <th>This Month</th>
                                <th>Last Month</th>
                                <th>Change</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><i class="fas fa-users text-info me-2"></i>New Users</td>
                                <td><?php echo number_format($systemStats['users_this_month']); ?></td>
                                <td><?php echo number_format($prevMonthUsers); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $userGrowth > 0 ? 'success' : ($userGrowth < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $userGrowth > 0 ? '+' : ''; ?><?php echo $userGrowth; ?>%
                                    </span>
                                </td>
                                <td>
                                    <i class="fas fa-<?php echo $userGrowth > 0 ? 'arrow-up text-success' : ($userGrowth < 0 ? 'arrow-down text-danger' : 'minus text-secondary'); ?>"></i>
                                </td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-calendar-check text-success me-2"></i>New Events</td>
                                <td><?php echo number_format($systemStats['events_this_month']); ?></td>
                                <td><?php echo number_format($prevMonthEvents); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $eventGrowth > 0 ? 'success' : ($eventGrowth < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $eventGrowth > 0 ? '+' : ''; ?><?php echo $eventGrowth; ?>%
                                    </span>
                                </td>
                                <td>
                                    <i class="fas fa-<?php echo $eventGrowth > 0 ? 'arrow-up text-success' : ($eventGrowth < 0 ? 'arrow-down text-danger' : 'minus text-secondary'); ?>"></i>
                                </td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-dollar-sign text-warning me-2"></i>Revenue</td>
                                <td><?php echo formatCurrency($systemStats['revenue_this_month']); ?></td>
                                <td><?php echo formatCurrency($prevMonthRevenue); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $revenueGrowth > 0 ? 'success' : ($revenueGrowth < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $revenueGrowth > 0 ? '+' : ''; ?><?php echo $revenueGrowth; ?>%
                                    </span>
                                </td>
                                <td>
                                    <i class="fas fa-<?php echo $revenueGrowth > 0 ? 'arrow-up text-success' : ($revenueGrowth < 0 ? 'arrow-down text-danger' : 'minus text-secondary'); ?>"></i>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Chart.js Configuration
        Chart.defaults.font.family = 'Poppins';
        Chart.defaults.color = '#666';

        // User Growth Chart
        const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
        const userGrowthChart = new Chart(userGrowthCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($userGrowthData, 'date')); ?>,
                datasets: [{
                    label: 'New Users',
                    data: <?php echo json_encode(array_column($userGrowthData, 'count')); ?>,
                    borderColor: '#2196F3',
                    backgroundColor: 'rgba(33, 150, 243, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#2196F3',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
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
                        },
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                elements: {
                    point: {
                        hoverBackgroundColor: '#2196F3'
                    }
                }
            }
        });

        // Registration Chart
        const registrationCtx = document.getElementById('registrationChart').getContext('2d');
        const registrationChart = new Chart(registrationCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($registrationData, 'date')); ?>,
                datasets: [{
                    label: 'Registrations',
                    data: <?php echo json_encode(array_column($registrationData, 'count')); ?>,
                    backgroundColor: 'rgba(76, 175, 80, 0.8)',
                    borderColor: '#4CAF50',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
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
                        },
                        ticks: {
                            stepSize: 1
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

        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($revenueData, 'date')); ?>,
                datasets: [{
                    label: 'Revenue (MWK)',
                    data: <?php echo json_encode(array_column($revenueData, 'revenue')); ?>,
                    borderColor: '#FF9800',
                    backgroundColor: 'rgba(255, 152, 0, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#FF9800',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
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
                        },
                        ticks: {
                            callback: function(value) {
                                return 'MWK ' + value.toLocaleString();
                            }
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

        // Event Status Chart
        const eventStatusCtx = document.getElementById('eventStatusChart').getContext('2d');
        const eventStatusChart = new Chart(eventStatusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($eventStatusData, 'status')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($eventStatusData, 'count')); ?>,
                    backgroundColor: [
                        '#4CAF50', // approved
                        '#FF9800', // pending
                        '#f44336', // rejected
                        '#2196F3', // draft
                        '#9C27B0', // cancelled
                        '#607D8B' // completed
                    ],
                    borderWidth: 0,
                    hoverBorderWidth: 3,
                    hoverBorderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });

        // Mobile Navigation Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileToggle = document.getElementById('mobileToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const sidebarClose = document.getElementById('sidebarClose');

            if (mobileToggle) {
                mobileToggle.addEventListener('click', function() {
                    sidebar.classList.add('active');
                    sidebarOverlay.classList.add('active');
                });
            }

            if (sidebarClose) {
                sidebarClose.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                });
            }

            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                });
            }

            // Auto-refresh data every 5 minutes
            setInterval(function() {
                location.reload();
            }, 300000);
        });

        // Tooltip initialization
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Add loading states for charts
        function showChartLoading(chartId) {
            const container = document.getElementById(chartId).parentElement;
            container.innerHTML = `
                <div class="loading">
                    <div class="spinner"></div>
                    Loading chart data...
                </div>
            `;
        }

        // Export functionality
        function exportData(type) {
            const exportUrl = `export.php?type=${type}`;
            window.open(exportUrl, '_blank');
        }

        // Print functionality
        function printAnalytics() {
            window.print();
        }

        // Real-time updates (if WebSocket is available)
        if (typeof WebSocket !== 'undefined') {
            // WebSocket connection for real-time updates
            // This would connect to a WebSocket server for live data
        }
    </script>

    <!-- Print Styles -->
    <style media="print">
        .sidebar {
            display: none !important;
        }

        .main-content {
            margin-left: 0 !important;
            padding: 1rem !important;
        }

        .chart-container {
            height: 200px !important;
        }

        .no-print {
            display: none !important;
        }

        .page-break {
            page-break-before: always;
        }

        body {
            font-size: 12px !important;
        }

        .stat-card {
            break-inside: avoid;
        }
    </style>
</body>

</html>