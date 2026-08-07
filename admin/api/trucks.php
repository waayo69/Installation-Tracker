<?php
/**
 * Admin API — Trucks CRUD + filtered/paginated listing
 * GET    /admin/api/trucks.php              — paginated list w/ filters
 * GET    /admin/api/trucks.php?id=X         — single truck detail w/ installs
 * POST   /admin/api/trucks.php              — create
 * PUT    /admin/api/trucks.php?id=X         — update
 * DELETE /admin/api/trucks.php?id=X         — soft-delete
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_admin_or_team_leader(true);

$db = getDB();

switch (method()) {
    case 'GET':
        $id = input('id');

        // ── Single truck detail ───────────────────────────
        if ($id) {
            $stmt = $db->prepare("
                SELECT t.*, h.name AS hauler_name, l.name AS location
                FROM trucks t
                INNER JOIN haulers h ON h.id = t.hauler_id
                LEFT JOIN locations l ON l.id = t.location_id
                WHERE t.id = ?
            ");
            $stmt->execute([$id]);
            $truck = $stmt->fetch();
            if (!$truck) json_response(['error' => 'Truck not found'], 404);

            // Omnitraq install
            $stmt = $db->prepare("SELECT o.*, te.nickname AS technician_name FROM omnitraq_installs o LEFT JOIN technicians te ON te.id = o.technician_id WHERE o.truck_id = ?");
            $stmt->execute([$id]);
            $truck['omnitraq'] = $stmt->fetch() ?: null;

            // MDVR install
            $stmt = $db->prepare("SELECT m.*, te.nickname AS technician_name FROM mdvr_installs m LEFT JOIN technicians te ON te.id = m.technician_id WHERE m.truck_id = ?");
            $stmt->execute([$id]);
            $truck['mdvr'] = $stmt->fetch() ?: null;

            // Door sensor
            $stmt = $db->prepare("SELECT * FROM door_sensor_installs WHERE truck_id = ?");
            $stmt->execute([$id]);
            $truck['door_sensor'] = $stmt->fetch() ?: null;

            // Assignments
            $stmt = $db->prepare("
                SELECT ta.*, te.nickname AS technician_name
                FROM truck_assignments ta
                INNER JOIN technicians te ON te.id = ta.technician_id
                WHERE ta.truck_id = ?
                ORDER BY ta.install_type
            ");
            $stmt->execute([$id]);
            $truck['assignments'] = $stmt->fetchAll();

            json_response($truck);
        }

        // ── Paginated list with filters ───────────────────
        [$offset, $limit, $page] = paginate();

        $where   = ['t.is_active = 1'];
        $params  = [];

        // Filter: location
        if (($_SESSION['role'] ?? '') === 'team_leader') {
            $where[]  = 't.location_id = ?';
            $params[] = (int)current_location_id();
        } elseif ($loc = (int)input('location')) {
            $where[]  = 't.location_id = ?';
            $params[] = $loc;
        }
        // Filter: hauler
        if ($hid = input('hauler_id')) {
            $where[]  = 't.hauler_id = ?';
            $params[] = (int)$hid;
        }
        // Filter: technician (via assignments)
        if ($tid = input('technician_id')) {
            $where[]  = 'EXISTS (SELECT 1 FROM truck_assignments ta WHERE ta.truck_id = t.id AND ta.technician_id = ?)';
            $params[] = (int)$tid;
        }
        // Filter: omnitraq status
        if ($os = input('omnitraq_status')) {
            if ($os === 'not_started') {
                $where[] = "(o.status IS NULL OR o.status = 'not_started')";
            } else {
                $where[]  = 'o.status = ?';
                $params[] = $os;
            }
        }
        // Filter: mdvr status
        if ($ms = input('mdvr_status')) {
            if ($ms === 'not_started') {
                $where[] = "(m.status IS NULL OR m.status = 'not_started')";
            } else {
                $where[]  = 'm.status = ?';
                $params[] = $ms;
            }
        }
        // Filter: door sensor
        if (($ds = input('door_sensor_status')) !== null && $ds !== '') {
            if ($ds === 'installed') {
                $where[] = 'ds.installed = 1';
            } else {
                $where[] = '(ds.installed IS NULL OR ds.installed = 0)';
            }
        }
        // Filter: overall status
        if ($ov = input('overall_status')) {
            switch ($ov) {
                case 'completed':
                    $where[] = "o.status IN ('installed','verified') AND m.status IN ('installed','verified') AND ds.installed = 1";
                    break;
                case 'not_started':
                    $where[] = "(o.status IS NULL OR o.status = 'not_started') AND (m.status IS NULL OR m.status = 'not_started') AND (ds.installed IS NULL OR ds.installed = 0)";
                    break;
                case 'in_progress':
                    $where[] = "NOT (o.status IN ('installed','verified') AND m.status IN ('installed','verified') AND ds.installed = 1)
                                AND NOT ((o.status IS NULL OR o.status = 'not_started') AND (m.status IS NULL OR m.status = 'not_started') AND (ds.installed IS NULL OR ds.installed = 0))";
                    break;
            }
        }
        // Free-text search
        if ($q = input('search')) {
            $where[]  = '(t.plate_number LIKE ? OR t.me_no LIKE ?)';
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }

        $whereClause = implode(' AND ', $where);

        // Count
        $countSql = "SELECT COUNT(DISTINCT t.id) FROM trucks t
                     LEFT JOIN locations l ON l.id = t.location_id
                     LEFT JOIN omnitraq_installs o ON o.truck_id = t.id
                     LEFT JOIN mdvr_installs m ON m.truck_id = t.id
                     LEFT JOIN door_sensor_installs ds ON ds.truck_id = t.id
                     WHERE {$whereClause}";
        $stmt = $db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Sort
        $sortCol = input('sort', 't.updated_at');
        $allowedSorts = ['t.me_no','t.plate_number','h.name','l.name','t.tractor_model','t.updated_at'];
        if (!in_array($sortCol, $allowedSorts)) $sortCol = 't.updated_at';
        $sortDir = strtoupper(input('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        // Data
        $dataSql = "SELECT t.id, t.me_no, t.plate_number, t.tractor_model, l.name AS location, t.location_id, t.updated_at,
                           h.name AS hauler_name,
                           ISNULL(o.status, 'not_started') AS omnitraq_status,
                           ISNULL(m.status, 'not_started') AS mdvr_status,
                           m.mdvr_type,
                           CASE WHEN ISNULL(ds.installed,0) = 1 THEN 'installed' ELSE 'not_started' END AS door_sensor_status
                    FROM trucks t
                    INNER JOIN haulers h ON h.id = t.hauler_id
                    LEFT JOIN locations l ON l.id = t.location_id
                    LEFT JOIN omnitraq_installs o ON o.truck_id = t.id
                    LEFT JOIN mdvr_installs m ON m.truck_id = t.id
                    LEFT JOIN door_sensor_installs ds ON ds.truck_id = t.id
                    WHERE {$whereClause}
                    ORDER BY {$sortCol} {$sortDir}
                    OFFSET {$offset} ROWS FETCH NEXT {$limit} ROWS ONLY";
        $stmt = $db->prepare($dataSql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Attach technician names per truck
        if ($rows) {
            $ids = array_map('intval', array_column($rows, 'id'));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("
                SELECT ta.truck_id, ta.install_type, te.nickname
                FROM truck_assignments ta
                INNER JOIN technicians te ON te.id = ta.technician_id
                WHERE ta.truck_id IN ({$placeholders})
            ");
            $stmt->execute($ids);
            $assignments = $stmt->fetchAll();

            $techMap = [];
            foreach ($assignments as $a) {
                $techMap[$a['truck_id']][] = $a['nickname'] . ' (' . $a['install_type'] . ')';
            }
            foreach ($rows as &$row) {
                $row['technicians'] = implode(', ', $techMap[$row['id']] ?? []);
            }
        }

        paginated_response($rows, $total, $page, $limit);

    case 'POST':
        $data = json_body();
        $missing = validate_required($data, ['hauler_id']);
        if ($missing) json_response(['error' => 'Missing fields', 'fields' => $missing], 422);

        $loc_id = parse_location_id_from_data($db, $data);
        if (($_SESSION['role'] ?? '') === 'team_leader') {
            $loc_id = (int)current_location_id();
        }

        $stmt = $db->prepare("
            INSERT INTO trucks (hauler_id, me_no, plate_number, tractor_model, location_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        try {
            $stmt->execute([
                $data['hauler_id'],
                normalize_me_no($data['me_no'] ?? null),
                normalize_upper($data['plate_number'] ?? null),
                normalize_upper($data['tractor_model'] ?? null),
                $loc_id,
            ]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UQ_trucks_plate')) {
                json_response(['error' => 'A truck with that plate number already exists'], 409);
            }
            throw $e;
        }

        $truckId = (int)$db->lastInsertId();

        // Auto-create install record stubs
        $db->prepare("INSERT INTO omnitraq_installs (truck_id) VALUES (?)")->execute([$truckId]);
        $db->prepare("INSERT INTO mdvr_installs (truck_id, mdvr_type) VALUES (?, 'NEW')")->execute([$truckId]);
        $db->prepare("INSERT INTO door_sensor_installs (truck_id) VALUES (?)")->execute([$truckId]);

        json_response(['id' => $truckId, 'message' => 'Truck created'], 201);

    case 'PUT':
        $id = input('id');
        if (!$id) json_response(['error' => 'ID required'], 400);

        $data = json_body();
        $loc_id = parse_location_id_from_data($db, $data);
        if (($_SESSION['role'] ?? '') === 'team_leader') {
            // Team leaders cannot change truck location
            $stmt = $db->prepare("SELECT location_id FROM trucks WHERE id = ?");
            $stmt->execute([$id]);
            $truckLoc = $stmt->fetchColumn();
            if ($truckLoc != current_location_id()) {
                json_response(['error' => 'Unauthorized truck modification'], 403);
            }
            $loc_id = (int)current_location_id();
        }

        $stmt = $db->prepare("
            UPDATE trucks
            SET hauler_id     = ISNULL(?, hauler_id),
                me_no         = ?,
                plate_number  = ?,
                tractor_model = ?,
                location_id   = ?,
                updated_at    = GETDATE()
            WHERE id = ?
        ");
        $stmt->execute([
            $data['hauler_id'] ?? null,
            normalize_me_no($data['me_no'] ?? null),
            normalize_upper($data['plate_number'] ?? null),
            normalize_upper($data['tractor_model'] ?? null),
            $loc_id,
            $id,
        ]);
        json_response(['message' => 'Truck updated']);

    case 'DELETE':
        $id = input('id');
        if (!$id) json_response(['error' => 'ID required'], 400);

        if (($_SESSION['role'] ?? '') === 'team_leader') {
            $stmt = $db->prepare("SELECT location_id FROM trucks WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() != current_location_id()) {
                json_response(['error' => 'Unauthorized truck modification'], 403);
            }
        }

        $stmt = $db->prepare("UPDATE trucks SET is_active = 0, updated_at = GETDATE() WHERE id = ?");
        $stmt->execute([$id]);
        json_response(['message' => 'Truck deactivated']);

    default:
        json_response(['error' => 'Method not allowed'], 405);
}
