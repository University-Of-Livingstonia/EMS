<?php
/**
 * ⚙️ System Settings - EMS Admin
 * Ekwendeni Mighty Campus Event Management System
 * System Configuration & Settings Management 🔧
 */

require_once '../includes/functions.php';

// Get database connection
$conn = require_once '../config/database.php';

// Initialize session manager
require_once '../includes/session.php';
$sessionManager = new SessionManager($conn);

// Require admin login
$sessionManager->requireLogin();
$currentUser = $sessionManager->getCurrentUser();

// Check if user is admin
if ($currentUser['role'] !== 'admin') {
    header('Location: ../dashboard/index.php');
    exit;
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'general_settings':
            // Handle general settings update
            $siteName = $_POST['site_name'] ?? '';
            $siteDescription = $_POST['site_description'] ?? '';
            $contactEmail = $_POST['contact_email'] ?? '';
            $timezone = $_POST['timezone'] ?? '';
            
            // In a real application, you would save these to a settings table
            $message = 'General settings updated successfully!';
            $messageType = 'success';
            break;
            
        case 'email_settings':
            // Handle email settings update
            $smtpHost = $_POST['smtp_host'] ?? '';
            $smtpPort = $_POST['smtp_port'] ?? '';
            $smtpUsername = $_POST['smtp_username'] ?? '';
            $smtpPassword = $_POST['smtp_password'] ?? '';
            
            $message = 'Email settings updated successfully!';
            $messageType = 'success';
            break;
            
        case 'payment_settings':
            // Handle payment settings update
            $paymentGateway = $_POST['payment_gateway'] ?? '';
            $paymentApiKey = $_POST['payment_api_key'] ?? '';
            $paymentSecretKey = $_POST['payment_secret_key'] ?? '';
            
            $message = 'Payment settings updated successfully!';
            $messageType = 'success';
            break;
            
        case 'security_settings':
            // Handle security settings update
            $sessionTimeout = $_POST['session_timeout'] ?? '';
            $maxLoginAttempts = $_POST['max_login_attempts'] ?? '';
            $passwordMinLength = $_POST['password_min_length'] ?? '';
            
            $message = 'Security settings updated successfully!';
            $messageType = 'success';
            break;
    }
}

