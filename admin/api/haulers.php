<?php
/**
 * Admin API — Haulers CRUD
 * GET    /admin/api/haulers.php         — list all
 * GET    /admin/api/haulers.php?id=X    — single hauler
 * POST   /admin/api/haulers.php         — create
 * PUT    /admin/api/haulers.php?id=X    — update
 * DELETE /admin/api/haulers.php?id=X    — soft-deactivate
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_admin_or_team_leader(true);

$db = getDB();
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

switch (method()) {
    case 'GET':
        $id = input('id');
        if ($id) {
            $stmt = $db->prepare("SELECT * FROM haulers WHERE id = ?");
            $stmt->execute([$id]);
            $hauler = $stmt->fetch();
            if (!$hauler) json_response(['error' => 'Hauler not found'], 404);

            // Include truck count
            $stmt2 = $db->prepare("SELECT COUNT(*) FROM trucks WHERE hauler_id = ? AND is_active = 1");
            $stmt2->execute([$id]);
            $hauler['truck_count'] = (int)$stmt2->fetchColumn();
            json_response($hauler);
        }

        // List all with search
        $search = input('search', '');
        $where = 'is_active = 1';
        $params = [];
        if ($search) {
            $where .= ' AND name LIKE ?';
            $params[] = "%{$search}%";
        }

        $stmt = $db->prepare("
            SELECT h.*,
                   (SELECT COUNT(*) FROM trucks WHERE hauler_id = h.id AND is_active = 1) AS truck_count
            FROM haulers h
            WHERE {$where}
            ORDER BY h.name
        ");
        $stmt->execute($params);
        json_response($stmt->fetchAll());

    case 'POST':
        if (!$isAdmin) json_response(['error' => 'Unauthorized'], 403);
        $data = json_body();
        $missing = validate_required($data, ['name']);
        if ($missing) json_response(['error' => 'Missing fields', 'fields' => $missing], 422);

        $stmt = $db->prepare("INSERT INTO haulers (name, region) VALUES (?, ?)");
        try {
            $stmt->execute([
                normalize_upper($data['name']),
                normalize_upper($data['region'] ?? null),
            ]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UQ_haulers_name')) {
                json_response(['error' => 'A hauler with that name already exists'], 409);
            }
            throw $e;
        }
        json_response(['id' => (int)$db->lastInsertId(), 'message' => 'Hauler created'], 201);

    case 'PUT':
        $id = input('id');
        if (!$id) json_response(['error' => 'ID required'], 400);

        $data = json_body();
        $missing = validate_required($data, ['name']);
        if ($missing) json_response(['error' => 'Missing fields', 'fields' => $missing], 422);

        $stmt = $db->prepare("UPDATE haulers SET name = ?, region = ?, updated_at = GETDATE() WHERE id = ?");
        try {
            $stmt->execute([
                normalize_upper($data['name']),
                normalize_upper($data['region'] ?? null),
                $id,
            ]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UQ_haulers_name')) {
                json_response(['error' => 'A hauler with that name already exists'], 409);
            }
            throw $e;
        }
        json_response(['message' => 'Hauler updated']);

    case 'DELETE':
        $id = input('id');
        if (!$id) json_response(['error' => 'ID required'], 400);

        // Check for active trucks
        $stmt = $db->prepare("SELECT COUNT(*) FROM trucks WHERE hauler_id = ? AND is_active = 1");
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            json_response(['error' => 'Cannot deactivate hauler with active trucks. Deactivate trucks first.'], 409);
        }

        $stmt = $db->prepare("UPDATE haulers SET is_active = 0, updated_at = GETDATE() WHERE id = ?");
        $stmt->execute([$id]);
        json_response(['message' => 'Hauler deactivated']);

    default:
        json_response(['error' => 'Method not allowed'], 405);
}
