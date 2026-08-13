<?php
/**
 * Admin API — Locations CRUD
 * GET    /admin/api/locations.php         — list all
 * POST   /admin/api/locations.php         — create (admin)
 * PUT    /admin/api/locations.php?id=X    — rename (admin)
 * DELETE /admin/api/locations.php?id=X    — delete (admin; blocked if in use)
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_admin_or_team_leader(true);

$db = getDB();
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

switch (method()) {
    case 'GET':
        $stmt = $db->query("
            SELECT
                l.id,
                l.name,
                (SELECT COUNT(*) FROM trucks WHERE location_id = l.id AND is_active = 1) AS truck_count,
                (SELECT COUNT(*) FROM technicians WHERE location_id = l.id AND is_active = 1) AS tech_count
            FROM locations l
            ORDER BY l.name ASC
        ");
        json_response(['data' => $stmt->fetchAll()]);

    case 'POST':
        if (!$isAdmin) json_response(['error' => 'Unauthorized'], 403);
        $data = json_body();
        $name = normalize_upper($data['name'] ?? '');
        if ($name === null) json_response(['error' => 'Location name is required'], 422);

        $stmt = $db->prepare("INSERT INTO locations (name) VALUES (?)");
        try {
            $stmt->execute([$name]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UQ') || str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'duplicate')) {
                json_response(['error' => 'A location with that name already exists'], 409);
            }
            throw $e;
        }
        $newLocId = (int)$db->lastInsertId();
        
        // Automatically insert global inventory items for the new location
        $db->prepare("
            INSERT INTO inventory_items (name, linked_system, deduction_type, quantity, location_id, is_global)
            SELECT name, linked_system, deduction_type, 0, ?, 1
            FROM inventory_items
            WHERE location_id IS NULL AND is_global = 1
        ")->execute([$newLocId]);

        json_response(['id' => $newLocId, 'success' => true, 'message' => 'Location created'], 201);

    case 'PUT':
        if (!$isAdmin) json_response(['error' => 'Unauthorized'], 403);
        $id = input('id');
        if (!$id) json_response(['error' => 'ID required'], 400);

        $data = json_body();
        $name = normalize_upper($data['name'] ?? '');
        if ($name === null) json_response(['error' => 'Location name is required'], 422);

        $stmt = $db->prepare("UPDATE locations SET name = ? WHERE id = ?");
        try {
            $stmt->execute([$name, $id]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UQ') || str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'duplicate')) {
                json_response(['error' => 'A location with that name already exists'], 409);
            }
            throw $e;
        }
        if ($stmt->rowCount() === 0) {
            $check = $db->prepare("SELECT id FROM locations WHERE id = ?");
            $check->execute([$id]);
            if (!$check->fetchColumn()) json_response(['error' => 'Location not found'], 404);
        }
        json_response(['success' => true, 'message' => 'Location updated']);

    case 'DELETE':
        if (!$isAdmin) json_response(['error' => 'Unauthorized'], 403);
        $id = input('id');
        if (!$id) json_response(['error' => 'ID required'], 400);

        $check = $db->prepare("SELECT id FROM locations WHERE id = ?");
        $check->execute([$id]);
        if (!$check->fetchColumn()) json_response(['error' => 'Location not found'], 404);

        $inUse = $db->prepare("
            SELECT
                (SELECT COUNT(*) FROM trucks WHERE location_id = ? AND is_active = 1) AS trucks,
                (SELECT COUNT(*) FROM technicians WHERE location_id = ? AND is_active = 1) AS techs
        ");
        $inUse->execute([$id, $id]);
        $counts = $inUse->fetch();
        if ((int)$counts['trucks'] > 0 || (int)$counts['techs'] > 0) {
            json_response([
                'error' => 'Cannot delete: location is assigned to '
                    . (int)$counts['trucks'] . ' truck(s) and '
                    . (int)$counts['techs'] . ' technician(s)',
            ], 409);
        }

        // Clear inactive references then delete
        $db->prepare("UPDATE trucks SET location_id = NULL WHERE location_id = ?")->execute([$id]);
        $db->prepare("UPDATE technicians SET location_id = NULL WHERE location_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM locations WHERE id = ?")->execute([$id]);
        json_response(['success' => true, 'message' => 'Location deleted']);

    default:
        json_response(['error' => 'Method not allowed'], 405);
}
