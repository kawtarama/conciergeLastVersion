<?php
// create_apartment.php
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

        // Validate input
        if (!isset($input['apartment_name']) || !isset($input['location']) || 
            !isset($input['price']) || !isset($input['description'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        // Start transaction
        if (!$conn->inTransaction()) {
            $conn->beginTransaction();
        }

        // Handle cover image
        $cover_image = null;
        if (!empty($input['coverImageBase64'])) {
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/uploads/apartments/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $input['coverImageBase64']));
            $imageName = 'apartment_cover_' . uniqid() . '.jpg';
            $imagePath = $uploadDir . $imageName;
            
            if (file_put_contents($imagePath, $imageData)) {
                $cover_image = 'uploads/apartments/' . $imageName;
            }
        }

        // Insert apartment
        $stmt = $conn->prepare("INSERT INTO apartments (apartment_name, location, price, description, cover_image) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            htmlspecialchars($input['apartment_name']),
            htmlspecialchars($input['location']),
            floatval($input['price']),
            $input['description'],
            $cover_image
        ]);

        $apartment_id = $conn->lastInsertId();

        // Handle apartment images
        $apartment_images = [];
        if (!empty($input['apartmentImages']) && is_array($input['apartmentImages'])) {
            foreach ($input['apartmentImages'] as $image) {
                if (empty($image['base64'])) continue;

                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/uploads/apartments/media/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $imageData = base64_decode(preg_replace('#^data:.*?;base64,#i', '', $image['base64']));
                $imageName = 'apartment_image_' . uniqid() . '.jpg';
                $imagePath = $uploadDir . $imageName;

                if (file_put_contents($imagePath, $imageData)) {
                    $stmt = $conn->prepare("INSERT INTO apartment_images (apartment_id, image_path) VALUES (?, ?)");
                    $stmt->execute([
                        $apartment_id,
                        'uploads/apartments/media/' . $imageName
                    ]);
                    $apartment_images[] = [
                        'id' => $conn->lastInsertId(),
                        'image_path' => 'uploads/apartments/media/' . $imageName
                    ];
                }
            }
        }

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Apartment created successfully',
            'apartment' => [
                'id' => $apartment_id,
                'apartment_name' => $input['apartment_name'],
                'location' => $input['location'],
                'price' => floatval($input['price']),
                'description' => $input['description'],
                'cover_image' => $cover_image,
                'apartment_images' => $apartment_images
            ]
        ]);

    } catch (Exception $e) {
        // Check if transaction is active before rolling back
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error occurred', 'error' => $e->getMessage()]);
    }
}
?>