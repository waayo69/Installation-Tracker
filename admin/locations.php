<?php
/**
 * Admin — Locations redirect
 * Locations are managed from Truck Management → Locations button.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_admin();
require_once __DIR__ . '/../includes/config.php';
header('Location: ' . BASE_URL . '/admin/trucks.php');
exit;
