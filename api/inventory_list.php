<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
init_session();
if (empty($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $db = getDB();
    $role = $_SESSION['role'];
    $location_id = $_SESSION['location_id'] ?? null;
    
    if ($role === 'admin' || !$location_id) {
        $stmt = $db->query("SELECT i.*, l.name as location_name 
                            FROM dbo.inventory_items i 
                            LEFT JOIN dbo.locations l ON i.location_id = l.id
                            ORDER BY i.linked_system, i.name");
    } else {
        $stmt = $db->prepare("SELECT i.*, l.name as location_name 
                              FROM dbo.inventory_items i 
                              LEFT JOIN dbo.locations l ON i.location_id = l.id
                              WHERE i.location_id = ? 
                              ORDER BY i.linked_system, i.name");
        $stmt->execute([$location_id]);
    }
    
    $items = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $items]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
