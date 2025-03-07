<?php
// update_riad.php
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
            echo json_encode(['success' => false, 'message' => 'Missing riad ID']);
            exit;
        }

        $conn->beginTransaction();

        // Get existing riad data
        $stmt = $conn->prepare("SELECT * FROM riads WHERE id = ?");
        $stmt->execute([$input['id']]);
        $existingRiad = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingRiad) {
            $conn->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'riad not found']);
            exit;
        }

        // Handle cover image update
        $cover_image = $existingRiad['cover_image'];
        if (!empty($input['coverImageBase64'])) {
            // Delete old cover image
            if ($cover_image) {
                $oldImagePath = $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/' . $cover_image;
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }

            // Save new cover image
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/uploads/riads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $input['coverImageBase64']));
            $imageName = 'riad_cover_' . uniqid() . '.jpg';
            $imagePath = $uploadDir . $imageName;
            
            if (file_put_contents($imagePath, $imageData)) {
                $cover_image = 'uploads/riads/' . $imageName;
            }
        }

        // Update riad
        $stmt = $conn->prepare("
            UPDATE riads 
            SET riad_name = ?, 
                location = ?, 
                price = ?, 
                description = ?, 
                cover_image = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $input['riad_name'] ?? $existingRiad['riad_name'],
            $input['location'] ?? $existingRiad['location'],
            $input['price'] ?? $existingRiad['price'],
            $input['description'] ?? $existingRiad['description'],
            $cover_image,
            $input['id']
        ]);

        // Handle riad images update
        if (isset($input['riadImages'])) {
            // Delete removed images
            if (isset($input['deletedImageIds']) && is_array($input['deletedImageIds'])) {
                foreach ($input['deletedImageIds'] as $imageId) {
                    // Get file path before deletion
                    $stmt = $conn->prepare("SELECT image_path FROM riad_images WHERE id = ? AND riad_id = ?");
                    $stmt->execute([$imageId, $input['id']]);
                    $imageFile = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($imageFile) {
                        // Delete physical file
                        $filePath = $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/' . $imageFile['image_path'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }

                        // Delete database record
                        $stmt = $conn->prepare("DELETE FROM riad_images WHERE id = ? AND riad_id = ?");
                        $stmt->execute([$imageId, $input['id']]);
                    }
                }
            }

            // Add new riad images
            foreach ($input['riadImages'] as $image) {
                if (empty($image['base64'])) continue;

                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/uploads/riads/media/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $imageData = base64_decode(preg_replace('#^data:.*?;base64,#i', '', $image['base64']));
                $imageName = 'riad_image_' . uniqid() . '.jpg';
                $imagePath = $uploadDir . $imageName;

                if (file_put_contents($imagePath, $imageData)) {
                    $stmt = $conn->prepare("INSERT INTO riad_images (riad_id, image_path) VALUES (?, ?)");
                    $stmt->execute([
                        $input['id'],
                        'uploads/riads/media/' . $imageName
                    ]);
                }
            }
        }

        $conn->commit();

        // Fetch updated riad data
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
                        FROM riad_images ai 
                        WHERE ai.riad_id = a.id
                    ), '[]'
                ) AS riad_images
            FROM riads a
            WHERE a.id = ?
        ");
        $stmt->execute([$input['id']]);
        $updatedRiad = $stmt->fetch(PDO::FETCH_ASSOC);

        // Convert riad_images string to actual JSON array
        $updatedRiad['riad_images'] = json_decode($updatedRiad['riad_images'], true) ?: [];
        $updatedRiad['price'] = floatval($updatedRiad['price']);

        echo json_encode([
            'success' => true,
            'message' => 'riad updated successfully',
            'riad' => $updatedRiad
        ]);

    } catch (Exception $e) {
        $conn->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error occurred', 'error' => $e->getMessage()]);
    }
}
?>