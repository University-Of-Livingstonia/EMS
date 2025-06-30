<?php
/**
 * 📝 Epic Registration Page - EMS
 * Ekwendeni Mighty Campus Event Management System
 */

require_once '../includes/session.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: /EMS/dashboard/index.php');
    exit;
}

$errors = [];
$success = '';
$formData = [];

// Handle registration form submission
if ($_POST && isset($_POST['register'])) {
    $formData = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'department' => trim($_POST['department'] ?? ''),
        'phone_number' => trim($_POST['phone_number'] ?? ''),
        'role' => $_POST['role'] ?? 'user'
    ];
    
    // Validate passwords match
    if ($formData['password'] !== $formData['confirm_password']) {
        $errors[] = 'Passwords do not match';
    }
    
    // Validate terms acceptance
    if (!isset($_POST['terms'])) {
        $errors[] = 'You must accept the terms and conditions';
    }
    
    if (empty($errors)) {
        $result = $sessionManager->register($formData);
        
        if ($result['success']) {
            header('Location: login.php?registered=1');
            exit;
        } else {
            if (isset($result['errors'])) {
                $errors = array_merge($errors, $result['errors']);
            } else {
                $errors[] = $result['message'];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - EMS | Ekwendeni Mighty Campus</title>
    
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
            padding: 20px 0;
        }
        
        .register-container {
            max-width: 1000px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            min-height: 700px;
        }
        
        .register-left {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 50%, #fecfef 100%);
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
            position: relative;
        }
        
        .register-left::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="60" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="60" cy="40" r="1" fill="rgba(255,255,255,0.1)"/></svg>');
            animation: float 15s ease-in-out infinite;
        }
        
        .register-logo {
            font-size: 3rem;
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
        }
        
        .register-welcome {
            position: relative;
            z-index: 2;
        }
        
        .register-welcome h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .register-welcome p {
            font-size: 1rem;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .register-right {
            padding: 40px;
            overflow-y: auto;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h2 {
            color: #333;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .register-header p {
            color: #666;
            font-size: 0.95rem;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }
        
        .alert-error ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #ff9a9e;
            background: white;
            box-shadow: 0 0 0 3px rgba(255, 154, 158, 0.1);
        }
        
        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 1rem;
        }
        
        .form-control:focus + .input-icon {
            color: #ff9a9e;
        }
        
        select.form-control {
            padding-left: 40px;
            cursor: pointer;
        }
        
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 25px 0;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #ff9a9e;
            margin-top: 2px;
        }
        
        .checkbox-group label {
            margin: 0;
            font-size: 0.9rem;
            color: #666;
            line-height: 1.4;
        }
        
        .checkbox-group a {
            color: #ff9a9e;
            text-decoration: none;
        }
        
        .checkbox-group a:hover {
            text-decoration: underline;
        }
        
        .btn-register {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-register::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-register:hover::before {
            left: 100%;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 154, 158, 0.4);
        }
        
        .btn-register:active {
            transform: translateY(0);
        }
        
        .login-link {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #e1e5e9;
        }
        
        .login-link p {
            color: #666;
            margin-bottom: 10px;
        }
        
        .login-link a {
            color: #ff9a9e;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .login-link a:hover {
            color: #e8898d;
            text-decoration: underline;
        }
        
        .loading {
            display: none;
            margin-left: 10px;
        }
        
        .loading.show {
            display: inline-block;
        }
        
        /* Password strength indicator */
        .password-strength {
            margin-top: 5px;
            height: 4px;
            background: #e1e5e9;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .strength-weak { background: #ff6b6b; width: 25%; }
        .strength-fair { background: #ffa726; width: 50%; }
        .strength-good { background: #66bb6a; width: 75%; }
        .strength-strong { background: #4caf50; width: 100%; }
        
        .password-requirements {
            margin-top: 8px;
            font-size: 0.8rem;
            color: #666;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            gap: 5px;
            margin: 2px 0;
        }
        
        .requirement.met {
            color: #4caf50;
        }
        
        .requirement.met i {
            color: #4caf50;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .register-container {
                grid-template-columns: 1fr;
                margin: 10px;
            }
            
            .register-left {
                padding: 30px 20px;
                min-height: 200px;
            }
            
            .register-welcome h1 {
                font-size: 1.8rem;
            }
            
            .register-logo {
                font-size: 2rem;
            }
            
            .register-right {
                padding: 30px 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
        
        /* Animation for form elements */
        .form-group, .form-row {
            opacity: 0;
            transform: translateY(20px);
            animation: slideUp 0.6s ease forwards;
        }
        
        .form-group:nth-child(1), .form-row:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2), .form-row:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3), .form-row:nth-child(3) { animation-delay: 0.3s; }
        .form-group:nth-child(4), .form-row:nth-child(4) { animation-delay: 0.4s; }
        .form-group:nth-child(5), .form-row:nth-child(5) { animation-delay: 0.5s; }
        .form-group:nth-child(6), .form-row:nth-child(6) { animation-delay: 0.6s; }
        
        @keyframes slideUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-15px) rotate(180deg); }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <!-- Left Side - Welcome -->
        <div class="register-left">
            <div class="register-logo">🎓</div>
            <div class="register-welcome">
                <h1>Join Us Today!</h1>
                <p>Create your account and become part of the Ekwendeni Mighty Campus community. Access exclusive events, connect with peers, and never miss an opportunity!</p>
            </div>
        </div>
        
        <!-- Right Side - Registration Form -->
        <div class="register-right">
            <div class="register-header">
                <h2>Create Account</h2>
                <p>Fill in your details to get started</p>
            </div>
            
            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Please fix the following errors:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Registration Form -->
            <form method="POST" id="registerForm">
                <!-- Name Fields -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <div class="input-wrapper">
                            <input type="text" 
                                   id="first_name" 
                                   name="first_name" 
                                   class="form-control" 
                                   placeholder="Enter first name"
                                   value="<?php echo htmlspecialchars($formData['first_name'] ?? ''); ?>"
                                   required>
                            <i class="fas fa-user input-icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <div class="input-wrapper">
                            <input type="text" 
                                   id="last_name" 
                                   name="last_name" 
                                   class="form-control" 
                                   placeholder="Enter last name"
                                   value="<?php echo htmlspecialchars($formData['last_name'] ?? ''); ?>"
                                   required>
                            <i class="fas fa-user input-icon"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Username and Email -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-wrapper">
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   class="form-control" 
                                   placeholder="Choose username"
                                   value="<?php echo htmlspecialchars($formData['username'] ?? ''); ?>"
                                   required>
                            <i class="fas fa-at input-icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrapper">
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   class="form-control" 
                                   placeholder="Enter email address"
                                   value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
                                   required>
                            <i class="fas fa-envelope input-icon"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Password Fields -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   class="form-control" 
                                   placeholder="Create password"
                                   required>
                            <i class="fas fa-lock input-icon"></i>
                        </div>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="password-requirements" id="passwordRequirements">
                            <div class="requirement" id="req-length">
                                <i class="fas fa-times"></i> At least 6 characters
                            </div>
                            <div class="requirement" id="req-number">
                                <i class="fas fa-times"></i> Contains a number
                            </div>
                            <div class="requirement" id="req-letter">
                                <i class="fas fa-times"></i> Contains a letter
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-wrapper">
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   class="form-control" 
                                   placeholder="Confirm password"
                                   required>
                            <i class="fas fa-lock input-icon"></i>
                        </div>
                        <div id="passwordMatch" style="font-size: 0.8rem; margin-top: 5px;"></div>
                    </div>
                </div>
                
                <!-- Department and Phone -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="department">Department</label>
                        <div class="input-wrapper">
                            <select id="department" name="department" class="form-control">
                                <option value="">Select Department</option>
                                <option value="Computer Science" <?php echo ($formData['department'] ?? '') === 'Computer Science' ? 'selected' : ''; ?>>Computer Science</option>
                                <option value="Business Administration" <?php echo ($formData['department'] ?? '') === 'Business Administration' ? 'selected' : ''; ?>>Business Administration</option>
                                <option value="Education" <?php echo ($formData['department'] ?? '') === 'Education' ? 'selected' : ''; ?>>Education</option>
                                <option value="Theology" <?php echo ($formData['department'] ?? '') === 'Theology' ? 'selected' : ''; ?>>Theology</option>
                                <option value="Nursing" <?php echo ($formData['department'] ?? '') === 'Nursing' ? 'selected' : ''; ?>>Nursing</option>
                                <option value="Agriculture" <?php echo ($formData['department'] ?? '') === 'Agriculture' ? 'selected' : ''; ?>>Agriculture</option>
                                <option value="Other" <?php echo ($formData['department'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                            <i class="fas fa-building input-icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone_number">Phone Number</label>
                        <div class="input-wrapper">
                            <input type="tel" 
                                   id="phone_number" 
                                   name="phone_number" 
                                   class="form-control" 
                                   placeholder="Enter phone number"
                                   value="<?php echo htmlspecialchars($formData['phone_number'] ?? ''); ?>">
                            <i class="fas fa-phone input-icon"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Role Selection -->
                <div class="form-group">
                    <label for="role">Account Type</label>
                    <div class="input-wrapper">
                        <select id="role" name="role" class="form-control" required>
                            <option value="user" <?php echo ($formData['role'] ?? 'user') === 'user' ? 'selected' : ''; ?>>Student</option>
                            <option value="organizer" <?php echo ($formData['role'] ?? '') === 'organizer' ? 'selected' : ''; ?>>Event Organizer</option>
                        </select>
                        <i class="fas fa-user-tag input-icon"></i>
                    </div>
                </div>
                
                <!-- Terms and Conditions -->
                <div class="checkbox-group">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">
                        I agree to the <a href="#" onclick="showTerms()">Terms and Conditions</a> 
                        and <a href="#" onclick="showPrivacy()">Privacy Policy</a>
                    </label>
                </div>
                
                <!-- Submit Button -->
                <div class="form-group">
                    <button type="submit" name="register" class="btn-register" id="registerBtn">
                        <span>Create Account</span>
                        <i class="fas fa-spinner fa-spin loading" id="registerSpinner"></i>
                    </button>
                </div>
            </form>
            
            <!-- Login Link -->
            <div class="login-link">
                <p>Already have an account?</p>
                <a href="login.php">
                    <i class="fas fa-sign-in-alt"></i> Sign In Here
                </a>
            </div>
        </div>
    </div>

    <script>
        // 🚀 Enhanced Registration Form JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            const registerForm = document.getElementById('registerForm');
            const registerBtn = document.getElementById('registerBtn');
            const registerSpinner = document.getElementById('registerSpinner');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthBar = document.getElementById('strengthBar');
            const passwordMatch = document.getElementById('passwordMatch');
            
            // Password strength checker
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = checkPasswordStrength(password);
                updatePasswordStrength(strength);
                updatePasswordRequirements(password);
            });
            
            // Password confirmation checker
            confirmPasswordInput.addEventListener('input', function() {
                const password = passwordInput.value;
                const confirmPassword = this.value;
                
                if (confirmPassword.length > 0) {
                    if (password === confirmPassword) {
                        passwordMatch.innerHTML = '<i class="fas fa-check" style="color: #4caf50;"></i> <span style="color: #4caf50;">Passwords match</span>';
                    } else {
                        passwordMatch.innerHTML = '<i class="fas fa-times" style="color: #ff6b6b;"></i> <span style="color: #ff6b6b;">Passwords do not match</span>';
                    }
                } else {
                    passwordMatch.innerHTML = '';
                }
            });
            
            // Form submission with loading state
            registerForm.addEventListener('submit', function(e) {
                // Validate passwords match
                if (passwordInput.value !== confirmPasswordInput.value) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return;
                }
                
                // Show loading state
                registerBtn.disabled = true;
                registerSpinner.classList.add('show');
                registerBtn.querySelector('span').textContent = 'Creating Account...';
            });
            
            // Password strength function
            function checkPasswordStrength(password) {
                let strength = 0;
                
                if (password.length >= 6) strength += 1;
                if (password.match(/[a-z]/)) strength += 1;
                if (password.match(/[A-Z]/)) strength += 1;
                if (password.match(/[0-9]/)) strength += 1;
                if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
                
                return strength;
            }
            
            // Update password strength bar
            function updatePasswordStrength(strength) {
                strengthBar.className = 'password-strength-bar';
                
                switch(strength) {
                    case 0:
                    case 1:
                        strengthBar.classList.add('strength-weak');
                        break;
                    case 2:
                        strengthBar.classList.add('strength-fair');
                        break;
                    case 3:
                        strengthBar.classList.add('strength-good');
                        break;
                    case 4:
                    case 5:
                        strengthBar.classList.add('strength-strong');
                        break;
                }
            }
            
            // Update password requirements
            function updatePasswordRequirements(password) {
                const requirements = {
                    'req-length': password.length >= 6,
                    'req-number': /[0-9]/.test(password),
                    'req-letter': /[a-zA-Z]/.test(password)
                };
                
                Object.keys(requirements).forEach(reqId => {
                    const element = document.getElementById(reqId);
                    const icon = element.querySelector('i');
                    
                    if (requirements[reqId]) {
                        element.classList.add('met');
                        icon.className = 'fas fa-check';
                    } else {
                        element.classList.remove('met');
                        icon.className = 'fas fa-times';
                    }
                });
            }
            
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
            
            // Real-time validation
            const emailInput = document.getElementById('email');
            const usernameInput = document.getElementById('username');
            
            emailInput.addEventListener('blur', function() {
                if (this.value && !this.value.includes('@')) {
                    showFieldError(this, 'Please enter a valid email');
                }
            });
            
            usernameInput.addEventListener('blur', function() {
                if (this.value && this.value.length < 3) {
                    showFieldError(this, 'Username must be at least 3 characters');
                }
            });
            
            function showFieldError(field, message) {
                field.style.borderColor = '#ff6b6b';
                field.style.boxShadow = '0 0 0 3px rgba(255, 107, 107, 0.1)';
                
                // Remove error styling after user starts typing
                field.addEventListener('input', function() {
                    this.style.borderColor = '#e1e5e9';
                    this.style.boxShadow = 'none';
                }, { once: true });
            }
        });
        
        // Terms and Privacy functions
        function showTerms() {
            const modal = createModal('Terms and Conditions', `
                <div style="max-height: 400px; overflow-y: auto; padding: 20px;">
                    <h3>Terms and Conditions - EMS</h3>
                    <p><strong>Last updated: ${new Date().toLocaleDateString()}</strong></p>
                    
                    <h4>1. Acceptance of Terms</h4>
                    <p>By creating an account on the Ekwendeni Mighty Campus Event Management System, you agree to these terms.</p>
                    
                    <h4>2. User Responsibilities</h4>
                    <ul>
                        <li>Provide accurate and complete information</li>
                        <li>Maintain the security of your account</li>
                        <li>Use the system responsibly and ethically</li>
                        <li>Respect other users and event organizers</li>
                    </ul>
                    
                    <h4>3. Event Registration</h4>
                    <ul>
                        <li>Registration for events is subject to availability</li>
                        <li>Cancellation policies vary by event</li>
                        <li>You are responsible for attending registered events</li>
                    </ul>
                    
                    <h4>4. Privacy</h4>
                    <p>We respect your privacy and protect your personal information according to our Privacy Policy.</p>
                    
                    <h4>5. Prohibited Activities</h4>
                    <ul>
                        <li>Creating fake accounts or impersonating others</li>
                        <li>Spamming or sending unsolicited messages</li>
                        <li>Attempting to hack or disrupt the system</li>
                        <li>Posting inappropriate or offensive content</li>
                    </ul>
                    
                    <h4>6. Account Termination</h4>
                    <p>We reserve the right to suspend or terminate accounts that violate these terms.</p>
                    
                    <h4>7. Contact</h4>
                    <p>For questions about these terms, contact us at admin@unilia.ac.mw</p>
                </div>
            `);
        }
        
        function showPrivacy() {
            const modal = createModal('Privacy Policy', `
                <div style="max-height: 400px; overflow-y: auto; padding: 20px;">
                    <h3>Privacy Policy - EMS</h3>
                    <p><strong>Last updated: ${new Date().toLocaleDateString()}</strong></p>
                    
                    <h4>1. Information We Collect</h4>
                    <ul>
                        <li><strong>Personal Information:</strong> Name, email, phone number, department</li>
                        <li><strong>Account Information:</strong> Username, password (encrypted)</li>
                        <li><strong>Usage Data:</strong> Event registrations, system interactions</li>
                    </ul>
                    
                    <h4>2. How We Use Your Information</h4>
                    <ul>
                        <li>Manage your account and provide services</li>
                        <li>Send event notifications and updates</li>
                        <li>Improve our system and user experience</li>
                        <li>Communicate important announcements</li>
                    </ul>
                    
                    <h4>3. Information Sharing</h4>
                    <p>We do not sell or share your personal information with third parties, except:</p>
                    <ul>
                        <li>With event organizers for registered events</li>
                        <li>When required by law or university policy</li>
                        <li>To protect the safety and security of users</li>
                    </ul>
                    
                    <h4>4. Data Security</h4>
                    <p>We implement appropriate security measures to protect your information, including:</p>
                    <ul>
                        <li>Encrypted password storage</li>
                        <li>Secure data transmission</li>
                        <li>Regular security updates</li>
                        <li>Access controls and monitoring</li>
                    </ul>
                    
                    <h4>5. Your Rights</h4>
                    <ul>
                        <li>Access and update your personal information</li>
                        <li>Delete your account and associated data</li>
                        <li>Opt-out of non-essential communications</li>
                        <li>Request information about data usage</li>
                    </ul>
                    
                    <h4>6. Contact Us</h4>
                    <p>For privacy concerns, contact us at privacy@unilia.ac.mw</p>
                </div>
            `);
        }
        
        function createModal(title, content) {
            // Create modal overlay
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
                backdrop-filter: blur(5px);
            `;
            
            // Create modal content
            const modal = document.createElement('div');
            modal.style.cssText = `
                background: white;
                border-radius: 15px;
                max-width: 600px;
                width: 90%;
                max-height: 80vh;
                overflow: hidden;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            `;
            
            modal.innerHTML = `
                <div style="padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="margin: 0; color: #333;">${title}</h2>
                    <button onclick="this.closest('.modal-overlay').remove()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
                </div>
                ${content}
            `;
            
            overlay.className = 'modal-overlay';
            overlay.appendChild(modal);
            document.body.appendChild(overlay);
            
            // Close on overlay click
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    overlay.remove();
                }
            });
            
            return overlay;
        }
        
        // 🎨 Additional animations and effects
        window.addEventListener('load', function() {
            // Animate register container entrance
            const container = document.querySelector('.register-container');
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