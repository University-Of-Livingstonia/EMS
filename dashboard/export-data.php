<?php
/**
 * 📊 Data Export Handler - EMS
 * Exports user data in various formats
 */

require_once '../includes/functions.php';

// Get database connection
$conn = require_once '../config/database.php';

// Initialize session manager
require_once '../includes/session.php';
$sessionManager = new SessionManager($conn);

// Require login
$sessionManager->requireLogin();
$currentUser = $sessionManager->getCurrentUser();
$userId = $currentUser['user_id'];

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: settings.php');
    exit;
}

$exportTypes = $_POST['export_types'] ?? [];

if (empty($exportTypes)) {
    header('Location: settings.php?error=no_data_selected');
    exit;
}

try {
    $exportData = [];
    $exportData['export_info'] = [
        'user_id' => $userId,
        'export_date' => date('Y-m-d H:i:s'),
        'export_types' => $exportTypes,
        'format_version' => '1.0'
    ];
    
    foreach ($exportTypes as $type) {
        switch ($type) {
            case 'profile':
                $stmt = $conn->prepare("
                    SELECT user_id, username, email, first_name, last_name, 
                           phone, date_of_birth, gender, role, status, 
                           created_at, updated_at
                    FROM users 
                    WHERE user_id = ?
                ");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $exportData['profile'] = $stmt->get_result()->fetch_assoc();
                
                // Remove sensitive data
                unset($exportData['profile']['password']);
                break;
                
            case 'events':
                $stmt = $conn->prepare("
                    SELECT e.*, t.ticket_id, t.payment_status, t.amount_paid,
                           t.created_at as registration_date
                    FROM events e
                    JOIN tickets t ON e.event_id = t.event_id
                    WHERE t.user_id = ?
                    ORDER BY e.start_datetime DESC
                ");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $exportData['events'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                break;
                
            case 'tickets':
                $stmt = $conn->prepare("
                    SELECT t.*, e.title as event_title, e.start_datetime
                    FROM tickets t
                    JOIN events e ON t.event_id = e.event_id
                    WHERE t.user_id = ?
                    ORDER BY t.created_at DESC
                ");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $exportData['tickets'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                break;
                
            case 'notifications':
                $stmt = $conn->prepare("
                    SELECT notification_id, title, message, type, is_read, created_at
                    FROM notifications
                    WHERE user_id = ?
                    ORDER BY created_at DESC
                ");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $exportData['notifications'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                break;
                
            case 'activity':
                $stmt = $conn->prepare("
                    SELECT activity_type, description, ip_address, user_agent, created_at
                    FROM activity_logs
                    WHERE user_id = ?
                    ORDER BY created_at DESC
                ");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $exportData['activity'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                break;
        }
    }
    
    // Create temporary directory for export
    $tempDir = sys_get_temp_dir() . '/ems_export_' . $userId . '_' . time();
    mkdir($tempDir, 0755, true);
    
    // Save JSON file
    $jsonFile = $tempDir . '/user_data.json';
    file_put_contents($jsonFile, json_encode($exportData, JSON_PRETTY_PRINT));
    
    // Create README file
    $readmeContent = "EMS User Data Export\n";
    $readmeContent .= "===================\n\n";
    $readmeContent .= "Export Date: " . date('Y-m-d H:i:s') . "\n";
    $readmeContent .= "User ID: " . $userId . "\n";
    $readmeContent .= "Export Types: " . implode(', ', $exportTypes) . "\n\n";
    $readmeContent .= "Files Included:\n";
    $readmeContent .= "- user_data.json: Your complete data in JSON format\n";
    $readmeContent .= "- README.txt: This file\n\n";
    $readmeContent .= "Data Format: JSON\n";
    $readmeContent .= "Encoding: UTF-8\n\n";
    $readmeContent .= "For questions about this export, contact support.\n";
    
    file_put_contents($tempDir . '/README.txt', $readmeContent);
    
    // Create ZIP file
    $zipFile = $tempDir . '.zip';
    $zip = new ZipArchive();
    
    if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
        $files = scandir($tempDir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $zip->addFile($tempDir . '/' . $file, $file);
            }
        }
        $zip->close();
        
        // Clean up temp directory
        array_map('unlink', glob($tempDir . '/*'));
        rmdir($tempDir);
        
        // Log the export
        logActivity($conn, $userId, 'data_exported', 'User exported their data: ' . implode(', ', $exportTypes));
        
        // Send file to browser
        $filename = 'EMS_UserData_' . $userId . '_' . date('Y-m-d') . '.zip';
        
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($zipFile));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        readfile($zipFile);
        
        // Clean up ZIP file
        unlink($zipFile);
        
    } else {
        throw new Exception('Failed to create ZIP file');
    }
    
} catch (Exception $e) {
    error_log("Data export error: " . $e->getMessage());
    header('Location: settings.php?error=export_failed');
    exit;
}
?>