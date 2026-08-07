<?php
/**
 * Admin API — MDVR install CRUD
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_admin(true);

$db = getDB();

switch (method()) {
    case 'GET':
        $truckId = input('truck_id');
        if (!$truckId) json_response(['error' => 'truck_id required'], 400);

        $stmt = $db->prepare("
            SELECT m.*, te.nickname AS technician_name
            FROM mdvr_installs m
            LEFT JOIN technicians te ON te.id = m.technician_id
            WHERE m.truck_id = ?
        ");
        $stmt->execute([$truckId]);
        $record = $stmt->fetch();
        json_response($record ?: ['truck_id' => (int)$truckId, 'status' => 'not_started', 'mdvr_type' => 'NEW']);

    case 'POST':
    case 'PUT':
        $data = json_body();
        $truckId = $data['truck_id'] ?? input('truck_id');
        if (!$truckId) json_response(['error' => 'truck_id required'], 400);

        // Validate mdvr_type
        $mdvrType = $data['mdvr_type'] ?? 'NEW';
        if (!in_array($mdvrType, ['NEW', 'OLD'])) {
            json_response(['error' => 'mdvr_type must be NEW or OLD'], 422);
        }

        // Check if record exists
        $stmt = $db->prepare("SELECT id FROM mdvr_installs WHERE truck_id = ?");
        $stmt->execute([$truckId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $db->prepare("
                UPDATE mdvr_installs
                SET mdvr_type          = ?,
                    device_serial      = ?,
                    sim_iccid          = ?,
                    sim_number         = ?,
                    install_date       = ?,
                    technician_id      = ?,
                    integrated         = ?,
                    visible            = ?,
                    performance_status = ?,
                    detailed_remarks   = ?,
                    documentation_link = ?,
                    status             = ?,
                    updated_at         = GETDATE()
                WHERE truck_id = ?
            ");
            $stmt->execute([
                $mdvrType,
                $data['device_serial'] ?? null,
                $data['sim_iccid'] ?? null,
                $data['sim_number'] ?? null,
                $data['install_date'] ?? null,
                $data['technician_id'] ?? null,
                $data['integrated'] ?? 0,
                $data['visible'] ?? 0,
                $data['performance_status'] ?? null,
                $data['detailed_remarks'] ?? null,
                $data['documentation_link'] ?? null,
                $data['status'] ?? 'not_started',
                $truckId,
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO mdvr_installs
                    (truck_id, mdvr_type, device_serial, sim_iccid, sim_number,
                     install_date, technician_id, integrated, visible,
                     performance_status, detailed_remarks, documentation_link, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $truckId,
                $mdvrType,
                $data['device_serial'] ?? null,
                $data['sim_iccid'] ?? null,
                $data['sim_number'] ?? null,
                $data['install_date'] ?? null,
                $data['technician_id'] ?? null,
                $data['integrated'] ?? 0,
                $data['visible'] ?? 0,
                $data['performance_status'] ?? null,
                $data['detailed_remarks'] ?? null,
                $data['documentation_link'] ?? null,
                $data['status'] ?? 'not_started',
            ]);
        }

        // Sync technician to truck_assignments
        $db->prepare("DELETE FROM truck_assignments WHERE truck_id = ? AND install_type = 'MDVR'")->execute([$truckId]);
        if (!empty($data['technician_id'])) {
            $db->prepare("
                INSERT INTO truck_assignments (truck_id, technician_id, install_type, assigned_date)
                VALUES (?, ?, 'MDVR', CAST(GETDATE() AS DATE))
            ")->execute([$truckId, $data['technician_id']]);
        }

        touch_updated($db, 'trucks', (int)$truckId);

        json_response(['message' => 'MDVR install saved']);

    default:
        json_response(['error' => 'Method not allowed'], 405);
}
