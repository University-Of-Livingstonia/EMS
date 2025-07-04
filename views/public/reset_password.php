<?php
/**
 * Ekwendeni Mighty Campus EMS - Reset Password UI (Styled)
 * File: /views/public/reset_password.php
 */
require_once '../../config/database.php';
require_once '../../models/User.php';

// ✅ Set base folder (edit this only if folder name changes)
$basePath = '/ATTACH-PROJECT/EMS';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = "Invalid or missing token.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($newPassword) || empty($confirmPassword)) {
        $error = "Both password fields are required.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        $user = new User($conn);
        $updated = $user->updatePasswordByToken($token, $newPassword);
        if ($updated) {
            $success = true;
        } else {
            $error = "Invalid or expired reset token.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password - EMS</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;600&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(to right, #667eea, #764ba2);
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }
    .form-box {
      background: #fff;
      padding: 40px;
      border-radius: 10px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.2);
      width: 100%;
      max-width: 400px;
      text-align: center;
    }
    h2 {
      color: #333;
    }
    .icon {
      font-size: 2.5rem;
      color: #667eea;
      margin-bottom: 15px;
    }
    .alert {
      margin-bottom: 20px;
      padding: 15px;
      border-radius: 6px;
      font-size: 0.95rem;
    }
    .alert-error {
      background: #ffe5e5;
      color: #c0392b;
    }
    .alert-success {
      background: #e6ffea;
      color: #27ae60;
    }
    input[type="password"] {
      width: 100%;
      padding: 12px;
      margin-top: 10px;
      margin-bottom: 20px;
      border: 2px solid #ddd;
      border-radius: 8px;
      font-size: 1rem;
    }
    button {
      width: 100%;
      padding: 12px;
      border: none;
      background: #667eea;
      color: white;
      font-size: 1rem;
      border-radius: 8px;
      cursor: pointer;
    }
    button:hover {
      background: #5a67d8;
    }
    .login-button {
      margin-top: 20px;
      display: inline-block;
      background: #27ae60;
      padding: 12px 24px;
      color: white;
      text-decoration: none;
      font-weight: 500;
      border-radius: 8px;
    }
    .login-button:hover {
      background: #219150;
    }
  </style>
</head>
<body>
  <div class="form-box">
    <div class="icon"><i class="fas fa-unlock-alt"></i></div>
    <h2>Reset Password</h2>

    <?php if ($error): ?>
      <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success">
        Your password has been successfully reset. <br> You may now log in.
      </div>
      <a href="<?php echo $basePath; ?>/auth/login.php" class="login-button">
        <i class="fas fa-sign-in-alt"></i> Log In Now
      </a>
    <?php else: ?>
      <form method="POST">
        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" required>

        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required>

        <button type="submit"><i class="fas fa-key"></i> Reset Password</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>