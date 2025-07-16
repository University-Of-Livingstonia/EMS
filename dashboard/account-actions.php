<?php

/**
 * 🚨 Account Actions Handler - EMS
 * Handles account deactivation and deletion
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

if (!$currentUser['email_verified'] == 1) {
    header('Location: verify_email.php');
    exit;
}
// Set JSON response header
header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$action = $input['action'];
$password = $input['password'] ?? '';

// Verify password
if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit;
}

// Check current password
$stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid password']);
    exit;
}

try {
    switch ($action) {
        case 'deactivate':
            // Deactivate account
            $stmt = $conn->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE user_id = ?");
            $stmt->bind_param("i", $userId);

            if ($stmt->execute()) {
                // Log the action
                logActivity($conn, $userId, 'account_deactivated', 'User deactivated their account');

                // Send notification email (optional)
                // sendAccountDeactivationEmail($currentUser['email'], $currentUser['first_name']);

                echo json_encode(['success' => true, 'message' => 'Account deactivated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to deactivate account']);
            }
            break;

        case 'delete':
            // Verify DELETE confirmation
            if (($input['confirmation'] ?? '') !== 'DELETE') {
                echo json_encode(['success' => false, 'message' => 'Invalid confirmation']);
                exit;
            }

            // Start transaction
            $conn->begin_transaction();

            try {
                // Delete user's tickets
                $stmt = $conn->prepare("DELETE FROM tickets WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();

                // Delete user's notifications
                $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();

                // Delete user's activity logs
                $stmt = $conn->prepare("DELETE FROM activity_logs WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();

                // Delete user's preferences
                $stmt = $conn->prepare("DELETE FROM user_preferences WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();

                // Delete user's search history
                $stmt = $conn->prepare("DELETE FROM search_history WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();

                // If user is organizer, handle their events
                if ($currentUser['role'] === 'organizer') {
                    // Get user's events
                    $stmt = $conn->prepare("SELECT event_id FROM events WHERE organizer_id = ?");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                    foreach ($events as $event) {
                        // Delete tickets for these events
                        $stmt = $conn->prepare("DELETE FROM tickets WHERE event_id = ?");
                        $stmt->bind_param("i", $event['event_id']);
                        $stmt->execute();
                    }

                    // Delete user's events
                    $stmt = $conn->prepare("DELETE FROM events WHERE organizer_id = ?");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                }

                // Finally, delete the user
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();

                // Commit transaction
                $conn->commit();

                // Destroy session
                session_destroy();

                echo json_encode(['success' => true, 'message' => 'Account deleted successfully']);
            } catch (Exception $e) {
                // Rollback transaction
                $conn->rollback();
                throw $e;
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log("Account action error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
