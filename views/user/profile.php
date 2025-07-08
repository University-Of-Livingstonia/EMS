<?php
/**
 * EMS - Student Profile Page
 * File: /views/user/profile.php
 */
require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../models/User.php';

// Initialize SessionManager
$sessionManager = initializeSessionManager($conn);

// Ensure the user is logged in using SessionManager
$sessionManager->requireLogin('../../auth/login.php?error=access_denied');

$userModel = new User($conn);
$userData = $userModel->getById($sessionManager->getCurrentUser()['user_id']);

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $data = [
        'user_id' => $sessionManager->getCurrentUser()['user_id'],
        'first_name' => trim($_POST['first_name']),
        'last_name' => trim($_POST['last_name']),
        'phone_number' => trim($_POST['phone_number']),
        'department' => $_POST['department']
    ];

    if ($userModel->updateProfile($data)) {
        $success = "Profile updated successfully.";
        $userData = $userModel->getById($sessionManager->getCurrentUser()['user_id']); // Refresh
    } else {
        $error = "Failed to update profile.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Your Profile - EMS</title>
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
    .profile-box {
      background: #fff;
      padding: 40px;
      border-radius: 10px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.2);
      width: 100%;
      max-width: 500px;
    }
    h2 {
      text-align: center;
      color: #333;
    }
    .form-group {
      margin-bottom: 20px;
    }
    label {
      display: block;
      font-weight: 500;
      margin-bottom: 8px;
    }
    input, select {
      width: 100%;
      padding: 12px;
      font-size: 1rem;
      border-radius: 8px;
      border: 2px solid #ddd;
    }
    .alert {
      margin-bottom: 15px;
      padding: 10px;
      border-radius: 6px;
      font-size: 0.95rem;
    }
    .alert-success { background: #e6ffea; color: #27ae60; }
    .alert-error { background: #ffe5e5; color: #c0392b; }
    button {
      width: 100%;
      padding: 12px;
      background: #667eea;
      color: white;
      font-size: 1rem;
      border: none;
      border-radius: 8px;
      cursor: pointer;
    }
    button:hover {
      background: #5a67d8;
    }
 </style>
</head>
<body>
  <div class="profile-box">
    <h2>Your Profile</h2>

    <?php if ($success): ?>
      <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label for="first_name">First Name</label>
        <input type="text" name="first_name" value="<?php echo htmlspecialchars($userData['first_name']); ?>" required>
      </div>

      <div class="form-group">
        <label for="last_name">Last Name</label>
        <input type="text" name="last_name" value="<?php echo htmlspecialchars($userData['last_name']); ?>" required>
      </div>

      <div class="form-group">
        <label for="department">Department</label>
        <select name="department" required>
          <?php
            $departments = ['Computer Science', 'Business Administration', 'Education', 'Theology', 'Nursing', 'Agriculture', 'Other'];
            foreach ($departments as $dept) {
                $selected = $dept === $userData['department'] ? 'selected' : '';
                echo "<option value=\"$dept\" $selected>$dept</option>";
            }
          ?>
        </select>
      </div>

      <div class="form-group">
        <label for="phone_number">Phone Number</label>
        <input type="tel" name="phone_number" value="<?php echo htmlspecialchars($userData['phone_number']); ?>">
      </div>

      <button type="submit" name="update_profile">Update Profile</button>
    </form>
  </div>
</body>
</html>
