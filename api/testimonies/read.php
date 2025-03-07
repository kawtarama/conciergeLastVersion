<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

include_once $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/config/db.php';

ob_clean();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $conn->prepare("SELECT * FROM testimonies ORDER BY created_at DESC");
        $stmt->execute();
        $testimonies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'testimonies' => $testimonies]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch testimonies', 'error' => $e->getMessage()]);
    }
}
?>
