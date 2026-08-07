<?php
/**
 * Seed Import Script
 * ─────────────────────────────────────────────────────────
 * Populates a fresh tank_truck_tracking database from:
 *   - sql/trucks_data.json
 *   - sql/installs_data.json
 *
 * Creates:
 *   1. Default admin (admin / admin123)
 *   2. Base locations (LIMAY, MABINI, MANDAUE, NORTH HARBOR)
 *   3. Haulers + trucks (+ install stubs)
 *   4. Omnitraq / MDVR / door sensor data + technicians
 *
 * Usage:
 *   1. CREATE DATABASE tank_truck_tracking;
 *   2. Run sql/schema.sql against that database
 *   3. php sql/seed_import.php
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();

echo "=== Tank Truck Tracker — Seed Import ===\n";
echo "Database: " . DB_NAME . "\n\n";

/** Seed-only: look up or create location by name. */
function seed_location_id(PDO $db, ?string $name): ?int {
    $name = normalize_upper($name);
    if ($name === null) return null;
    $id = resolve_location_id($db, $name);
    if ($id) return $id;
    $db->prepare("INSERT INTO locations (name) VALUES (?)")->execute([$name]);
    return (int)$db->lastInsertId();
}

/** Ensure install stub rows exist for a truck. */
function ensure_install_stubs(PDO $db, int $truckId): void {
    $checks = [
        ['omnitraq_installs', "INSERT INTO omnitraq_installs (truck_id) VALUES (?)"],
        ['mdvr_installs', "INSERT INTO mdvr_installs (truck_id, mdvr_type) VALUES (?, 'NEW')"],
        ['door_sensor_installs', "INSERT INTO door_sensor_installs (truck_id) VALUES (?)"],
    ];
    foreach ($checks as [$table, $insert]) {
        $stmt = $db->prepare("SELECT id FROM {$table} WHERE truck_id = ?");
        $stmt->execute([$truckId]);
        if (!$stmt->fetch()) {
            $db->prepare($insert)->execute([$truckId]);
        }
    }
}

function loadJsonFile(string $filepath): ?array {
    if (!file_exists($filepath)) return null;
    $raw = file_get_contents($filepath);
    if (str_contains(substr($raw, 0, 4), "\x00")) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
    }
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function sanitizeText($val): ?string {
    if ($val === null || $val === '') return null;
    $str = trim((string)$val);
    if (stripos($str, 'E+') !== false) {
        return sprintf('%.0f', (float)$str);
    }
    return $str;
}

function getOrCreateTech(PDO $db, string $nickname, array &$techCache): ?int {
    $nick = strtoupper(trim($nickname));
    if (!$nick || $nick === 'N/A' || $nick === 'TECHNICIAN') return null;

    if (isset($techCache[$nick])) return $techCache[$nick];

    $stmt = $db->prepare("SELECT id FROM technicians WHERE nickname = ?");
    $stmt->execute([$nick]);
    $existing = $stmt->fetch();

    if ($existing) {
        $techCache[$nick] = (int)$existing['id'];
    } else {
        $hash = password_hash('tech123', PASSWORD_BCRYPT);
        $db->prepare("INSERT INTO technicians (nickname, password_hash, role) VALUES (?, ?, 'technician')")
           ->execute([$nick, $hash]);
        $techCache[$nick] = (int)$db->lastInsertId();
        echo "    Created technician: {$nick} (password: tech123)\n";
    }
    return $techCache[$nick];
}

