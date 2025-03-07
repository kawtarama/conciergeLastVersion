<?php
// delete_villa.php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

include_once $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/config/db.php';
require $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

ob_clean();

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
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

        $villa_id = $input['id'];

        // Start transaction
        $conn->beginTransaction();

        // Get villa data before deletion
        $stmt = $conn->prepare("SELECT * FROM villas WHERE id = ?");
        $stmt->execute([$villa_id]);
        $villa = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$villa) {
            $conn->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'villa not found']);
            exit;
        }

        // Get all images before deletion
        $stmt = $conn->prepare("SELECT * FROM villa_images WHERE villa_id = ?");
        $stmt->execute([$villa_id]);
        $villaImages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Delete cover image file
        if ($villa['cover_image']) {
            $coverImagePath = $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/' . $villa['cover_image'];
            if (file_exists($coverImagePath)) {
                unlink($coverImagePath);
            }
        }

        // Delete all villa images
        foreach ($villaImages as $image) {
            $imagePath = $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/' . $image['image_path'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        // Delete villa (will cascade delete image records due to foreign key)
        $stmt = $conn->prepare("DELETE FROM villas WHERE id = ?");
        $stmt->execute([$villa_id]);

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'villa and associated images deleted successfully'
        ]);

    } catch (Exception $e) {
        $conn->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error occurred', 'error' => $e->getMessage()]);
    }
}
?>