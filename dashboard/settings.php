<?php

/**
 * ⚙️ User Settings - EMS Dashboard
 * Customize Your Experience! 
 */

require_once '../includes/functions.php';

// Get database connection
$conn = require_once '../config/database.php';

// Initialize session manager
require_once '../includes/session.php';
$sessionManager = new SessionManager($conn);

// Require login
$sessionManager->requireLogin();
$currentUser = $sessionManager->getCurrentUser();
$userId = $currentUser['user_id'];

if (!$currentUser['email_verified'] == 1) {
    header('Location: verify_email.php');
    exit;
}
// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_preferences'])) {
        // Update notification preferences
        $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
        $smsNotifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $pushNotifications = isset($_POST['push_notifications']) ? 1 : 0;
        $eventReminders = isset($_POST['event_reminders']) ? 1 : 0;
        $marketingEmails = isset($_POST['marketing_emails']) ? 1 : 0;
        $weeklyDigest = isset($_POST['weekly_digest']) ? 1 : 0;

        // Privacy settings
        $profileVisibility = $_POST['profile_visibility'] ?? 'public';
        $showAttendedEvents = isset($_POST['show_attended_events']) ? 1 : 0;
        $allowMessages = isset($_POST['allow_messages']) ? 1 : 0;

        // Display preferences
        $theme = $_POST['theme'] ?? 'light';
        $language = $_POST['language'] ?? 'en';
        $timezone = $_POST['timezone'] ?? 'Africa/Blantyre';
        $dateFormat = $_POST['date_format'] ?? 'Y-m-d';

        try {
            // Check if user preferences exist
            $stmt = $conn->prepare("SELECT user_id FROM user_preferences WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;

            if ($exists) {
                // Update existing preferences
                $stmt = $conn->prepare("
                    UPDATE user_preferences SET
                        email_notifications = ?, sms_notifications = ?, push_notifications = ?,
                        event_reminders = ?, marketing_emails = ?, weekly_digest = ?,
                        profile_visibility = ?, show_attended_events = ?, allow_messages = ?,
                        theme = ?, language = ?, timezone = ?, date_format = ?,
                        updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt->bind_param(
                    "iiiiiisisssssi",
                    $emailNotifications,
                    $smsNotifications,
                    $pushNotifications,
                    $eventReminders,
                    $marketingEmails,
                    $weeklyDigest,
                    $profileVisibility,
                    $showAttendedEvents,
                    $allowMessages,
                    $theme,
                    $language,
                    $timezone,
                    $dateFormat,
                    $userId
                );
            } else {
                // Insert new preferences
                $stmt = $conn->prepare("
                    INSERT INTO user_preferences (
                        user_id, email_notifications, sms_notifications, push_notifications,
                        event_reminders, marketing_emails, weekly_digest,
                        profile_visibility, show_attended_events, allow_messages,
                        theme, language, timezone, date_format, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param(
                    "iiiiiisissssss",
                    $userId,
                    $emailNotifications,
                    $smsNotifications,
                    $pushNotifications,
                    $eventReminders,
                    $marketingEmails,
                    $weeklyDigest,
                    $profileVisibility,
                    $showAttendedEvents,
                    $allowMessages,
                    $theme,
                    $language,
                    $timezone,
                    $dateFormat
                );
            }

            if ($stmt->execute()) {
                $message = "Settings updated successfully!";
                $messageType = "success";
            } else {
                $message = "Failed to update settings";
                $messageType = "error";
            }
        } catch (Exception $e) {
            $message = "Database error occurred";
            $messageType = "error";
            error_log("Settings update error: " . $e->getMessage());
        }
    }

    if (isset($_POST['clear_data'])) {
        $dataType = $_POST['data_type'];

        try {
            switch ($dataType) {
                case 'notifications':
                    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $message = "Notifications cleared successfully!";
                    break;

                case 'activity_log':
                    $stmt = $conn->prepare("DELETE FROM user_activity_log WHERE user_id = ?");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $message = "Activity log cleared successfully!";
                    break;

                case 'search_history':
                    $stmt = $conn->prepare("DELETE FROM user_search_history WHERE user_id = ?");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $message = "Search history cleared successfully!";
                    break;

                default:
                    $message = "Invalid data type selected";
                    $messageType = "error";
            }

            if ($messageType !== "error") {
                $messageType = "success";
            }
        } catch (Exception $e) {
            $message = "Failed to clear data";
            $messageType = "error";
            error_log("Data clearing error: " . $e->getMessage());
        }
    }
}

// Get current user preferences
$userPreferences = [];
try {
    $stmt = $conn->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $userPreferences = $result->fetch_assoc();
    } else {
        // Default preferences
        $userPreferences = [
            'email_notifications' => 1,
            'sms_notifications' => 0,
            'push_notifications' => 1,
            'event_reminders' => 1,
            'marketing_emails' => 0,
            'weekly_digest' => 1,
            'profile_visibility' => 'public',
            'show_attended_events' => 1,
            'allow_messages' => 1,
            'theme' => 'light',
            'language' => 'en',
            'timezone' => 'Africa/Blantyre',
            'date_format' => 'Y-m-d'
        ];
    }
} catch (Exception $e) {
    error_log("Preferences fetch error: " . $e->getMessage());
    $userPreferences = [];
}

// Get storage usage statistics
$storageStats = [];
try {
    // Count notifications
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $storageStats['notifications'] = $stmt->get_result()->fetch_assoc()['count'];

    // Count activity logs
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_activity_log WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $storageStats['activity_logs'] = $stmt->get_result()->fetch_assoc()['count'];

    // Count search history
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_search_history WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $storageStats['search_history'] = $stmt->get_result()->fetch_assoc()['count'];

    // Count tickets
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tickets WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $storageStats['tickets'] = $stmt->get_result()->fetch_assoc()['count'];
} catch (Exception $e) {
    error_log("Storage stats error: " . $e->getMessage());
    $storageStats = [
        'notifications' => 0,
        'activity_logs' => 0,
        'search_history' => 0,
        'tickets' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - EMS Dashboard</title>

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
            --info-gradient: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            --danger-gradient: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            --sidebar-bg: #2c3e50;
            --sidebar-hover: #34495e;
            --content-bg: #f8f9fa;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --card-hover-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
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

        /* 🎨 Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: var(--sidebar-bg);
            color: white;
            transition: all 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .sidebar-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: var(--secondary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .sidebar-header p {
            font-size: 0.9rem;
            opacity: 0.7;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.5rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link:hover,
        .nav-link.active {
            background: var(--sidebar-hover);
            color: white;
            transform: translateX(5px);
        }

        .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--secondary-gradient);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .nav-link:hover::before,
        .nav-link.active::before {
            transform: scaleY(1);
        }

        .nav-icon {
            font-size: 1.2rem;
            margin-right: 1rem;
            width: 20px;
            text-align: center;
        }

        .nav-text {
            font-weight: 500;
        }

        /* 📱 Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 80px;
        }

        /* 🎯 Top Bar */
        .top-bar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--sidebar-bg);
            margin: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .user-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--sidebar-bg);
        }

        .user-details small {
            color: #6c757d;
            text-transform: capitalize;
        }

        /* ⚙️ Settings Content */
        .settings-content {
            padding: 2rem;
        }

        /* 🎪 Content Cards */
        .settings-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .settings-card:hover {
            box-shadow: var(--card-hover-shadow);
        }

        .card-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e9ecef;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--sidebar-bg);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-subtitle {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .card-body {
            padding: 2rem;
        }

        /* 🎛️ Settings Groups */
        .settings-group {
            margin-bottom: 2rem;
        }

        .settings-group:last-child {
            margin-bottom: 0;
        }

        .settings-group-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--sidebar-bg);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .settings-group-title i {
            color: #667eea;
        }

        /* 🎚️ Setting Items */
        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f8f9fa;
        }

        .setting-item:last-child {
            border-bottom: none;
        }

        .setting-info {
            flex: 1;
        }

        .setting-label {
            font-weight: 500;
            color: var(--sidebar-bg);
            margin-bottom: 0.25rem;
        }

        .setting-description {
            font-size: 0.9rem;
            color: #6c757d;
            margin: 0;
        }

        .setting-control {
            margin-left: 1rem;
        }

        /* 🎯 Toggle Switches */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.toggle-slider {
            background: var(--primary-gradient);
        }

        input:checked+.toggle-slider:before {
            transform: translateX(26px);
        }

        /* 📝 Form Controls */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--sidebar-bg);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control,
        .form-select {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus,
        .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* 🎯 Buttons */
        .btn {
            padding: 0.8rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            color: white;
        }

        .btn-success {
            background: var(--success-gradient);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(76, 175, 80, 0.3);
            color: white;
        }

        .btn-danger {
            background: var(--danger-gradient);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(244, 67, 54, 0.3);
            color: white;
        }

        .btn-outline {
            background: transparent;
            color: var(--sidebar-bg);
            border: 2px solid #e9ecef;
        }

        .btn-outline:hover {
            background: #f8f9fa;
            border-color: #667eea;
            color: #667eea;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        /* 📊 Storage Usage */
        .storage-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .storage-item:last-child {
            margin-bottom: 0;
        }

        .storage-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .storage-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
        }

        .storage-icon.notifications {
            background: var(--info-gradient);
        }

        .storage-icon.activity {
            background: var(--success-gradient);
        }

        .storage-icon.search {
            background: var(--warning-gradient);
        }

        .storage-icon.tickets {
            background: var(--primary-gradient);
        }

        .storage-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--sidebar-bg);
        }

        .storage-details p {
            margin: 0;
            font-size: 0.9rem;
            color: #6c757d;
        }

        .storage-count {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--sidebar-bg);
        }

        /* 🎨 Theme Preview */
        .theme-preview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .theme-option {
            position: relative;
            border: 3px solid transparent;
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .theme-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .theme-option.selected {
            border-color: #667eea;
        }

        .theme-preview-content {
            height: 100px;
            display: flex;
            flex-direction: column;
        }

        .theme-header {
            height: 30%;
            display: flex;
            align-items: center;
            padding: 0 1rem;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .theme-body {
            height: 70%;
            padding: 0.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .theme-line {
            height: 8px;
            border-radius: 4px;
            opacity: 0.7;
        }

        /* Light Theme */
        .theme-light .theme-header {
            background: #f8f9fa;
            color: #2c3e50;
        }

        .theme-light .theme-body {
            background: white;
        }

        .theme-light .theme-line {
            background: #e9ecef;
        }

        /* Dark Theme */
        .theme-dark .theme-header {
            background: #2c3e50;
            color: white;
        }

        .theme-dark .theme-body {
            background: #34495e;
        }

        .theme-dark .theme-line {
            background: #4a5568;
        }

        /* Auto Theme */
        .theme-auto .theme-header {
            background: linear-gradient(90deg, #f8f9fa 50%, #2c3e50 50%);
            color: #2c3e50;
        }

        .theme-auto .theme-body {
            background: linear-gradient(90deg, white 50%, #34495e 50%);
        }

        .theme-auto .theme-line {
            background: linear-gradient(90deg, #e9ecef 50%, #4a5568 50%);
        }

        .theme-label {
            text-align: center;
            margin-top: 0.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--sidebar-bg);
        }

        /* 🚨 Danger Zone */
        .danger-zone {
            border: 2px solid #f44336;
            border-radius: 15px;
            padding: 2rem;
            background: rgba(244, 67, 54, 0.05);
        }

        .danger-zone-title {
            color: #f44336;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .danger-zone-description {
            color: #6c757d;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        /* 📱 Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .top-bar {
                padding: 1rem;
            }

            .settings-content {
                padding: 1rem;
            }

            .setting-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .setting-control {
                margin-left: 0;
                width: 100%;
            }

            .theme-preview {
                grid-template-columns: 1fr 1fr;
            }

            .card-body {
                padding: 1rem;
            }
        }

        /* 🎨 Animations */
        .fade-in {
            animation: fadeIn 0.6s ease-in;
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
            animation: slideIn 0.6s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* 🎯 Loading States */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
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
    </style>
</head>

<body>
    <!-- 🎨 Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>🎓 EMS</h3>
            <p>Event Management</p>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-home nav-icon"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="events.php" class="nav-link">
                    <i class="fas fa-calendar-alt nav-icon"></i>
                    <span class="nav-text">Browse Events</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="my-events.php" class="nav-link">
                    <i class="fas fa-ticket-alt nav-icon"></i>
                    <span class="nav-text">My Events</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="notifications.php" class="nav-link">
                    <i class="fas fa-bell nav-icon"></i>
                    <span class="nav-text">Notifications</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="profile.php" class="nav-link">
                    <i class="fas fa-user nav-icon"></i>
                    <span class="nav-text">Profile</span>
                </a>
            </div>

            <?php if ($currentUser['role'] === 'organizer'): ?>
                <div class="nav-item">
                    <a href="../organizer/dashboard.php" class="nav-link">
                        <i class="fas fa-plus-circle nav-icon"></i>
                        <span class="nav-text">Create Event</span>
                    </a>
                </div>
            <?php endif; ?>

            <div class="nav-item">
                <a href="settings.php" class="nav-link active">
                    <i class="fas fa-cog nav-icon"></i>
                    <span class="nav-text">Settings</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="../auth/logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt nav-icon"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- 📱 Main Content -->
    <div class="main-content" id="mainContent">
        <!-- 🎯 Top Bar -->
        <div class="top-bar">
            <div class="d-flex align-items-center">
                <button class="btn btn-link d-md-none" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">
                    <i class="fas fa-cog"></i> Settings
                </h1>
            </div>

            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)); ?>
                </div>
                <div class="user-details">
                    <h6><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></h6>
                    <small><?php echo htmlspecialchars($currentUser['role']); ?></small>
                </div>
            </div>
        </div>

        <!-- ⚙️ Settings Content -->
        <div class="settings-content">
            <!-- Alert Messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> fade-in">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- 🔔 Notification Preferences -->
            <div class="settings-card fade-in">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-bell"></i>
                        Notification Preferences
                    </h3>
                    <p class="card-subtitle">Customize how you receive notifications</p>
                </div>
                <div class="card-body">
                    <form method="POST" id="preferencesForm">
                        <div class="settings-group">
                            <div class="settings-group-title">
                                <i class="fas fa-envelope"></i>
                                Communication Channels
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <div class="setting-label">Email Notifications</div>
                                    <p class="setting-description">Receive notifications via email</p>
                                </div>
                                <div class="setting-control">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="email_notifications"
                                            <?php echo ($userPreferences['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <div class="setting-label">SMS Notifications</div>
                                    <p class="setting-description">Receive important alerts via SMS</p>
                                </div>
                                <div class="setting-control">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="sms_notifications"
                                            <?php echo ($userPreferences['sms_notifications'] ?? 0) ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <div class="setting-label">Push Notifications</div>
                                    <p class="setting-description">Receive browser push notifications</p>
                                </div>
                                <div class="setting-control">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="push_notifications"
                                            <?php echo ($userPreferences['push_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="settings-group">
                            <div class="settings-group-title">
                                <i class="fas fa-calendar-alt"></i>
                                Event Notifications
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <div class="setting-label">Event Reminders</div>
                                    <p class="setting-description">Get reminded about upcoming events</p>
                                </div>
                                <div class="setting-control">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="event_reminders"
                                            <?php echo ($userPreferences['event_reminders'] ?? 1) ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <div class="setting-label">Marketing Emails</div>
                                    <p class="setting-description">Receive promotional content and event suggestions</p>
                                </div>
                                <div class="setting-control">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="marketing_emails"
                                            <?php echo ($userPreferences['marketing_emails'] ?? 0) ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <div class="setting-label">Weekly Digest</div>
                                    <p class="setting-description">Get a weekly summary of events and activities</p>
                                </div>
                                <div class="setting-control">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="weekly_digest"
                                            <?php echo ($userPreferences['weekly_digest'] ?? 1) ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 🔒 Privacy Settings -->
            <div class="settings-card slide-in">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-shield-alt"></i>
                        Privacy Settings
                    </h3>
                    <p class="card-subtitle">Control your privacy and data visibility</p>
                </div>
                <div class="card-body">
                    <div class="settings-group">
                        <div class="settings-group-title">
                            <i class="fas fa-eye"></i>
                            Profile Visibility
                        </div>

                        <div class="form-group">
                            <label class="form-label">Who can see your profile?</label>
                            <select class="form-select" name="profile_visibility" form="preferencesForm">
                                <option value="public" <?php echo ($userPreferences['profile_visibility'] ?? 'public') === 'public' ? 'selected' : ''; ?>>
                                    Everyone
                                </option>
                                <option value="students" <?php echo ($userPreferences['profile_visibility'] ?? 'public') === 'students' ? 'selected' : ''; ?>>
                                    Students Only
                                </option>
                                <option value="private" <?php echo ($userPreferences['profile_visibility'] ?? 'public') === 'private' ? 'selected' : ''; ?>>
                                    Private
                                </option>
                            </select>
                        </div>

                        <div class="setting-item">
                            <div class="setting-info">
                                <div class="setting-label">Show Attended Events</div>
                                <p class="setting-description">Display events you've attended on your profile</p>
                            </div>
                            <div class="setting-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="show_attended_events" form="preferencesForm"
                                        <?php echo ($userPreferences['show_attended_events'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="setting-item">
                            <div class="setting-info">
                                <div class="setting-label">Allow Messages</div>
                                <p class="setting-description">Let other users send you messages</p>
                            </div>
                            <div class="setting-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="allow_messages" form="preferencesForm"
                                        <?php echo ($userPreferences['allow_messages'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🎨 Display Preferences -->
            <div class="settings-card slide-in">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-palette"></i>
                        Display Preferences
                    </h3>
                    <p class="card-subtitle">Customize your viewing experience</p>
                </div>
                <div class="card-body">
                    <div class="settings-group">
                        <div class="settings-group-title">
                            <i class="fas fa-moon"></i>
                            Theme
                        </div>

                        <div class="theme-preview">
                            <div class="theme-option theme-light <?php echo ($userPreferences['theme'] ?? 'light') === 'light' ? 'selected' : ''; ?>"
                                onclick="selectTheme('light')">
                                <div class="theme-preview-content">
                                    <div class="theme-header">Light Theme</div>
                                    <div class="theme-body">
                                        <div class="theme-line" style="width: 80%;"></div>
                                        <div class="theme-line" style="width: 60%;"></div>
                                        <div class="theme-line" style="width: 90%;"></div>
                                    </div>
                                </div>
                                <div class="theme-label">Light</div>
                            </div>


                        <input type="hidden" name="theme" id="themeInput" form="preferencesForm"
                            value="<?php echo htmlspecialchars($userPreferences['theme'] ?? 'light'); ?>">
                    </div>

                    <div class="settings-group">
                        <div class="settings-group-title">
                            <i class="fas fa-globe"></i>
                            Localization
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Language</label>
                                    <select class="form-select" name="language" form="preferencesForm">
                                        <option value="en" <?php echo ($userPreferences['language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>
                                            English
                                        </option>
                                        <option value="ny" <?php echo ($userPreferences['language'] ?? 'en') === 'ny' ? 'selected' : ''; ?>>
                                            Chichewa
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Timezone</label>
                                    <select class="form-select" name="timezone" form="preferencesForm">
                                        <option value="Africa/Blantyre" <?php echo ($userPreferences['timezone'] ?? 'Africa/Blantyre') === 'Africa/Blantyre' ? 'selected' : ''; ?>>
                                            Africa/Blantyre (CAT)
                                        </option>
                                        <option value="UTC" <?php echo ($userPreferences['timezone'] ?? 'Africa/Blantyre') === 'UTC' ? 'selected' : ''; ?>>
                                            UTC
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Date Format</label>
                            <select class="form-select" name="date_format" form="preferencesForm">
                                <option value="Y-m-d" <?php echo ($userPreferences['date_format'] ?? 'Y-m-d') === 'Y-m-d' ? 'selected' : ''; ?>>
                                    2024-01-15
                                </option>
                                <option value="d/m/Y" <?php echo ($userPreferences['date_format'] ?? 'Y-m-d') === 'd/m/Y' ? 'selected' : ''; ?>>
                                    15/01/2024
                                </option>
                                <option value="m/d/Y" <?php echo ($userPreferences['date_format'] ?? 'Y-m-d') === 'm/d/Y' ? 'selected' : ''; ?>>
                                    01/15/2024
                                </option>
                                <option value="F j, Y" <?php echo ($userPreferences['date_format'] ?? 'Y-m-d') === 'F j, Y' ? 'selected' : ''; ?>>
                                    January 15, 2024
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" name="update_preferences" form="preferencesForm" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Preferences
                        </button>
                    </div>
                </div>
            </div>

            <!-- 📊 Data & Storage -->
            <div class="settings-card slide-in">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-database"></i>
                        Data & Storage
                    </h3>
                    <p class="card-subtitle">Manage your data and storage usage</p>
                </div>
                <div class="card-body">
                    <div class="settings-group">
                        <div class="settings-group-title">
                            <i class="fas fa-chart-pie"></i>
                            Storage Usage
                        </div>

                        <div class="storage-item">
                            <div class="storage-info">
                                <div class="storage-icon notifications">
                                    <i class="fas fa-bell"></i>
                                </div>
                                <div class="storage-details">
                                    <h6>Notifications</h6>
                                    <p>Stored notification history</p>
                                </div>
                            </div>
                            <div class="storage-count"><?php echo number_format($storageStats['notifications']); ?></div>
                        </div>

                        <div class="storage-item">
                            <div class="storage-info">
                                <div class="storage-icon activity">
                                    <i class="fas fa-history"></i>
                                </div>
                                <div class="storage-details">
                                    <h6>Activity Logs</h6>
                                    <p>Your activity history</p>
                                </div>
                            </div>
                            <div class="storage-count"><?php echo number_format($storageStats['activity_logs']); ?></div>
                        </div>

                        <div class="storage-item">
                            <div class="storage-info">
                                <div class="storage-icon search">
                                    <i class="fas fa-search"></i>
                                </div>
                                <div class="storage-details">
                                    <h6>Search History</h6>
                                    <p>Your search queries</p>
                                </div>
                            </div>
                            <div class="storage-count"><?php echo number_format($storageStats['search_history']); ?></div>
                        </div>

                        <div class="storage-item">
                            <div class="storage-info">
                                <div class="storage-icon tickets">
                                    <i class="fas fa-ticket-alt"></i>
                                </div>
                                <div class="storage-details">
                                    <h6>Event Tickets</h6>
                                    <p>Your event registrations</p>
                                </div>
                            </div>
                            <div class="storage-count"><?php echo number_format($storageStats['tickets']); ?></div>
                        </div>
                    </div>

                    <div class="settings-group">
                        <div class="settings-group-title">
                            <i class="fas fa-broom"></i>
                            Data Management
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="data_type" value="notifications">
                                    <button type="submit" name="clear_data" class="btn btn-outline w-100"
                                        onclick="return confirm('Clear all notifications? This cannot be undone.')">
                                        <i class="fas fa-bell-slash"></i>
                                        <br>Clear Notifications
                                    </button>
                                </form>
                            </div>

                            <div class="col-md-4 mb-3">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="data_type" value="activity_log">
                                    <button type="submit" name="clear_data" class="btn btn-outline w-100"
                                        onclick="return confirm('Clear activity log? This cannot be undone.')">
                                        <i class="fas fa-history"></i>
                                        <br>Clear Activity
                                    </button>
                                </form>
                            </div>

                            <div class="col-md-4 mb-3">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="data_type" value="search_history">
                                    <button type="submit" name="clear_data" class="btn btn-outline w-100"
                                        onclick="return confirm('Clear search history? This cannot be undone.')">
                                        <i class="fas fa-search-minus"></i>
                                        <br>Clear Searches
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="text-center mt-3">
                            <button type="button" class="btn btn-success" onclick="exportData()">
                                <i class="fas fa-download"></i> Export My Data
                            </button>
                        </div>
                    </div>
                </div>
            </div>

    

    <!-- 🗂️ Export Data Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-download"></i> Export Your Data
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Select the data you want to export:</p>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="exportProfile" checked>
                        <label class="form-check-label" for="exportProfile">
                            <i class="fas fa-user"></i> Profile Information
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="exportEvents" checked>
                        <label class="form-check-label" for="exportEvents">
                            <i class="fas fa-calendar-alt"></i> Event History
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="exportTickets" checked>
                        <label class="form-check-label" for="exportTickets">
                            <i class="fas fa-ticket-alt"></i> Tickets & Registrations
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="exportNotifications">
                        <label class="form-check-label" for="exportNotifications">
                            <i class="fas fa-bell"></i> Notifications
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="exportActivity">
                        <label class="form-check-label" for="exportActivity">
                            <i class="fas fa-history"></i> Activity Log
                        </label>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Your data will be exported in JSON format and downloaded as a ZIP file.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="processExport()">
                        <i class="fas fa-download"></i> Export Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 🚨 Deactivate Account Modal -->
    <div class="modal fade" id="deactivateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-warning">
                        <i class="fas fa-exclamation-triangle"></i> Deactivate Account
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>Warning!</strong> Deactivating your account will:
                    </div>

                    <ul class="list-unstyled">
                        <li><i class="fas fa-times text-danger"></i> Hide your profile from other users</li>
                        <li><i class="fas fa-times text-danger"></i> Cancel all upcoming event registrations</li>
                        <li><i class="fas fa-times text-danger"></i> Disable notifications</li>
                        <li><i class="fas fa-check text-success"></i> Preserve your data for reactivation</li>
                    </ul>

                    <p>You can reactivate your account anytime by logging in again.</p>

                    <div class="form-group">
                        <label class="form-label">Enter your password to confirm:</label>
                        <input type="password" class="form-control" id="deactivatePassword"
                            placeholder="Your current password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="processDeactivation()">
                        <i class="fas fa-user-slash"></i> Deactivate Account
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 💀 Delete Account Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-skull-crossbones"></i> Delete Account
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <strong>DANGER!</strong> This action cannot be undone!
                    </div>

                    <p>Deleting your account will permanently:</p>

                    <ul class="list-unstyled">
                        <li><i class="fas fa-times text-danger"></i> Delete all your personal data</li>
                        <li><i class="fas fa-times text-danger"></i> Cancel all event registrations</li>
                        <li><i class="fas fa-times text-danger"></i> Remove your profile completely</li>
                        <li><i class="fas fa-times text-danger"></i> Delete all notifications and history</li>
                    </ul>

                    <div class="form-group mb-3">
                        <label class="form-label">Type "DELETE" to confirm:</label>
                        <input type="text" class="form-control" id="deleteConfirmation"
                            placeholder="Type DELETE here">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Enter your password:</label>
                        <input type="password" class="form-control" id="deletePassword"
                            placeholder="Your current password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="processDeletion()" disabled id="deleteButton">
                        <i class="fas fa-trash-alt"></i> Delete Forever
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 📱 Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // 🎯 Settings Controller
        class SettingsController {
            constructor() {
                this.init();
            }

            init() {
                this.bindEvents();
                this.initializeTheme();
                this.setupFormValidation();
            }

            bindEvents() {
                // Sidebar toggle for mobile
                const sidebarToggle = document.getElementById('sidebarToggle');
                const sidebar = document.getElementById('sidebar');

                if (sidebarToggle) {
                    sidebarToggle.addEventListener('click', () => {
                        sidebar.classList.toggle('show');
                    });
                }

                // Close sidebar when clicking outside on mobile
                document.addEventListener('click', (e) => {
                    if (window.innerWidth <= 768) {
                        if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                            sidebar.classList.remove('show');
                        }
                    }
                });

                // Auto-save preferences on change
                const form = document.getElementById('preferencesForm');
                if (form) {
                    const inputs = form.querySelectorAll('input, select');
                    inputs.forEach(input => {
                        input.addEventListener('change', () => {
                            this.autoSave();
                        });
                    });
                }

                // Delete confirmation input validation
                const deleteConfirmation = document.getElementById('deleteConfirmation');
                const deleteButton = document.getElementById('deleteButton');

                if (deleteConfirmation && deleteButton) {
                    deleteConfirmation.addEventListener('input', (e) => {
                        deleteButton.disabled = e.target.value !== 'DELETE';
                    });
                }
            }

            initializeTheme() {
                const currentTheme = document.getElementById('themeInput').value;
                this.applyTheme(currentTheme);
            }

            applyTheme(theme) {
                document.body.className = document.body.className.replace(/theme-\w+/g, '');

                if (theme === 'dark') {
                    document.body.classList.add('theme-dark');
                } else if (theme === 'auto') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    document.body.classList.add(prefersDark ? 'theme-dark' : 'theme-light');
                } else {
                    document.body.classList.add('theme-light');
                }
            }

            setupFormValidation() {
                const forms = document.querySelectorAll('form');
                forms.forEach(form => {
                    form.addEventListener('submit', (e) => {
                        if (!this.validateForm(form)) {
                            e.preventDefault();
                        }
                    });
                });
            }

            validateForm(form) {
                let isValid = true;
                const requiredFields = form.querySelectorAll('[required]');

                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        this.showFieldError(field, 'This field is required');
                        isValid = false;
                    } else {
                        this.clearFieldError(field);
                    }
                });

                return isValid;
            }

            showFieldError(field, message) {
                field.classList.add('is-invalid');

                let errorDiv = field.parentNode.querySelector('.field-error');
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'field-error text-danger mt-1';
                    field.parentNode.appendChild(errorDiv);
                }
                errorDiv.textContent = message;
            }

            clearFieldError(field) {
                field.classList.remove('is-invalid');
                const errorDiv = field.parentNode.querySelector('.field-error');
                if (errorDiv) {
                    errorDiv.remove();
                }
            }

            autoSave() {
                // Show saving indicator
                this.showSavingIndicator();

                // Debounce auto-save
                clearTimeout(this.autoSaveTimeout);
                this.autoSaveTimeout = setTimeout(() => {
                    this.savePreferences();
                }, 1000);
            }

            showSavingIndicator() {
                const indicator = document.createElement('div');
                indicator.className = 'saving-indicator';
                indicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                indicator.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #667eea;
                    color: white;
                    padding: 0.5rem 1rem;
                    border-radius: 20px;
                    z-index: 9999;
                    font-size: 0.9rem;
                `;

                document.body.appendChild(indicator);

                setTimeout(() => {
                    if (indicator.parentNode) {
                        indicator.remove();
                    }
                }, 2000);
            }

            savePreferences() {
                const form = document.getElementById('preferencesForm');
                const formData = new FormData(form);
                formData.append('update_preferences', '1');

                fetch('settings.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        // Handle response silently for auto-save
                        console.log('Preferences saved');
                    })
                    .catch(error => {
                        console.error('Auto-save failed:', error);
                    });
            }
        }

        // 🎨 Theme Selection
        function selectTheme(theme) {
            // Update visual selection
            document.querySelectorAll('.theme-option').forEach(option => {
                option.classList.remove('selected');
            });
            document.querySelector(`.theme-${theme}`).classList.add('selected');

            // Update hidden input
            document.getElementById('themeInput').value = theme;

            // Apply theme immediately
            const controller = new SettingsController();
            controller.applyTheme(theme);

            // Auto-save
            controller.autoSave();
        }

        // 📊 Export Data
        function exportData() {
            const modal = new bootstrap.Modal(document.getElementById('exportModal'));
            modal.show();
        }

        function processExport() {
            const selectedData = [];

            // Check which data types are selected
            if (document.getElementById('exportProfile').checked) selectedData.push('profile');
            if (document.getElementById('exportEvents').checked) selectedData.push('events');
            if (document.getElementById('exportTickets').checked) selectedData.push('tickets');
            if (document.getElementById('exportNotifications').checked) selectedData.push('notifications');
            if (document.getElementById('exportActivity').checked) selectedData.push('activity');

            if (selectedData.length === 0) {
                alert('Please select at least one data type to export.');
                return;
            }

            // Show loading
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
            button.disabled = true;

            // Create export request
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'export-data.php';

            selectedData.forEach(dataType => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'export_types[]';
                input.value = dataType;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            // Reset button after delay
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
                bootstrap.Modal.getInstance(document.getElementById('exportModal')).hide();
            }, 3000);
        }

        // 🚨 Account Deactivation
        function deactivateAccount() {
            const modal = new bootstrap.Modal(document.getElementById('deactivateModal'));
            modal.show();
        }

        function processDeactivation() {
            const password = document.getElementById('deactivatePassword').value;

            if (!password) {
                alert('Please enter your password to confirm deactivation.');
                return;
            }

            // Show loading
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deactivating...';
            button.disabled = true;

            // Send deactivation request
            fetch('account-actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'deactivate',
                        password: password
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Account deactivated successfully. You will be redirected to the login page.');
                        window.location.href = '../auth/login.php';
                    } else {
                        alert(data.message || 'Failed to deactivate account. Please try again.');
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Deactivation error:', error);
                    alert('An error occurred. Please try again.');
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
        }

        // 💀 Account Deletion
        function deleteAccount() {
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }

        function processDeletion() {
            const confirmation = document.getElementById('deleteConfirmation').value;
            const password = document.getElementById('deletePassword').value;

            if (confirmation !== 'DELETE') {
                alert('Please type "DELETE" to confirm account deletion.');
                return;
            }

            if (!password) {
                alert('Please enter your password to confirm deletion.');
                return;
            }

            // Final confirmation
            if (!confirm('This is your FINAL WARNING! Your account and all data will be permanently deleted. This cannot be undone. Are you absolutely sure?')) {
                return;
            }

            // Show loading
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
            button.disabled = true;

            // Send deletion request
            fetch('account-actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        password: password,
                        confirmation: confirmation
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Account deleted successfully. Goodbye!');
                        window.location.href = '../auth/login.php';
                    } else {
                        alert(data.message || 'Failed to delete account. Please try again.');
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Deletion error:', error);
                    alert('An error occurred. Please try again.');
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
        }

        // 🎯 Initialize Settings
        document.addEventListener('DOMContentLoaded', () => {
            new SettingsController();

            // Add smooth scrolling to anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Add loading states to all buttons
            document.querySelectorAll('.btn').forEach(button => {
                if (!button.hasAttribute('data-no-loading')) {
                    button.addEventListener('click', function(e) {
                        if (this.type === 'submit' || this.form) {
                            const originalText = this.innerHTML;
                            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                            this.disabled = true;

                            // Re-enable after form submission
                            setTimeout(() => {
                                this.innerHTML = originalText;
                                this.disabled = false;
                            }, 3000);
                        }
                    });
                }
            });

            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Add confirmation to dangerous actions
            document.querySelectorAll('.btn-danger').forEach(button => {
                if (!button.hasAttribute('data-no-confirm')) {
                    button.addEventListener('click', function(e) {
                        if (!confirm('Are you sure you want to perform this action?')) {
                            e.preventDefault();
                        }
                    });
                }
            });
        });

        // 🎨 Additional CSS for dynamic elements
        const additionalStyles = `
            <style>
                .theme-dark {
                    --content-bg: #1a1a2e;
                    --sidebar-bg: #16213e;
                    --sidebar-hover: #0f172a;
                    color: #e2e8f0;
                }
                
                .theme-dark .settings-card,
                .theme-dark .top-bar {
                    background: #2d3748;
                    color: #e2e8f0;
                }
                
                .theme-dark .card-header {
                    background: linear-gradient(135deg, #2d3748 0%, #4a5568 100%);
                    border-color: #4a5568;
                }
                
                .theme-dark .form-control,
                .theme-dark .form-select {
                    background: #4a5568;
                    border-color: #6b7280;
                    color: #e2e8f0;
                }
                
                .theme-dark .form-control:focus,
                .theme-dark .form-select:focus {
                    background: #4a5568;
                    border-color: #667eea;
                    color: #e2e8f0;
                }
                
                .theme-dark .storage-item {
                    background: #4a5568;
                }
                
                .theme-dark .setting-item {
                    border-color: #4a5568;
                }
                
                .theme-dark .modal-content {
                    background: #2d3748;
                    color: #e2e8f0;
                }
                
                .theme-dark .modal-header {
                    border-color: #4a5568;
                }
                
                .theme-dark .modal-footer {
                    border-color: #4a5568;
                }
                
                .field-error {
                    font-size: 0.8rem;
                    margin-top: 0.25rem;
                }
                
                .form-control.is-invalid,
                .form-select.is-invalid {
                    border-color: #f44336;
                    box-shadow: 0 0 0 3px rgba(244, 67, 54, 0.1);
                }
                
                .saving-indicator {
                    animation: slideInRight 0.3s ease-out;
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
                
                .settings-group-title {
                    position: relative;
                }
                
                .settings-group-title::after {
                    content: '';
                    position: absolute;
                    bottom: -2px;
                    left: 0;
                    width: 50px;
                    height: 2px;
                    background: var(--primary-gradient);
                    border-radius: 1px;
                }
                
                .storage-item:hover {
                    background: #e9ecef;
                    transform: translateX(5px);
                }
                
                .theme-dark .storage-item:hover {
                    background: #6b7280;
                }
                
                .setting-item:hover {
                    background: rgba(102, 126, 234, 0.05);
                }
                
                .theme-dark .setting-item:hover {
                    background: rgba(102, 126, 234, 0.1);
                }
                
                @media (max-width: 768px) {
                    .theme-preview {
                        grid-template-columns: 1fr;
                    }
                    
                    .storage-item {
                        flex-direction: column;
                        text-align: center;
                        gap: 1rem;
                    }
                    
                    .danger-zone {
                        padding: 1rem;
                    }
                }
            </style>
        `;

        document.head.insertAdjacentHTML('beforeend', additionalStyles);
    </script>
</body>

</html>