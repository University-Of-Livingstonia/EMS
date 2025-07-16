<?php

/**
 * 👤 User Profile - EMS Dashboard
 * Manage Your Personal Information! 
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
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $bio = trim($_POST['bio']);

        // Validate inputs
        $errors = [];

        if (empty($firstName)) $errors[] = "First name is required";
        if (empty($lastName)) $errors[] = "Last name is required";
        if (empty($email)) $errors[] = "Email is required";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";

        // Check if email is already taken by another user
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->bind_param("si", $email, $userId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "Email is already taken by another user";
        }

        if (empty($errors)) {
            try {
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, email = ?, phone = ?, bio = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt->bind_param("sssssi", $firstName, $lastName, $email, $phone, $bio, $userId);

                if ($stmt->execute()) {
                    $message = "Profile updated successfully!";
                    $messageType = "success";

                    // Refresh user data
                    $currentUser = $sessionManager->getCurrentUser();
                } else {
                    $message = "Failed to update profile";
                    $messageType = "error";
                }
            } catch (Exception $e) {
                $message = "Database error occurred";
                $messageType = "error";
                error_log("Profile update error: " . $e->getMessage());
            }
        } else {
            $message = implode(", ", $errors);
            $messageType = "error";
        }
    }

    if (isset($_POST['change_password'])) {
        // Change password
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        $errors = [];

        if (empty($currentPassword)) $errors[] = "Current password is required";
        if (empty($newPassword)) $errors[] = "New password is required";
        if (strlen($newPassword) < 6) $errors[] = "New password must be at least 6 characters";
        if ($newPassword !== $confirmPassword) $errors[] = "New passwords do not match";

        if (empty($errors)) {
            // Verify current password
            if (password_verify($currentPassword, $currentUser['password'])) {
                try {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?");
                    $stmt->bind_param("si", $hashedPassword, $userId);

                    if ($stmt->execute()) {
                        $message = "Password changed successfully!";
                        $messageType = "success";
                    } else {
                        $message = "Failed to change password";
                        $messageType = "error";
                    }
                } catch (Exception $e) {
                    $message = "Database error occurred";
                    $messageType = "error";
                    error_log("Password change error: " . $e->getMessage());
                }
            } else {
                $message = "Current password is incorrect";
                $messageType = "error";
            }
        } else {
            $message = implode(", ", $errors);
            $messageType = "error";
        }
    }
}

// Get user statistics for profile
$userStats = [];
try {
    // Events registered
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tickets WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userStats['events_registered'] = $stmt->get_result()->fetch_assoc()['count'];

    // Events attended
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tickets t 
        JOIN events e ON t.event_id = e.event_id 
        WHERE t.user_id = ? AND e.end_datetime < NOW() AND t.payment_status = 'completed'
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userStats['events_attended'] = $stmt->get_result()->fetch_assoc()['count'];

    // Total spent
    $stmt = $conn->prepare("SELECT SUM(amount_paid) as total FROM tickets WHERE user_id = ? AND payment_status = 'completed'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $userStats['total_spent'] = $result['total'] ?? 0;

    // Member since
    $userStats['member_since'] = date('M Y', strtotime($currentUser['created_at']));
} catch (Exception $e) {
    error_log("User stats error: " . $e->getMessage());
    $userStats = [
        'events_registered' => 0,
        'events_attended' => 0,
        'total_spent' => 0,
        'member_since' => 'Unknown'
    ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - EMS</title>

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

        /* 🎨 Sidebar Styles (Same as other pages) */
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

        /* 📊 Profile Content */
        .profile-content {
            padding: 2rem;
        }

        /* 🎯 Profile Header */
        .profile-header {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--primary-gradient);
        }

        .profile-header-content {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 3rem;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            position: relative;
        }

        .profile-avatar::after {
            content: '';
            position: absolute;
            inset: -5px;
            border-radius: 50%;
            background: var(--primary-gradient);
            z-index: -1;
            opacity: 0.3;
        }

        .profile-info h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--sidebar-bg);
            margin-bottom: 0.5rem;
        }

        .profile-info .role-badge {
            display: inline-block;
            padding: 0.4rem 1rem;
            background: var(--info-gradient);
            color: white;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: capitalize;
            margin-bottom: 1rem;
        }

        .profile-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .profile-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .profile-meta-item i {
            color: #667eea;
            width: 18px;
        }

        /* 📊 Stats Cards */
        .stats-section {
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
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

        .stat-card.primary::before {
            background: var(--primary-gradient);
        }

        .stat-card.success::before {
            background: var(--success-gradient);
        }

        .stat-card.warning::before {
            background: var(--warning-gradient);
        }

        .stat-card.info::before {
            background: var(--info-gradient);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-hover-shadow);
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
        }

        .stat-icon.primary {
            background: var(--primary-gradient);
        }

        .stat-icon.success {
            background: var(--success-gradient);
        }

        .stat-icon.warning {
            background: var(--warning-gradient);
        }

        .stat-icon.info {
            background: var(--info-gradient);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--sidebar-bg);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6c757d;
            font-weight: 500;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* 🎪 Content Cards */
        .content-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .content-card:hover {
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

        .card-body {
            padding: 2rem;
        }

        /* 📝 Forms */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--sidebar-bg);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control:disabled {
            background: #f8f9fa;
            color: #6c757d;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
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
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-success {
            background: var(--success-gradient);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }

        .btn-danger {
            background: var(--danger-gradient);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(244, 67, 54, 0.3);
        }

        .btn-outline {
            background: transparent;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-outline:hover {
            background: #667eea;
            color: white;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        /* 🚨 Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .alert-info {
            background: rgba(33, 150, 243, 0.1);
            color: #2196F3;
            border-left: 4px solid #2196F3;
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

            .profile-content {
                padding: 1rem;
            }

            .profile-header-content {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-number {
                font-size: 1.8rem;
            }

            .profile-meta {
                grid-template-columns: 1fr;
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

        /* 🎯 Password Strength Indicator */
        .password-strength {
            margin-top: 0.5rem;
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak {
            background: #f44336;
            width: 25%;
        }

        .strength-fair {
            background: #ff9800;
            width: 50%;
        }

        .strength-good {
            background: #2196F3;
            width: 75%;
        }

        .strength-strong {
            background: #4CAF50;
            width: 100%;
        }

        .password-requirements {
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: #6c757d;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            margin-bottom: 0.2rem;
        }

        .requirement.met {
            color: #4CAF50;
        }

        .requirement.met i {
            color: #4CAF50;
        }

        .requirement i {
            color: #e9ecef;
            font-size: 0.7rem;
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
                <a href="profile.php" class="nav-link active">
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
                <a href="settings.php" class="nav-link">
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
                    <i class="fas fa-user"></i> My Profile
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

        <!-- 📊 Profile Content -->
        <div class="profile-content">
            <!-- Alert Messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> fade-in">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- 🎯 Profile Header -->
            <div class="profile-header fade-in">
                <div class="profile-header-content">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)); ?>
                    </div>

                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></h2>
                        <span class="role-badge"><?php echo ucfirst($currentUser['role']); ?></span>

                        <div class="profile-meta">
                            <div class="profile-meta-item">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo htmlspecialchars($currentUser['email']); ?></span>
                            </div>
                            <div class="profile-meta-item">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($currentUser['phone'] ?? 'Not provided'); ?></span>
                            </div>
                            <div class="profile-meta-item">
                                <i class="fas fa-calendar-plus"></i>
                                <span>Member since <?php echo $userStats['member_since']; ?></span>
                            </div>
                            <div class="profile-meta-item">
                                <i class="fas fa-clock"></i>
                                <span>Last updated <?php echo timeAgo($currentUser['updated_at'] ?? $currentUser['created_at']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 📊 Stats Section -->
            <div class="stats-section">
                <div class="stats-grid">
                    <div class="stat-card primary slide-in">
                        <div class="stat-header">
                            <div class="stat-icon primary">
                                <i class="fas fa-ticket-alt"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $userStats['events_registered']; ?></div>
                        <div class="stat-label">Events Registered</div>
                    </div>

                    <div class="stat-card success slide-in">
                        <div class="stat-header">
                            <div class="stat-icon success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $userStats['events_attended']; ?></div>
                        <div class="stat-label">Events Attended</div>
                    </div>

                    <div class="stat-card warning slide-in">
                        <div class="stat-header">
                            <div class="stat-icon warning">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                        </div>
                        <div class="stat-number">K<?php echo number_format($userStats['total_spent']); ?></div>
                        <div class="stat-label">Total Spent</div>
                    </div>

                    <div class="stat-card info slide-in">
                        <div class="stat-header">
                            <div class="stat-icon info">
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $userStats['events_attended'] > 0 ? '4.8' : '0'; ?></div>
                        <div class="stat-label">Average Rating</div>
                    </div>
                </div>
            </div>

            <!-- 📝 Profile Forms -->
            <div class="row">
                <!-- Update Profile Form -->
                <div class="col-lg-8 mb-4">
                    <div class="content-card slide-in">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-user-edit"></i>
                                Update Profile Information
                            </h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="profileForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">First Name *</label>
                                            <input type="text" name="first_name" class="form-control"
                                                value="<?php echo htmlspecialchars($currentUser['first_name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Last Name *</label>
                                            <input type="text" name="last_name" class="form-control"
                                                value="<?php echo htmlspecialchars($currentUser['last_name']); ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Email Address *</label>
                                    <input type="email" name="email" class="form-control"
                                        value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control"
                                        value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>"
                                        placeholder="+265 123 456 789">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Bio</label>
                                    <textarea name="bio" class="form-control" rows="4"
                                        placeholder="Tell us about yourself..."><?php echo htmlspecialchars($currentUser['bio'] ?? ''); ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Account Type</label>
                                    <input type="text" class="form-control"
                                        value="<?php echo ucfirst($currentUser['role']); ?>" disabled>
                                    <small class="text-muted">Contact admin to change account type</small>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Profile
                                    </button>
                                    <button type="reset" class="btn btn-outline">
                                        <i class="fas fa-undo"></i> Reset Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Change Password & Account Settings -->
                <div class="col-lg-4">
                    <!-- Change Password -->
                    <div class="content-card slide-in mb-4">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-lock"></i>
                                Change Password
                            </h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="passwordForm">
                                <div class="form-group">
                                    <label class="form-label">Current Password *</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">New Password *</label>
                                    <input type="password" name="new_password" class="form-control"
                                        id="newPassword" required>
                                    <div class="password-strength">
                                        <div class="password-strength-bar" id="strengthBar"></div>
                                    </div>
                                    <div class="password-requirements" id="passwordRequirements">
                                        <div class="requirement" id="lengthReq">
                                            <i class="fas fa-circle"></i>
                                            <span>At least 6 characters</span>
                                        </div>
                                        <div class="requirement" id="upperReq">
                                            <i class="fas fa-circle"></i>
                                            <span>One uppercase letter</span>
                                        </div>
                                        <div class="requirement" id="lowerReq">
                                            <i class="fas fa-circle"></i>
                                            <span>One lowercase letter</span>
                                        </div>
                                        <div class="requirement" id="numberReq">
                                            <i class="fas fa-circle"></i>
                                            <span>One number</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Confirm New Password *</label>
                                    <input type="password" name="confirm_password" class="form-control"
                                        id="confirmPassword" required>
                                    <div class="password-match" id="passwordMatch"></div>
                                </div>

                                <button type="submit" name="change_password" class="btn btn-danger w-100">
                                    <i class="fas fa-key"></i> Change Password
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Account Actions -->
                    <div class="content-card slide-in">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-cog"></i>
                                Account Actions
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="my-events.php" class="btn btn-primary">
                                    <i class="fas fa-calendar-alt"></i> View My Events
                                </a>

                                <a href="notifications.php" class="btn btn-success">
                                    <i class="fas fa-bell"></i> Manage Notifications
                                </a>

                                <a href="../tickets/history.php" class="btn btn-info">
                                    <i class="fas fa-history"></i> Ticket History
                                </a>

                                <button onclick="exportData()" class="btn btn-outline">
                                    <i class="fas fa-download"></i> Export My Data
                                </button>

                                <hr>

                                <button onclick="deactivateAccount()" class="btn btn-danger">
                                    <i class="fas fa-user-times"></i> Deactivate Account
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🎯 Recent Activity -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-history"></i>
                                Recent Account Activity
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="activity-timeline">
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-user-edit"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h6>Profile Updated</h6>
                                        <p>You updated your profile information</p>
                                        <small class="text-muted"><?php echo timeAgo($currentUser['updated_at'] ?? $currentUser['created_at']); ?></small>
                                    </div>
                                </div>

                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-sign-in-alt"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h6>Account Created</h6>
                                        <p>Welcome to EMS! Your account was created</p>
                                        <small class="text-muted"><?php echo timeAgo($currentUser['created_at']); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 🎨 Modals -->
    <!-- Deactivate Account Modal -->
    <div class="modal fade" id="deactivateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius: 15px; border: none;">
                <div class="modal-header" style="background: var(--danger-gradient); color: white; border-radius: 15px 15px 0 0;">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle"></i> Deactivate Account
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 2rem;">
                    <div class="alert alert-danger">
                        <i class="fas fa-warning"></i>
                        <strong>Warning!</strong> This action cannot be undone.
                    </div>
                    <p>Are you sure you want to deactivate your account? This will:</p>
                    <ul>
                        <li>Cancel all your upcoming event registrations</li>
                        <li>Remove access to your account</li>
                        <li>Delete your personal data (after 30 days)</li>
                        <li>Prevent you from accessing EMS services</li>
                    </ul>
                    <p><strong>If you're sure, please type "DEACTIVATE" below:</strong></p>
                    <input type="text" id="deactivateConfirm" class="form-control" placeholder="Type DEACTIVATE">
                </div>
                <div class="modal-footer" style="border: none; padding: 1rem 2rem 2rem;">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDeactivate" disabled>
                        <i class="fas fa-user-times"></i> Deactivate Account
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Data Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius: 15px; border: none;">
                <div class="modal-header" style="background: var(--info-gradient); color: white; border-radius: 15px 15px 0 0;">
                    <h5 class="modal-title">
                        <i class="fas fa-download"></i> Export Your Data
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 2rem;">
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
                            <i class="fas fa-ticket-alt"></i> Ticket History
                        </label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="exportPayments" checked>
                        <label class="form-check-label" for="exportPayments">
                            <i class="fas fa-credit-card"></i> Payment History
                        </label>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Your data will be exported as a ZIP file containing JSON and PDF files.
                    </div>
                </div>
                <div class="modal-footer" style="border: none; padding: 1rem 2rem 2rem;">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="confirmExport">
                        <i class="fas fa-download"></i> Export Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 📱 Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 🎨 Profile Page Controller
        class ProfileController {
            constructor() {
                this.init();
            }

            init() {
                this.bindEvents();
                this.initPasswordStrength();
                this.initFormValidation();
                this.initAnimations();
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

                // Deactivate account confirmation
                const deactivateConfirm = document.getElementById('deactivateConfirm');
                const confirmDeactivate = document.getElementById('confirmDeactivate');

                if (deactivateConfirm) {
                    deactivateConfirm.addEventListener('input', (e) => {
                        confirmDeactivate.disabled = e.target.value !== 'DEACTIVATE';
                    });
                }

                if (confirmDeactivate) {
                    confirmDeactivate.addEventListener('click', () => {
                        this.processDeactivation();
                    });
                }

                // Export data
                const confirmExport = document.getElementById('confirmExport');
                if (confirmExport) {
                    confirmExport.addEventListener('click', () => {
                        this.processDataExport();
                    });
                }

                // Form submissions with loading states
                const forms = document.querySelectorAll('form');
                forms.forEach(form => {
                    form.addEventListener('submit', (e) => {
                        const submitBtn = form.querySelector('button[type="submit"]');
                        if (submitBtn) {
                            const originalText = submitBtn.innerHTML;
                            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                            submitBtn.disabled = true;

                            // Re-enable after 3 seconds if form doesn't submit
                            setTimeout(() => {
                                if (submitBtn.disabled) {
                                    submitBtn.innerHTML = originalText;
                                    submitBtn.disabled = false;
                                }
                            }, 3000);
                        }
                    });
                });
            }

            initPasswordStrength() {
                const newPasswordInput = document.getElementById('newPassword');
                const confirmPasswordInput = document.getElementById('confirmPassword');
                const strengthBar = document.getElementById('strengthBar');
                const passwordMatch = document.getElementById('passwordMatch');

                if (newPasswordInput) {
                    newPasswordInput.addEventListener('input', (e) => {
                        this.checkPasswordStrength(e.target.value);
                    });
                }

                if (confirmPasswordInput) {
                    confirmPasswordInput.addEventListener('input', (e) => {
                        this.checkPasswordMatch(newPasswordInput.value, e.target.value);
                    });
                }
            }

            checkPasswordStrength(password) {
                const requirements = {
                    length: password.length >= 6,
                    upper: /[A-Z]/.test(password),
                    lower: /[a-z]/.test(password),
                    number: /\d/.test(password)
                };

                // Update requirement indicators
                Object.keys(requirements).forEach(req => {
                    const element = document.getElementById(req + 'Req');
                    if (element) {
                        if (requirements[req]) {
                            element.classList.add('met');
                            element.querySelector('i').className = 'fas fa-check-circle';
                        } else {
                            element.classList.remove('met');
                            element.querySelector('i').className = 'fas fa-circle';
                        }
                    }
                });

                // Update strength bar
                const metCount = Object.values(requirements).filter(Boolean).length;
                const strengthBar = document.getElementById('strengthBar');

                if (strengthBar) {
                    strengthBar.className = 'password-strength-bar';

                    if (metCount === 1) {
                        strengthBar.classList.add('strength-weak');
                    } else if (metCount === 2) {
                        strengthBar.classList.add('strength-fair');
                    } else if (metCount === 3) {
                        strengthBar.classList.add('strength-good');
                    } else if (metCount === 4) {
                        strengthBar.classList.add('strength-strong');
                    }
                }
            }

            checkPasswordMatch(password, confirmPassword) {
                const passwordMatch = document.getElementById('passwordMatch');

                if (passwordMatch) {
                    if (confirmPassword === '') {
                        passwordMatch.innerHTML = '';
                    } else if (password === confirmPassword) {
                        passwordMatch.innerHTML = '<small class="text-success"><i class="fas fa-check"></i> Passwords match</small>';
                    } else {
                        passwordMatch.innerHTML = '<small class="text-danger"><i class="fas fa-times"></i> Passwords do not match</small>';
                    }
                }
            }

            initFormValidation() {
                // Real-time email validation
                const emailInput = document.querySelector('input[name="email"]');
                if (emailInput) {
                    emailInput.addEventListener('blur', (e) => {
                        this.validateEmail(e.target);
                    });
                }

                // Phone number formatting
                const phoneInput = document.querySelector('input[name="phone"]');
                if (phoneInput) {
                    phoneInput.addEventListener('input', (e) => {
                        this.formatPhoneNumber(e.target);
                    });
                }
            }

            validateEmail(input) {
                const email = input.value.trim();
                const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

                if (email && !isValid) {
                    input.style.borderColor = '#f44336';
                    this.showFieldError(input, 'Please enter a valid email address');
                } else {
                    input.style.borderColor = '#e9ecef';
                    this.hideFieldError(input);
                }
            }

            formatPhoneNumber(input) {
                let value = input.value.replace(/\D/g, '');

                if (value.startsWith('265')) {
                    value = value.substring(3);
                }

                if (value.length > 0) {
                    if (value.length <= 3) {
                        value = `+265 ${value}`;
                    } else if (value.length <= 6) {
                        value = `+265 ${value.substring(0, 3)} ${value.substring(3)}`;
                    } else {
                        value = `+265 ${value.substring(0, 3)} ${value.substring(3, 6)} ${value.substring(6, 9)}`;
                    }
                }

                input.value = value;
            }

            showFieldError(input, message) {
                this.hideFieldError(input);

                const errorDiv = document.createElement('div');
                errorDiv.className = 'field-error text-danger';
                errorDiv.innerHTML = `<small><i class="fas fa-exclamation-circle"></i> ${message}</small>`;

                input.parentNode.appendChild(errorDiv);
            }

            hideFieldError(input) {
                const existingError = input.parentNode.querySelector('.field-error');
                if (existingError) {
                    existingError.remove();
                }
            }

            initAnimations() {
                // Animate elements on scroll
                const observerOptions = {
                    threshold: 0.1,
                    rootMargin: '0px 0px -50px 0px'
                };

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.style.animationDelay = Math.random() * 0.3 + 's';
                            entry.target.classList.add('fade-in');
                        }
                    });
                }, observerOptions);

                // Observe all animatable elements
                const animatableElements = document.querySelectorAll('.slide-in, .content-card, .stat-card');
                animatableElements.forEach(el => observer.observe(el));
            }

            async processDeactivation() {
                const button = document.getElementById('confirmDeactivate');
                const originalText = button.innerHTML;

                try {
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deactivating...';
                    button.disabled = true;

                    const response = await fetch('../api/deactivate_account.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            confirm: 'DEACTIVATE'
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.showNotification('Account deactivated successfully. Redirecting...', 'success');
                        setTimeout(() => {
                            window.location.href = '../auth/logout.php';
                        }, 2000);
                    } else {
                        this.showNotification(data.message || 'Failed to deactivate account', 'error');
                    }
                } catch (error) {
                    console.error('Deactivation error:', error);
                    this.showNotification('Network error occurred', 'error');
                } finally {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            }

            async processDataExport() {
                const button = document.getElementById('confirmExport');
                const originalText = button.innerHTML;

                try {
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing Export...';
                    button.disabled = true;

                    const exportOptions = {
                        profile: document.getElementById('exportProfile').checked,
                        events: document.getElementById('exportEvents').checked,
                        tickets: document.getElementById('exportTickets').checked,
                        payments: document.getElementById('exportPayments').checked
                    };

                    const response = await fetch('../api/export_data.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(exportOptions)
                    });

                    if (response.ok) {
                        const blob = await response.blob();
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `ems_data_export_${new Date().toISOString().split('T')[0]}.zip`;
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);

                        this.showNotification('Data exported successfully!', 'success');

                        // Close modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('exportModal'));
                        modal.hide();
                    } else {
                        const data = await response.json();
                        this.showNotification(data.message || 'Failed to export data', 'error');
                    }
                } catch (error) {
                    console.error('Export error:', error);
                    this.showNotification('Network error occurred', 'error');
                } finally {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            }

            showNotification(message, type = 'info') {
                // Create notification element
                const notification = document.createElement('div');
                notification.className = `notification-toast toast-${type}`;
                notification.innerHTML = `
                    <div class="toast-content">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                        <span>${message}</span>
                    </div>
                    <button class="toast-close" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                `;

                // Add to page
                document.body.appendChild(notification);

                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.style.animation = 'slideOutRight 0.3s ease-out';
                        setTimeout(() => notification.remove(), 300);
                    }
                }, 5000);
            }
        }

        // Global functions for button clicks
        window.deactivateAccount = function() {
            const modal = new bootstrap.Modal(document.getElementById('deactivateModal'));
            modal.show();
        };

        window.exportData = function() {
            const modal = new bootstrap.Modal(document.getElementById('exportModal'));
            modal.show();
        };

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', () => {
            new ProfileController();
        });

        // Additional CSS for notifications and enhancements
        const additionalStyles = `
            <style>
                .notification-toast {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: white;
                    border-radius: 10px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                    padding: 1rem 1.5rem;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    min-width: 300px;
                    z-index: 9999;
                    animation: slideInRight 0.3s ease-out;
                    border-left: 5px solid;
                }
                
                .toast-success { border-left-color: #4CAF50; }
                .toast-error { border-left-color: #f44336; }
                .toast-info { border-left-color: #2196F3; }
                
                .toast-content {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                
                .toast-success .toast-content i { color: #4CAF50; }
                .toast-error .toast-content i { color: #f44336; }
                .toast-info .toast-content i { color: #2196F3; }
                
                .toast-close {
                    background: none;
                    border: none;
                    color: #999;
                    cursor: pointer;
                    padding: 0.2rem;
                    border-radius: 3px;
                }
                
                .toast-close:hover { 
                    color: #666; 
                    background: #f0f0f0;
                }
                
                .activity-timeline {
                    position: relative;
                }
                
                .activity-timeline::before {
                    content: '';
                    position: absolute;
                    left: 20px;
                    top: 0;
                    bottom: 0;
                    width: 2px;
                    background: #e9ecef;
                }
                
                .activity-item {
                    position: relative;
                    padding-left: 60px;
                    margin-bottom: 2rem;
                }
                
                .activity-item:last-child {
                    margin-bottom: 0;
                }
                
                .activity-item .activity-icon {
                    position: absolute;
                    left: 0;
                    top: 0;
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    background: var(--primary-gradient);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 0.9rem;
                    z-index: 1;
                }
                
                .activity-content h6 {
                    margin: 0 0 0.5rem 0;
                    font-weight: 600;
                    color: var(--sidebar-bg);
                }
                
                .activity-content p {
                    margin: 0 0 0.5rem 0;
                    color: #6c757d;
                    font-size: 0.9rem;
                }
                
                .field-error {
                    margin-top: 0.25rem;
                }
                
                .form-check {
                    padding: 0.5rem;
                    border-radius: 8px;
                    transition: background 0.2s ease;
                }
                
                .form-check:hover {
                    background: #f8f9fa;
                }
                
                .form-check-label {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    cursor: pointer;
                    font-weight: 500;
                }
                
                .form-check-label i {
                    color: #667eea;
                    width: 18px;
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
                
                @keyframes slideOutRight {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                }
                
                /* Enhanced mobile responsiveness */
                @media (max-width: 576px) {
                    .profile-header {
                        padding: 1rem;
                    }
                    
                    .profile-avatar {
                        width: 80px;
                        height: 80px;
                        font-size: 2rem;
                    }
                    
                    .profile-info h2 {
                        font-size: 1.5rem;
                    }
                    
                    .stats-grid {
                        grid-template-columns: 1fr 1fr;
                        gap: 1rem;
                    }
                    
                    .stat-card {
                        padding: 1rem;
                    }
                    
                    .stat-number {
                        font-size: 1.8rem;
                    }
                    
                    .card-body {
                        padding: 1rem;
                    }
                    
                    .notification-toast {
                        width: calc(100% - 40px);
                        right: 20px;
                        left: 20px;
                        min-width: auto;
                    }
                    
                    .modal-dialog {
                        margin: 1rem;
                    }
                }
                
                /* Loading states */
                .btn:disabled {
                    opacity: 0.7;
                    cursor: not-allowed;
                }
                
                .btn:disabled:hover {
                    transform: none !important;
                    box-shadow: none !important;
                }
                
                /* Form enhancements */
                .form-control:focus {
                    border-color: #667eea;
                    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                }
                
                .form-control.is-valid {
                    border-color: #4CAF50;
                }
                
                .form-control.is-invalid {
                    border-color: #f44336;
                }
                
                /* Profile avatar hover effect */
                .profile-avatar:hover {
                    transform: scale(1.05);
                    transition: transform 0.3s ease;
                }
                
                /* Smooth transitions for all interactive elements */
                .btn, .form-control, .nav-link, .stat-card, .content-card {
                    transition: all 0.3s ease;
                }
                
                /* Custom scrollbar for sidebar */
                .sidebar::-webkit-scrollbar {
                    width: 6px;
                }
                
                .sidebar::-webkit-scrollbar-track {
                    background: rgba(255, 255, 255, 0.1);
                }
                
                .sidebar::-webkit-scrollbar-thumb {
                    background: rgba(255, 255, 255, 0.3);
                    border-radius: 3px;
                }
                
                .sidebar::-webkit-scrollbar-thumb:hover {
                    background: rgba(255, 255, 255, 0.5);
                }
            </style>
        `;

        document.head.insertAdjacentHTML('beforeend', additionalStyles);
    </script>
</body>

</html>