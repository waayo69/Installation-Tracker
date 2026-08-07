<?php
// admin/api/add_option.php — custom tractor models only (locations live in dbo.locations)
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_admin(true);

$cacheFile = __DIR__ . '/../../custom_options.json';

function load_model_options(string $cacheFile): array {
    $options = ['models' => []];
    if (file_exists($cacheFile)) {
        $options = json_decode(file_get_contents($cacheFile), true) ?: $options;
        if (!isset($options['models']) || !is_array($options['models'])) $options['models'] = [];
    }
    // Always store uppercase unique models
    $options['models'] = array_values(array_unique(array_filter(array_map('normalize_upper', $options['models']))));
    sort($options['models']);
    return $options;
}

function save_model_options(string $cacheFile, array $options): void {
    file_put_contents($cacheFile, json_encode($options, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

switch (method()) {
    case 'GET':
        json_response(load_model_options($cacheFile));

    case 'POST':
        $data = json_body();
        $type = $data['type'] ?? 'model';
        $value = normalize_upper($data['value'] ?? '');
        if ($type !== 'model' || $value === null) {
            json_response(['error' => 'Invalid type or empty value. Use Locations manager to add locations.'], 400);
        }
        $options = load_model_options($cacheFile);
        if (!in_array($value, $options['models'], true)) {
            $options['models'][] = $value;
            sort($options['models']);
            save_model_options($cacheFile, $options);
        }
        json_response(['success' => true, 'value' => $value]);

    case 'DELETE':
        $data = json_body();
        $value = normalize_upper($data['value'] ?? input('value'));
        if ($value === null) json_response(['error' => 'value required'], 400);
        $options = load_model_options($cacheFile);
        $options['models'] = array_values(array_filter($options['models'], fn($m) => $m !== $value));
        save_model_options($cacheFile, $options);
        json_response(['success' => true, 'message' => 'Model removed from catalog']);

    default:
        json_response(['error' => 'Method not allowed'], 405);
}