function findOrCreateTruck(PDO $db, ?string $plate, ?string $meNo, ?string $location = null): ?int {
    $cleanPlate = $plate ? preg_replace('/[^A-Z0-9]/i', '', $plate) : '';
    $cleanMeNo  = $meNo  ? preg_replace('/[^A-Z0-9]/i', '', $meNo)  : '';
    if (!$cleanPlate && !$cleanMeNo) return null;

    $stmt = $db->query("SELECT id, plate_number, me_no FROM trucks WHERE is_active = 1");
    foreach ($stmt->fetchAll() as $t) {
        $tp = $t['plate_number'] ? preg_replace('/[^A-Z0-9]/i', '', $t['plate_number']) : '';
        $tm = $t['me_no']        ? preg_replace('/[^A-Z0-9]/i', '', $t['me_no'])        : '';
        if (($cleanPlate && $tp && $tp === $cleanPlate) || ($cleanMeNo && $tm && $tm === $cleanMeNo)) {
            return (int)$t['id'];
        }
    }

    $haulerId = $db->query("SELECT TOP 1 id FROM haulers WHERE is_active = 1 ORDER BY id")->fetchColumn();
    if (!$haulerId) {
        $db->exec("INSERT INTO haulers (name, region) VALUES ('Default Hauler', 'SOUTH LUZON')");
        $haulerId = (int)$db->lastInsertId();
    }

    $locId = seed_location_id($db, $location);
    $db->prepare("INSERT INTO trucks (hauler_id, me_no, plate_number, location_id) VALUES (?, ?, ?, ?)")
       ->execute([(int)$haulerId, normalize_me_no($meNo), normalize_upper($plate), $locId]);
    $truckId = (int)$db->lastInsertId();
    ensure_install_stubs($db, $truckId);
    return $truckId;
}

// ── 1. Admin user ───────────────────────────────────────────
echo "[1] Admin user...\n";
$stmt = $db->prepare("SELECT COUNT(*) FROM admin_users WHERE username = 'admin'");
$stmt->execute();
if ((int)$stmt->fetchColumn() === 0) {
    $hash = password_hash('admin123', PASSWORD_BCRYPT);
    $db->prepare("INSERT INTO admin_users (username, password_hash) VALUES ('admin', ?)")
       ->execute([$hash]);
    echo "    Created admin / admin123\n";
} else {
    echo "    Already exists, skipping\n";
}

// ── 2. Base locations ───────────────────────────────────────
echo "\n[2] Locations...\n";
$baseLocations = ['LIMAY', 'MABINI', 'MANDAUE', 'NORTH HARBOR'];
$locCreated = 0;
foreach ($baseLocations as $locName) {
    if (!resolve_location_id($db, $locName)) {
        $db->prepare("INSERT INTO locations (name) VALUES (?)")->execute([$locName]);
        $locCreated++;
    }
}
echo "    {$locCreated} new, " . count($baseLocations) . " total base locations ensured\n";

// ── 3. Haulers & trucks ─────────────────────────────────────
$haulersCount = 0;
$trucksCount  = 0;
$trucksData = loadJsonFile(__DIR__ . '/trucks_data.json');

