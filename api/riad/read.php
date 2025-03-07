<?php
// read_riads.php
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
                a.riad_name, 
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
                        FROM riad_images ai 
                        WHERE ai.riad_id = a.id
                    ), '[]'
                ) AS riad_images
            FROM riads a
            ORDER BY a.created_at DESC
        ");
        $stmt->execute();
        $riads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Convert riad_images string to actual JSON array
        foreach ($riads as &$riad) {
            $riad['riad_images'] = json_decode($riad['riad_images'], true) ?: [];
            $riad['price'] = floatval($riad['price']);
        }

        echo json_encode(['success' => true, 'riads' => $riads]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch riads', 'error' => $e->getMessage()]);
    }
}
?>