<?php
/**
 * ✏️ Edit Event - Organizer Dashboard
 * Ekwendeni Mighty Campus Event Management System
 * Perfect Your Events! 🎯
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

/*// Deny access and redirect if user is not verified
if (!$currentUser || !isset($currentUser['email_verified']) || $currentUser['email_verified'] != 1) {
    header('Location: verify_email.php');
    exit;
}*/

// Check if user is organizer
if ($currentUser['role'] !== 'organizer') {
    header('Location: ../../dashboard/index.php');
    exit;
}

// Get event ID
$eventId = intval($_GET['id'] ?? 0);
if (!$eventId) {
    header('Location: ../organizer/dashboard.php');
    exit;
}

// Get event details
$event = null;
try {
    $stmt = $conn->prepare("
        SELECT * FROM events 
        WHERE event_id = ? AND organizer_id = ?
    ");
    $stmt->bind_param("ii", $eventId, $currentUser['user_id']);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    
    if (!$event) {
        header('Location: ../organizer/dashboard.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Event fetch error: " . $e->getMessage());
    header('Location: ../organizer/dashboard.php');
    exit;
}

$errors = [];
$success = '';
$formData = $event; // Pre-populate with existing data

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_event'])) {
    $formData = [
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'category' => $_POST['category'] ?? '',
        'start_datetime' => $_POST['start_datetime'] ?? '',
        'end_datetime' => $_POST['end_datetime'] ?? '',
        'location' => trim($_POST['location'] ?? ''),
        'max_attendees' => intval($_POST['max_attendees'] ?? 0),
        'price' => floatval($_POST['price'] ?? 0),
        'is_public' => isset($_POST['is_public']) ? 1 : 0,
        'requires_approval' => isset($_POST['requires_approval']) ? 1 : 0,
               'tags' => trim($_POST['tags'] ?? ''),
        'special_instructions' => trim($_POST['special_instructions'] ?? ''),
        'contact_email' => trim($_POST['contact_email'] ?? ''),
        'contact_phone' => trim($_POST['contact_phone'] ?? ''),
        'budget' => floatval($_POST['budget'] ?? 0),
        'expected_attendees' => intval($_POST['expected_attendees'] ?? 0)
    ];
    
    // Validation
    if (empty($formData['title'])) {
        $errors[] = "Event title is required";
    }
    
    if (empty($formData['description'])) {
        $errors[] = "Event description is required";
    }
    
    if (empty($formData['category'])) {
        $errors[] = "Event category is required";
    }
    
    if (empty($formData['start_datetime'])) {
        $errors[] = "Start date and time is required";
    }
    
    if (empty($formData['end_datetime'])) {
        $errors[] = "End date and time is required";
    }
    
    if (empty($formData['location'])) {
        $errors[] = "Event location is required";
    }
    
    if ($formData['max_attendees'] <= 0) {
        $errors[] = "Maximum attendees must be greater than 0";
    }
    
    if ($formData['price'] < 0) {
        $errors[] = "Price cannot be negative";
    }
    
    // Validate dates
    if (!empty($formData['start_datetime']) && !empty($formData['end_datetime'])) {
        $startTime = strtotime($formData['start_datetime']);
        $endTime = strtotime($formData['end_datetime']);
        
        if ($startTime >= $endTime) {
            $errors[] = "End date must be after start date";
        }
        
        // Only check future date if event hasn't started yet
        if ($event['status'] === 'pending' && $startTime <= time()) {
            $errors[] = "Start date must be in the future";
        }
    }
    
    // Handle image upload
    $imagePath = $event['image_path']; // Keep existing image by default
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/events/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileInfo = pathinfo($_FILES['event_image']['name']);
        $fileName = 'event_' . time() . '_' . uniqid() . '.' . $fileInfo['extension'];
        $targetPath = $uploadDir . $fileName;
        
        // Validate image
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array(strtolower($fileInfo['extension']), $allowedTypes)) {
            $errors[] = "Only JPG, PNG, and GIF images are allowed";
        }
        
        if ($_FILES['event_image']['size'] > 5 * 1024 * 1024) { // 5MB limit
            $errors[] = "Image size must be less than 5MB";
        }
        
        if (empty($errors) && move_uploaded_file($_FILES['event_image']['tmp_name'], $targetPath)) {
            // Delete old image if it exists
            if ($event['image_path'] && file_exists('../../' . $event['image_path'])) {
                unlink('../../' . $event['image_path']);
            }
            $imagePath = 'uploads/events/' . $fileName;
        } elseif (empty($errors)) {
            $errors[] = "Failed to upload image";
        }
    }
    
    // If no errors, update the event
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                UPDATE events SET 
                    title = ?, description = ?, category = ?, start_datetime = ?, 
                    end_datetime = ?, location = ?, max_attendees = ?, price = ?, 
                    is_public = ?, requires_approval = ?, image_path = ?, tags = ?, 
                    special_instructions = ?, contact_email = ?, contact_phone = ?, 
                    budget = ?, expected_attendees = ?, updated_at = NOW()
                WHERE event_id = ? AND organizer_id = ?
            ");
            
            $stmt->bind_param("ssssssidiiissssdiis",
                $formData['title'],
                $formData['description'],
                $formData['category'],
                $formData['start_datetime'],
                $formData['end_datetime'],
                $formData['location'],
                $formData['max_attendees'],
                $formData['price'],
                $formData['is_public'],
                $formData['requires_approval'],
                $imagePath,
                $formData['tags'],
                $formData['special_instructions'],
                $formData['contact_email'],
                $formData['contact_phone'],
                $formData['budget'],
                $formData['expected_attendees'],
                $eventId,
                $currentUser['user_id']
            );
            
            if ($stmt->execute()) {
                $success = "Event updated successfully!";
                
                // Refresh event data
                $stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ?");
                $stmt->bind_param("i", $eventId);
                $stmt->execute();
                $event = $stmt->get_result()->fetch_assoc();
                $formData = $event;
            } else {
                $errors[] = "Failed to update event. Please try again.";
            }
            
        } catch (Exception $e) {
            error_log("Event update error: " . $e->getMessage());
            $errors[] = "An error occurred while updating the event.";
        }
    }
}

