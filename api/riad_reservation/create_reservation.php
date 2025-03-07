<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

include_once $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/config/db.php';

ob_clean();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (!isset($input['riad_id'], $input['start_date'], $input['end_date'], $input['status'], $input['phone_number'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    try {
        // Prepare the SQL statement with the 'name' column included (optional)
        $stmt = $conn->prepare("
            INSERT INTO riadreservations (riad_id, start_date, end_date, status, phone_number, name) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $input['riad_id'],      // Integer, no htmlspecialchars needed
            $input['start_date'],
            $input['end_date'],
            $input['status'],       // Enum value, no htmlspecialchars needed
            $input['phone_number'], // Phone number
            $input['name'] ?? null  // Optional 'name' field, defaults to NULL if not provided
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Reservation created successfully',
            'id' => $conn->lastInsertId()
        ]);
        http_response_code(201);
    } catch (Exception $e) {
        // Log the error for debugging
        error_log($e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create reservation', 'error' => $e->getMessage()]);
    }
}
?>