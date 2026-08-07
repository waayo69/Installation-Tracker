<?php
/**
 * Technician API — Update install status/fields
 * POST /tech/api/update_install.php
 *
 * Body: { truck_id, install_type: "OMNITRAQ"|"MDVR"|"DOOR_SENSOR", ...fields }
 * Validates that the truck is assigned to this technician before allowing updates.
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_technician(true);
require_method('POST', 'PUT');

$db     = getDB();
$techId = current_technician_id();
$data   = json_body();

$truckId     = $data['truck_id'] ?? null;
$installType = $data['install_type'] ?? null;

if (!$truckId || !$installType) {
    json_response(['error' => 'truck_id and install_type required'], 400);
}

// Verify this truck is assigned to this technician for this install type
$stmt = $db->prepare("
    SELECT id FROM truck_assignments
    WHERE truck_id = ? AND technician_id = ? AND install_type = ?
");
$stmt->execute([$truckId, $techId, $installType]);
if (!$stmt->fetch()) {
    json_response(['error' => 'This truck/install type is not assigned to you'], 403);
}

switch ($installType) {
    case 'OMNITRAQ':
        $stmt = $db->prepare("SELECT id FROM omnitraq_installs WHERE truck_id = ?");
        $stmt->execute([$truckId]);
        if ($stmt->fetch()) {
            $stmt = $db->prepare("
                UPDATE omnitraq_installs
                SET omnitraq_no   = ISNULL(?, omnitraq_no),
                    imei          = ISNULL(?, imei),
                    sim_iccid     = ISNULL(?, sim_iccid),
                    install_date  = ISNULL(?, install_date),
                    status        = ISNULL(?, status),
                    remarks       = ISNULL(?, remarks),
                    updated_at    = GETDATE()
                WHERE truck_id = ?
            ");
            $stmt->execute([
                $data['omnitraq_no'] ?? null,
                $data['imei'] ?? null,
                $data['sim_iccid'] ?? null,
                $data['install_date'] ?? null,
                $data['status'] ?? null,
                $data['remarks'] ?? null,
                $truckId,
            ]);
        }
        break;

    case 'MDVR':
        $stmt = $db->prepare("SELECT id FROM mdvr_installs WHERE truck_id = ?");
        $stmt->execute([$truckId]);
        if ($stmt->fetch()) {
            $stmt = $db->prepare("
                UPDATE mdvr_installs
                SET mdvr_type          = ISNULL(?, mdvr_type),
                    device_serial      = ISNULL(?, device_serial),
                    sim_iccid          = ISNULL(?, sim_iccid),
                    sim_number         = ISNULL(?, sim_number),
                    install_date       = ISNULL(?, install_date),
                    integrated         = ISNULL(?, integrated),
                    visible            = ISNULL(?, visible),
                    performance_status = ISNULL(?, performance_status),
                    detailed_remarks   = ISNULL(?, detailed_remarks),
                    documentation_link = ISNULL(?, documentation_link),
                    status             = ISNULL(?, status),
                    updated_at         = GETDATE()
                WHERE truck_id = ?
            ");
            $stmt->execute([
                $data['mdvr_type'] ?? null,
                $data['device_serial'] ?? null,
                $data['sim_iccid'] ?? null,
                $data['sim_number'] ?? null,
                $data['install_date'] ?? null,
                isset($data['integrated']) ? ($data['integrated'] ? 1 : 0) : null,
                isset($data['visible']) ? ($data['visible'] ? 1 : 0) : null,
                $data['performance_status'] ?? null,
                $data['detailed_remarks'] ?? null,
                $data['documentation_link'] ?? null,
                $data['status'] ?? null,
                $truckId,
            ]);
        }
        break;

    case 'DOOR_SENSOR':
        $stmt = $db->prepare("SELECT id FROM door_sensor_installs WHERE truck_id = ?");
        $stmt->execute([$truckId]);
        if ($stmt->fetch()) {
            $stmt = $db->prepare("
                UPDATE door_sensor_installs
                SET installed    = ?,
                    install_date = ISNULL(?, install_date),
                    remarks      = ISNULL(?, remarks),
                    updated_at   = GETDATE()
                WHERE truck_id = ?
            ");
            $stmt->execute([
                $data['installed'] ?? 0,
                $data['install_date'] ?? null,
                $data['remarks'] ?? null,
                $truckId,
            ]);
        }
        break;

    default:
        json_response(['error' => 'Invalid install_type'], 422);
}

touch_updated($db, 'trucks', (int)$truckId);

// --- INVENTORY PROCESSING ---
$is_installed = false;
if ($installType === 'DOOR_SENSOR') {
    $is_installed = (isset($data['installed']) && $data['installed'] == 1);
} else {
    $status = $data['status'] ?? '';
    $is_installed = in_array($status, ['installed', 'verified']);
}

// 1. Get all automatic items for this install type
$stmt = $db->prepare("SELECT id FROM inventory_items WHERE linked_system = ? AND deduction_type = 'automatic'");
$stmt->execute([$installType]);
$autoItems = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

// 2. Get passed optional items
$usedOptional = $data['used_inventory_items'] ?? [];
if (!is_array($usedOptional)) {
    $usedOptional = empty($usedOptional) ? [] : [$usedOptional];
}
$usedOptional = array_map('intval', $usedOptional);

$shouldBeUsedIds = [];
if ($is_installed) {
    $shouldBeUsedIds = array_unique(array_merge($autoItems, $usedOptional));
}

// 3. Get current usage
$stmt = $db->prepare("SELECT item_id FROM truck_inventory_usage WHERE truck_id = ? AND install_type = ?");
$stmt->execute([$truckId, $installType]);
$currentIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
$currentIds = array_map('intval', $currentIds);

// 4. Calculate diffs
$toRefund = array_diff($currentIds, $shouldBeUsedIds);
$toDeduct = array_diff($shouldBeUsedIds, $currentIds);

// 5. Apply changes
try {
    $db->beginTransaction();

    // Refunds
    foreach ($toRefund as $itemId) {
        $stmt = $db->prepare("DELETE FROM truck_inventory_usage WHERE truck_id = ? AND item_id = ? AND install_type = ?");
        $stmt->execute([$truckId, $itemId, $installType]);
        
        $stmt = $db->prepare("UPDATE inventory_items SET quantity = quantity + 1, updated_at = GETDATE() WHERE id = ?");
        $stmt->execute([$itemId]);
    }

    // Deducts
    foreach ($toDeduct as $itemId) {
        $stmt = $db->prepare("SELECT id FROM inventory_items WHERE id = ?");
        $stmt->execute([$itemId]);
        if ($stmt->fetch()) {
            $stmt = $db->prepare("INSERT INTO truck_inventory_usage (truck_id, item_id, install_type) VALUES (?, ?, ?)");
            $stmt->execute([$truckId, $itemId, $installType]);
            
            $stmt = $db->prepare("UPDATE inventory_items SET quantity = quantity - 1, updated_at = GETDATE() WHERE id = ?");
            $stmt->execute([$itemId]);
        }
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    error_log("Inventory processing failed: " . $e->getMessage());
}
// --- END INVENTORY PROCESSING ---

json_response(['message' => 'Install updated successfully']);
