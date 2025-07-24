<?php
class User {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // ✅ Register a new user
    public function create($data) {
        $firstName = $data['first_name'];
        $lastName = $data['last_name'];
        $username = $data['username'];
        $email = $data['email'];
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        $department = $data['department'];
        $phone = $data['phone_number'];
        $role = $data['role'];

        $stmt = $this->conn->prepare("
            INSERT INTO users 
            (first_name, last_name, username, email, password, department, phone_number, role) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            die("MySQL prepare failed: " . $this->conn->error);
        }

        $stmt->bind_param("ssssssss", 
            $firstName, $lastName, $username, $email,
            $password, $department, $phone, $role
        );

        return $stmt->execute();
    }

    // ✅ Set verification token for user by email
    public function setVerificationToken($email, $token) {
        $stmt = $this->conn->prepare("UPDATE users SET verification_token = ?, email_verified = 0 WHERE email = ?");
        $stmt->bind_param("ss", $token, $email);
        return $stmt->execute();
    }

    // ✅ Verify user by token
    public function verifyUserByToken($token) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE verification_token = ? AND email_verified = 0");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            $stmt = $this->conn->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE user_id = ?");
            $stmt->bind_param("i", $user['user_id']);
            return $stmt->execute();
        }

        return false;
    }

    // ✅ Get user by email
    public function getByEmail($email) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // ✅ Store reset token and expiry
    public function storeResetToken($email, $token, $expiry) {
        $stmt = $this->conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
        $stmt->bind_param("sss", $token, $expiry, $email);
        return $stmt->execute();
    }

    // ✅ Update password using valid token
    public function updatePasswordByToken($token, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Check if token is valid and not expired
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expires >= NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            // Use correct primary key: user_id
            $stmt = $this->conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE user_id = ?");
            $stmt->bind_param("si", $hashedPassword, $user['user_id']);
            return $stmt->execute();
        }

        return false; // token invalid or expired
    }

    // ✅ Get user by ID (for profile page)
    public function getById($userId) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // ✅ Update user profile (first/last name, phone, dept)
    public function updateProfile($data) {
        $stmt = $this->conn->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, phone_number = ?, department = ?, updated_at = NOW() 
            WHERE user_id = ?
        ");
        $stmt->bind_param("ssssi", 
            $data['first_name'], 
            $data['last_name'], 
            $data['phone_number'], 
            $data['department'], 
            $data['user_id']
        );
        return $stmt->execute();
    }

    // Set verification code for user by user_id
    public function setVerificationCode($userId, $code) {
        $stmt = $this->conn->prepare("UPDATE users SET verification_token = ?, email_verified = 0 WHERE user_id = ?");
        if (!$stmt) {
            die("MySQL prepare failed in setVerificationCode: " . $this->conn->error);
        }
        $stmt->bind_param("si", $code, $userId);
        return $stmt->execute();
    }

    // Verify user by verification code
    public function verifyUserByCode($userId, $code) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE user_id = ? AND verification_token = ? AND email_verified = 0");
        $stmt->bind_param("is", $userId, $code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            $stmt = $this->conn->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            return $stmt->execute();
        }

        return false;
    }
}
?>
