<?php
// // Set headers for JSON response and caching
// header('Content-Type: application/json');
// header('Cache-Control: no-cache, no-store, must-revalidate');
// header('Pragma: no-cache');
// header('Expires: 0');

// // Include the database connection file
// include_once $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/config/db.php';

// // Clean the output buffer to ensure no unwanted output interferes with JSON
// ob_clean();

// // Check if the request method is PUT
// if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
//     // Read and decode the JSON input
//     $input = json_decode(file_get_contents('php://input'), true);

//     // Check if the input is valid JSON
//     if ($input === null) {
//         http_response_code(400);
//         echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
//         exit;
//     }

//     // Check if 'id' is provided
//     if (!isset($input['id'])) {
//         http_response_code(400);
//         echo json_encode(['success' => false, 'message' => 'Missing id']);
//         exit;
//     }

//     // Collect fields to update
//     $fieldsToUpdate = [];
//     if (isset($input['apartment_id'])) {
//         $fieldsToUpdate['apartment_id'] = $input['apartment_id'];
//     }
//     if (isset($input['start_date'])) {
//         $fieldsToUpdate['start_date'] = $input['start_date'];
//     }
//     if (isset($input['end_date'])) {
//         $fieldsToUpdate['end_date'] = $input['end_date'];
//     }
//     if (isset($input['status'])) {
//         $fieldsToUpdate['status'] = $input['status'];
//     }
//     if (isset($input['phone_number'])) {
//         $fieldsToUpdate['phone_number'] = $input['phone_number'];
//     }

//     // Check if there are fields to update
//     if (empty($fieldsToUpdate)) {
//         http_response_code(400);
//         echo json_encode(['success' => false, 'message' => 'No fields provided to update']);
//         exit;
//     }

//     try {
//         // Build the SET clause for the UPDATE statement
//         $setClause = [];
//         $parameters = [];
//         foreach ($fieldsToUpdate as $field => $value) {
//             $setClause[] = "$field = ?";
//             $parameters[] = $value;
//         }
//         // Add the id for the WHERE clause
//         $parameters[] = $input['id'];

//         // Prepare the SQL UPDATE statement
//         $sql = "UPDATE Apreservations SET " . implode(', ', $setClause) . " WHERE id = ?";
//         $stmt = $conn->prepare($sql);

//         // Execute the statement
//         $stmt->execute($parameters);

//         // Check if any row was updated
//         if ($stmt->rowCount() === 0) {
//             http_response_code(404);
//             echo json_encode(['success' => false, 'message' => 'Reservation not found']);
//         } else {
//             echo json_encode(['success' => true, 'message' => 'Reservation updated successfully']);
//         }
//     } catch (Exception $e) {
//         http_response_code(500);
//         echo json_encode(['success' => false, 'message' => 'Failed to update reservation', 'error' => $e->getMessage()]);
//     }
// } else {
//     http_response_code(405);
//     echo json_encode(['success' => false, 'message' => 'Invalid request method']);
// }

// Set headers for JSON response and caching
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Include the database connection file
include_once $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/config/db.php';

// Clean the output buffer to ensure no unwanted output interferes with JSON
ob_clean();

// Check if the request method is PUT
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Read and decode the JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Check if the input is valid JSON
    if ($input === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
        exit;
    }

    // Check if 'id' is provided
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing id']);
        exit;
    }

    // Collect fields to update
    $fieldsToUpdate = [];
    if (isset($input['apartment_id'])) {
        $fieldsToUpdate['apartment_id'] = $input['apartment_id'];
    }
    if (isset($input['start_date'])) {
        $fieldsToUpdate['start_date'] = $input['start_date'];
    }
    if (isset($input['end_date'])) {
        $fieldsToUpdate['end_date'] = $input['end_date'];
    }
    if (isset($input['status'])) {
        $fieldsToUpdate['status'] = $input['status'];
    }
    if (isset($input['phone_number'])) {
        $fieldsToUpdate['phone_number'] = $input['phone_number'];
    }
    if (isset($input['name'])) { // Added 'name' to updatable fields
        $fieldsToUpdate['name'] = $input['name'];
    }

    // Check if there are fields to update
    if (empty($fieldsToUpdate)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields provided to update']);
        exit;
    }

    try {
        // Build the SET clause for the UPDATE statement
        $setClause = [];
        $parameters = [];
        foreach ($fieldsToUpdate as $field => $value) {
            $setClause[] = "$field = ?";
            $parameters[] = $value;
        }
        // Add the id for the WHERE clause
        $parameters[] = $input['id'];

        // Prepare the SQL UPDATE statement
        $sql = "UPDATE Apreservations SET " . implode(', ', $setClause) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);

        // Execute the statement
        $stmt->execute($parameters);

        // Check if any row was updated
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Reservation not found']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Reservation updated successfully']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update reservation', 'error' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>