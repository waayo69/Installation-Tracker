<?php
// admin/api/options.php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_admin_or_team_leader(true);

header('Content-Type: application/json');
$db = getDB();

$locations = $db->query("
    SELECT l.id, l.name
    FROM locations l
    ORDER BY l.name
")->fetchAll(PDO::FETCH_ASSOC);

$models = $db->query("SELECT DISTINCT tractor_model FROM trucks WHERE tractor_model IS NOT NULL AND tractor_model != ''")->fetchAll(PDO::FETCH_COLUMN);
$models = array_values(array_unique(array_filter(array_map('normalize_upper', $models))));

$cacheFile = __DIR__ . '/../../custom_options.json';
if (file_exists($cacheFile)) {
    $custom = json_decode(file_get_contents($cacheFile), true);
    if (!empty($custom['models']) && is_array($custom['models'])) {
        $customModels = array_filter(array_map('normalize_upper', $custom['models']));
        $models = array_values(array_unique(array_merge($models, $customModels)));
    }
}
sort($models);

echo json_encode([
    'locations' => $locations,
    'models' => $models,
    'me_prefixes' => me_no_prefixes(),
]);
