<?php
// Include database connection
include_once $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['name']) && isset($data['email']) && isset($data['password']) && isset($data['userType'])) {
        $name = $data['name'];
        $email = $data['email'];
        $password = $data['password'];
        $userType = $data['userType'];

        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Debugging: Check if connection exists
            if (!$conn) {
                die(json_encode(['message' => 'Database connection failed']));
            }

            // Debugging: Print SQL statement
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, user_type, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");

            if ($stmt) {
                $stmt->bindParam(1, $name);
                $stmt->bindParam(2, $email);
                $stmt->bindParam(3, $hashedPassword);
                $stmt->bindParam(4, $userType);

                // Debugging: Execute and check for success
                if ($stmt->execute()) {
                    echo json_encode(['message' => 'Signup successful']);
                } else {
                    echo json_encode(['message' => 'Signup failed', 'error' => $stmt->errorInfo()]);
                }
            } else {
                echo json_encode(['message' => 'Statement preparation failed', 'error' => $conn->errorInfo()]);
            }
        } catch (PDOException $e) {
            echo json_encode(['message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['message' => 'Invalid input']);
    }
} else {
    echo json_encode(['message' => 'Unsupported request method']);
}
