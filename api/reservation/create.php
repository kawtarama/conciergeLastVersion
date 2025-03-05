<?php

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

include_once $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/config/db.php';

ob_clean();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (!isset($input['phone'], $input['service_type'], $input['reservation_date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    // Default status to 'pending' if not provided
    $status = isset($input['status']) ? $input['status'] : 'pending';
    $validStatuses = ['pending', 'confirmed', 'cancelled'];

    // Validate the status value
    if (!in_array($status, $validStatuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status value']);
        exit;
    }

    try {
        // Insert reservation with full validation
        $stmt = $conn->prepare("
            INSERT INTO reservations (phone, service_type, reservation_date, status) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            htmlspecialchars($input['phone']),
            htmlspecialchars($input['service_type']),
            $input['reservation_date'],
            $status
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Reservation created successfully',
            'id' => $conn->lastInsertId()
        ]);
        http_response_code(201);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create reservation',
            'error' => $e->getMessage()
        ]);
    }
}
?>
