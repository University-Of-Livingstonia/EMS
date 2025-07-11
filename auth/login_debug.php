<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Include database connection
require_once '../config/database.php';

$error = '';
$success = '';
$debug_info = [];

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $debug_info[] = "Form submitted with email: $email";

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        try {
            // Find user by email or username
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
            $stmt->bind_param("ss", $email, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user) {
                $debug_info[] = "User found: ID={$user['user_id']}, Role={$user['role']}, Email={$user['email']}";

                if (password_verify($password, $user['password'])) {
                    $debug_info[] = "Password verified successfully";

                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['login_time'] = time();

                    $debug_info[] = "Session variables set. Role in session: " . $_SESSION['role'];

                    // Update last login
                    $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                    $updateStmt->bind_param("i", $user['user_id']);
                    $updateStmt->execute();

                    // Determine redirect based on role
                    $redirect_url = '';
                    if ($user['role'] === 'admin') {
                        $redirect_url = '../admin/dashboard.php';
                        $debug_info[] = "Should redirect to: $redirect_url (ADMIN)";
                    } elseif ($user['role'] === 'organizer') {
                        $redirect_url = '../views/organizer/dashboard.php';
                        $debug_info[] = "Should redirect to: $redirect_url (ORGANIZER)";
                    } else {
                        $redirect_url = '../dashboard/index.php';
                        $debug_info[] = "Should redirect to: $redirect_url (USER)";
                    }

                    // Show debug info instead of redirecting
                    $success = "Login successful! Debug mode - showing redirect info instead of redirecting.";
                } else {
                    $error = 'Invalid password';
                    $debug_info[] = "Password verification failed";
                }
            } else {
                $error = 'User not found';
                $debug_info[] = "No user found with email/username: $email";
            }
        } catch (Exception $e) {
            $error = 'Login error: ' . $e->getMessage();
            $debug_info[] = "Exception: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Debug - EMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .debug-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }

        .debug-item {
            padding: 5px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .debug-item:last-child {
            border-bottom: none;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3>🔍 Login Debug Mode</h3>
                    </div>
                    <div class="card-body">

                        <!-- Debug Information -->
                        <?php if (!empty($debug_info)): ?>
                            <div class="debug-info">
                                <h5>🔧 Debug Information:</h5>
                                <?php foreach ($debug_info as $info): ?>
                                    <div class="debug-item"><?php echo htmlspecialchars($info); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Messages -->
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <?php echo htmlspecialchars($success); ?>
                            </div>

                            <!-- Show redirect buttons -->
                            <?php if (isset($_SESSION['role'])): ?>
                                <div class="mt-3">
                                    <h5>Choose your destination:</h5>
                                    <?php if ($_SESSION['role'] === 'admin'): ?>
                                        <a href="../admin/dashboard.php" class="btn btn-danger me-2">
                                            <i class="fas fa-tachometer-alt"></i> Go to Admin Dashboard
                                        </a>
                                    <?php endif; ?>

                                    <a href="../dashboard/index.php" class="btn btn-primary me-2">
                                        <i class="fas fa-home"></i> Go to User Dashboard
                                    </a>

                                    <a href="logout.php" class="btn btn-secondary">
                                        <i class="fas fa-sign-out-alt"></i> Logout
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- Login Form -->
                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email or Username</label>
                                <input type="text" class="form-control" id="email" name="email"
                                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>

                            <button type="submit" name="login" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt"></i> Debug Login
                            </button>
                        </form>

                        <!-- Quick Test Buttons -->
                        <div class="mt-4">
                            <h5>🚀 Quick Test:</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <form method="POST">
                                        <input type="hidden" name="email" value="admin@unilia.ac.mw">
                                        <input type="hidden" name="password" value="admin123">
                                        <input type="hidden" name="login" value="1">
                                        <button type="submit" class="btn btn-danger w-100">Test Admin</button>
                                    </form>
                                </div>
                                <div class="col-md-4">
                                    <form method="POST">
                                        <input type="hidden" name="email" value="john@student.ems.com">
                                        <input type="hidden" name="password" value="password123">
                                        <input type="hidden" name="login" value="1">
                                        <button type="submit" class="btn btn-success w-100">Test User</button>
                                    </form>
                                </div>
                                <div class="col-md-4">
                                    <form method="POST">
                                        <input type="hidden" name="email" value="mike@student.ems.com">
                                        <input type="hidden" name="password" value="password123">
                                        <input type="hidden" name="login" value="1">
                                        <button type="submit" class="btn btn-warning w-100">Test Organizer</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>