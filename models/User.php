<?php
class User {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

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
}
?>
