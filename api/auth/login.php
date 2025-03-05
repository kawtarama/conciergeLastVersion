<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST");

include_once $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/config/db.php';
require 'vendor/autoload.php'; // Autoload JWT library

use Firebase\JWT\JWT;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['message' => 'Invalid request method']);
    exit;
}

// Read incoming request
$data = json_decode(file_get_contents("php://input"));

// Debug: Log incoming request
file_put_contents('login_debug.log', print_r($data, true)); 

if (!isset($data->email) || !isset($data->password)) {
    echo json_encode(['message' => 'Invalid input']);
    exit;
}

$email = $data->email;
$password = $data->password;

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($password, $user['password'])) {
    // Generate JWT token
    $payload = [
        'user_id' => $user['id'],
        'user_type' => $user['user_type'],
        'exp' => time() + 36000 // Token expires in 10 hours
    ];

    $jwt_secret_key = "gY9mK8z4WqL2VxN1aP6dQbJ3rTf0H7uM5cE"; // Make sure to set your secret key
    $jwt = JWT::encode($payload, $jwt_secret_key, 'HS256');

    unset($user['password']); // Remove password from response

    echo json_encode([
        'message' => 'Login successful',
        'user' => $user,
        'token' => $jwt
    ]);
} else {
    echo json_encode(['message' => 'Invalid credentials']);
}
?>