if ($trucksData) {
    echo "\n[3] Haulers & trucks from trucks_data.json...\n";
    $rows = array_slice($trucksData, 2);
    $haulerCache = [];

    foreach ($rows as $row) {
        $haulerName = normalize_upper($row['Col_2'] ?? '');
        if (!$haulerName || $haulerName === 'HAULER') continue;

        if (!isset($haulerCache[$haulerName])) {
            $stmt = $db->prepare("SELECT id FROM haulers WHERE name = ?");
            $stmt->execute([$haulerName]);
            $existing = $stmt->fetch();
            if ($existing) {
                $haulerCache[$haulerName] = (int)$existing['id'];
            } else {
                $region = normalize_upper($row['Col_3'] ?? '');
                $db->prepare("INSERT INTO haulers (name, region) VALUES (?, ?)")
                   ->execute([normalize_upper($haulerName), $region]);
                $haulerCache[$haulerName] = (int)$db->lastInsertId();
                $haulersCount++;
            }
        }
        
        $haulerId = $haulerCache[$haulerName];
        $meNo     = normalize_me_no($row['Col_1'] ?? '');
        $plate    = normalize_upper($row['Col_9'] ?? '');
        $location = normalize_upper($row['Col_4'] ?? '');
        $model    = normalize_upper($row['Col_8'] ?? '');

        if ($plate) {
            $stmt = $db->prepare("SELECT id FROM trucks WHERE plate_number = ?");
            $stmt->execute([$plate]);
        } elseif ($meNo) {
            $stmt = $db->prepare("SELECT id FROM trucks WHERE me_no = ?");
            $stmt->execute([$meNo]);
        } else {
            continue;
        }

        $existingTruck = $stmt->fetch();
        if ($existingTruck) {
            $truckId = (int)$existingTruck['id'];
            // Keep location/model in sync on re-run if truck already exists
            $locId = seed_location_id($db, $location ?: null);
            $db->prepare("UPDATE trucks SET location_id = ISNULL(?, location_id), tractor_model = ISNULL(?, tractor_model), updated_at = GETDATE() WHERE id = ?")
               ->execute([$locId, $model ?: null, $truckId]);
        } else {
            $locId = seed_location_id($db, $location ?: null);
            $db->prepare("INSERT INTO trucks (hauler_id, me_no, plate_number, tractor_model, location_id) VALUES (?, ?, ?, ?, ?)")
               ->execute([$haulerId, $meNo ?: null, $plate ?: null, $model ?: null, $locId]);
            $truckId = (int)$db->lastInsertId();
            $trucksCount++;
        }

        ensure_install_stubs($db, $truckId);
    }
    echo "    +{$haulersCount} haulers, +{$trucksCount} trucks (" . count($haulerCache) . " haulers total in import)\n";
} else {
    echo "\n[3] trucks_data.json not found — skipping\n";
}

