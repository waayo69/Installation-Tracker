<?php
require_once __DIR__ . '/includes/db.php';

try {
    $db = getDB();
    
    // 1. Create inventory_items table
    $sql1 = "
        IF OBJECT_ID('dbo.inventory_items', 'U') IS NULL
        BEGIN
            CREATE TABLE dbo.inventory_items (
                id INT IDENTITY(1,1) PRIMARY KEY,
                name NVARCHAR(200) NOT NULL,
                linked_system NVARCHAR(50) NOT NULL,
                deduction_type NVARCHAR(20) NOT NULL,
                quantity INT NOT NULL CONSTRAINT DF_inventory_qty DEFAULT 0,
                created_at DATETIME2 NOT NULL CONSTRAINT DF_inventory_created DEFAULT GETDATE(),
                updated_at DATETIME2 NOT NULL CONSTRAINT DF_inventory_updated DEFAULT GETDATE()
            );
        END;
    ";
    $db->exec($sql1);
    
    // 2. Create truck_inventory_usage table
    $sql2 = "
        IF OBJECT_ID('dbo.truck_inventory_usage', 'U') IS NULL
        BEGIN
            CREATE TABLE dbo.truck_inventory_usage (
                id INT IDENTITY(1,1) PRIMARY KEY,
                truck_id INT NOT NULL,
                item_id INT NOT NULL,
                install_type NVARCHAR(50) NOT NULL,
                used_at DATETIME2 NOT NULL CONSTRAINT DF_usage_used DEFAULT GETDATE(),
                CONSTRAINT FK_usage_truck FOREIGN KEY (truck_id) REFERENCES dbo.trucks(id) ON DELETE CASCADE,
                CONSTRAINT FK_usage_item FOREIGN KEY (item_id) REFERENCES dbo.inventory_items(id) ON DELETE CASCADE,
                CONSTRAINT UQ_usage_truck_item UNIQUE (truck_id, item_id, install_type)
            );
        END;
    ";
    $db->exec($sql2);
    
    echo "Tables created successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
