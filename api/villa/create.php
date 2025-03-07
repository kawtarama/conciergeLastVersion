<?php
// create_villa.php
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
        if (!isset($input['villa_name']) || !isset($input['location']) || 
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
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/uploads/villas/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $input['coverImageBase64']));
            $imageName = 'villa_cover_' . uniqid() . '.jpg';
            $imagePath = $uploadDir . $imageName;
            
            if (file_put_contents($imagePath, $imageData)) {
                $cover_image = 'uploads/villas/' . $imageName;
            }
        }

        // Insert villa
        $stmt = $conn->prepare("INSERT INTO villas (villa_name, location, price, description, cover_image) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            htmlspecialchars($input['villa_name']),
            htmlspecialchars($input['location']),
            floatval($input['price']),
            $input['description'],
            $cover_image
        ]);

        $villa_id = $conn->lastInsertId();

        // Handle villa images
        $villa_images = [];
        if (!empty($input['villaImages']) && is_array($input['villaImages'])) {
            foreach ($input['villaImages'] as $image) {
                if (empty($image['base64'])) continue;

                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/uploads/villas/media/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $imageData = base64_decode(preg_replace('#^data:.*?;base64,#i', '', $image['base64']));
                $imageName = 'villa_image_' . uniqid() . '.jpg';
                $imagePath = $uploadDir . $imageName;

                if (file_put_contents($imagePath, $imageData)) {
                    $stmt = $conn->prepare("INSERT INTO villa_images (villa_id, image_path) VALUES (?, ?)");
                    $stmt->execute([
                        $villa_id,
                        'uploads/villas/media/' . $imageName
                    ]);
                    $villa_images[] = [
                        'id' => $conn->lastInsertId(),
                        'image_path' => 'uploads/villas/media/' . $imageName
                    ];
                }
            }
        }

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'villa created successfully',
            'villa' => [
                'id' => $villa_id,
                'villa_name' => $input['villa_name'],
                'location' => $input['location'],
                'price' => floatval($input['price']),
                'description' => $input['description'],
                'cover_image' => $cover_image,
                'villa_images' => $villa_images
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