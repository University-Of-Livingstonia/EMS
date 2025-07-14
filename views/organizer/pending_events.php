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