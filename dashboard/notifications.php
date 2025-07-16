<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'mark_read':
            $notification_id = (int)$_POST['notification_id'];
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $notification_id, $user_id);
            echo json_encode(['success' => $stmt->execute()]);
            exit;

        case 'mark_all_read':
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
            $stmt->bind_param("i", $user_id);
            echo json_encode(['success' => $stmt->execute()]);
            exit;

        case 'delete':
            $notification_id = (int)$_POST['notification_id'];
            $stmt = $conn->prepare("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $notification_id, $user_id);
            echo json_encode(['success' => $stmt->execute()]);
            exit;

        case 'delete_all_read':
            $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1");
            $stmt->bind_param("i", $user_id);
            echo json_encode(['success' => $stmt->execute()]);
            exit;

        case 'get_notifications':
            $page = (int)($_POST['page'] ?? 1);
            $limit = 20;
            $offset = ($page - 1) * $limit;

            $filter = $_POST['filter'] ?? 'all';
            $whereClause = "WHERE user_id = ?";
            $params = [$user_id];
            $types = 'i';

            if ($filter === 'unread') {
                $whereClause .= " AND is_read = 0";
            } elseif ($filter === 'read') {
                $whereClause .= " AND is_read = 1";
            } elseif ($filter !== 'all') {
                $whereClause .= " AND type = ?";
                $params[] = $filter;
                $types .= 's';
            }

            // Get notifications
            $sql = "SELECT * FROM notifications $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $notifications = $result->fetch_all(MYSQLI_ASSOC);

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM notifications $whereClause";
            $countStmt = $conn->prepare($countSql);
            $countStmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
            $countStmt->execute();
            $totalResult = $countStmt->get_result();
            $total = $totalResult->fetch_assoc()['total'];

            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'total' => $total,
                'has_more' => ($offset + $limit) < $total
            ]);
            exit;
    }
}

// Get notification statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN is_read = 0 THEN 1 END) as unread,
    COUNT(CASE WHEN type = 'event_reminder' THEN 1 END) as reminders,
    COUNT(CASE WHEN type = 'payment_completed' THEN 1 END) as payments,
    COUNT(CASE WHEN type = 'event_approved' THEN 1 END) as approvals,
    COUNT(CASE WHEN type = 'system' THEN 1 END) as system
FROM notifications WHERE user_id = ?";