// Get event categories
$categories = [
    'academic' => '🎓 Academic',
    'cultural' => '🎭 Cultural',
    'sports' => '⚽ Sports',
    'social' => '🎉 Social',
    'workshop' => '🛠️ Workshop',
    'seminar' => '📚 Seminar',
    'conference' => '🏢 Conference',
    'competition' => '🏆 Competition',
    'fundraising' => '💰 Fundraising',
    'other' => '📋 Other'
];

// Get event statistics
$eventStats = [];
try {
    // Total registrations
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tickets WHERE event_id = ?");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $eventStats['total_registrations'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Confirmed registrations
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tickets WHERE event_id = ? AND status = 'confirmed'");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $eventStats['confirmed_registrations'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Revenue
    $stmt = $conn->prepare("SELECT SUM(price) as revenue FROM tickets WHERE event_id = ? AND payment_status = 'completed'");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $eventStats['revenue'] = $stmt->get_result()->fetch_assoc()['revenue'] ?? 0;
    
} catch (Exception $e) {
    error_log("Event stats error: " . $e->getMessage());
    $eventStats = ['total_registrations' => 0, 'confirmed_registrations' => 0, 'revenue' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - <?= htmlspecialchars($event['title']) ?> | EMS</title>
    
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
        }
        
        .stat-card.primary::before { background: var(--primary-gradient); }
        .stat-card.success::before { background: var(--success-gradient); }
        .stat-card.info::before { background: var(--info-gradient); }
        
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
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
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
        
        .form-label.required::after {
            content: ' *';
            color: #f44336;
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
        
                .form-text {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
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
        }
        
        /* 🖼️ Image Upload */
        .image-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        
        .image-upload-area:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        
        .upload-icon {
            font-size: 3rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            margin-top: 1rem;
        }
        
        .current-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        /* 🎯 Form Grid */
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        /* ✅ Checkboxes */
        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .form-check-input {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-color);
            border-radius: 5px;
            cursor: pointer;
        }
        
        .form-check-input:checked {
            background: var(--primary-gradient);
            border-color: #667eea;
        }
        
        .form-check-label {
            font-weight: 500;
            color: var(--text-dark);
            cursor: pointer;
            font-size: 0.9rem;
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
        
        /* 🎯 Buttons */
        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
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
        
        .btn-danger {
            background: var(--danger-gradient);
            color: white;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        /* 🏷️ Status Badge */
        .status-badge {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .status-pending {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
            border: 2px solid #ff9800;
        }
        
        .status-approved {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border: 2px solid #4CAF50;
        }
        
        .status-rejected {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 2px solid #f44336;
        }
        
        /* 📱 Responsive Design */
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .form-container {
                padding: 1.5rem;
            }
            
            .form-grid-2 {
                grid-template-columns: 1fr;
            }
            
            .btn-group {
                justify-content: center;
            }
            
            .btn-custom {
                width: 100%;
                justify-content: center;
            }
            
            .stats-row .col-md-4 {
                margin-bottom: 1rem;
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
                    <h1 class="page-title">✏️ Edit Event</h1>
                    <p class="page-subtitle">Perfect your event details</p>
                    <div class="status-badge status-<?= $event['status'] ?>">
                        <?= ucfirst($event['status']) ?>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="breadcrumb-nav">
                        <a href="../../dashboard/">Dashboard</a> / 
                        <a href="../organizer/dashboard.php">Organizer</a> / 
                        <span>Edit Event</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Event Statistics -->
        <div class="row stats-row fade-in">
            <div class="col-md-4">
                <div class="stat-card primary">
                    <i class="fas fa-users stat-icon"></i>
                    <div class="stat-number"><?= $eventStats['total_registrations'] ?></div>
                    <div class="stat-label">Total Registrations</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card success">
                    <i class="fas fa-check-circle stat-icon"></i>
                    <div class="stat-number"><?= $eventStats['confirmed_registrations'] ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card info">
                    <i class="fas fa-money-bill-wave stat-icon"></i>
                    <div class="stat-number">MWK <?= number_format($eventStats['revenue'], 2) ?></div>
                    <div class="stat-label">Revenue</div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success fade-in">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger fade-in">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Please fix the following errors:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Warning for approved events -->
        <?php if ($event['status'] === 'approved'): ?>
            <div class="alert alert-warning fade-in">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Note:</strong> This event has been approved. Major changes may require re-approval.
            </div>
        <?php endif; ?>

        <!-- Edit Event Form -->
        <div class="form-container fade-in">
            <form method="POST" enctype="multipart/form-data" id="editEventForm">
                <!-- Basic Information Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Basic Information
                    </h3>
                    
                    <div class="form-group">
                        <label class="form-label required">Event Title</label>
                        <input type="text" 
                               name="title" 
                               class="form-control" 
                               placeholder="Enter an exciting event title..."
                               value="<?= htmlspecialchars($formData['title']) ?>"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Event Description</label>
                        <div class="editor-container" id="descriptionEditor">
                            <div id="description-editor"></div>
                        </div>
                        <textarea name="description" 
                                  id="descriptionInput" 
                                  style="display: none;"
                                  required><?= htmlspecialchars($formData['description']) ?></textarea>
                    </div>
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label required">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $value => $label): ?>
                                    <option value="<?= $value ?>" 
                                            <?= $formData['category'] === $value ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Event Tags</label>
                            <input type="text" 
                                   name="tags" 
                                   class="form-control" 
                                   placeholder="Enter tags separated by commas..."
                                   value="<?= htmlspecialchars($formData['tags']) ?>">
                        </div>
                    </div>
                </div>

                <!-- Date & Time Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-calendar-alt"></i>
                        Date & Time
                    </h3>
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label required">Start Date & Time</label>
                            <input type="datetime-local" 
                                   name="start_datetime" 
                                   class="form-control" 
                                   value="<?= date('Y-m-d\TH:i', strtotime($formData['start_datetime'])) ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required">End Date & Time</label>
                            <input type="datetime-local" 
                                   name="end_datetime" 
                                   class="form-control" 
                                   value="<?= date('Y-m-d\TH:i', strtotime($formData['end_datetime'])) ?>"
                                   required>
                        </div>
                    </div>
                </div>

                <!-- Location & Capacity Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-map-marker-alt"></i>
                        Location & Capacity
                    </h3>
                    
                    <div class="form-group">
                        <label class="form-label required">Event Location</label>
                        <input type="text" 
                               name="location" 
                               class="form-control" 
                               placeholder="Enter event location..."
                               value="<?= htmlspecialchars($formData['location']) ?>"
                               required>
                    </div>
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label required">Maximum Attendees</label>
                            <input type="number" 
                                   name="max_attendees" 
                                   class="form-control" 
                                   min="1"
                                   value="<?= $formData['max_attendees'] ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Expected Attendees</label>
                                    .form-text {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
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
        }
        
        /* 🖼️ Image Upload */
        .image-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        
        .image-upload-area:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        
        .upload-icon {
            font-size: 3rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            margin-top: 1rem;
        }
        
        .current-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        /* 🎯 Form Grid */
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        /* ✅ Checkboxes */
        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .form-check-input {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-color);
            border-radius: 5px;
            cursor: pointer;
        }
        
        .form-check-input:checked {
            background: var(--primary-gradient);
            border-color: #667eea;
        }
        
        .form-check-label {
            font-weight: 500;
            color: var(--text-dark);
            cursor: pointer;
            font-size: 0.9rem;
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
        
        /* 🎯 Buttons */
        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
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
        
        .btn-danger {
            background: var(--danger-gradient);
            color: white;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        /* 🏷️ Status Badge */
        .status-badge {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .status-pending {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
            border: 2px solid #ff9800;
        }
        
        .status-approved {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border: 2px solid #4CAF50;
        }
        
        .status-rejected {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 2px solid #f44336;
        }
        
        /* 📱 Responsive Design */
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .form-container {
                padding: 1.5rem;
            }
            
            .form-grid-2 {
                grid-template-columns: 1fr;
            }
            
            .btn-group {
                justify-content: center;
            }
            
            .btn-custom {
                width: 100%;
                justify-content: center;
            }
            
            .stats-row .col-md-4 {
                margin-bottom: 1rem;
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
                    <h1 class="page-title">✏️ Edit Event</h1>
                    <p class="page-subtitle">Perfect your event details</p>
                    <div class="status-badge status-<?= $event['status'] ?>">
                        <?= ucfirst($event['status']) ?>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="breadcrumb-nav">
                        <a href="../../dashboard/">Dashboard</a> / 
                        <a href="../organizer/dashboard.php">Organizer</a> / 
                        <span>Edit Event</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Event Statistics -->
        <div class="row stats-row fade-in">
            <div class="col-md-4">
                <div class="stat-card primary">
                    <i class="fas fa-users stat-icon"></i>
                    <div class="stat-number"><?= $eventStats['total_registrations'] ?></div>
                    <div class="stat-label">Total Registrations</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card success">
                    <i class="fas fa-check-circle stat-icon"></i>
                    <div class="stat-number"><?= $eventStats['confirmed_registrations'] ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card info">
                    <i class="fas fa-money-bill-wave stat-icon"></i>
                    <div class="stat-number">MWK <?= number_format($eventStats['revenue'], 2) ?></div>
                    <div class="stat-label">Revenue</div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success fade-in">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger fade-in">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Please fix the following errors:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Warning for approved events -->
        <?php if ($event['status'] === 'approved'): ?>
            <div class="alert alert-warning fade-in">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Note:</strong> This event has been approved. Major changes may require re-approval.
            </div>
        <?php endif; ?>

        <!-- Edit Event Form -->
        <div class="form-container fade-in">
            <form method="POST" enctype="multipart/form-data" id="editEventForm">
                <!-- Basic Information Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Basic Information
                    </h3>
                    
                    <div class="form-group">
                        <label class="form-label required">Event Title</label>
                        <input type="text" 
                               name="title" 
                               class="form-control" 
                               placeholder="Enter an exciting event title..."
                               value="<?= htmlspecialchars($formData['title']) ?>"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Event Description</label>
                        <div class="editor-container" id="descriptionEditor">
                            <div id="description-editor"></div>
                        </div>
                        <textarea name="description" 
                                  id="descriptionInput" 
                                  style="display: none;"
                                  required><?= htmlspecialchars($formData['description']) ?></textarea>
                    </div>
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label required">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $value => $label): ?>
                                    <option value="<?= $value ?>" 
                                            <?= $formData['category'] === $value ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Event Tags</label>
                            <input type="text" 
                                   name="tags" 
                                   class="form-control" 
                                   placeholder="Enter tags separated by commas..."
                                   value="<?= htmlspecialchars($formData['tags']) ?>">
                        </div>
                    </div>
                </div>

                <!-- Date & Time Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-calendar-alt"></i>
                        Date & Time
                    </h3>
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label required">Start Date & Time</label>
                            <input type="datetime-local" 
                                   name="start_datetime" 
                                   class="form-control" 
                                   value="<?= date('Y-m-d\TH:i', strtotime($formData['start_datetime'])) ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required">End Date & Time</label>
                            <input type="datetime-local" 
                                   name="end_datetime" 
                                   class="form-control" 
                                   value="<?= date('Y-m-d\TH:i', strtotime($formData['end_datetime'])) ?>"
                                   required>
                        </div>
                    </div>
                </div>

                <!-- Location & Capacity Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-map-marker-alt"></i>
                        Location & Capacity
                    </h3>
                    
                    <div class="form-group">
                        <label class="form-label required">Event Location</label>
                        <input type="text" 
                               name="location" 
                               class="form-control" 
                               placeholder="Enter event location..."
                               value="<?= htmlspecialchars($formData['location']) ?>"
                               required>
                    </div>
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label required">Maximum Attendees</label>
                            <input type="number" 
                                   name="max_attendees" 
                                   class="form-control" 
                                   min="1"
                                   value="<?= $formData['max_attendees'] ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Expected Attendees</label>
                                                        <input type="number" 
                                   name="expected_attendees" 
                                   class="form-control" 
                                   min="0"
                                   value="<?= $formData['expected_attendees'] ?>">
                        </div>
                    </div>
                </div>

                <!-- Pricing & Budget Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-money-bill-wave"></i>
                        Pricing & Budget
                    </h3>
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Ticket Price (MWK)</label>
                            <input type="number" 
                                   name="price" 
                                   class="form-control" 
                                   min="0"
                                   step="0.01"
                                   value="<?= $formData['price'] ?>">
                            <div class="form-text">Set to 0 for free events</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Event Budget (MWK)</label>
                            <input type="number" 
                                   name="budget" 
                                   class="form-control" 
                                   min="0"
                                   step="0.01"
                                   value="<?= $formData['budget'] ?>">
                        </div>
                    </div>
                </div>

                <!-- Event Image Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-image"></i>
                        Event Image
                    </h3>
                    
                    <?php if ($event['image_path']): ?>
                        <div class="form-group">
                            <label class="form-label">Current Image</label>
                            <div>
                                <img src="../../<?= htmlspecialchars($event['image_path']) ?>" 
                                     alt="Current Event Image" 
                                     class="current-image">
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label class="form-label">Update Event Image</label>
                        <div class="image-upload-area" onclick="document.getElementById('eventImage').click()">
                            <input type="file" 
                                   name="event_image" 
                                   id="eventImage" 
                                   accept="image/*" 
                                   style="display: none;"
                                   onchange="previewImage(this)">
                            <div class="upload-content">
                                <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                <div class="upload-text">
                                    <strong>Click to upload new image</strong> or drag and drop<br>
                                    <small>PNG, JPG, GIF up to 5MB</small>
                                </div>
                            </div>
                            <img id="imagePreview" class="image-preview" style="display: none;">
                        </div>
                    </div>
                </div>

                <!-- Contact Information Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-address-book"></i>
                        Contact Information
                    </h3>
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Contact Email</label>
                            <input type="email" 
                                   name="contact_email" 
                                   class="form-control" 
                                   placeholder="contact@example.com"
                                   value="<?= htmlspecialchars($formData['contact_email']) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Contact Phone</label>
                            <input type="tel" 
                                   name="contact_phone" 
                                   class="form-control" 
                                   placeholder="+265 123 456 789"
                                   value="<?= htmlspecialchars($formData['contact_phone']) ?>">
                        </div>
                    </div>
                </div>

                <!-- Additional Information Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-cog"></i>
                        Additional Settings
                    </h3>
                    
                    <div class="form-group">
                        <label class="form-label">Special Instructions</label>
                        <textarea name="special_instructions" 
                                  class="form-control" 
                                  rows="4"
                                  placeholder="Any special instructions for attendees..."><?= htmlspecialchars($formData['special_instructions']) ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" 
                                   name="is_public" 
                                   id="isPublic" 
                                   class="form-check-input"
                                   <?= $formData['is_public'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="isPublic">
                                Make this event public
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" 
                                   name="requires_approval" 
                                   id="requiresApproval" 
                                   class="form-check-input"
                                   <?= $formData['requires_approval'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="requiresApproval">
                                Require manual approval for registrations
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="btn-group">
                    <a href="../organizer/dashboard.php" class="btn-custom btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Dashboard
                    </a>
                    <a href="../../events/view.php?id=<?= $event['event_id'] ?>" class="btn-custom btn-secondary">
                        <i class="fas fa-eye"></i>
                        View Event
                    </a>
                    <button type="submit" name="update_event" class="btn-custom btn-primary">
                        <i class="fas fa-save"></i>
                        Update Event
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    
    <script>
        // Initialize Rich Text Editor
        const quill = new Quill('#description-editor', {
            theme: 'snow',
            placeholder: 'Describe your event in detail...',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    ['link', 'blockquote'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['clean']
                ]
            }
        });

        // Set initial content
        quill.root.innerHTML = `<?= addslashes($formData['description']) ?>`;

        // Sync Quill content with hidden textarea
        quill.on('text-change', function() {
            document.getElementById('descriptionInput').value = quill.root.innerHTML;
        });

        // Image preview function
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const uploadContent = document.querySelector('.upload-content');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    uploadContent.style.display = 'none';
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Form validation
        document.getElementById('editEventForm').addEventListener('submit', function(e) {
            const startDate = new Date(document.querySelector('input[name="start_datetime"]').value);
            const endDate = new Date(document.querySelector('input[name="end_datetime"]').value);
            
            // Check if end date is after start date
            if (endDate <= startDate) {
                alert('End date must be after start date');
                e.preventDefault();
                return;
            }
            
            // Sync Quill content before submission
            document.getElementById('descriptionInput').value = quill.root.innerHTML;
            
            // Show loading state
            const submitBtn = document.querySelector('button[name="update_event"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating Event...';
            submitBtn.disabled = false;
        });

        console.log('✏️ Edit Event Page Loaded');
        console.log('🎯 Event ID: <?= $eventId ?>');
    </script>
</body>
</html>



