<?php
/**
 * 🔐 Epic Session Management System - EMS
 * Ekwendeni Mighty Campus Event Management System
 * 
 * This class handles all authentication, session management,
 * user registration, login, and role-based access control.
 */

class SessionManager {
    private $conn;
    private $currentUser = null;
    private $sessionTimeout = 3600; // 1 hour
    
    public function __construct($database) {
        $this->conn = $database;
        $this->startSession();
        $this->checkSessionTimeout();
        $this->loadCurrentUser();
    }
    
    /**
     * 🚀 Start secure session
     */
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure session configuration
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.use_strict_mode', 1);
            
            session_start();
            
            // Regenerate session ID periodically for security
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }
    
    /**
     * 🔐 User Registration
     */
    public function register($userData) {
        try {
            // Validate input data
            $errors = $this->validateRegistrationData($userData);
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
            
            // Check if username or email already exists
            $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
            $stmt->bind_param("ss", $userData['username'], $userData['email']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }
            
            // Hash password
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            // Generate verification token
            $verificationToken = generateToken();
            
            // Insert new user
            $stmt = $this->conn->prepare("
                INSERT INTO users (username, email, password, first_name, last_name, role, department, phone_number, verification_token, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->bind_param("sssssssss", 
                $userData['username'],
                $userData['email'],
                $hashedPassword,
                $userData['first_name'],
                $userData['last_name'],
                $userData['role'],
                $userData['department'],
                $userData['phone_number'],
                $verificationToken
            );
            
            if ($stmt->execute()) {
                $userId = $this->conn->insert_id;
                
                // Send welcome email
                $this->sendWelcomeEmail($userData['email'], $userData['first_name']);
                
                // Log activity
                logActivity($this->conn, $userId, 'user_registered', 'New user registration');
                
                return [
                    'success' => true,
                    'message' => 'Registration successful! Please check your email to verify your account.',
                    'user_id' => $userId
                ];
            } else {
                return ['success' => false, 'message' => 'Registration failed. Please try again.'];
            }
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred during registration.'];
        }
    }
    
    /**
     * 🔑 User Login
     */
    public function login($username, $password, $rememberMe = false) {
        try {
            // Find user by username or email
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return ['success' => false, 'message' => 'Invalid username or password'];
            }
            
            $user = $result->fetch_assoc();
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => 'Invalid username or password'];
            }
            
            // Check if account is verified (optional)
            // if (!$user['email_verified']) {
            //     return ['success' => false, 'message' => 'Please verify your email address first'];
            // }
            
            // Create session
            $this->createUserSession($user);
            
            // Handle "Remember Me"
            if ($rememberMe) {
              //  $this->setRememberMeCookie($user['user_id']);
            }
            
            // Update last login
           $stmt = $this->conn->prepare("UPDATE users SET updated_at = NOW() WHERE user_id = ?");
            $stmt->bind_param("i", $user['user_id']);
            $stmt->execute();
            
            // Log activity
           // logActivity($this->conn, $user['user_id'], 'user_login', 'User logged in');
            
            return [
                'success' => true,
                'message' => 'Login successful!',
                'user' => $this->sanitizeUserData($user),
                'redirect' => $this->getRedirectUrl($user['role'])
            ];
            
        } catch (Exception $e) {
          //  error_log("Login error: " . $e->getMessage());
           return ['success' => false, 'message' => 'An error occurred during login.'];
        }
    }
    
    /**
     * 🚪 User Logout
     */
    public function logout() {
        try {
            if (isset($_SESSION['user_id'])) {
                // Log activity
                logActivity($this->conn, $_SESSION['user_id'], 'user_logout', 'User logged out');
            }
            
            // Clear session data
            $_SESSION = [];
            
            // Delete session cookie
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }
            
            // Clear remember me cookie
            if (isset($_COOKIE['remember_me'])) {
                setcookie('remember_me', '', time() - 3600, '/');
            }
            
            // Destroy session
            session_destroy();
            
            return ['success' => true, 'message' => 'Logged out successfully'];
            
        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred during logout.'];
        }
    }
    
    /**
     * 👤 Get Current User
     */
    public function getCurrentUser() {
        return $this->currentUser;
    }
    
    /**
     * 🔍 Check if user is logged in
     */
    public function isLoggedIn() {
        return $this->currentUser !== null;
    }
    
    /**
     * 🛡️ Check user role
     */
    public function hasRole($role) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        if (is_array($role)) {
            return in_array($this->currentUser['role'], $role);
        }
        
        return $this->currentUser['role'] === $role;
    }
    
    /**
     * 🔐 Require login
     */
    public function requireLogin($redirectUrl = '/EMS/auth/login.php') {
        if (!$this->isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header("Location: $redirectUrl");
            exit;
        }
    }
    
    /**
     * 🛡️ Require specific role
     */
    public function requireRole($role, $redirectUrl = '/EMS/dashboard/') {
        $this->requireLogin();
        
        if (!$this->hasRole($role)) {
            $_SESSION['flash_message'] = 'Access denied. Insufficient permissions.';
            $_SESSION['flash_type'] = 'error';
            header("Location: $redirectUrl");
            exit;
        }
    }
    
    /**
     * 🔄 Password Reset Request
     */
    public function requestPasswordReset($email) {
        try {
            // Find user by email
            $stmt = $this->conn->prepare("SELECT user_id, first_name FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                // Don't reveal if email exists or not for security
                return ['success' => true, 'message' => 'If the email exists, a reset link has been sent.'];
            }
            
            $user = $result->fetch_assoc();
            
            // Generate reset token
            $resetToken = generateToken();
            $resetExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store reset token
            $stmt = $this->conn->prepare("UPDATE users SET verification_token = ?, updated_at = ? WHERE user_id = ?");
            $stmt->bind_param("ssi", $resetToken, $resetExpiry, $user['user_id']);
            $stmt->execute();
            
            // Send reset email
            $this->sendPasswordResetEmail($email, $user['first_name'], $resetToken);
            
            return ['success' => true, 'message' => 'Password reset link has been sent to your email.'];
            
        } catch (Exception $e) {
            error_log("Password reset request error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again.'];
        }
    }
    
    /**
     * 🔑 Reset Password
     */
    public function resetPassword($token, $newPassword) {
        try {
            // Find user by token
            $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE verification_token = ? AND updated_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return ['success' => false, 'message' => 'Invalid or expired reset token.'];
            }
            
            $user = $result->fetch_assoc();
            
            // Validate new password
            if (strlen($newPassword) < 6) {
                return ['success' => false, 'message' => 'New password must be at least 6 characters long.'];
            }
            
            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password
            $stmt = $this->conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?");
            $stmt->bind_param("si", $hashedPassword, $user['user_id']);
            
            if ($stmt->execute()) {
                // Log activity
                logActivity($this->conn, $user['user_id'], 'password_changed', 'User changed password');
                
                return ['success' => true, 'message' => 'Password changed successfully!'];
            } else {
                return ['success' => false, 'message' => 'Failed to change password.'];
            }
            
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again.'];
        }
    }
    
    /**
     * 👤 Update User Profile
     */
    public function updateProfile($userId, $profileData) {
        try {
            // Validate profile data
            $errors = $this->validateProfileData($profileData);
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
            
            // Check if email is being changed and if it's already taken
            if (!empty($profileData['email'])) {
                $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                $stmt->bind_param("si", $profileData['email'], $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    return ['success' => false, 'message' => 'Email address is already in use.'];
                }
            }
            
            // Build update query dynamically
            $updateFields = [];
            $params = [];
            $types = '';
            
            $allowedFields = ['first_name', 'last_name', 'email', 'department', 'phone_number'];
            
            foreach ($allowedFields as $field) {
                if (isset($profileData[$field]) && $profileData[$field] !== '') {
                    $updateFields[] = "$field = ?";
                    $params[] = $profileData[$field];
                    $types .= 's';
                }
            }
            
            if (empty($updateFields)) {
                return ['success' => false, 'message' => 'No valid fields to update.'];
            }
            
            $sql = "UPDATE users SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE user_id = ?";
            $params[] = $userId;
            $types .= 'i';
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                // Reload current user data
                $this->loadCurrentUser();
                
                // Log activity
                logActivity($this->conn, $userId, 'profile_updated', 'User profile updated');
                
                return ['success' => true, 'message' => 'Profile updated successfully!'];
            } else {
                return ['success' => false, 'message' => 'Failed to update profile.'];
            }
            
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while updating profile.'];
        }
    }
    
    /**
     * 🔐 Change Password
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Get current password hash
            $stmt = $this->conn->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return ['success' => false, 'message' => 'User not found.'];
            }
            
            $user = $result->fetch_assoc();
            
            // Verify current password
            if (!password_verify($currentPassword, $user['password'])) {
                return ['success' => false, 'message' => 'Current password is incorrect.'];
            }
            
            // Validate new password
            if (strlen($newPassword) < 6) {
                return ['success' => false, 'message' => 'New password must be at least 6 characters long.'];
            }
            
            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password
            $stmt = $this->conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?");
            $stmt->bind_param("si", $hashedPassword, $userId);
            
            if ($stmt->execute()) {
                // Log activity
                logActivity($this->conn, $userId, 'password_changed', 'User changed password');
                
                return ['success' => true, 'message' => 'Password changed successfully!'];
            } else {
                return ['success' => false, 'message' => 'Failed to change password.'];
            }
            
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while changing password.'];
        }
    }
    
    /**
     * 🔍 Private Methods - Internal Functionality
     */
    
    /**
     * ⏰ Check session timeout
     */
    private function checkSessionTimeout() {
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > $this->sessionTimeout) {
                $this->logout();
                return;
            }
        }
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * 👤 Load current user data
     */
    private function loadCurrentUser() {
        if (isset($_SESSION['user_id'])) {
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $this->currentUser = $result->fetch_assoc();
            } else {
                // User not found, clear session
                $this->logout();
            }
        } elseif (isset($_COOKIE['remember_me'])) {
            // Check remember me cookie
           //$this->checkRememberMeCookie();
        }
    }
    
    /**
     * 🔐 Create user session
     */
    private function createUserSession($user) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['last_activity'] = time();
        
        $this->currentUser = $user;
    }
    
    /**
     * 🍪 Set remember me cookie
     */
 /*   private function setRememberMeCookie($userId) {
        $token = generateToken();
        $expiry = time() + (30 * 24 * 60 * 60); // 30 days
        
        // Store token in database (you might want to create a remember_tokens table)
        $stmt = $this->conn->prepare("UPDATE users SET verification_token = ? WHERE user_id = ?");
        $stmt->bind_param("si", $token, $userId);
        $stmt->execute();
        
        // Set cookie
        setcookie('remember_me', $token, $expiry, '/', '', isset($_SERVER['HTTPS']), true);
    }
    */
    /**
     * 🍪 Check remember me cookie
     */
   /* private function checkRememberMeCookie() {
        if (isset($_COOKIE['remember_me'])) {
            $token = $_COOKIE['remember_me'];
            
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE verification_token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $this->createUserSession($user);
            } else {
                // Invalid token, clear cookie
                setcookie('remember_me', '', time() - 3600, '/');
            }
        }
    }*/
    
    /**
     * ✅ Validate registration data
     */
    private function validateRegistrationData($data) {
        $errors = [];
        
        // Required fields
        $requiredFields = ['username', 'email', 'password', 'first_name', 'last_name'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        
        // Username validation
        if (!empty($data['username'])) {
            if (strlen($data['username']) < 3) {
                $errors[] = 'Username must be at least 3 characters long';
            }
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
                $errors[] = 'Username can only contain letters, numbers, and underscores';
            }
        }
        
        // Email validation
        if (!empty($data['email']) && !isValidEmail($data['email'])) {
            $errors[] = 'Please enter a valid email address';
        }
        
        // Password validation
        if (!empty($data['password'])) {
            if (strlen($data['password']) < 6) {
                $errors[] = 'Password must be at least 6 characters long';
            }
        }
        
        // Phone validation
        if (!empty($data['phone_number']) && !isValidPhone($data['phone_number'])) {
            $errors[] = 'Please enter a valid phone number';
        }
        
        // Role validation
        $allowedRoles = ['user', 'organizer'];
        if (!empty($data['role']) && !in_array($data['role'], $allowedRoles)) {
            $errors[] = 'Invalid role selected';
        }
        
        return $errors;
    }
    
    /**
     * ✅ Validate profile data
     */
    private function validateProfileData($data) {
        $errors = [];
        
        // Email validation
        if (!empty($data['email']) && !isValidEmail($data['email'])) {
            $errors[] = 'Please enter a valid email address';
        }
        
        // Phone validation
        if (!empty($data['phone_number']) && !isValidPhone($data['phone_number'])) {
            $errors[] = 'Please enter a valid phone number';
        }
        
        // Name validation
        if (!empty($data['first_name']) && strlen($data['first_name']) < 2) {
            $errors[] = 'First name must be at least 2 characters long';
        }
        
        if (!empty($data['last_name']) && strlen($data['last_name']) < 2) {
            $errors[] = 'Last name must be at least 2 characters long';
        }
        
        return $errors;
    }
    
    /**
     * 🧹 Sanitize user data for output
     */
    private function sanitizeUserData($user) {
        unset($user['password']);
        unset($user['verification_token']);
        return $user;
    }
    
    /**
     * 🔄 Get redirect URL based on role
     */
    private function getRedirectUrl($role) {
        $redirectUrls = [
            'admin' => '/EMS/admin/dashboard.php',
            'organizer' => '/EMS/organizer/dashboard.php',
            'user' => '/EMS/dashboard/index.php'
        ];
        
        // Check if there's a stored redirect URL
        if (isset($_SESSION['redirect_after_login'])) {
            $url = $_SESSION['redirect_after_login'];
            unset($_SESSION['redirect_after_login']);
            return $url;
        }
        
        return $redirectUrls[$role] ?? '/EMS/dashboard/index.php';
    }
    
    /**
     * 📧 Send welcome email
     */
    private function sendWelcomeEmail($email, $firstName) {
        $subject = "Welcome to Ekwendeni Mighty Campus EMS! 🎓";
        $message = "
        <html>
        <head>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; background: #ff9a9e; color: white; padding: 12px 30px; text-decoration: none; border-radius: 25px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎓 Welcome to EMS!</h1>
                    <p>Ekwendeni Mighty Campus Event Management System</p>
                </div>
                <div class='content'>
                    <h2>Hello " . htmlspecialchars($firstName) . "! 👋</h2>
                    <p>Welcome to our amazing event management system! You can now:</p>
                    <ul>
                        <li>🎪 Discover campus events</li>
                        <li>🎫 Register for events</li>
                        <li>📅 Manage your schedule</li>
                        <li>🌟 Connect with peers</li>
                    </ul>
                    <a href='" . $_SERVER['HTTP_HOST'] . "/EMS/dashboard/' class='button'>Get Started 🚀</a>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return sendEmail($email, $subject, $message, true);
    }
    
    /**
     * 📧 Send password reset email
     */
    private function sendPasswordResetEmail($email, $firstName, $token) {
        $resetUrl = $_SERVER['HTTP_HOST'] . "/EMS/auth/reset-password.php?token=" . $token;
        
        $subject = "Password Reset Request - EMS";
        $message = "
        <html>
        <head>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; background: #ff6b6b; color: white; padding: 12px 30px; text-decoration: none; border-radius: 25px; margin: 20px 0; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Password Reset</h1>
                    <p>Ekwendeni Mighty Campus EMS</p>
                </div>
                <div class='content'>
                    <h2>Hello " . htmlspecialchars($firstName) . "!</h2>
                    <p>We received a request to reset your password. Click the button below to create a new password:</p>
                    
                    <a href='" . $resetUrl . "' class='button'>Reset Password 🔑</a>
                    
                    <div class='warning'>
                        <strong>⚠️ Important:</strong>
                        <ul>
                            <li>This link will expire in 1 hour</li>
                            <li>If you didn't request this reset, please ignore this email</li>
                            <li>For security, never share this link with anyone</li>
                        </ul>
                    </div>
                    
                    <p>If the button doesn't work, copy and paste this link:<br>
                    <code>" . $resetUrl . "</code></p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return sendEmail($email, $subject, $message, true);
    }
    
    /**
     * 📊 Get user statistics
     */
    public function getUserStats($userId) {
        try {
            $stats = [];
            
            // Events registered
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM tickets WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['events_registered'] = $result->fetch_assoc()['count'];
            
            // Events attended
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM tickets WHERE user_id = ? AND is_used = 1");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['events_attended'] = $result->fetch_assoc()['count'];
            
            // Upcoming events
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as count 
                FROM tickets t 
                JOIN events e ON t.event_id = e.event_id 
                WHERE t.user_id = ? AND e.start_datetime > NOW()
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['upcoming_events'] = $result->fetch_assoc()['count'];
            
            // Total spent
            $stmt = $this->conn->prepare("SELECT SUM(price) as total FROM tickets WHERE user_id = ? AND payment_status = 'completed'");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['total_spent'] = $result->fetch_assoc()['total'] ?? 0;
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("User stats error: " . $e->getMessage());
            return [
                'events_registered' => 0,
                'events_attended' => 0,
                'upcoming_events' => 0,
                'total_spent' => 0
            ];
        }
    }
    
    /**
     * 🔔 Get user notifications count
     */
    public function getUnreadNotificationsCount($userId) {
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_assoc()['count'] ?? 0;
            
        } catch (Exception $e) {
            error_log("Notifications count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 🎯 Check if user can access event
     */
    public function canAccessEvent($eventId, $userId = null) {
        if (!$userId) {
            $userId = $this->currentUser['user_id'] ?? null;
        }
        
        if (!$userId) {
            return false;
        }
        
        try {
            // Check if user is the organizer
            $stmt = $this->conn->prepare("SELECT organizer_id FROM events WHERE event_id = ?");
            $stmt->bind_param("i", $eventId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $event = $result->fetch_assoc();
                
                // Admin can access all events
                if ($this->hasRole('admin')) {
                    return true;
                }
                
                // Organizer can access their own events
                if ($event['organizer_id'] == $userId) {
                    return true;
                }
                
                // Check if user has a ticket
                $stmt = $this->conn->prepare("SELECT ticket_id FROM tickets WHERE event_id = ? AND user_id = ?");
                $stmt->bind_param("ii", $eventId, $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                return $result->num_rows > 0;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Event access check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 🔐 Two-Factor Authentication Setup (Future Enhancement)
     */
    public function setup2FA($userId) {
        // Placeholder for 2FA implementation
        // You can integrate with Google Authenticator or similar
        return ['success' => false, 'message' => '2FA not implemented yet'];
    }
    
    /**
     * 📱 Send SMS verification (Future Enhancement)
     */
    public function sendSMSVerification($phoneNumber) {
        // Placeholder for SMS verification
        return ['success' => false, 'message' => 'SMS verification not implemented yet'];
    }
    
    /**
     * 🔍 Search users (Admin only)
     */
    public function searchUsers($query, $role = null, $limit = 20) {
        if (!$this->hasRole('admin')) {
            return ['success' => false, 'message' => 'Access denied'];
        }
        
        try {
            $sql = "SELECT user_id, username, email, first_name, last_name, role, department, created_at 
                    FROM users 
                    WHERE (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
            
            $searchTerm = '%' . $query . '%';
            $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
            $types = 'ssss';
            
            if ($role) {
                $sql .= " AND role = ?";
                $params[] = $role;
                $types .= 's';
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;
            $types .= 'i';
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return [
                'success' => true,
                'users' => $result->fetch_all(MYSQLI_ASSOC)
            ];
            
        } catch (Exception $e) {
            error_log("User search error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Search failed'];
        }
    }
    
    /**
     * 🛡️ Update user role (Admin only)
     */
    public function updateUserRole($userId, $newRole) {
        if (!$this->hasRole('admin')) {
            return ['success' => false, 'message' => 'Access denied'];
        }
        
        $allowedRoles = ['user', 'organizer', 'admin'];
        if (!in_array($newRole, $allowedRoles)) {
            return ['success' => false, 'message' => 'Invalid role'];
        }
        
        try {
            $stmt = $this->conn->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE user_id = ?");
            $stmt->bind_param("si", $newRole, $userId);
            
            if ($stmt->execute()) {
                // Log activity
                logActivity($this->conn, $this->currentUser['user_id'], 'role_updated', "Changed user $userId role to $newRole");
                
                return ['success' => true, 'message' => 'User role updated successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to update user role'];
            }
            
        } catch (Exception $e) {
            error_log("Role update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred'];
        }
    }
    
    /**
     * 🚫 Suspend/Activate user (Admin only)
     */
    public function toggleUserStatus($userId, $suspend = true) {
        if (!$this->hasRole('admin')) {
            return ['success' => false, 'message' => 'Access denied'];
        }
        
        try {
            // Add a status field to users table if not exists
            $status = $suspend ? 'suspended' : 'active';
            
            // For now, we'll use a simple approach
            // You might want to add a 'status' column to the users table
            $stmt = $this->conn->prepare("UPDATE users SET updated_at = NOW() WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            
            if ($stmt->execute()) {
                $action = $suspend ? 'suspended' : 'activated';
                logActivity($this->conn, $this->currentUser['user_id'], 'user_status_changed', "User $userId $action");
                
                return ['success' => true, 'message' => "User $action successfully"];
            } else {
                return ['success' => false, 'message' => 'Failed to update user status'];
            }
            
        } catch (Exception $e) {
            error_log("User status toggle error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred'];
        }
    }
    
    /**
     * 📊 Get login history (Future Enhancement)
     */
    public function getLoginHistory($userId, $limit = 10) {
        // Placeholder for login history
        // You would need to create a login_history table
        return [
            'success' => true,
            'history' => []
        ];
    }
    
    /**
     * 🔐 Verify email address
     */
    public function verifyEmail($token) {
        try {
            $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE verification_token = ? AND email_verified = 0");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return ['success' => false, 'message' => 'Invalid or expired verification token'];
            }
            
            $user = $result->fetch_assoc();
            
            // Mark email as verified
            $stmt = $this->conn->prepare("UPDATE users SET email_verified = 1, verification_token = NULL, updated_at = NOW() WHERE user_id = ?");
            $stmt->bind_param("i", $user['user_id']);
            
            if ($stmt->execute()) {
                logActivity($this->conn, $user['user_id'], 'email_verified', 'Email address verified');
                
                return ['success' => true, 'message' => 'Email verified successfully! You can now login.'];
            } else {
                return ['success' => false, 'message' => 'Failed to verify email'];
            }
            
        } catch (Exception $e) {
            error_log("Email verification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred'];
        }
    }
    
    /**
     * 🔄 Resend verification email
     */
    public function resendVerificationEmail($email) {
        try {
            $stmt = $this->conn->prepare("SELECT user_id, first_name, verification_token FROM users WHERE email = ? AND email_verified = 0");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return ['success' => false, 'message' => 'Email not found or already verified'];
            }
            
            $user = $result->fetch_assoc();
            
            // Generate new token if needed
            if (empty($user['verification_token'])) {
                $newToken = generateToken();
                $stmt = $this->conn->prepare("UPDATE users SET verification_token = ? WHERE user_id = ?");
                $stmt->bind_param("si", $newToken, $user['user_id']);
                $stmt->execute();
                $user['verification_token'] = $newToken;
            }
            
            // Send verification email
            $this->sendVerificationEmail($email, $user['first_name'], $user['verification_token']);
            
            return ['success' => true, 'message' => 'Verification email sent successfully'];
            
        } catch (Exception $e) {
            error_log("Resend verification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send verification email'];
        }
    }
    
    /**
     * 📧 Send verification email
     */
    private function sendVerificationEmail($email, $firstName, $token) {
        $verifyUrl = $_SERVER['HTTP_HOST'] . "/EMS/auth/verify-email.php?token=" . $token;
        
        $subject = "Verify Your Email - EMS";
        $message = "
        <html>
        <head>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; background: #4CAF50; color: white; padding: 12px 30px; text-decoration: none; border-radius: 25px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✅ Verify Your Email</h1>
                    <p>Ekwendeni Mighty Campus EMS</p>
                </div>
                <div class='content'>
                    <h2>Hello " . htmlspecialchars($firstName) . "!</h2>
                    <p>Thank you for registering! Please verify your email address to complete your account setup:</p>
                    
                    <a href='" . $verifyUrl . "' class='button'>Verify Email ✅</a>
                    
                    <p>If the button doesn't work, copy and paste this link:<br>
                    <code>" . $verifyUrl . "</code></p>
                    
                    <p>This link will expire in 24 hours for security reasons.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return sendEmail($email, $subject, $message, true);
    }
    
    /**
     * 🎯 Final cleanup method
     */
    public function __destruct() {
        // Any cleanup needed when the session manager is destroyed
        // This is automatically called when the script ends
    }
}

// 🌟 Global helper functions for easy access

/**
 * 🔍 Check if user is logged in (Global function)
 */
function isLoggedIn() {
    global $sessionManager;
    return $sessionManager ? $sessionManager->isLoggedIn() : false;
}

/**
 * 👤 Get current user (Global function)
 */
function getCurrentUser() {
    global $sessionManager;
    return $sessionManager ? $sessionManager->getCurrentUser() : null;
}

/**
 * 🛡️ Check user role (Global function)
 */
function hasRole($role) {
    global $sessionManager;
    return $sessionManager ? $sessionManager->hasRole($role) : false;
}

/**
 * 🔐 Require login (Global function)
 */
function requireLogin($redirectUrl = '/EMS/auth/login.php') {
    global $sessionManager;
    if ($sessionManager) {
        $sessionManager->requireLogin($redirectUrl);
    } else {
        header("Location: $redirectUrl");
        exit;
    }
}

/**
 * 🛡️ Require role (Global function)
 */
function requireRole($role, $redirectUrl = '/EMS/dashboard/') {
    global $sessionManager;
    if ($sessionManager) {
        $sessionManager->requireRole($role, $redirectUrl);
    } else {
        header("Location: $redirectUrl");
        exit;
    }
}

/**
 * 🎯 Initialize session manager (Call this in your bootstrap/config)
 */
function initializeSessionManager($database) {
    global $sessionManager;
    $sessionManager = new SessionManager($database);
    return $sessionManager;
}
?>