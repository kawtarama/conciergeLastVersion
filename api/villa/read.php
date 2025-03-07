<?php
// read_villas.php
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
                a.villa_name, 
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
                        FROM villa_images ai 
                        WHERE ai.villa_id = a.id
                    ), '[]'
                ) AS villa_images
            FROM villas a
            ORDER BY a.created_at DESC
        ");
        $stmt->execute();
        $villas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Convert villa_images string to actual JSON array
        foreach ($villas as &$villa) {
            $villa['villa_images'] = json_decode($villa['villa_images'], true) ?: [];
            $villa['price'] = floatval($villa['price']);
        }

        echo json_encode(['success' => true, 'villas' => $villas]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch villas', 'error' => $e->getMessage()]);
    }
}

// // Get single villa
// if (isset($_GET['id'])) {
//     try {
//         $villa_id = $_GET['id'];
        
//         $stmt = $conn->prepare("
//             SELECT 
//                 a.id, 
//                 a.villa_name, 
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
//                         FROM villa_images ai 
//                         WHERE ai.villa_id = a.id
//                     ), '[]'
//                 ) AS villa_images
//             FROM villas a
//             WHERE a.id = ?
//         ");
//         $stmt->execute([$villa_id]);
//         $villa = $stmt->fetch(PDO::FETCH_ASSOC);
        
//         if (!$villa) {
//             http_response_code(404);
//             echo json_encode(['success' => false, 'message' => 'villa not found']);
//             exit;
//         }
        
//         // Convert villa_images string to actual JSON array
//         $villa['villa_images'] = json_decode($villa['villa_images'], true) ?: [];
//         $villa['price'] = floatval($villa['price']);
        
//         echo json_encode(['success' => true, 'villa' => $villa]);
//     } catch (Exception $e) {
//         http_response_code(500);
//         echo json_encode(['success' => false, 'message' => 'Failed to fetch villa', 'error' => $e->getMessage()]);
//     }
// }
?>