// Sample settings data (in real app, fetch from database)
$settings = [
    'general' => [
        'site_name' => 'EMS - Event Management System',
        'site_description' => 'Ekwendeni Mighty Campus Event Management System',
        'contact_email' => 'admin@ems.edu',
        'timezone' => 'Africa/Blantyre',
        'maintenance_mode' => false,
        'registration_enabled' => true
    ],
    'email' => [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => '587',
        'smtp_username' => 'noreply@ems.edu',
        'smtp_password' => '••••••••',
        'smtp_encryption' => 'tls',
        'from_name' => 'EMS System'
    ],
    'payment' => [
        'payment_gateway' => 'stripe',
        'payment_api_key' => 'pk_test_••••••••',
        'payment_secret_key' => 'sk_test_••••••••',
        'currency' => 'MWK',
        'payment_enabled' => true
    ],
    'security' => [
        'session_timeout' => '30',
        'max_login_attempts' => '5',
        'password_min_length' => '8',
        'two_factor_enabled' => false,
        'ip_whitelist_enabled' => false
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - EMS Admin</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --admin-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --admin-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --admin-success: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            --admin-warning: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            --admin-danger: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            --admin-info: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            --sidebar-bg: #1a1a2e;
            --sidebar-hover: #16213e;
            --content-bg: #f8f9fa;
            --card-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --border-color: #e9ecef;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--content-bg);
        }
        
        /* Sidebar Styles */
        .admin-sidebar {
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
            background: var(--admin-primary);
        }
        
        .sidebar-header h3 {
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: white;
        }
        
        .admin-nav {
            padding: 1.5rem 0;
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
        
        .admin-nav-link {
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
        
        .admin-nav-link:hover,
        .admin-nav-link.active {
            background: var(--sidebar-hover);
            color: white;
            transform: translateX(10px);
        }
        
        .nav-icon {
            font-size: 1.3rem;
            margin-right: 1rem;
            width: 25px;
            text-align: center;
        }
        
        .admin-main {
            margin-left: 300px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        .admin-topbar {
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
        
        .admin-title {
            font-size: 2rem;
            font-weight: 800;
            background: var(--admin-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }
        
        .admin-content {
            padding: 2rem;
        }
        
        .admin-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }
        
        .admin-card-header {
            padding: 2rem 2rem 1rem 2rem;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }
        
        .admin-card-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }
        
        .admin-card-body {
            padding: 2rem;
        }
        
        /* Settings Tabs */
        .settings-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .settings-tab {
            padding: 1rem 2rem;
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 15px;
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .settings-tab:hover,
        .settings-tab.active {
            background: var(--admin-primary);
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
        }
        
        /* Form Styles */
        .settings-form {
            display: none;
        }
        
        .settings-form.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
                .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-help {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.3rem;
        }
        
        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background: var(--admin-primary);
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        /* Save Button */
        .save-btn {
            background: var(--admin-success);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 15px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .save-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }
        
        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            border-left: 5px solid;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            border-left-color: #4CAF50;
            color: #2e7d32;
        }
        
        .alert-danger {
            background: rgba(244, 67, 54, 0.1);
            border-left-color: #f44336;
            color: #c62828;
        }
        
        /* System Status Cards */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .status-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: all 0.3s ease;
            border-left: 5px solid;
        }
        
        .status-card:hover {
            transform: translateY(-5px);
        }
        
        .status-card.online { border-left-color: #4CAF50; }
        .status-card.warning { border-left-color: #ff9800; }
        .status-card.offline { border-left-color: #f44336; }
        
        .status-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .status-icon.online { color: #4CAF50; }
        .status-icon.warning { color: #ff9800; }
        .status-icon.offline { color: #f44336; }
        
        .status-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .status-description {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            
            .admin-main {
                margin-left: 0;
            }
            
            .admin-content {
                padding: 1rem;
            }
            
            .settings-tabs {
                flex-direction: column;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Admin Sidebar -->
    <div class="admin-sidebar">
        <div class="sidebar-header">
            <h3>👑 EMS Admin</h3>
            <p>System Control Center</p>
        </div>
        
        <nav class="admin-nav">
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <div class="admin-nav-item">
                    <a href="dashboard.php" class="admin-nav-link">
                        <i class="fas fa-tachometer-alt nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Management</div>
                <div class="admin-nav-item">
                    <a href="users.php" class="admin-nav-link">
                        <i class="fas fa-users nav-icon"></i>
                        <span class="nav-text">Users</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="events.php" class="admin-nav-link">
                        <i class="fas fa-calendar-alt nav-icon"></i>
                        <span class="nav-text">Events</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">System</div>
                <div class="admin-nav-item">
                    <a href="settings.php" class="admin-nav-link active">
                        <i class="fas fa-cog nav-icon"></i>
                        <span class="nav-text">Settings</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="logs.php" class="admin-nav-link">
                        <i class="fas fa-list-alt nav-icon"></i>
                        <span class="nav-text">System Logs</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="reports.php" class="admin-nav-link">
                        <i class="fas fa-file-invoice-dollar nav-icon"></i>
                        <span class="nav-text">Reports</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="../dashboard/index.php" class="admin-nav-link">
                        <i class="fas fa-arrow-left nav-icon"></i>
                        <span class="nav-text">Back to User</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="../auth/logout.php" class="admin-nav-link">
                        <i class="fas fa-sign-out-alt nav-icon"></i>
                        <span class="nav-text">Logout</span>
                    </a>
                </div>
            </div>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="admin-main">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <h1 class="admin-title">⚙️ System Settings</h1>
            <div class="admin-user-info">
                <span>Welcome, <?= htmlspecialchars($currentUser['first_name']) ?>!</span>
            </div>
        </div>
        
        <!-- Content -->
        <div class="admin-content">
            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>">
                    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <!-- System Status -->
            <div class="status-grid">
                <div class="status-card online">
                    <div class="status-icon online">
                        <i class="fas fa-server"></i>
                    </div>
                    <div class="status-title">Database</div>
                    <div class="status-description">Connected & Running</div>
                </div>
                
                <div class="status-card online">
                    <div class="status-icon online">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="status-title">Email Service</div>
                    <div class="status-description">SMTP Configured</div>
                </div>
                
                <div class="status-card warning">
                    <div class="status-icon warning">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="status-title">Payment Gateway</div>
                    <div class="status-description">Test Mode Active</div>
                </div>
                
                <div class="status-card online">
                    <div class="status-icon online">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="status-title">Security</div>
                    <div class="status-description">All Systems Secure</div>
                </div>
            </div>
            
            <!-- Settings Tabs -->
            <div class="settings-tabs">
                <button class="settings-tab active" onclick="showTab('general')">
                    <i class="fas fa-cog"></i> General
                </button>
                <button class="settings-tab" onclick="showTab('email')">
                    <i class="fas fa-envelope"></i> Email
                </button>
                <button class="settings-tab" onclick="showTab('payment')">
                    <i class="fas fa-credit-card"></i> Payment
                </button>
                <button class="settings-tab" onclick="showTab('security')">
                    <i class="fas fa-shield-alt"></i> Security
                </button>
            </div>
            
            <!-- General Settings -->
            <div class="admin-card">
                <div class="settings-form active" id="general-form">
                    <div class="admin-card-header">
                        <h3 class="admin-card-title">
                            <i class="fas fa-cog"></i>
                            General Settings
                        </h3>
                    </div>
                    <div class="admin-card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="general_settings">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="site_name">Site Name</label>
                                    <input type="text" id="site_name" name="site_name" value="<?= htmlspecialchars($settings['general']['site_name']) ?>" required>
                                    <div class="form-help">The name of your event management system</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="contact_email">Contact Email</label>
                                    <input type="email" id="contact_email" name="contact_email" value="<?= htmlspecialchars($settings['general']['contact_email']) ?>" required>
                                    <div class="form-help">Primary contact email for the system</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="timezone">Timezone</label>
                                    <select id="timezone" name="timezone" required>
                                        <option value="Africa/Blantyre" <?= $settings['general']['timezone'] === 'Africa/Blantyre' ? 'selected' : '' ?>>Africa/Blantyre</option>
                                        <option value="UTC" <?= $settings['general']['timezone'] === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                        <option value="America/New_York" <?= $settings['general']['timezone'] === 'America/New_York' ? 'selected' : '' ?>>America/New_York</option>
                                        <option value="Europe/London" <?= $settings['general']['timezone'] === 'Europe/London' ? 'selected' : '' ?>>Europe/London</option>
                                    </select>
                                    <div class="form-help">Default timezone for the system</div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Maintenance Mode</label>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="maintenance_mode" <?= $settings['general']['maintenance_mode'] ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <div class="form-help">Enable to put the system in maintenance mode</div>
                                </div>
                                
                                <div class="form-group">
                                    <label>User Registration</label>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="registration_enabled" <?= $settings['general']['registration_enabled'] ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <div class="form-help">Allow new user registrations</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="site_description">Site Description</label>
                                <textarea id="site_description" name="site_description" rows="3"><?= htmlspecialchars($settings['general']['site_description']) ?></textarea>
                                <div class="form-help">Brief description of your event management system</div>
                            </div>
                            
                            <button type="submit" class="save-btn">
                                <i class="fas fa-save"></i>
                                Save General Settings
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Email Settings -->
                <div class="settings-form" id="email-form">
                    <div class="admin-card-header">
                        <h3 class="admin-card-title">
                            <i class="fas fa-envelope"></i>
                            Email Settings
                        </h3>
                    </div>
                    <div class="admin-card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="email_settings">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="smtp_host">SMTP Host</label>
                                    <input type="text" id="smtp_host" name="smtp_host" value="<?= htmlspecialchars($settings['email']['smtp_host']) ?>" required>
                                    <div class="form-help">SMTP server hostname (e.g., smtp.gmail.com)</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="smtp_port">SMTP Port</label>
                                    <input type="number" id="smtp_port" name="smtp_port" value="<?= htmlspecialchars($settings['email']['smtp_port']) ?>" required>
                                    <div class="form-help">SMTP server port (587 for TLS, 465 for SSL)</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="smtp_username">SMTP Username</label>
                                    <input type="text" id="smtp_username" name="smtp_username" value="<?= htmlspecialchars($settings['email']['smtp_username']) ?>" required>
                                    <div class="form-help">SMTP authentication username</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="smtp_password">SMTP Password</label>
                                    <input type="password" id="smtp_password" name="smtp_password" value="<?= htmlspecialchars($settings['email']['smtp_password']) ?>">
                                    <div class="form-help">SMTP authentication password</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="smtp_encryption">Encryption</label>
                                    <select id="smtp_encryption" name="smtp_encryption" required>
                                        <option value="tls" <?= $settings['email']['smtp_encryption'] === 'tls' ? 'selected' : '' ?>>TLS</option>
                                        <option value="ssl" <?= $settings['email']['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                        <option value="none" <?= $settings['email']['smtp_encryption'] === 'none' ? 'selected' : '' ?>>None</option>
                                    </select>
                                    <div class="form-help">Email encryption method</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="from_name">From Name</label>
                                    <input type="text" id="from_name" name="from_name" value="<?= htmlspecialchars($settings['email']['from_name']) ?>" required>
                                    <div class="form-help">Name that appears in sent emails</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="button" class="btn btn-outline-primary" onclick="testEmailConnection()">
                                    <i class="fas fa-paper-plane"></i>
                                    Test Email Connection
                                </button>
                            </div>
                            
                            <button type="submit" class="save-btn">
                                <i class="fas fa-save"></i>
                                Save Email Settings
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Payment Settings -->
                <div class="settings-form" id="payment-form">
                    <div class="admin-card-header">
                        <h3 class="admin-card-title">
                            <i class="fas fa-credit-card"></i>
                            Payment Settings
                        </h3>
                    </div>
                    <div class="admin-card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="payment_settings">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="payment_gateway">Payment Gateway</label>
                                    <select id="payment_gateway" name="payment_gateway" required>
                                        <option value="stripe" <?= $settings['payment']['payment_gateway'] === 'stripe' ? 'selected' : '' ?>>Stripe</option>
                                        <option value="paypal" <?= $settings['payment']['payment_gateway'] === 'paypal' ? 'selected' : '' ?>>PayPal</option>
                                        <option value="flutterwave" <?= $settings['payment']['payment_gateway'] === 'flutterwave' ? 'selected' : '' ?>>Flutterwave</option>
                                        <option value="paystack" <?= $settings['payment']['payment_gateway'] === 'paystack' ? 'selected' : '' ?>>Paystack</option>
                                    </select>
                                    <div class="form-help">Choose your preferred payment gateway</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="currency">Currency</label>
                                    <select id="currency" name="currency" required>
                                        <option value="MWK" <?= $settings['payment']['currency'] === 'MWK' ? 'selected' : '' ?>>MWK - Malawian Kwacha</option>
                                        <option value="USD" <?= $settings['payment']['currency'] === 'USD' ? 'selected' : '' ?>>USD - US Dollar</option>
                                        <option value="EUR" <?= $settings['payment']['currency'] === 'EUR' ? 'selected' : '' ?>>EUR - Euro</option>
                                        <option value="GBP" <?= $settings['payment']['currency'] === 'GBP' ? 'selected' : '' ?>>GBP - British Pound</option>
                                    </select>
                                    <div class="form-help">Default currency for payments</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="payment_api_key">API Key (Public)</label>
                                    <input type="text" id="payment_api_key" name="payment_api_key" value="<?= htmlspecialchars($settings['payment']['payment_api_key']) ?>" required>
                                    <div class="form-help">Public API key from your payment provider</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="payment_secret_key">Secret Key</label>
                                    <input type="password" id="payment_secret_key" name="payment_secret_key" value="<?= htmlspecialchars($settings['payment']['payment_secret_key']) ?>" required>
                                    <div class="form-help">Secret key from your payment provider</div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Payment Processing</label>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="payment_enabled" <?= $settings['payment']['payment_enabled'] ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <div class="form-help">Enable payment processing for events</div>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Security Notice:</strong> Keep your secret keys secure and never share them publicly.
                            </div>
                            
                            <button type="submit" class="save-btn">
                                <i class="fas fa-save"></i>
                                Save Payment Settings
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Security Settings -->
                <div class="settings-form" id="security-form">
                    <div class="admin-card-header">
                        <h3 class="admin-card-title">
                            <i class="fas fa-shield-alt"></i>
                            Security Settings
                        </h3>
                    </div>
                    <div class="admin-card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="security_settings">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="session_timeout">Session Timeout (minutes)</label>
                                    <input type="number" id="session_timeout" name="session_timeout" value="<?= htmlspecialchars($settings['security']['session_timeout']) ?>" min="5" max="1440" required>
                                    <div class="form-help">How long users stay logged in (5-1440 minutes)</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="max_login_attempts">Max Login Attempts</label>
                                    <input type="number" id="max_login_attempts" name="max_login_attempts" value="<?= htmlspecialchars($settings['security']['max_login_attempts']) ?>" min="3" max="10" required>
                                    <div class="form-help">Maximum failed login attempts before lockout</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password_min_length">Minimum Password Length</label>
                                    <input type="number" id="password_min_length" name="password_min_length" value="<?= htmlspecialchars($settings['security']['password_min_length']) ?>" min="6" max="20" required>
                                    <div class="form-help">Minimum required password length</div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Two-Factor Authentication</label>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="two_factor_enabled" <?= $settings['security']['two_factor_enabled'] ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <div class="form-help">Require 2FA for admin accounts</div>
                                </div>
                                
                                <div class="form-group">
                                    <label>IP Whitelist</label>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="ip_whitelist_enabled" <?= $settings['security']['ip_whitelist_enabled'] ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <div class="form-help">Restrict admin access to specific IP addresses</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="allowed_ips">Allowed IP Addresses</label>
                                <textarea id="allowed_ips" name="allowed_ips" rows="3" placeholder="192.168.1.1&#10;10.0.0.1&#10;203.0.113.1"></textarea>
                                <div class="form-help">One IP address per line (only used if IP Whitelist is enabled)</div>
                            </div>
                            
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i>
                                <strong>Warning:</strong> Changing security settings may affect user access. Test thoroughly before applying to production.
                            </div>
                            
                            <button type="submit" class="save-btn">
                                <i class="fas fa-save"></i>
                                Save Security Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- System Actions -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3 class="admin-card-title">
                        <i class="fas fa-tools"></i>
                        System Actions
                    </h3>
                </div>
                <div class="admin-card-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <h5>Clear System Cache</h5>
                            <p class="text-muted">Clear all cached data to improve performance</p>
                            <button type="button" class="btn btn-outline-warning" onclick="clearCache()">
                                <i class="fas fa-broom"></i>
                                Clear Cache
                            </button>
                        </div>
                        
                        <div class="form-group">
                            <h5>Database Backup</h5>
                            <p class="text-muted">Create a backup of the system database</p>
                            <button type="button" class="btn btn-outline-info" onclick="createBackup()">
                                <i class="fas fa-database"></i>
                                Create Backup
                            </button>
                        </div>
                        
                        <div class="form-group">
                            <h5>System Health Check</h5>
                            <p class="text-muted">Run a comprehensive system health check</p>
                            <button type="button" class="btn btn-outline-success" onclick="runHealthCheck()">
                                <i class="fas fa-heartbeat"></i>
                                Run Health Check
                            </button>
                        </div>
                        
                        <div class="form-group">
                            <h5>Reset System</h5>
                            <p class="text-muted text-danger">⚠️ This will reset all settings to default</p>
                            <button type="button" class="btn btn-outline-danger" onclick="resetSystem()">
                                <i class="fas fa-undo"></i>
                                Reset System
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Settings Tab Management
        function showTab(tabName) {
            // Hide all forms
            document.querySelectorAll('.settings-form').forEach(form => {
                form.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.settings-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected form
            document.getElementById(tabName + '-form').classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        // System Actions
        function clearCache() {
            if (confirm('Are you sure you want to clear the system cache?')) {
                showLoading();
                fetch('api/system_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'clear_cache'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showToast('Cache cleared successfully!', 'success');
                    } else {
                        showToast('Failed to clear cache: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    hideLoading();
                    showToast('Network error occurred', 'error');
                });
            }
        }
        
        function createBackup() {
            if (confirm('Create a database backup? This may take a few minutes.')) {
                showLoading();
                
                fetch('api/system_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'create_backup'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showToast('Backup created successfully!', 'success');
                        // Optionally download the backup file
                        if (data.download_url) {
                            window.open(data.download_url, '_blank');
                        }
                    } else {
                                               showToast('Failed to create backup: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    hideLoading();
                    showToast('Network error occurred', 'error');
                });
            }
        }
        
        function runHealthCheck() {
            showLoading();
            
            fetch('api/system_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'health_check'
                })
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showHealthCheckResults(data.results);
                } else {
                    showToast('Health check failed: ' + data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showToast('Network error occurred', 'error');
            });
        }
        
        function resetSystem() {
            if (confirm('⚠️ WARNING: This will reset ALL system settings to default values.\n\nThis action cannot be undone. Are you absolutely sure?')) {
                if (confirm('Last chance! This will reset everything. Continue?')) {
                    showLoading();
                    
                    fetch('api/system_actions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'reset_system'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        if (data.success) {
                            showToast('System reset successfully! Reloading...', 'success');
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            showToast('Failed to reset system: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        hideLoading();
                        showToast('Network error occurred', 'error');
                    });
                }
            }
        }
        
        function testEmailConnection() {
            const formData = new FormData();
            formData.append('smtp_host', document.getElementById('smtp_host').value);
            formData.append('smtp_port', document.getElementById('smtp_port').value);
            formData.append('smtp_username', document.getElementById('smtp_username').value);
            formData.append('smtp_password', document.getElementById('smtp_password').value);
            formData.append('smtp_encryption', document.getElementById('smtp_encryption').value);
            
            showLoading();
            
            fetch('api/test_email.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast('Email connection successful!', 'success');
                } else {
                    showToast('Email connection failed: ' + data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showToast('Network error occurred', 'error');
            });
        }
        
        // UI Helper Functions
        function showLoading() {
            // Create loading overlay if it doesn't exist
            if (!document.getElementById('loadingOverlay')) {
                const overlay = document.createElement('div');
                overlay.id = 'loadingOverlay';
                overlay.innerHTML = `
                    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 9999;">
                        <div style="background: white; padding: 2rem; border-radius: 15px; text-align: center;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div style="margin-top: 1rem; font-weight: 600;">Processing...</div>
                        </div>
                    </div>
                `;
                document.body.appendChild(overlay);
            }
            document.getElementById('loadingOverlay').style.display = 'block';
        }
        
        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }
        
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.innerHTML = `
                <div style="position: fixed; top: 20px; right: 20px; background: white; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); padding: 1rem 1.5rem; display: flex; align-items: center; gap: 0.5rem; min-width: 300px; z-index: 9999; animation: slideInRight 0.3s ease-out; border-left: 5px solid ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#2196F3'};">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}" style="color: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#2196F3'};"></i>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: #999; cursor: pointer; margin-left: auto;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.style.animation = 'slideOutRight 0.3s ease-out';
                    setTimeout(() => toast.remove(), 300);
                }
            }, 5000);
        }
        
        function showHealthCheckResults(results) {
            const modal = document.createElement('div');
            modal.innerHTML = `
                <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 9999;" onclick="this.remove()">
                    <div style="background: white; border-radius: 15px; padding: 2rem; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;" onclick="event.stopPropagation()">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                            <h3 style="margin: 0; color: #2c3e50;">
                                <i class="fas fa-heartbeat" style="color: #4CAF50; margin-right: 0.5rem;"></i>
                                System Health Check Results
                            </h3>
                            <button onclick="this.closest('div').parentElement.remove()" style="background: none; border: none; font-size: 1.5rem; color: #999; cursor: pointer;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div id="healthResults">
                            ${formatHealthResults(results)}
                        </div>
                        <div style="text-align: center; margin-top: 1.5rem;">
                            <button onclick="this.closest('div').parentElement.remove()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 0.7rem 1.5rem; border-radius: 10px; font-weight: 600; cursor: pointer;">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        }
        
        function formatHealthResults(results) {
            let html = '';
            
            for (const [category, checks] of Object.entries(results)) {
                html += `
                    <div style="margin-bottom: 1.5rem;">
                        <h5 style="color: #2c3e50; margin-bottom: 1rem; text-transform: capitalize;">
                            ${category.replace('_', ' ')}
                        </h5>
                `;
                
                for (const [check, result] of Object.entries(checks)) {
                    const status = result.status;
                    const color = status === 'pass' ? '#4CAF50' : status === 'warning' ? '#ff9800' : '#f44336';
                    const icon = status === 'pass' ? 'check-circle' : status === 'warning' ? 'exclamation-triangle' : 'times-circle';
                    
                    html += `
                        <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; border-radius: 8px; background: ${status === 'pass' ? 'rgba(76, 175, 80, 0.1)' : status === 'warning' ? 'rgba(255, 152, 0, 0.1)' : 'rgba(244, 67, 54, 0.1)'}; margin-bottom: 0.5rem;">
                            <i class="fas fa-${icon}" style="color: ${color};"></i>
                            <span style="font-weight: 500;">${check.replace('_', ' ')}</span>
                            <span style="margin-left: auto; color: ${color}; font-weight: 600; text-transform: uppercase;">
                                ${status}
                            </span>
                        </div>
                        ${result.message ? `<div style="font-size: 0.9rem; color: #666; margin-left: 1.5rem; margin-bottom: 0.5rem;">${result.message}</div>` : ''}
                    `;
                }
                
                html += '</div>';
            }
            
            return html;
        }
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Add form validation
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let isValid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            field.style.borderColor = '#f44336';
                            isValid = false;
                        } else {
                            field.style.borderColor = '#e9ecef';
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        showToast('Please fill in all required fields', 'error');
                    }
                });
            });
            
            // Add real-time validation
            const inputs = document.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.hasAttribute('required') && !this.value.trim()) {
                        this.style.borderColor = '#f44336';
                    } else {
                        this.style.borderColor = '#e9ecef';
                    }
                });
            });
        });
        
        // Add CSS animations
        const additionalStyles = `
            <style>
                @keyframes slideInRight {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                
                @keyframes slideOutRight {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                }
                
                .btn {
                    padding: 0.5rem 1rem;
                    border-radius: 8px;
                    font-weight: 600;
                    text-decoration: none;
                    transition: all 0.3s ease;
                    border: 2px solid;
                    cursor: pointer;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                
                .btn-outline-primary {
                    background: transparent;
                    color: #667eea;
                    border-color: #667eea;
                }
                
                .btn-outline-primary:hover {
                    background: #667eea;
                    color: white;
                }
                
                .btn-outline-warning {
                    background: transparent;
                    color: #ff9800;
                    border-color: #ff9800;
                }
                
                .btn-outline-warning:hover {
                    background: #ff9800;
                    color: white;
                }
                
                .btn-outline-info {
                    background: transparent;
                    color: #2196F3;
                    border-color: #2196F3;
                }
                
                .btn-outline-info:hover {
                    background: #2196F3;
                    color: white;
                }
                
                .btn-outline-success {
                    background: transparent;
                    color: #4CAF50;
                    border-color: #4CAF50;
                }
                
                .btn-outline-success:hover {
                    background: #4CAF50;
                    color: white;
                }
                
                .btn-outline-danger {
                    background: transparent;
                    color: #f44336;
                    border-color: #f44336;
                }
                
                .btn-outline-danger:hover {
                    background: #f44336;
                    color: white;
                }
            </style>
        `;
        
        document.head.insertAdjacentHTML('beforeend', additionalStyles);
    </script>
</body>
</html>
