<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/config.php';
require_admin();

$pageTitle = 'Inventory';
$headerTitle = 'Inventory Management';

require_once __DIR__ . '/../includes/inventory_view.php';
