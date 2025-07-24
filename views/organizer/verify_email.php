<?php
require_once __DIR__ . '/../../includes/session.php';
//require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../includes/mailer.php';

$conn = require_once __DIR__ . '/../../config/database.php';
$userModel = new User($conn);

// Initialize session manager and get current user
$sessionManager = new SessionManager($conn);
$currentUser = $sessionManager->getCurrentUser();

// Redirect organizer to dashboard if already verified
if ($currentUser && isset($currentUser['email_verified']) && $currentUser['email_verified'] == 1) {
    header('Location: /EMS/views/organizer/dashboard.php');
    exit;
}

$message = '';
$error = '';

// Handle verification code submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verification_code']) && !isset($_POST['send_verification'])) {
    $verificationCode = trim($_POST['verification_code']);
    $userId = $currentUser['user_id'] ?? null;

    if (!$userId) {
        $error = "User not logged in.";
    } elseif (empty($verificationCode)) {
        $error = "Please enter the verification code.";
    } else {
        // Verify the code for the logged-in user
        $verified = $userModel->verifyUserByCode($userId, $verificationCode);

        if ($verified) {
            $message = "Your email has been successfully verified. You can now access all features.";
            // Redirect to organizer dashboard
            header('Location: /EMS/views/organizer/dashboard.php');
            exit;
        } else {
            $error = "Invalid or expired verification code.";
        }
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_verification'])) {
    // Send verification code email automatically for logged-in user
    $userId = $currentUser['user_id'] ?? null;

    if (!$userId) {
        // Do not show error inline for resend
        // Instead redirect back silently
        header('Location: verify_email.php');
        exit;
    } else {
        $user = $userModel->getById($userId);
        if ($user['email_verified']) {
            // Redirect back silently if already verified
            header('Location: verify_email.php');
            exit;
        } else {
            // Generate a verification code
            $code = bin2hex(random_bytes(3)); // 6 hex chars

            // Save code to user record
            $result = $userModel->setVerificationCode($userId, $code);
            if (!$result) {
                // Redirect back silently on failure
                header('Location: verify_email.php');
                exit;
            }

            // Prepare email with code
            $email = $user['email'];
            $subject = "Email Verification Code - EMS";
            $name = htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name']));
            $body = '
            <!DOCTYPE html>
            <html>
            <head>
              <meta charset="UTF-8">
              <title>Email Verification Code</title>
            </head>
            <body style="font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0;">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td align="center" style="padding: 20px 0; background-color:rgb(253, 253, 253);">
                    <img src="https://unilia.ac.mw/wp-content/uploads/2021/11/cropped-unilia_logo-624x91.png" width="365" height="54" alt="University of Livingstonia" style="display: block;">
                  </td>
                </tr>
                <tr>
                  <td align="center">
                    <table width="600" cellpadding="20" cellspacing="0" style="background-color: #ffffff; border-radius: 6px; margin-top: 20px;">
                      <tr>
                        <td>
                          <h2 style="color: #003366;">Hello, ' . $name . '!</h2>
                          <p style="font-size: 16px; color: #333;">
                            Your email verification code is:
                          </p>
                          <p style="text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 4px; color: #4CAF50;">
                            ' . $code . '
                          </p>
                          <p style="font-size: 14px; color: #666;">
                            Please enter this code in the verification page to verify your email address.
                          </p>
                          <p style="font-size: 16px; color: #003366;"><strong>Best regards,<br>EMS Team</strong></p>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td align="center" style="padding: 20px; font-size: 12px; color: #888;">
                    &copy; ' . date("Y") . ' University of Livingstonia. All rights reserved.
                  </td>
                </tr>
              </table>
            </body>
            </html>
            ';

            if (sendEmail($email, $subject, $body)) {
                // Redirect back silently after sending email
                header('Location: verify_email.php');
                exit;
            } else {
                // Redirect back silently on failure
                header('Location: verify_email.php');
                exit;
            }
        }
    }
} else {
    $message = "";
}

// Get current page for active navigation
$currentPage = basename($_SERVER['PHP_SELF']);

