<?php
/**
 * 🎫 Register for Event - EMS User
 * Allows users to register for a specific event
 */

require_once '../../includes/functions.php';

// Get database connection
$conn = require_once '../../config/database.php';

// Initialize session manager
require_once '../../includes/session.php';
$sessionManager = new SessionManager($conn);
$currentUser = $sessionManager->getCurrentUser();

// Get event ID from query parameter
$eventId = $_GET['id'] ?? null;
if (!$eventId) {
    echo "Invalid event ID.";
    exit;
}

// Fetch event details
$stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ? AND status = 'approved' AND is_public = 1");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) {
    // Event not found or not public/approved
    echo "<div style='max-width:600px; margin: 2rem auto; padding: 1.5rem; background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; border-radius: 8px; font-family: Poppins, sans-serif; font-size: 1.1rem; text-align: center;'>Event not found or is not public/approved.</div>";
    exit;
}

$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $ticket_quantity = intval($_POST['ticket_quantity'] ?? 1);

    // Basic validation
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    if ($event['price'] > 0 && $ticket_quantity < 1) {
        $errors[] = "Ticket quantity must be at least 1";
    }

    // Check if spots are available
    if ($event['price'] == 0) {
        // Free event: count distinct users registered
        $stmtTickets = $conn->prepare("SELECT COUNT(DISTINCT user_id) as total_registered FROM event_registrations WHERE event_id = ?");
        $stmtTickets->bind_param("i", $eventId);
        $stmtTickets->execute();
        $resultTickets = $stmtTickets->get_result()->fetch_assoc();
        $totalRegistered = $resultTickets['total_registered'] ?? 0;
    } else {
        // Paid event: count total tickets registered with payment_status not completed
        $stmtTickets = $conn->prepare("SELECT COUNT(*) as total_registered FROM event_registrations WHERE event_id = ? AND payment_status != 'completed'");
        $stmtTickets->bind_param("i", $eventId);
        $stmtTickets->execute();
        $resultTickets = $stmtTickets->get_result()->fetch_assoc();
        $totalRegistered = $resultTickets['total_registered'] ?? 0;
    }

    if (($totalRegistered + $ticket_quantity) > $event['max_attendees']) {
        $errors[] = "Not enough spots available for the requested ticket quantity";
    }

    if (empty($errors)) {
        // Insert registration(s)
        $userId = $currentUser['user_id'] ?? null;
        $registrationDate = date('Y-m-d H:i:s');
        $status = 'pending';
        $paymentStatus = 'pending';

        $stmtInsert = $conn->prepare("INSERT INTO event_registrations (user_id, event_id, registration_date, `status`, payment_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        if ($stmtInsert === false) {
            die('Prepare failed: ' . htmlspecialchars($conn->error));
        }
        $stmtInsert->bind_param("iisss", $userId, $eventId, $registrationDate, $status, $paymentStatus);

        $successCount = 0;
        if ($event['price'] == 0) {
            // Free event: register current user once
            if ($stmtInsert->execute()) {
                $successCount = 1;
            }
        } else {
            // Paid event: register ticket_quantity times
            for ($i = 0; $i < $ticket_quantity; $i++) {
                if ($stmtInsert->execute()) {
                    $successCount++;
                }
            }
        }

        if ($successCount === $ticket_quantity) {
            $success = "Registration successful! Your tickets are pending payment confirmation.";
        } else {
            $errors[] = "Failed to register all tickets. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Register for Event - <?= htmlspecialchars($event['title']) ?> | EMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
            padding: 2rem;
        }
        .container {
            max-width: 600px;
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .event-info {
            margin-bottom: 1.5rem;
        }
        .event-title {
            font-weight: 700;
            font-size: 1.8rem;
        }
        .event-meta {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .form-label.required::after {
            content: ' *';
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Register for Event</h1>

        <div class="event-info">
            <div class="event-title"><?= htmlspecialchars($event['title']) ?></div>
            <div class="event-meta">
                <div>Date: <?= date('M j, Y', strtotime($event['start_datetime'])) ?></div>
                <div>Venue: <?= htmlspecialchars($event['venue']) ?></div>
                <div>Max Attendees: <?= $event['max_attendees'] ?></div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="mb-3">
                <label for="name" class="form-label required">Full Name</label>
                <input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" />
            </div>
            <div class="mb-3">
                <label for="email" class="form-label required">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" />
            </div>
            <div class="mb-3">
                <label for="phone" class="form-label">Phone Number</label>
                <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" />
            </div>
            <div class="mb-3">
                <label for="ticket_quantity" class="form-label required">Ticket Quantity</label>
                <input type="number" class="form-control" id="ticket_quantity" name="ticket_quantity" min="1" value="<?= htmlspecialchars($_POST['ticket_quantity'] ?? '1') ?>" required />
            </div>
            <button type="submit" name="register" class="btn btn-primary">Register</button>
            <a href="../public/event_details.php?id=<?= $eventId ?>" class="btn btn-secondary ms-2">Back to Event Details</a>
        </form>
    </div>
</body>
</html>
