<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized');
}

$user_id = $_SESSION['user_id'];

// Sample notifications
$sampleNotifications = [
    [
        'title' => 'Welcome to EMS!',
        'message' => 'Thank you for joining our Event Management System. Explore upcoming events and start registering!',
        'type' => 'system'
    ],
    [
        'title' => 'Event Reminder: Tech Conference 2024',
        'message' => 'Your registered event "Tech Conference 2024" starts tomorrow at 9:00 AM. Don\'t forget to bring your ticket!',
        'type' => 'event_reminder'
    ],
    [
        'title' => 'Payment Successful',
        'message' => 'Your payment of MWK 5,000 for "Annual Sports Day" has been processed successfully. Your ticket is now active.',
        'type' => 'payment_completed'
    ],
    [
        'title' => 'Event Approved',
        'message' => 'Congratulations! Your event "Workshop on Web Development" has been approved and is now live.',
        'type' => 'event_approved'
    ],
    [
        'title' => 'New Registration',
        'message' => 'John Doe has registered for your event "Photography Masterclass". Total registrations: 15',
        'type' => 'new_registration'
    ],
    [
        'title' => 'System Maintenance',
        'message' => 'Scheduled maintenance will occur tonight from 2:00 AM to 4:00 AM. Some features may be temporarily unavailable.',
        'type' => 'system'
    ],
    [
        'title' => 'Event Updated',
        'message' => 'The venue for "Music Festival 2024" has been changed to Main Auditorium. Please check the updated details.',
        'type' => 'event_updated'
    ],
    [
        'title' => 'Ticket Verified',
        'message' => 'Your ticket for "Business Seminar" has been successfully verified. Welcome to the event!',
        'type' => 'ticket_verified'
    ]
];

try {
    $conn->begin_transaction();

    foreach ($sampleNotifications as $notification) {
        $result = createNotification(
            $conn,
            $user_id,
            $notification['title'],
            $notification['message'],
            $notification['type']
        );

        if (!$result['success']) {
            throw new Exception('Failed to create notification: ' . $notification['title']);
        }

        // Add some random read status
        if (rand(1, 3) === 1) {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ?");
            $stmt->bind_param("i", $result['notification_id']);
            $stmt->execute();
        }

        // Add random timestamps
        $randomHours = rand(1, 72);
        $stmt = $conn->prepare("UPDATE notifications SET created_at = DATE_SUB(NOW(), INTERVAL ? HOUR) WHERE notification_id = ?");
        $stmt->bind_param("ii", $randomHours, $result['notification_id']);
        $stmt->execute();
    }

    $conn->commit();
    echo "Sample notifications created successfully!";
} catch (Exception $e) {
    $conn->rollback();
    echo "Error creating sample notifications: " . $e->getMessage();
}
