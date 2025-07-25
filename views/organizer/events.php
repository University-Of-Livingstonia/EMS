<?php

/**
 * 🎪 My Events - EMS Organizer
 * Ekwendeni Mighty Campus Event Management System
 * Complete Event Management Hub! 🎭
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

// Deny access and redirect if user is not verified (only for organizer role)
if ($currentUser['role'] === 'organizer' && (!isset($currentUser['email_verified']) || $currentUser['email_verified'] != 1)) {
    header('Location: verify_email.php');
    exit;
}

$organizerId = $currentUser['user_id'];
$currentPage = basename($_SERVER['PHP_SELF']);

// Handle event actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $eventId = $_POST['event_id'] ?? 0;

    switch ($action) {
        case 'delete':
            try {
                // Check if event belongs to organizer
                $stmt = $conn->prepare("SELECT event_id FROM events WHERE event_id = ? AND organizer_id = ?");
                $stmt->bind_param("ii", $eventId, $organizerId);
                $stmt->execute();

                if ($stmt->get_result()->num_rows > 0) {
                    // Delete related tickets first
                    $stmt = $conn->prepare("DELETE FROM tickets WHERE event_id = ?");
                    $stmt->bind_param("i", $eventId);
                    $stmt->execute();

                    // Delete event
                    $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
                    $stmt->bind_param("i", $eventId);
                    $stmt->execute();

                    $success = "Event deleted successfully!";
                } else {
                    $error = "Event not found or access denied.";
                }
            } catch (Exception $e) {
                $error = "Error deleting event: " . $e->getMessage();
            }
            break;

        case 'duplicate':
            try {
                // Get original event
                $stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ? AND organizer_id = ?");
                $stmt->bind_param("ii", $eventId, $organizerId);
                $stmt->execute();
                $originalEvent = $stmt->get_result()->fetch_assoc();

                if ($originalEvent) {
                    // Create duplicate
                    $stmt = $conn->prepare("
                        INSERT INTO events (title, description, start_datetime, end_datetime, location, 
                                          max_attendees, ticket_price, organizer_id, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())
                    ");

                    $newTitle = $originalEvent['title'] . ' (Copy)';
                    $stmt->bind_param(
                        "sssssiid",
                        $newTitle,
                        $originalEvent['description'],
                        $originalEvent['start_datetime'],
                        $originalEvent['end_datetime'],
                        $originalEvent['location'],
                        $originalEvent['max_attendees'],
                        $originalEvent['ticket_price'],
                        $organizerId
                    );

                    $stmt->execute();
                    $success = "Event duplicated successfully!";
                } else {
                    $error = "Event not found or access denied.";
                }
            } catch (Exception $e) {
                $error = "Error duplicating event: " . $e->getMessage();
            }
            break;
    }
}

// Get filter parameters
$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, $_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$whereConditions = ["organizer_id = ?"];
$params = [$organizerId];
$paramTypes = "i";

if ($status !== 'all') {
    $whereConditions[] = "status = ?";
    $params[] = $status;
    $paramTypes .= "s";
}

if (!empty($search)) {
    $whereConditions[] = "(title LIKE ? OR description LIKE ? OR location LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $paramTypes .= "sss";
}

$whereClause = implode(" AND ", $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM events WHERE $whereClause";
$stmt = $conn->prepare($countQuery);
$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$totalEvents = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalEvents / $limit);

// Get events with statistics
$eventsQuery = "
    SELECT e.*, 
           COUNT(t.ticket_id) as total_registrations,
           SUM(CASE WHEN t.payment_status = 'completed' THEN 1 ELSE 0 END) as paid_registrations,
           SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as event_revenue
    FROM events e
    LEFT JOIN tickets t ON e.event_id = t.event_id
    WHERE $whereClause
    GROUP BY e.event_id
    ORDER BY e.$sort $order
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$paramTypes .= "ii";

$stmt = $conn->prepare($eventsQuery);
$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get organizer statistics
$organizerStats = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_events,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_events,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_events,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_events
        FROM events 
        WHERE organizer_id = ?
    ");
    $stmt->bind_param("i", $organizerId);
    $stmt->execute();
    $organizerStats = $stmt->get_result()->fetch_assoc();
} catch (Exception $e) {
    error_log("Organizer stats error: " . $e->getMessage());
    $organizerStats = ['total_events' => 0, 'approved_events' => 0, 'pending_events' => 0, 'draft_events' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Events - EMS Organizer</title>

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --organizer-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --organizer-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --organizer-success: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            --organizer-warning: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            --organizer-danger: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            --organizer-info: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
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

        /* 🎪 Organizer Sidebar */
        .organizer-sidebar {
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
            background: var(--organizer-primary);
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

        .organizer-nav {
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

        .organizer-nav-item {
            margin: 0.3rem 0;
        }

        .organizer-nav-link {
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

        .organizer-nav-link:hover,
        .organizer-nav-link.active {
            background: var(--sidebar-hover);
            color: white;
            transform: translateX(10px);
        }

        .organizer-nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--organizer-secondary);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .organizer-nav-link:hover::before,
        .organizer-nav-link.active::before {
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
            background: var(--organizer-danger);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* 📱 Main Content */
        .organizer-main {
            margin-left: 300px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        /* 🎯 Organizer Top Bar */
        .organizer-topbar {
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

        .organizer-title {
            font-size: 2rem;
            font-weight: 800;
            background: var(--organizer-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }

        .organizer-user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .organizer-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--organizer-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .organizer-user-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }

        .organizer-user-details small {
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* 📊 Dashboard Content */
        .organizer-content {
            padding: 2rem;
        }

        /* 🎯 Stats Cards */
        .stats-cards {
            margin-bottom: 2rem;
        }

        .stat-card {
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

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
        }

        .stat-card.primary::before {
            background: var(--organizer-primary);
        }

        .stat-card.success::before {
            background: var(--organizer-success);
        }

        .stat-card.warning::before {
            background: var(--organizer-warning);
        }

        .stat-card.info::before {
            background: var(--organizer-info);
        }

        .stat-card:hover {
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

        .stat-icon.primary {
            background: var(--organizer-primary);
        }

        .stat-icon.success {
            background: var(--organizer-success);
        }

        .stat-icon.warning {
            background: var(--organizer-warning);
        }

        .stat-icon.info {
            background: var(--organizer-info);
        }

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

        /* 🎪 Content Cards */
        .organizer-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .organizer-card:hover {
            box-shadow: var(--card-hover-shadow);
            transform: translateY(-5px);
        }

        .organizer-card-header {
            padding: 2rem 2rem 1rem 2rem;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }

        .organizer-card-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }

        .organizer-card-body {
            padding: 2rem;
        }

        /* 🔍 Filters & Search */
        .filters-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }

        .filter-group {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-label {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .filter-input {
            padding: 0.7rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .filter-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-btn {
            padding: 0.7rem 1.5rem;
            background: var(--organizer-primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        /* 🎭 Event Cards */
        .event-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            position: relative;
            border-left: 5px solid;
        }

        .event-card.approved {
            border-left-color: #4CAF50;
        }

        .event-card.pending {
            border-left-color: #ff9800;
        }

        .event-card.draft {
            border-left-color: #6c757d;
        }

        .event-card.rejected {
            border-left-color: #f44336;
        }

        .event-card:hover {
            transform: translateX(10px);
            box-shadow: var(--card-hover-shadow);
        }

        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .event-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 0.5rem 0;
        }

        .event-description {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.5;
            margin: 0;
        }

        .event-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-approved {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border: 2px solid #4CAF50;
        }

        .status-pending {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
            border: 2px solid #ff9800;
        }

        .status-draft {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
            border: 2px solid #6c757d;
        }

        .status-rejected {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 2px solid #f44336;
        }

        .event-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .event-meta-item {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .event-meta-item i {
            color: #667eea;
            width: 18px;
            font-size: 1rem;
        }

        .event-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .event-stat {
            text-align: center;
        }

        .event-stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.3rem;
        }

        .event-stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
        }

        .event-actions {
            display: flex;
            gap: 0.7rem;
            flex-wrap: wrap;
        }

        .organizer-btn {
            padding: 0.6rem 1.2rem;
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

        .organizer-btn-primary {
            background: var(--organizer-primary);
            color: white;
        }

        .organizer-btn-success {
            background: var(--organizer-success);
            color: white;
        }

        .organizer-btn-warning {
            background: var(--organizer-warning);
            color: white;
        }

        .organizer-btn-danger {
            background: var(--organizer-danger);
            color: white;
        }

        .organizer-btn-info {
            background: var(--organizer-info);
            color: white;
        }

        .organizer-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        /* 📄 Pagination */
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
        }

        .pagination {
            display: flex;
            gap: 0.5rem;
            align-items: center;
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
            background: var(--organizer-primary);
            color: white;
            transform: translateY(-2px);
        }

        .pagination .current {
            background: var(--organizer-primary);
            color: white;
            border: 2px solid transparent;
        }

        /* 📱 Responsive Design */
        @media (max-width: 768px) {
            .organizer-sidebar {
                transform: translateX(-100%);
            }

            .organizer-sidebar.show {
                transform: translateX(0);
            }

            .organizer-main {
                margin-left: 0;
            }

            .organizer-topbar {
                padding: 1rem;
            }

            .organizer-content {
                padding: 1rem;
            }

            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }

            .event-meta {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .event-stats {
                grid-template-columns: repeat(2, 1fr);
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

        /* 🎯 Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 5rem;
            margin-bottom: 2rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .empty-state p {
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }

        /* 🚨 Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }

        .alert-danger {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid #f44336;
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
                    <a href="events.php" class="organizer-nav-link active">
                        <i class="fas fa-calendar-alt nav-icon"></i>
                        <span class="nav-text">My Events</span>
                        <span class="nav-badge"><?= $organizerStats['total_events'] ?></span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="pending_events.php" class="organizer-nav-link">
                        <i class="fas fa-clock nav-icon"></i>
                        <span class="nav-text">Pending Events</span>
                        <span class="nav-badge"><?= $organizerStats['pending_events'] ?></span>
                    </a>
                </div>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Analytics</div>
                <div class="organizer-nav-item">
                    <a href="revenue.php" class="organizer-nav-link">
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
                    <a href="create_event.php" class="organizer-nav-link">
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
                <h1 class="organizer-title">My Events</h1>
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
            <!-- 🚨 Alerts -->
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- 🎯 Stats Cards -->
            <div class="stats-cards">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="stat-card primary fade-in-up">
                            <div class="stat-header">
                                <div class="stat-icon primary">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            </div>
                            <div class="stat-number"><?= $organizerStats['total_events'] ?></div>
                            <div class="stat-label">Total Events</div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="stat-card success fade-in-up">
                            <div class="stat-header">
                                <div class="stat-icon success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                            <div class="stat-number"><?= $organizerStats['approved_events'] ?></div>
                            <div class="stat-label">Approved Events</div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="stat-card warning fade-in-up">
                            <div class="stat-header">
                                <div class="stat-icon warning">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                            <div class="stat-number"><?= $organizerStats['pending_events'] ?></div>
                            <div class="stat-label">Pending Events</div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="stat-card info fade-in-up">
                            <div class="stat-header">
                                <div class="stat-icon info">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                            </div>
                            <div class="stat-number"><?= $organizerStats['draft_events'] ?></div>
                            <div class="stat-label">Draft Events</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🔍 Filters & Search -->
            <div class="filters-section">
                <form method="GET" class="filter-group">
                    <div class="filter-item">
                        <label class="filter-label">Search Events</label>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search by title, description, or location..." class="filter-input" style="width: 300px;">
                    </div>

                    <div class="filter-item">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-input">
                            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label class="filter-label">Sort By</label>
                        <select name="sort" class="filter-input">
                            <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Date Created</option>
                            <option value="start_datetime" <?= $sort === 'start_datetime' ? 'selected' : '' ?>>Event Date</option>
                            <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Title</option>
                            <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Status</option>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label class="filter-label">Order</label>
                        <select name="order" class="filter-input">
                            <option value="DESC" <?= $order === 'DESC' ? 'selected' : '' ?>>Descending</option>
                            <option value="ASC" <?= $order === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label class="filter-label">&nbsp;</label>
                        <button type="submit" class="filter-btn">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>

                    <div class="filter-item">
                        <label class="filter-label">&nbsp;</label>
                        <a href="events.php" class="organizer-btn organizer-btn-info">
                            <i class="fas fa-refresh"></i> Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- 🎪 Events List -->
            <div class="organizer-card fade-in-up">
                <div class="organizer-card-header">
                    <h3 class="organizer-card-title">
                        <i class="fas fa-calendar-alt"></i>
                        My Events (<?= $totalEvents ?> total)
                    </h3>
                    <div class="card-actions">
                        <a href="create_event.php" class="organizer-btn organizer-btn-primary">
                            <i class="fas fa-plus"></i> Create New Event
                        </a>
                    </div>
                </div>
                <div class="organizer-card-body">
                    <?php if (!empty($events)): ?>
                        <?php foreach ($events as $event): ?>
                            <div class="event-card <?= $event['status'] ?>">
                                <div class="event-header">
                                    <div>
                                        <h3 class="event-title"><?= htmlspecialchars($event['title']) ?></h3>
                                        <p class="event-description"><?= htmlspecialchars(substr($event['description'], 0, 150)) ?>...</p>
                                    </div>
                                    <span class="event-status status-<?= $event['status'] ?>">
                                        <?= ucfirst($event['status']) ?>
                                    </span>
                                </div>

                                <div class="event-meta">
                                    <div class="event-meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?= date('M j, Y', strtotime($event['start_datetime'])) ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-clock"></i>
                                        <span><?= date('g:i A', strtotime($event['start_datetime'])) ?></span>
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
                                        <i class="fas fa-money-bill-wave"></i>
                                        <span>K<?= number_format($event['ticket_price'], 0) ?> per ticket</span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-calendar-plus"></i>
                                        <span>Created: <?= date('M j, Y', strtotime($event['created_at'])) ?></span>
                                    </div>
                                </div>

                                <div class="event-stats">
                                    <div class="event-stat">
                                        <div class="event-stat-number"><?= $event['total_registrations'] ?></div>
                                        <div class="event-stat-label">Total Registrations</div>
                                    </div>
                                    <div class="event-stat">
                                        <div class="event-stat-number"><?= $event['paid_registrations'] ?></div>
                                        <div class="event-stat-label">Paid Registrations</div>
                                    </div>
                                    <div class="event-stat">
                                        <div class="event-stat-number">K<?= number_format($event['event_revenue'], 0) ?></div>
                                        <div class="event-stat-label">Revenue Generated</div>
                                    </div>
                                    <div class="event-stat">
                                        <div class="event-stat-number"><?= $event['max_attendees'] - $event['total_registrations'] ?></div>
                                        <div class="event-stat-label">Spots Available</div>
                                    </div>

                                    <div class="event-actions">
                                        <a href="view-event.php?id=<?= $event['event_id'] ?>" class="organizer-btn organizer-btn-primary">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>

                                        <?php if ($event['status'] === 'draft' || $event['status'] === 'rejected'): ?>
                                            <a href="edit-event.php?id=<?= $event['event_id'] ?>" class="organizer-btn organizer-btn-warning">
                                                <i class="fas fa-edit"></i> Edit Event
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($event['status'] === 'draft'): ?>
                                            <a href="submit-event.php?id=<?= $event['event_id'] ?>" class="organizer-btn organizer-btn-success">
                                                <i class="fas fa-paper-plane"></i> Submit for Approval
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($event['total_registrations'] > 0): ?>
                                            <a href="attendees.php?event_id=<?= $event['event_id'] ?>" class="organizer-btn organizer-btn-info">
                                                <i class="fas fa-users"></i> View Attendees (<?= $event['total_registrations'] ?>)
                                            </a>
                                        <?php endif; ?>

                                        <button onclick="duplicateEvent(<?= $event['event_id'] ?>)" class="organizer-btn organizer-btn-info">
                                            <i class="fas fa-copy"></i> Duplicate
                                        </button>

                                        <?php if ($event['status'] === 'draft' || $event['total_registrations'] == 0): ?>
                                            <button onclick="deleteEvent(<?= $event['event_id'] ?>)" class="organizer-btn organizer-btn-danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($event['status'] === 'approved'): ?>
                                            <a href="qr-checkin.php?event_id=<?= $event['event_id'] ?>" class="organizer-btn organizer-btn-success">
                                                <i class="fas fa-qrcode"></i> QR Check-in
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- 📄 Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <div class="pagination-wrapper">
                                    <div class="pagination">
                                        <?php if ($page > 1): ?>
                                            <a href="?page=<?= $page - 1 ?>&status=<?= $status ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&order=<?= $order ?>">
                                                <i class="fas fa-chevron-left"></i> Previous
                                            </a>
                                        <?php endif; ?>

                                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                            <?php if ($i == $page): ?>
                                                <span class="current"><?= $i ?></span>
                                            <?php else: ?>
                                                <a href="?page=<?= $i ?>&status=<?= $status ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&order=<?= $order ?>"><?= $i ?></a>
                                            <?php endif; ?>
                                        <?php endfor; ?>

                                        <?php if ($page < $totalPages): ?>
                                            <a href="?page=<?= $page + 1 ?>&status=<?= $status ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&order=<?= $order ?>">
                                                Next <i class="fas fa-chevron-right"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <h3>No Events Found</h3>
                                <p>
                                    <?php if (!empty($search) || $status !== 'all'): ?>
                                        No events match your current filters. Try adjusting your search criteria.
                                    <?php else: ?>
                                        You haven't created any events yet. Start by creating your first event!
                                    <?php endif; ?>
                                </p>
                                <a href="create_event.php" class="organizer-btn organizer-btn-primary">
                                    <i class="fas fa-plus-circle"></i> Create Your First Event
                                </a>
                            </div>
                        <?php endif; ?>
                            </div>
                </div>
            </div>
        </div>

        <!-- 📱 Scripts -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

        <script>
            // 🎯 Event Management Functions
            function toggleSidebar() {
                const sidebar = document.getElementById('organizerSidebar');
                const main = document.getElementById('organizerMain');

                if (window.innerWidth <= 768) {
                    sidebar.classList.toggle('show');
                } else {
                    sidebar.classList.toggle('collapsed');
                    main.classList.toggle('expanded');
                }
            }

            function deleteEvent(eventId) {
                if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="event_id" value="${eventId}">
                `;
                    document.body.appendChild(form);
                    form.submit();
                }
            }

            function duplicateEvent(eventId) {
                if (confirm('Do you want to create a copy of this event?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                    <input type="hidden" name="action" value="duplicate">
                    <input type="hidden" name="event_id" value="${eventId}">
                `;
                    document.body.appendChild(form);
                    form.submit();
                }
            }

            // 🎨 Initialize animations
            document.addEventListener('DOMContentLoaded', function() {
                // Animate cards on scroll
                const observerOptions = {
                    threshold: 0.1,
                    rootMargin: '0px 0px -50px 0px'
                };

                const observer = new IntersectionObserver(function(entries) {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('fade-in-up');
                        }
                    });
                }, observerOptions);

                // Observe all event cards
                const eventCards = document.querySelectorAll('.event-card');
                eventCards.forEach(card => observer.observe(card));

                // Auto-hide alerts after 5 seconds
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    setTimeout(() => {
                        alert.style.opacity = '0';
                        alert.style.transform = 'translateY(-20px)';
                        setTimeout(() => alert.remove(), 300);
                    }, 5000);
                });
            });

            // 📱 Responsive handling
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    document.getElementById('organizerSidebar').classList.remove('show');
                }
            });
        </script>
</body>

</html>