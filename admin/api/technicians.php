<?php
/**
 * Admin API — Technicians CRUD
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_admin_or_team_leader(true);

$db = getDB();

switch (method()) {
    case 'GET':
        $id = input('id');
        if ($id) {
            $stmt = $db->prepare("SELECT id, nickname, is_active, created_at, role, location_id FROM technicians WHERE id = ?");
            $stmt->execute([$id]);
            $tech = $stmt->fetch();
            if (!$tech) json_response(['error' => 'Technician not found'], 404);

            // Assignment count
            $stmt2 = $db->prepare("SELECT COUNT(*) FROM truck_assignments WHERE technician_id = ?");
            $stmt2->execute([$id]);
            $tech['assignment_count'] = (int)$stmt2->fetchColumn();
            json_response($tech);
        }

        // List all active technicians
        $showAll = input('all') === '1';
        $where = $showAll ? '1=1' : 't.is_active = 1';
        $params = [];
        if (($_SESSION['role'] ?? '') === 'team_leader') {
            // Same location only — global (NULL) technicians are admin-only
            $where .= ' AND t.location_id = ?';
            $params[] = current_location_id();
        }

        $stmt = $db->prepare("
            SELECT t.id, t.nickname, t.is_active, t.created_at, t.role, t.location_id, l.name AS location_name,
                   (SELECT COUNT(*) FROM truck_assignments WHERE technician_id = t.id) AS assignment_count
            FROM technicians t
            LEFT JOIN locations l ON l.id = t.location_id
            WHERE {$where}
            ORDER BY t.nickname
        ");
        $stmt->execute($params);
        json_response($stmt->fetchAll());

    case 'POST':
        if (($_SESSION['role'] ?? '') === 'team_leader') {
            json_response(['error' => 'Team leaders cannot create technicians'], 403);
        }
        $data = json_body();
        $missing = validate_required($data, ['nickname', 'password']);
        if ($missing) json_response(['error' => 'Missing fields', 'fields' => $missing], 422);

        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $role = $data['role'] ?? 'technician';
        $location_id = parse_location_id_from_data($db, $data);

        $stmt = $db->prepare("INSERT INTO technicians (nickname, password_hash, role, location_id) VALUES (?, ?, ?, ?)");
        try {
            $stmt->execute([strtoupper(trim($data['nickname'])), $hash, $role, $location_id]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UQ_technicians_nickname')) {
                json_response(['error' => 'A technician with that nickname already exists'], 409);
            }
            throw $e;
        }
        json_response(['id' => (int)$db->lastInsertId(), 'message' => 'Technician created'], 201);

    case 'PUT':
        if (($_SESSION['role'] ?? '') === 'team_leader') {
            json_response(['error' => 'Team leaders cannot modify technicians'], 403);
        }
        $id = input('id');
        if (!$id) json_response(['error' => 'ID required'], 400);

        $data = json_body();

        // Update nickname if provided
        if (!empty($data['nickname'])) {
            $stmt = $db->prepare("UPDATE technicians SET nickname = ?, role = ?, location_id = ?, updated_at = GETDATE() WHERE id = ?");
            $role = $data['role'] ?? 'technician';
            $location_id = parse_location_id_from_data($db, $data);
            $stmt->execute([strtoupper(trim($data['nickname'])), $role, $location_id, $id]);
        }

        // Update password if provided (admin sets it)
        if (!empty($data['password'])) {
            $hash = password_hash($data['password'], PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE technicians SET password_hash = ?, updated_at = GETDATE() WHERE id = ?");
            $stmt->execute([$hash, $id]);
        }

        // Update active status
        if (isset($data['is_active'])) {
            $stmt = $db->prepare("UPDATE technicians SET is_active = ?, updated_at = GETDATE() WHERE id = ?");
            $stmt->execute([$data['is_active'] ? 1 : 0, $id]);
        }

        json_response(['message' => 'Technician updated']);

    case 'DELETE':
        if (($_SESSION['role'] ?? '') === 'team_leader') {
            json_response(['error' => 'Team leaders cannot delete technicians'], 403);
        }
        $id = input('id');
        if (!$id) json_response(['error' => 'ID required'], 400);

        // Soft-deactivate (preserve history)
        $stmt = $db->prepare("UPDATE technicians SET is_active = 0, updated_at = GETDATE() WHERE id = ?");
        $stmt->execute([$id]);
        json_response(['message' => 'Technician deactivated']);

    default:
        json_response(['error' => 'Method not allowed'], 405);
}
