<?php
/**
 * Authentication guard helpers.
 * Include this file at the top of every protected page/endpoint.
 */

require_once __DIR__ . '/config.php';

/**
 * Start the session with secure settings.
 */
function init_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path'     => '/',
        'httponly'  => true,
        'samesite'  => 'Lax',
    ]);
    session_start();

    // Session timeout check
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            session_start(); // fresh session
        }
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Require admin role or redirect/403.
 */
function require_admin(bool $isApi = false): void {
    init_session();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized — admin login required']);
            exit;
        }
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}

/**
 * Require technician role or redirect/403.
 */
function require_technician(bool $isApi = false): void {
    init_session();
    $role = $_SESSION['role'] ?? '';
    if ($role !== 'technician' && $role !== 'team_leader') {
        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized — technician login required']);
            exit;
        }
        header('Location: ' . BASE_URL . '/tech/login.php');
        exit;
    }
}

/**
 * Get the logged-in technician ID (or null).
 */
function current_technician_id(): ?int {
    return $_SESSION['technician_id'] ?? null;
}

/**
 * Require team leader role or redirect/403.
 */
function require_team_leader(bool $isApi = false): void {
    init_session();
    if (($_SESSION['role'] ?? '') !== 'team_leader') {
        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized — team leader login required']);
            exit;
        }
        header('Location: ' . BASE_URL . '/tech/login.php');
        exit;
    }
}

/**
 * Require admin OR team leader role.
 */
function require_admin_or_team_leader(bool $isApi = false): void {
    init_session();
    $role = $_SESSION['role'] ?? '';
    if ($role !== 'admin' && $role !== 'team_leader') {
        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        header('Location: ' . BASE_URL . '/tech/login.php');
        exit;
    }
}

/**
 * Get the team leader's assigned location ID.
 */
function current_location_id(): ?int {
    return $_SESSION['location_id'] ?? null;
}

/**
 * Get the logged-in admin ID (or null).
 */
function current_admin_id(): ?int {
    return $_SESSION['admin_id'] ?? null;
}