$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get notification preferences
$prefs = getNotificationPreferences($conn, $user_id);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - EMS Dashboard</title>

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="../assets/css/dashboard.css" rel="stylesheet">

    <style>
        :root {
            --notification-primary: #667eea;
            --notification-success: #4CAF50;
            --notification-warning: #FF9800;
            --notification-danger: #f44336;
            --notification-info: #2196F3;
            --notification-bg: #f8f9fa;
            --notification-border: #e9ecef;
            --notification-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            --notification-hover-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
        }

        .notifications-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .notifications-header {
            background: linear-gradient(135deg, var(--notification-primary) 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .notifications-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% {
                transform: translateX(0) translateY(0);
            }

            100% {
                transform: translateX(-50px) translateY(-50px);
            }
        }

        .notifications-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .notifications-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .notifications-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: var(--notification-shadow);
            transition: all 0.3s ease;
            border-left: 4px solid var(--notification-primary);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--notification-hover-shadow);
        }

        .stat-card.unread {
            border-left-color: var(--notification-danger);
        }

        .stat-card.reminders {
            border-left-color: var(--notification-warning);
        }

        .stat-card.payments {
            border-left-color: var(--notification-success);
        }

        .stat-card.system {
            border-left-color: var(--notification-info);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--notification-primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        .notifications-controls {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: var(--notification-shadow);
            margin-bottom: 2rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
        }

        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 0.5rem 1rem;
            border: 2px solid var(--notification-border);
            background: white;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            text-decoration: none;
            color: #6c757d;
        }

        .filter-tab:hover,
        .filter-tab.active {
            background: var(--notification-primary);
            border-color: var(--notification-primary);
            color: white;
            text-decoration: none;
        }

        .bulk-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-notification {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            border: none;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-notification.primary {
            background: var(--notification-primary);
            color: white;
        }

        .btn-notification.success {
            background: var(--notification-success);
            color: white;
        }

        .btn-notification.danger {
            background: var(--notification-danger);
            color: white;
        }

        .btn-notification:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .notifications-list {
            background: white;
            border-radius: 15px;
            box-shadow: var(--notification-shadow);
            overflow: hidden;
        }

        .notification-item {
            padding: 1.5rem;
            border-bottom: 1px solid var(--notification-border);
            transition: all 0.3s ease;
            position: relative;
            cursor: pointer;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background: var(--notification-bg);
        }

        .notification-item.unread {
            background: rgba(102, 126, 234, 0.05);
            border-left: 4px solid var(--notification-primary);
        }

        .notification-item.unread::before {
            content: '';
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 10px;
            height: 10px;
            background: var(--notification-primary);
            border-radius: 50%;
        }

        .notification-content {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            flex-shrink: 0;
        }

        .notification-icon.event {
            background: var(--notification-primary);
        }

        .notification-icon.payment {
            background: var(--notification-success);
        }

        .notification-icon.reminder {
            background: var(--notification-warning);
        }

        .notification-icon.system {
            background: var(--notification-info);
        }

        .notification-icon.error {
            background: var(--notification-danger);
        }

        .notification-details {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .notification-message {
            color: #6c757d;
            line-height: 1.5;
            margin-bottom: 0.5rem;
        }

        .notification-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.9rem;
            color: #95a5a6;
        }

        .notification-time {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .notification-type {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            text-transform: capitalize;
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
            margin-left: auto;
        }

        .notification-action {
            width: 35px;
            height: 35px;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: transparent;
            color: #6c757d;
        }

        .notification-action:hover {
            background: var(--notification-bg);
            transform: scale(1.1);
        }

        .notification-action.read {
            color: var(--notification-success);
        }

        .notification-action.delete {
            color: var(--notification-danger);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            margin-bottom: 1rem;
            color: #2c3e50;
        }

        .loading-spinner {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--notification-border);
            border-top: 4px solid var(--notification-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .load-more-btn {
            width: 100%;
            padding: 1rem;
            background: var(--notification-bg);
            border: 2px dashed var(--notification-border);
            border-radius: 10px;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .load-more-btn:hover {
            background: var(--notification-primary);
            border-color: var(--notification-primary);
            color: white;
        }

        .preferences-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .preferences-modal.active {
            display: flex;
        }

        .preferences-content {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .preferences-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--notification-border);
        }

        .preferences-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6c757d;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            background: var(--notification-bg);
            color: var(--notification-danger);
        }

        .preference-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--notification-border);
        }

        .preference-item:last-child {
            border-bottom: none;
        }

        .preference-label {
            font-weight: 500;
            color: #2c3e50;
        }

        .preference-description {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .toggle-switch {
            position: relative;
            width: 50px;
            height: 25px;
            background: #ccc;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .toggle-switch.active {
            background: var(--notification-primary);
        }

        .toggle-switch::before {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 21px;
            height: 21px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .toggle-switch.active::before {
            transform: translateX(25px);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .notifications-container {
                padding: 1rem;
            }

            .notifications-title {
                font-size: 2rem;
            }

            .notifications-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-tabs {
                justify-content: center;
            }

            .bulk-actions {
                justify-content: center;
            }

            .notification-content {
                flex-direction: column;
                gap: 0.5rem;
            }

            .notification-actions {
                margin-left: 0;
                justify-content: center;
            }

            .stat-number {
                font-size: 2rem;
            }
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .slide-in {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(-100%);
            }

            to {
                transform: translateX(0);
            }
        }

        .bounce-in {
            animation: bounceIn 0.6s ease-out;
        }

        @keyframes bounceIn {
            0% {
                transform: scale(0.3);
                opacity: 0;
            }

            50% {
                transform: scale(1.05);
            }

            70% {
                transform: scale(0.9);
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
    </style>
</head>

<body>
    <!-- Include Sidebar -->
    <?php include '../includes/user_sidebar.php'; ?>

    <div class="main-content">
        <div class="notifications-container">
            <!-- Header -->
            <div class="notifications-header fade-in">
                <h1 class="notifications-title">
                    <i class="fas fa-bell"></i>
                    Notifications
                </h1>
                <p class="notifications-subtitle">
                    Stay updated with your events, payments, and system alerts
                </p>
            </div>

            <!-- Statistics -->
            <div class="notifications-stats">
                <div class="stat-card fade-in">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Notifications</div>
                </div>
                <div class="stat-card unread fade-in">
                    <div class="stat-number"><?php echo $stats['unread']; ?></div>
                    <div class="stat-label">Unread</div>
                </div>
                <div class="stat-card reminders fade-in">
                    <div class="stat-number"><?php echo $stats['reminders']; ?></div>
                    <div class="stat-label">Reminders</div>
                </div>
                <div class="stat-card payments fade-in">
                    <div class="stat-number"><?php echo $stats['payments']; ?></div>
                    <div class="stat-label">Payments</div>
                </div>
                <div class="stat-card system fade-in">
                    <div class="stat-number"><?php echo $stats['system']; ?></div>
                    <div class="stat-label">System</div>
                </div>
            </div>

            <!-- Controls -->
            <div class="notifications-controls fade-in">
                <div class="filter-tabs">
                    <a href="#" class="filter-tab active" data-filter="all">
                        <i class="fas fa-list"></i> All
                    </a>
                    <a href="#" class="filter-tab" data-filter="unread">
                        <i class="fas fa-envelope"></i> Unread
                    </a>
                    <a href="#" class="filter-tab" data-filter="event_reminder">
                        <i class="fas fa-clock"></i> Reminders
                    </a>
                    <a href="#" class="filter-tab" data-filter="payment_completed">
                        <i class="fas fa-credit-card"></i> Payments
                    </a>
                    <a href="#" class="filter-tab" data-filter="system">
                        <i class="fas fa-cog"></i> System
                    </a>
                </div>

                <div class="bulk-actions">
                    <button class="btn-notification success" onclick="markAllRead()">
                        <i class="fas fa-check-double"></i> Mark All Read
                    </button>
                    <button class="btn-notification danger" onclick="deleteAllRead()">
                        <i class="fas fa-trash"></i> Delete Read
                    </button>
                    <button class="btn-notification primary" onclick="showPreferences()">
                        <i class="fas fa-cog"></i> Preferences
                    </button>
                </div>
            </div>

            <!-- Notifications List -->
            <div class="notifications-list fade-in" id="notificationsList">
                <div class="loading-spinner">
                    <div class="spinner"></div>
                </div>
            </div>

            <!-- Load More Button -->
            <button class="load-more-btn" id="loadMoreBtn" onclick="loadMore()" style="display: none;">
                <i class="fas fa-chevron-down"></i>
                Load More Notifications
            </button>
        </div>
    </div>

    <!-- Preferences Modal -->
    <div class="preferences-modal" id="preferencesModal">
        <div class="preferences-content">
            <div class="preferences-header">
                <h3 class="preferences-title">
                    <i class="fas fa-cog"></i>
                    Notification Preferences
                </h3>
                <button class="close-modal" onclick="hidePreferences()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="preferencesForm">
                <div class="preference-item">
                    <div>
                        <div class="preference-label">Email Notifications</div>
                        <div class="preference-description">Receive notifications via email</div>
                    </div>
                    <div class="toggle-switch <?php echo $prefs['email_notifications'] ? 'active' : ''; ?>"
                        data-pref="email_notifications">
                    </div>
                </div>

                <div class="preference-item">
                    <div>
                        <div class="preference-label">SMS Notifications</div>
                        <div class="preference-description">Receive notifications via SMS</div>
                    </div>
                    <div class="toggle-switch <?php echo $prefs['sms_notifications'] ? 'active' : ''; ?>"
                        data-pref="sms_notifications">
                    </div>
                </div>

                <div class="preference-item">
                    <div>
                        <div class="preference-label">Push Notifications</div>
                        <div class="preference-description">Receive browser push notifications</div>
                    </div>
                    <div class="toggle-switch <?php echo $prefs['push_notifications'] ? 'active' : ''; ?>"
                        data-pref="push_notifications">
                    </div>
                </div>

                <div class="preference-item">
                    <div>
                        <div class="preference-label">Event Reminders</div>
                        <div class="preference-description">Get reminded about upcoming events</div>
                    </div>
                    <div class="toggle-switch <?php echo $prefs['event_reminders'] ? 'active' : ''; ?>"
                        data-pref="event_reminders">
                    </div>
                </div>

                <div class="preference-item">
                    <div>
                        <div class="preference-label">Payment Notifications</div>
                        <div class="preference-description">Get notified about payment updates</div>
                    </div>
                    <div class="toggle-switch <?php echo $prefs['payment_notifications'] ? 'active' : ''; ?>"
                        data-pref="payment_notifications">
                    </div>
                </div>

                <div class="preference-item">
                    <div>
                        <div class="preference-label">Marketing Emails</div>
                        <div class="preference-description">Receive promotional emails and updates</div>
                    </div>
                    <div class="toggle-switch <?php echo $prefs['marketing_emails'] ? 'active' : ''; ?>"
                        data-pref="marketing_emails">
                    </div>
                </div>

                <div style="margin-top: 2rem; text-align: center;">
                    <button type="button" class="btn-notification success" onclick="savePreferences()">
                        <i class="fas fa-save"></i> Save Preferences
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        class NotificationManager {
            constructor() {
                this.currentPage = 1;
                this.currentFilter = 'all';
                this.hasMore = true;
                this.loading = false;
                this.preferences = {};

                this.init();
            }

            init() {
                this.loadNotifications();
                this.bindEvents();
                this.initPreferences();

                // Auto-refresh every 30 seconds
                setInterval(() => {
                    if (this.currentPage === 1) {
                        this.loadNotifications(true);
                    }
                }, 30000);
            }

            bindEvents() {
                // Filter tabs
                document.querySelectorAll('.filter-tab').forEach(tab => {
                    tab.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.setFilter(tab.dataset.filter);
                    });
                });

                // Toggle switches
                document.querySelectorAll('.toggle-switch').forEach(toggle => {
                    toggle.addEventListener('click', () => {
                        toggle.classList.toggle('active');
                    });
                });

                // Modal close on outside click
                document.getElementById('preferencesModal').addEventListener('click', (e) => {
                    if (e.target.id === 'preferencesModal') {
                        this.hidePreferences();
                    }
                });
            }

            setFilter(filter) {
                // Update active tab
                document.querySelectorAll('.filter-tab').forEach(tab => {
                    tab.classList.remove('active');
                });
                document.querySelector(`[data-filter="${filter}"]`).classList.add('active');

                this.currentFilter = filter;
                this.currentPage = 1;
                this.hasMore = true;
                this.loadNotifications();
            }

            async loadNotifications(refresh = false) {
                if (this.loading) return;

                this.loading = true;

                if (refresh) {
                    this.currentPage = 1;
                    this.hasMore = true;
                }

                try {
                    const formData = new FormData();
                    formData.append('action', 'get_notifications');
                    formData.append('page', this.currentPage);
                    formData.append('filter', this.currentFilter);

                    const response = await fetch('notifications.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        if (refresh || this.currentPage === 1) {
                            document.getElementById('notificationsList').innerHTML = '';
                        }

                        this.renderNotifications(data.notifications);
                        this.hasMore = data.has_more;

                        // Update load more button
                        const loadMoreBtn = document.getElementById('loadMoreBtn');
                        loadMoreBtn.style.display = this.hasMore ? 'block' : 'none';

                        // Show empty state if no notifications
                        if (data.notifications.length === 0 && this.currentPage === 1) {
                            this.showEmptyState();
                        }
                    }
                } catch (error) {
                    console.error('Error loading notifications:', error);
                    this.showError('Failed to load notifications');
                } finally {
                    this.loading = false;
                }
            }

            renderNotifications(notifications) {
                const container = document.getElementById('notificationsList');

                notifications.forEach(notification => {
                    const notificationElement = this.createNotificationElement(notification);
                    container.appendChild(notificationElement);
                });
            }

            createNotificationElement(notification) {
                const div = document.createElement('div');
                div.className = `notification-item ${notification.is_read == 0 ? 'unread' : ''} fade-in`;
                div.dataset.id = notification.notification_id;

                const iconClass = this.getNotificationIcon(notification.type);
                const iconType = this.getNotificationIconType(notification.type);

                div.innerHTML = `
                    <div class="notification-content">
                        <div class="notification-icon ${iconType}">
                            <i class="fas fa-${iconClass}"></i>
                        </div>
                        <div class="notification-details">
                            <div class="notification-title">${this.escapeHtml(notification.title)}</div>
                            <div class="notification-message">${this.escapeHtml(notification.message)}</div>
                            <div class="notification-meta">
                                <div class="notification-time">
                                    <i class="fas fa-clock"></i>
                                    ${this.timeAgo(notification.created_at)}
                                </div>
                                <div class="notification-type">
                                    <i class="fas fa-tag"></i>
                                    ${this.formatType(notification.type)}
                                </div>
                            </div>
                        </div>
                        <div class="notification-actions">
                            ${notification.is_read == 0 ? `
                                <button class="notification-action read" onclick="notificationManager.markAsRead(${notification.notification_id})" title="Mark as read">
                                    <i class="fas fa-check"></i>
                                </button>
                            ` : ''}
                            <button class="notification-action delete" onclick="notificationManager.deleteNotification(${notification.notification_id})" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;

                // Add click handler to mark as read
                if (notification.is_read == 0) {
                    div.addEventListener('click', (e) => {
                        if (!e.target.closest('.notification-actions')) {
                            this.markAsRead(notification.notification_id);
                        }
                    });
                }

                return div;
            }

            getNotificationIcon(type) {
                const icons = {
                    'event_approved': 'check-circle',
                    'event_rejected': 'times-circle',
                    'event_reminder': 'clock',
                    'payment_completed': 'credit-card',
                    'payment_failed': 'exclamation-triangle',
                    'ticket_verified': 'ticket-alt',
                    'system': 'cog',
                    'new_registration': 'user-plus',
                    'event_cancelled': 'ban',
                    'event_updated': 'edit'
                };
                return icons[type] || 'bell';
            }

            getNotificationIconType(type) {
                const types = {
                    'event_approved': 'event',
                    'event_rejected': 'error',
                    'event_reminder': 'reminder',
                    'payment_completed': 'payment',
                    'payment_failed': 'error',
                    'ticket_verified': 'event',
                    'system': 'system',
                    'new_registration': 'event',
                    'event_cancelled': 'error',
                    'event_updated': 'event'
                };
                return types[type] || 'system';
            }

            formatType(type) {
                return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            }

            timeAgo(dateString) {
                const date = new Date(dateString);
                const now = new Date();
                const diffInSeconds = Math.floor((now - date) / 1000);

                if (diffInSeconds < 60) return 'just now';
                if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
                if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
                if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)}d ago`;
                if (diffInSeconds < 31536000) return `${Math.floor(diffInSeconds / 2592000)}mo ago`;
                return `${Math.floor(diffInSeconds / 31536000)}y ago`;
            }

            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            showEmptyState() {
                const container = document.getElementById('notificationsList');
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-bell-slash empty-state-icon"></i>
                        <h3>No notifications found</h3>
                        <p>You're all caught up! No ${this.currentFilter === 'all' ? '' : this.currentFilter + ' '}notifications to show.</p>
                    </div>
                `;
            }

            showError(message) {
                const container = document.getElementById('notificationsList');
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle empty-state-icon" style="color: var(--notification-danger);"></i>
                        <h3>Error</h3>
                        <p>${message}</p>
                        <button class="btn-notification primary" onclick="notificationManager.loadNotifications()">
                            <i class="fas fa-refresh"></i> Try Again
                        </button>
                    </div>
                `;
            }

            async markAsRead(notificationId) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'mark_read');
                    formData.append('notification_id', notificationId);

                    const response = await fetch('notifications.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        const element = document.querySelector(`[data-id="${notificationId}"]`);
                        if (element) {
                            element.classList.remove('unread');
                            element.querySelector('.notification-actions').innerHTML = `
                                <button class="notification-action delete" onclick="notificationManager.deleteNotification(${notificationId})" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            `;
                        }

                        this.updateStats();
                        this.showToast('Notification marked as read', 'success');
                    }
                } catch (error) {
                    console.error('Error marking notification as read:', error);
                    this.showToast('Failed to mark notification as read', 'error');
                }
            }

            async deleteNotification(notificationId) {
                if (!confirm('Are you sure you want to delete this notification?')) {
                    return;
                }

                try {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('notification_id', notificationId);

                    const response = await fetch('notifications.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        const element = document.querySelector(`[data-id="${notificationId}"]`);
                        if (element) {
                            element.style.animation = 'slideOut 0.3s ease-in';
                            setTimeout(() => {
                                element.remove();

                                // Check if list is empty
                                const container = document.getElementById('notificationsList');
                                if (container.children.length === 0) {
                                    this.showEmptyState();
                                }
                            }, 300);
                        }

                        this.updateStats();
                        this.showToast('Notification deleted', 'success');
                    }
                } catch (error) {
                    console.error('Error deleting notification:', error);
                    this.showToast('Failed to delete notification', 'error');
                }
            }

            async markAllRead() {
                if (!confirm('Mark all notifications as read?')) {
                    return;
                }

                try {
                    const formData = new FormData();
                    formData.append('action', 'mark_all_read');

                    const response = await fetch('notifications.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Update all unread notifications
                        document.querySelectorAll('.notification-item.unread').forEach(element => {
                            element.classList.remove('unread');
                            const actions = element.querySelector('.notification-actions');
                            const notificationId = element.dataset.id;
                            actions.innerHTML = `
                                <button class="notification-action delete" onclick="notificationManager.deleteNotification(${notificationId})" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            `;
                        });

                        this.updateStats();
                        this.showToast('All notifications marked as read', 'success');
                    }
                } catch (error) {
                    console.error('Error marking all as read:', error);
                    this.showToast('Failed to mark all as read', 'error');
                }
            }

            async deleteAllRead() {
                if (!confirm('Delete all read notifications? This action cannot be undone.')) {
                    return;
                }

                try {
                    const formData = new FormData();
                    formData.append('action', 'delete_all_read');

                    const response = await fetch('notifications.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Remove all read notifications
                        document.querySelectorAll('.notification-item:not(.unread)').forEach(element => {
                            element.style.animation = 'slideOut 0.3s ease-in';
                            setTimeout(() => element.remove(), 300);
                        });

                        // Check if list is empty after animation
                        setTimeout(() => {
                            const container = document.getElementById('notificationsList');
                            if (container.children.length === 0) {
                                this.showEmptyState();
                            }
                        }, 400);

                        this.updateStats();
                        this.showToast('All read notifications deleted', 'success');
                    }
                } catch (error) {
                    console.error('Error deleting read notifications:', error);
                    this.showToast('Failed to delete read notifications', 'error');
                }
            }

            loadMore() {
                if (this.hasMore && !this.loading) {
                    this.currentPage++;
                    this.loadNotifications();
                }
            }

            updateStats() {
                // Reload page stats
                setTimeout(() => {
                    location.reload();
                }, 1000);
            }

            showPreferences() {
                document.getElementById('preferencesModal').classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            hidePreferences() {
                document.getElementById('preferencesModal').classList.remove('active');
                document.body.style.overflow = '';
            }

            initPreferences() {
                // Load current preferences
                this.preferences = {
                    email_notifications: <?php echo $prefs['email_notifications'] ? 'true' : 'false'; ?>,
                    sms_notifications: <?php echo $prefs['sms_notifications'] ? 'true' : 'false'; ?>,
                    push_notifications: <?php echo $prefs['push_notifications'] ? 'true' : 'false'; ?>,
                    event_reminders: <?php echo $prefs['event_reminders'] ? 'true' : 'false'; ?>,
                    payment_notifications: <?php echo $prefs['payment_notifications'] ? 'true' : 'false'; ?>,
                    marketing_emails: <?php echo $prefs['marketing_emails'] ? 'true' : 'false'; ?>
                };
            }

            async savePreferences() {
                const preferences = {};

                document.querySelectorAll('.toggle-switch').forEach(toggle => {
                    const pref = toggle.dataset.pref;
                    preferences[pref] = toggle.classList.contains('active') ? 1 : 0;
                });

                try {
                    const formData = new FormData();
                    formData.append('action', 'save_preferences');
                    Object.entries(preferences).forEach(([key, value]) => {
                        formData.append(key, value);
                    });

                    const response = await fetch('../api/user/preferences.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.preferences = preferences;
                        this.hidePreferences();
                        this.showToast('Preferences saved successfully', 'success');
                    } else {
                        this.showToast('Failed to save preferences', 'error');
                    }
                } catch (error) {
                    console.error('Error saving preferences:', error);
                    this.showToast('Failed to save preferences', 'error');
                }
            }

            showToast(message, type = 'info') {
                // Remove existing toast
                const existingToast = document.querySelector('.toast-notification');
                if (existingToast) {
                    existingToast.remove();
                }

                const toast = document.createElement('div');
                toast.className = `toast-notification toast-${type}`;
                toast.innerHTML = `
                    <div class="toast-content">
                        <i class="fas fa-${this.getToastIcon(type)}"></i>
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

            getToastIcon(type) {
                const icons = {
                    success: 'check-circle',
                    error: 'exclamation-triangle',
                    warning: 'exclamation-circle',
                    info: 'info-circle'
                };
                return icons[type] || 'info-circle';
            }
        }

        // Initialize notification manager
        let notificationManager;

        document.addEventListener('DOMContentLoaded', () => {
            notificationManager = new NotificationManager();
        });

        // Global functions
        function markAllRead() {
            notificationManager.markAllRead();
        }

        function deleteAllRead() {
            notificationManager.deleteAllRead();
        }

        function showPreferences() {
            notificationManager.showPreferences();
        }

        function hidePreferences() {
            notificationManager.hidePreferences();
        }

        function savePreferences() {
            notificationManager.savePreferences();
        }

        function loadMore() {
            notificationManager.loadMore();
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 'a':
                        e.preventDefault();
                        markAllRead();
                        break;
                    case 'd':
                        e.preventDefault();
                        deleteAllRead();
                        break;
                    case ',':
                        e.preventDefault();
                        showPreferences();
                        break;
                }
            }

            if (e.key === 'Escape') {
                hidePreferences();
            }
        });

        // Service Worker for push notifications
        if ('serviceWorker' in navigator && 'PushManager' in window) {
            navigator.serviceWorker.register('../sw.js')
                .then(registration => {
                    console.log('Service Worker registered');
                })
                .catch(error => {
                    console.log('Service Worker registration failed');
                });
        }
    </script>

    <style>
        /* Toast Notifications */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            z-index: 1001;
            min-width: 300px;
            animation: slideInRight 0.3s ease-out;
        }

        .toast-success {
            border-left: 4px solid var(--notification-success);
        }

        .toast-error {
            border-left: 4px solid var(--notification-danger);
        }

        .toast-warning {
            border-left: 4px solid var(--notification-warning);
        }

        .toast-info {
            border-left: 4px solid var(--notification-info);
        }

        .toast-content {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
        }

        .toast-success .toast-content i {
            color: var(--notification-success);
        }

        .toast-error .toast-content i {
            color: var(--notification-danger);
        }

        .toast-warning .toast-content i {
            color: var(--notification-warning);
        }

        .toast-info .toast-content i {
            color: var(--notification-info);
        }

        .toast-close {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .toast-close:hover {
            background: var(--notification-bg);
            color: var(--notification-danger);
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }

            to {
                transform: translateX(-100%);
                opacity: 0;
            }
        }

        /* Keyboard shortcuts help */
        .shortcuts-help {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 1rem;
            border-radius: 10px;
            font-size: 0.9rem;
            z-index: 1000;
            display: none;
        }

        .shortcuts-help.show {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }

        .shortcut-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .shortcut-item:last-child {
            margin-bottom: 0;
        }

        kbd {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        /* Loading states */
        .notification-item.loading {
            opacity: 0.5;
            pointer-events: none;
        }

        .notification-item.loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            border: 2px solid var(--notification-border);
            border-top: 2px solid var(--notification-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            transform: translate(-50%, -50%);
        }

        /* Notification sound toggle */
        .sound-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            background: var(--notification-primary);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            z-index: 999;
        }

        .sound-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }

        .sound-toggle.muted {
            background: #6c757d;
        }

        /* Notification categories */
        .notification-category {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background: var(--notification-bg);
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        .notification-category.high-priority {
            background: rgba(244, 67, 54, 0.1);
            color: var(--notification-danger);
        }

        .notification-category.medium-priority {
            background: rgba(255, 152, 0, 0.1);
            color: var(--notification-warning);
        }

        .notification-category.low-priority {
            background: rgba(76, 175, 80, 0.1);
            color: var(--notification-success);
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            :root {
                --notification-bg: #2c3e50;
                --notification-border: #34495e;
                --text-color: #ecf0f1;
            }

            .notifications-list,
            .notifications-controls,
            .stat-card,
            .preferences-content {
                background: #34495e;
                color: var(--text-color);
            }

            .notification-item:hover {
                background: #3c4f66;
            }

            .notification-item.unread {
                background: rgba(102, 126, 234, 0.2);
            }
        }

        /* Print styles */
        @media print {

            .notifications-controls,
            .notification-actions,
            .preferences-modal,
            .toast-notification,
            .sound-toggle {
                display: none !important;
            }

            .notifications-container {
                padding: 0;
            }

            .notification-item {
                break-inside: avoid;
                margin-bottom: 1rem;
                border: 1px solid #ddd;
                border-radius: 5px;
            }
        }
    </style>
</body>

</html>