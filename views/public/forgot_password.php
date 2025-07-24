<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/models/User.php';
require_once dirname(__DIR__, 2) . '/includes/mailer.php';
// ...existing code...

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = "Please enter your student email.";
    } else {
        $user = new User($conn);
        $student = $user->getByEmail($email);

        if ($student) {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour from now

            // ✅ Dynamically build reset link (no hardcoded project folder)
            $host = $_SERVER['HTTP_HOST']; // localhost
            $path = dirname($_SERVER['PHP_SELF']); // /EMS/views/public
            $resetLink = "http://$host$path/reset_password.php?token=$token";

            // Save token to DB
            $user->storeResetToken($email, $token, $expiry);

            // Build email
            $subject = "EMS Password Reset";
            $body = "<p>Hello {$student['first_name']},</p>
                     <p>Click the link below to reset your password:</p>
                     <p><a href='$resetLink'>Reset Password</a></p>
                     <p>This link expires in 1 hour.</p>
                     <p>If you didn’t request this, please ignore this email.</p>";

            sendEmail($email, $subject, $body);
            $success = "A password reset link has been sent to your email.";
        } else {
            $error = "That email is not registered.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password - EMS</title>
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
    }
    h2 {
      text-align: center;
      color: #333;
    }
    .icon {
      font-size: 2.5rem;
      color: #667eea;
      margin-bottom: 15px;
      text-align: center;
    }
    .alert {
      margin-bottom: 15px;
      padding: 10px;
      border-radius: 6px;
      font-size: 0.95rem;
    }
    .alert-error { background: #ffe5e5; color: #c0392b; }
    .alert-success { background: #e6ffea; color: #27ae60; }
    input[type="email"] {
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
  </style>
</head>
<body>
  <div class="form-box">
    <div class="icon"><i class="fas fa-lock"></i></div>
    <h2>Forgot Password</h2>

    <?php if ($error): ?>
      <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST">
      <label for="email">Student Email</label>
      <input type="email" id="email" name="email" placeholder="you@unilia.ac.mw" required>
      <button type="submit">Send Reset Link</button>
    </form>
  </div>
</body>
</html>