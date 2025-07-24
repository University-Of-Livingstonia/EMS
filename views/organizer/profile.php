<?php

/**
 * 👤 Organizer Profile - EMS
 * Ekwendeni Mighty Campus Event Management System
 * Manage Your Profile! 📝
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
if (!in_array($currentUser['role'], ['organizer', 'admin'])) {
    header('Location: ../../dashboard/index.php');
    exit;
}

$message = '';
$messageType = '';

// Handle profile update
if ($_POST && isset($_POST['update_profile'])) {
    try {
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phoneNumber = trim($_POST['phone_number']);
        $department = trim($_POST['department']);
        $bio = trim($_POST['bio']);

        // Validate inputs
        if (empty($firstName) || empty($lastName) || empty($email)) {
            throw new Exception('First name, last name, and email are required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }

        // Check if email is already taken by another user
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->bind_param("si", $email, $currentUser['user_id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception('This email is already registered to another account.');
        }

        // Update user profile
        $stmt = $conn->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, email = ?, phone_number = ?, department = ?, bio = ?, updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->bind_param("ssssssi", $firstName, $lastName, $email, $phoneNumber, $department, $bio, $currentUser['user_id']);
        $stmt->execute();

        // Update session data
        $currentUser['first_name'] = $firstName;
        $currentUser['last_name'] = $lastName;
        $currentUser['email'] = $email;
        $currentUser['phone_number'] = $phoneNumber;
        $currentUser['department'] = $department;
        $currentUser['bio'] = $bio;

        $message = 'Profile updated successfully!';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Error updating profile: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle password change
if ($_POST && isset($_POST['change_password'])) {
    try {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        // Validate inputs
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            throw new Exception('All password fields are required.');
        }

        if ($newPassword !== $confirmPassword) {
            throw new Exception('New passwords do not match.');
        }

        if (strlen($newPassword) < 6) {
            throw new Exception('New password must be at least 6 characters long.');
        }

        // Verify current password
        if (!password_verify($currentPassword, $currentUser['password'])) {
            throw new Exception('Current password is incorrect.');
        }

        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("si", $hashedPassword, $currentUser['user_id']);
        $stmt->execute();

        $message = 'Password changed successfully!';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Error changing password: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get organizer statistics for profile
$profileStats = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_events,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_events,
            SUM(CASE WHEN start_datetime > NOW() THEN 1 ELSE 0 END) as upcoming_events
        FROM events 
        WHERE organizer_id = ?
    ");
    $stmt->bind_param("i", $currentUser['user_id']);
    $stmt->execute();
    $profileStats = $stmt->get_result()->fetch_assoc();

    // Get total attendees
    $stmt = $conn->prepare("
        SELECT COUNT(t.ticket_id) as total_attendees
        FROM tickets t
        JOIN events e ON t.event_id = e.event_id
        WHERE e.organizer_id = ?
    ");
    $stmt->bind_param("i", $currentUser['user_id']);
    $stmt->execute();
    $attendeeStats = $stmt->get_result()->fetch_assoc();
    $profileStats['total_attendees'] = $attendeeStats['total_attendees'];

    // Get total revenue
    $stmt = $conn->prepare("
        SELECT SUM(t.price) as total_revenue
        FROM tickets t
        JOIN events e ON t.event_id = e.event_id
        WHERE e.organizer_id = ? AND t.payment_status = 'completed'
    ");
    $stmt->bind_param("i", $currentUser['user_id']);
    $stmt->execute();
    $revenueStats = $stmt->get_result()->fetch_assoc();
    $profileStats['total_revenue'] = $revenueStats['total_revenue'] ?? 0;
} catch (Exception $e) {
    $profileStats = [
        'total_events' => 0,
        'approved_events' => 0,
        'upcoming_events' => 0,
        'total_attendees' => 0,
        'total_revenue' => 0
    ];
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Organizer | EMS</title>

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

        /* 👤 Profile Card */
        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .profile-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 800;
            margin: 0 auto 1rem;
            border: 4px solid rgba(255, 255, 255, 0.3);
        }

        .profile-name {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .profile-role {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 1rem;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .stat-item {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: 10px;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.2rem;
        }

        .stat-label {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        /* 📝 Form Sections */
        .form-section {
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .section-header {
            background: var(--primary-gradient);
            color: white;
            padding: 1.5rem;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-body {
            padding: 2rem;
        }

        /* 🎛️ Form Controls */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control,
        .form-select,
        .form-textarea {
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        .form-control:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        /* 🔒 Password Strength */
        .password-strength {
            margin-top: 0.5rem;
            height: 4px;
            background: var(--border-color);
            border-radius: 2px;
            overflow: hidden;
        }

        .strength-bar {
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

        .strength-text {
            font-size: 0.8rem;
            margin-top: 0.3rem;
            font-weight: 500;
        }

        /* 💾 Buttons */
        .btn-primary {
            padding: 0.75rem 2rem;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-danger {
            padding: 0.75rem 2rem;
            background: var(--danger-gradient);
            color: white;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(244, 67, 54, 0.3);
        }

        /* 🚨 Alert */
        .alert {
            padding: 1rem;
            border-radius: 8px;
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

        /* 📱 Responsive */
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }

            .profile-header {
                padding: 1.5rem;
            }

            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }

            .profile-name {
                font-size: 1.5rem;
            }

            .section-body {
                padding: 1rem;
            }

            .profile-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* 🎨 Animations */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
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
    </style>
</head>

<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="page-title">👤 My Profile</h1>
                    <p class="page-subtitle">Manage your account information and settings</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="breadcrumb-nav">
                        <a href="../../dashboard/">Dashboard</a> /
                        <a href="dashboard.php">Organizer</a> /
                        <span>Profile</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> fade-in">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Profile Overview -->
        <div class="profile-card fade-in">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?= strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)) ?>
                </div>
                <h2 class="profile-name"><?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></h2>
                <p class="profile-role">🎪 Event Organizer</p>
                <p class="profile-role"><?= htmlspecialchars($currentUser['department'] ?? 'No Department') ?></p>

                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?= $profileStats['total_events'] ?></div>
                        <div class="stat-label">Total Events</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= $profileStats['approved_events'] ?></div>
                        <div class="stat-label">Approved</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= $profileStats['upcoming_events'] ?></div>
                        <div class="stat-label">Upcoming</div>
                    </div
                        <div class="stat-item">
                    <div class="stat-number"><?= $profileStats['total_attendees'] ?></div>
                    <div class="stat-label">Attendees</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">K<?= number_format($profileStats['total_revenue'] / 1000, 1) ?></div>
                    <div class="stat-label">Revenue</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Profile Information -->
        <div class="col-lg-8">
            <div class="form-section fade-in">
                <div class="section-header">
                    <h5 class="section-title">
                        <i class="fas fa-user-edit"></i>
                        Profile Information
                    </h5>
                </div>
                <div class="section-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" name="first_name" class="form-control"
                                        value="<?= htmlspecialchars($currentUser['first_name']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" name="last_name" class="form-control"
                                        value="<?= htmlspecialchars($currentUser['last_name']) ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Email Address *</label>
                                    <input type="email" name="email" class="form-control"
                                        value="<?= htmlspecialchars($currentUser['email']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone_number" class="form-control"
                                        value="<?= htmlspecialchars($currentUser['phone_number'] ?? '') ?>">
                                </div>
                            </div>

                        </div>

                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <select name="department" class="form-select">
                                <option value="">Select Department</option>
                                <option value="Computer Science" <?= $currentUser['department'] === 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
                                <option value="Business Administration" <?= $currentUser['department'] === 'Business Administration' ? 'selected' : '' ?>>Business Administration</option>
                                <option value="Education" <?= $currentUser['department'] === 'Education' ? 'selected' : '' ?>>Education</option>
                                <option value="Nursing" <?= $currentUser['department'] === 'Nursing' ? 'selected' : '' ?>>Nursing</option>
                                <option value="Theology" <?= $currentUser['department'] === 'Theology' ? 'selected' : '' ?>>Theology</option>
                                <option value="Agriculture" <?= $currentUser['department'] === 'Agriculture' ? 'selected' : '' ?>>Agriculture</option>
                                <option value="Other" <?= $currentUser['department'] === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Bio</label>
                            <textarea name="bio" class="form-textarea" placeholder="Tell us about yourself and your event organizing experience..."><?= htmlspecialchars($currentUser['bio'] ?? '') ?></textarea>
                        </div>

                        <div class="text-end">
                            <button type="submit" name="update_profile" class="btn-primary">
                                <i class="fas fa-save"></i>
                                Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Account Security -->
        <div class="col-lg-4">
            <div class="form-section fade-in">
                <div class="section-header">
                    <h5 class="section-title">
                        <i class="fas fa-lock"></i>
                        Account Security
                    </h5>
                </div>
                <div class="section-body">
                    <form method="POST" id="passwordForm">
                        <div class="form-group">
                            <label class="form-label">Current Password *</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">New Password *</label>
                            <input type="password" name="new_password" class="form-control" id="newPassword" required>
                            <div class="password-strength">
                                <div class="strength-bar" id="strengthBar"></div>
                            </div>
                            <div class="strength-text" id="strengthText"></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Confirm New Password *</label>
                            <input type="password" name="confirm_password" class="form-control" id="confirmPassword" required>
                            <div class="password-match" id="passwordMatch"></div>
                        </div>

                        <div class="text-end">
                            <button type="submit" name="change_password" class="btn-danger">
                                <i class="fas fa-key"></i>
                                Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Account Info -->
            <div class="form-section fade-in">
                <div class="section-header">
                    <h5 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Account Information
                    </h5>
                </div>
                <div class="section-body">
                    <div class="info-item">
                        <strong>Username:</strong><br>
                        <span class="text-muted"><?= htmlspecialchars($currentUser['username']) ?></span>
                    </div>
                    <hr>
                    <div class="info-item">
                        <strong>Account Created:</strong><br>
                        <span class="text-muted"><?= date('M j, Y', strtotime($currentUser['created_at'])) ?></span>
                    </div>
                    <hr>
                    <div class="info-item">
                        <strong>Last Updated:</strong><br>
                        <span class="text-muted"><?= $currentUser['updated_at'] ? date('M j, Y', strtotime($currentUser['updated_at'])) : 'Never' ?></span>
                    </div>
                    <hr>
                    <div class="info-item">
                        <strong>Account Status:</strong><br>
                        <span class="badge bg-success">Active</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Password strength checker
        document.getElementById('newPassword').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');

            let strength = 0;
            let text = '';
            let className = '';

            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;

            switch (strength) {
                case 0:
                case 1:
                    text = 'Very Weak';
                    className = 'strength-weak';
                    break;
                case 2:
                    text = 'Weak';
                    className = 'strength-weak';
                    break;
                case 3:
                    text = 'Fair';
                    className = 'strength-fair';
                    break;
                case 4:
                    text = 'Good';
                    className = 'strength-good';
                    break;
                case 5:
                    text = 'Strong';
                    className = 'strength-strong';
                    break;
            }

            strengthBar.className = 'strength-bar ' + className;
            strengthText.textContent = text;
            strengthText.className = 'strength-text text-' + (strength < 3 ? 'danger' : strength < 4 ? 'warning' : 'success');
        });

        // Password match checker
        document.getElementById('confirmPassword').addEventListener('input', function() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = this.value;
            const matchDiv = document.getElementById('passwordMatch');

            if (confirmPassword.length > 0) {
                if (newPassword === confirmPassword) {
                    matchDiv.innerHTML = '<small class="text-success"><i class="fas fa-check"></i> Passwords match</small>';
                } else {
                    matchDiv.innerHTML = '<small class="text-danger"><i class="fas fa-times"></i> Passwords do not match</small>';
                }
            } else {
                matchDiv.innerHTML = '';
            }
        });

        // Form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                return;
            }

            if (newPassword.length < 6) {
                e.preventDefault();
                alert('New password must be at least 6 characters long!');
                return;
            }
        });

        // Auto-resize textarea
        document.querySelector('textarea[name="bio"]').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });

        console.log('👤 Profile Page Loaded');
    </script>
</body>

</html>