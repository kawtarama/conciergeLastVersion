<?php
// create_event.php
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
        if (!isset($input['title']) || !isset($input['description']) || !isset($input['event_date'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        // ✅ Start transaction
        if (!$conn->inTransaction()) { 
            $conn->beginTransaction(); 
        }

        // Handle cover image
        $cover_image = null;
        if (!empty($input['coverImageBase64'])) {
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/uploads/events/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $input['coverImageBase64']));
            $imageName = 'event_cover_' . uniqid() . '.jpg';
            $imagePath = $uploadDir . $imageName;
            
            if (file_put_contents($imagePath, $imageData)) {
                $cover_image = 'uploads/events/' . $imageName;
            }
        }

        // Insert event
        $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, cover_image) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            htmlspecialchars($input['title']),
            $input['description'],
            $input['event_date'],
            $cover_image
        ]);

        $event_id = $conn->lastInsertId();

        // Handle event media
        $media_files = [];
        if (!empty($input['mediaFiles']) && is_array($input['mediaFiles'])) {
            foreach ($input['mediaFiles'] as $mediaFile) {
                if (empty($mediaFile['base64']) || empty($mediaFile['type'])) continue;

                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/uploads/events/media/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileData = base64_decode(preg_replace('#^data:.*?;base64,#i', '', $mediaFile['base64']));
                $fileName = 'event_media_' . uniqid() . '.jpg';
                $filePath = $uploadDir . $fileName;

                if (file_put_contents($filePath, $fileData)) {
                    $stmt = $conn->prepare("INSERT INTO event_media (event_id, file_path, file_type) VALUES (?, ?, ?)");
                    $stmt->execute([
                        $event_id,
                        'uploads/events/media/' . $fileName,
                        $mediaFile['type']
                    ]);
                    $media_files[] = [
                        'id' => $conn->lastInsertId(),
                        'file_path' => 'uploads/events/media/' . $fileName,
                        'file_type' => $mediaFile['type']
                    ];
                }
            }
        }

        // ✅ Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Event created successfully',
            'event' => [
                'id' => $event_id,
                'title' => $input['title'],
                'description' => $input['description'],
                'event_date' => $input['event_date'],
                'cover_image' => $cover_image,
                'media_files' => $media_files
            ]
        ]);

    } catch (Exception $e) {
        // ✅ Check if transaction is active before rolling back
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error occurred', 'error' => $e->getMessage()]);
    }
}
?>
