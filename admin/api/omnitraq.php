<?php
/**
 * Admin API — Omnitraq install CRUD
 * POST /admin/api/omnitraq.php         — create/update omnitraq install for a truck
 * GET  /admin/api/omnitraq.php?truck_id=X — get install record
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_admin_or_team_leader(true);

$db = getDB();

switch (method()) {
    case 'GET':
        $truckId = input('truck_id');
        if (!$truckId) json_response(['error' => 'truck_id required'], 400);

        $stmt = $db->prepare("
            SELECT o.*, te.nickname AS technician_name
            FROM omnitraq_installs o
            LEFT JOIN technicians te ON te.id = o.technician_id
            WHERE o.truck_id = ?
        ");
        $stmt->execute([$truckId]);
        $record = $stmt->fetch();
        json_response($record ?: ['truck_id' => (int)$truckId, 'status' => 'not_started']);

    case 'POST':
    case 'PUT':
        $data = json_body();
        $truckId = $data['truck_id'] ?? input('truck_id');
        if (!$truckId) json_response(['error' => 'truck_id required'], 400);

        // Check if record exists
        $stmt = $db->prepare("SELECT id FROM omnitraq_installs WHERE truck_id = ?");
        $stmt->execute([$truckId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $db->prepare("
                UPDATE omnitraq_installs
                SET omnitraq_no   = ?,
                    imei          = ?,
                    sim_iccid     = ?,
                    install_date  = ?,
                    technician_id = ?,
                    status        = ?,
                    remarks       = ?,
                    updated_at    = GETDATE()
                WHERE truck_id = ?
            ");
            $stmt->execute([
                $data['omnitraq_no'] ?? null,
                $data['imei'] ?? null,
                $data['sim_iccid'] ?? null,
                $data['install_date'] ?? null,
                $data['technician_id'] ?? null,
                $data['status'] ?? 'not_started',
                $data['remarks'] ?? null,
                $truckId,
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO omnitraq_installs (truck_id, omnitraq_no, imei, sim_iccid, install_date, technician_id, status, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $truckId,
                $data['omnitraq_no'] ?? null,
                $data['imei'] ?? null,
                $data['sim_iccid'] ?? null,
                $data['install_date'] ?? null,
                $data['technician_id'] ?? null,
                $data['status'] ?? 'not_started',
                $data['remarks'] ?? null,
            ]);
        }

        // Sync technician to truck_assignments
        $db->prepare("DELETE FROM truck_assignments WHERE truck_id = ? AND install_type = 'OMNITRAQ'")->execute([$truckId]);
        if (!empty($data['technician_id'])) {
            $db->prepare("
                INSERT INTO truck_assignments (truck_id, technician_id, install_type, assigned_date)
                VALUES (?, ?, 'OMNITRAQ', CAST(GETDATE() AS DATE))
            ")->execute([$truckId, $data['technician_id']]);
        }

        // Update truck timestamp
        touch_updated($db, 'trucks', (int)$truckId);

        json_response(['message' => 'Omnitraq install saved']);

    default:
        json_response(['error' => 'Method not allowed'], 405);
}
