<?php
/**
 * 🎫 Tickets Management - Organizer Dashboard
 * Ekwendeni Mighty Campus Event Management System
 * Manage Event Tickets & Registrations! 🎪
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

// Deny access and redirect if user is not verified
if (!$currentUser || !isset($currentUser['email_verified']) || $currentUser['email_verified'] != 1) {
    header('Location: verify_email.php');
    exit;
}

// Check if user is organizer
if ($currentUser['role'] !== 'organizer') {
    header('Location: ../../dashboard/index.php');
    exit;
}

// Get event ID from URL
$eventId = $_GET['event_id'] ?? null;
if (!$eventId) {
    header('Location: ../organizer/dashboard.php');
    exit;
}

// Verify event belongs to current organizer
$event = null;
try {
    $stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ? AND organizer_id = ?");
    $stmt->bind_param("ii", $eventId, $currentUser['user_id']);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    
    if (!$event) {
        header('Location: ../organizer/dashboard.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Event verification error: " . $e->getMessage());
    header('Location: ../organizer/dashboard.php');
    exit;
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause for tickets
$whereConditions = ["t.event_id = ?"];
$params = [$eventId];
$types = 'i';

if (!empty($status_filter)) {
    $whereConditions[] = "t.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($payment_filter)) {
    $whereConditions[] = "t.payment_status = ?";
    $params[] = $payment_filter;
    $types .= 's';
}

if (!empty($search)) {
    $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR t.ticket_code LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ssss';
}

$whereClause = implode(' AND ', $whereConditions);

// Get tickets with user information
$tickets = [];
$totalTickets = 0;

try {
    // Count total tickets
    $countQuery = "
        SELECT COUNT(*) as total
        FROM tickets t 
        JOIN users u ON t.user_id = u.user_id
        WHERE $whereClause
    ";
    
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalTickets = $stmt->get_result()->fetch_assoc()['total'];
    
    // Get tickets with pagination
    $ticketsQuery = "
        SELECT t.*, u.first_name, u.last_name, u.email, u.phone_number, u.department
        FROM tickets t 
        JOIN users u ON t.user_id = u.user_id
        WHERE $whereClause
        ORDER BY t.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $allParams = array_merge($params, [$limit, $offset]);
    $allTypes = $types . 'ii';
    
    $stmt = $conn->prepare($ticketsQuery);
    $stmt->bind_param($allTypes, ...$allParams);
    $stmt->execute();
    $tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    error_log("Tickets query error: " . $e->getMessage());
}

// Get ticket statistics
$ticketStats = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_tickets,
            SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as paid_tickets,
            SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_tickets,
            SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed_tickets,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_tickets,
            SUM(CASE WHEN payment_status = 'completed' THEN price ELSE 0 END) as total_revenue,
            SUM(CASE WHEN payment_status = 'pending' THEN price ELSE 0 END) as pending_revenue
        FROM tickets 
        WHERE event_id = ?
    ");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $ticketStats = $stmt->get_result()->fetch_assoc();
} catch (Exception $e) {
    error_log("Ticket stats error: " . $e->getMessage());
    $ticketStats = [
        'total_tickets' => 0,
        'paid_tickets' => 0,
        'pending_tickets' => 0,
        'failed_tickets' => 0,
        'confirmed_tickets' => 0,
        'total_revenue' => 0,
        'pending_revenue' => 0
    ];
}

// Calculate pagination
$totalPages = ceil($totalTickets / $limit);

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $ticketId = $_POST['ticket_id'] ?? null;
    
    try {
        switch ($action) {
            case 'confirm_ticket':
                $stmt = $conn->prepare("
                    UPDATE tickets 
                    SET status = 'confirmed', updated_at = NOW() 
                    WHERE ticket_id = ? AND event_id = ?
                ");
                $stmt->bind_param("ii", $ticketId, $eventId);
                $stmt->execute();
                
                echo json_encode(['success' => true, 'message' => 'Ticket confirmed successfully']);
                break;
                
            case 'cancel_ticket':
                $stmt = $conn->prepare("
                    UPDATE tickets 
                    SET status = 'cancelled', updated_at = NOW() 
                    WHERE ticket_id = ? AND event_id = ?
                ");
                $stmt->bind_param("ii", $ticketId, $eventId);
                $stmt->execute();
                
                echo json_encode(['success' => true, 'message' => 'Ticket cancelled successfully']);
                break;
                
            case 'resend_ticket':
                // Logic to resend ticket email would go here
                echo json_encode(['success' => true, 'message' => 'Ticket resent successfully']);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets Management - <?= htmlspecialchars($event['title']) ?> | EMS</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            --success-gradient: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            --warning-gradient: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            --danger-gradient: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            --info-gradient: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
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
        
        /* 📊 Stats Cards */
        .stats-row {
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            height: 100%;
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
        .stat-card.danger::before { background: var(--danger-gradient); }
        .stat-card.info::before { background: var(--info-gradient); }
        
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
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-icon.primary { background: var(--primary-gradient); }
        .stat-icon.success { background: var(--success-gradient); }
        .stat-icon.warning { background: var(--warning-gradient); }
        .stat-icon.danger { background: var(--danger-gradient); }
        .stat-icon.info { background: var(--info-gradient); }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* 🔍 Filter Section */
        .filter-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .filter-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
              .filter-input,
        .filter-select {
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .filter-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .btn-filter {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }
        
        .btn-secondary {
            background: var(--border-color);
            color: var(--text-dark);
        }
        
        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        /* 🎫 Tickets Table */
        .tickets-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .tickets-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .tickets-table th,
        .tickets-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        
        .tickets-table th {
            background: var(--light-bg);
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .tickets-table tbody tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .user-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }
        
        .user-details small {
            color: var(--text-muted);
            font-size: 0.8rem;
        }
        
        .ticket-code {
            font-family: 'Courier New', monospace;
            background: var(--light-bg);
            padding: 0.3rem 0.6rem;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-confirmed {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }
        
        .status-pending {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
            border: 1px solid #ff9800;
        }
        
        .status-cancelled {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid #f44336;
        }
        
        .payment-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .payment-completed {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }
        
        .payment-pending {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
            border: 1px solid #ff9800;
        }
        
        .payment-failed {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid #f44336;
        }
        
        .ticket-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .btn-success {
            background: var(--success-gradient);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger-gradient);
            color: white;
        }
        
        .btn-info {
            background: var(--info-gradient);
            color: white;
        }
        
        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        }
        
        /* 📄 Pagination */
        .pagination-section {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .pagination {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .pagination-item {
            padding: 0.6rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .pagination-item:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-1px);
        }
        
        .pagination-item.active {
            background: var(--primary-gradient);
            border-color: transparent;
            color: white;
        }
        
        .pagination-item.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        /* 📱 Responsive Design */
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                justify-content: center;
            }
            
            .tickets-table {
                font-size: 0.8rem;
            }
            
            .tickets-table th,
            .tickets-table td {
                padding: 0.5rem;
            }
            
            .user-info {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }
            
            .ticket-actions {
                flex-direction: column;
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
        
        /* 🚨 Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast {
            background: white;
            border-radius: 10px;
            padding: 1rem 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
            border-left: 4px solid;
            animation: slideInRight 0.3s ease-out;
        }
        
        .toast.success { border-left-color: #4CAF50; }
        .toast.error { border-left-color: #f44336; }
        .toast.warning { border-left-color: #ff9800; }
        .toast.info { border-left-color: #2196F3; }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="page-title">🎫 Tickets Management</h1>
                    <p class="page-subtitle"><?= htmlspecialchars($event['title']) ?></p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="breadcrumb-nav">
                        <a href="../../dashboard/">Dashboard</a> / 
                        <a href="../organizer/dashboard.php">Organizer</a> / 
                        <span>Tickets</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Cards -->
        <div class="stats-row row fade-in">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card primary">
                    <div class="stat-header">
                        <div class="stat-icon primary">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?= number_format($ticketStats['total_tickets']) ?></div>
                    <div class="stat-label">Total Tickets</div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card success">
                    <div class="stat-header">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?= number_format($ticketStats['paid_tickets']) ?></div>
                    <div class="stat-label">Paid Tickets</div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card warning">
                    <div class="stat-header">
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?= number_format($ticketStats['pending_tickets']) ?></div>
                    <div class="stat-label">Pending Payment</div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card info">
                    <div class="stat-header">
                        <div class="stat-icon info">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                    <div class="stat-number">MWK <?= number_format($ticketStats['total_revenue']) ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section fade-in">
            <h3 class="filter-title">
                <i class="fas fa-filter"></i>
                Filter Tickets
            </h3>
            
            <form method="GET" action="">
                <input type="hidden" name="event_id" value="<?= $eventId ?>">
                
                <div class="filter-row">
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <input type="text" 
                               name="search" 
                               class="filter-input" 
                               placeholder="Search by name, email, or ticket code..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-select">
                            <option value="">All Statuses</option>
                            <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Payment Status</label>
                        <select name="payment" class="filter-select">
                            <option value="">All Payments</option>
                            <option value="completed" <?= $payment_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="pending" <?= $payment_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="failed" <?= $payment_filter === 'failed' ? 'selected' : '' ?>>Failed</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn-filter btn-primary">
                        <i class="fas fa-search me-2"></i>Apply Filters
                    </button>
                    <a href="?event_id=<?= $eventId ?>" class="btn-filter btn-secondary">
                        <i class="fas fa-times me-2"></i>Clear Filters
                    </a>
                    <button type="button" class="btn-filter btn-info" onclick="exportTickets()">
                        <i class="fas fa-download me-2"></i>Export CSV
                    </button>
                </div>
            </form>
        </div>

        <!-- Tickets Table -->
        <div class="tickets-section fade-in">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="section-title">
                    <i class="fas fa-list"></i>
                    Tickets List
                </h3>
                <div class="text-muted">
                    Showing <?= count($tickets) ?> of <?= number_format($totalTickets) ?> tickets
                </div>
            </div>
            
            <?php if (empty($tickets)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                    <h4>No Tickets Found</h4>
                    <p class="text-muted">No tickets match your current filters.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="tickets-table" id="ticketsTable">
                        <thead>
                            <tr>
                                <th>Attendee</th>
                                <th>Ticket Code</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Price</th>
                                <th>Registration Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr data-ticket-id="<?= $ticket['ticket_id'] ?>">
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?= strtoupper(substr($ticket['first_name'], 0, 1) . substr($ticket['last_name'], 0, 1)) ?>
                                            </div>
                                            <div class="user-details">
                                                <h6><?= htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) ?></h6>
                                                <small><?= htmlspecialchars($ticket['email']) ?></small>
                                                <?php if (!empty($ticket['phone_number'])): ?>
                                                    <br><small><i class="fas fa-phone fa-xs"></i> <?= htmlspecialchars($ticket['phone_number']) ?></small>
                                                <?php endif; ?>
                                                <?php if (!empty($ticket['department'])): ?>
                                                    <br><small><i class="fas fa-building fa-xs"></i> <?= htmlspecialchars($ticket['department']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="ticket-code"><?= htmlspecialchars($ticket['ticket_code']) ?></span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $ticket['status'] ?>">
                                            <?= ucfirst($ticket['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="payment-badge payment-<?= $ticket['payment_status'] ?>">
                                            <?= ucfirst($ticket['payment_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($ticket['price'] == 0): ?>
                                            <span class="text-success fw-bold">FREE</span>
                                        <?php else: ?>
                                            <span class="fw-bold">MWK <?= number_format($ticket['price']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?= date('M j, Y', strtotime($ticket['created_at'])) ?><br>
                                            <?= date('g:i A', strtotime($ticket['created_at'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="ticket-actions">
                                            <?php if ($ticket['status'] === 'pending'): ?>
                                                <button class="btn-action btn-success" 
                                                        onclick="updateTicketStatus(<?= $ticket['ticket_id'] ?>, 'confirm_ticket')"
                                                        title="Confirm Ticket">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($ticket['status'] !== 'cancelled'): ?>
                                                <button class="btn-action btn-danger" 
                                                        onclick="updateTicketStatus(<?= $ticket['ticket_id'] ?>, 'cancel_ticket')"
                                                        title="Cancel Ticket">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn-action btn-info" 
                                                    onclick="resendTicket(<?= $ticket['ticket_id'] ?>)"
                                                    title="Resend Ticket">
                                                <i class="fas fa-envelope"></i>
                                            </button>
                                            
                                            <a href="ticket-details.php?id=<?= $ticket['ticket_id'] ?>" 
                                               class="btn-action btn-info"
                                               title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-section">
                        <div class="pagination">
                            <!-- Previous Page -->
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                                   class="pagination-item">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php else: ?>
                                <span class="pagination-item disabled">
                                    <i class="fas fa-chevron-left"></i>
                                </span>
                            <?php endif; ?>
                            
                            <!-- Page Numbers -->
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            if ($startPage > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" 
                                   class="pagination-item">1</a>
                                <?php if ($startPage > 2): ?>
                                    <span class="pagination-item disabled">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                   class="pagination-item <?= $i === $page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <span class="pagination-item disabled">...</span>
                                <?php endif; ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>" 
                                   class="pagination-item"><?= $totalPages ?></a>
                            <?php endif; ?>
                            
                            <!-- Next Page -->
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                                   class="pagination-item">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php else: ?>
                                <span class="pagination-item disabled">
                                    <i class="fas fa-chevron-right"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // Toast notification function
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                    <span>${message}</span>
                    <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
                </div>
            `;
            
            document.getElementById('toastContainer').appendChild(toast);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 5000);
        }

        // Update ticket status
        function updateTicketStatus(ticketId, action) {
            const actionText = action === 'confirm_ticket' ? 'confirm' : 'cancel';
            
            if (!confirm(`Are you sure you want to ${actionText} this ticket?`)) {
                return;
            }
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=${action}&ticket_id=${ticketId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    // Reload page after short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                showToast('An error occurred. Please try again.', 'error');
                console.error('Error:', error);
            });
        }

        // Resend ticket
        function resendTicket(ticketId) {
            if (!confirm('Are you sure you want to resend this ticket?')) {
                return;
            }
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=resend_ticket&ticket_id=${ticketId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                showToast('An error occurred. Please try again.', 'error');
                console.error('Error:', error);
            });
        }

        // Export tickets to CSV
        function exportTickets() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            
            const exportUrl = `export-tickets.php?${params.toString()}`;
            window.open(exportUrl, '_blank');
            
            showToast('Export started. Download will begin shortly.', 'info');
        }

        // Auto-refresh every 30 seconds for real-time updates
        setInterval(() => {
            // Only refresh if no modals are open
            if (!document.querySelector('.modal.show')) {
                const currentUrl = window.location.href;
                fetch(currentUrl)
                    .then(response => response.text())
                    .then(html => {
                        // Update only the stats cards
                        const parser = new DOMParser();
                        const newDoc = parser.parseFromString(html, 'text/html');
                        const newStats = newDoc.querySelector('.stats-row');
                        const currentStats = document.querySelector('.stats-row');
                        
                        if (newStats && currentStats) {
                            currentStats.innerHTML = newStats.innerHTML;
                        }
                    })
                    .catch(error => {
                        console.log('Auto-refresh failed:', error);
                    });
            }
        }, 30000);

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Add fade-in animation to elements
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            console.log('🎫 Tickets Management Page Loaded');
            console.log(`📊 Managing ${<?= count($tickets) ?>} tickets for event: <?= htmlspecialchars($event['title']) ?>`);
        });
    </script>
</body>
</html>
