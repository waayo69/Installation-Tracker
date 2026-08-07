<?php
/**
 * Application Configuration
 * Tank Truck Installation Tracking
 */

// ── Database ────────────────────────────────────────────────
define('DB_SERVER', 'db37991.public.databaseasp.net'); // MSSQL instance name
define('DB_NAME', 'db37991');
define('DB_USER', 'db37991');                            // Leave empty for Windows Authentication
define('DB_PASS', 'Hx2-6!fQcZ+4');                            // Leave empty for Windows Authentication

// ── Session ─────────────────────────────────────────────────
define('SESSION_TIMEOUT', 8 * 3600);      // 8 hours in seconds
define('SESSION_NAME', 'TankTruckSID');

// ── Pagination ──────────────────────────────────────────────
define('DEFAULT_PAGE_SIZE', 25);
define('MAX_PAGE_SIZE', 50);

// ── App ─────────────────────────────────────────────────────
define('APP_NAME', 'Tank Truck Tracker');
define('APP_VERSION', '1.0.0');

if (!defined('BASE_URL')) {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($scriptName));
    $baseDir = preg_replace('#/(admin|tech|team_leader|includes|sql|assets)(/.*)?$#i', '', $dir);
    $base = rtrim($baseDir, '/');
    define('BASE_URL', $base === '' ? '' : $base);
}

