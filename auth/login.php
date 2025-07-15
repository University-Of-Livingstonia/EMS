<?php
/**
 * 🔐 Epic Login Page - EMS
 * Ekwendeni Mighty Campus Event Management System
 */


require_once dirname(__DIR__) . '/config/database.php';
require_once '../includes/session.php';

// Initialize SessionManager if not already done
if (!isset($sessionManager)) {
    $sessionManager = initializeSessionManager($conn);
}
// Redirect if already logged in
if (isLoggedIn()) {
    $user = getCurrentUser();
    $basePath = dirname(dirname($_SERVER['PHP_SELF']));
    header('Location: ' . $basePath . '/dashboard/index.php');
    exit;
}

$error = '';
$success = '';

// Handle login form submission
if ($_POST && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $result = $sessionManager->login($email, $password, $remember);
        
        if ($result['success']) {
            $basePath = dirname(dirname($_SERVER['PHP_SELF']));
            header('Location: ' . $basePath . '/dashboard/index.php');
            exit;
        } else {
            $error = $result['message'];
        }
        
    }
}

// Handle URL parameters
if (isset($_GET['registered'])) {
    $success = 'Registration successful! Please check your email to verify your account.';
}
if (isset($_GET['timeout'])) {
    $error = 'Your session has expired. Please log in again.';
}
if (isset($_GET['error']) && $_GET['error'] === 'access_denied') {
    $error = 'Access denied. Please log in with appropriate permissions.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EMS | Ekwendeni Mighty Campus</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 600px;
        }
        
        .login-left {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            animation: float 20s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .logo {
            font-size: 3rem;
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
        }
        
        .welcome-text {
            position: relative;
            z-index: 2;
        }
        
        .welcome-text h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .welcome-text p {
            font-size: 1.1rem;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .login-right {
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-header h2 {
            color: #333;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #666;
            font-size: 1rem;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #51cf66, #40c057);
            color: white;
            box-shadow: 0 4px 15px rgba(81, 207, 102, 0.3);
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .form-control {
            width: 100%;
            padding: 15px 20px 15px 50px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4facfe;
            background: white;
            box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.1);
            transform: translateY(-2px);
        }
        
        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 1.1rem;
        }
        
        .form-control:focus + .input-icon {
            color: #4facfe;
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-wrapper input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #4facfe;
        }
        
        .checkbox-wrapper label {
            margin: 0;
            font-size: 0.9rem;
            color: #666;
        }
        
        .forgot-password {
            color: #4facfe;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .forgot-password:hover {
            color: #357abd;
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(79, 172, 254, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .divider {
            text-align: center;
            margin: 30px 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e1e5e9;
        }
        
        .divider span {
            background: white;
            padding: 0 20px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .register-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .register-link p {
            color: #666;
            margin-bottom: 10px;
        }
        
        .btn-register {
            display: inline-block;
            padding: 12px 30px;
            background: transparent;
            color: #4facfe;
            border: 2px solid #4facfe;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-register:hover {
            background: #4facfe;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(79, 172, 254, 0.3);
        }
        
        .loading {
            display: none;
            margin-left: 10px;
        }
        
        .loading.show {
            display: inline-block;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .login-container {
                grid-template-columns: 1fr;
                max-width: 400px;
            }
            
            .login-left {
                padding: 40px 30px;
                min-height: 200px;
            }
            
            .welcome-text h1 {
                font-size: 2rem;
            }
            
            .logo {
                font-size: 2rem;
            }
            
            .login-right {
                padding: 40px 30px;
            }
            
            .form-options {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }
        
        /* Animation for form elements */
        .form-group {
            opacity: 0;
            transform: translateY(20px);
            animation: slideUp 0.6s ease forwards;
        }
        
        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        .form-group:nth-child(4) { animation-delay: 0.4s; }
        
        @keyframes slideUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left Side - Welcome -->
        <div class="login-left">
            <div class="logo">🎓</div>
            <div class="welcome-text">
                <h1>Welcome Back!</h1>
                <p>Access your Ekwendeni Mighty Campus Event Management System and stay connected with all campus events.</p>
            </div>
        </div>
        
        <!-- Right Side - Login Form -->
        <div class="login-right">
            <div class="login-header">
                <h2>Sign In</h2>
                <p>Enter your credentials to access your account</p>
            </div>
            
            <!-- Error/Success Messages -->
            <?php if ($error): ?>
                <div class="alert alert-error">
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
            
            <!-- Login Form -->
            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control" 
                               placeholder="Enter your email"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               required>
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control" 
                               placeholder="Enter your password"
                               required>
                        <i class="fas fa-lock input-icon"></i>
                    </div>
                </div>
                
                <div class="form-options">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="login" class="btn-login" id="loginBtn">
                        <span>Sign In</span>
                        <i class="fas fa-spinner fa-spin loading" id="loginSpinner"></i>
                    </button>
                </div>
            </form>
            
            <div class="divider">
                <span>Don't have an account?</span>
            </div>
            
            <div class="register-link">
                <p>Join the Ekwendeni Mighty Campus community</p>
                <a href="register.php" class="btn-register">
                    <i class="fas fa-user-plus"></i> Create Account
                </a>
            </div>
        </div>
    </div>

    <script>
        // 🚀 Enhanced Login Form JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const loginSpinner = document.getElementById('loginSpinner');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            
            // Form submission with loading state
            loginForm.addEventListener('submit', function(e) {
                // Show loading state
               // loginBtn.disabled = true;
                loginSpinner.classList.add('show');
                loginBtn.querySelector('span').textContent = 'Signing In...';
                
                // Add a small delay for better UX
                setTimeout(() => {
                    // Form will submit normally
                }, 500);
            });
            
            // Enhanced input focus effects
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.parentElement.classList.remove('focused');
                });
            });
            
            // Password visibility toggle
            const passwordToggle = document.createElement('i');
            passwordToggle.className = 'fas fa-eye password-toggle';
            passwordToggle.style.cssText = `
                position: absolute;
                right: 18px;
                top: 50%;
                transform: translateY(-50%);
                cursor: pointer;
                color: #666;
                z-index: 10;
            `;
            
            passwordInput.parentElement.appendChild(passwordToggle);
            
            passwordToggle.addEventListener('click', function() {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    this.className = 'fas fa-eye-slash password-toggle';
                } else {
                    passwordInput.type = 'password';
                    this.className = 'fas fa-eye password-toggle';
                }
            });
            
            // Auto-focus first empty field
            if (!emailInput.value) {
                emailInput.focus();
            } else if (!passwordInput.value) {
                passwordInput.focus();
            }
            
            // Enter key handling
            emailInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    passwordInput.focus();
                }
            });
            
            // Form validation feedback
            function showFieldError(field, message) {
                field.style.borderColor = '#ff6b6b';
                field.style.boxShadow = '0 0 0 3px rgba(255, 107, 107, 0.1)';
                
                // Remove error styling after user starts typing
                field.addEventListener('input', function() {
                    this.style.borderColor = '#e1e5e9';
                    this.style.boxShadow = 'none';
                }, { once: true });
            }
            
            // Real-time validation
            emailInput.addEventListener('blur', function() {
                if (this.value && !this.value.includes('@')) {
                    showFieldError(this, 'Please enter a valid email');
                }
            });
            
            passwordInput.addEventListener('blur', function() {
                if (this.value && this.value.length < 6) {
                    showFieldError(this, 'Password must be at least 6 characters');
                }
            });
        });
        
        // 🎨 Additional animations and effects
        window.addEventListener('load', function() {
            // Animate login container entrance
            const container = document.querySelector('.login-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                container.style.transition = 'all 0.6s ease';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>