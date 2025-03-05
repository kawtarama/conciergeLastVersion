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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
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

        // Fetch statistics
        $stats = [];

        // Count total services
        $stmt = $conn->query("SELECT COUNT(*) FROM services");
        $stats['total_services'] = $stmt->fetchColumn();

        // Count total testimonies
        $stmt = $conn->query("SELECT COUNT(*) FROM testimonies");
        $stats['total_testimonies'] = $stmt->fetchColumn();

        // Count total events
        $stmt = $conn->query("SELECT COUNT(*) FROM events");
        $stats['total_events'] = $stmt->fetchColumn();

        // Count total reservations
        $stmt = $conn->query("SELECT COUNT(*) FROM reservations");
        $stats['total_reservations'] = $stmt->fetchColumn();

        // Count reservations by status
        $stmt = $conn->query("
            SELECT status, COUNT(*) as count 
            FROM reservations 
            GROUP BY status
        ");
        $reservations_by_status = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stats['reservations_by_status'] = [
            'pending' => 0,
            'confirmed' => 0,
            'cancelled' => 0
        ];
        
        foreach ($reservations_by_status as $row) {
            $stats['reservations_by_status'][$row['status']] = $row['count'];
        }

        echo json_encode(['success' => true, 'statistics' => $stats]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch statistics',
            'error' => $e->getMessage()
        ]);
    }
}
?>
