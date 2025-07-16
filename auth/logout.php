<?php
/**
 * 🚪 Logout - EMS
 * Ekwendeni Mighty Campus Event Management System
 * Safe Exit Portal! 👋
 */

require_once '../includes/functions.php';

// Get database connection
$conn = require_once '../config/database.php';

// Initialize session manager
require_once '../includes/session.php';
$sessionManager = new SessionManager($conn);

// Get current user info before logout (for logging)
$currentUser = $sessionManager->getCurrentUser();
$userRole = $currentUser ? $currentUser['role'] : 'guest';
$userId = $currentUser ? $currentUser['user_id'] : null;

// Log the logout activity
if ($userId) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent, created_at)
            VALUES (?, 'logout', 'User logged out', ?, ?, NOW())
        ");
        if ($stmt === false) {
            error_log("Logout prepare failed: " . $conn->error);
            throw new Exception("Database prepare failed: " . $conn->error);
        }
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $stmt->bind_param("iss", $userId, $ipAddress, $userAgent);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Perform logout
$sessionManager->logout();

// Determine redirect URL based on user role
$redirectUrl = '../index.php';
if (isset($_GET['redirect'])) {
    $redirectUrl = $_GET['redirect'];
} elseif ($userRole === 'admin') {
    $redirectUrl = '../admin/login.php';
} elseif ($userRole === 'organizer') {
    $redirectUrl = '../auth/login.php';
}

// Set logout success message
$_SESSION['logout_message'] = 'You have been successfully logged out. Thank you for using EMS!';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out... | EMS</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            --light-bg: #f8f9fa;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-muted: #6c757d;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logout-container {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            width: 90%;
            animation: fadeInUp 0.6s ease-out;
        }
        
        .logout-icon {
            width: 100px;
            height: 100px;
                        border-radius: 50%;
            background: var(--success-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: white;
            font-size: 3rem;
            animation: pulse 2s infinite;
        }
        
        .logout-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }
        
        .logout-message {
            font-size: 1.1rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .logout-progress {
            width: 100%;
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .progress-bar {
            height: 100%;
            background: var(--primary-gradient);
            border-radius: 3px;
            animation: progress 3s ease-out forwards;
        }
        
        .logout-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-dark);
            border: 2px solid var(--text-muted);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .btn-primary:hover {
            color: white;
        }
        
        .btn-outline:hover {
            border-color: var(--text-dark);
            color: var(--text-dark);
        }
        
        .countdown {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-top: 1rem;
        }
        
        .countdown-number {
            font-weight: 700;
            color: #667eea;
        }
        
        /* Animations */
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
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }
        
        @keyframes progress {
            from {
                width: 0%;
            }
            to {
                width: 100%;
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .logout-container {
                padding: 2rem;
                margin: 1rem;
            }
            
            .logout-title {
                font-size: 1.5rem;
            }
            
            .logout-message {
                font-size: 1rem;
            }
            
            .logout-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        
        <h1 class="logout-title">👋 Goodbye!</h1>
        
        <p class="logout-message">
            You have been successfully logged out from EMS. Thank you for using our Event Management System!
        </p>
        
        <div class="logout-progress">
            <div class="progress-bar"></div>
        </div>
        
        <div class="logout-actions">
            <a href="<?= htmlspecialchars($redirectUrl) ?>" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i>
                Login Again
            </a>
            <a href="../index.php" class="btn btn-outline">
                <i class="fas fa-home"></i>
                Go Home
            </a>
        </div>
        
        <div class="countdown">
            Redirecting in <span class="countdown-number" id="countdown">5</span> seconds...
        </div>
    </div>

    <script>
        // Countdown timer
        let timeLeft = 5;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(() => {
            timeLeft--;
            countdownElement.textContent = timeLeft;
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                window.location.href = '<?= htmlspecialchars($redirectUrl) ?>';
            }
        }, 1000);
        
        // Allow user to cancel redirect by clicking anywhere
        document.addEventListener('click', () => {
            clearInterval(timer);
            document.querySelector('.countdown').style.display = 'none';
        });
        
        // Prevent back button after logout
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };
        
        console.log('🚪 Logout Page Loaded - User safely logged out');
    </script>
</body>
</html>
