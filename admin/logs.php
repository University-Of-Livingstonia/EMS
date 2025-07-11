<?php
/**
 * 📋 System Logs - EMS Admin
 * Ekwendeni Mighty Campus Event Management System
 * System Activity & Error Logs Monitor 🔍
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

// Pagination settings
$logsPerPage = 50;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $logsPerPage;

// Filter settings
$logType = isset($_GET['type']) ? $_GET['type'] : 'all';
$logLevel = isset($_GET['level']) ? $_GET['level'] : 'all';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Sample logs for demonstration
$systemLogs = [
    [
        'log_id' => 1001,
        'log_type' => 'auth',
        'log_level' => 'info',
        'message' => 'User login successful',
        'user_id' => $currentUser['user_id'],
        'username' => $currentUser['username'],
        'first_name' => $currentUser['first_name'],
        'last_name' => $currentUser['last_name'],
        'ip_address' => '192.168.1.100',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
    ],
    [
        'log_id' => 1002,
        'log_type' => 'event',
        'log_level' => 'info',
        'message' => 'New event created: Tech Conference 2024',
        'user_id' => $currentUser['user_id'],
        'username' => $currentUser['username'],
        'first_name' => $currentUser['first_name'],
        'last_name' => $currentUser['last_name'],
        'ip_address' => '192.168.1.100',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'created_at' => date('Y-m-d H:i:s', strtotime('-4 hours'))
    ],
    [
        'log_id' => 1003,
        'log_type' => 'payment',
        'log_level' => 'warning',
        'message' => 'Payment timeout for ticket #12345',
        'user_id' => null,
        'username' => null,
        'first_name' => null,
        'last_name' => null,
        'ip_address' => '192.168.1.200',
        'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_7_1 like Mac OS X)',
        'created_at' => date('Y-m-d H:i:s', strtotime('-6 hours'))
    ],
    [
        'log_id' => 1004,
        'log_type' => 'system',
        'log_level' => 'error',
        'message' => 'Database connection timeout',
        'user_id' => null,
        'username' => null,
        'first_name' => null,
        'last_name' => null,
        'ip_address' => null,
        'user_agent' => null,
        'created_at' => date('Y-m-d H:i:s', strtotime('-8 hours'))
    ],
    [
        'log_id' => 1005,
        'log_type' => 'security',
        'log_level' => 'critical',
        'message' => 'Multiple failed login attempts detected from IP',
        'user_id' => null,
        'username' => null,
        'first_name' => null,
        'last_name' => null,
        'ip_address' => '192.168.1.250',
        'user_agent' => 'curl/7.68.0',
        'created_at' => date('Y-m-d H:i:s', strtotime('-12 hours'))
    ]
];

$totalLogs = count($systemLogs);
$totalPages = ceil($totalLogs / $logsPerPage);

// Log statistics
$logStats = [
    'total' => 1247,
    'today' => 89,
    'errors' => 23,
    'warnings' => 156,
    'critical' => 5
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - EMS Admin</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --admin-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --admin-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --admin-success: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            --admin-warning: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            --admin-danger: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            --admin-info: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            --sidebar-bg: #1a1a2e;
            --sidebar-hover: #16213e;
            --content-bg: #f8f9fa;
            --card-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
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
        }
        
        /* Sidebar Styles */
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
        
        .admin-nav {
            padding: 1.5rem 0;
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
        
        .nav-icon {
            font-size: 1.3rem;
            margin-right: 1rem;
            width: 25px;
            text-align: center;
        }
        
        .admin-main {
            margin-left: 300px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
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
        
        .admin-content {
            padding: 2rem;
        }
        
        .admin-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
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
        
        .admin-card-body {
            padding: 2rem;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: all 0.3s ease;
            border-left: 5px solid;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.total { border-left-color: #667eea; }
        .stat-card.today { border-left-color: #4CAF50; }
        .stat-card.errors { border-left-color: #f44336; }
        .stat-card.warnings { border-left-color: #ff9800; }
        .stat-card.critical { border-left-color: #9c27b0; }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        
        /* Filters */
        .filters-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 0.7rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .filter-btn {
            background: var(--admin-primary);
            color: white;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        /* Logs Table */
        .logs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .logs-table th,
        .logs-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
              .logs-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .logs-table tr:hover {
            background: #f8f9fa;
        }
        
        /* Log Level Badges */
        .log-level {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .log-level.info {
            background: rgba(33, 150, 243, 0.1);
            color: #2196F3;
        }
        
        .log-level.warning {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
        }
        
        .log-level.error {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }
        
        .log-level.critical {
            background: rgba(156, 39, 176, 0.1);
            color: #9c27b0;
        }
        
        .log-level.debug {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
        
        /* Log Type Badges */
        .log-type {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .log-type.auth {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .log-type.event {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }
        
        .log-type.payment {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
        }
        
        .log-type.system {
            background: rgba(33, 150, 243, 0.1);
            color: #2196F3;
        }
        
        .log-type.error {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }
        
        .log-type.security {
            background: rgba(156, 39, 176, 0.1);
            color: #9c27b0;
        }
        
        /* Pagination */
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .pagination {
            display: flex;
            gap: 0.5rem;
        }
        
        .pagination a,
        .pagination span {
            padding: 0.7rem 1rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .pagination a {
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }
        
        .pagination a:hover {
            background: var(--admin-primary);
            color: white;
            border-color: transparent;
        }
        
        .pagination .current {
            background: var(--admin-primary);
            color: white;
            border: 2px solid transparent;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            
            .admin-main {
                margin-left: 0;
            }
            
            .admin-content {
                padding: 1rem;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .logs-table {
                font-size: 0.8rem;
            }
            
            .logs-table th,
            .logs-table td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Admin Sidebar -->
    <div class="admin-sidebar">
        <div class="sidebar-header">
            <h3>👑 EMS Admin</h3>
            <p>System Control Center</p>
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
                    <a href="logs.php" class="admin-nav-link active">
                        <i class="fas fa-list-alt nav-icon"></i>
                        <span class="nav-text">System Logs</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="reports.php" class="admin-nav-link">
                        <i class="fas fa-file-invoice-dollar nav-icon"></i>
                        <span class="nav-text">Reports</span>
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
    
    <!-- Main Content -->
    <div class="admin-main">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <h1 class="admin-title">📋 System Logs</h1>
            <div class="admin-user-info">
                <span>Welcome, <?= htmlspecialchars($currentUser['first_name']) ?>!</span>
            </div>
        </div>
        
        <!-- Content -->
        <div class="admin-content">
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-number"><?= number_format($logStats['total']) ?></div>
                    <div class="stat-label">Total Logs</div>
                </div>
                <div class="stat-card today">
                    <div class="stat-number"><?= number_format($logStats['today']) ?></div>
                    <div class="stat-label">Today</div>
                </div>
                <div class="stat-card errors">
                    <div class="stat-number"><?= number_format($logStats['errors']) ?></div>
                    <div class="stat-label">Errors</div>
                </div>
                <div class="stat-card warnings">
                    <div class="stat-number"><?= number_format($logStats['warnings']) ?></div>
                    <div class="stat-label">Warnings</div>
                </div>
                <div class="stat-card critical">
                    <div class="stat-number"><?= number_format($logStats['critical']) ?></div>
                    <div class="stat-label">Critical</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="type">Log Type</label>
                            <select name="type" id="type">
                                <option value="all" <?= $logType === 'all' ? 'selected' : '' ?>>All Types</option>
                                <option value="auth" <?= $logType === 'auth' ? 'selected' : '' ?>>Authentication</option>
                                <option value="event" <?= $logType === 'event' ? 'selected' : '' ?>>Events</option>
                                <option value="payment" <?= $logType === 'payment' ? 'selected' : '' ?>>Payments</option>
                                <option value="system" <?= $logType === 'system' ? 'selected' : '' ?>>System</option>
                                <option value="error" <?= $logType === 'error' ? 'selected' : '' ?>>Errors</option>
                                <option value="security" <?= $logType === 'security' ? 'selected' : '' ?>>Security</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="level">Log Level</label>
                            <select name="level" id="level">
                                <option value="all" <?= $logLevel === 'all' ? 'selected' : '' ?>>All Levels</option>
                                <option value="info" <?= $logLevel === 'info' ? 'selected' : '' ?>>Info</option>
                                <option value="warning" <?= $logLevel === 'warning' ? 'selected' : '' ?>>Warning</option>
                                <option value="error" <?= $logLevel === 'error' ? 'selected' : '' ?>>Error</option>
                                <option value="critical" <?= $logLevel === 'critical' ? 'selected' : '' ?>>Critical</option>
                                <option value="debug" <?= $logLevel === 'debug' ? 'selected' : '' ?>>Debug</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="date_from">From Date</label>
                            <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="date_to">To Date</label>
                            <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="search">Search</label>
                            <input type="text" name="search" id="search" placeholder="Search logs..." value="<?= htmlspecialchars($searchQuery) ?>">
                        </div>
                        
                        <div class="filter-group">
                            <button type="submit" class="filter-btn">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Logs Table -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3 class="admin-card-title">
                        <i class="fas fa-list-alt"></i>
                        System Logs
                    </h3>
                </div>
                <div class="admin-card-body">
                    <div class="table-responsive">
                        <table class="logs-table">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Type</th>
                                    <th>Level</th>
                                    <th>Message</th>
                                    <th>User</th>
                                    <th>IP Address</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($systemLogs)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="fas fa-inbox text-muted" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                                            <h5>No Logs Found</h5>
                                            <p class="text-muted">No logs match your current filters</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($systemLogs as $log): ?>
                                        <tr>
                                            <td>
                                                <strong><?= date('M j, Y', strtotime($log['created_at'])) ?></strong><br>
                                                <small class="text-muted"><?= date('g:i A', strtotime($log['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <span class="log-type <?= $log['log_type'] ?>">
                                                    <?= ucfirst($log['log_type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="log-level <?= $log['log_level'] ?>">
                                                    <?= ucfirst($log['log_level']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="max-width: 300px;">
                                                    <?= htmlspecialchars($log['message']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($log['user_id']): ?>
                                                    <strong><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></strong><br>
                                                    <small class="text-muted">@<?= htmlspecialchars($log['username']) ?></small>
                                                <?php else: ?>
                                                                                                        <span class="text-muted">System</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($log['ip_address']): ?>
                                                    <code><?= htmlspecialchars($log['ip_address']) ?></code>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewLogDetails(<?= $log['log_id'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination-wrapper">
                            <div class="pagination">
                                <?php if ($currentPage > 1): ?>
                                    <a href="?page=<?= $currentPage - 1 ?>&type=<?= $logType ?>&level=<?= $logLevel ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&search=<?= urlencode($searchQuery) ?>">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                                    <?php if ($i == $currentPage): ?>
                                        <span class="current"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?page=<?= $i ?>&type=<?= $logType ?>&level=<?= $logLevel ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&search=<?= urlencode($searchQuery) ?>"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($currentPage < $totalPages): ?>
                                    <a href="?page=<?= $currentPage + 1 ?>&type=<?= $logType ?>&level=<?= $logLevel ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&search=<?= urlencode($searchQuery) ?>">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function viewLogDetails(logId) {
            // Implement log details modal or redirect
            alert('View log details for ID: ' + logId);
        }
    </script>
</body>
</html>

