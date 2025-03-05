<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

include_once $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/config/db.php';
require $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Clear output buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Verify endpoint
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method Not Allowed']));
}

// Verify token
$headers = apache_request_headers();
$token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');

if (!$token) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Token not provided']));
}

try {
    global $jwt_secret_key;
    $decoded = JWT::decode($token, new Key($jwt_secret_key, 'HS256'));

    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    error_log('Received input: ' . json_encode($input));

    if (!isset($input['id']) || !isset($input['name']) || !isset($input['location']) || !isset($input['content'])) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Missing required fields']));
    }

    $testimony_id = $input['id'];

    // Check if testimony exists and get current image
    $stmt = $conn->prepare("SELECT * FROM testimonies WHERE id = ?");
    $stmt->execute([$testimony_id]);
    $existingTestimony = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingTestimony) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'Testimony not found']));
    }

    // Initialize image URL with existing one
    $image_url = $existingTestimony['image_url'];

    // Handle new image if provided
    if (!empty($input['imageBase64'])) {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/uploads/';

        // Create uploads directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Delete old image if it exists
        if (!empty($existingTestimony['image_url'])) {
            $oldImagePath = $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/' . $existingTestimony['image_url'];
            if (file_exists($oldImagePath)) {
                unlink($oldImagePath);
                error_log('Deleted old image: ' . $oldImagePath);
            }
        }

        // Process and save new image
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $input['imageBase64']));
        $imageName = 'testimony_' . uniqid() . '.jpg';
        $imagePath = $uploadDir . $imageName;

        if (file_put_contents($imagePath, $imageData)) {
            $image_url = 'uploads/' . $imageName;
            error_log('Saved new image: ' . $image_url);
        } else {
            error_log('Failed to save new image');
            throw new Exception('Failed to save new image');
        }
    }

    // Update testimony in database
    $updateStmt = $conn->prepare("
        UPDATE testimonies 
        SET name = ?, 
            location = ?, 
            content = ?, 
            image_url = ?, 
            created_at = NOW() 
        WHERE id = ?
    ");

    $success = $updateStmt->execute([
        htmlspecialchars($input['name']),
        htmlspecialchars($input['location']),
        htmlspecialchars($input['content']),
        $image_url,
        $testimony_id
    ]);

    if ($success) {
        // Fetch updated testimony data
        $fetchStmt = $conn->prepare("SELECT * FROM testimonies WHERE id = ?");
        $fetchStmt->execute([$testimony_id]);
        $updatedTestimony = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        error_log('Update successful. Response: ' . json_encode([
            'success' => true,
            'message' => 'Testimony updated successfully',
            'testimony' => $updatedTestimony
        ]));

        die(json_encode([
            'success' => true,
            'message' => 'Testimony updated successfully',
            'testimony' => $updatedTestimony
        ]));
    } else {
        throw new Exception('Database update failed');
    }
} catch (Exception $e) {
    error_log('Update error: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Server error occurred',
        'error' => $e->getMessage()
    ]));
}
?>
