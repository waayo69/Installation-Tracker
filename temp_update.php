<?php
require 'includes/db.php';
$db = getDB();
$db->exec("ALTER TABLE dbo.inventory_items ADD is_global BIT NOT NULL DEFAULT 0");

$db->exec("UPDATE inventory_items SET is_global = 1 WHERE name IN (SELECT name FROM inventory_items GROUP BY name HAVING COUNT(*) > 1)");

echo "Done";
