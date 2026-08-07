<?php
/**
 * Admin API — Excel Export
 * GET /admin/api/export.php  — exports filtered truck data as Excel (.xlsx)
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_admin_or_team_leader(true);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$db = getDB();

$where   = ['t.is_active = 1'];
$params  = [];

if (($_SESSION['role'] ?? '') === 'team_leader') {
    $where[] = 't.location_id = ?';
    $params[] = $_SESSION['location_id'];
} else {
    if ($loc = (int)input('location')) { $where[] = 't.location_id = ?'; $params[] = $loc; }
}

if ($hid = input('hauler_id'))     { $where[] = 't.hauler_id = ?';  $params[] = (int)$hid; }
if ($q   = input('search'))        { $where[] = '(t.plate_number LIKE ? OR t.me_no LIKE ?)'; $params[] = "%{$q}%"; $params[] = "%{$q}%"; }

$whereClause = implode(' AND ', $where);

$sql = "SELECT t.me_no, t.plate_number, t.tractor_model, l.name AS location,
               h.name AS hauler_name,
               ISNULL(o.status, 'not_started') AS omnitraq_status,
               o.omnitraq_no, o.imei AS omnitraq_imei, o.sim_iccid AS omnitraq_sim,
               ISNULL(m.status, 'not_started') AS mdvr_status,
               m.mdvr_type, m.device_serial, m.sim_iccid AS mdvr_sim, m.sim_number AS mdvr_sim_number,
               CASE WHEN m.integrated = 1 THEN 'Yes' ELSE 'No' END AS mdvr_integrated,
               CASE WHEN m.visible = 1 THEN 'Yes' ELSE 'No' END AS mdvr_linked,
               CASE WHEN ISNULL(ds.installed,0) = 1 THEN 'installed' ELSE 'not_started' END AS door_sensor_status,
               t.updated_at
        FROM trucks t
        INNER JOIN haulers h ON h.id = t.hauler_id
        LEFT JOIN locations l ON l.id = t.location_id
        LEFT JOIN omnitraq_installs o ON o.truck_id = t.id
        LEFT JOIN mdvr_installs m ON m.truck_id = t.id
        LEFT JOIN door_sensor_installs ds ON ds.truck_id = t.id
        WHERE {$whereClause}
        ORDER BY h.name, t.me_no";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set Header Row
$headers = [
    'ME No.', 'Plate Number', 'Tractor Model', 'Location', 'Hauler Name',
    'Omnitraq Status', 'Omnitraq No', 'Omnitraq IMEI', 'Omnitraq SIM',
    'MDVR Status', 'MDVR Type', 'MDVR Serial', 'MDVR SIM ICCID', 'MDVR SIM Number',
    'MDVR Integrated', 'MDVR Linked',
    'Door Sensor Status', 'Updated At'
];

$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);
    // Make header bold
    $sheet->getStyle($col . '1')->getFont()->setBold(true);
    $col++;
}

$rowNum = 2;
foreach ($rows as $row) {
    // Standardize statuses for display
    $omnitraq_status = str_replace('_', ' ', ucwords($row['omnitraq_status'], '_'));
    $mdvr_status = str_replace('_', ' ', ucwords($row['mdvr_status'], '_'));
    $door_sensor_status = str_replace('_', ' ', ucwords($row['door_sensor_status'], '_'));

    $sheet->setCellValue('A' . $rowNum, $row['me_no']);
    $sheet->setCellValue('B' . $rowNum, $row['plate_number']);
    $sheet->setCellValue('C' . $rowNum, $row['tractor_model']);
    $sheet->setCellValue('D' . $rowNum, $row['location']);
    $sheet->setCellValue('E' . $rowNum, $row['hauler_name']);
    
    // Status Columns
    $sheet->setCellValue('F' . $rowNum, $omnitraq_status);
    $sheet->setCellValue('G' . $rowNum, $row['omnitraq_no']);
    $sheet->setCellValue('H' . $rowNum, $row['omnitraq_imei']);
    $sheet->setCellValue('I' . $rowNum, $row['omnitraq_sim']);
    
    $sheet->setCellValue('J' . $rowNum, $mdvr_status);
    $sheet->setCellValue('K' . $rowNum, $row['mdvr_type']);
    $sheet->setCellValue('L' . $rowNum, $row['device_serial']);
    $sheet->setCellValue('M' . $rowNum, $row['mdvr_sim']);
    $sheet->setCellValue('N' . $rowNum, $row['mdvr_sim_number']);
    $sheet->setCellValue('O' . $rowNum, $row['mdvr_integrated']);
    $sheet->setCellValue('P' . $rowNum, $row['mdvr_linked']);
    
    $sheet->setCellValue('Q' . $rowNum, $door_sensor_status);
    $sheet->setCellValue('R' . $rowNum, $row['updated_at']);

    // Apply conditional formatting manually
    $statusCols = ['F' => $row['omnitraq_status'], 'J' => $row['mdvr_status'], 'Q' => $row['door_sensor_status']];
    
    foreach ($statusCols as $colLetter => $rawStatus) {
        $cell = $colLetter . $rowNum;
        if ($rawStatus === 'installed' || $rawStatus === 'verified' || $rawStatus === 'completed') {
            // Green (Good)
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC6EFCE');
            $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FF006100');
        } elseif ($rawStatus === 'not_started' || $rawStatus === 'not_installed' || $rawStatus === '0') {
            // Red (Bad)
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFC7CE');
            $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FF9C0006');
        }
    }
    
    $rowNum++;
}

// Auto size columns
foreach (range('A', 'R') as $colChar) {
    $sheet->getColumnDimension($colChar)->setAutoSize(true);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="truck_installations_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
