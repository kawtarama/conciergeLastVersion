<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

include_once $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/config/db.php';

ob_clean();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Fetch only the 'name' column from the 'services' table
        $stmt = $conn->query("SELECT name FROM services");
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'services' => $services
        ]);
        http_response_code(200);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch services',
            'error' => $e->getMessage()
        ]);
    }
}
?>
