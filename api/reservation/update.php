<?php
// header('Content-Type: application/json');
// header('Cache-Control: no-cache, no-store, must-revalidate');
// header('Pragma: no-cache');
// header('Expires: 0');

// include_once $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/config/db.php';
// require $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/vendor/autoload.php';

// use Firebase\JWT\JWT;
// use Firebase\JWT\Key;

// ob_clean();

// if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
//     $headers = apache_request_headers();
//     $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');

//     if (!$token) {
//         http_response_code(401);
//         echo json_encode(['success' => false, 'message' => 'Token not provided']);
//         exit;
//     }

//     try {
//         global $jwt_secret_key;
//         $decoded = JWT::decode($token, new Key($jwt_secret_key, 'HS256'));
//         $user_id = $decoded->user_id;

//         // Verify admin status
//         $stmt = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
//         $stmt->execute([$user_id]);
//         $user_type = $stmt->fetchColumn();

//         if ($user_type !== 'admin') {
//             http_response_code(403);
//             echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
//             exit;
//         }

//         // Get input data
//         $input = json_decode(file_get_contents('php://input'), true);
//         if (!isset($input['id'], $input['status'])) {
//             http_response_code(400);
//             echo json_encode(['success' => false, 'message' => 'Missing required fields']);
//             exit;
//         }

//         // Update reservation status
//         $stmt = $conn->prepare("UPDATE reservations SET status = ?, updated_at = NOW() WHERE id = ?");
//         $stmt->execute([$input['status'], $input['id']]);

//         echo json_encode(['success' => true, 'message' => 'Reservation status updated successfully']);
//     } catch (Exception $e) {
//         http_response_code(500);
//         echo json_encode(['success' => false, 'message' => 'Failed to update reservation status', 'error' => $e->getMessage()]);
//     }
// }
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

include_once $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/config/db.php';
require $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

ob_clean();

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $headers = apache_request_headers();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');

    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token not provided']);
        exit;
    }

    try {
        global $jwt_secret_key;
        $decoded = JWT::decode($token, new Key($jwt_secret_key, 'HS256'));
        $user_id = $decoded->user_id;

        // Verify admin status
        $stmt = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_type = $stmt->fetchColumn();

        if ($user_type !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
            exit;
        }

        // Get input data
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['id'], $input['phone'], $input['service_type'], $input['reservation_date'], $input['status'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        // Validate status value
        $validStatuses = ['pending', 'confirmed', 'cancelled'];
        if (!in_array($input['status'], $validStatuses)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid status value']);
            exit;
        }

        // Update all fields in the reservation
        $stmt = $conn->prepare("
            UPDATE reservations 
            SET 
                phone = ?, 
                service_type = ?, 
                reservation_date = ?, 
                status = ?, 
                updated_at = NOW() 
            WHERE id = ?
        ");

        $result = $stmt->execute([
            $input['phone'],
            $input['service_type'],
            $input['reservation_date'],
            $input['status'],
            $input['id']
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Reservation updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update reservation']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error occurred', 'error' => $e->getMessage()]);
    }
}
?>
