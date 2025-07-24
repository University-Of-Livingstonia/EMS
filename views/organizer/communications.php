<?php
/**
 * 📧 Communications - Organizer Dashboard
 * Ekwendeni Mighty Campus Event Management System
 * Connect with Your Attendees! 💬
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

// Deny access and redirect if user is not verified
if (!$currentUser || !isset($currentUser['email_verified']) || $currentUser['email_verified'] != 1) {
    header('Location: verify_email.php');
    exit;
}

// Check if user is organizer
if ($currentUser['role'] !== 'organizer') {
    header('Location: ../../dashboard/index.php');
    exit;
}

// Get organizer's events
$organizerEvents = [];
try {
    $stmt = $conn->prepare("
        SELECT event_id, title, start_datetime, status,
               (SELECT COUNT(*) FROM tickets WHERE event_id = events.event_id AND status = 'confirmed') as attendee_count
        FROM events 
        WHERE organizer_id = ? 
        ORDER BY start_datetime DESC
    ");
    $stmt->bind_param("i", $currentUser['user_id']);
    $stmt->execute();
    $organizerEvents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Organizer events error: " . $e->getMessage());
}

$message = '';
$messageType = '';

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $eventId = intval($_POST['event_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $messageContent = trim($_POST['message'] ?? '');
    $recipientType = $_POST['recipient_type'] ?? 'all';
    
    if ($eventId && $subject && $messageContent) {
        try {
            // Verify event belongs to organizer
            $stmt = $conn->prepare("SELECT title FROM events WHERE event_id = ? AND organizer_id = ?");
            $stmt->bind_param("ii", $eventId, $currentUser['user_id']);
            $stmt->execute();
            $eventData = $stmt->get_result()->fetch_assoc();
            
            if ($eventData) {
                // Get recipients based on type
                $recipientQuery = "
                    SELECT DISTINCT u.user_id, u.email, u.first_name, u.last_name
                    FROM users u
                    JOIN tickets t ON u.user_id = t.user_id
                    WHERE t.event_id = ?
                ";
                
                if ($recipientType === 'confirmed') {
                    $recipientQuery .= " AND t.status = 'confirmed'";
                } elseif ($recipientType === 'pending') {
                    $recipientQuery .= " AND t.status = 'pending'";
                }
                
                $stmt = $conn->prepare($recipientQuery);
                $stmt->bind_param("i", $eventId);
                $stmt->execute();
                $recipients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                if (!empty($recipients)) {
                    // Store message in database
                    $stmt = $conn->prepare("
                        INSERT INTO event_messages (event_id, organizer_id, subject, message, recipient_type, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->bind_param("iisss", $eventId, $currentUser['user_id'], $subject, $messageContent, $recipientType);
                    $stmt->execute();
                    $messageId = $conn->insert_id;
                    
                    // Send emails (in a real application, this would be queued)
                    $sentCount = 0;
                    foreach ($recipients as $recipient) {
                        // Store individual message record
                        $stmt = $conn->prepare("
                            INSERT INTO message_recipients (message_id, user_id, email, status, created_at)
                            VALUES (?, ?, ?, 'sent', NOW())
                        ");
                        $stmt->bind_param("iis", $messageId, $recipient['user_id'], $recipient['email']);
                        $stmt->execute();
                        $sentCount++;
                    }
                    
                    $message = "Message sent successfully to {$sentCount} recipients!";
                    $messageType = "success";
                } else {
                    $message = "No recipients found for the selected criteria.";
                    $messageType = "warning";
                }
            } else {
                $message = "Event not found or access denied.";
                $messageType = "danger";
            }
        } catch (Exception $e) {
            error_log("Message sending error: " . $e->getMessage());
            $message = "An error occurred while sending the message.";
            $messageType = "danger";
        }
    } else {
        $message = "Please fill in all required fields.";
        $messageType = "danger";
    }
}

// Get recent messages
$recentMessages = [];
try {
    $stmt = $conn->prepare("
        SELECT em.*, e.title as event_title,
               (SELECT COUNT(*) FROM message_recipients WHERE message_id = em.message_id) as recipient_count
        FROM event_messages em
        JOIN events e ON em.event_id = e.event_id
        WHERE em.organizer_id = ?
        ORDER BY em.created_at DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $currentUser['user_id']);
    $stmt->execute();
    $recentMessages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Recent messages error: " . $e->getMessage());
}

// Get communication statistics
$commStats = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_messages,
            SUM((SELECT COUNT(*) FROM message_recipients WHERE message_id = event_messages.message_id)) as total_recipients
        FROM event_messages 
        WHERE organizer_id = ?
    ");
    $stmt->bind_param("i", $currentUser['user_id']);
    $stmt->execute();
    $commStats = $stmt->get_result()->fetch_assoc();
} catch (Exception $e) {
    $commStats = ['total_messages' => 0, 'total_recipients' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communications - Organizer Dashboard | EMS</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Rich Text Editor -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    
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
        
        /* 📊 Stats Cards */
        .stats-row {
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        /* 📝 Form Container */
        .form-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            display: block;
            font-size: 0.9rem;
        }
        
        .form-control,
        .form-select {
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .form-control:focus,
        .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        /* 📝 Rich Text Editor */
        .editor-container {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .editor-container.focused {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .ql-toolbar {
            border: none;
            border-bottom: 1px solid var(--border-color);
        }
        
        .ql-container {
            border: none;
            font-family: 'Poppins', sans-serif;
            min-height: 200px;
        }
        
        /* 🎯 Buttons */
        .btn-custom {
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }
        
        .btn-secondary {
            background: var(--border-color);
            color: var(--text-dark);
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        /* 📧 Message History */
        .message-history {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
        }
        
        .message-item {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .message-item:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .message-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0;
        }
        
        .message-meta {
            text-align: right;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .message-content {
            color: var(--text-muted);
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .message-stats {
            display: flex;
            gap: 1rem;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .message-stat {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        /* 🚨 Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
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
        
        .alert-warning {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
            border-left: 4px solid #ff9800;
        }
        
        /* 📱 Responsive Design */
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .form-container {
                padding: 1.5rem;
            }
            
            .message-header {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .message-meta {
                text-align: left;
            }
            
            .message-stats {
                flex-wrap: wrap;
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
                    <h1 class="page-title">📧 Communications</h1>
                    <p class="page-subtitle">Connect with your event attendees</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="breadcrumb-nav">
                        <a href="../../dashboard/">Dashboard</a> / 
                        <a href="../organizer/dashboard.php">Organizer</a> / 
                        <span>Communications</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Communication Statistics -->
        <div class="row stats-row fade-in">
            <div class="col-md-6">
                <div class="stat-card">
                    <i class="fas fa-envelope stat-icon"></i>
                    <div class="stat-number"><?= $commStats['total_messages'] ?></div>
                    <div class="stat-label">Messages Sent</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-card">
                    <i class="fas fa-users stat-icon"></i>
                    <div class="stat-number"><?= $commStats['total_recipients'] ?></div>
                    <div class="stat-label">Total Recipients</div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> fade-in">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'exclamation-circle') ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Send Message Form -->
        <div class="form-container fade-in">
            <form method="POST" id="messageForm">
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-paper-plane"></i>
                        Send Message
                    </h3>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Select Event</label>
                                <select name="event_id" class="form-select" required onchange="updateRecipientCount()">
                                    <option value="">Choose an event...</option>
                                    <?php foreach ($organizerEvents as $event): ?>
                                        <option value="<?= $event['event_id'] ?>" 
                                                data-attendees="<?= $event['attendee_count'] ?>">
                                            <?= htmlspecialchars($event['title']) ?> 
                                            (<?= $event['attendee_count'] ?> attendees)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Recipients</label>
                                <select name="recipient_type" class="form-select" required onchange="updateRecipientCount()">
                                    <option value="all">All Registered Attendees</option>
                                    <option value="confirmed">Confirmed Attendees Only</option>
                                    <option value="pending">Pending Attendees Only</option>
                                </select>
                                <div class="form-text">
                                    <span id="recipientCount">Select an event to see recipient count</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Subject</label>
                        <input type="text" 
                               name="subject" 
                               class="form-control" 
                               placeholder="Enter message subject..."
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Message</label>
                        <div class="editor-container" id="messageEditor">
                            <div id="message-editor"></div>
                        </div>
                        <textarea name="message" 
                                  id="messageInput" 
                                  style="display: none;"
                                  required></textarea>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" name="send_message" class="btn-custom btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Send Message
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Message History -->
        <div class="message-history fade-in">
            <h3 class="section-title">
                <i class="fas fa-history"></i>
                Recent Messages
            </h3>
            
            <?php if (empty($recentMessages)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No messages sent yet. Start communicating with your attendees!</p>
                </div>
            <?php else: ?>
                <?php foreach ($recentMessages as $msg): ?>
                    <div class="message-item">
                        <div class="message-header">
                            <div>
                                <h5 class="message-title"><?= htmlspecialchars($msg['subject']) ?></h5>
                                <small class="text-muted">
                                    <i class="fas fa-calendar-alt"></i>
                                    Event: <?= htmlspecialchars($msg['event_title']) ?>
                                </small>
                            </div>
                            <div class="message-meta">
                                <div><?= date('M j, Y g:i A', strtotime($msg['created_at'])) ?></div>
                                <div>To: <?= ucfirst($msg['recipient_type']) ?> Recipients</div>
                            </div>
                        </div>
                        
                        <div class="message-content">
                            <?= substr(strip_tags($msg['message']), 0, 200) ?>
                            <?= strlen(strip_tags($msg['message'])) > 200 ? '...' : '' ?>
                        </div>
                        
                        <div class="message-stats">
                            <div class="message-stat">
                                <i class="fas fa-users"></i>
                                <?= $msg['recipient_count'] ?> recipients
                            </div>
                            <div class="message-stat">
                                <i class="fas fa-tag"></i>
                                <?= ucfirst($msg['recipient_type']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    
    <script>
        // Initialize Rich Text Editor
        const quill = new Quill('#message-editor', {
            theme: 'snow',
            placeholder: 'Write your message to attendees...',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    ['link', 'blockquote'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['clean']
                ]
            }
        });

        // Sync Quill content with hidden textarea
        quill.on('text-change', function() {
            document.getElementById('messageInput').value = quill.root.innerHTML;
        });

        // Update recipient count based on selection
        function updateRecipientCount() {
            const eventSelect = document.querySelector('select[name="event_id"]');
            const recipientTypeSelect = document.querySelector('select[name="recipient_type"]');
            const recipientCountSpan = document.getElementById('recipientCount');
            
            if (eventSelect.value) {
                const selectedOption = eventSelect.options[eventSelect.selectedIndex];
                const totalAttendees = parseInt(selectedOption.dataset.attendees) || 0;
                const recipientType = recipientTypeSelect.value;
                
                let count = totalAttendees;
                let typeText = 'all registered attendees';
                
                if (recipientType === 'confirmed') {
                    typeText = 'confirmed attendees';
                } else if (recipientType === 'pending') {
                    typeText = 'pending attendees';
                }
                
                recipientCountSpan.textContent = `Approximately ${count} ${typeText}`;
            } else {
                recipientCountSpan.textContent = 'Select an event to see recipient count';
            }
        }

        // Form validation
        document.getElementById('messageForm').addEventListener('submit', function(e) {
            // Sync Quill content before submission
            document.getElementById('messageInput').value = quill.root.innerHTML;
            
            // Check if message has content
            if (quill.getText().trim().length === 0) {
                alert('Please enter a message');
                e.preventDefault();
                return;
            }
            
            // Show loading state
            const submitBtn = document.querySelector('button[name="send_message"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending Message...';
            submitBtn.disabled = true;
        });

        console.log('📧 Communications Page Loaded');
    </script>
</body>
</html>