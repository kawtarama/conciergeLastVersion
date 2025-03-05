<?php
// read_events.php
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
                e.id, 
                e.title, 
                e.description, 
                e.event_date, 
                e.cover_image, 
                e.created_at, 
                e.updated_at,
                IFNULL(
                    (
                        SELECT CONCAT('[', GROUP_CONCAT(
                            JSON_OBJECT(
                                'id', em.id,
                                'file_path', em.file_path,
                                'file_type', em.file_type
                            )
                        ), ']')
                        FROM event_media em 
                        WHERE em.event_id = e.id
                    ), '[]'
                ) AS media_files
            FROM events e
            ORDER BY e.event_date DESC
        ");
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Convert media_files string to actual JSON array
        foreach ($events as &$event) {
            $event['media_files'] = json_decode($event['media_files'], true) ?: [];
        }

        echo json_encode(['success' => true, 'events' => $events]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch events', 'error' => $e->getMessage()]);
    }
}
?>
