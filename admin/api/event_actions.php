<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['event_id']) || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$eventId = intval($input['event_id']);
$action = $input['action'];

if (!in_array($action, ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$status = $action === 'approve' ? 'approved' : 'rejected';

try {
    $stmt = $conn->prepare("UPDATE events SET status = ?, updated_at = NOW() WHERE event_id = ?");
    $stmt->bind_param("si", $status, $eventId);
    if ($stmt->execute()) {
        // Log activity
        logActivity($conn, $_SESSION['user_id'], 'event_' . $action, "Event ID $eventId $status by admin");

        echo json_encode(['success' => true, 'message' => "Event $status successfully"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update event status']);
    }
} catch (Exception $e) {
    error_log("Event action error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
