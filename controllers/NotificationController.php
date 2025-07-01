<?php
require_once '../includes/mailer.php';

class NotificationController {
    public function sendWelcome($email) {
        $subject = "Welcome to UNILIA EMS";
        $message = "<h3>Thank you for registering.</h3><p>We hope you enjoy the events!</p>";
        sendEmail($email, $subject, $message);
    }
}
?>