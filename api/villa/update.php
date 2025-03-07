<?php
// update_villa.php
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

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing villa ID']);
            exit;
        }

        $conn->beginTransaction();

        // Get existing villa data
        $stmt = $conn->prepare("SELECT * FROM villas WHERE id = ?");
        $stmt->execute([$input['id']]);
        $existingvilla = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingvilla) {
            $conn->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'villa not found']);
            exit;
        }

        // Handle cover image update
        $cover_image = $existingvilla['cover_image'];
        if (!empty($input['coverImageBase64'])) {
            // Delete old cover image
            if ($cover_image) {
                $oldImagePath = $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/' . $cover_image;
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }

            // Save new cover image
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

        // Update villa
        $stmt = $conn->prepare("
            UPDATE villas 
            SET villa_name = ?, 
                location = ?, 
                price = ?, 
                description = ?, 
                cover_image = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $input['villa_name'] ?? $existingvilla['villa_name'],
            $input['location'] ?? $existingvilla['location'],
            $input['price'] ?? $existingvilla['price'],
            $input['description'] ?? $existingvilla['description'],
            $cover_image,
            $input['id']
        ]);

        // Handle villa images update
        if (isset($input['villaImages'])) {
            // Delete removed images
            if (isset($input['deletedImageIds']) && is_array($input['deletedImageIds'])) {
                foreach ($input['deletedImageIds'] as $imageId) {
                    // Get file path before deletion
                    $stmt = $conn->prepare("SELECT image_path FROM villa_images WHERE id = ? AND villa_id = ?");
                    $stmt->execute([$imageId, $input['id']]);
                    $imageFile = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($imageFile) {
                        // Delete physical file
                        $filePath = $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/' . $imageFile['image_path'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }

                        // Delete database record
                        $stmt = $conn->prepare("DELETE FROM villa_images WHERE id = ? AND villa_id = ?");
                        $stmt->execute([$imageId, $input['id']]);
                    }
                }
            }

            // Add new villa images
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
                        $input['id'],
                        'uploads/villas/media/' . $imageName
                    ]);
                }
            }
        }

        $conn->commit();

        // Fetch updated villa data
        $stmt = $conn->prepare("
            SELECT 
                a.*, 
                IFNULL(
                    (
                        SELECT CONCAT('[', GROUP_CONCAT(
                            JSON_OBJECT(
                                'id', ai.id,
                                'image_path', ai.image_path
                            )
                        ), ']')
                        FROM villa_images ai 
                        WHERE ai.villa_id = a.id
                    ), '[]'
                ) AS villa_images
            FROM villas a
            WHERE a.id = ?
        ");
        $stmt->execute([$input['id']]);
        $updatedvilla = $stmt->fetch(PDO::FETCH_ASSOC);

        // Convert villa_images string to actual JSON array
        $updatedvilla['villa_images'] = json_decode($updatedvilla['villa_images'], true) ?: [];
        $updatedvilla['price'] = floatval($updatedvilla['price']);

        echo json_encode([
            'success' => true,
            'message' => 'villa updated successfully',
            'villa' => $updatedvilla
        ]);

    } catch (Exception $e) {
        $conn->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error occurred', 'error' => $e->getMessage()]);
    }
}
?>