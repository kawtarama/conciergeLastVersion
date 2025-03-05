<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

include_once $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/config/db.php';

ob_clean();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Receive JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        // Validate input
        if (!isset($input['name']) || !isset($input['email']) || !isset($input['message'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        // Insert contact data
        $stmt = $conn->prepare("INSERT INTO contact (name, email, phone, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            htmlspecialchars($input['name']),
            filter_var($input['email'], FILTER_VALIDATE_EMAIL) ? $input['email'] : null,
            $input['phone'] ?? null,
            htmlspecialchars($input['message'])
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Contact submitted successfully',
            'id' => $conn->lastInsertId()
        ]);
        http_response_code(201);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to submit contact',
            'error' => $e->getMessage()
        ]);
    }
}
?>
