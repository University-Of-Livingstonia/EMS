<?php

/**
 * 🎪 Admin Events Management - EMS
 * Ekwendeni Mighty Campus Event Management System
 * Complete Events Management System! 🎭
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

// Handle event actions
$message = '';
$messageType = '';

if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'approve_event':
                    $eventId = (int)$_POST['event_id'];

                    $stmt = $conn->prepare("UPDATE events SET status = 'approved', approved_at = NOW(), approved_by = ? WHERE event_id = ?");
                    $stmt->bind_param("ii", $currentUser['user_id'], $eventId);
                    $stmt->execute();

                    $message = 'Event approved successfully!';
                    $messageType = 'success';
                    break;

                case 'reject_event':
                    $eventId = (int)$_POST['event_id'];
                    $reason = trim($_POST['rejection_reason']);

                    $stmt = $conn->prepare("UPDATE events SET status = 'rejected', rejection_reason = ?, rejected_at = NOW(), rejected_by = ? WHERE event_id = ?");
                    $stmt->bind_param("sii", $reason, $currentUser['user_id'], $eventId);
                    $stmt->execute();

                    $message = 'Event rejected successfully!';
                    $messageType = 'success';
                    break;

                case 'feature_event':
                    $eventId = (int)$_POST['event_id'];
                    $featured = (int)$_POST['featured'];

                    $stmt = $conn->prepare("UPDATE events SET featured = ? WHERE event_id = ?");
                    $stmt->bind_param("ii", $featured, $eventId);
                    $stmt->execute();

                    $message = $featured ? 'Event featured successfully!' : 'Event unfeatured successfully!';
                    $messageType = 'success';
                    break;

                case 'delete_event':
                    $eventId = (int)$_POST['event_id'];

                    // Check if event has tickets
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tickets WHERE event_id = ?");
                    $stmt->bind_param("i", $eventId);
                    $stmt->execute();
                    $ticketCount = $stmt->get_result()->fetch_assoc()['count'];

                    if ($ticketCount > 0) {
                        throw new Exception('Cannot delete event with existing tickets.');
                    }

                    $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
                    $stmt->bind_param("i", $eventId);
                    $stmt->execute();

                    $message = 'Event deleted successfully!';
                    $messageType = 'success';
                    break;
            }
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$categoryFilter = $_GET['category'] ?? 'all';
$organizerFilter = $_GET['organizer'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Build query
$whereConditions = [];
$params = [];
$paramTypes = "";

if ($statusFilter !== 'all') {
    $whereConditions[] = "e.status = ?";
    $params[] = $statusFilter;
    $paramTypes .= "s";
}

if ($categoryFilter !== 'all') {
    $whereConditions[] = "e.category = ?";
    $params[] = $categoryFilter;
    $paramTypes .= "s";
}

if ($organizerFilter !== 'all') {
    $whereConditions[] = "e.organizer_id = ?";
    $params[] = $organizerFilter;
    $paramTypes .= "i";
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $paramTypes .= "sss";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination
$totalEvents = 0;
try {
    $countQuery = "SELECT COUNT(*) as total FROM events e $whereClause";
    if (!empty($params)) {
        $stmt = $conn->prepare($countQuery);
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $totalEvents = $stmt->get_result()->fetch_assoc()['total'];
    } else {
        $totalEvents = $conn->query($countQuery)->fetch_assoc()['total'];
    }
} catch (Exception $e) {
    error_log("Count query error: " . $e->getMessage());
}

$totalPages = ceil($totalEvents / $limit);

// Get events with organizer info and statistics
$events = [];
try {
    $query = "
        SELECT 
            e.*,
            u.first_name, u.last_name, u.email as organizer_email,
            COUNT(DISTINCT t.ticket_id) as total_tickets,
            SUM(CASE WHEN t.payment_status = 'completed' THEN 1 ELSE 0 END) as paid_tickets,
            SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as total_revenue
        FROM events e
        LEFT JOIN users u ON e.organizer_id = u.user_id
        LEFT JOIN tickets t ON e.event_id = t.event_id
        $whereClause
        GROUP BY e.event_id
        ORDER BY e.$sortBy $sortOrder
        LIMIT ? OFFSET ?
    ";

    $allParams = array_merge($params, [$limit, $offset]);
    $allParamTypes = $paramTypes . "ii";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($allParamTypes, ...$allParams);
    $stmt->execute();
    $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Events query error: " . $e->getMessage());
}

// Get organizers for filter
$organizers = [];
try {
    $stmt = $conn->query("
        SELECT DISTINCT u.user_id, u.first_name, u.last_name 
        FROM users u 
        JOIN events e ON u.user_id = e.organizer_id 
        ORDER BY u.first_name, u.last_name
    ");
    $organizers = $stmt->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Organizers query error: " . $e->getMessage());
}

// Get event categories
$categories = [];
try {
    $stmt = $conn->query("SELECT DISTINCT category FROM events WHERE category IS NOT NULL ORDER BY category");
    $categories = $stmt->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Categories query error: " . $e->getMessage());
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events Management - Admin | EMS</title>

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --admin-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --admin-success: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            --admin-warning: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            --admin-danger: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            --admin-info: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            --content-bg: #f8f9fa;
            --card-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --border-color: #e9ecef;
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

        .page-header {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--admin-primary);
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

        .filters-section {
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .filters-row {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .filter-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-select,
        .filter-input {
            padding: 0.5rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            min-width: 150px;
        }

        .filter-select:focus,
        .filter-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-input {
            min-width: 250px;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1.2rem;
        }

        .btn-primary {
            background: var(--admin-primary);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 2rem;
        }

        .event-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }

        .event-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        .event-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
        }

        .event-card.approved::before {
            background: var(--admin-success);
        }

        .event-card.pending::before {
            background: var(--admin-warning);
        }

        .event-card.rejected::before {
            background: var(--admin-danger);
        }

        .event-card.draft::before {
            background: var(--admin-info);
        }

        .event-image {
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }

        .event-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .event-status-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-approved {
            background: rgba(76, 175, 80, 0.9);
            color: white;
        }

        .status-pending {
            background: rgba(255, 152, 0, 0.9);
            color: white;
        }

        .status-rejected {
            background: rgba(244, 67, 54, 0.9);
            color: white;
        }

        .status-draft {
            background: rgba(33, 150, 243, 0.9);
            color: white;
        }

        .featured-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: rgba(255, 215, 0, 0.9);
            color: #333;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .event-content {
            padding: 1.5rem;
        }

        .event-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .event-organizer {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .event-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .meta-item i {
            color: #667eea;
            width: 16px;
        }

        .event-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .event-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .btn-approve {
            background: var(--admin-success);
            color: white;
        }

        .btn-reject {
            background: var(--admin-danger);
            color: white;
        }

        .btn-feature {
            background: var(--admin-warning);
            color: white;
        }

        .btn-view {
            background: var(--admin-info);
            color: white;
        }

        .btn-edit {
            background: var(--admin-primary);
            color: white;
        }

        .btn-delete {
            background: #6c757d;
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 3rem;
        }

        .pagination-btn {
            padding: 0.7rem 1.2rem;
            border: 2px solid var(--border-color);
            background: white;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 500;
        }

        .pagination-btn:hover,
        .pagination-btn.active {
            background: var(--admin-primary);
            color: white;
            border-color: transparent;
            text-decoration: none;
            transform: translateY(-2px);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .summary-card.total::before {
            background: var(--admin-primary);
        }

        .summary-card.approved::before {
            background: var(--admin-success);
        }

        .summary-card.pending::before {
            background: var(--admin-warning);
        }

        .summary-card.rejected::before {
            background: var(--admin-danger);
        }

        .summary-number {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .summary-label {
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: var(--card-shadow);
        }

        .modal-header {
            background: var(--admin-primary);
            color: white;
            border-radius: 20px 20px 0 0;
            border-bottom: none;
        }

        .modal-title {
            font-weight: 700;
        }

        .btn-close {
            filter: invert(1);
        }

        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 0.7rem;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Alert Styles */
        .alert {
            border-radius: 15px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
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

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                margin-left: 0;
            }

            .main-content {
                padding: 1rem;
            }

            .filters-row {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                width: 100%;
            }

            .filter-select,
            .filter-input {
                min-width: auto;
                width: 100%;
            }

            .events-grid {
                grid-template-columns: 1fr;
            }

            .event-meta {
                grid-template-columns: 1fr;
            }

            .event-stats {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>

<body>
    <!-- Include Admin Navigation -->
    <?php include 'includes/navigation.php';
    ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">🎪 Events Management</h1>
            <p class="text-muted">Manage all events, approvals, and featured content</p>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Summary -->
        <div class="stats-summary">
            <div class="summary-card total">
                <div class="summary-number"><?= number_format($totalEvents) ?></div>
                <div class="summary-label">Total Events</div>
            </div>
            <div class="summary-card approved">
                <div class="summary-number">
                    <?php
                    $approvedCount = count(array_filter($events, fn($e) => $e['status'] === 'approved'));
                    echo $approvedCount;
                    ?>
                </div>
                <div class="summary-label">Approved</div>
            </div>
            <div class="summary-card pending">
                <div class="summary-number">
                    <?php
                    $pendingCount = count(array_filter($events, fn($e) => $e['status'] === 'pending'));
                    echo $pendingCount;
                    ?>
                </div>
                <div class="summary-label">Pending</div>
            </div>
            <div class="summary-card rejected">
                <div class="summary-number">
                    <?php
                    $rejectedCount = count(array_filter($events, fn($e) => $e['status'] === 'rejected'));
                    echo $rejectedCount;
                    ?>
                </div>
                <div class="summary-label">Rejected</div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" class="filters-row">
                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select name="status" class="filter-select">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Category</label>
                    <select name="category" class="filter-select">
                        <option value="all" <?= $categoryFilter === 'all' ? 'selected' : '' ?>>All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category['category']) ?>"
                                <?= $categoryFilter === $category['category'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($category['category'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Organizer</label>
                    <select name="organizer" class="filter-select">
                        <option value="all" <?= $organizerFilter === 'all' ? 'selected' : '' ?>>All Organizers</option>
                        <?php foreach ($organizers as $organizer): ?>
                            <option value="<?= $organizer['user_id'] ?>"
                                <?= $organizerFilter == $organizer['user_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($organizer['first_name'] . ' ' . $organizer['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Sort By</label>
                    <select name="sort" class="filter-select">
                        <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>Date Created</option>
                        <option value="start_datetime" <?= $sortBy === 'start_datetime' ? 'selected' : '' ?>>Event Date</option>
                        <option value="title" <?= $sortBy === 'title' ? 'selected' : '' ?>>Title</option>
                        <option value="status" <?= $sortBy === 'status' ? 'selected' : '' ?>>Status</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Order</label>
                    <select name="order" class="filter-select">
                        <option value="DESC" <?= $sortOrder === 'DESC' ? 'selected' : '' ?>>Descending</option>
                        <option value="ASC" <?= $sortOrder === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Search</label>
                    <input type="text" name="search" class="filter-input search-input"
                        placeholder="Search events..." value="<?= htmlspecialchars($searchQuery) ?>">
                </div>
                <button type="submit" class="filter-btn btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>

                <a href="events.php" class="filter-btn btn-secondary">
                    <i class="fas fa-refresh"></i> Reset
                </a>
            </form>
        </div>

        <!-- Events Grid -->
        <div class="events-grid">
            <?php if (empty($events)): ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-calendar-times fa-5x text-muted mb-4"></i>
                    <h3>No Events Found</h3>
                    <p class="text-muted">No events match your current filters</p>
                </div>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <div class="event-card <?= $event['status'] ?>">
                        <!-- Event Image -->
                        <div class="event-image">
                            <?php if ($event['featured']): ?>
                                <div class="featured-badge">
                                    <i class="fas fa-star"></i> Featured
                                </div>
                            <?php endif; ?>

                            <div class="event-status-badge status-<?= $event['status'] ?>">
                                <?= ucfirst($event['status']) ?>
                            </div>

                            <?php if ($event['image_url']): ?>
                                <img src="<?= htmlspecialchars($event['image_url']) ?>" alt="Event Image">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center h-100">
                                    <i class="fas fa-calendar-alt fa-4x text-white opacity-50"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Event Content -->
                        <div class="event-content">
                            <h3 class="event-title"><?= htmlspecialchars($event['title']) ?></h3>

                            <div class="event-organizer">
                                <i class="fas fa-user"></i>
                                <span><?= htmlspecialchars($event['first_name'] . ' ' . $event['last_name']) ?></span>
                            </div>

                            <div class="event-meta">
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?= date('M j, Y', strtotime($event['start_datetime'])) ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?= date('g:i A', strtotime($event['start_datetime'])) ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?= htmlspecialchars($event['location']) ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-tag"></i>
                                    <span><?= htmlspecialchars(ucfirst($event['category'] ?? 'General')) ?></span>
                                </div>
                            </div>

                            <!-- Event Statistics -->
                            <div class="event-stats">
                                <div class="stat-item">
                                    <div class="stat-number"><?= $event['total_tickets'] ?></div>
                                    <div class="stat-label">Tickets</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?= $event['paid_tickets'] ?></div>
                                    <div class="stat-label">Paid</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number">K<?= number_format($event['total_revenue'], 0) ?></div>
                                    <div class="stat-label">Revenue</div>
                                </div>
                            </div>

                            <!-- Event Actions -->
                            <div class="event-actions">
                                <a href="view_event.php?id=<?= $event['event_id'] ?>" class="action-btn btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>

                                <?php if ($event['status'] === 'pending'): ?>
                                    <button onclick="approveEvent(<?= $event['event_id'] ?>)" class="action-btn btn-approve">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button onclick="rejectEvent(<?= $event['event_id'] ?>)" class="action-btn btn-reject">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                <?php endif; ?>

                                <?php if ($event['status'] === 'approved'): ?>
                                    <button onclick="toggleFeature(<?= $event['event_id'] ?>, <?= $event['featured'] ? 0 : 1 ?>)"
                                        class="action-btn btn-feature">
                                        <i class="fas fa-star"></i>
                                        <?= $event['featured'] ? 'Unfeature' : 'Feature' ?>
                                    </button>
                                <?php endif; ?>

                                <a href="edit_event.php?id=<?= $event['event_id'] ?>" class="action-btn btn-edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>

                                <?php if ($event['total_tickets'] == 0): ?>
                                    <button onclick="deleteEvent(<?= $event['event_id'] ?>, '<?= htmlspecialchars($event['title']) ?>')"
                                        class="action-btn btn-delete">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                        class="pagination-btn">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>

                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);

                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                        class="pagination-btn <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                        class="pagination-btn">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Reject Event Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-times-circle"></i> Reject Event
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="rejectForm">
                    <input type="hidden" name="action" value="reject_event">
                    <input type="hidden" name="event_id" id="rejectEventId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Rejection Reason *</label>
                            <textarea name="rejection_reason" class="form-control" rows="4"
                                placeholder="Please provide a reason for rejecting this event..." required></textarea>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            The organizer will be notified about this rejection and the reason provided.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times"></i> Reject Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Event Management Functions

        function approveEvent(eventId) {
            if (confirm('Are you sure you want to approve this event?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve_event">
                    <input type="hidden" name="event_id" value="${eventId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function rejectEvent(eventId) {
            document.getElementById('rejectEventId').value = eventId;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }

        function toggleFeature(eventId, featured) {
            const action = featured ? 'feature' : 'unfeature';
            if (confirm(`Are you sure you want to ${action} this event?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="feature_event">
                    <input type="hidden" name="event_id" value="${eventId}">
                    <input type="hidden" name="featured" value="${featured}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteEvent(eventId, eventTitle) {
            if (confirm(`Are you sure you want to delete "${eventTitle}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_event">
                    <input type="hidden" name="event_id" value="${eventId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-refresh every 5 minutes for pending events
        setInterval(function() {
            if (window.location.search.includes('status=pending') || window.location.search === '') {
                location.reload();
            }
        }, 300000);

        console.log('🎪 Events Management Loaded Successfully!');
    </script>
</body>

</html>