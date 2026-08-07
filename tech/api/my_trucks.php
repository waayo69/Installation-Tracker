<?php
/**
 * Technician API — My assigned trucks
 * GET /tech/api/my_trucks.php
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_technician(true);

require_method('GET');

$db    = getDB();
$techId = current_technician_id();

$stmt = $db->prepare("
    SELECT DISTINCT t.id, t.me_no, t.plate_number, t.tractor_model, l.name AS location,
           h.name AS hauler_name,
           ISNULL(o.status, 'not_started') AS omnitraq_status,
           o.omnitraq_no,
           o.imei AS omnitraq_imei,
           ISNULL(m.status, 'not_started') AS mdvr_status,
           m.mdvr_type,
           m.device_serial AS mdvr_imei,
           m.sim_iccid,
           CASE WHEN ISNULL(ds.installed,0) = 1 THEN 'installed' ELSE 'not_started' END AS door_sensor_status,
           t.updated_at
    FROM trucks t
    INNER JOIN haulers h ON h.id = t.hauler_id
    INNER JOIN truck_assignments ta ON ta.truck_id = t.id AND ta.technician_id = ?
    LEFT JOIN locations l ON l.id = t.location_id
    LEFT JOIN omnitraq_installs o ON o.truck_id = t.id
    LEFT JOIN mdvr_installs m ON m.truck_id = t.id
    LEFT JOIN door_sensor_installs ds ON ds.truck_id = t.id
    WHERE t.is_active = 1
    ORDER BY t.updated_at DESC
");
$stmt->execute([$techId]);
$trucks = $stmt->fetchAll();

// Get install types assigned to this tech for each truck
$stmt = $db->prepare("
    SELECT truck_id, install_type
    FROM truck_assignments
    WHERE technician_id = ?
");
$stmt->execute([$techId]);
$assignments = $stmt->fetchAll();

$assignMap = [];
foreach ($assignments as $a) {
    $assignMap[$a['truck_id']][] = $a['install_type'];
}

foreach ($trucks as &$truck) {
    $truck['assigned_types'] = $assignMap[$truck['id']] ?? [];
}

json_response($trucks);
