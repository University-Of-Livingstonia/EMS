<?php
/**
 * ⏳ Pending Events - EMS Organizer
 * Ekwendeni Mighty Campus Event Management System
 * Track Your Pending Approvals! 🕐
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

// Check if user is organizer or admin
if (!in_array($currentUser['role'], ['organizer', 'admin'])) {
    header('Location: ../../dashboard/index.php');
    exit;
}

$organizerId = $currentUser['user_id'];

// Handle event actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $eventId = $_POST['event_id'] ?? 0;
    
    switch ($action) {
        case 'withdraw':
            try {
                // Check if event belongs to organizer and is pending
                $stmt = $conn->prepare("SELECT event_id FROM events WHERE event_id = ? AND organizer_id = ? AND status = 'pending'");
                $stmt->bind_param("ii", $eventId, $organizerId);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    // Change status back to draft
                    $stmt = $conn->prepare("UPDATE events SET status = 'draft', updated_at = NOW() WHERE event_id = ?");
                    $stmt->bind_param("i", $eventId);
                    $stmt->execute();
                    
                    $success = "Event withdrawn from approval queue successfully!";
                } else {
                    $error = "Event not found or cannot be withdrawn.";
                }
            } catch (Exception $e) {
                $error = "Error withdrawing event: " . $e->getMessage();
            }
            break;
            
        case 'resubmit':
            try {
                // Check if event belongs to organizer and is rejected
                $stmt = $conn->prepare("SELECT event_id FROM events WHERE event_id = ? AND organizer_id = ? AND status = 'rejected'");
                $stmt->bind_param("ii", $eventId, $organizerId);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    // Change status to pending
                    $stmt = $conn->prepare("UPDATE events SET status = 'pending', updated_at = NOW() WHERE event_id = ?");
                    $stmt->bind_param("i", $eventId);
                    $stmt->execute();
                    
                    $success = "Event resubmitted for approval successfully!";
                } else {
                    $error = "Event not found or cannot be resubmitted.";
                }
            } catch (Exception $e) {
                $error = "Error resubmitting event: " . $e->getMessage();
            }
            break;
    }
}

// Get pending events
$pendingEvents = [];
try {
    $stmt = $conn->prepare("
        SELECT e.*, 
               COUNT(t.ticket_id) as total_registrations,
               DATEDIFF(NOW(), e.created_at) as days_pending
        FROM events e
        LEFT JOIN tickets t ON e.event_id = t.event_id
        WHERE e.organizer_id = ? AND e.status = 'pending'
        GROUP BY e.event_id
        ORDER BY e.created_at DESC
    ");
    $stmt->bind_param("i", $organizerId);
    $stmt->execute();
    $pendingEvents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Pending events error: " . $e->getMessage());
}

// Get rejected events
$rejectedEvents = [];
try {
    $stmt = $conn->prepare("
        SELECT e.*, 
               COUNT(t.ticket_id) as total_registrations,
               DATEDIFF(NOW(), e.updated_at) as days_since_rejection
        FROM events e
        LEFT JOIN tickets t ON e.event_id = t.event_id
        WHERE e.organizer_id = ? AND e.status = 'rejected'
        GROUP BY e.event_id
        ORDER BY e.updated_at DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $organizerId);
    $stmt->execute();
    $rejectedEvents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Rejected events error: " . $e->getMessage());
}

// Get approval statistics
$approvalStats = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
            AVG(CASE WHEN status = 'approved' THEN DATEDIFF(updated_at, created_at) END) as avg_approval_days
        FROM events 
        WHERE organizer_id = ?
    ");
    $stmt->bind_param("i", $organizerId);
    $stmt->execute();
    $approvalStats = $stmt->get_result()->fetch_assoc();
} catch (Exception $e) {
    error_log("Approval stats error: " . $e->getMessage());
    $approvalStats = ['pending_count' => 0, 'rejected_count' => 0, 'approved_count' => 0, 'avg_approval_days' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Events - EMS Organizer</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Include the same CSS from events.php -->
    <link rel="stylesheet" href="assets/css/organizer-styles.css">
    
    <style>
        /* Additional styles specific to pending events */
        .pending-timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .pending-timeline::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 0;
                        bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, #ff9800, #f57c00);
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 2rem;
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.75rem;
            top: 1.5rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #ff9800;
            border: 3px solid white;
            box-shadow: 0 0 0 3px #ff9800;
        }
        
        .timeline-item:hover {
            transform: translateX(10px);
            box-shadow: var(--card-hover-shadow);
        }
        
        .pending-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .pending-badge i {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .rejection-reason {
            background: rgba(244, 67, 54, 0.1);
            border-left: 4px solid #f44336;
            padding: 1rem;
            border-radius: 0 10px 10px 0;
            margin: 1rem 0;
        }
        
        .rejection-reason h6 {
            color: #f44336;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .rejection-reason p {
            color: #666;
            margin: 0;
            font-size: 0.9rem;
        }
        
        .approval-tips {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .approval-tips h4 {
            color: #1976D2;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .approval-tips ul {
            list-style: none;
            padding: 0;
        }
        
        .approval-tips li {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            color: #666;
        }
        
        .approval-tips li i {
            color: #4CAF50;
            margin-top: 0.2rem;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-pending {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
            border: 1px solid #ff9800;
        }
        
        .status-rejected {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid #f44336;
        }
        
        .days-counter {
            font-size: 0.9rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .priority-high {
            border-left: 5px solid #f44336;
        }
        
        .priority-medium {
            border-left: 5px solid #ff9800;
        }
        
        .priority-low {
            border-left: 5px solid #4CAF50;
        }
    </style>
</head>
<body>
    <!-- 🎪 Organizer Sidebar (Same as events.php) -->
    <div class="organizer-sidebar" id="organizerSidebar">
        <div class="sidebar-header">
            <h3>🎪 EMS Organizer</h3>
            <p>Event Management Hub</p>
        </div>
        
        <nav class="organizer-nav">
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <div class="organizer-nav-item">
                    <a href="dashboard.php" class="organizer-nav-link">
                        <i class="fas fa-tachometer-alt nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="events.php" class="organizer-nav-link">
                        <i class="fas fa-calendar-alt nav-icon"></i>
                        <span class="nav-text">My Events</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="pending_events.php" class="organizer-nav-link active">
                        <i class="fas fa-clock nav-icon"></i>
                        <span class="nav-text">Pending Events</span>
                        <span class="nav-badge"><?= $approvalStats['pending_count'] ?></span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Analytics</div>
                <div class="organizer-nav-item">
                    <a href="revenue.php" class="organizer-nav-link">
                        <i class="fas fa-chart-line nav-icon"></i>
                        <span class="nav-text">Revenue</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="reports.php" class="organizer-nav-link">
                        <i class="fas fa-file-chart-line nav-icon"></i>
                        <span class="nav-text">Reports</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Management</div>
                <div class="organizer-nav-item">
                    <a href="create-event.php" class="organizer-nav-link">
                        <i class="fas fa-plus-circle nav-icon"></i>
                        <span class="nav-text">Create Event</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="attendees.php" class="organizer-nav-link">
                        <i class="fas fa-users nav-icon"></i>
                        <span class="nav-text">Attendees</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Account</div>
                <div class="organizer-nav-item">
                    <a href="profile.php" class="organizer-nav-link">
                        <i class="fas fa-user nav-icon"></i>
                        <span class="nav-text">Profile</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="../../dashboard/index.php" class="organizer-nav-link">
                        <i class="fas fa-arrow-left nav-icon"></i>
                        <span class="nav-text">Back to User</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="../../auth/logout.php" class="organizer-nav-link">
                        <i class="fas fa-sign-out-alt nav-icon"></i>
                        <span class="nav-text">Logout</span>
                    </a>
                </div>
            </div>
        </nav>
    </div>
    
    <!-- 📱 Main Content Area -->
    <div class="organizer-main" id="organizerMain">
        <!-- 🎯 Organizer Top Bar -->
        <div class="organizer-topbar">
            <div class="organizer-title-section">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="organizer-title">⏳ Pending Events</h1>
            </div>
            
            <div class="organizer-user-info">
                <div class="organizer-avatar">
                    <?= strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)) ?>
                </div>
                <div class="organizer-user-details">
                    <h6><?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></h6>
                    <small>Event Organizer</small>
                </div>
            </div>
        </div>
        
        <!-- 📊 Dashboard Content -->
        <div class="organizer-content">
            <!-- 🚨 Alerts -->
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <!-- 🎯 Approval Statistics -->
            <div class="stats-cards">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="stat-card warning fade-in-up">
                            <div class="stat-header">
                                <div class="stat-icon warning">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                            <div class="stat-number"><?= $approvalStats['pending_count'] ?></div>
                            <div class="stat-label">Pending Approval</div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="stat-card success fade-in-up">
                            <div class="stat-header">
                                <div class="stat-icon success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                            <div class="stat-number"><?= $approvalStats['approved_count'] ?></div>
                            <div class="stat-label">Approved Events</div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="stat-card primary fade-in-up">
                            <div class="stat-header">
                                <div class="stat-icon primary">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                            </div>
                            <div class="stat-number"><?= $approvalStats['rejected_count'] ?></div>
                            <div class="stat-label">Rejected Events</div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="stat-card info fade-in-up">
                            <div class="stat-header">
                                <div class="stat-icon info">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                            </div>
                            <div class="stat-number"><?= round($approvalStats['avg_approval_days'] ?? 0, 1) ?></div>
                            <div class="stat-label">Avg Approval Days</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 💡 Approval Tips -->
            <div class="approval-tips fade-in-up">
                <h4>
                    <i class="fas fa-lightbulb"></i>
                    Tips for Faster Approval
                </h4>
                <div class="row">
                    <div class="col-md-6">
                        <ul>
                            <li><i class="fas fa-check"></i> Provide clear and detailed event descriptions</li>
                            <li><i class="fas fa-check"></i> Include accurate venue and timing information</li>
                            <li><i class="fas fa-check"></i> Set reasonable ticket prices and capacity limits</li>
                            <li><i class="fas fa-check"></i> Ensure your event complies with campus policies</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul>
                            <li><i class="fas fa-check"></i> Upload high-quality event images if possible</li>
                            <li><i class="fas fa-check"></i> Provide contact information for inquiries</li>
                            <li><i class="fas fa-check"></i> Submit events at least 7 days before the event date</li>
                            <li><i class="fas fa-check"></i> Respond promptly to admin feedback or requests</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- ⏳ Pending Events Timeline -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="organizer-card fade-in-up">
                        <div class="organizer-card-header">
                            <h3 class="organizer-card-title">
                                <i class="fas fa-clock"></i>
                                Events Awaiting Approval (<?= count($pendingEvents) ?>)
                            </h3>
                        </div>
                        <div class="organizer-card-body">
                            <?php if (!empty($pendingEvents)): ?>
                                <div class="pending-timeline">
                                    <?php foreach ($pendingEvents as $event): ?>
                                                                            <?php
                                        $priority = 'low';
                                        if ($event['days_pending'] > 7) $priority = 'high';
                                        elseif ($event['days_pending'] > 3) $priority = 'medium';
                                        ?>
                                        
                                        <div class="timeline-item priority-<?= $priority ?>">
                                            <div class="pending-badge">
                                                <i class="fas fa-clock"></i>
                                                Pending for <?= $event['days_pending'] ?> day<?= $event['days_pending'] != 1 ? 's' : '' ?>
                                            </div>
                                            
                                            <div class="event-header">
                                                <div>
                                                    <h4 class="event-title"><?= htmlspecialchars($event['title']) ?></h4>
                                                    <p class="event-description"><?= htmlspecialchars(substr($event['description'], 0, 120)) ?>...</p>
                                                </div>
                                                <span class="status-indicator status-pending">
                                                    <i class="fas fa-hourglass-half"></i>
                                                    Pending Review
                                                </span>
                                            </div>
                                            
                                            <div class="event-meta">
                                                <div class="event-meta-item">
                                                    <i class="fas fa-calendar"></i>
                                                    <span><?= date('M j, Y', strtotime($event['start_datetime'])) ?></span>
                                                </div>
                                                <div class="event-meta-item">
                                                    <i class="fas fa-clock"></i>
                                                    <span><?= date('g:i A', strtotime($event['start_datetime'])) ?></span>
                                                </div>
                                                <div class="event-meta-item">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <span><?= htmlspecialchars($event['location']) ?></span>
                                                </div>
                                                <div class="event-meta-item">
                                                    <i class="fas fa-users"></i>
                                                    <span><?= $event['max_attendees'] ?> max attendees</span>
                                                </div>
                                                <div class="event-meta-item">
                                                    <i class="fas fa-money-bill-wave"></i>
                                                    <span>K<?= number_format($event['ticket_price'], 0) ?> per ticket</span>
                                                </div>
                                                <div class="event-meta-item">
                                                    <i class="fas fa-paper-plane"></i>
                                                    <span>Submitted: <?= date('M j, Y g:i A', strtotime($event['created_at'])) ?></span>
                                                </div>
                                            </div>
                                            
                                            <?php if ($event['total_registrations'] > 0): ?>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle"></i>
                                                    <strong><?= $event['total_registrations'] ?></strong> people have already registered for this event while it's pending approval.
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="event-actions">
                                                <a href="view-event.php?id=<?= $event['event_id'] ?>" class="organizer-btn organizer-btn-primary">
                                                    <i class="fas fa-eye"></i> View Details
                                                </a>
                                                
                                                <a href="edit-event.php?id=<?= $event['event_id'] ?>" class="organizer-btn organizer-btn-warning">
                                                    <i class="fas fa-edit"></i> Edit Event
                                                </a>
                                                
                                                <button onclick="withdrawEvent(<?= $event['event_id'] ?>)" class="organizer-btn organizer-btn-danger">
                                                    <i class="fas fa-undo"></i> Withdraw Submission
                                                </button>
                                                
                                                <?php if ($event['total_registrations'] > 0): ?>
                                                    <a href="attendees.php?event_id=<?= $event['event_id'] ?>" class="organizer-btn organizer-btn-info">
                                                        <i class="fas fa-users"></i> View Registrations (<?= $event['total_registrations'] ?>)
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-check-circle"></i>
                                    <h3>No Pending Events</h3>
                                    <p>You don't have any events waiting for approval at the moment.</p>
                                    <a href="events.php" class="organizer-btn organizer-btn-primary">
                                        <i class="fas fa-calendar-alt"></i> View All Events
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- 📋 Approval Process Info -->
                <div class="col-lg-4">
                    <div class="organizer-card fade-in-up">
                        <div class="organizer-card-header">
                            <h3 class="organizer-card-title">
                                <i class="fas fa-info-circle"></i>
                                Approval Process
                            </h3>
                        </div>
                        <div class="organizer-card-body">
                            <div class="approval-process">
                                <div class="process-step">
                                    <div class="step-icon">
                                        <i class="fas fa-paper-plane"></i>
                                    </div>
                                    <div class="step-content">
                                        <h6>1. Event Submission</h6>
                                        <p>You submit your event for approval</p>
                                    </div>
                                </div>
                                
                                <div class="process-step">
                                    <div class="step-icon">
                                        <i class="fas fa-search"></i>
                                    </div>
                                    <div class="step-content">
                                        <h6>2. Admin Review</h6>
                                        <p>Administrators review your event details</p>
                                    </div>
                                </div>
                                
                                <div class="process-step">
                                    <div class="step-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="step-content">
                                        <h6>3. Approval Decision</h6>
                                        <p>Event is approved or feedback is provided</p>
                                    </div>
                                </div>
                                
                                <div class="process-step">
                                    <div class="step-icon">
                                        <i class="fas fa-rocket"></i>
                                    </div>
                                    <div class="step-content">
                                        <h6>4. Event Goes Live</h6>
                                        <p>Approved events become publicly visible</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="approval-timeline">
                                <h6><i class="fas fa-clock"></i> Typical Timeline</h6>
                                <ul>
                                    <li><strong>Standard Events:</strong> 1-3 business days</li>
                                    <li><strong>Large Events:</strong> 3-5 business days</li>
                                    <li><strong>Special Events:</strong> 5-7 business days</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 🚫 Recently Rejected Events -->
                    <?php if (!empty($rejectedEvents)): ?>
                        <div class="organizer-card fade-in-up">
                            <div class="organizer-card-header">
                                <h3 class="organizer-card-title">
                                    <i class="fas fa-times-circle"></i>
                                    Recently Rejected Events
                                </h3>
                            </div>
                            <div class="organizer-card-body">
                                <?php foreach ($rejectedEvents as $event): ?>
                                    <div class="event-card rejected mb-3">
                                        <div class="event-header">
                                            <div>
                                                <h5 class="event-title"><?= htmlspecialchars($event['title']) ?></h5>
                                                <div class="days-counter">
                                                    <i class="fas fa-calendar-times"></i>
                                                    Rejected <?= $event['days_since_rejection'] ?> day<?= $event['days_since_rejection'] != 1 ? 's' : '' ?> ago
                                                </div>
                                            </div>
                                            <span class="status-indicator status-rejected">
                                                <i class="fas fa-times-circle"></i>
                                                Rejected
                                            </span>
                                        </div>
                                        
                                        <?php if (!empty($event['rejection_reason'])): ?>
                                            <div class="rejection-reason">
                                                <h6><i class="fas fa-exclamation-triangle"></i> Rejection Reason</h6>
                                                <p><?= htmlspecialchars($event['rejection_reason']) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="event-actions">
                                            <a href="edit-event.php?id=<?= $event['event_id'] ?>" class="organizer-btn organizer-btn-warning">
                                                <i class="fas fa-edit"></i> Edit & Fix
                                            </a>
                                            
                                            <button onclick="resubmitEvent(<?= $event['event_id'] ?>)" class="organizer-btn organizer-btn-success">
                                                <i class="fas fa-redo"></i> Resubmit
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 📱 Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 🎯 Event Management Functions
        function toggleSidebar() {
            const sidebar = document.getElementById('organizerSidebar');
            const main = document.getElementById('organizerMain');
            
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                main.classList.toggle('expanded');
            }
        }
        
        function withdrawEvent(eventId) {
            if (confirm('Are you sure you want to withdraw this event from the approval queue? It will be moved back to drafts.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="withdraw">
                    <input type="hidden" name="event_id" value="${eventId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function resubmitEvent(eventId) {
            if (confirm('Are you sure you want to resubmit this event for approval? Make sure you have addressed the rejection reasons.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="resubmit">
                    <input type="hidden" name="event_id" value="${eventId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // 🎨 Initialize animations and interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Animate timeline items on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationDelay = '0.1s';
                        entry.target.classList.add('fade-in-up');
                    }
                });
            }, observerOptions);
            
            // Observe all timeline items
            const timelineItems = document.querySelectorAll('.timeline-item');
            timelineItems.forEach(item => observer.observe(item));
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
            
            // Add priority indicators based on pending days
            const timelineItems2 = document.querySelectorAll('.timeline-item');
            timelineItems2.forEach(item => {
                const badge = item.querySelector('.pending-badge');
                const daysText = badge.textContent;
                const days = parseInt(daysText.match(/\d+/)[0]);
                
                if (days > 7) {
                    badge.style.background = 'rgba(244, 67, 54, 0.1)';
                    badge.style.color = '#f44336';
                    badge.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${daysText} - High Priority`;
                } else if (days > 3) {
                    badge.style.background = 'rgba(255, 152, 0, 0.1)';
                    badge.style.color = '#ff9800';
                    badge.innerHTML = `<i class="fas fa-clock"></i> ${daysText} - Medium Priority`;
                }
            });
            
            // Real-time status updates (placeholder for WebSocket implementation)
            setInterval(() => {
                // Check for status updates
                console.log('Checking for approval status updates...');
                // Implement AJAX call to check for updates
            }, 30000);
        });
        
        // 📱 Responsive handling
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.getElementById('organizerSidebar').classList.remove('show');
            }
        });
    </script>
    
    <style>
        /* Additional CSS for approval process steps */
        .approval-process {
            margin-bottom: 2rem;
        }
        
        .process-step {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1.5rem;
            position: relative;
          }
        
        .process-step:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 1.25rem;
            top: 2.5rem;
            bottom: -1.5rem;
            width: 2px;
            background: linear-gradient(to bottom, #e0e0e0, #f5f5f5);
        }
        
        .step-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .step-content h6 {
            margin: 0 0 0.5rem 0;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .step-content p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .approval-timeline {
            background: rgba(102, 126, 234, 0.05);
            border-radius: 10px;
            padding: 1.5rem;
            border-left: 4px solid #667eea;
        }
        
        .approval-timeline h6 {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .approval-timeline ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .approval-timeline li {
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(102, 126, 234, 0.1);
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .approval-timeline li:last-child {
            border-bottom: none;
        }
        
        .approval-timeline strong {
            color: var(--text-primary);
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .pending-timeline {
                padding-left: 1rem;
            }
            
            .pending-timeline::before {
                left: 0.5rem;
            }
            
            .timeline-item::before {
                left: -1.25rem;
            }
            
            .timeline-item {
                margin-left: 0.5rem;
            }
            
            .event-meta {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .event-actions {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .organizer-btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Animation enhancements */
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out forwards;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Priority indicators */
        .priority-high .pending-badge {
            background: rgba(244, 67, 54, 0.1) !important;
            color: #f44336 !important;
            border: 1px solid #f44336;
        }
        
        .priority-medium .pending-badge {
            background: rgba(255, 152, 0, 0.1) !important;
            color: #ff9800 !important;
            border: 1px solid #ff9800;
        }
        
        .priority-low .pending-badge {
            background: rgba(76, 175, 80, 0.1) !important;
            color: #4CAF50 !important;
            border: 1px solid #4CAF50;
        }
        
        /* Loading states */
        .organizer-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .organizer-btn.loading {
            position: relative;
            color: transparent;
        }
        
        .organizer-btn.loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 16px;
            height: 16px;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to {
                transform: translate(-50%, -50%) rotate(360deg);
            }
        }
    </style>
</body>
</html>
