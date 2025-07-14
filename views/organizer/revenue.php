<?php
/**
 * 💰 Revenue Analytics - EMS Organizer
 * Ekwendeni Mighty Campus Event Management System
 * Track Your Event Revenue & Earnings! 📊
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

// Check if user is organizer or admin
if (!in_array($currentUser['role'], ['organizer', 'admin'])) {
    header('Location: ../../dashboard/index.php');
    exit;
}

$organizerId = $currentUser['user_id'];

// Get date range from query parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
$eventId = $_GET['event_id'] ?? 'all';

// Get revenue statistics
$revenueStats = [];
try {
    $whereClause = "WHERE e.organizer_id = ?";
    $params = [$organizerId];
    $types = "i";
    
    if ($eventId !== 'all') {
        $whereClause .= " AND e.event_id = ?";
        $params[] = $eventId;
        $types .= "i";
    }
    
    $whereClause .= " AND DATE(t.created_at) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= "ss";
    
    $stmt = $conn->prepare("
        SELECT 
            COUNT(t.ticket_id) as total_tickets_sold,
            SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as total_revenue,
            SUM(CASE WHEN t.payment_status = 'pending' THEN t.price ELSE 0 END) as pending_revenue,
            SUM(CASE WHEN t.payment_status = 'failed' THEN t.price ELSE 0 END) as failed_revenue,
            COUNT(CASE WHEN t.payment_status = 'completed' THEN 1 END) as completed_sales,
            COUNT(CASE WHEN t.payment_status = 'pending' THEN 1 END) as pending_sales,
            COUNT(CASE WHEN t.payment_status = 'failed' THEN 1 END) as failed_sales,
            AVG(t.price) as avg_ticket_price,
            COUNT(DISTINCT e.event_id) as events_with_sales
        FROM events e
        JOIN tickets t ON e.event_id = t.event_id
        $whereClause
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $revenueStats = $stmt->get_result()->fetch_assoc();
    
    // Handle null values
    foreach ($revenueStats as $key => $value) {
        if ($value === null) {
            $revenueStats[$key] = 0;
        }
    }
} catch (Exception $e) {
    error_log("Revenue stats error: " . $e->getMessage());
    $revenueStats = [
        'total_tickets_sold' => 0,
        'total_revenue' => 0,
        'pending_revenue' => 0,
        'failed_revenue' => 0,
        'completed_sales' => 0,
        'pending_sales' => 0,
        'failed_sales' => 0,
        'avg_ticket_price' => 0,
        'events_with_sales' => 0
    ];
}

// Get daily revenue data for chart
$dailyRevenue = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            DATE(t.created_at) as sale_date,
            COUNT(t.ticket_id) as tickets_sold,
            SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as daily_revenue
        FROM events e
        JOIN tickets t ON e.event_id = t.event_id
        WHERE e.organizer_id = ? 
        AND DATE(t.created_at) BETWEEN ? AND ?
        " . ($eventId !== 'all' ? "AND e.event_id = ?" : "") . "
        GROUP BY DATE(t.created_at)
        ORDER BY sale_date ASC
    ");
    
    if ($eventId !== 'all') {
        $stmt->bind_param("issi", $organizerId, $startDate, $endDate, $eventId);
    } else {
        $stmt->bind_param("iss", $organizerId, $startDate, $endDate);
    }
    
    $stmt->execute();
    $dailyRevenue = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Daily revenue error: " . $e->getMessage());
}

// Get event-wise revenue breakdown
$eventRevenue = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            e.event_id,
            e.title,
            e.start_datetime,
            e.ticket_price,
            COUNT(t.ticket_id) as tickets_sold,
            SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as event_revenue,
            COUNT(CASE WHEN t.payment_status = 'completed' THEN 1 END) as completed_sales,
            COUNT(CASE WHEN t.payment_status = 'pending' THEN 1 END) as pending_sales,
            (COUNT(t.ticket_id) * 100.0 / e.max_attendees) as capacity_percentage
        FROM events e
        LEFT JOIN tickets t ON e.event_id = t.event_id 
        WHERE e.organizer_id = ? 
        AND e.status = 'approved'
        " . ($eventId !== 'all' ? "AND e.event_id = ?" : "") . "
        GROUP BY e.event_id
        ORDER BY event_revenue DESC
        LIMIT 20
    ");
    
    if ($eventId !== 'all') {
        $stmt->bind_param("ii", $organizerId, $eventId);
    } else {
        $stmt->bind_param("i", $organizerId);
    }
    
    $stmt->execute();
    $eventRevenue = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Event revenue error: " . $e->getMessage());
}

// Get payment method breakdown
$paymentMethods = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(t.payment_method, 'Not Specified') as payment_method,
            COUNT(t.ticket_id) as transaction_count,
            SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as method_revenue
        FROM events e
        JOIN tickets t ON e.event_id = t.event_id
        WHERE e.organizer_id = ? 
        AND DATE(t.created_at) BETWEEN ? AND ?
        " . ($eventId !== 'all' ? "AND e.event_id = ?" : "") . "
        AND t.payment_status = 'completed'
        GROUP BY t.payment_method
        ORDER BY method_revenue DESC
    ");
    
    if ($eventId !== 'all') {
        $stmt->bind_param("issi", $organizerId, $startDate, $endDate, $eventId);
    } else {
        $stmt->bind_param("iss", $organizerId, $startDate, $endDate);
    }
    
    $stmt->execute();
    $paymentMethods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Payment methods error: " . $e->getMessage());
}

// Get organizer's events for filter dropdown
$organizerEvents = [];
try {
    $stmt = $conn->prepare("
        SELECT event_id, title, start_datetime
        FROM events 
        WHERE organizer_id = ? 
        AND status = 'approved'
        ORDER BY start_datetime DESC
    ");
    $stmt->bind_param("i", $organizerId);
    $stmt->execute();
    $organizerEvents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Organizer events error: " . $e->getMessage());
}

// Calculate conversion rate
$conversionRate = 0;
if ($revenueStats['total_tickets_sold'] > 0) {
    $conversionRate = ($revenueStats['completed_sales'] / $revenueStats['total_tickets_sold']) * 100;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue Analytics - EMS Organizer</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    
    <!-- Include organizer styles -->
    <link rel="stylesheet" href="../../assets/css/organizer-styles.css">
    
    <style>
        /* Revenue-specific styles */
        .revenue-card {
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
        
        .revenue-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-hover-shadow);
        }
        
        .revenue-filters {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }
        
        .filter-group {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-item {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-item label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .filter-item select,
        .filter-item input {
            width: 100%;
            padding: 0.7rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .filter-item select:focus,
        .filter-item input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
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
        
        .revenue-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .summary-item {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .summary-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .summary-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin: 0 auto 1rem auto;
        }
        
        .summary-icon.revenue { background: var(--organizer-success); }
        .summary-icon.pending { background: var(--organizer-warning); }
        .summary-icon.conversion { background: var(--organizer-info); }
        .summary-icon.average { background: var(--organizer-primary); }
        
        .summary-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .summary-label {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .event-revenue-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .event-revenue-table th,
        .event-revenue-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .event-revenue-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .event-revenue-table tr:hover {
            background: #f8f9fa;
        }
        
            .revenue-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .revenue-high {
            background: var(--organizer-success);
            color: white;
        }
        
        .revenue-medium {
            background: var(--organizer-warning);
            color: white;
        }
        
        .revenue-low {
            background: var(--organizer-info);
            color: white;
        }
        
        .capacity-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .capacity-fill {
            height: 100%;
            background: var(--organizer-primary);
            transition: width 0.3s ease;
        }
        
        .payment-method-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .payment-method-item:hover {
            background: #f8f9fa;
        }
        
        .payment-method-item:last-child {
            border-bottom: none;
        }
        
        .method-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .method-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--organizer-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
        }
        
        .method-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .method-details small {
            color: var(--text-secondary);
        }
        
        .method-revenue {
            text-align: right;
        }
        
        .method-revenue .amount {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .method-revenue .count {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .export-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .export-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .export-btn-primary {
            background: var(--organizer-primary);
            color: white;
        }
        
        .export-btn-success {
            background: var(--organizer-success);
            color: white;
        }
        
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .filter-group {
                flex-direction: column;
            }
            
            .filter-item {
                min-width: 100%;
            }
            
            .revenue-summary {
                grid-template-columns: 1fr;
            }
            
            .export-buttons {
                flex-direction: column;
            }
            
            .export-btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Animation classes */
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
    </style>
</head>
<body>
    <!-- 🎪 Organizer Sidebar -->
    <div class="organizer-sidebar" id="organizerSidebar">
        <div class="sidebar-header">
            <h3>🎪 EMS Organizer</h3>
            <p>Event Management Hub</p>
        </div>
        
        <nav class="organizer-nav">
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <div class="organizer-nav-item">
                    <a href="dashboard.php" class="organizer-nav-link">
                        <i class="fas fa-tachometer-alt nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="events.php" class="organizer-nav-link">
                        <i class="fas fa-calendar-alt nav-icon"></i>
                        <span class="nav-text">My Events</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="pending_events.php" class="organizer-nav-link">
                        <i class="fas fa-clock nav-icon"></i>
                        <span class="nav-text">Pending Events</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Analytics</div>
                <div class="organizer-nav-item">
                    <a href="revenue.php" class="organizer-nav-link active">
                        <i class="fas fa-chart-line nav-icon"></i>
                        <span class="nav-text">Revenue</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="reports.php" class="organizer-nav-link">
                        <i class="fas fa-file-chart-line nav-icon"></i>
                        <span class="nav-text">Reports</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Management</div>
                <div class="organizer-nav-item">
                    <a href="create-event.php" class="organizer-nav-link">
                        <i class="fas fa-plus-circle nav-icon"></i>
                        <span class="nav-text">Create Event</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="attendees.php" class="organizer-nav-link">
                        <i class="fas fa-users nav-icon"></i>
                        <span class="nav-text">Attendees</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Account</div>
                <div class="organizer-nav-item">
                    <a href="profile.php" class="organizer-nav-link">
                        <i class="fas fa-user nav-icon"></i>
                        <span class="nav-text">Profile</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="../../dashboard/index.php" class="organizer-nav-link">
                        <i class="fas fa-arrow-left nav-icon"></i>
                        <span class="nav-text">Back to User</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="../../auth/logout.php" class="organizer-nav-link">
                        <i class="fas fa-sign-out-alt nav-icon"></i>
                        <span class="nav-text">Logout</span>
                    </a>
                </div>
            </div>
        </nav>
    </div>
    
    <!-- 📱 Main Content Area -->
    <div class="organizer-main" id="organizerMain">
        <!-- 🎯 Organizer Top Bar -->
        <div class="organizer-topbar">
            <div class="organizer-title-section">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="organizer-title">💰 Revenue Analytics</h1>
            </div>
            
            <div class="organizer-user-info">
                <div class="organizer-avatar">
                    <?= strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)) ?>
                </div>
                <div class="organizer-user-details">
                    <h6><?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></h6>
                    <small>Event Organizer</small>
                </div>
            </div>
        </div>
        
        <!-- 📊 Dashboard Content -->
        <div class="organizer-content">
            <!-- 🎯 Revenue Filters -->
            <div class="revenue-filters fade-in-up">
                <form method="GET" action="revenue.php" id="revenueFilters">
                    <div class="filter-group">
                        <div class="filter-item">
                            <label for="event_id">Event</label>
                            <select name="event_id" id="event_id">
                                <option value="all" <?= $eventId === 'all' ? 'selected' : '' ?>>All Events</option>
                                <?php foreach ($organizerEvents as $event): ?>
                                    <option value="<?= $event['event_id'] ?>" <?= $eventId == $event['event_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($event['title']) ?> - <?= date('M Y', strtotime($event['start_datetime'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-item">
                            <label for="start_date">Start Date</label>
                            <input type="date" name="start_date" id="start_date" value="<?= $startDate ?>">
                        </div>
                        
                        <div class="filter-item">
                            <label for="end_date">End Date</label>
                            <input type="date" name="end_date" id="end_date" value="<?= $endDate ?>">
                        </div>
                        
                        <div class="filter-item">
                            <button type="submit" class="organizer-btn organizer-btn-primary">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- 💰 Revenue Summary -->
            <div class="revenue-summary fade-in-up">
                <div class="summary-item">
                    <div class="summary-icon revenue">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="summary-value">K<?= number_format($revenueStats['total_revenue'], 0) ?></div>
                    <div class="summary-label">Total Revenue</div>
                </div>
                
                <div class="summary-item">
                    <div class="summary-icon pending">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="summary-value">K<?= number_format($revenueStats['pending_revenue'], 0) ?></div>
                    <div class="summary-label">Pending Revenue</div>
                </div>
                
                <div class="summary-item">
                    <div class="summary-icon conversion">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="summary-value"><?= number_format($conversionRate, 1) ?>%</div>
                    <div class="summary-label">Conversion Rate</div>
                </div>
                
                <div class="summary-item">
                    <div class="summary-icon average">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="summary-value">K<?= number_format($revenueStats['avg_ticket_price'], 0) ?></div>
                    <div class="summary-label">Avg Ticket Price</div>
                </div>
            </div>
            
            <!-- 📈 Charts Row -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="revenue-card fade-in-up">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-line"></i>
                                Daily Revenue Trend
                            </h3>
                            <div class="export-buttons">
                                <button onclick="exportChart('revenue')" class="export-btn export-btn-primary">
                                    <i class="fas fa-download"></i> Export Chart
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="revenue-card fade-in-up">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-credit-card"></i>
                                Payment Methods
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="mini-chart-container">
                                <canvas id="paymentMethodChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 📊 Revenue Breakdown -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="revenue-card slide-in-right">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-calendar-alt"></i>
                                Event Revenue Breakdown
                            </h3>
                            <div class="export-buttons">
                                <button onclick="exportTable('event-revenue')" class="export-btn export-btn-success">
                                    <i class="fas fa-file-excel"></i> Export Excel
                                </button>
                                <button onclick="exportTable('event-revenue', 'pdf')" class="export-btn export-btn-primary">
                                    <i class="fas fa-file-pdf"></i> Export PDF
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($eventRevenue)): ?>
                                <div class="table-responsive">
                                    <table class="event-revenue-table" id="event-revenue-table">
                                        <thead>
                                            <tr>
                                                <th>Event</th>
                                                <th>Date</th>
                                                <th>Tickets Sold</th>
                                                <th>Revenue</th>
                                                <th>Capacity</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($eventRevenue as $event): ?>
                                                <tr>
                                                    <td>
                                                        <div>
                                                            <strong><?= htmlspecialchars($event['title']) ?></strong>
                                                            <br>
                                                            <small class="text-muted">K<?= number_format($event['ticket_price'], 0) ?> per ticket</small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?= date('M j, Y', strtotime($event['start_datetime'])) ?>
                                                        <br>
                                                        <small class="text-muted"><?= date('g:i A', strtotime($event['start_datetime'])) ?></small>
                                                    </td>
                                                    <td>
                                                        <strong><?= $event['tickets_sold'] ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?= $event['completed_sales'] ?> paid, <?= $event['pending_sales'] ?> pending
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <strong>K<?= number_format($event['event_revenue'], 0) ?></strong>
                                                        <?php
                                                        $revenueLevel = 'low';
                                                        if ($event['event_revenue'] > 50000) $revenueLevel = 'high';
                                                        elseif ($event['event_revenue'] > 20000) $revenueLevel = 'medium';
                                                        ?>
                                                        <span class="revenue-badge revenue-<?= $revenueLevel ?>">
                                                            <?= ucfirst($revenueLevel) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <?= number_format($event['capacity_percentage'], 1) ?>%
                                                            <div class="capacity-bar">
                                                                <div class="capacity-fill" style="width: <?= min($event['capacity_percentage'], 100) ?>%"></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if (strtotime($event['start_datetime']) > time()): ?>
                                                            <span class="badge bg-success">Upcoming</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Completed</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-chart-line"></i>
                                    <h4>No Revenue Data</h4>
                                    <p>No revenue data available for the selected period.</p>
                                    <a href="create-event.php" class="organizer-btn organizer-btn-primary">
                                        <i class="fas fa-plus-circle"></i> Create Your First Event
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="revenue-card slide-in-right">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-credit-card"></i>
                                Payment Method Details
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($paymentMethods)): ?>
                                <?php foreach ($paymentMethods as $method): ?>
                                    <div class="payment-method-item">
                                        <div class="method-info">
                                            <div class="method-icon">
                                                <?php
                                                $icon = 'fas fa-credit-card';
                                                switch (strtolower($method['payment_method'])) {
                                                    case 'mobile_money':
                                                        $icon = 'fas fa-mobile-alt';
                                                        break;
                                                    case 'bank_transfer':
                                                        $icon = 'fas fa-university';
                                                        break;
                                                    case 'cash':
                                                        $icon = 'fas fa-money-bill';
                                                        break;
                                                    case 'card':
                                                        $icon = 'fas fa-credit-card';
                                                        break;
                                                }
                                                ?>
                                                <i class="<?= $icon ?>"></i>
                                            </div>
                                            <div class="method-details">
                                                <h6><?= ucwords(str_replace('_', ' ', $method['payment_method'])) ?></h6>
                                                <small><?= $method['transaction_count'] ?> transactions</small>
                                            </div>
                                        </div>
                                        <div class="method-revenue">
                                            <div class="amount">K<?= number_format($method['method_revenue'], 0) ?></div>
                                            <div class="count"><?= number_format(($method['method_revenue'] / $revenueStats['total_revenue']) * 100, 1) ?>%</div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-credit-card"></i>
                                    <h6>No Payment Data</h6>
                                    <p>No payment method data available.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 📈 Additional Analytics -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="revenue-card fade-in-up">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-pie"></i>
                                Revenue Distribution
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="mini-chart-container">
                                <canvas id="revenueDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="revenue-card fade-in-up">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-bar"></i>
                                Sales Performance
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="performance-metrics">
                                <div class="metric-item">
                                    <div class="metric-label">Total Tickets Sold</div>
                                    <div class="metric-value"><?= number_format($revenueStats['total_tickets_sold']) ?></div>
                                    <div class="metric-change positive">
                                        <i class="fas fa-arrow-up"></i> 
                                        <?= $revenueStats['completed_sales'] ?> completed
                                    </div>
                                </div>
                                
                                <div class="metric-item">
                                    <div class="metric-label">Pending Sales</div>
                                    <div class="metric-value"><?= number_format($revenueStats['pending_sales']) ?></div>
                                    <div class="metric-change warning">
                                        <i class="fas fa-clock"></i> 
                                        K<?= number_format($revenueStats['pending_revenue']) ?> pending
                                    </div>
                                </div>
                                
                                <div class="metric-item">
                                    <div class="metric-label">Failed Sales</div>
                                    <div class="metric-value"><?= number_format($revenueStats['failed_sales']) ?></div>
                                    <div class="metric-change negative">
                                        <i class="fas fa-times"></i> 
                                        K<?= number_format($revenueStats['failed_revenue']) ?> lost
                                    </div>
                                </div>
                                
                                <div class="metric-item">
                                    <div class="metric-label">Events with Sales</div>
                                    <div class="metric-value"><?= number_format($revenueStats['events_with_sales']) ?></div>
                                    <div class="metric-change positive">
                                        <i class="fas fa-calendar-check"></i> 
                                        Active events
                                    </div>
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
        // 🎯 Revenue Analytics Controller
        class RevenueAnalytics {
            constructor() {
                this.charts = {};
                this.init();
            }
            
            init() {
                this.initCharts();
                this.bindEvents();
            }
            
            // 📊 Initialize Charts
            initCharts() {
                this.initRevenueChart();
                this.initPaymentMethodChart();
                this.initRevenueDistributionChart();
            }
            
            initRevenueChart() {
                const ctx = document.getElementById('revenueChart');
                if (!ctx) return;
                
                const dailyData = <?= json_encode($dailyRevenue) ?>;
                
                // Prepare data for chart
                const labels = dailyData.map(item => item.sale_date);
                const revenueData = dailyData.map(item => parseFloat(item.daily_revenue));
                const ticketData = dailyData.map(item => parseInt(item.tickets_sold));
                
                this.charts.revenue = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Daily Revenue (K)',
                            data: revenueData,
                            borderColor: 'rgb(102, 126, 234)',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            tension: 0.4,
                            fill: true,
                            yAxisID: 'y'
                        }, {
                            label: 'Tickets Sold',
                            data: ticketData,
                            borderColor: 'rgb(76, 175, 80)',
                            backgroundColor: 'rgba(76, 175, 80, 0.1)',
                            tension: 0.4,
                            fill: false,
                            yAxisID: 'y1'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'day',
                                    displayFormats: {
                                        day: 'MMM dd'
                                    }
                                },
                                grid: {
                                    color: 'rgba(0,0,0,0.1)'
                                }
                            },
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Revenue (K)'
                                },
                                grid: {
                                    color: 'rgba(0,0,0,0.1)'
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Tickets Sold'
                                },
                                grid: {
                                    drawOnChartArea: false,
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.datasetIndex === 0) {
                                            label += 'K' + context.parsed.y.toLocaleString();
                                        } else {
                                            label += context.parsed.y.toLocaleString();
                                        }
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            initPaymentMethodChart() {
                const ctx = document.getElementById('paymentMethodChart');
                if (!ctx) return;
                
                const paymentData = <?= json_encode($paymentMethods) ?>;
                
                const labels = paymentData.map(item => item.payment_method.replace('_', ' ').toUpperCase());
                const data = paymentData.map(item => parseFloat(item.method_revenue));
                const colors = [
                    'rgb(102, 126, 234)',
                    'rgb(255, 152, 0)',
                    'rgb(76, 175, 80)',
                    'rgb(244, 67, 54)',
                    'rgb(33, 150, 243)'
                ];
                
                this.charts.paymentMethod = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: colors.slice(0, data.length),
                            borderWidth: 0
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
                                    usePointStyle: true
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                                        return context.label + ': K' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            initRevenueDistributionChart() {
                const ctx = document.getElementById('revenueDistributionChart');
                if (!ctx) return;
                
                const eventData = <?= json_encode($eventRevenue) ?>;
                
                // Take top 10 events by revenue
                const topEvents = eventData.slice(0, 10);
                const labels = topEvents.map(item => item.title.length > 20 ? item.title.substring(0, 20) + '...' : item.title);
                const data = topEvents.map(item => parseFloat(item.event_revenue));
                
                this.charts.revenueDistribution = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Revenue (K)',
                            data: data,
                            backgroundColor: 'rgba(102, 126, 234, 0.8)',
                            borderColor: 'rgb(102, 126, 234)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Revenue (K)'
                                },
                                grid: {
                                    color: 'rgba(0,0,0,0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    color: 'rgba(0,0,0,0.1)'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Revenue: K' + context.parsed.y.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // 🎯 Event Handlers
            bindEvents() {
                // Auto-submit form when filters change
                const filterInputs = document.querySelectorAll('#revenueFilters select, #revenueFilters input');
                filterInputs.forEach(input => {
                    input.addEventListener('change', () => {
                        document.getElementById('revenueFilters').submit();
                    });
                });
                
                // Sidebar toggle
                window.toggleSidebar = () => {
                    const sidebar = document.getElementById('organizerSidebar');
                    const main = document.getElementById('organizerMain');
                    
                    if (window.innerWidth <= 768) {
                        sidebar.classList.toggle('show');
                    } else {
                        sidebar.classList.toggle('collapsed');
                        main.classList.toggle('expanded');
                    }
                };
                
                // Export functions
                window.exportChart = (type) => {
                    const chart = this.charts[type];
                    if (chart) {
                        const url = chart.toBase64Image();
                        const link = document.createElement('a');
                        link.download = `${type}-chart.png`;
                        link.href = url;
                        link.click();
                    }
                };
                
                window.exportTable = (tableId, format = 'excel') => {
                    const table = document.getElementById(tableId + '-table');
                    if (!table) return;
                    
                    if (format === 'excel') {
                        this.exportToExcel(table, `${tableId}-data.xlsx`);
                    } else if (format === 'pdf') {
                        this.exportToPDF(table, `${tableId}-data.pdf`);
                    }
                };
            }
            
            // 📊 Export Functions
            exportToExcel(table, filename) {
                // Simple CSV export (can be enhanced with proper Excel library)
                let csv = [];
                const rows = table.querySelectorAll('tr');
                
                for (let i = 0; i < rows.length; i++) {
                    const row = [];
                    const cols = rows[i].querySelectorAll('td, th');
                    
                    for (let j = 0; j < cols.length; j++) {
                        let cellText = cols[j].innerText.replace(/\n/g, ' ').trim();
                        row.push('"' + cellText + '"');
                    }
                    
                    csv.push(row.join(','));
                }
                
                const csvContent = csv.join('\n');
                const blob = new Blob([csvContent], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename.replace('.xlsx', '.csv');
                link.click();
                window.URL.revokeObjectURL(url);
            }
            
            exportToPDF(table, filename) {
                                // Simple print functionality (can be enhanced with PDF library)
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>Revenue Report</title>
                            <style>
                                body { font-family: Arial, sans-serif; margin: 20px; }
                                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                                th { background-color: #f2f2f2; font-weight: bold; }
                                .header { text-align: center; margin-bottom: 20px; }
                                .date { color: #666; font-size: 14px; }
                            </style>
                        </head>
                        <body>
                            <div class="header">
                                <h1>Revenue Report</h1>
                                <p class="date">Generated on ${new Date().toLocaleDateString()}</p>
                            </div>
                            ${table.outerHTML}
                        </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
            }
        }
        
        // 🚀 Initialize Revenue Analytics
        document.addEventListener('DOMContentLoaded', () => {
            new RevenueAnalytics();
        });
        
        // 🎨 Additional Styles for Performance Metrics
        const additionalStyles = `
            <style>
                .performance-metrics {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 1rem;
                }
                
                .metric-item {
                    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
                    border-radius: 10px;
                    padding: 1.5rem;
                    text-align: center;
                    border: 1px solid var(--border-color);
                    transition: all 0.3s ease;
                }
                
                .metric-item:hover {
                    transform: translateY(-3px);
                    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
                }
                
                .metric-label {
                    font-size: 0.9rem;
                    color: var(--text-secondary);
                    font-weight: 500;
                    margin-bottom: 0.5rem;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                .metric-value {
                    font-size: 2rem;
                    font-weight: 800;
                    color: var(--text-primary);
                    margin-bottom: 0.5rem;
                }
                
                .metric-change {
                    font-size: 0.8rem;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.3rem;
                }
                
                .metric-change.positive { color: #4CAF50; }
                .metric-change.negative { color: #f44336; }
                .metric-change.warning { color: #ff9800; }
                
                .empty-state {
                    text-align: center;
                    padding: 3rem 2rem;
                    color: var(--text-secondary);
                }
                
                .empty-state i {
                    font-size: 4rem;
                    margin-bottom: 1rem;
                    opacity: 0.5;
                }
                
                .empty-state h4, .empty-state h6 {
                    margin-bottom: 1rem;
                    color: var(--text-primary);
                }
                
                .empty-state p {
                    margin-bottom: 2rem;
                }
                
                .card-header {
                    padding: 1.5rem 2rem;
                    border-bottom: 1px solid var(--border-color);
                    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                
                .card-title {
                    font-size: 1.3rem;
                    font-weight: 600;
                    color: var(--text-primary);
                    margin: 0;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                
                .card-body {
                    padding: 2rem;
                }
                
                .organizer-btn {
                    padding: 0.7rem 1.5rem;
                    font-size: 0.9rem;
                    border-radius: 10px;
                    font-weight: 600;
                    text-decoration: none;
                    transition: all 0.3s ease;
                    border: none;
                    cursor: pointer;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                
                .organizer-btn-primary {
                    background: var(--organizer-primary);
                    color: white;
                }
                
                .organizer-btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
                }
                
                @media (max-width: 768px) {
                    .performance-metrics {
                        grid-template-columns: 1fr;
                    }
                    
                    .metric-item {
                        margin-bottom: 1rem;
                    }
                }
            </style>
        `;
        
        document.head.insertAdjacentHTML('beforeend', additionalStyles);
    </script>
</body>
</html>
