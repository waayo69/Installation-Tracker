-- ============================================================
-- OBSOLETE for fresh installs
-- ============================================================
-- All changes from this migration are now included in schema.sql:
--   - dbo.locations
--   - trucks.location_id (trucks.location string removed)
--   - technicians.role + technicians.location_id
--
-- Fresh DB:
--   1. CREATE DATABASE tank_truck_tracking;
--   2. Run schema.sql
--   3. php sql/seed_import.php
--
-- Do NOT run this script on a database created from the current schema.sql.
-- ============================================================

PRINT 'migrate_team_leaders.sql is obsolete. Use schema.sql for new databases.';
GO
