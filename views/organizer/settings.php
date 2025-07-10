<?php
/**
 * ⚙️ Organizer Settings - EMS
 * Ekwendeni Mighty Campus Event Management System
 * Customize Your Experience! 🎛️
 */

require_once '../../includes/functions.php';

// Get database connection
$conn = require_once '../../config/database.php';

// Initialize session manager
require_once '../../includes/session.php';
$sessionManager = new SessionManager($conn);

// Require organizer login
$sessionManager->requireLogin();
$currentUser = $sessionManager->getCurrentUser();

// Check if user is organizer
if (!in_array($currentUser['role'], ['organizer', 'admin'])) {
    header('Location: ../../dashboard/index.php');
    exit;
}

$message = '';
$messageType = '';

// Handle settings update
if ($_POST && isset($_POST['update_settings'])) {
    try {
        $settings = [
            'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
            'sms_notifications' => isset($_POST['sms_notifications']) ? 1 : 0,
            'event_reminders' => isset($_POST['event_reminders']) ? 1 : 0,
            'marketing_emails' => isset($_POST['marketing_emails']) ? 1 : 0,
            'auto_approve_registrations' => isset($_POST['auto_approve_registrations']) ? 1 : 0,
            'require_payment_confirmation' => isset($_POST['require_payment_confirmation']) ? 1 : 0,
            'default_event_capacity' => intval($_POST['default_event_capacity']),
            'default_ticket_price' => floatval($_POST['default_ticket_price']),
            'timezone' => $_POST['timezone'],
            'language' => $_POST['language'],
            'currency' => $_POST['currency']
        ];
        
        // Update or insert user settings
        $stmt = $conn->prepare("
            INSERT INTO user_settings (user_id, setting_key, setting_value, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        
        foreach ($settings as $key => $value) {
            $stmt->bind_param("iss", $currentUser['user_id'], $key, $value);
            $stmt->execute();
        }
        
        $message = 'Settings updated successfully!';
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = 'Error updating settings: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get current settings
$userSettings = [];
try {
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?");
    $stmt->bind_param("i", $currentUser['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $userSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    error_log("Settings fetch error: " . $e->getMessage());
}

// Default values
$defaults = [
    'email_notifications' => 1,
    'sms_notifications' => 0,
    'event_reminders' => 1,
    'marketing_emails' => 0,
    'auto_approve_registrations' => 0,
    'require_payment_confirmation' => 1,
    'default_event_capacity' => 100,
    'default_ticket_price' => 0,
    'timezone' => 'Africa/Blantyre',
    'language' => 'en',
    'currency' => 'MWK'
];

// Merge with user settings
foreach ($defaults as $key => $value) {
    if (!isset($userSettings[$key])) {
        $userSettings[$key] = $value;
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Organizer | EMS</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            --success-gradient: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            --warning-gradient: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            --danger-gradient: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            --info-gradient: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            --light-bg: #f8f9fa;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 20px 40px rgba(0, 0, 0, 0.15);
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
        }
        
        /* 🎨 Header */
        .page-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .breadcrumb-nav {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 25px;
            margin-top: 1rem;
        }
        
        .breadcrumb-nav a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .breadcrumb-nav a:hover {
            color: white;
        }
        
        /* ⚙️ Settings Container */
        .settings-container {
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .settings-header {
            background: var(--primary-gradient);
            color: white;
            padding: 1.5rem;
        }
        
        .settings-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .settings-body {
            padding: 2rem;
        }
        
        .settings-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .settings-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-description {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        
        /* 🎛️ Form Controls */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-control, .form-select {
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        /* 🔘 Toggle Switches */
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
        
        .slider {
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
        
        .slider:before {
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
        
        input:checked + .slider {
            background: var(--primary-gradient);
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .toggle-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .toggle-item:hover {
            background: #f8f9fa;
            border-color: #667eea;
        }
        
        .toggle-info {
            flex: 1;
        }
        
        .toggle-title {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.2rem;
        }
        
        .toggle-description {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        /* 💾 Save Button */
        .btn-save {
            padding: 0.75rem 2rem;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-reset {
            padding: 0.75rem 2rem;
            background: transparent;
            color: var(--text-muted);
            border: 2px solid var(--border-color);
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-left: 1rem;
        }
        
        .btn-reset:hover {
            border-color: #f44336;
            color: #f44336;
        }
        
        /* 🚨 Alert */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: none;
        }
        
        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border-left: 4px solid #4CAF50;
        }
        
        .alert-danger {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border-left: 4px solid #f44336;
        }
        
        /* 📱 Responsive */
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .settings-body {
                padding: 1rem;
            }
            
            .toggle-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .btn-reset {
                margin-left: 0;
                margin-top: 0.5rem;
            }
        }
        
        /* 🎨 Animations */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="page-title">⚙️ Settings</h1>
                    <p class="page-subtitle">Customize your organizer experience</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="breadcrumb-nav">
                        <a href="../../dashboard/">Dashboard</a> / 
                        <a href="dashboard.php">Organizer</a> / 
                        <span>Settings</span>
                    </div>
                </div>
                            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> fade-in">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="fade-in">
            <!-- Notification Settings -->
            <div class="settings-container">
                <div class="settings-header">
                    <h5 class="settings-title">
                        <i class="fas fa-bell"></i>
                        Notification Preferences
                    </h5>
                </div>
                <div class="settings-body">
                    <div class="section-description">
                        Choose how you want to receive notifications about your events and activities.
                    </div>
                    
                    <div class="toggle-item">
                        <div class="toggle-info">
                            <div class="toggle-title">Email Notifications</div>
                            <div class="toggle-description">Receive email updates about registrations, payments, and event changes</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="email_notifications" <?= $userSettings['email_notifications'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="toggle-item">
                        <div class="toggle-info">
                            <div class="toggle-title">SMS Notifications</div>
                            <div class="toggle-description">Get text messages for urgent updates and reminders</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="sms_notifications" <?= $userSettings['sms_notifications'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="toggle-item">
                        <div class="toggle-info">
                            <div class="toggle-title">Event Reminders</div>
                            <div class="toggle-description">Receive reminders before your events start</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="event_reminders" <?= $userSettings['event_reminders'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="toggle-item">
                        <div class="toggle-info">
                            <div class="toggle-title">Marketing Emails</div>
                            <div class="toggle-description">Receive tips, updates, and promotional content</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="marketing_emails" <?= $userSettings['marketing_emails'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Event Management Settings -->
            <div class="settings-container">
                <div class="settings-header">
                    <h5 class="settings-title">
                        <i class="fas fa-calendar-alt"></i>
                        Event Management
                    </h5>
                </div>
                <div class="settings-body">
                    <div class="section-description">
                        Configure default settings for your events and registration process.
                    </div>
                    
                    <div class="toggle-item">
                        <div class="toggle-info">
                            <div class="toggle-title">Auto-approve Registrations</div>
                            <div class="toggle-description">Automatically approve new registrations without manual review</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="auto_approve_registrations" <?= $userSettings['auto_approve_registrations'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="toggle-item">
                        <div class="toggle-info">
                            <div class="toggle-title">Require Payment Confirmation</div>
                            <div class="toggle-description">Require payment before confirming event registration</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="require_payment_confirmation" <?= $userSettings['require_payment_confirmation'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Default Event Capacity</label>
                                <input type="number" name="default_event_capacity" class="form-control" 
                                       value="<?= htmlspecialchars($userSettings['default_event_capacity']) ?>" 
                                       min="1" max="10000">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Default Ticket Price (MWK)</label>
                                <input type="number" name="default_ticket_price" class="form-control" 
                                       value="<?= htmlspecialchars($userSettings['default_ticket_price']) ?>" 
                                       min="0" step="0.01">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Regional Settings -->
            <div class="settings-container">
                <div class="settings-header">
                    <h5 class="settings-title">
                        <i class="fas fa-globe"></i>
                        Regional Settings
                    </h5>
                </div>
                <div class="settings-body">
                    <div class="section-description">
                        Set your timezone, language, and currency preferences.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Timezone</label>
                                <select name="timezone" class="form-select">
                                    <option value="Africa/Blantyre" <?= $userSettings['timezone'] === 'Africa/Blantyre' ? 'selected' : '' ?>>Africa/Blantyre</option>
                                    <option value="Africa/Harare" <?= $userSettings['timezone'] === 'Africa/Harare' ? 'selected' : '' ?>>Africa/Harare</option>
                                    <option value="Africa/Johannesburg" <?= $userSettings['timezone'] === 'Africa/Johannesburg' ? 'selected' : '' ?>>Africa/Johannesburg</option>
                                    <option value="UTC" <?= $userSettings['timezone'] === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Language</label>
                                <select name="language" class="form-select">
                                    <option value="en" <?= $userSettings['language'] === 'en' ? 'selected' : '' ?>>English</option>
                                    <option value="ny" <?= $userSettings['language'] === 'ny' ? 'selected' : '' ?>>Chichewa</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Currency</label>
                                <select name="currency" class="form-select">
                                    <option value="MWK" <?= $userSettings['currency'] === 'MWK' ? 'selected' : '' ?>>Malawian Kwacha (MWK)</option>
                                    <option value="USD" <?= $userSettings['currency'] === 'USD' ? 'selected' : '' ?>>US Dollar (USD)</option>
                                    <option value="ZAR" <?= $userSettings['currency'] === 'ZAR' ? 'selected' : '' ?>>South African Rand (ZAR)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Settings -->
            <div class="text-center">
                <button type="submit" name="update_settings" class="btn-save">
                    <i class="fas fa-save"></i>
                    Save Settings
                </button>
                <button type="reset" class="btn-reset">
                    <i class="fas fa-undo"></i>
                    Reset
                </button>
            </div>
        </form>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const capacity = document.querySelector('input[name="default_event_capacity"]').value;
            const price = document.querySelector('input[name="default_ticket_price"]').value;
            
            if (capacity < 1) {
                alert('Event capacity must be at least 1');
                e.preventDefault();
                return;
            }
            
            if (price < 0) {
                alert('Ticket price cannot be negative');
                e.preventDefault();
                return;
            }
        });
        
        // Auto-save indication
        const toggles = document.querySelectorAll('input[type="checkbox"]');
        toggles.forEach(toggle => {
            toggle.addEventListener('change', function() {
                // Add visual feedback for unsaved changes
                const saveBtn = document.querySelector('.btn-save');
                saveBtn.style.background = 'var(--warning-gradient)';
                saveBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Unsaved Changes';
            });
        });
        
        console.log('⚙️ Settings Page Loaded');
    </script>
</body>
</html>
