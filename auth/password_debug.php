<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once '../config/database.php';

$error = '';
$success = '';
$user_info = null;
$show_set_form = false;

// Handle user lookup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lookup'])) {
    $email = trim($_POST['email'] ?? '');
    if (empty($email)) {
        $error = 'Please enter an email or username.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_info = $result->fetch_assoc();
        if (!$user_info) {
            $error = 'User not found.';
        } else {
            $show_set_form = true;
        }
    }
}

// Handle password set
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_password'])) {
    $user_id = intval($_POST['user_id'] ?? 0);
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    if (empty($user_id) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed, $user_id);
        if ($stmt->execute()) {
            $success = 'Password updated successfully!';
            // Fetch user info again
            $stmt2 = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt2->bind_param("i", $user_id);
            $stmt2->execute();
            $user_info = $stmt2->get_result()->fetch_assoc();
            $show_set_form = true;
        } else {
            $error = 'Failed to update password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Debug - EMS</title>
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
        .warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3>🔑 Password Debug Tool <span style="font-size:1rem; color:#c00;">(Sensitive!)</span></h3>
                </div>
                <div class="card-body">
                    <div class="warning">
                        <strong>Warning:</strong> This tool exposes sensitive password info and allows password changes for any user. Use only for debugging and remove in production!
                    </div>
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
                    <?php endif; ?>
                    <form method="POST" class="mb-4">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email or Username</label>
                            <input type="text" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                        <button type="submit" name="lookup" class="btn btn-primary">
                            <i class="fas fa-search"></i> Lookup User
                        </button>
                    </form>
                    <?php if ($user_info): ?>
                        <div class="debug-info">
                            <h5>User Info:</h5>
                            <div class="debug-item"><strong>ID:</strong> <?php echo htmlspecialchars($user_info['user_id']); ?></div>
                            <div class="debug-item"><strong>Username:</strong> <?php echo htmlspecialchars($user_info['username']); ?></div>
                            <div class="debug-item"><strong>Email:</strong> <?php echo htmlspecialchars($user_info['email']); ?></div>
                            <div class="debug-item"><strong>Role:</strong> <?php echo htmlspecialchars($user_info['role']); ?></div>
                            <div class="debug-item"><strong>Hashed Password:</strong> <code><?php echo htmlspecialchars($user_info['password']); ?></code></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($show_set_form && $user_info): ?>
                        <form method="POST" class="mt-4">
                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_info['user_id']); ?>">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <button type="submit" name="set_password" class="btn btn-warning">
                                <i class="fas fa-key"></i> Set New Password
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- FontAwesome for icons -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 