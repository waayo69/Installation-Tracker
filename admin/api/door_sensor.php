<?php
/**
 * Admin API — Door Sensor install CRUD
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

        $stmt = $db->prepare("SELECT * FROM door_sensor_installs WHERE truck_id = ?");
        $stmt->execute([$truckId]);
        $record = $stmt->fetch();
        json_response($record ?: ['truck_id' => (int)$truckId, 'installed' => 0]);

    case 'POST':
    case 'PUT':
        $data = json_body();
        $truckId = $data['truck_id'] ?? input('truck_id');
        if (!$truckId) json_response(['error' => 'truck_id required'], 400);

        $stmt = $db->prepare("SELECT id FROM door_sensor_installs WHERE truck_id = ?");
        $stmt->execute([$truckId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $db->prepare("
                UPDATE door_sensor_installs
                SET installed    = ?,
                    install_date = ?,
                    remarks      = ?,
                    updated_at   = GETDATE()
                WHERE truck_id = ?
            ");
            $stmt->execute([
                $data['installed'] ?? 0,
                $data['install_date'] ?? null,
                $data['remarks'] ?? null,
                $truckId,
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO door_sensor_installs (truck_id, installed, install_date, remarks)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $truckId,
                $data['installed'] ?? 0,
                $data['install_date'] ?? null,
                $data['remarks'] ?? null,
            ]);
        }

        touch_updated($db, 'trucks', (int)$truckId);

        json_response(['message' => 'Door sensor install saved']);

    default:
        json_response(['error' => 'Method not allowed'], 405);
}
