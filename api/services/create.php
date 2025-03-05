<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

include_once $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/config/db.php';
require $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

ob_clean();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $headers = apache_request_headers();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');

    if ($token) {
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

            // Receive JSON input
            $input = json_decode(file_get_contents('php://input'), true);

            // Validate input
            if (!isset($input['name']) || !isset($input['description'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }

            // Handle image upload
            $image_url = null;
            if (!empty($input['imageBase64'])) {
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/uploads/';
                
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $imageData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $input['imageBase64']));
                $imageName = uniqid('service_', true) . '.jpg';
                $imagePath = $uploadDir . $imageName;
                
                if (file_put_contents($imagePath, $imageData) !== false) {
                    $image_url = 'uploads/' . $imageName;
                }
            }

            // Insert service
            $stmt = $conn->prepare("INSERT INTO services (name, description, image_url) VALUES (?, ?, ?)");
            $result = $stmt->execute([
                htmlspecialchars($input['name']),
                $input['description'], 
                $image_url
            ]);

            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Service created successfully', 'id' => $conn->lastInsertId()]);
                http_response_code(201);
            } else {
                error_log("Service insertion failed: " . json_encode($stmt->errorInfo())); // Log the error
                echo json_encode(['success' => false, 'message' => 'Failed to create service']);
                http_response_code(500);
            }

        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication failed', 'error' => $e->getMessage()]);
        }
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token not provided']);
    }
}

// -----------------------------------------------

// header('Content-Type: application/json');
// header('Cache-Control: no-cache, no-store, must-revalidate');
// header('Pragma: no-cache');
// header('Expires: 0');

// include_once $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/config/db.php';
// require $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/vendor/autoload.php';

// use Firebase\JWT\JWT;
// use Firebase\JWT\Key;

// ob_clean();

// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     $headers = apache_request_headers();
//     $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');

//     if ($token) {
//         try {
//             global $jwt_secret_key;

//             $decoded = JWT::decode($token, new Key($jwt_secret_key, 'HS256'));
//             $user_id = $decoded->user_id;

//             // Verify admin status
//             $stmt = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
//             $stmt->execute([$user_id]);
//             $user_type = $stmt->fetchColumn();

//             if ($user_type !== 'admin') {
//                 http_response_code(403);
//                 echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
//                 exit;
//             }

//             // Receive JSON input
//             $input = json_decode(file_get_contents('php://input'), true);

//             // Validate input
//             if (!isset($input['id']) || !isset($input['name']) || !isset($input['description'])) {
//                 http_response_code(400);
//                 echo json_encode(['success' => false, 'message' => 'Missing required fields']);
//                 exit;
//             }

//             // Update service
//             $stmt = $conn->prepare("UPDATE services SET name = ?, description = ? WHERE id = ?");
//             $result = $stmt->execute([
//                 htmlspecialchars($input['name']),
//                 $input['description'],
//                 $input['id']
//             ]);

//             if ($result) {
//                 echo json_encode(['success' => true, 'message' => 'Service updated successfully']);
//                 http_response_code(200);
//             } else {
//                 error_log("Service update failed: " . json_encode($stmt->errorInfo()));
//                 echo json_encode(['success' => false, 'message' => 'Failed to update service']);
//                 http_response_code(500);
//             }

//         } catch (Exception $e) {
//             http_response_code(401);
//             echo json_encode(['success' => false, 'message' => 'Authentication failed', 'error' => $e->getMessage()]);
//         }
//     } else {
//         http_response_code(401);
//         echo json_encode(['success' => false, 'message' => 'Token not provided']);
//     }
// }
?>

