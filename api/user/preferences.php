<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $preferences = [
            'email_notifications' => (int)($_POST['email_notifications'] ?? 0),
            'sms_notifications' => (int)($_POST['sms_notifications'] ?? 0),
            'push_notifications' => (int)($_POST['push_notifications'] ?? 0),
            'event_reminders' => (int)($_POST['event_reminders'] ?? 0),
            'payment_notifications' => (int)($_POST['payment_notifications'] ?? 0),
            'marketing_emails' => (int)($_POST['marketing_emails'] ?? 0)
        ];

        $result = updateNotificationPreferences($conn, $user_id, $preferences);

        if ($result['success']) {
            // Log the preference change
            logActivity($conn, $user_id, 'preferences_updated', 'Notification preferences updated');

            echo json_encode([
                'success' => true,
                'message' => 'Preferences updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $result['message']
            ]);
        }
    } catch (Exception $e) {
        error_log("Preferences update error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred while updating preferences'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