// Get organizer statistics for sidebar badges
$organizerStats = [];
$organizerId = $currentUser['user_id'];
try {
    $stmt = $conn->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM events 
        WHERE organizer_id = ?
        GROUP BY status
    ");
    $stmt->bind_param("i", $organizerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $eventsByStatus = ['approved' => 0, 'pending' => 0, 'draft' => 0];
    while ($row = $result->fetch_assoc()) {
        $eventsByStatus[$row['status']] = $row['count'];
    }
    $organizerStats['events'] = $eventsByStatus;
} catch (Exception $e) {
    $organizerStats['events'] = ['approved' => 0, 'pending' => 0, 'draft' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Verify Email - Organizer - EMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        /* Include necessary styles from dashboard */
        :root {
            --organizer-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --sidebar-bg: #1a1a2e;
            --sidebar-hover: #16213e;
            --content-bg: #f8f9fa;
            --card-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            --card-hover-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --border-color: #e9ecef;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--content-bg);
            overflow-x: hidden;
            margin: 0;
            padding: 0;
        }
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
            background: var(--organizer-primary);
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
            background: var(--organizer-primary);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .nav-badge.pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .nav-badge.new {
            background: var(--organizer-success);
        }
        .organizer-main {
            margin-left: 300px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }
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
        .organizer-title-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .sidebar-toggle {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--organizer-primary);
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
        .organizer-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .organizer-search {
            position: relative;
        }
        .organizer-search input {
            padding: 0.7rem 1rem 0.7rem 2.5rem;
            border: 2px solid var(--border-color);
            border-radius: 25px;
            width: 300px;
            transition: all 0.3s ease;
        }
        .organizer-search input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .organizer-search i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
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
        /* Verify Email Content */
        .verify-container {
            max-width: 400px;
            margin: 3rem auto;
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            text-align: center;
        }
        .verify-container h2 {
            margin-bottom: 1rem;
            color: var(--text-primary);
            font-weight: 700;
        }
        .verify-container form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            align-items: center;
        }
        .verify-container input[type="text"] {
            padding: 0.75rem 1rem;
            font-size: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            width: 100%;
            max-width: 250px;
            text-align: center;
        }
        .verify-container button {
            padding: 0.75rem 1rem;
            font-size: 1rem;
            background: var(--organizer-primary);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.3s ease;
            width: 100%;
            max-width: 250px;
        }
        .verify-container button:hover {
            background: #5a6bc1;
        }
        .alert {
            margin-top: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="organizer-sidebar" id="organizerSidebar">
        <div class="sidebar-header">
            <h3>🎪 EMS</h3>
            <p>Event Organizer</p>
        </div>
        <nav class="organizer-nav">
            <div class="nav-section">
                <div class="nav-section-title">Dashboard</div>
                <div class="organizer-nav-item">
                    <a href="dashboard.php" class="organizer-nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <span class="nav-text">Overview</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="analytics.php" class="organizer-nav-link <?= $currentPage === 'analytics.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-chart-line"></i>
                        <span class="nav-text">Analytics</span>
                    </a>
                </div>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Events</div>
                <div class="organizer-nav-item">
                    <a href="events.php" class="organizer-nav-link <?= $currentPage === 'events.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-calendar-alt"></i>
                        <span class="nav-text">My Events</span>
                        <span class="nav-badge"><?= $organizerStats['events']['approved'] ?? 0 ?></span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="create_event.php" class="organizer-nav-link <?= $currentPage === 'create_event.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-plus-circle"></i>
                        <span class="nav-text">Create Event</span>
                    </a>
                </div>
                <div class="nav-section-title">Account</div>
                <div class="organizer-nav-item">
                    <a href="profile.php" class="organizer-nav-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-user"></i>
                        <span class="nav-text">Profile</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="../../auth/logout.php" class="organizer-nav-link">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <span class="nav-text">Logout</span>
                    </a>
                </div>
            </div>
        </nav>
    </div>
    <div class="organizer-main" id="organizerMain">
        <div class="organizer-topbar">
            <div class="organizer-title-section">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="organizer-title">Verify Your Email</h1>
            </div>
            <div class="organizer-controls">
                <div class="organizer-user-info">
                    <div class="organizer-user-details">
                        <h6><?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></h6>
                        <small>Event Organizer</small>
                    </div>
                    <div class="organizer-avatar">
                        <?= strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="organizer-content">
            <div class="verify-container">
                <?php if ($message && !isset($_POST['send_verification'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <?php if ($error && !isset($_POST['send_verification'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <?php if ($message === ""): ?>
                        <p>Click the button below to receive your email verification code for <?= htmlspecialchars($currentUser['email']) ?>.</p>
                        <button type="submit" name="send_verification" class="btn btn-secondary">Send Verification Code</button>
                    <?php else: ?>
                        <p>Enter the verification code sent to your email and click Verify.</p>
                        <label for="verification_code">Verification Code:</label>
                        <input type="text" id="verification_code" name="verification_code" placeholder="Enter code here" />
                        <div style="display: flex; justify-content: space-between; max-width: 300px; margin-top: 10px;">
                            <button type="submit" class="btn btn-primary">Verify</button>
                            <button type="submit" name="send_verification" class="btn btn-secondary">Resend Code</button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('organizerSidebar');
            const main = document.getElementById('organizerMain');
            sidebar.classList.toggle('collapsed');
            main.classList.toggle('expanded');
        }
    </script>
</body>
</html>
