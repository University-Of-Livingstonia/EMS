<?php
/**
 * Application Configuration
 * 
 * This file contains the global configuration settings for the Ekwendeni Mighty Campus
 * Event Management System.
 */

// Application settings
define('APP_NAME', 'Ekwendeni Mighty Campus EMS');
define('APP_URL', 'http://localhost/2025_Projects/EMS');
define('APP_ROOT', dirname(__DIR__));

// URL paths
define('ASSETS_URL', APP_URL . '/assets');
define('CSS_URL', ASSETS_URL . '/css');
define('JS_URL', ASSETS_URL . '/js');
define('IMAGES_URL', ASSETS_URL . '/images');

// Create uploads directory if it doesn't exist
$uploadsDir = APP_ROOT . '/uploads';
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
    mkdir($uploadsDir . '/events', 0755, true);
    mkdir($uploadsDir . '/profiles', 0755, true);
    mkdir($uploadsDir . '/documents', 0755, true);
}
define('UPLOADS_URL', APP_URL . '/uploads');

// Email settings (for sending notifications)
define('MAIL_FROM', 'noreply@unilia.ac.mw');
define('MAIL_FROM_NAME', APP_NAME);

// File upload settings
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('ALLOWED_DOCUMENT_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// Security settings
define('HASH_COST', 10); // For password hashing
define('TOKEN_EXPIRY', 24 * 60 * 60); // 24 hours (in seconds)

// Error reporting (set to 0 in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Time zone
date_default_timezone_set('Africa/Blantyre');

// Session configuration
session_name('ems_session');
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Function to get base URL
function base_url($path = '') {
    return APP_URL . '/' . ltrim($path, '/');
}

// Function to redirect
function redirect($path) {
    header('Location: ' . base_url($path));
    exit;
}

// Function to sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>
