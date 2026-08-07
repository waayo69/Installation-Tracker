<?php
/**
 * Database Connection (PDO + pdo_sqlsrv)
 * Uses persistent connections for performance.
 */

require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    // Check if pdo_sqlsrv driver is loaded
    if (!extension_loaded('pdo_sqlsrv') && !in_array('sqlsrv', PDO::getAvailableDrivers(), true)) {
        http_response_code(500);
        $errMsg = 'PHP extension "pdo_sqlsrv" is not enabled in XAMPP. Please enable extension=pdo_sqlsrv in your php.ini file.';
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'json') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $errMsg]);
        } else {
            echo '<div style="max-width:600px;margin:50px auto;padding:24px;background:#1e293b;color:#ef4444;font-family:sans-serif;border-radius:12px;border:1px solid rgba(239,68,68,0.3);box-shadow:0 10px 30px rgba(0,0,0,0.5);">';
            echo '<h3 style="margin-top:0;display:flex;align-items:center;gap:8px;">⚠️ Missing MSSQL Driver</h3>';
            echo '<p style="color:#f1f5f9;font-size:0.95rem;line-height:1.5;">' . htmlspecialchars($errMsg) . '</p>';
            echo '<p style="color:#94a3b8;font-size:0.85rem;">Make sure you downloaded <code>php_pdo_sqlsrv_*.dll</code> to <code>C:\xampp\php\ext\</code> and added <code>extension=pdo_sqlsrv</code> to <code>C:\xampp\php\php.ini</code>.</p>';
            echo '</div>';
        }
        exit;
    }

    $dsn = "sqlsrv:Server=" . DB_SERVER . ";Database=" . DB_NAME . ";TrustServerCertificate=yes";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    if (defined('PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE')) {
        $options[PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE] = true;
    }

    try {
        if (defined('DB_USER') && DB_USER !== '') {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } else {
            // Windows Authentication (Integrated Security)
            $pdo = new PDO($dsn, null, null, $options);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'json') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        } else {
            echo '<div style="max-width:600px;margin:50px auto;padding:24px;background:#1e293b;color:#ef4444;font-family:sans-serif;border-radius:12px;border:1px solid rgba(239,68,68,0.3);box-shadow:0 10px 30px rgba(0,0,0,0.5);">';
            echo '<h3 style="margin-top:0;display:flex;align-items:center;gap:8px;">⚠️ Database Connection Failed</h3>';
            echo '<p style="color:#f1f5f9;font-size:0.95rem;line-height:1.5;">' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p style="color:#94a3b8;font-size:0.85rem;">Please check your MSSQL credentials in <code>includes/config.php</code> and ensure Microsoft SQL Server is running.</p>';
            echo '</div>';
        }
        exit;
    }

    return $pdo;
}
