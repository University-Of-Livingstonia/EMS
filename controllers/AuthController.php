<?php
require_once '../models/User.php';
require_once '../includes/mailer.php'; //  Email sender

class AuthController {
    public function register($data) {
        $user = new User();
        $created = $user->create($data); // This should insert into the database

        if ($created) {
            //  Send welcome email
            $email = $data['email'];
            $name = $data['full_name'];
            $subject = "Welcome to UNILIA Event Management System";
            $body = "
                <h3>Hello $name,</h3>
                <p>Your account has been successfully created!</p>
                <p>Start exploring events now.</p>
            ";

            sendEmail($email, $subject, $body); // From mailer.php

            //  Redirect or show success
            header("Location: ../auth/login.php?registered=1");
            exit;
        } else {
            echo "Registration failed.";
        }
    }
}
?>
