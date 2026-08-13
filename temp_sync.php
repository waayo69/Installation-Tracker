<?php
require 'includes/db.php';
$db = getDB();

$db->exec("
    INSERT INTO inventory_items (name, linked_system, deduction_type, quantity, location_id, is_global)
    SELECT name, linked_system, deduction_type, 0, 6, 1
    FROM inventory_items
    WHERE location_id IS NULL AND is_global = 1
    AND name NOT IN (SELECT name FROM inventory_items WHERE location_id = 6)
");

echo "Done syncing to Davao";
