-- ============================================================
-- Tank Truck Installation Tracking — MSSQL Schema (current)
-- ============================================================
-- Fresh install:
--   1. CREATE DATABASE tank_truck_tracking;
--   2. Run this entire script against that database.
--   3. php sql/seed_import.php
--
-- Includes locations + location_id FKs (no trucks.location string).
-- migrate_team_leaders.sql is obsolete for fresh installs.
-- ============================================================

SET QUOTED_IDENTIFIER ON;
GO
SET ANSI_NULLS ON;
GO

-- ────────────────────────────────────────────────────────────
-- 1. HAULERS
-- ────────────────────────────────────────────────────────────
IF OBJECT_ID('dbo.haulers', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.haulers (
        id          INT IDENTITY(1,1) PRIMARY KEY,
        name        NVARCHAR(200)  NOT NULL,
        region      NVARCHAR(100)  NULL,
        is_active   BIT            NOT NULL CONSTRAINT DF_haulers_active DEFAULT 1,
        created_at  DATETIME2      NOT NULL CONSTRAINT DF_haulers_created DEFAULT GETDATE(),
        updated_at  DATETIME2      NOT NULL CONSTRAINT DF_haulers_updated DEFAULT GETDATE(),
        CONSTRAINT UQ_haulers_name UNIQUE (name)
    );
END;
GO

-- ────────────────────────────────────────────────────────────
-- 2. LOCATIONS
-- ────────────────────────────────────────────────────────────
IF OBJECT_ID('dbo.locations', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.locations (
        id   INT IDENTITY(1,1) PRIMARY KEY,
        name NVARCHAR(100) NOT NULL,
        CONSTRAINT UQ_locations_name UNIQUE (name)
    );
END;
GO

-- ────────────────────────────────────────────────────────────
-- 3. TRUCKS
-- ────────────────────────────────────────────────────────────
IF OBJECT_ID('dbo.trucks', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.trucks (
        id              INT IDENTITY(1,1) PRIMARY KEY,
        hauler_id       INT            NOT NULL,
        me_no           NVARCHAR(20)   NULL,
        plate_number    NVARCHAR(20)   NULL,
        tractor_model   NVARCHAR(50)   NULL,
        location_id     INT            NULL,
        is_active       BIT            NOT NULL CONSTRAINT DF_trucks_active DEFAULT 1,
        created_at      DATETIME2      NOT NULL CONSTRAINT DF_trucks_created DEFAULT GETDATE(),
        updated_at      DATETIME2      NOT NULL CONSTRAINT DF_trucks_updated DEFAULT GETDATE(),
        CONSTRAINT FK_trucks_hauler FOREIGN KEY (hauler_id)
            REFERENCES dbo.haulers(id) ON DELETE NO ACTION,
        CONSTRAINT FK_trucks_location FOREIGN KEY (location_id)
            REFERENCES dbo.locations(id) ON DELETE NO ACTION
    );
END;
GO

-- Filtered unique: multiple NULL plates allowed; duplicate non-null plates blocked
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UQ_trucks_plate' AND object_id = OBJECT_ID('dbo.trucks'))
    CREATE UNIQUE NONCLUSTERED INDEX UQ_trucks_plate ON dbo.trucks(plate_number) WHERE plate_number IS NOT NULL;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_trucks_me_no' AND object_id = OBJECT_ID('dbo.trucks'))
    CREATE NONCLUSTERED INDEX IX_trucks_me_no ON dbo.trucks(me_no) WHERE me_no IS NOT NULL;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_trucks_location_id' AND object_id = OBJECT_ID('dbo.trucks'))
    CREATE NONCLUSTERED INDEX IX_trucks_location_id ON dbo.trucks(location_id);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_trucks_hauler_id' AND object_id = OBJECT_ID('dbo.trucks'))
    CREATE NONCLUSTERED INDEX IX_trucks_hauler_id ON dbo.trucks(hauler_id);
GO

-- ────────────────────────────────────────────────────────────
-- 4. TECHNICIANS
-- ────────────────────────────────────────────────────────────
IF OBJECT_ID('dbo.technicians', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.technicians (
        id              INT IDENTITY(1,1) PRIMARY KEY,
        nickname        NVARCHAR(50)   NOT NULL,
        password_hash   NVARCHAR(255)  NOT NULL,
        role            NVARCHAR(20)   NOT NULL CONSTRAINT DF_technicians_role DEFAULT 'technician',
        location_id     INT            NULL,
        is_active       BIT            NOT NULL CONSTRAINT DF_technicians_active DEFAULT 1,
        created_at      DATETIME2      NOT NULL CONSTRAINT DF_technicians_created DEFAULT GETDATE(),
        updated_at      DATETIME2      NOT NULL CONSTRAINT DF_technicians_updated DEFAULT GETDATE(),
        CONSTRAINT UQ_technicians_nickname UNIQUE (nickname),
        CONSTRAINT CK_technicians_role CHECK (role IN ('technician', 'team_leader')),
        CONSTRAINT FK_technicians_location FOREIGN KEY (location_id)
            REFERENCES dbo.locations(id) ON DELETE NO ACTION
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_technicians_nickname' AND object_id = OBJECT_ID('dbo.technicians'))
    CREATE NONCLUSTERED INDEX IX_technicians_nickname ON dbo.technicians(nickname);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_technicians_location_id' AND object_id = OBJECT_ID('dbo.technicians'))
    CREATE NONCLUSTERED INDEX IX_technicians_location_id ON dbo.technicians(location_id);
GO

-- ────────────────────────────────────────────────────────────
-- 5. TRUCK_ASSIGNMENTS
-- ────────────────────────────────────────────────────────────
IF OBJECT_ID('dbo.truck_assignments', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.truck_assignments (
        id              INT IDENTITY(1,1) PRIMARY KEY,
        truck_id        INT            NOT NULL,
        technician_id   INT            NOT NULL,
        install_type    NVARCHAR(20)   NOT NULL,
        assigned_date   DATE           NOT NULL CONSTRAINT DF_assignments_date DEFAULT CAST(GETDATE() AS DATE),
        created_at      DATETIME2      NOT NULL CONSTRAINT DF_assignments_created DEFAULT GETDATE(),
        CONSTRAINT FK_assignments_truck FOREIGN KEY (truck_id)
            REFERENCES dbo.trucks(id) ON DELETE NO ACTION,
        CONSTRAINT FK_assignments_tech FOREIGN KEY (technician_id)
            REFERENCES dbo.technicians(id) ON DELETE NO ACTION,
        CONSTRAINT CK_assignments_type CHECK (install_type IN ('MDVR','OMNITRAQ','DOOR_SENSOR'))
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_assignments_truck' AND object_id = OBJECT_ID('dbo.truck_assignments'))
    CREATE NONCLUSTERED INDEX IX_assignments_truck ON dbo.truck_assignments(truck_id);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_assignments_tech' AND object_id = OBJECT_ID('dbo.truck_assignments'))
    CREATE NONCLUSTERED INDEX IX_assignments_tech ON dbo.truck_assignments(technician_id);
GO

-- ────────────────────────────────────────────────────────────
-- 6. OMNITRAQ_INSTALLS
-- ────────────────────────────────────────────────────────────
IF OBJECT_ID('dbo.omnitraq_installs', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.omnitraq_installs (
        id              INT IDENTITY(1,1) PRIMARY KEY,
        truck_id        INT            NOT NULL,
        omnitraq_no     NVARCHAR(50)   NULL,
        imei            NVARCHAR(30)   NULL,
        sim_iccid       NVARCHAR(30)   NULL,
        install_date    DATE           NULL,
        technician_id   INT            NULL,
        status          NVARCHAR(20)   NOT NULL CONSTRAINT DF_omnitraq_status DEFAULT 'not_started',
        remarks         NVARCHAR(500)  NULL,
        created_at      DATETIME2      NOT NULL CONSTRAINT DF_omnitraq_created DEFAULT GETDATE(),
        updated_at      DATETIME2      NOT NULL CONSTRAINT DF_omnitraq_updated DEFAULT GETDATE(),
        CONSTRAINT FK_omnitraq_truck FOREIGN KEY (truck_id)
            REFERENCES dbo.trucks(id) ON DELETE NO ACTION,
        CONSTRAINT FK_omnitraq_tech FOREIGN KEY (technician_id)
            REFERENCES dbo.technicians(id) ON DELETE NO ACTION,
        CONSTRAINT CK_omnitraq_status CHECK (status IN ('not_started','installed','verified')),
        CONSTRAINT UQ_omnitraq_truck UNIQUE (truck_id)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_omnitraq_status' AND object_id = OBJECT_ID('dbo.omnitraq_installs'))
    CREATE NONCLUSTERED INDEX IX_omnitraq_status ON dbo.omnitraq_installs(status);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_omnitraq_tech' AND object_id = OBJECT_ID('dbo.omnitraq_installs'))
    CREATE NONCLUSTERED INDEX IX_omnitraq_tech ON dbo.omnitraq_installs(technician_id);
GO

-- ────────────────────────────────────────────────────────────
-- 7. MDVR_INSTALLS
-- ────────────────────────────────────────────────────────────
IF OBJECT_ID('dbo.mdvr_installs', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.mdvr_installs (
        id                  INT IDENTITY(1,1) PRIMARY KEY,
        truck_id            INT            NOT NULL,
        mdvr_type           NVARCHAR(10)   NOT NULL,
        device_serial       NVARCHAR(30)   NULL,
        sim_iccid           NVARCHAR(30)   NULL,
        sim_number          NVARCHAR(20)   NULL,
        install_date        DATE           NULL,
        technician_id       INT            NULL,
        integrated          BIT            NOT NULL CONSTRAINT DF_mdvr_integrated DEFAULT 0,
        visible             BIT            NOT NULL CONSTRAINT DF_mdvr_visible DEFAULT 0,
        performance_status  NVARCHAR(200)  NULL,
        detailed_remarks    NVARCHAR(MAX)  NULL,
        documentation_link  NVARCHAR(500)  NULL,
        status              NVARCHAR(20)   NOT NULL CONSTRAINT DF_mdvr_status DEFAULT 'not_started',
        created_at          DATETIME2      NOT NULL CONSTRAINT DF_mdvr_created DEFAULT GETDATE(),
        updated_at          DATETIME2      NOT NULL CONSTRAINT DF_mdvr_updated DEFAULT GETDATE(),
        CONSTRAINT FK_mdvr_truck FOREIGN KEY (truck_id)
            REFERENCES dbo.trucks(id) ON DELETE NO ACTION,
        CONSTRAINT FK_mdvr_tech FOREIGN KEY (technician_id)
            REFERENCES dbo.technicians(id) ON DELETE NO ACTION,
        CONSTRAINT CK_mdvr_type CHECK (mdvr_type IN ('NEW','OLD')),
        CONSTRAINT CK_mdvr_status CHECK (status IN ('not_started','installed','verified')),
        CONSTRAINT UQ_mdvr_truck UNIQUE (truck_id)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_mdvr_status' AND object_id = OBJECT_ID('dbo.mdvr_installs'))
    CREATE NONCLUSTERED INDEX IX_mdvr_status ON dbo.mdvr_installs(status);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_mdvr_tech' AND object_id = OBJECT_ID('dbo.mdvr_installs'))
    CREATE NONCLUSTERED INDEX IX_mdvr_tech ON dbo.mdvr_installs(technician_id);
GO

-- ────────────────────────────────────────────────────────────
-- 8. DOOR_SENSOR_INSTALLS
-- ────────────────────────────────────────────────────────────
IF OBJECT_ID('dbo.door_sensor_installs', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.door_sensor_installs (
        id              INT IDENTITY(1,1) PRIMARY KEY,
        truck_id        INT            NOT NULL,
        installed       BIT            NOT NULL CONSTRAINT DF_door_installed DEFAULT 0,
        install_date    DATE           NULL,
        remarks         NVARCHAR(500)  NULL,
        created_at      DATETIME2      NOT NULL CONSTRAINT DF_door_created DEFAULT GETDATE(),
        updated_at      DATETIME2      NOT NULL CONSTRAINT DF_door_updated DEFAULT GETDATE(),
        CONSTRAINT FK_door_sensor_truck FOREIGN KEY (truck_id)
            REFERENCES dbo.trucks(id) ON DELETE NO ACTION,
        CONSTRAINT UQ_door_sensor_truck UNIQUE (truck_id)
    );
END;
GO

-- ────────────────────────────────────────────────────────────
-- 9. ADMIN_USERS
-- ────────────────────────────────────────────────────────────
IF OBJECT_ID('dbo.admin_users', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.admin_users (
        id              INT IDENTITY(1,1) PRIMARY KEY,
        username        NVARCHAR(50)   NOT NULL,
        password_hash   NVARCHAR(255)  NOT NULL,
        is_active       BIT            NOT NULL CONSTRAINT DF_admin_active DEFAULT 1,
        created_at      DATETIME2      NOT NULL CONSTRAINT DF_admin_created DEFAULT GETDATE(),
        CONSTRAINT UQ_admin_username UNIQUE (username)
    );
END;
GO

-- ────────────────────────────────────────────────────────────
-- 10. VIEW: vw_truck_overview
-- ────────────────────────────────────────────────────────────
IF OBJECT_ID('dbo.vw_truck_overview', 'V') IS NOT NULL
    DROP VIEW dbo.vw_truck_overview;
GO

CREATE VIEW dbo.vw_truck_overview AS
SELECT
    t.id,
    t.me_no,
    t.plate_number,
    t.tractor_model,
    t.location_id,
    l.name AS location,
    t.is_active,
    t.created_at,
    t.updated_at,
    h.id   AS hauler_id,
    h.name AS hauler_name,
    ISNULL(o.status, 'not_started')  AS omnitraq_status,
    ISNULL(m.status, 'not_started')  AS mdvr_status,
    m.mdvr_type,
    CASE WHEN ISNULL(ds.installed, 0) = 1 THEN 'installed' ELSE 'not_started' END AS door_sensor_status,
    CASE
        WHEN ISNULL(o.status,'not_started') IN ('installed','verified')
         AND ISNULL(m.status,'not_started') IN ('installed','verified')
         AND ISNULL(ds.installed, 0) = 1
        THEN 'completed'
        WHEN ISNULL(o.status,'not_started') = 'not_started'
         AND ISNULL(m.status,'not_started') = 'not_started'
         AND ISNULL(ds.installed, 0) = 0
        THEN 'not_started'
        ELSE 'in_progress'
    END AS overall_status
FROM dbo.trucks t
INNER JOIN dbo.haulers h ON h.id = t.hauler_id
LEFT JOIN dbo.locations l ON l.id = t.location_id
LEFT JOIN dbo.omnitraq_installs o ON o.truck_id = t.id
LEFT JOIN dbo.mdvr_installs m ON m.truck_id = t.id
LEFT JOIN dbo.door_sensor_installs ds ON ds.truck_id = t.id;
GO
-- ────────────────────────────────────────────────────────────
-- 11. INVENTORY
-- ────────────────────────────────────────────────────────────
IF OBJECT_ID('dbo.inventory_items', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.inventory_items (
        id INT IDENTITY(1,1) PRIMARY KEY,
        name NVARCHAR(200) NOT NULL,
        linked_system NVARCHAR(50) NOT NULL,
        deduction_type NVARCHAR(20) NOT NULL,
        quantity INT NOT NULL CONSTRAINT DF_inventory_qty DEFAULT 0,
        location_id INT NULL,
        created_at DATETIME2 NOT NULL CONSTRAINT DF_inventory_created DEFAULT GETDATE(),
        updated_at DATETIME2 NOT NULL CONSTRAINT DF_inventory_updated DEFAULT GETDATE(),
        CONSTRAINT FK_inventory_location FOREIGN KEY (location_id) REFERENCES dbo.locations(id) ON DELETE NO ACTION
    );
END;
GO

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
GO

PRINT 'Schema created successfully.';
GO
