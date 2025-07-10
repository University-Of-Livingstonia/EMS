<?php
/**
 * 👥 Attendees Management - Organizer Dashboard
 * Ekwendeni Mighty Campus Event Management System
 * Manage Your Event Attendees! 🎪
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

// Get event ID from URL
$eventId = intval($_GET['event_id'] ?? 0);
$event = null;

if ($eventId) {
    // Verify event belongs to current organizer
    try {
        $stmt = $conn->prepare("
            SELECT * FROM events 
            WHERE event_id = ? AND organizer_id = ?
        ");
        $stmt->bind_param("ii", $eventId, $currentUser['user_id']);
        $stmt->execute();
        $event = $stmt->get_result()->fetch_assoc();
        
        if (!$event) {
            header('Location: ../organizer/dashboard.php');
            exit;
        }
    } catch (Exception $e) {
        error_log("Event fetch error: " . $e->getMessage());
        header('Location: ../organizer/dashboard.php');
        exit;
    }
}

// Get organizer's events for filter
$organizerEvents = [];
try {
    $stmt = $conn->prepare("
        SELECT event_id, title, start_datetime, status,
               (SELECT COUNT(*) FROM tickets WHERE event_id = events.event_id) as total_attendees
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

$message = '';
$messageType = '';

// Handle attendee actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ticketId = intval($_POST['ticket_id'] ?? 0);
    
    if ($action && $ticketId) {
        try {
            switch ($action) {
                case 'approve':
                    $stmt = $conn->prepare("
                        UPDATE tickets t
                        JOIN events e ON t.event_id = e.event_id
                        SET t.status = 'confirmed'
                        WHERE t.ticket_id = ? AND e.organizer_id = ? AND t.status = 'pending'
                    ");
                    $stmt->bind_param("ii", $ticketId, $currentUser['user_id']);
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $message = "Attendee approved successfully!";
                        $messageType = "success";
                    }
                    break;
                    
                case 'reject':
                    $stmt = $conn->prepare("
                        UPDATE tickets t
                        JOIN events e ON t.event_id = e.event_id
                        SET t.status = 'cancelled'
                        WHERE t.ticket_id = ? AND e.organizer_id = ?
                    ");
                    $stmt->bind_param("ii", $ticketId, $currentUser['user_id']);
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $message = "Attendee registration cancelled!";
                        $messageType = "success";
                    }
                    break;
                    
                case 'check_in':
                    $stmt = $conn->prepare("
                        UPDATE tickets t
                        JOIN events e ON t.event_id = e.event_id
                        SET t.checked_in = 1, t.check_in_time = NOW()
                        WHERE t.ticket_id = ? AND e.organizer_id = ? AND t.status = 'confirmed'
                    ");
                    $stmt->bind_param("ii", $ticketId, $currentUser['user_id']);
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $message = "Attendee checked in successfully!";
                        $messageType = "success";
                    }
                    break;
                    
                case 'check_out':
                    $stmt = $conn->prepare("
                        UPDATE tickets t
                        JOIN events e ON t.event_id = e.event_id
                        SET t.checked_in = 0, t.check_in_time = NULL
                        WHERE t.ticket_id = ? AND e.organizer_id = ?
                    ");
                    $stmt->bind_param("ii", $ticketId, $currentUser['user_id']);
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $message = "Attendee checked out successfully!";
                        $messageType = "success";
                    }
                    break;
            }
        } catch (Exception $e) {
            error_log("Attendee action error: " . $e->getMessage());
            $message = "An error occurred while processing the request.";
            $messageType = "danger";
        }
    }
}

// Build attendees query with filters
$whereConditions = ["e.organizer_id = ?"];
$params = [$currentUser['user_id']];
$paramTypes = "i";

if ($eventId) {
    $whereConditions[] = "e.event_id = ?";
    $params[] = $eventId;
    $paramTypes .= "i";
}

$statusFilter = $_GET['status'] ?? '';
if ($statusFilter && in_array($statusFilter, ['pending', 'confirmed', 'cancelled'])) {
    $whereConditions[] = "t.status = ?";
    $params[] = $statusFilter;
    $paramTypes .= "s";
}

$checkedInFilter = $_GET['checked_in'] ?? '';
if ($checkedInFilter !== '') {
    $whereConditions[] = "t.checked_in = ?";
    $params[] = intval($checkedInFilter);
    $paramTypes .= "i";
}

// Get attendees with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$attendees = [];
$totalAttendees = 0;

try {
    // Get total count
    $countQuery = "
        SELECT COUNT(*) as total
        FROM tickets t
        JOIN events e ON t.event_id = e.event_id
        JOIN users u ON t.user_id = u.user_id
        WHERE " . implode(' AND ', $whereConditions);
    
    $stmt = $conn->prepare($countQuery);
    if (!empty($params)) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    $stmt->execute();
    $totalAttendees = $stmt->get_result()->fetch_assoc()['total'];
    
    // Get attendees data
    $attendeesQuery = "
        SELECT t.*, u.first_name, u.last_name, u.email, u.phone_number, u.department,
               e.title as event_title, e.start_datetime, e.price as event_price
        FROM tickets t
        JOIN events e ON t.event_id = e.event_id
        JOIN users u ON t.user_id = u.user_id
        WHERE " . implode(' AND ', $whereConditions) . "
        ORDER BY t.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($attendeesQuery);
    $params[] = $limit;
    $params[] = $offset;
    $paramTypes .= "ii";
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $attendees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    error_log("Attendees fetch error: " . $e->getMessage());
}

// Calculate pagination
$totalPages = ceil($totalAttendees / $limit);

// Get attendee statistics
$attendeeStats = [];
try {
    $statsQuery = "
        SELECT 
            COUNT(*) as total_attendees,
            SUM(CASE WHEN t.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_attendees,
            SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) as pending_attendees,
            SUM(CASE WHEN t.checked_in = 1 THEN 1 ELSE 0 END) as checked_in_attendees,
            SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as total_revenue
        FROM tickets t
        JOIN events e ON t.event_id = e.event_id
        WHERE e.organizer_id = ?
    ";
    
    if ($eventId) {
        $statsQuery .= " AND e.event_id = ?";
        $stmt = $conn->prepare($statsQuery);
        $stmt->bind_param("ii", $currentUser['user_id'], $eventId);
    } else {
        $stmt = $conn->prepare($statsQuery);
        $stmt->bind_param("i", $currentUser['user_id']);
    }
    
    $stmt->execute();
    $attendeeStats = $stmt->get_result()->fetch_assoc();
    
} catch (Exception $e) {
    error_log("Attendee stats error: " . $e->getMessage());
    $attendeeStats = [
        'total_attendees' => 0,
        'confirmed_attendees' => 0,
        'pending_attendees' => 0,
        'checked_in_attendees' => 0,
        'total_revenue' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendees Management - Organizer Dashboard | EMS</title>
    
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
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .stat-icon.primary { color: #667eea; }
        .stat-icon.success { color: #4CAF50; }
        .stat-icon.warning { color: #ff9800; }
        .stat-icon.info { color: #2196F3; }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.9rem;
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
        
        .form-select {
            padding: 0.5rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-filter {
            padding: 0.5rem 1rem;
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
        
        /* 👥 Attendees Table */
        .attendees-container {
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .table-header {
            background: var(--primary-gradient);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
        }
        
        .attendees-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .attendees-table th,
        .attendees-table td {
                       padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .attendees-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .attendees-table tr:hover {
            background: #f8f9fa;
        }
        
        .attendee-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .attendee-avatar {
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
        
        .attendee-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .attendee-details small {
            color: var(--text-muted);
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
        
        .checkin-badge {
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .checkin-yes {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .checkin-no {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 0.3rem 0.6rem;
            border: none;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .btn-approve {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }
        
        .btn-reject {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid #f44336;
        }
        
        .btn-checkin {
            background: rgba(33, 150, 243, 0.1);
            color: #2196F3;
            border: 1px solid #2196F3;
        }
        
        .btn-checkout {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
            border: 1px solid #6c757d;
        }
        
        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
        }
        
        /* 📄 Pagination */
        .pagination-container {
            padding: 1.5rem;
            background: white;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .pagination-info {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .pagination {
            display: flex;
            gap: 0.5rem;
        }
        
        .page-link {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-dark);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .page-link:hover,
        .page-link.active {
            background: var(--primary-gradient);
            color: white;
            border-color: transparent;
        }
        
        /* 🚨 Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: none;
        }
        
        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border-left: 4px solid #4CAF50;
        }
        
        .alert-danger {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border-left: 4px solid #f44336;
        }
        
        /* 📱 Responsive Design */
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .stats-row .col-md-3 {
                margin-bottom: 1rem;
            }
            
            .attendees-table {
                font-size: 0.8rem;
            }
            
            .attendees-table th,
            .attendees-table td {
                padding: 0.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .pagination-container {
                flex-direction: column;
                gap: 1rem;
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
    </style>
</head>
<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="page-title">👥 Attendees Management</h1>
                    <p class="page-subtitle">
                        <?= $event ? 'Managing attendees for: ' . htmlspecialchars($event['title']) : 'Manage all your event attendees' ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="breadcrumb-nav">
                        <a href="../../dashboard/">Dashboard</a> / 
                        <a href="../organizer/dashboard.php">Organizer</a> / 
                        <span>Attendees</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Attendee Statistics -->
        <div class="row stats-row fade-in">
            <div class="col-md-3">
                <div class="stat-card primary">
                    <i class="fas fa-users stat-icon primary"></i>
                    <div class="stat-number"><?= $attendeeStats['total_attendees'] ?></div>
                    <div class="stat-label">Total Attendees</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card success">
                    <i class="fas fa-check-circle stat-icon success"></i>
                    <div class="stat-number"><?= $attendeeStats['confirmed_attendees'] ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card warning">
                    <i class="fas fa-clock stat-icon warning"></i>
                    <div class="stat-number"><?= $attendeeStats['pending_attendees'] ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card info">
                    <i class="fas fa-sign-in-alt stat-icon info"></i>
                    <div class="stat-number"><?= $attendeeStats['checked_in_attendees'] ?></div>
                    <div class="stat-label">Checked In</div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> fade-in">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filters-container fade-in">
            <h5 class="filters-title">
                <i class="fas fa-filter me-2"></i>
                Filter Attendees
            </h5>
            
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Event</label>
                    <select name="event_id" class="form-select">
                        <option value="">All Events</option>
                        <?php foreach ($organizerEvents as $orgEvent): ?>
                            <option value="<?= $orgEvent['event_id'] ?>" 
                                    <?= $eventId == $orgEvent['event_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($orgEvent['title']) ?> 
                                (<?= $orgEvent['total_attendees'] ?> attendees)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Check-in Status</label>
                    <select name="checked_in" class="form-select">
                        <option value="">All</option>
                        <option value="1" <?= $checkedInFilter === '1' ? 'selected' : '' ?>>Checked In</option>
                        <option value="0" <?= $checkedInFilter === '0' ? 'selected' : '' ?>>Not Checked In</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-search me-1"></i>
                            Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Attendees Table -->
        <div class="attendees-container fade-in">
            <div class="table-header">
                <h3 class="table-title">
                    <i class="fas fa-users me-2"></i>
                    Attendees (<?= $totalAttendees ?>)
                </h3>
                <div>
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" 
                       class="btn btn-light btn-sm">
                        <i class="fas fa-download me-1"></i>
                        Export CSV
                    </a>
                </div>
            </div>
            
            <?php if (empty($attendees)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No attendees found matching your criteria.</p>
                </div>
            <?php else: ?>
                <table class="attendees-table">
                    <thead>
                        <tr>
                            <th>Attendee</th>
                            <th>Event</th>
                            <th>Registration Date</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Check-in</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendees as $attendee): ?>
                            <tr>
                                <td>
                                    <div class="attendee-info">
                                        <div class="attendee-avatar">
                                            <?= strtoupper(substr($attendee['first_name'], 0, 1) . substr($attendee['last_name'], 0, 1)) ?>
                                        </div>
                                        <div class="attendee-details">
                                            <h6><?= htmlspecialchars($attendee['first_name'] . ' ' . $attendee['last_name']) ?></h6>
                                            <small><?= htmlspecialchars($attendee['email']) ?></small>
                                            <?php if ($attendee['department']): ?>
                                                <br><small><?= htmlspecialchars($attendee['department']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($attendee['event_title']) ?></strong>
                                                                     <br><small><?= date('M j, Y', strtotime($attendee['start_datetime'])) ?></small>
                                </td>
                                <td>
                                    <?= date('M j, Y g:i A', strtotime($attendee['created_at'])) ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $attendee['status'] ?>">
                                        <?= ucfirst($attendee['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $attendee['payment_status'] ?>">
                                        <?= ucfirst($attendee['payment_status']) ?>
                                    </span>
                                    <?php if ($attendee['price'] > 0): ?>
                                        <br><small>K<?= number_format($attendee['price'], 2) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="checkin-badge checkin-<?= $attendee['checked_in'] ? 'yes' : 'no' ?>">
                                        <?= $attendee['checked_in'] ? 'Checked In' : 'Not Checked In' ?>
                                    </span>
                                    <?php if ($attendee['check_in_time']): ?>
                                        <br><small><?= date('M j, g:i A', strtotime($attendee['check_in_time'])) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($attendee['status'] === 'pending'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="ticket_id" value="<?= $attendee['ticket_id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn-action btn-approve" 
                                                        onclick="return confirm('Approve this attendee?')">
                                                    <i class="fas fa-check"></i>
                                                    Approve
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="ticket_id" value="<?= $attendee['ticket_id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn-action btn-reject" 
                                                        onclick="return confirm('Reject this attendee?')">
                                                    <i class="fas fa-times"></i>
                                                    Reject
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($attendee['status'] === 'confirmed'): ?>
                                            <?php if (!$attendee['checked_in']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="ticket_id" value="<?= $attendee['ticket_id'] ?>">
                                                    <input type="hidden" name="action" value="check_in">
                                                    <button type="submit" class="btn-action btn-checkin">
                                                        <i class="fas fa-sign-in-alt"></i>
                                                        Check In
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="ticket_id" value="<?= $attendee['ticket_id'] ?>">
                                                    <input type="hidden" name="action" value="check_out">
                                                    <button type="submit" class="btn-action btn-checkout">
                                                        <i class="fas fa-sign-out-alt"></i>
                                                        Check Out
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?= ($page - 1) * $limit + 1 ?> to <?= min($page * $limit, $totalAttendees) ?> 
                            of <?= $totalAttendees ?> attendees
                        </div>
                        
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                                   class="page-link">
                                    <i class="fas fa-chevron-left"></i>
                                    Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                   class="page-link <?= $i === $page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                                   class="page-link">
                                    Next
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-refresh page every 30 seconds for real-time updates
        setInterval(function() {
            // Only refresh if no forms are being submitted
            if (!document.querySelector('form:target')) {
                location.reload();
            }
        }, 30000);

        // Confirmation dialogs for actions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const action = this.querySelector('input[name="action"]')?.value;
                let message = '';
                
                switch(action) {
                    case 'approve':
                        message = 'Are you sure you want to approve this attendee?';
                        break;
                    case 'reject':
                        message = 'Are you sure you want to reject this attendee? This action cannot be undone.';
                        break;
                    case 'check_in':
                        message = 'Check in this attendee?';
                        break;
                    case 'check_out':
                        message = 'Check out this attendee?';
                        break;
                }
                
                if (message && !confirm(message)) {
                    e.preventDefault();
                }
            });
        });

        console.log('👥 Attendees Management Page Loaded');
    </script>
</body>
</html>
