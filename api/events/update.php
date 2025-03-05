<?php
// update_event.php
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
            echo json_encode(['success' => false, 'message' => 'Missing event ID']);
            exit;
        }

        $conn->beginTransaction();

        // Get existing event data
        $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$input['id']]);
        $existingEvent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingEvent) {
            $conn->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Event not found']);
            exit;
        }

        // Handle cover image update
        $cover_image = $existingEvent['cover_image'];
        if (!empty($input['coverImageBase64'])) {
            // Delete old cover image
            if ($cover_image) {
                $oldImagePath = $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/' . $cover_image;
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }

            // Save new cover image
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

        // Update event
        $stmt = $conn->prepare("
            UPDATE events 
            SET title = ?, 
                description = ?, 
                event_date = ?, 
                cover_image = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $input['title'] ?? $existingEvent['title'],
            $input['description'] ?? $existingEvent['description'],
            $input['event_date'] ?? $existingEvent['event_date'],
            $cover_image,
            $input['id']
        ]);

        // Handle media files update
        if (isset($input['mediaFiles'])) {
            // Delete removed media files
            if (isset($input['deletedMediaIds']) && is_array($input['deletedMediaIds'])) {
                foreach ($input['deletedMediaIds'] as $mediaId) {
                    // Get file path before deletion
                    $stmt = $conn->prepare("SELECT file_path FROM event_media WHERE id = ? AND event_id = ?");
                    $stmt->execute([$mediaId, $input['id']]);
                    $mediaFile = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($mediaFile) {
                        // Delete physical file
                        $filePath = $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/' . $mediaFile['file_path'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }

                        // Delete database record
                        $stmt = $conn->prepare("DELETE FROM event_media WHERE id = ? AND event_id = ?");
                        $stmt->execute([$mediaId, $input['id']]);
                    }
                }
            }

            // Add new media files
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
                        $input['id'],
                        'uploads/events/media/' . $fileName,
                        $mediaFile['type']
                    ]);
                }
            }
        }

        $conn->commit();

        // Fetch updated event data
        $stmt = $conn->prepare("
            SELECT 
                e.*, 
                IFNULL(
                    (
                        SELECT CONCAT('[', GROUP_CONCAT(
                            JSON_OBJECT(
                                'id', em.id,
                                'file_path', em.file_path,
                                'file_type', em.file_type
                            )
                        ), ']')
                        FROM event_media em 
                        WHERE em.event_id = e.id
                    ), '[]'
                ) AS media_files
            FROM events e
            WHERE e.id = ?
        ");
        $stmt->execute([$input['id']]);
        $updatedEvent = $stmt->fetch(PDO::FETCH_ASSOC);

        // Convert media_files string to actual JSON array
        $updatedEvent['media_files'] = json_decode($updatedEvent['media_files'], true) ?: [];

        echo json_encode([
            'success' => true,
            'message' => 'Event updated successfully',
            'event' => $updatedEvent
        ]);

    } catch (Exception $e) {
        $conn->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error occurred', 'error' => $e->getMessage()]);
    }
}
