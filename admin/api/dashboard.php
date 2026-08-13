<?php
/**
 * Admin API — Dashboard summary stats
 * GET /admin/api/dashboard.php
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_admin_or_team_leader(true);
require_method('GET');

$db = getDB();

$location = (int)input('location');
if (($_SESSION['role'] ?? '') === 'team_leader') {
    $location = (int)current_location_id();
}

$fromTrucks = "FROM trucks t LEFT JOIN locations l ON l.id = t.location_id";

// Build WHERE clause
$where = 't.is_active = 1';
$params = [];
if ($location > 0) {
    $where .= ' AND t.location_id = ?';
    $params[] = $location;
}

// Total trucks
$sql = "SELECT COUNT(*) {$fromTrucks} WHERE {$where}";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$totalTrucks = (int)$stmt->fetchColumn();

// Omnitraq complete count
$sql = "SELECT COUNT(*) {$fromTrucks}
        LEFT JOIN omnitraq_installs o ON o.truck_id = t.id
        WHERE {$where} AND o.status IN ('installed','verified')";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$omnitraqDone = (int)$stmt->fetchColumn();

// MDVR complete count
$sql = "SELECT COUNT(*) {$fromTrucks}
        LEFT JOIN mdvr_installs m ON m.truck_id = t.id
        WHERE {$where} AND m.status IN ('installed','verified')";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$mdvrDone = (int)$stmt->fetchColumn();

// Door Sensor complete count
$sql = "SELECT COUNT(*) {$fromTrucks}
        LEFT JOIN door_sensor_installs ds ON ds.truck_id = t.id
        WHERE {$where} AND ds.installed = 1";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$doorDone = (int)$stmt->fetchColumn();

// Fully completed
$sql = "SELECT COUNT(*) {$fromTrucks}
        LEFT JOIN omnitraq_installs o ON o.truck_id = t.id
        LEFT JOIN mdvr_installs m ON m.truck_id = t.id
        LEFT JOIN door_sensor_installs ds ON ds.truck_id = t.id
        WHERE {$where}
          AND o.status IN ('installed','verified')
          AND m.status IN ('installed','verified')
          AND ds.installed = 1";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$fullyDone = (int)$stmt->fetchColumn();

// Locations list (for filter dropdown) — return id+name so JS uses integer ID
$stmt = $db->query("SELECT id, name FROM locations ORDER BY name");
$locations = $stmt->fetchAll();

// Per-location breakdown
$sql = "SELECT
            l.name AS location,
            COUNT(*) as total,
            SUM(CASE WHEN o.status IN ('installed','verified') THEN 1 ELSE 0 END) as omnitraq_done,
            SUM(CASE WHEN m.status IN ('installed','verified') THEN 1 ELSE 0 END) as mdvr_done,
            SUM(CASE WHEN ISNULL(ds.installed,0) = 1 THEN 1 ELSE 0 END) as door_done
        FROM trucks t
        LEFT JOIN locations l ON l.id = t.location_id
        LEFT JOIN omnitraq_installs o ON o.truck_id = t.id
        LEFT JOIN mdvr_installs m ON m.truck_id = t.id
        LEFT JOIN door_sensor_installs ds ON ds.truck_id = t.id
        WHERE t.is_active = 1 AND t.location_id IS NOT NULL
        GROUP BY l.name
        ORDER BY l.name";
$stmt = $db->query($sql);
$byLocation = $stmt->fetchAll();

// Inventory Alerts
$invParams = [];
$invSql = "SELECT i.name, i.quantity, l.name as location
           FROM inventory_items i
           LEFT JOIN locations l ON l.id = i.location_id
           WHERE i.quantity <= 5 ";
if ($location > 0) {
    $invSql .= " AND (i.location_id = ? OR i.location_id IS NULL) ";
    $invParams[] = $location;
}
$invSql .= " ORDER BY i.quantity ASC";
$invStmt = $db->prepare($invSql);
$invStmt->execute($invParams);
$inventoryAlerts = $invStmt->fetchAll();

// Technician Rankings Function
function getRankings($db, $startDate, $location) {
    $sql = "
    SELECT top 5 t.nickname, 
           SUM(omnitraq) as omnitraq_count, 
           SUM(mdvr) as mdvr_count, 
           SUM(door) as door_count, 
           (SUM(omnitraq)+SUM(mdvr)+SUM(door)) as total
    FROM (
        SELECT technician_id as tech_id, 1 as omnitraq, 0 as mdvr, 0 as door 
        FROM omnitraq_installs 
        WHERE status IN ('installed','verified') AND install_date >= ?
        
        UNION ALL
        
        SELECT technician_id as tech_id, 0, 1, 0 
        FROM mdvr_installs 
        WHERE status IN ('installed','verified') AND install_date >= ?
        
        UNION ALL
        
        SELECT ta.technician_id as tech_id, 0, 0, 1 
        FROM door_sensor_installs ds
        JOIN truck_assignments ta ON ta.truck_id = ds.truck_id AND ta.install_type = 'DOOR_SENSOR'
        WHERE ds.installed = 1 AND ds.install_date >= ?
    ) as installs
    JOIN technicians t ON t.id = installs.tech_id
    WHERE tech_id IS NOT NULL 
    ";
    
    $params = [$startDate, $startDate, $startDate];
    if ($location > 0) {
        $sql .= " AND t.location_id = ? ";
        $params[] = $location;
    }
    
    $sql .= " GROUP BY t.id, t.nickname ORDER BY total DESC, t.nickname ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

$rankings = [
    'daily' => getRankings($db, date('Y-m-d'), $location),
    'weekly' => getRankings($db, date('Y-m-d', strtotime('-7 days')), $location),
    'monthly' => getRankings($db, date('Y-m-01'), $location)
];

json_response([
    'total_trucks'    => $totalTrucks,
    'omnitraq_done'   => $omnitraqDone,
    'mdvr_done'       => $mdvrDone,
    'door_sensor_done'=> $doorDone,
    'fully_completed' => $fullyDone,
    'omnitraq_pct'    => $totalTrucks ? round($omnitraqDone / $totalTrucks * 100, 1) : 0,
    'mdvr_pct'        => $totalTrucks ? round($mdvrDone / $totalTrucks * 100, 1) : 0,
    'door_sensor_pct' => $totalTrucks ? round($doorDone / $totalTrucks * 100, 1) : 0,
    'completed_pct'   => $totalTrucks ? round($fullyDone / $totalTrucks * 100, 1) : 0,
    'locations'       => $locations,
    'by_location'     => $byLocation,
    'inventory_alerts'=> $inventoryAlerts,
    'rankings'        => $rankings
]);
