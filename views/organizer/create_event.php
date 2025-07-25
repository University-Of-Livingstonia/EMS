<?php
/**
 * 🎪 Create Event - Organizer Dashboard
 * Ekwendeni Mighty Campus Event Management System
 * Create Amazing Events! ✨
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

$errors = [];
$success = '';
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $formData = [
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'category' => $_POST['category'] ?? '',
        'start_datetime' => $_POST['start_datetime'] ?? '',
        'end_datetime' => $_POST['end_datetime'] ?? '',
        'venue' => trim($_POST['venue'] ?? ''),
        'max_attendees' => intval($_POST['max_attendees'] ?? 0),
        'price' => floatval($_POST['price'] ?? 0),
        'is_public' => isset($_POST['is_public']) ? 1 : 0,
        'tags' => trim($_POST['tags'] ?? ''),
        'contact_email' => trim($_POST['contact_email'] ?? ''),
        'contact_phone' => trim($_POST['contact_phone'] ?? ''),
        'image' => null
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
    
    if (empty($formData['venue'])) {
        $errors[] = "Event venue is required";
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
        
        if ($startTime <= time()) {
            $errors[] = "Start date must be in the future";
        }
    }
    
    // Handle image upload
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
            $formData['image'] = 'uploads/events/' . $fileName;
        } elseif (empty($errors)) {
            $errors[] = "Failed to upload image";
        }
    }
    
    // If no errors, create the event
    if (empty($errors)) {
        try {
            $tagsJson = json_encode(array_filter(array_map('trim', explode(',', $formData['tags']))));
            $contactInfoJson = json_encode([
                'email' => $formData['contact_email'],
                'phone' => $formData['contact_phone']
            ]);
            
            $stmt = $conn->prepare("
                INSERT INTO events (
                    title, description, category, start_datetime, end_datetime, 
                    venue, max_attendees, price, is_public, organizer_id, 
                    tags, contact_info, image, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            $stmt->bind_param("ssssssidisssss",
                $formData['title'],
                $formData['description'],
                $formData['category'],
                $formData['start_datetime'],
                $formData['end_datetime'],
                $formData['venue'],
                $formData['max_attendees'],
                $formData['price'],
                $formData['is_public'],
                $currentUser['user_id'],
                $tagsJson,
                $contactInfoJson,
                $formData['image']
            );
            
            if ($stmt->execute()) {
                $eventId = $conn->insert_id;
                $success = "Event created successfully! It's now pending admin approval.";
                
                // Clear form data on success
                $formData = [];
                
                // Redirect after short delay
                // Redirect based on user role
                if ($currentUser['role'] === 'organizer') {
                    header("refresh:2;url=../organizer/dashboard.php");
                } elseif ($currentUser['role'] === 'admin') {
                    header("refresh:2;url=../../admin/dashboard.php");
                } else {
                    header("refresh:2;url=../../dashboard/index.php");
                }
            } else {
                $errors[] = "Failed to create event. Please try again.";
                error_log("Create Event Insert Error: " . $stmt->error);
            }
            
        } catch (Exception $e) {
            error_log("Event creation error: " . $e->getMessage());
            $errors[] = "An error occurred while creating the event.";
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event - Organizer Dashboard | EMS</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Date/Time Picker -->
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    
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
            --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
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
        
        .form-control.is-invalid,
        .form-select.is-invalid {
            border-color: #f44336;
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
        
        .image-upload-area.dragover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
        
        .upload-icon {
            font-size: 3rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        
               .upload-text {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            margin-top: 1rem;
        }
        
        /* 🎯 Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
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
        
        /* 🏷️ Tags Input */
        .tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .tag {
            background: var(--primary-gradient);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .tag-remove {
            cursor: pointer;
            font-weight: bold;
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
        
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        /* 💡 Help Tips */
        .help-tip {
            background: rgba(33, 150, 243, 0.1);
            border-left: 4px solid #2196F3;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }
        
        .help-tip-title {
            font-weight: 600;
            color: #2196F3;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .help-tip-text {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin: 0;
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
                    <h1 class="page-title">🎪 Create New Event</h1>
                    <p class="page-subtitle">Bring your amazing event ideas to life!</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="breadcrumb-nav">
                        <a href="../../dashboard/">Dashboard</a> / 
                        <a href="../organizer/dashboard.php">Organizer</a> / 
                        <span>Create Event</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Progress Steps -->
        <div class="progress-steps fade-in">
            <div class="step active">
                <i class="fas fa-edit"></i>
                <span>Event Details</span>
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

        <!-- Help Tip -->
        <div class="help-tip fade-in">
            <div class="help-tip-title">
                <i class="fas fa-lightbulb"></i>
                Pro Tip
            </div>
            <p class="help-tip-text">
                Make your event stand out! Add a compelling title, detailed description, and attractive image. 
                Events with complete information get more registrations.
            </p>
        </div>

        <!-- Create Event Form -->
        <div class="form-container fade-in">
            <form method="POST" enctype="multipart/form-data" id="createEventForm">
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
                               value="<?= htmlspecialchars($formData['title'] ?? '') ?>"
                               required>
                        <div class="form-text">Choose a catchy title that describes your event</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Event Description</label>
                        <div class="editor-container" id="descriptionEditor">
                            <div id="description-editor"></div>
                        </div>
                        <textarea name="description" 
                                  id="descriptionInput" 
                                  style="display: none;"
                                  required><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
                        <div class="form-text">Provide detailed information about your event</div>
                    </div>
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label required">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $value => $label): ?>
                                    <option value="<?= $value ?>" 
                                            <?= ($formData['category'] ?? '') === $value ? 'selected' : '' ?>>
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
                                   value="<?= htmlspecialchars($formData['tags'] ?? '') ?>">
                            <div class="form-text">Add relevant tags to help people find your event</div>
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
                                   value="<?= htmlspecialchars($formData['start_datetime'] ?? '') ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required">End Date & Time</label>
                            <input type="datetime-local" 
                                   name="end_datetime" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($formData['end_datetime'] ?? '') ?>"
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
                        <label class="form-label required">Event Venue</label>
                        <input type="text" 
                               name="venue" 
                               class="form-control" 
                               placeholder="Enter event venue..."
                               value="<?= htmlspecialchars($formData['venue'] ?? '') ?>"
                               required>
                        <div class="form-text">Specify the exact venue or location</div>
                    </div>
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label required">Maximum Attendees</label>
                            <input type="number" 
                                   name="max_attendees" 
                                   class="form-control" 
                                   placeholder="100"
                                   min="1"
                                   value="<?= htmlspecialchars($formData['max_attendees'] ?? '') ?>"
                                   required>
                        </div>
                    </div>
                </div>

                <!-- Pricing Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-money-bill-wave"></i>
                        Pricing
                    </h3>
                    
                    <div class="form-group">
                        <label class="form-label">Ticket Price (MWK)</label>
                        <input type="number" 
                               name="price" 
                               class="form-control" 
                               placeholder="0"
                               min="0"
                               step="0.01"
                               value="<?= htmlspecialchars($formData['price'] ?? '') ?>">
                        <div class="form-text">Set to 0 for free events</div>
                    </div>
                </div>

                <!-- Event Image Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-image"></i>
                        Event Image
                    </h3>
                    
                    <div class="form-group">
                        <label class="form-label">Event Image</label>
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
                                    <strong>Click to upload</strong> or drag and drop<br>
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
                    
                    <div class="form-group">
                        <label class="form-label">Contact Email</label>
                        <input type="email" 
                               name="contact_email" 
                               class="form-control" 
                               placeholder="contact@example.com"
                               value="<?= htmlspecialchars($formData['contact_email'] ?? $currentUser['email']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Contact Phone</label>
                        <input type="tel" 
                               name="contact_phone" 
                               class="form-control" 
                               placeholder="+265 123 456 789"
                               value="<?= htmlspecialchars($formData['contact_phone'] ?? $currentUser['phone_number']) ?>">
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="btn-group">
                    <a href="../organizer/dashboard.php" class="btn-custom btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                    <button type="submit" name="create_event" class="btn-custom btn-primary">
                        <i class="fas fa-plus"></i>
                        Create Event
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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

        // Sync Quill content with hidden textarea
        quill.on('text-change', function() {
            document.getElementById('descriptionInput').value = quill.root.innerHTML;
        });

        // Set initial content if editing
        const initialDescription = `<?= addslashes($formData['description'] ?? '') ?>`;
        if (initialDescription) {
            quill.root.innerHTML = initialDescription;
        }

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

        // Drag and drop functionality
        const uploadArea = document.querySelector('.image-upload-area');
        
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('eventImage').files = files;
                previewImage(document.getElementById('eventImage'));
            }
        });

        // Form validation
        document.getElementById('createEventForm').addEventListener('submit', function(e) {
            const startDate = new Date(document.querySelector('input[name="start_datetime"]').value);
            const endDate = new Date(document.querySelector('input[name="end_datetime"]').value);
            const now = new Date();
            
            // Check if start date is in the future
            if (startDate <= now) {
                alert('Start date must be in the future');
                e.preventDefault();
                return;
            }
            
            // Check if end date is after start date
            if (endDate <= startDate) {
                alert('End date must be after start date');
                e.preventDefault();
                return;
            }
            
            // Sync Quill content before submission
            document.getElementById('descriptionInput').value = quill.root.innerHTML;
            
            // Show loading state
            const submitBtn = document.querySelector('button[name="create_event"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Event...';
            submitBtn.disabled = true;
        });

        // Auto-save draft functionality
        let autoSaveTimer;
        const formInputs = document.querySelectorAll('input, select, textarea');
        
        formInputs.forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(saveDraft, 2000);
            });
        });
        
        quill.on('text-change', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(saveDraft, 2000);
        });

        function saveDraft() {
            const formData = new FormData(document.getElementById('createEventForm'));
            formData.set('description', quill.root.innerHTML);
            
            // Save to localStorage
            const draftData = {};
            for (let [key, value] of formData.entries()) {
                if (key !== 'event_image') { // Don't save file data
                    draftData[key] = value;
                }
            }
            
            localStorage.setItem('eventDraft', JSON.stringify(draftData));
            
            // Show save indicator
            showSaveIndicator();
        }

        function showSaveIndicator() {
            const indicator = document.createElement('div');
            indicator.innerHTML = '<i class="fas fa-check"></i> Draft saved';
            indicator.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #4CAF50;
                color: white;
                padding: 0.5rem 1rem;
                border-radius: 5px;
                font-size: 0.9rem;
                z-index: 9999;
                animation: fadeInOut 2s ease-in-out;
            `;
            
            document.body.appendChild(indicator);
            
            setTimeout(() => {
                if (indicator.parentNode) {
                    indicator.remove();
                }
            }, 2000);
        }

        // Load draft on page load
        window.addEventListener('load', function() {
            const savedDraft = localStorage.getItem('eventDraft');
            if (savedDraft && !<?= !empty($formData) ? 'true' : 'false' ?>) {
                const draftData = JSON.parse(savedDraft);
                
                // Ask user if they want to restore draft
                if (confirm('Found a saved draft. Would you like to restore it?')) {
                    Object.keys(draftData).forEach(key => {
                        const input = document.querySelector(`[name="${key}"]`);
                        if (input) {
                            if (input.type === 'checkbox') {
                                input.checked = draftData[key] === 'on';
                            } else {
                                input.value = draftData[key];
                            }
                        }
                    });
                    
                    // Restore description in Quill
                    if (draftData.description) {
                        quill.root.innerHTML = draftData.description;
                    }
                }
            }
        });

        // Clear draft on successful submission
        <?php if (!empty($success)): ?>
            localStorage.removeItem('eventDraft');
        <?php endif; ?>

        // Add CSS for fade animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInOut {
                0% { opacity: 0; transform: translateY(-10px); }
                20% { opacity: 1; transform: translateY(0); }
                80% { opacity: 1; transform: translateY(0); }
                100% { opacity: 0; transform: translateY(-10px); }
            }
        `;
        document.head.appendChild(style);

        console.log('🎪 Create Event Page Loaded');
        console.log('✨ Ready to create amazing events!');
    </script>
</body>
