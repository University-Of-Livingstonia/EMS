<?php

/**
 * 👥 Admin Users Management - EMS
 * Ekwendeni Mighty Campus Event Management System
 * Complete User Management System! 🎯
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

include 'includes/navigation.php';

// Handle user actions
$message = '';
$messageType = '';

if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_role':
                    $userId = (int)$_POST['user_id'];
                    $newRole = $_POST['role'];

                    if (!in_array($newRole, ['user', 'organizer', 'admin'])) {
                        throw new Exception('Invalid role specified.');
                    }

                    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $newRole, $userId);
                    $stmt->execute();

                    $message = 'User role updated successfully!';
                    $messageType = 'success';
                    break;

                case 'toggle_status':
                    $userId = (int)$_POST['user_id'];
                    $newStatus = $_POST['status'] === '1' ? 'inactive' : 'active';

                    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $newStatus, $userId);
                    $stmt->execute();

                    $message = 'User status updated successfully!';
                    $messageType = 'success';
                    break;

                case 'delete_user':
                    $userId = (int)$_POST['user_id'];

                    // Check if user has events or tickets
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM events WHERE organizer_id = ?");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $eventCount = $stmt->get_result()->fetch_assoc()['count'];

                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tickets WHERE user_id = ?");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $ticketCount = $stmt->get_result()->fetch_assoc()['count'];

                    if ($eventCount > 0 || $ticketCount > 0) {
                        throw new Exception('Cannot delete user with existing events or tickets.');
                    }

                    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();

                    $message = 'User deleted successfully!';
                    $messageType = 'success';
                    break;

                case 'create_user':
                    $userData = [
                        'username' => trim($_POST['username']),
                        'email' => trim($_POST['email']),
                        'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                        'first_name' => trim($_POST['first_name']),
                        'last_name' => trim($_POST['last_name']),
                        'role' => $_POST['role'],
                        'department' => trim($_POST['department']),
                        'phone_number' => trim($_POST['phone_number'])
                    ];

                    // Validate required fields
                    if (empty($userData['username']) || empty($userData['email']) || empty($_POST['password'])) {
                        throw new Exception('Username, email, and password are required.');
                    }

                    // Check if username or email already exists
                    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
                    $stmt->bind_param("ss", $userData['username'], $userData['email']);
                    $stmt->execute();

                    if ($stmt->get_result()->num_rows > 0) {
                        throw new Exception('Username or email already exists.');
                    }

                    $stmt = $conn->prepare("
                        INSERT INTO users (username, email, password, first_name, last_name, role, department, phone_number, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                    ");
                    $stmt->bind_param(
                        "ssssssss",
                        $userData['username'],
                        $userData['email'],
                        $userData['password'],
                        $userData['first_name'],
                        $userData['last_name'],
                        $userData['role'],
                        $userData['department'],
                        $userData['phone_number']
                    );
                    $stmt->execute();

                    $message = 'User created successfully!';
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
$roleFilter = $_GET['role'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$whereConditions = [];
$params = [];
$paramTypes = "";

if ($roleFilter !== 'all') {
    $whereConditions[] = "role = ?";
    $params[] = $roleFilter;
    $paramTypes .= "s";
}

if ($statusFilter !== 'all') {
    $whereConditions[] = "status = ?";
    $params[] = $statusFilter;
    $paramTypes .= "s";
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $paramTypes .= "ssss";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination
$totalUsers = 0;
try {
    $countQuery = "SELECT COUNT(*) as total FROM users $whereClause";
    if (!empty($params)) {
        $stmt = $conn->prepare($countQuery);
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $totalUsers = $stmt->get_result()->fetch_assoc()['total'];
    } else {
        $totalUsers = $conn->query($countQuery)->fetch_assoc()['total'];
    }
} catch (Exception $e) {
    error_log("Count query error: " . $e->getMessage());
}

$totalPages = ceil($totalUsers / $limit);

// Get users with statistics
$users = [];
try {
    $query = "
        SELECT 
            u.*,
            COUNT(DISTINCT e.event_id) as total_events,
            COUNT(DISTINCT t.ticket_id) as total_tickets,
            SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as total_spent
        FROM users u
        LEFT JOIN events e ON u.user_id = e.organizer_id
        LEFT JOIN tickets t ON u.user_id = t.user_id
        $whereClause
        GROUP BY u.user_id
        ORDER BY u.$sortBy $sortOrder
        LIMIT ? OFFSET ?
    ";

    $allParams = array_merge($params, [$limit, $offset]);
    $allParamTypes = $paramTypes . "ii";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($allParamTypes, ...$allParams);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Users query error: " . $e->getMessage());
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - Admin | EMS</title>

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
            margin-left: 300px;
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

        .users-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
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
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }

        .add-user-btn {
            background: var(--admin-success);
            color: white;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .add-user-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th,
        .users-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .users-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .users-table tr:hover {
            background: #f8f9fa;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--admin-primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            margin-right: 0.7rem;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }

        .user-details small {
            color: var(--text-secondary);
        }

        .role-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .role-admin {
            background: var(--admin-danger);
            color: white;
        }

        .role-organizer {
            background: var(--admin-warning);
            color: white;
        }

        .role-user {
            background: var(--admin-info);
            color: white;
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }

        .status-inactive {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid #f44336;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            padding: 0.3rem 0.6rem;
            border: none;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: var(--admin-info);
            color: white;
        }

        .btn-toggle {
            background: var(--admin-warning);
            color: white;
        }

        .btn-delete {
            background: var(--admin-danger);
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
            padding: 1rem;
        }

        .pagination-btn {
            padding: 0.5rem 1rem;
            border: 2px solid var(--border-color);
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-primary);
        }

        .pagination-btn:hover,
        .pagination-btn.active {
            background: var(--admin-primary);
            color: white;
            border-color: transparent;
            text-decoration: none;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            text-align: center;
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

        .stat-card.primary::before {
            background: var(--admin-primary);
        }

        .stat-card.success::before {
            background: var(--admin-success);
        }

        .stat-card.warning::before {
            background: var(--admin-warning);
        }

        .stat-card.info::before {
            background: var(--admin-info);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 900;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
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

            .header-content {
                flex-direction: column;
                align-items: flex-start;
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

            .users-table {
                font-size: 0.8rem;
            }

            .users-table th,
            .users-table td {
                padding: 0.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <!-- Include Admin Navigation 
    

    <div class="main-content">
        <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <div>
                <h1 class="page-title">👥 Users Management</h1>
                <p class="text-muted">Manage all system users and their permissions</p>
            </div>
            <button class="add-user-btn" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus"></i> Add New User
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Row -->
    <div class="stats-row">
        <div class="stat-card primary">
            <div class="stat-number"><?= number_format($totalUsers) ?></div>
            <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-card success">
            <div class="stat-number">
                <?php
                $activeUsers = array_filter($users, fn($u) => $u['email_verified'] === 'active');
                echo count($activeUsers);
                ?>
            </div>
            <div class="stat-label">Active Users</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-number">
                <?php
                $organizers = array_filter($users, fn($u) => $u['role'] === 'organizer');
                echo count($organizers);
                ?>
            </div>
            <div class="stat-label">Organizers</div>
        </div>
        <div class="stat-card info">
            <div class="stat-number">
                <?php
                $newUsers = array_filter($users, fn($u) => strtotime($u['created_at']) > strtotime('-30 days'));
                echo count($newUsers);
                ?>
            </div>
            <div class="stat-label">New This Month</div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
        <form method="GET" class="filters-row">
            <div class="filter-group">
                <label class="filter-label">Role</label>
                <select name="role" class="filter-select">
                    <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>All Roles</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="organizer" <?= $roleFilter === 'organizer' ? 'selected' : '' ?>>Organizer</option>
                    <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>User</option>
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">Status</label>
                <select name="status" class="filter-select">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">Sort By</label>
                <select name="sort" class="filter-select">
                    <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>Date Created</option>
                    <option value="username" <?= $sortBy === 'username' ? 'selected' : '' ?>>Username</option>
                    <option value="email" <?= $sortBy === 'email' ? 'selected' : '' ?>>Email</option>
                    <option value="role" <?= $sortBy === 'role' ? 'selected' : '' ?>>Role</option>
                    <option value="status" <?= $sortBy === 'email_verified' ? 'selected' : '' ?>>Status</option>
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
                    placeholder="Search users..." value="<?= htmlspecialchars($searchQuery) ?>">
            </div>

            <button type="submit" class="filter-btn btn-primary">
                <i class="fas fa-search"></i> Filter
            </button>

            <a href="users.php" class="filter-btn btn-secondary">
                <i class="fas fa-refresh"></i> Reset
            </a>
        </form>
    </div>

    <!-- Users Table -->
    <div class="users-card">
        <div class="card-header">
            <h5 class="card-title">
                <i class="fas fa-users"></i>
                Users List (<?= number_format($totalUsers) ?> total)
            </h5>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm" onclick="exportUsers('csv')">
                    <i class="fas fa-download"></i> Export CSV
                </button>
                <button class="btn btn-outline-success btn-sm" onclick="exportUsers('excel')">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Events</th>
                        <th>Tickets</th>
                        <th>Total Spent</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5>No Users Found</h5>
                                <p class="text-muted">No users match your current filters</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?= strtoupper(substr($user['first_name'] ?: $user['username'], 0, 1) .
                                                substr($user['last_name'] ?: $user['username'], 0, 1)) ?>
                                        </div>
                                        <div class="user-details">
                                            <h6><?= htmlspecialchars(($user['first_name'] && $user['last_name']) ?
                                                    $user['first_name'] . ' ' . $user['last_name'] : $user['username']) ?></h6>
                                            <small><?= htmlspecialchars($user['email']) ?></small>
                                            <?php if ($user['department']): ?>
                                                <small class="d-block text-info"><?= htmlspecialchars($user['department']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge role-<?= $user['role'] ?>">
                                        <?= ucfirst($user['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $user['email_verified'] ?>">
                                        <?= ucfirst($user['email_verified']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= $user['total_events'] ?></strong>
                                    <?php if ($user['total_events'] > 0): ?>
                                        <small class="text-muted d-block">as organizer</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= $user['total_tickets'] ?></strong>
                                    <?php if ($user['total_tickets'] > 0): ?>
                                        <small class="text-muted d-block">purchased</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong>K<?= number_format($user['total_spent'], 2) ?></strong>
                                </td>
                                <td>
                                    <span><?= date('M j, Y', strtotime($user['created_at'])) ?></span>
                                    <small class="text-muted d-block"><?= timeAgo($user['created_at']) ?></small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn btn-edit"
                                            onclick="editUser(<?= $user['user_id'] ?>)"
                                            title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </button>

                                        <?php if ($user['user_id'] !== $currentUser['user_id']): ?>
                                            <button class="action-btn btn-toggle"
                                                onclick="toggleUserStatus(<?= $user['user_id'] ?>, '<?= $user['email_verified'] ?>')"
                                                title="Toggle Status">
                                                <i class="fas fa-<?= $user['email_verified'] === '1' ? 'pause' : 'play' ?>"></i>
                                            </button>

                                            <button class="action-btn btn-delete"
                                                onclick="deleteUser(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['username']) ?>')"
                                                title="Delete User">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
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
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus"></i> Add New User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="addUserForm">
                    <input type="hidden" name="action" value="create_user">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Username *</label>
                                    <input type="text" name="username" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Password *</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <select name="role" class="form-control">
                                        <option value="user">User</option>
                                        <option value="organizer">Organizer</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Department</label>
                                    <input type="text" name="department" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone_number" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit"></i> Edit User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editUserForm">
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" id="editUsername" class="form-control" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" id="editEmail" class="form-control" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <select name="role" id="editRole" class="form-control">
                                        <option value="user">User</option>
                                        <option value="organizer">Organizer</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" id="editStatus" class="form-control">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" id="editFirstName" class="form-control" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" id="editLastName" class="form-control" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Department</label>
                                    <input type="text" id="editDepartment" class="form-control" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" id="editPhone" class="form-control" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Member Since</label>
                            <input type="text" id="editCreatedAt" class="form-control" readonly>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Users Management JavaScript

        // Edit User Function
        function editUser(userId) {
            // Find user data from the table
            const userRow = document.querySelector(`tr:has(button[onclick*="${userId}"])`);
            if (!userRow) return;

            // Extract user data (this is a simplified approach)
            // In a real application, you'd make an AJAX call to get full user data
            fetch(`../api/get_user.php?id=${userId}`)
                .then(response => response.json())
                .then(user => {
                    document.getElementById('editUserId').value = user.user_id;
                    document.getElementById('editUsername').value = user.username;
                    document.getElementById('editEmail').value = user.email;
                    document.getElementById('editRole').value = user.role;
                    document.getElementById('editStatus').value = user.status;
                    document.getElementById('editFirstName').value = user.first_name || '';
                    document.getElementById('editLastName').value = user.last_name || '';
                    document.getElementById('editDepartment').value = user.department || '';
                    document.getElementById('editPhone').value = user.phone_number || '';
                    document.getElementById('editCreatedAt').value = new Date(user.created_at).toLocaleDateString();

                    // Show modal
                    new bootstrap.Modal(document.getElementById('editUserModal')).show();
                })
                .catch(error => {
                    console.error('Error fetching user data:', error);
                    showToast('Error loading user data', 'error');
                });
        }

        // Toggle User Status
        function toggleUserStatus(userId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = newStatus === 'active' ? 'activate' : 'deactivate';

            if (confirm(`Are you sure you want to ${action} this user?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="user_id" value="${userId}">
                    <input type="hidden" name="status" value="${currentStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Delete User
        function deleteUser(userId, username) {
            if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Export Users
        function exportUsers(format) {
            const params = new URLSearchParams(window.location.search);
            params.set('export', format);
            window.location.href = `export_users.php?${params.toString()}`;
        }

        // Toast Notification System
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    <span>${message}</span>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;

            document.body.appendChild(toast);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 5000);
        }

        // Form Validation
        document.getElementById('addUserForm').addEventListener('submit', function(e) {
            const username = this.username.value.trim();
            const email = this.email.value.trim();
            const password = this.password.value;

            if (username.length < 3) {
                e.preventDefault();
                showToast('Username must be at least 3 characters long', 'error');
                return;
            }

            if (!email.includes('@')) {
                e.preventDefault();
                showToast('Please enter a valid email address', 'error');
                return;
            }

            if (password.length < 6) {
                e.preventDefault();
                showToast('Password must be at least 6 characters long', 'error');
                return;
            }
        });

        // Auto-refresh every 5 minutes
        setInterval(function() {
            if (!document.querySelector('.modal.show')) {
                location.reload();
            }
        }, 300000);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + N for new user
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                new bootstrap.Modal(document.getElementById('addUserModal')).show();
            }

            // Escape to close modals
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal.show');
                modals.forEach(modal => {
                    bootstrap.Modal.getInstance(modal)?.hide();
                });
            }
        });

        // Add toast notification styles
        const toastStyles = `
            <style>
                .toast-notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: white;
                    border-radius: 10px;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
                    padding: 1rem;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    min-width: 300px;
                    z-index: 9999;
                    animation: slideInRight 0.3s ease-out;
                }
                
                .toast-success {
                    border-left: 4px solid #4CAF50;
                }
                
                .toast-error {
                    border-left: 4px solid #f44336;
                }
                
                .toast-info {
                    border-left: 4px solid #2196F3;
                }
                
                .toast-content {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                
                .toast-content i {
                    font-size: 1.2rem;
                }
                
                .toast-success .toast-content i {
                    color: #4CAF50;
                }
                
                .toast-error .toast-content i {
                    color: #f44336;
                }
                
                .toast-info .toast-content i {
                    color: #2196F3;
                }
                
                .toast-close {
                    background: none;
                    border: none;
                    font-size: 1rem;
                    color: #999;
                    cursor: pointer;
                    padding: 0.2rem;
                }
                
                .toast-close:hover {
                    color: #333;
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
            </style>s
        `;

        document.head.insertAdjacentHTML('beforeend', toastStyles);

        console.log('👥 Users Management Loaded Successfully!');
    </script>
</body>

</html>