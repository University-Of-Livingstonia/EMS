<?php
require_once '../../config/database.php';
require_once '../../includes/session.php';

$sessionManager = initializeSessionManager($conn);

$eventId = $_GET['id'] ?? null;

if (!$eventId || !is_numeric($eventId)) {
    echo "Invalid event ID.";
    exit;
}

$stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ?");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Event not found.";
    exit;
}

$event = $result->fetch_assoc();
$isLoggedIn = $sessionManager->isLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($event['title']); ?> - Event Details</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: auto; background: white; padding: 20px; border-radius: 10px; }
        .event-header h1 { font-size: 28px; margin-bottom: 10px; }
        .event-meta, .event-description { margin: 10px 0; }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4caf50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .btn-login {
            background: #2196F3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="event-header">
            <h1><?php echo htmlspecialchars($event['title']); ?></h1>
            <p><strong>Location:</strong> <?php echo htmlspecialchars($event['venue']); ?></p>
            <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($event['start_datetime'])); ?> to <?php echo date('F j, Y', strtotime($event['end_datetime'])); ?></p>
        </div>

        <div class="event-meta">
            <p><strong>Category:</strong> <?php echo htmlspecialchars($event['category']); ?></p>
            <p><strong>Type:</strong> <?php echo htmlspecialchars($event['event_type']); ?></p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($event['status']); ?></p>
            <p><strong>Max Attendees:</strong> <?php echo htmlspecialchars($event['max_attendees']); ?></p>
            <p><strong>Ticket Price:</strong> K<?php echo number_format($event['price'], 0); ?></p>
        </div>

        <div class="event-description">
            <h3>Description</h3>
            <p><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
        </div>

        <?php if ($isLoggedIn): ?>
            <a href="/EMS/views/user/register_event.php?id=<?php echo $event['event_id']; ?>" class="btn">Register for Event</a>
        <?php else: ?>
            <a href="/EMS/auth/login.php?error=access_denied" class="btn btn-login">Login to Register</a>
        <?php endif; ?>
    </div>
</body>
</html>
