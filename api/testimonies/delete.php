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

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
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
            if (!isset($input['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing testimony ID']);
                exit;
            }

            $testimony_id = $input['id'];

            // Fetch image URL before deleting the testimony
            $stmt = $conn->prepare("SELECT image_url FROM testimonies WHERE id = ?");
            $stmt->execute([$testimony_id]);
            $testimony = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$testimony) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Testimony not found']);
                exit;
            }

            // Delete the testimony from the database
            $stmt = $conn->prepare("DELETE FROM testimonies WHERE id = ?");
            $stmt->execute([$testimony_id]);

            // Delete the associated image if it exists
            if (!empty($testimony['image_url'])) {
                $imagePath = $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/' . $testimony['image_url'];

                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            echo json_encode(['success' => true, 'message' => 'Testimony and associated image deleted successfully']);
            http_response_code(200);
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication failed', 'error' => $e->getMessage()]);
        }
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token not provided']);
    }
}
?>
