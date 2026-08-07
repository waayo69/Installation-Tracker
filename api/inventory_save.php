<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_admin_or_team_leader(true);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = !empty($input['id']) ? (int)$input['id'] : null;
$name = trim($input['name'] ?? '');
$linked_system = trim($input['linked_system'] ?? 'none');
$deduction_type = trim($input['deduction_type'] ?? 'optional');
$quantity = (int)($input['quantity'] ?? 0);

init_session();
$role = $_SESSION['role'] ?? '';
$user_location = $_SESSION['location_id'] ?? null;

$location_id = null;
if ($role === 'team_leader') {
    $location_id = $user_location;
} else {
    if (isset($input['location_id']) && $input['location_id'] !== '') {
        $location_id = $input['location_id']; // could be 'ALL' or numeric string
    }
}

$items = $input['items'] ?? [];
if (empty($items)) {
    echo json_encode(['success' => false, 'error' => 'No items provided']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();
    
    // Determine target locations for insertion
    $target_locations = [];
    if ($location_id === 'ALL' && !$id) {
        // Get all locations
        $target_locations[] = null; // HQ
        $locStmt = $db->query("SELECT id FROM locations");
        while ($row = $locStmt->fetch()) {
            $target_locations[] = (int)$row['id'];
        }
    } else {
        $target_locations[] = $location_id !== null && $location_id !== 'ALL' ? (int)$location_id : null;
    }

    foreach ($items as $item) {
        $name = trim($item['name'] ?? '');
        $linked_system = trim($item['linked_system'] ?? 'none');
        $deduction_type = trim($item['deduction_type'] ?? 'optional');
        $quantity = (int)($item['quantity'] ?? 0);
        
        if ($name === '') {
            throw new Exception("Item name cannot be empty");
        }

        foreach ($target_locations as $loc) {
            // Check for duplicates
            if ($id) {
                if ($loc === null) {
                    $checkStmt = $db->prepare("SELECT id FROM dbo.inventory_items WHERE LOWER(name) = LOWER(?) AND location_id IS NULL AND id != ?");
                    $checkStmt->execute([$name, $id]);
                } else {
                    $checkStmt = $db->prepare("SELECT id FROM dbo.inventory_items WHERE LOWER(name) = LOWER(?) AND location_id = ? AND id != ?");
                    $checkStmt->execute([$name, $loc, $id]);
                }
            } else {
                if ($loc === null) {
                    $checkStmt = $db->prepare("SELECT id FROM dbo.inventory_items WHERE LOWER(name) = LOWER(?) AND location_id IS NULL");
                    $checkStmt->execute([$name]);
                } else {
                    $checkStmt = $db->prepare("SELECT id FROM dbo.inventory_items WHERE LOWER(name) = LOWER(?) AND location_id = ?");
                    $checkStmt->execute([$name, $loc]);
                }
            }
            
            if ($checkStmt->fetchColumn()) {
                throw new Exception("An item named '$name' already exists in one of the selected locations.");
            }
            
            if ($id) {
                $stmt = $db->prepare("
                    UPDATE dbo.inventory_items
                    SET name = ?, linked_system = ?, deduction_type = ?, quantity = ?, location_id = ?, updated_at = GETDATE()
                    WHERE id = ?
                ");
                $stmt->execute([$name, $linked_system, $deduction_type, $quantity, $loc, $id]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO dbo.inventory_items (name, linked_system, deduction_type, quantity, location_id)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $linked_system, $deduction_type, $quantity, $loc]);
            }
        }
    }
    
    $db->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