// ── 4. Installation data ────────────────────────────────────
$installsData = loadJsonFile(__DIR__ . '/installs_data.json');
if ($installsData) {
    echo "\n[4] Installation records from installs_data.json...\n";
    $techCache = [];

    if (isset($installsData['Omnitraq'])) {
        echo "    [4a] Omnitraq...\n";
        $omniUpdated = 0;
        foreach (array_slice($installsData['Omnitraq'], 1) as $row) {
            $plate = trim($row['Col_4'] ?? '');
            $meNo  = trim($row['Col_3'] ?? '');
            $loc   = trim($row['Col_6'] ?? '');
            if (!$plate && !$meNo) continue;

            $truckId = findOrCreateTruck($db, $plate, $meNo, $loc);
            if (!$truckId) continue;

            $imei        = sanitizeText($row['Col_1'] ?? null);
            $simIccid    = sanitizeText($row['Col_2'] ?? null);
            $installDate = trim($row['Col_5'] ?? '');
            $techName    = trim($row['Col_7'] ?? '');
            $doorSensor  = strtoupper(trim($row['Col_8'] ?? ''));
            $remarks     = trim($row['Col_9'] ?? '');
            $techId      = $techName ? getOrCreateTech($db, $techName, $techCache) : null;

            $parsedDate = ($installDate && strtotime($installDate))
                ? date('Y-m-d', strtotime($installDate)) : null;

            $db->prepare("
                UPDATE omnitraq_installs
                SET imei = ISNULL(?, imei),
                    sim_iccid = ISNULL(?, sim_iccid),
                    install_date = ISNULL(?, install_date),
                    technician_id = ISNULL(?, technician_id),
                    status = 'installed',
                    remarks = ISNULL(?, remarks),
                    updated_at = GETDATE()
                WHERE truck_id = ?
            ")->execute([$imei, $simIccid, $parsedDate, $techId, $remarks ?: null, $truckId]);

            if ($doorSensor === 'YES' || $doorSensor === 'INSTALLED') {
                $db->prepare("
                    UPDATE door_sensor_installs
                    SET installed = 1,
                        install_date = ISNULL(?, install_date),
                        updated_at = GETDATE()
                    WHERE truck_id = ?
                ")->execute([$parsedDate, $truckId]);
            }

            if ($techId) {
                $db->prepare("DELETE FROM truck_assignments WHERE truck_id = ? AND install_type = 'OMNITRAQ'")
                   ->execute([$truckId]);
                $db->prepare("INSERT INTO truck_assignments (truck_id, technician_id, install_type) VALUES (?, ?, 'OMNITRAQ')")
                   ->execute([$truckId, $techId]);
            }
            $omniUpdated++;
        }
        echo "    Updated {$omniUpdated} Omnitraq records\n";
    }

    if (isset($installsData['Howen'])) {
        echo "    [4b] MDVR (Howen)...\n";
        $mdvrUpdated = 0;
        foreach (array_slice($installsData['Howen'], 1) as $row) {
            $plate = trim($row['Col_5'] ?? '');
            $meNo  = trim($row['Col_6'] ?? '');
            $loc   = trim($row['Col_8'] ?? '');
            if (!$plate && !$meNo) continue;

            $truckId = findOrCreateTruck($db, $plate, $meNo, $loc);
            if (!$truckId) continue;

            $installDate = trim($row['Col_1'] ?? '');
            $deviceSerial = sanitizeText($row['Col_2'] ?? null);
            $simIccid    = sanitizeText($row['Col_3'] ?? null);
            $simNumber   = sanitizeText($row['Col_4'] ?? null);
            $techName    = trim($row['Col_7'] ?? '');
            $integrated  = strtoupper(trim($row['Col_9'] ?? '')) === 'YES' ? 1 : 0;
            $visible     = strtoupper(trim($row['Col_10'] ?? '')) === 'YES' ? 1 : 0;
            $newIccid    = sanitizeText($row['Col_11'] ?? null);
            $remarks     = trim($row['Col_12'] ?? '');
            $perfStatus  = trim($row['Col_15'] ?? '');
            $detRemarks  = trim($row['Col_16'] ?? '');
            $docLink     = trim($row['Col_17'] ?? '');
            $techId      = $techName ? getOrCreateTech($db, $techName, $techCache) : null;

            $parsedDate = ($installDate && strtotime($installDate))
                ? date('Y-m-d', strtotime($installDate)) : null;

            $db->prepare("
                UPDATE mdvr_installs
                SET device_serial = ISNULL(?, device_serial),
                    sim_iccid = ISNULL(?, sim_iccid),
                    sim_number = ISNULL(?, sim_number),
                    install_date = ISNULL(?, install_date),
                    technician_id = ISNULL(?, technician_id),
                    integrated = ?,
                    visible = ?,
                    performance_status = ISNULL(?, performance_status),
                    detailed_remarks = ISNULL(?, detailed_remarks),
                    documentation_link = ISNULL(?, documentation_link),
                    status = 'installed',
                    updated_at = GETDATE()
                WHERE truck_id = ?
            ")->execute([
                $deviceSerial, $newIccid ?: $simIccid, $simNumber,
                $parsedDate, $techId, $integrated, $visible,
                $perfStatus ?: null, $detRemarks ?: $remarks ?: null,
                $docLink ?: null, $truckId
            ]);

            if ($techId) {
                $db->prepare("DELETE FROM truck_assignments WHERE truck_id = ? AND install_type = 'MDVR'")
                   ->execute([$truckId]);
                $db->prepare("INSERT INTO truck_assignments (truck_id, technician_id, install_type) VALUES (?, ?, 'MDVR')")
                   ->execute([$truckId, $techId]);
            }
            $mdvrUpdated++;
        }
        echo "    Updated {$mdvrUpdated} MDVR records\n";
    }
} else {
    echo "\n[4] installs_data.json not found — skipping\n";
}

echo "\n=== Import Complete ===\n";
echo "Login: " . BASE_URL . "/admin/login.php\n";
echo "  Username: admin\n";
echo "  Password: admin123\n";
