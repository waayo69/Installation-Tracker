<?php
/**
 * Shared utility functions
 */

require_once __DIR__ . '/config.php';

/**
 * Send a JSON response and exit.
 */
function json_response(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Read JSON body from a POST/PUT request.
 */
function json_body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = [];
    return $data;
}

/**
 * Get sanitised request parameters (GET or POST).
 */
function input(string $key, mixed $default = null): mixed {
    return $_REQUEST[$key] ?? $default;
}

/**
 * Build pagination values from request.
 * Returns [offset, limit, page].
 */
function paginate(): array {
    $page  = max(1, (int)(input('page', 1)));
    $limit = min(MAX_PAGE_SIZE, max(1, (int)(input('per_page', DEFAULT_PAGE_SIZE))));
    $offset = ($page - 1) * $limit;
    return [$offset, $limit, $page];
}

/**
 * Return a standard paginated response envelope.
 */
function paginated_response(array $rows, int $total, int $page, int $perPage): never {
    json_response([
        'data'       => $rows,
        'pagination' => [
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / max(1, $perPage)),
        ],
    ]);
}

/**
 * Require specific HTTP method(s), else 405.
 */
function require_method(string ...$methods): void {
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods, true)) {
        json_response(['error' => 'Method not allowed'], 405);
    }
}

/**
 * Validate required fields exist in data array.
 * Returns array of missing field names (empty = all good).
 */
function validate_required(array $data, array $fields): array {
    $missing = [];
    foreach ($fields as $f) {
        if (!isset($data[$f]) || (is_string($data[$f]) && trim($data[$f]) === '')) {
            $missing[] = $f;
        }
    }
    return $missing;
}

/**
 * Touch updated_at on a table row.
 */
function touch_updated(PDO $db, string $table, int $id): void {
    $stmt = $db->prepare("UPDATE {$table} SET updated_at = GETDATE() WHERE id = ?");
    $stmt->execute([$id]);
}

/**
 * Get current HTTP method.
 */
function method(): string {
    return $_SERVER['REQUEST_METHOD'];
}

/**
 * Normalize free-text labels to UPPERCASE trimmed string (or null if empty).
 * Prevents duplicates like Isuzu / ISUZU / isuzu.
 */
function normalize_upper(?string $value): ?string {
    if ($value === null) return null;
    $value = trim($value);
    if ($value === '') return null;
    return strtoupper($value);
}

/** Allowed ME No. prefixes: PREFIX-NUMBER */
function me_no_prefixes(): array {
    return ['PL', 'PI', 'PF', 'BT', 'HC', 'LT'];
}

/**
 * Normalize ME No. to PREFIX-NUMBER (uppercase). Returns null if empty.
 */
function normalize_me_no(?string $value): ?string {
    $value = normalize_upper($value);
    if ($value === null) return null;
    $prefixes = implode('|', me_no_prefixes());
    if (preg_match('/^(' . $prefixes . ')-?(.+)$/', $value, $m)) {
        $num = ltrim($m[2], '-');
        return $num !== '' ? $m[1] . '-' . $num : $m[1];
    }
    return $value;
}

/**
 * Look up a location ID by name (no auto-create).
 * Returns null if name is empty or not found.
 */
function resolve_location_id(PDO $db, ?string $name): ?int {
    $name = normalize_upper($name);
    if ($name === null) return null;
    $stmt = $db->prepare("SELECT id FROM locations WHERE name = ?");
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

/**
 * Resolve location_id from request payload.
 * Prefers location_id; falls back to location / location_name lookup.
 */
function parse_location_id_from_data(PDO $db, array $data): ?int {
    if (array_key_exists('location_id', $data)) {
        if ($data['location_id'] === '' || $data['location_id'] === null) return null;
        $id = (int)$data['location_id'];
        if ($id <= 0) return null;
        $stmt = $db->prepare("SELECT id FROM locations WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn() ? $id : null;
    }
    $name = $data['location'] ?? $data['location_name'] ?? null;
    return resolve_location_id($db, is_string($name) ? $name : null);
}
