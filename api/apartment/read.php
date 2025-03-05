<?php
// read_apartments.php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

include_once $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/config/db.php';

ob_clean();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $conn->prepare("
            SELECT 
                a.id, 
                a.apartment_name, 
                a.location, 
                a.price, 
                a.description, 
                a.cover_image, 
                a.created_at, 
                a.updated_at,
                IFNULL(
                    (
                        SELECT CONCAT('[', GROUP_CONCAT(
                            JSON_OBJECT(
                                'id', ai.id,
                                'image_path', ai.image_path
                            )
                        ), ']')
                        FROM apartment_images ai 
                        WHERE ai.apartment_id = a.id
                    ), '[]'
                ) AS apartment_images
            FROM apartments a
            ORDER BY a.created_at DESC
        ");
        $stmt->execute();
        $apartments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Convert apartment_images string to actual JSON array
        foreach ($apartments as &$apartment) {
            $apartment['apartment_images'] = json_decode($apartment['apartment_images'], true) ?: [];
            $apartment['price'] = floatval($apartment['price']);
        }

        echo json_encode(['success' => true, 'apartments' => $apartments]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch apartments', 'error' => $e->getMessage()]);
    }
}

// // Get single apartment
// if (isset($_GET['id'])) {
//     try {
//         $apartment_id = $_GET['id'];
        
//         $stmt = $conn->prepare("
//             SELECT 
//                 a.id, 
//                 a.apartment_name, 
//                 a.location, 
//                 a.price, 
//                 a.description, 
//                 a.cover_image, 
//                 a.created_at, 
//                 a.updated_at,
//                 IFNULL(
//                     (
//                         SELECT CONCAT('[', GROUP_CONCAT(
//                             JSON_OBJECT(
//                                 'id', ai.id,
//                                 'image_path', ai.image_path
//                             )
//                         ), ']')
//                         FROM apartment_images ai 
//                         WHERE ai.apartment_id = a.id
//                     ), '[]'
//                 ) AS apartment_images
//             FROM apartments a
//             WHERE a.id = ?
//         ");
//         $stmt->execute([$apartment_id]);
//         $apartment = $stmt->fetch(PDO::FETCH_ASSOC);
        
//         if (!$apartment) {
//             http_response_code(404);
//             echo json_encode(['success' => false, 'message' => 'Apartment not found']);
//             exit;
//         }
        
//         // Convert apartment_images string to actual JSON array
//         $apartment['apartment_images'] = json_decode($apartment['apartment_images'], true) ?: [];
//         $apartment['price'] = floatval($apartment['price']);
        
//         echo json_encode(['success' => true, 'apartment' => $apartment]);
//     } catch (Exception $e) {
//         http_response_code(500);
//         echo json_encode(['success' => false, 'message' => 'Failed to fetch apartment', 'error' => $e->getMessage()]);
//     }
// }
?>