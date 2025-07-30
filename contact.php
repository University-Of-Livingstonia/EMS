<?php
/**
 * 📞 Contact Page - EMS
 * Ekwendeni Mighty Campus Event Management System
 * Get in Touch with Us! 💬
 */

require_once 'includes/functions.php';

// Get database connection
$conn = require_once 'config/database.php';

// Initialize session manager
require_once 'includes/session.php';
$sessionManager = new SessionManager($conn);

// Get current user if logged in
$currentUser = $sessionManager->getCurrentUser();
$isLoggedIn = $sessionManager->isLoggedIn();

$success = '';
$errors = [];

// Handle contact form submission
if ($_POST && isset($_POST['send_message'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (empty($subject)) {
        $errors[] = 'Subject is required';
    }
    
    if (empty($message)) {
        $errors[] = 'Message is required';
    }
    
    // If no errors, save the message
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO contact_messages (name, email, subject, message, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("ssss", $name, $email, $subject, $message);
            
            if ($stmt->execute()) {
                $success = 'Thank you for your message! We\'ll get back to you soon.';
                
                // Clear form data
                $_POST = [];
            } else {
                $errors[] = 'Failed to send message. Please try again.';
            }
        } catch (Exception $e) {
            error_log("Contact form error: " . $e->getMessage());
            $errors[] = 'An error occurred. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - EMS | Ekwendeni Mighty Campus</title>
    
    <!-- Meta Tags -->
    <meta name="description" content="Get in touch with EMS team. Contact us for support, feedback, or any questions about our event management platform.">
    <meta name="keywords" content="contact, support, EMS, Ekwendeni, help, feedback">
    <meta name="author" content="Ekwendeni Mighty Campus">
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom Styles -->
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            --success-gradient: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            --warning-gradient: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            --danger-gradient: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
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
            line-height: 1.6;
            color: var(--text-dark);
            overflow-x: hidden;
        }
        
        /* 🎨 Navigation Bar */
        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
                       box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            padding: 1rem 0;
        }
        
        .navbar.scrolled {
            padding: 0.5rem 0;
            background: rgba(255, 255, 255, 0.98) !important;
        }
        
        .navbar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .navbar-nav .nav-link {
            font-weight: 500;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: #667eea !important;
            transform: translateY(-2px);
        }
        
        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 50%;
            background: var(--primary-gradient);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        
        .navbar-nav .nav-link:hover::after,
        .navbar-nav .nav-link.active::after {
            width: 100%;
        }
        
        .btn-nav {
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-left: 0.5rem;
        }
        
        .btn-login {
            background: transparent;
            border: 2px solid #667eea;
            color: #667eea;
        }
        
        .btn-login:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-register {
            background: var(--primary-gradient);
            border: none;
            color: white;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        /* 📞 Contact Hero Section */
        .contact-hero {
            background: var(--primary-gradient);
            padding: 8rem 0 4rem 0;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .contact-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="60" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="60" cy="40" r="1" fill="rgba(255,255,255,0.1)"/></svg>');
            animation: float 20s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .contact-hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }
        
        .contact-hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }
        
        .contact-hero .lead {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* 📧 Contact Section */
        .contact-section {
            padding: 5rem 0;
            background: var(--light-bg);
            margin-top: -2rem;
            position: relative;
            z-index: 3;
        }
        
        .contact-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .contact-form-section {
            padding: 3rem;
        }
        
        .contact-info-section {
            background: var(--primary-gradient);
            color: white;
            padding: 3rem;
            position: relative;
            overflow: hidden;
        }
        
        .contact-info-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="1" fill="rgba(255,255,255,0.1)"/></svg>');
            animation: float 15s ease-in-out infinite;
        }
        
        .contact-info-content {
            position: relative;
            z-index: 2;
        }
        
        .section-title {
            margin-bottom: 2rem;
        }
        
        .section-title h2 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-dark);
        }
        
        .section-title p {
            font-size: 1.1rem;
            color: var(--text-muted);
        }
        
        .contact-info-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: white;
        }
        
        .contact-info-text {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        .contact-info-item {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        
        .contact-info-item:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(10px);
        }
        
        .contact-info-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.2rem;
        }
        
        .contact-info-details h6 {
            margin: 0;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .contact-info-details p {
            margin: 0;
            opacity: 0.8;
            font-size: 0.9rem;
        }
        
        /* 📝 Contact Form */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 15px;
            padding: 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }
        
        .form-control::placeholder {
            color: var(--text-muted);
            opacity: 0.7;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn-submit {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 25px;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        /* 🚨 Alerts */
        .alert {
            border-radius: 15px;
            padding: 1rem 1.5rem;
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
        
        /* 🗺️ Map Section */
        .map-section {
            padding: 5rem 0;
            background: white;
        }
        
        .map-container {
            background: var(--light-bg);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            box-shadow: var(--shadow);
        }
        
        .map-placeholder {
            background: var(--primary-gradient);
            color: white;
            padding: 4rem 2rem;
            border-radius: 15px;
            margin-bottom: 1rem;
        }
        
        .map-placeholder i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .map-placeholder h4 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .map-placeholder p {
            opacity: 0.9;
            margin: 0;
        }
        
        /* 📱 FAQ Section */
        .faq-section {
            padding: 5rem 0;
            background: var(--light-bg);
        }
        
        .faq-item {
            background: white;
            border-radius: 15px;
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .faq-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        
        .faq-question {
            padding: 1.5rem;
            background: white;
            border: none;
            width: 100%;
            text-align: left;
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--text-dark);
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .faq-question:hover {
            background: var(--light-bg);
        }
        
        .faq-answer {
            padding: 0 1.5rem 1.5rem 1.5rem;
            color: var(--text-muted);
            line-height: 1.6;
            display: none;
        }
        
        .faq-answer.show {
            display: block;
            animation: fadeInDown 0.3s ease;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .faq-icon {
            transition: transform 0.3s ease;
        }
        
        .faq-icon.rotate {
            transform: rotate(180deg);
        }
        
        /* 📱 Responsive Design */
        @media (max-width: 768px) {
            .contact-hero h1 {
                font-size: 2.5rem;
            }
            
            .contact-hero .lead {
                font-size: 1.1rem;
            }
            
            .contact-form-section,
            .contact-info-section {
                padding: 2rem;
            }
            
            .section-title h2 {
                font-size: 2rem;
            }
            
            .contact-info-title {
                font-size: 1.5rem;
            }
        }
        
        /* 🎨 Animations */
        .fade-in-up {
            animation: fadeInUp 0.8s ease-out;
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
        
        .slide-in-left {
            animation: slideInLeft 0.8s ease-out;
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .slide-in-right {
            animation: slideInRight 0.8s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">🎪 EMS</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="events.php">Events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="contact.php">Contact</a>
                    </li>
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item">
                            <a class="btn-nav btn-login" href="dashboard/">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn-nav btn-register" href="auth/logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="btn-nav btn-login" href="auth/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn-nav btn-register" href="auth/register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contact Hero Section -->
    <section class="contact-hero">
        <div class="container">
            <div class="contact-hero-content fade-in-up">
                <h1>Contact Us</h1>
                <p class="lead">Have questions, suggestions, or need support? We'd love to hear from you! Get in touch with our team.</p>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section">
        <div class="container">
            <div class="contact-card">
                <div class="row g-0">
                    <div class="col-lg-8">
                        <div class="contact-form-section">
                            <div class="section-title fade-in-up">
                                <h2>Send us a Message</h2>
                                <p>Fill out the form below and we'll get back to you as soon as possible</p>
                            </div>

                            <?php if ($success): ?>
                                <div class="alert alert-success fade-in-up">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?= htmlspecialchars($success) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger fade-in-up">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <form method="POST" class="slide-in-left">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="name" class="form-label">Full Name *</label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="name" 
                                                   name="name" 
                                                   placeholder="Enter your full name"
                                                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="email" class="form-label">Email Address *</label>
                                            <input type="email" 
                                                   class="form-control" 
                                                   id="email" 
                                                   name="email" 
                                                   placeholder="Enter your email address"
                                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                                   required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="subject" class="form-label">Subject *</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="subject" 
                                           name="subject" 
                                           placeholder="What is this about?"
                                           value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="message" class="form-label">Message *</label>
                                    <textarea class="form-control" 
                                              id="message" 
                                              name="message" 
                                              rows="5" 
                                              placeholder="Tell us more about your inquiry..."
                                              required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                                </div>
                                
                                <button type="submit" name="send_message" class="btn-submit">
                                    <i class="fas fa-paper-plane me-2"></i>
                                    Send Message
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="contact-info-section">
                            <div class="contact-info-content">
                                <h3 class="contact-info-title">Get in Touch</h3>
                                <p class="contact-info-text">
                                    We're here to help! Reach out to us through any of these channels.
                                </p>
                                
                                <div class="contact-info-item">
                                    <div class="contact-info-icon">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <div class="contact-info-details">
                                        <h6>Address</h6>
                                        <p>Ekwendeni Mission<br>P.O. Box 19<br>Ekwendeni, Mzimba<br>Malawi</p>
                                    </div>
                                </div>
                                
                                <div class="contact-info-item">
                                    <div class="contact-info-icon">
                                        <i class="fas fa-phone"></i>
                                    </div>
                                    <div class="contact-info-details">
                                        <h6>Phone</h6>
                                        <p>+265 1 362 333<br>+265 1 362 444</p>
                                    </div>
                                </div>
                                
                                <div class="contact-info-item">
                                    <div class="contact-info-icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="contact-info-details">
                                        <h6>Email</h6>
                                        <p>info@unilia.ac.mw<br>ems@unilia.ac.mw</p>
                                    </div>
                                </div>
                                
                                <div class="contact-info-item">
                                    <div class="contact-info-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="contact-info-details">
                                        <h6>Office Hours</h6>
                                        <p>Monday - Friday<br>8:00 AM - 5:00 PM</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Map Section -->
    <section class="map-section">
        <div class="container">
            <div class="section-title text-center fade-in-up">
                <h2>Find Us</h2>
                <p>Located in the heart of Ekwendeni, Mzimba District</p>
            </div>
            
            <div class="map-container fade-in-up">
                <div class="map-placeholder">
                    <i class="fas fa-map-marked-alt"></i>
                    <h4>Ekwendeni Mighty Campus</h4>
                    <p>Interactive map coming soon! For now, you can find us at Ekwendeni Mission, Mzimba District, Malawi.</p>
                </div>
                <p class="text-muted mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    We're located near the Ekwendeni Hospital and Mission Station. 
                    Look for the main campus buildings with the EMS signage.
                </p>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section">
        <div class="container">
            <div class="section-title text-center fade-in-up">
                <h2>Frequently Asked Questions</h2>
                <p>Quick answers to common questions about EMS</p>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="faq-item fade-in-up">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            How do I create an account on EMS?
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </button>
                        <div class="faq-answer">
                            Click on the "Register" button in the top navigation, fill out the registration form with your details, 
                            and verify your email address. Once verified, you can start using EMS to register for events or create your own!
                        </div>
                    </div>
                    
                    <div class="faq-item fade-in-up">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            How do I register for an event?
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </button>
                        <div class="faq-answer">
                            Browse events on our Events page, click on the event you're interested in, and click the "Register" button. 
                            You'll need to be logged in to register. Some events may require payment or have specific requirements.
                        </div>
                    </div>
                    
                    <div class="faq-item fade-in-up">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            Can I create my own events?
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </button>
                        <div class="faq-answer">
                            Yes! If you're a registered user, you can request organizer privileges by contacting us. 
                            Once approved, you'll be able to create and manage your own events through the organizer dashboard.
                        </div>
                    </div>
                    
                    <div class="faq-item fade-in-up">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            What types of events can be hosted on EMS?
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </button>
                        <div class="faq-answer">
                            EMS supports all types of campus events including academic conferences, workshops, seminars, 
                            cultural events, sports activities, social gatherings, and more. All events must comply with campus policies.
                        </div>
                    </div>
                    
                    <div class="faq-item fade-in-up">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            Is there a cost to use EMS?
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </button>
                        <div class="faq-answer">
                            EMS is free to use for all Ekwendeni Mighty Campus community members. 
                            However, individual events may have their own registration fees set by the organizers.
                        </div>
                    </div>
                    
                    <div class="faq-item fade-in-up">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            How do I get technical support?
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </button>
                        <div class="faq-answer">
                            You can reach our technical support team through this contact form, email us at ems@unilia.ac.mw, 
                            or visit our office during business hours. We typically respond within 24 hours.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5 class="mb-3">🎪 EMS</h5>
                    <p class="text-muted">
                        Ekwendeni Mighty Campus Event Management System - 
                        Connecting our community through amazing events and experiences.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-white">
                            <i class="fab fa-facebook fa-lg"></i>
                        </a>
                        <a href="#" class="text-white">
                            <i class="fab fa-twitter fa-lg"></i>
                        </a>
                        <a href="#" class="text-white">
                            <i class="fab fa-instagram fa-lg"></i>
                        </a>
                        <a href="#" class="text-white">
                            <i class="fab fa-linkedin fa-lg"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                                                <li><a href="index.php" class="text-muted">Home</a></li>
                        <li><a href="events.php" class="text-muted">Events</a></li>
                        <li><a href="about.php" class="text-muted">About</a></li>
                        <li><a href="contact.php" class="text-muted">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <h6 class="mb-3">For Users</h6>
                    <ul class="list-unstyled">
                        <li><a href="auth/register.php" class="text-muted">Register</a></li>
                        <li><a href="auth/login.php" class="text-muted">Login</a></li>
                        <li><a href="dashboard/" class="text-muted">Dashboard</a></li>
                        <li><a href="#" class="text-muted">Help Center</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <h6 class="mb-3">Contact Info</h6>
                    <ul class="list-unstyled text-muted">
                        <li><i class="fas fa-map-marker-alt me-2"></i>Ekwendeni, Mzimba, Malawi</li>
                        <li><i class="fas fa-phone me-2"></i>+265 1 362 333</li>
                        <li><i class="fas fa-envelope me-2"></i>info@unilia.ac.mw</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-muted">&copy; 2024 EMS - Ekwendeni Mighty Campus. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-muted me-3">Privacy Policy</a>
                    <a href="#" class="text-muted">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // FAQ Toggle Function
        function toggleFAQ(button) {
            const answer = button.nextElementSibling;
            const icon = button.querySelector('.faq-icon');
            
            // Close all other FAQs
            document.querySelectorAll('.faq-answer').forEach(item => {
                if (item !== answer) {
                    item.classList.remove('show');
                }
            });
            
            document.querySelectorAll('.faq-icon').forEach(item => {
                if (item !== icon) {
                    item.classList.remove('rotate');
                }
            });
            
            // Toggle current FAQ
            answer.classList.toggle('show');
            icon.classList.toggle('rotate');
        }

        // Form validation and enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const submitBtn = document.querySelector('.btn-submit');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
                });
            }
            
            // Auto-resize textarea
            const textarea = document.querySelector('textarea');
            if (textarea) {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
            }
        });

        // Animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all animated elements
        document.querySelectorAll('.fade-in-up, .slide-in-left, .slide-in-right').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            observer.observe(el);
        });

        // Contact form character counter
        const messageField = document.getElementById('message');
        if (messageField) {
            const maxLength = 1000;
            const counter = document.createElement('small');
            counter.className = 'text-muted';
            counter.style.float = 'right';
            messageField.parentNode.appendChild(counter);
            
            function updateCounter() {
                const remaining = maxLength - messageField.value.length;
                counter.textContent = `${remaining} characters remaining`;
                
                if (remaining < 50) {
                    counter.className = 'text-warning';
                } else if (remaining < 0) {
                    counter.className = 'text-danger';
                } else {
                    counter.className = 'text-muted';
                }
            }
            
            messageField.addEventListener('input', updateCounter);
            updateCounter();
        }

        console.log('📞 Contact Page Loaded Successfully!');
    </script>
</body>
</html>


