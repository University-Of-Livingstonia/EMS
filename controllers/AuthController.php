<?php
require_once '../models/User.php';
require_once '../includes/mailer.php'; // Email sender

class AuthController {
    public function register($data) {
        $user = new User();
        $created = $user->create($data); // Insert into DB

        if ($created) {
            $email = $data['email'];
            $firstName = isset($data['first_name']) ? $data['first_name'] : '';
            $lastName = isset($data['last_name']) ? $data['last_name'] : '';
            $name = htmlspecialchars(trim($firstName . ' ' . $lastName));

            $subject = "Welcome to UNILIA Event Management System";

            // ✅ FULL HTML EMAIL BODY
            $body = '
            <!DOCTYPE html>
            <html>
            <head>
              <meta charset="UTF-8">
              <title>Welcome Email</title>
            </head>
            <body style="font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0;">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td align="center" style="padding: 20px 0; background-color:rgb(253, 253, 253);">
                    <img src="https://unilia.ac.mw/wp-content/uploads/2021/11/cropped-unilia_logo-624x91.png" width="365" height="54" alt="University of Livingstonia" style="display: block;">
                  </td>
                </tr>
                <tr>
                  <td align="center">
                    <table width="600" cellpadding="20" cellspacing="0" style="background-color: #ffffff; border-radius: 6px; margin-top: 20px;">
                      <tr>
                        <td>
                          <h2 style="color: #003366;">Welcome, ' . $name . '!</h2>
                          <p style="font-size: 16px; color: #333;">
                            Your account has been successfully created with the University of Livingstonia Event Management System.
                          </p>
                          <p style="font-size: 16px; color: #333;">
                            You can now book events, view tickets, and participate in campus activities.
                          </p>
                          <p style="font-size: 16px; color: #003366;"><strong>Best regards,<br>EMS Team</strong></p>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td align="center" style="padding: 20px; font-size: 12px; color: #888;">
                    &copy; ' . date("Y") . ' University of Livingstonia. All rights reserved.
                  </td>
                </tr>
              </table>
            </body>
            </html>
            ';

            sendEmail($email, $subject, $body); // Uses your mailer.php

            // Redirect to login page
            header("Location: ../auth/login.php?registered=1");
            exit;
        } else {
            echo "Registration failed.";
        }
    }
}
?>
