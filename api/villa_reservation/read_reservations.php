<?php
// Set headers for JSON response and caching
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Include the database connection file
include_once $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/config/db.php';

// Clean the output buffer to ensure no unwanted output interferes with JSON
ob_clean();

// Check if the request method is GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Prepare the SQL query to fetch reservations from villareservations table
        $stmt = $conn->prepare("
            SELECT id, villa_id, start_date, end_date, status, phone_number, created_at, updated_at
            FROM villareservations
            ORDER BY start_date DESC
        ");
        $stmt->execute();
        
        // Fetch all reservations as an associative array
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Return the reservations as a JSON response
        echo json_encode([
            'success' => true,
            'reservations' => $reservations
        ]);
        http_response_code(200); // OK
    } catch (Exception $e) {
        // Handle any errors and return a JSON error message
        http_response_code(500); // Internal Server Error
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch reservations',
            'error' => $e->getMessage()
        ]);
    }
} else {
    // Return an error if the request method is not GET
    http_response_code(405); // Method Not Allowed
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>