<?php
/**
 * Admin API — Truck-Technician Assignments
 * GET    ?truck_id=X        — list assignments for a truck
 * POST                      — assign technician to truck/install_type
 * DELETE ?id=X              — remove assignment
 *
 * Team leaders may assign/remove only within their location
 * (truck + technician must match TL location; no global techs).
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_admin_or_team_leader(true);

$db = getDB();
$isTL = ($_SESSION['role'] ?? '') === 'team_leader';
$tlLoc = $isTL ? (int)current_location_id() : null;

function assert_tl_can_use_truck(PDO $db, int $truckId, ?int $tlLoc): void {
    if ($tlLoc === null) json_response(['error' => 'Team leader has no location assigned'], 403);
    $stmt = $db->prepare("SELECT location_id FROM trucks WHERE id = ? AND is_active = 1");
    $stmt->execute([$truckId]);
    $loc = $stmt->fetchColumn();
    if ($loc === false) json_response(['error' => 'Truck not found'], 404);
    if ((int)$loc !== $tlLoc) json_response(['error' => 'Truck is outside your location'], 403);
}

function assert_tl_can_use_tech(PDO $db, int $techId, ?int $tlLoc): void {
    if ($tlLoc === null) json_response(['error' => 'Team leader has no location assigned'], 403);
    $stmt = $db->prepare("SELECT location_id, role, is_active FROM technicians WHERE id = ?");
    $stmt->execute([$techId]);
    $tech = $stmt->fetch();
    if (!$tech || !(int)$tech['is_active']) json_response(['error' => 'Technician not found'], 404);
    if ($tech['location_id'] === null || $tech['location_id'] === '') {
        json_response(['error' => 'Global technicians can only be assigned by an admin'], 403);
    }
    if ((int)$tech['location_id'] !== $tlLoc) {
        json_response(['error' => 'Technician is outside your location'], 403);
    }
}

switch (method()) {
    case 'GET':
        $truckId = input('truck_id');
        if (!$truckId) json_response(['error' => 'truck_id required'], 400);
        if ($isTL) assert_tl_can_use_truck($db, (int)$truckId, $tlLoc);

        $stmt = $db->prepare("
            SELECT ta.*, te.nickname AS technician_name
            FROM truck_assignments ta
            INNER JOIN technicians te ON te.id = ta.technician_id
            WHERE ta.truck_id = ?
            ORDER BY ta.install_type
        ");
        $stmt->execute([$truckId]);
        json_response($stmt->fetchAll());

    case 'POST':
        $data = json_body();
        $missing = validate_required($data, ['truck_id', 'technician_id', 'install_type']);
        if ($missing) json_response(['error' => 'Missing fields', 'fields' => $missing], 422);

        if (!in_array($data['install_type'], ['MDVR', 'OMNITRAQ', 'DOOR_SENSOR'])) {
            json_response(['error' => 'Invalid install_type'], 422);
        }

        $truckId = (int)$data['truck_id'];
        $techId  = (int)$data['technician_id'];

        if ($isTL) {
            assert_tl_can_use_truck($db, $truckId, $tlLoc);
            assert_tl_can_use_tech($db, $techId, $tlLoc);
        }

        $stmt = $db->prepare("DELETE FROM truck_assignments WHERE truck_id = ? AND install_type = ?");
        $stmt->execute([$truckId, $data['install_type']]);

        $stmt = $db->prepare("
            INSERT INTO truck_assignments (truck_id, technician_id, install_type, assigned_date)
            VALUES (?, ?, ?, CAST(GETDATE() AS DATE))
        ");
        $stmt->execute([$truckId, $techId, $data['install_type']]);

        switch ($data['install_type']) {
            case 'OMNITRAQ':
                $db->prepare("UPDATE omnitraq_installs SET technician_id = ?, updated_at = GETDATE() WHERE truck_id = ?")
                   ->execute([$techId, $truckId]);
                break;
            case 'MDVR':
                $db->prepare("UPDATE mdvr_installs SET technician_id = ?, updated_at = GETDATE() WHERE truck_id = ?")
                   ->execute([$techId, $truckId]);
                break;
        }

        json_response(['message' => 'Technician assigned'], 201);

    case 'DELETE':
        $id = input('id');
        if (!$id) json_response(['error' => 'ID required'], 400);

        if ($isTL) {
            $stmt = $db->prepare("SELECT truck_id FROM truck_assignments WHERE id = ?");
            $stmt->execute([$id]);
            $truckId = $stmt->fetchColumn();
            if (!$truckId) json_response(['error' => 'Assignment not found'], 404);
            assert_tl_can_use_truck($db, (int)$truckId, $tlLoc);
        }

        $stmt = $db->prepare("DELETE FROM truck_assignments WHERE id = ?");
        $stmt->execute([$id]);
        json_response(['message' => 'Assignment removed']);

    default:
        json_response(['error' => 'Method not allowed'], 405);
}
