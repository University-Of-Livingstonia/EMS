<?php
/**
 * 💾 Backup Download API - EMS Admin
 * Secure backup file download
 */

require_once '../../includes/functions.php';

// Get database connection
$conn = require_once '../../config/database.php';

// Initialize session manager
require_once '../../includes/session.php';
$sessionManager = new SessionManager($conn);

// Require admin login
$sessionManager->requireLogin();
$currentUser = $sessionManager->getCurrentUser();

// Check if user is admin
if ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    die('Access denied');
}

$filename = $_GET['file'] ?? '';

if (!$filename) {
    http_response_code(400);
    die('No file specified');
}

// Sanitize filename
$filename = basename($filename);
$filepath = '../backups/' . $filename;

// Check if file exists and is a backup file
if (!file_exists($filepath) || !preg_match('/^ems_backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $filename)) {
    http_response_code(404);
    die('File not found or invalid');
}

// Set headers for download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Output file
readfile($filepath);
exit;
?>